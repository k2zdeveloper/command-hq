<?php

namespace App\Controllers;

use App\Libraries\ClaudeService;
use App\Libraries\ToolDispatcher;
use App\Models\SupabaseModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * ChatApi
 * -------------------------------------------------------------
 * JSON endpoints called by the frontend `fetch` layer.
 * Pipeline:
 *   1. Resolve agent by slug
 *   2. Load agent's assigned skills
 *   3. Compose final system prompt
 *   4. Pull recent memory window from Supabase
 *   5. Append the new user message
 *   6. Call Claude
 *   7. Persist BOTH the user turn and assistant reply
 */
class ChatApi extends BaseController
{
    /** Whole org as JSON — used to redraw the chart client-side if needed. */
    public function org(): ResponseInterface
    {
        $rows = (new SupabaseModel())->getOrg();
        return $this->response->setJSON(['ok' => true, 'agents' => $rows]);
    }

    /** Recent chat turns for an agent. */
    public function history(string $slug): ResponseInterface
    {
        $supabase = new SupabaseModel();
        $agent    = $supabase->getAgentBySlug($slug);
        if (!$agent) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'agent_not_found']);
        }

        $session = $this->resolveSession($slug);
        $turns   = $supabase->getRecentTurns($agent['id'], $session, (int) (getenv('memory.windowTurns') ?: 12));

        return $this->response->setJSON([
            'ok'      => true,
            'agent'   => $agent,
            'session' => $session,
            'turns'   => $turns,
        ]);
    }

    /** Latest daily reports for an agent (5pm heartbeat output). */
    public function reports(string $slug): ResponseInterface
    {
        $supabase = new SupabaseModel();
        $agent    = $supabase->getAgentBySlug($slug);
        if (!$agent) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false]);
        }
        return $this->response->setJSON([
            'ok'      => true,
            'reports' => $supabase->getRecentReports($agent['id']),
        ]);
    }

    /** POST: send a user message, return assistant reply. */
    public function send(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $slug    = trim((string) ($json['slug']    ?? ''));
        $message = trim((string) ($json['message'] ?? ''));

        if ($slug === '' || $message === '') {
            return $this->response->setStatusCode(400)->setJSON([
                'ok'    => false,
                'error' => 'slug and message are required',
            ]);
        }

        $supabase = new SupabaseModel();
        $agent    = $supabase->getAgentBySlug($slug);
        if (!$agent) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'agent_not_found']);
        }

        $session = $this->resolveSession($slug);

        // 1) Compose system prompt: base role + skills
        $skillRows    = $supabase->getAgentSkills($agent['id']);
        $systemPrompt = $this->composeSystemPrompt($agent, $skillRows);

        // 2) Memory window from Supabase
        $windowSize = (int) (getenv('memory.windowTurns') ?: 12);
        $history    = $supabase->getRecentTurns($agent['id'], $session, $windowSize);

        // 3) Build messages array for Claude
        $messages = [];
        foreach ($history as $h) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        // 4) Call Claude
        try {
            $claude = new ClaudeService();
            $result = $claude->chat(
                $systemPrompt,
                $messages,
                (float) ($agent['temperature'] ?? 0.7),
                $agent['model'] ?? null,
            );
        } catch (Throwable $e) {
            log_message('error', 'Chat send failed: ' . $e->getMessage());
            return $this->response->setStatusCode(502)->setJSON([
                'ok'    => false,
                'error' => $e->getMessage(),
            ]);
        }

        // 5) Persist both turns
        $supabase->saveTurn($agent['id'], $session, 'user', $message);
        $supabase->saveTurn($agent['id'], $session, 'assistant', $result['text'], $result['usage']);

        return $this->response->setJSON([
            'ok'      => true,
            'reply'   => $result['text'],
            'usage'   => $result['usage'],
            'session' => $session,
        ]);
    }

    /** POST: create a new skill {name, skill_type, context}. */
    public function createSkill(): ResponseInterface
    {
        $json    = $this->request->getJSON(true) ?? [];
        $name    = trim((string) ($json['name']       ?? ''));
        $type    = trim((string) ($json['skill_type'] ?? 'context'));
        $context = trim((string) ($json['context']    ?? ''));

        if ($name === '') {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => 'name required']);
        }

        $skill = (new SupabaseModel())->createSkill($name, $type, $context);
        return $this->response->setJSON(['ok' => true, 'skill' => $skill]);
    }

    /** POST: update an existing skill {id, name, skill_type, context, is_active}. */
    public function updateSkillById(): ResponseInterface
    {
        $json     = $this->request->getJSON(true) ?? [];
        $id       = trim((string) ($json['id']         ?? ''));
        $name     = trim((string) ($json['name']       ?? ''));
        $type     = trim((string) ($json['skill_type'] ?? 'context'));
        $context  = trim((string) ($json['context']    ?? ''));
        $isActive = (bool) ($json['is_active'] ?? true);

        if ($id === '' || $name === '') {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => 'id and name required']);
        }

        (new SupabaseModel())->updateSkill($id, $name, $type, $context, $isActive);
        return $this->response->setJSON(['ok' => true]);
    }

    /** POST: delete a skill by id {id}. */
    public function deleteSkillById(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $id   = trim((string) ($json['id'] ?? ''));

        if ($id === '') {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => 'id required']);
        }

        (new SupabaseModel())->deleteSkill($id);
        return $this->response->setJSON(['ok' => true]);
    }

    /** GET: all agents for a company division with full profile data. */
    public function companyAgents(string $division): ResponseInterface
    {
        $agents = (new SupabaseModel())->getCompanyAgents($division);
        return $this->response->setJSON(['ok' => true, 'agents' => $agents]);
    }

    /** POST: update agent profile fields {id, name?, role_title?, system_prompt?, temperature?, model?, is_active?}. */
    public function updateAgent(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $id   = trim((string)($json['id'] ?? ''));
        if (!$id) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => 'id required']);
        }
        $allowed = ['name', 'role_title', 'system_prompt', 'temperature', 'model', 'is_active'];
        $data    = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $json)) {
                $data[$key] = $json[$key];
            }
        }
        if (empty($data)) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => 'no fields to update']);
        }
        (new SupabaseModel())->updateAgent($id, $data);
        return $this->response->setJSON(['ok' => true]);
    }

    public function createAgent(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        foreach (['name', 'role_title', 'division'] as $f) {
            if (empty($json[$f])) {
                return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => "$f required"]);
            }
        }
        $base = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($json['name'])));
        $slug = ($json['division'] ?? 'agent') . '-' . $base . '-' . substr(uniqid(), -4);
        $name = trim($json['name']);
        $role = trim($json['role_title']);
        $div  = trim($json['division']);
        $data = [
            'slug'          => $slug,
            'name'          => $name,
            'role_title'    => $role,
            'division'      => $div,
            'parent_id'     => $json['parent_id'] ?: null,
            'system_prompt' => $json['system_prompt'] ?: "You are {$name}, {$role} at Positive Nation LLC. Execute tasks with precision and report results clearly.",
            'model'         => $json['model'] ?? 'claude-sonnet-4-6',
            'temperature'   => (float) ($json['temperature'] ?? 0.7),
            'is_active'     => true,
        ];
        $agent = (new SupabaseModel())->createAgent($data);
        if (!$agent) {
            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'error' => 'Supabase insert failed']);
        }
        // Assign skills if provided
        if (!empty($json['skill_ids']) && is_array($json['skill_ids'])) {
            $sb = new SupabaseModel();
            foreach ($json['skill_ids'] as $skId) {
                $sb->assignSkillToAgent($agent['id'], $skId);
            }
        }
        return $this->response->setJSON(['ok' => true, 'agent' => $agent]);
    }

    /** POST: delete an agent {id}. */
    public function deleteAgent(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $id   = trim((string) ($json['id'] ?? ''));
        if ($id === '') {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => 'id required']);
        }
        $ok = (new SupabaseModel())->deleteAgent($id);
        if (!$ok) {
            return $this->response->setStatusCode(500)->setJSON(['ok' => false, 'error' => 'delete failed']);
        }
        return $this->response->setJSON(['ok' => true]);
    }

    public function agentHistory(string $slug): ResponseInterface
    {
        $supabase = new SupabaseModel();
        $agent    = $supabase->getAgentBySlug($slug);
        if (!$agent) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'not_found']);
        }
        $turns = $supabase->getAllAgentTurns($agent['id'], 50);
        return $this->response->setJSON(['ok' => true, 'agent' => $agent, 'turns' => $turns]);
    }

    /** GET: recent activity turns for an agent (for activity feed). */
    public function activity(string $slug): ResponseInterface
    {
        $supabase = new SupabaseModel();
        $agent    = $supabase->getAgentBySlug($slug);
        if (!$agent) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false]);
        }
        return $this->response->setJSON([
            'ok'       => true,
            'activity' => $supabase->getAgentActivity($agent['id']),
        ]);
    }

    /** GET: skills currently assigned to an agent. */
    public function skills(string $slug): ResponseInterface
    {
        $supabase = new SupabaseModel();
        $agent    = $supabase->getAgentBySlug($slug);
        if (!$agent) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'agent_not_found']);
        }
        return $this->response->setJSON([
            'ok'     => true,
            'skills' => $supabase->getAgentSkills($agent['id']),
        ]);
    }

    /** GET: all skills available in the system. */
    public function allSkills(): ResponseInterface
    {
        return $this->response->setJSON([
            'ok'     => true,
            'skills' => (new SupabaseModel())->getAllSkills(),
        ]);
    }

    /** POST: assign a skill to an agent {slug, skill_id, priority?}. */
    public function assignSkill(): ResponseInterface
    {
        $json    = $this->request->getJSON(true) ?? [];
        $slug    = trim((string) ($json['slug']     ?? ''));
        $skillId = trim((string) ($json['skill_id'] ?? ''));
        $priority = (int) ($json['priority'] ?? 100);

        if ($slug === '' || $skillId === '') {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => 'slug and skill_id required']);
        }

        $supabase = new SupabaseModel();
        $agent    = $supabase->getAgentBySlug($slug);
        if (!$agent) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'agent_not_found']);
        }

        $supabase->assignSkillToAgent($agent['id'], $skillId, $priority);
        return $this->response->setJSON(['ok' => true]);
    }

    /** POST: remove a skill from an agent {slug, skill_id}. */
    public function removeSkill(): ResponseInterface
    {
        $json    = $this->request->getJSON(true) ?? [];
        $slug    = trim((string) ($json['slug']     ?? ''));
        $skillId = trim((string) ($json['skill_id'] ?? ''));

        if ($slug === '' || $skillId === '') {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => 'slug and skill_id required']);
        }

        $supabase = new SupabaseModel();
        $agent    = $supabase->getAgentBySlug($slug);
        if (!$agent) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'agent_not_found']);
        }

        $supabase->removeSkillFromAgent($agent['id'], $skillId);
        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * SSE streaming endpoint — emits text tokens as they arrive from Claude.
     * Saves turns to Supabase after the stream completes.
     */
    public function stream(): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        $json    = $this->request->getJSON(true) ?? [];
        $slug    = trim((string) ($json['slug']    ?? ''));
        $message = trim((string) ($json['message'] ?? ''));

        if ($slug === '' || $message === '') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'slug and message are required']);
            exit;
        }

        $supabase = new SupabaseModel();
        $agent    = $supabase->getAgentBySlug($slug);
        if (!$agent) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'agent_not_found']);
            exit;
        }

        $session      = $this->resolveSession($slug);
        $skillRows    = $supabase->getAgentSkills($agent['id']);
        $division     = $agent['division'] ?? 'positive_nation';
        $rosterAgents = $supabase->getCompanyAgents($division);
        $memory       = $supabase->getMemory($agent['id'], $session);          // long-term summary
        $systemPrompt = $this->composeSystemPrompt($agent, $skillRows, $rosterAgents, $memory['summary'] ?? '');
        $windowSize   = (int) (getenv('memory.windowTurns') ?: 12);
        $history      = $supabase->getRecentTurns($agent['id'], $session, $windowSize);

        $messages = [];
        foreach ($history as $h) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');

        $sse = static function (array $data): void {
            echo 'data: ' . json_encode($data) . "\n\n";
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        };

        $sse(['type' => 'start']);

        try {
            $claude = new ClaudeService();
            $result = $claude->chatStream(
                $systemPrompt,
                $messages,
                (float) ($agent['temperature'] ?? 0.7),
                $agent['model'] ?? null,
                function (string $chunk) use ($sse): void {
                    $sse(['type' => 'chunk', 'text' => $chunk]);
                }
            );
        } catch (\Throwable $e) {
            log_message('error', 'Stream failed: ' . $e->getMessage());
            $sse(['type' => 'error', 'error' => $e->getMessage()]);
            exit;
        }

        $supabase->saveTurn($agent['id'], $session, 'user', $message);
        $supabase->saveTurn($agent['id'], $session, 'assistant', $result['text'], $result['usage']);

        // ── Execute any tool calls found in the response ─────────────────
        $dispatcher = new ToolDispatcher($division, $agent['id']);
        if ($dispatcher->hasTools($result['text'])) {
            $toolResults = $dispatcher->executeAll($result['text']);
            if ($toolResults) {
                $sse(['type' => 'tool_result', 'results' => $toolResults]);
            }
        }

        $sse(['type' => 'done', 'session' => $session]);

        // Long-term memory: fold older turns into a compact summary (runs
        // after the reply is delivered; only refreshes occasionally).
        $this->maybeSummarize($supabase, $agent, $session);

        exit;
    }

    // ---- helpers ----------------------------------------------------------

    /**
     * Tier-2 memory: when a conversation grows past the recent window,
     * summarize the older turns into a short "memory note" so the agent
     * remembers the gist without re-sending thousands of tokens.
     * Cheap: uses Haiku, and only refreshes every ~8 new turns.
     */
    private function maybeSummarize(SupabaseModel $sb, array $agent, string $session): void
    {
        $window = (int) (getenv('memory.windowTurns') ?: 12);

        // Pull a generous slice of history (oldest → newest)
        $turns = $sb->getRecentTurns($agent['id'], $session, 80);
        $total = count($turns);
        if ($total <= $window + 4) {
            return; // conversation still short — nothing to summarize
        }

        // The turns that have "fallen out" of the recent window
        $older = array_slice($turns, 0, $total - $window);

        $mem     = $sb->getMemory($agent['id'], $session);
        $covered = (int) ($mem['turns_covered'] ?? 0);

        // Only refresh once ~8 new turns have rolled out of the window
        if (count($older) - $covered < 8) {
            return;
        }

        $transcript = '';
        foreach ($older as $t) {
            $transcript .= strtoupper($t['role']) . ': ' . $t['content'] . "\n";
        }
        $prev = $mem['summary'] ?? '';

        $sysPrompt = "You compress conversation history into a concise memory note for an AI agent. "
            . "Capture key facts, decisions, assigned tasks, names, numbers, and unresolved threads. "
            . "Keep it under 200 words. Output ONLY the note, no preamble.";
        $userMsg = ($prev !== '' ? "Existing memory note:\n{$prev}\n\n" : '')
            . "Conversation to fold into the memory:\n{$transcript}\n\nProduce the updated memory note.";

        try {
            $claude  = new ClaudeService();
            $summary = $claude->chat(
                $sysPrompt,
                [['role' => 'user', 'content' => $userMsg]],
                0.3,
                'claude-haiku-4-5-20251001' // cheap, fast model for summarization
            );
            $sb->saveMemory($agent['id'], $session, trim($summary['text']), count($older));
            log_message('info', "Memory summarized for agent {$agent['id']} ({$session}): " . count($older) . ' turns');
        } catch (\Throwable $e) {
            log_message('warning', 'Memory summarize failed: ' . $e->getMessage());
        }
    }

    /**
     * Compose the final system prompt: base role + agent roster (for delegation)
     * + any assigned skill context blocks.
     */
    private function composeSystemPrompt(array $agent, array $skillRows, array $rosterAgents = [], string $memorySummary = ''): string
    {
        $parts   = [];
        $parts[] = $agent['system_prompt'] ?? '';

        // Long-term memory: gist of older messages not included in the recent window
        if (trim($memorySummary) !== '') {
            $parts[] = "\n\n---\n## CONVERSATION MEMORY\n"
                . "(Summary of earlier messages in this conversation that are no longer shown in full. "
                . "Treat it as background you already know.)\n"
                . $memorySummary;
        }

        // Inject roster so the CEO (or any director) knows exactly who to delegate to
        if (!empty($rosterAgents)) {
            $lines = [];
            foreach ($rosterAgents as $a) {
                if ($a['id'] === $agent['id'] || empty($a['is_active'])) {
                    continue;
                }
                $lines[] = "• {$a['name']} — {$a['role_title']}";
            }
            if ($lines) {
                $roster  = "\n\n---\n## YOUR AGENT TEAM\n";
                $roster .= implode("\n", $lines);
                $roster .= "\n\nDelegate by exact agent name using the [CREATE_TASK] or [HIRE_AGENT] tools below.\n";
                $parts[] = $roster;
            }
        }

        // Response style — keep agents concise and action-oriented
        $parts[] = "\n\n---\n## RESPONSE STYLE — BE BRIEF\n"
            . "- Lead with the answer or action in ONE short line. No long preambles or strategic commentary.\n"
            . "- Keep replies scannable: 3-6 short bullets max. Avoid walls of text and big tables unless explicitly asked.\n"
            . "- When you use a tool, write 1 line before it, then let the tool result speak. Don't restate it after.\n"
            . "- To hire: confirm the role in ONE line, then call [HIRE_AGENT]. Do NOT write a full job description, salary, or interview questions unless the user explicitly asks for a 'hiring package'.\n"
            . "- Never repeat information already shown. Finish your tool calls — never leave one half-written.";

        $skillBlocks = [];
        foreach ($skillRows as $row) {
            $skill = $row['skill'] ?? null;
            if (!$skill || empty($skill['is_active'])) {
                continue;
            }
            $context = $skill['payload']['context'] ?? null;
            if ($context) {
                $skillBlocks[] = "### Skill: {$skill['name']}\n{$context}";
            }
        }

        if ($skillBlocks) {
            $parts[] = "\n\n---\nADDITIONAL CONTEXT GRANTED TO THIS AGENT:\n" . implode("\n\n", $skillBlocks);
        }

        // Inject tool documentation — tools are executed immediately in this session
        $parts[] = "\n\n---\n## AVAILABLE TOOLS — EXECUTE REAL ACTIONS\n"
            . "You have access to tools that perform real actions. Use them directly — do NOT say you cannot send emails or search the web.\n\n"
            . "### Send Email\n"
            . "[SEND_EMAIL]\n"
            . "to: recipient@email.com\n"
            . "subject: Subject line\n"
            . "body:\n"
            . "Email body here. Can be multiple lines.\n"
            . "[/SEND_EMAIL]\n\n"
            . "### Generate Image\n"
            . "You CAN create real images. Never say you cannot generate images — use this tool. Write a vivid, detailed prompt.\n"
            . "[GENERATE_IMAGE]\n"
            . "prompt: detailed description of the image\n"
            . "size: 1024x1024\n"
            . "[/GENERATE_IMAGE]\n\n"
            . "### Generate Document (saved to Files, printable to PDF)\n"
            . "[GENERATE_DOC]\n"
            . "title: Document Title\n"
            . "content:\n"
            . "# Heading\\nFull document body in markdown...\n"
            . "[/GENERATE_DOC]\n\n"
            . "### Web Search (current information)\n"
            . "[SEARCH]\n"
            . "query: what you want to search\n"
            . "[/SEARCH]\n\n"
            . "### Assign Task to a Specific Agent\n"
            . "[CREATE_TASK]\n"
            . "agent: Agent Name\n"
            . "title: Task title\n"
            . "priority: high\n"
            . "description:\n"
            . "What the agent should do.\n"
            . "[/CREATE_TASK]\n\n"
            . "### Hire a New Agent (you CAN expand the team)\n"
            . "[HIRE_AGENT]\n"
            . "name: New Agent Name\n"
            . "role: Their Role Title\n"
            . "reports_to: Manager Agent Name\n"
            . "prompt: System prompt describing their duties\n"
            . "[/HIRE_AGENT]\n\n"
            . "### Fire (deactivate) an Agent — reversible\n"
            . "[FIRE_AGENT]\n"
            . "agent: Agent Name\n"
            . "[/FIRE_AGENT]\n\n"
            . "**Rules:** Use tools when the user asks for real actions. Tools execute immediately and results are shown to the user. "
            . "Never say 'I cannot send emails' — use [SEND_EMAIL] instead. "
            . "Always confirm what you did after a tool runs.";

        return implode("\n", $parts);
    }

    /**
     * One continuous conversation thread per agent.
     *
     * Previously this was tied to the PHP/browser session, so the ID reset
     * each day (or when the browser closed) and agents appeared to "forget"
     * past conversations — even though every turn was stored. Now it's STABLE
     * per agent: every chat with an agent is one ongoing thread across days
     * and devices. The Tier-2 summarizer (maybeSummarize) keeps it bounded,
     * so an eternal thread stays cheap on tokens.
     */
    private function resolveSession(string $slug): string
    {
        return 'main:' . $slug;
    }
}
