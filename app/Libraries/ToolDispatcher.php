<?php

namespace App\Libraries;

use App\Models\SupabaseModel;

/**
 * ToolDispatcher
 * ─────────────────────────────────────────────────────────────
 * Parses agent output for tool call blocks and executes them.
 *
 * Supported tools and their format:
 *
 * ── Web Search ──────────────────────────────────
 * [SEARCH]
 * query: what you want to search for
 * [/SEARCH]
 *
 * ── Send Email ──────────────────────────────────
 * [SEND_EMAIL]
 * to: recipient@email.com
 * subject: Email Subject Line
 * body:
 * Your email body here.
 * Can span multiple lines.
 * [/SEND_EMAIL]
 *
 * ── Create Sub-task for Another Agent ───────────
 * [CREATE_TASK]
 * agent: Agent Name
 * title: Task title here
 * priority: high
 * description:
 * Detailed description of what the agent should do.
 * [/CREATE_TASK]
 */
class ToolDispatcher
{
    private SearchService $search;
    private EmailService  $email;
    private ImageService  $image;
    private SupabaseModel $supabase;
    private string        $companyId;
    private ?string       $agentId;

    public function __construct(string $companyId = '', ?string $agentId = null)
    {
        $this->search    = new SearchService();
        $this->email     = new EmailService();
        $this->image     = new ImageService();
        $this->supabase  = new SupabaseModel();
        $this->companyId = $companyId;
        $this->agentId   = $agentId;
    }

    /** Save a produced file into the artifacts catalogue (graceful if table missing). */
    private function recordArtifact(string $type, string $title, string $fileUrl, ?string $mime = null): void
    {
        if (empty($this->companyId)) return;
        try {
            $this->supabase->createArtifact([
                'company_id' => $this->companyId,
                'agent_id'   => $this->agentId,
                'type'       => $type,
                'title'      => $title,
                'file_url'   => $fileUrl,
                'mime'       => $mime,
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'recordArtifact failed: ' . $e->getMessage());
        }
    }

    /**
     * Returns true if the agent response contains any tool call blocks.
     */
    public function hasTools(string $text): bool
    {
        return (bool) preg_match('/\[(SEARCH|SEND_EMAIL|CREATE_TASK|GENERATE_IMAGE|GENERATE_DOC|HIRE_AGENT|FIRE_AGENT)\]/i', $text);
    }

    /**
     * Find all tool calls in $text, execute them, and return
     * a formatted string of results to feed back to Claude.
     */
    public function executeAll(string $text): string
    {
        $results = [];

        // ── [SEARCH] ... [/SEARCH] ───────────────────────────────────
        if (preg_match_all('/\[SEARCH\](.*?)\[\/SEARCH\]/si', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $query = $this->field($block, 'query');
                if (empty($query)) {
                    continue;
                }
                log_message('info', "ToolDispatcher: SEARCH — {$query}");
                $result    = $this->search->search($query);
                $results[] = $this->wrapResult('SEARCH', "Query: {$query}\n\n{$result}");
            }
        }

        // ── [SEND_EMAIL] ... [/SEND_EMAIL] ───────────────────────────
        if (preg_match_all('/\[SEND_EMAIL\](.*?)\[\/SEND_EMAIL\]/si', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $to      = $this->field($block, 'to');
                $subject = $this->field($block, 'subject');
                $body    = $this->field($block, 'body');

                if (empty($to) || empty($subject)) {
                    $results[] = $this->wrapResult('SEND_EMAIL', '⚠ Skipped — "to" and "subject" are required fields.');
                    continue;
                }

                log_message('info', "ToolDispatcher: SEND_EMAIL → {$to} | {$subject}");
                $result    = $this->email->send($to, $subject, $body ?? '');
                $results[] = $this->wrapResult('SEND_EMAIL', $result);
            }
        }

        // ── [GENERATE_IMAGE] ... [/GENERATE_IMAGE] ───────────────────
        if (preg_match_all('/\[GENERATE_IMAGE\](.*?)\[\/GENERATE_IMAGE\]/si', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $prompt = $this->field($block, 'prompt');
                $size   = $this->field($block, 'size') ?? '1024x1024';
                if (empty($prompt)) {
                    $results[] = $this->wrapResult('GENERATE_IMAGE', '⚠ Skipped — "prompt" is required.');
                    continue;
                }
                log_message('info', "ToolDispatcher: GENERATE_IMAGE — {$prompt}");
                $result    = $this->image->generate($prompt, $size);
                // Catalogue the image if it succeeded
                if (preg_match('/IMAGE_URL:\s*(\S+)/', $result, $um)) {
                    $title = mb_substr(trim($prompt), 0, 80);
                    $this->recordArtifact('image', $title, $um[1], 'image/jpeg');
                }
                $results[] = $this->wrapResult('GENERATE_IMAGE', $result);
            }
        }

        // ── [GENERATE_DOC] ... [/GENERATE_DOC] ───────────────────────
        if (preg_match_all('/\[GENERATE_DOC\](.*?)\[\/GENERATE_DOC\]/si', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $title   = $this->field($block, 'title');
                $content = $this->field($block, 'content');
                if (empty($title) || empty($content)) {
                    $results[] = $this->wrapResult('GENERATE_DOC', '⚠ Skipped — "title" and "content" are required.');
                    continue;
                }
                $result    = $this->saveDocument($title, $content);
                $results[] = $this->wrapResult('GENERATE_DOC', $result);
            }
        }

        // ── [HIRE_AGENT] ... [/HIRE_AGENT] ───────────────────────────
        if (preg_match_all('/\[HIRE_AGENT\](.*?)\[\/HIRE_AGENT\]/si', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $name = $this->field($block, 'name');
                $role = $this->field($block, 'role');
                if (empty($name) || empty($role)) {
                    $results[] = $this->wrapResult('HIRE_AGENT', '⚠ Skipped — "name" and "role" are required.');
                    continue;
                }
                $reportsTo = $this->field($block, 'reports_to');
                $prompt    = $this->field($block, 'prompt');
                $result    = $this->hireAgent($name, $role, $reportsTo, $prompt);
                $results[] = $this->wrapResult('HIRE_AGENT', $result);
            }
        }

        // ── [FIRE_AGENT] ... [/FIRE_AGENT] ───────────────────────────
        if (preg_match_all('/\[FIRE_AGENT\](.*?)\[\/FIRE_AGENT\]/si', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $agentName = $this->field($block, 'agent');
                if (empty($agentName)) {
                    $results[] = $this->wrapResult('FIRE_AGENT', '⚠ Skipped — "agent" name is required.');
                    continue;
                }
                $result    = $this->fireAgent($agentName);
                $results[] = $this->wrapResult('FIRE_AGENT', $result);
            }
        }

        // ── [CREATE_TASK] ... [/CREATE_TASK] ─────────────────────────
        if (preg_match_all('/\[CREATE_TASK\](.*?)\[\/CREATE_TASK\]/si', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $agentName   = $this->field($block, 'agent');
                $title       = $this->field($block, 'title');
                $priority    = $this->field($block, 'priority') ?? 'medium';
                $description = $this->field($block, 'description') ?? '';

                if (empty($title)) {
                    $results[] = $this->wrapResult('CREATE_TASK', '⚠ Skipped — "title" is required.');
                    continue;
                }

                $result    = $this->createSubTask($agentName, $title, $description, $priority);
                $results[] = $this->wrapResult('CREATE_TASK', $result);
            }
        }

        if (empty($results)) {
            return '';
        }

        $summary  = count($results) . ' tool' . (count($results) > 1 ? 's' : '') . ' executed.';
        $combined = implode("\n\n", $results);

        return "══ TOOL RESULTS ══\n\n{$combined}\n\n══ END OF TOOL RESULTS ══\n\n"
            . "{$summary} Please review the results above and continue your response. "
            . "If your task is complete, end with:\nSTATUS: done | [brief summary of what was accomplished]";
    }

    // ── Tool: Create Sub-task ────────────────────────────────────────────

    private function createSubTask(
        ?string $agentName,
        string  $title,
        string  $description,
        string  $priority
    ): string {
        if (empty($this->companyId)) {
            return "⚠ Cannot create task — company ID not available.";
        }

        // Resolve agent by name if provided
        $agentId = null;
        if (!empty($agentName)) {
            $agents = $this->supabase->getCompanyAgents($this->companyId);
            foreach ($agents as $a) {
                if (stripos($a['name'], $agentName) !== false) {
                    $agentId = $a['id'];
                    break;
                }
            }
        }

        $validPriorities = ['low', 'medium', 'high', 'critical'];
        if (!in_array($priority, $validPriorities)) {
            $priority = 'medium';
        }

        $task = $this->supabase->createTask([
            'company_id'  => $this->companyId,
            'agent_id'    => $agentId,
            'title'       => $title,
            'description' => $description,
            'priority'    => $priority,
            'status'      => 'pending',
        ]);

        if (!$task) {
            $err = $this->supabase->getLastError();
            if (stripos($err, 'does not exist') !== false || stripos($err, 'relation') !== false) {
                return "⚠ Failed — the 'tasks' table does not exist yet. Run database/tasks_migration.sql in Supabase first.";
            }
            return "⚠ Failed to create task in database." . ($err ? " Reason: {$err}" : '');
        }

        $assignedTo = $agentId ? ($agentName ?? 'resolved agent') : 'unassigned (no matching agent found)';
        return "✓ Task created successfully\n  Title: {$title}\n  Assigned to: {$assignedTo}\n  Priority: {$priority}\n  Status: pending (will be picked up by runner)";
    }

    // ── Tool: Generate Document (saved as printable HTML) ────────────────

    private function saveDocument(string $title, string $content): string
    {
        $dir = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'generated';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!is_dir($dir) || !is_writable($dir)) {
            return "⚠ Cannot save document — public/generated/ is not writable.";
        }

        $html = $this->documentHtml($title, $content);
        $name = 'doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.html';
        $path = $dir . DIRECTORY_SEPARATOR . $name;

        if (file_put_contents($path, $html) === false) {
            return "⚠ Failed to write document file.";
        }

        $url = '/generated/' . $name;
        $this->recordArtifact('document', mb_substr($title, 0, 120), $url, 'text/html');

        return "✓ Document saved\nFILE_URL: {$url}\nTitle: {$title}\n(Open it, then Print → Save as PDF for a PDF copy.)";
    }

    /** Wrap markdown-ish content in a clean, printable HTML document. */
    private function documentHtml(string $title, string $raw): string
    {
        $b = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
        $b = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $b);
        $b = preg_replace('/^## (.+)$/m',  '<h2>$1</h2>', $b);
        $b = preg_replace('/^# (.+)$/m',   '<h1>$1</h1>', $b);
        $b = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $b);
        $b = preg_replace('/^[•\-\*] (.+)$/m', '<li>$1</li>', $b);
        $b = preg_replace('/^---+$/m', '<hr>', $b);
        $b = preg_replace('/\n{2,}/', '</p><p>', $b);
        $b = str_replace("\n", '<br>', $b);
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $safeTitle . '</title>'
            . '<style>body{font-family:Georgia,"Times New Roman",serif;max-width:760px;margin:40px auto;'
            . 'padding:0 24px;color:#222;line-height:1.65;}h1{font-size:28px;border-bottom:2px solid #c08818;padding-bottom:8px;}'
            . 'h2{font-size:21px;color:#8a5a10;margin-top:26px;}h3{font-size:16px;color:#444;}'
            . 'li{margin:4px 0;}hr{border:none;border-top:1px solid #ddd;margin:18px 0;}'
            . '.brand{font-family:Arial,sans-serif;font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:#b08828;margin-bottom:18px;}'
            . '@media print{body{margin:0;}}</style></head><body>'
            . '<div class="brand">Mosbat AI &middot; ' . $safeTitle . '</div>'
            . '<p>' . $b . '</p>'
            . '<hr><p style="font-size:11px;color:#999;font-family:Arial,sans-serif;">Generated by Mosbat AI &middot; '
            . date('F j, Y') . '</p></body></html>';
    }

    // ── Tool: Hire Agent ─────────────────────────────────────────────────

    private function hireAgent(string $name, string $role, ?string $reportsTo, ?string $prompt): string
    {
        if (empty($this->companyId)) {
            return "⚠ Cannot hire — company context not available.";
        }

        // Resolve reports_to (manager) by name → parent_id
        $parentId = null;
        $roster   = $this->supabase->getCompanyAgents($this->companyId);
        if (!empty($reportsTo)) {
            foreach ($roster as $a) {
                if (stripos($a['name'], $reportsTo) !== false) {
                    $parentId = $a['id'];
                    break;
                }
            }
        }

        // Avoid duplicate names
        foreach ($roster as $a) {
            if (strcasecmp(trim($a['name']), trim($name)) === 0) {
                return "⚠ An agent named \"{$name}\" already exists. Choose a different name.";
            }
        }

        $base = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($name)));
        $slug = $this->companyId . '-' . $base . '-' . substr(uniqid(), -4);

        $agent = $this->supabase->createAgent([
            'slug'          => $slug,
            'name'          => trim($name),
            'role_title'    => trim($role),
            'division'      => $this->companyId,
            'parent_id'     => $parentId,
            'system_prompt' => $prompt ?: "You are {$name}, the {$role}. Execute your responsibilities with precision and report results clearly to your manager.",
            'model'         => 'claude-sonnet-4-6',
            'temperature'   => 0.7,
            'is_active'     => true,
        ]);

        if (!$agent) {
            $err = $this->supabase->getLastError();
            return "⚠ Failed to hire agent." . ($err ? " Reason: {$err}" : '');
        }

        $mgr = $parentId ? " reporting to {$reportsTo}" : " (standalone)";
        return "✓ Agent hired successfully\n  Name: {$name}\n  Role: {$role}{$mgr}\n  Status: active (visible after page refresh)";
    }

    // ── Tool: Fire Agent (deactivate — reversible) ───────────────────────

    private function fireAgent(string $agentName): string
    {
        if (empty($this->companyId)) {
            return "⚠ Cannot fire — company context not available.";
        }

        $roster = $this->supabase->getCompanyAgents($this->companyId);
        $target = null;
        foreach ($roster as $a) {
            if (stripos($a['name'], $agentName) !== false) {
                $target = $a;
                break;
            }
        }

        if (!$target) {
            return "⚠ No agent found matching \"{$agentName}\".";
        }

        // Protect the top director (no parent) from being fired by an agent
        if (empty($target['parent_id'])) {
            return "⚠ {$target['name']} is the company director and cannot be fired by an agent. Use the dashboard to remove it.";
        }

        $this->supabase->updateAgent($target['id'], ['is_active' => false]);
        return "✓ {$target['name']} has been deactivated (fired). This is reversible from the agent's Instructions tab.";
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Extract a named field from a tool block.
     *
     * Handles two formats:
     *   field: single line value
     *
     *   field:
     *   multi
     *   line value
     */
    private function field(string $block, string $name): ?string
    {
        // Multi-line: field name on its own line, content follows until next field or end
        $pattern = "/^{$name}:\s*\n(.*?)(?=\n\s*\w[\w\s]*:\s*\n|\n\s*\w[\w\s]*:\s+\S|$)/si";
        if (preg_match($pattern, trim($block), $m)) {
            $value = trim($m[1]);
            if ($value !== '') {
                return $value;
            }
        }

        // Single-line: field: value on same line
        if (preg_match("/^{$name}:\s*(.+)$/mi", $block, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function wrapResult(string $tool, string $content): string
    {
        return "[TOOL_RESULT: {$tool}]\n{$content}\n[/TOOL_RESULT]";
    }

    // ────────────────────────────────────────────────────────────────────

    /**
     * Return the tool usage documentation to inject into agent system prompts.
     * Called by AgentRun when building the system prompt.
     */
    public static function toolDocs(): string
    {
        return <<<'DOCS'


---
## AVAILABLE TOOLS

You have access to the following tools. Use them to complete your task with real, current information.

### 🔍 Web Search
Search Google for current information, research, pricing, news, contacts.

[SEARCH]
query: your search query here
[/SEARCH]

### 📧 Send Email
Send an actual email to any address. Useful for reports, notifications, outreach.

[SEND_EMAIL]
to: recipient@email.com
subject: Subject Line Here
body:
Email body here.
Can be multiple paragraphs.
Supports **bold** and # headers.
[/SEND_EMAIL]

### 🎨 Generate Image
Create a real image from a text description. The image is generated and shown to the user.
You CAN generate images — never say you cannot. Write a vivid, detailed prompt.

[GENERATE_IMAGE]
prompt: A detailed description of the image to create
size: 1024x1024
[/GENERATE_IMAGE]

### 📄 Generate Document (saved as a downloadable file)
Produce a report, proposal, or document. It is saved to the Files library and can be printed to PDF.

[GENERATE_DOC]
title: Q2 Marketing Report
content:
# Q2 Marketing Report
## Summary
...full document body in markdown...
[/GENERATE_DOC]

### 📋 Create Task for Another Agent
Delegate work to a specific agent. They will process it autonomously.

[CREATE_TASK]
agent: Agent Name
title: What the agent should do
priority: high
description:
Detailed instructions for the agent.
Be specific about the expected output.
[/CREATE_TASK]

### 🧑‍💼 Hire a New Agent
Expand the team. You CAN hire — create a real new agent for the company.

[HIRE_AGENT]
name: New Agent Name
role: Their Role Title
reports_to: Manager Agent Name
prompt: Their system prompt describing duties and behavior
[/HIRE_AGENT]

### 🚫 Fire (Deactivate) an Agent
Deactivate an underperforming or redundant agent. This is reversible.

[FIRE_AGENT]
agent: Agent Name
[/FIRE_AGENT]

### Tool Rules
- Use [SEARCH] before stating any current facts, prices, or statistics
- You can use multiple tools in a single response
- After tool results are shown, continue your analysis using that information
- When fully done, end your response with exactly:
  STATUS: done | [one sentence summary of what was accomplished]
DOCS;
    }
}
