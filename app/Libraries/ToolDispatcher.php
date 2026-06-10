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
    private SearchService    $search;
    private EmailService     $email;
    private ImageService     $image;
    private SiteCheckService $siteCheck;
    private FacebookService  $facebook;
    private SupabaseModel    $supabase;
    private string           $companyId;
    private ?string          $agentId;

    public function __construct(string $companyId = '', ?string $agentId = null)
    {
        $this->search    = new SearchService();
        $this->email     = new EmailService();
        $this->image     = new ImageService();
        $this->siteCheck = new SiteCheckService();
        $this->facebook  = new FacebookService();
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
        return (bool) preg_match('/\[(SEARCH|SEARCH_PERSON|SEND_EMAIL|CREATE_TASK|GENERATE_IMAGE|GENERATE_DOC|GENERATE_FILE|GENERATE_PPTX|GENERATE_DOCX|GENERATE_XLSX|HIRE_AGENT|FIRE_AGENT|CHECK_SITE|POST_FACEBOOK|COMPANY_REPORT)\]/i', $text);
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

        // ── [SEARCH_PERSON] ... [/SEARCH_PERSON] ─────────────────────
        if (preg_match_all('/\[SEARCH_PERSON\](.*?)\[\/SEARCH_PERSON\]/si', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $name    = $this->field($block, 'name');
                $context = $this->field($block, 'context') ?? '';
                if (empty($name)) {
                    $results[] = $this->wrapResult('SEARCH_PERSON', '⚠ Skipped — "name" is required.');
                    continue;
                }
                log_message('info', "ToolDispatcher: SEARCH_PERSON — {$name}");
                $result    = $this->search->searchPerson($name, $context);
                $results[] = $this->wrapResult('SEARCH_PERSON', $result);
            }
        }

        // ── [CHECK_SITE] ... [/CHECK_SITE] ───────────────────────────
        if (preg_match_all('/\[CHECK_SITE\](.*?)\[\/CHECK_SITE\]/si', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $url = $this->field($block, 'url');
                if (empty($url)) {
                    $results[] = $this->wrapResult('CHECK_SITE', '⚠ Skipped — "url" is required.');
                    continue;
                }
                // scope: "site" → discover & check every page via sitemap.xml.
                $scope = strtolower((string) ($this->field($block, 'scope') ?? 'page'));
                log_message('info', "ToolDispatcher: CHECK_SITE — {$url} (scope={$scope})");
                $result    = $this->siteCheck->check($url, $scope);
                $results[] = $this->wrapResult('CHECK_SITE', $result);
            }
        }

        // ── [COMPANY_REPORT] ... [/COMPANY_REPORT] ───────────────────
        if (preg_match_all('/\[COMPANY_REPORT\](.*?)\[\/COMPANY_REPORT\]/si', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $focus = strtolower((string) ($this->field($block, 'focus') ?? 'all'));
                log_message('info', "ToolDispatcher: COMPANY_REPORT (focus={$focus})");
                $results[] = $this->wrapResult('COMPANY_REPORT', $this->companyReport($focus));
            }
        }

        // ── [POST_FACEBOOK] ... [/POST_FACEBOOK] ─────────────────────
        if (preg_match_all('/\[POST_FACEBOOK\](.*?)\[\/POST_FACEBOOK\]/si', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $message     = $this->field($block, 'message');
                $image       = $this->field($block, 'image');        // optional public URL or local path
                $imagePrompt = $this->field($block, 'image_prompt'); // optional — generate the image server-side

                // If the agent wants an image but only gave a prompt, generate it
                // HERE and use the REAL url. (In single-pass chat the agent can't
                // know the generated URL ahead of time, so it must not guess it.)
                if (empty($image) && !empty($imagePrompt)) {
                    log_message('info', "ToolDispatcher: POST_FACEBOOK generating image — {$imagePrompt}");
                    $gen = $this->image->generate($imagePrompt, '1024x1024');
                    if (preg_match('/IMAGE_URL:\s*(\S+)/', $gen, $im)) {
                        $image = trim($im[1]);
                        if ($this->companyId) {
                            $this->recordArtifact('image', mb_substr($imagePrompt, 0, 80), $image, 'image/png');
                        }
                    } else {
                        $results[] = $this->wrapResult('POST_FACEBOOK', '⚠ Image generation failed — ' . $gen);
                        continue;
                    }
                }

                if (empty($message) && empty($image)) {
                    $results[] = $this->wrapResult('POST_FACEBOOK', '⚠ Skipped — "message", "image", or "image_prompt" is required.');
                    continue;
                }
                if (!$this->facebook->isConfigured()) {
                    $results[] = $this->wrapResult('POST_FACEBOOK',
                        '⚠ Facebook not connected — set facebook.pageId and facebook.pageAccessToken in .env.');
                    continue;
                }
                log_message('info', 'ToolDispatcher: POST_FACEBOOK' . ($image ? ' (with image)' : ''));
                $res = $image
                    ? $this->facebook->postPhoto($image, (string) $message)
                    : $this->facebook->postText((string) $message);

                if ($res['ok'] ?? false) {
                    $id  = $res['post_id'] ?? $res['id'] ?? '';
                    $msg = "✓ Posted to Facebook Page successfully." . ($id ? "\n  Post ID: {$id}" : '');
                    $this->recordArtifact('facebook_post', mb_substr((string) $message, 0, 80), $id, 'text/plain');
                } else {
                    $msg = '⚠ Facebook post failed — ' . ($res['error'] ?? 'unknown error');
                }
                $results[] = $this->wrapResult('POST_FACEBOOK', $msg);
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
                $title = $this->field($block, 'title');
                // Same greedy extraction as GENERATE_FILE — content may contain
                // CSS/code lines that break the lookahead-based field() parser.
                $content = null;
                if (preg_match('/^content:\s*\n([\s\S]*)/mi', $block, $cm)) {
                    $content = $cm[1];
                } elseif (preg_match('/^content:\s*(.+)/mi', $block, $cm)) {
                    $content = trim($cm[1]);
                }
                if (empty($title) || empty($content)) {
                    $results[] = $this->wrapResult('GENERATE_DOC', '⚠ Skipped — "title" and "content" are required.');
                    continue;
                }
                $result    = $this->saveDocument($title, $content);
                $results[] = $this->wrapResult('GENERATE_DOC', $result);
            }
        }

        // ── [GENERATE_FILE] ... [/GENERATE_FILE] ─────────────────────
        if (preg_match_all('/\[GENERATE_FILE\](.*?)\[\/GENERATE_FILE\]/si', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $filename = $this->field($block, 'filename');
                // Cannot use field() for content — CSS/JS lines like "color: red"
                // match the lookahead and prematurely terminate multi-line extraction.
                // Grab everything from "content:\n" to end of block instead.
                $content = null;
                if (preg_match('/^content:\s*\n([\s\S]*)/mi', $block, $cm)) {
                    $content = $cm[1];
                } elseif (preg_match('/^content:\s*(.+)/mi', $block, $cm)) {
                    $content = trim($cm[1]);
                }
                if (empty($filename) || $content === null || trim($content) === '') {
                    $results[] = $this->wrapResult('GENERATE_FILE', '⚠ Skipped — "filename" and "content" are required.');
                    continue;
                }
                log_message('info', "ToolDispatcher: GENERATE_FILE — {$filename}");
                $result    = $this->saveFile($filename, $content);
                $results[] = $this->wrapResult('GENERATE_FILE', $result);
            }
        }

        // ── [GENERATE_PPTX] ... [/GENERATE_PPTX] ────────────────────────────
        if (preg_match_all('/\[GENERATE_PPTX\](.*?)\[\/GENERATE_PPTX\]/si', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $filename = $this->field($block, 'filename') ?? 'presentation.pptx';
                $theme    = $this->field($block, 'theme') ?? 'dark';

                // Parse slides — each slide is delimited by a line containing only ---
                $rawSections = preg_split('/^\s*---\s*$/m', $block);
                $slides = [];
                foreach ($rawSections as $rawSlide) {
                    $rawSlide = trim($rawSlide);
                    if ($rawSlide === '') continue;
                    // Skip the header section (contains filename/theme, has no title: line)
                    if (!preg_match('/^title:\s*/mi', $rawSlide)) continue;

                    $slideTitle    = $this->field($rawSlide, 'title') ?? '';
                    $slideSubtitle = $this->field($rawSlide, 'subtitle');
                    $slideType     = $this->field($rawSlide, 'type') ?? 'content';
                    $slideContent  = null;
                    if (preg_match('/^content:\s*\n([\s\S]*)/mi', $rawSlide, $cm)) {
                        $slideContent = trim($cm[1]);
                    } elseif (preg_match('/^content:\s*(.+)/mi', $rawSlide, $cm)) {
                        $slideContent = trim($cm[1]);
                    }

                    $slides[] = [
                        'title'    => $slideTitle,
                        'subtitle' => $slideSubtitle,
                        'type'     => $slideType,
                        'content'  => $slideContent,
                    ];
                }

                if (empty($slides)) {
                    $results[] = $this->wrapResult('GENERATE_PPTX', '⚠ Skipped — no slides found. Use --- to separate slides and title: for each slide.');
                    continue;
                }

                log_message('info', "ToolDispatcher: GENERATE_PPTX — {$filename} (" . count($slides) . " slides)");
                $result    = $this->savePptx($filename, $slides, $theme);
                $results[] = $this->wrapResult('GENERATE_PPTX', $result);
            }
        }

        // ── [GENERATE_DOCX] ... [/GENERATE_DOCX] ────────────────────────────
        if (preg_match_all('/\[GENERATE_DOCX\](.*?)\[\/GENERATE_DOCX\]/si', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $filename = $this->field($block, 'filename') ?? 'document.docx';
                $title    = $this->field($block, 'title') ?? 'Document';
                $content  = null;
                if (preg_match('/^content:\s*\n([\s\S]*)/mi', $block, $cm)) {
                    $content = $cm[1];
                } elseif (preg_match('/^content:\s*(.+)/mi', $block, $cm)) {
                    $content = trim($cm[1]);
                }
                if (empty($content)) {
                    $results[] = $this->wrapResult('GENERATE_DOCX', '⚠ Skipped — "content" is required.');
                    continue;
                }
                log_message('info', "ToolDispatcher: GENERATE_DOCX — {$filename}");
                $result    = $this->saveDocx($filename, $title, $content);
                $results[] = $this->wrapResult('GENERATE_DOCX', $result);
            }
        }

        // ── [GENERATE_XLSX] ... [/GENERATE_XLSX] ────────────────────────────
        if (preg_match_all('/\[GENERATE_XLSX\](.*?)\[\/GENERATE_XLSX\]/si', $text, $matches)) {
            foreach ($matches[1] as $block) {
                $filename = $this->field($block, 'filename') ?? 'spreadsheet.xlsx';
                $title    = $this->field($block, 'title') ?? 'Spreadsheet';

                // Parse sheets — each sheet starts with a "sheet: Name" line
                $sheets = [];
                // Split block into sheet sections by lines beginning with "sheet:"
                $sections = preg_split('/^sheet:\s*/mi', "\n" . $block);
                foreach ($sections as $section) {
                    $section = trim($section);
                    if ($section === '') continue;
                    // First line of section = sheet name
                    $lines     = explode("\n", $section);
                    $sheetName = trim(array_shift($lines));
                    if ($sheetName === '' || preg_match('/^(filename|title):/i', $sheetName)) continue;
                    $remaining = implode("\n", $lines);

                    // headers: line (CSV)
                    $headers = [];
                    if (preg_match('/^headers:\s*(.+)$/mi', $remaining, $hm)) {
                        $headers = array_map('trim', str_getcsv($hm[1]));
                    }

                    // row: lines (CSV)
                    $rows = [];
                    if (preg_match_all('/^row:\s*(.+)$/mi', $remaining, $rm)) {
                        foreach ($rm[1] as $rowLine) {
                            $rows[] = array_map('trim', str_getcsv($rowLine));
                        }
                    }

                    $sheets[] = ['name' => $sheetName, 'headers' => $headers, 'rows' => $rows];
                }

                // Fallback: no "sheet:" markers — treat whole block as one sheet
                if (empty($sheets)) {
                    $headers = [];
                    if (preg_match('/^headers:\s*(.+)$/mi', $block, $hm)) {
                        $headers = array_map('trim', str_getcsv($hm[1]));
                    }
                    $rows = [];
                    if (preg_match_all('/^row:\s*(.+)$/mi', $block, $rm)) {
                        foreach ($rm[1] as $rowLine) {
                            $rows[] = array_map('trim', str_getcsv($rowLine));
                        }
                    }
                    $sheets[] = ['name' => 'Sheet1', 'headers' => $headers, 'rows' => $rows];
                }

                if (empty($sheets[0]['headers']) && empty($sheets[0]['rows'])) {
                    $results[] = $this->wrapResult('GENERATE_XLSX', '⚠ Skipped — no headers or rows found. Use "headers:" and "row:" lines.');
                    continue;
                }

                log_message('info', "ToolDispatcher: GENERATE_XLSX — {$filename} (" . count($sheets) . " sheet(s))");
                $result    = $this->saveXlsx($filename, $title, $sheets);
                $results[] = $this->wrapResult('GENERATE_XLSX', $result);
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
        // If the agent passed raw code as content, save it as a real file instead
        // of wrapping it in the document template (which would HTML-escape the code).
        $trimmed = ltrim($content);
        $isCode = preg_match('/^<!DOCTYPE\s/i', $trimmed)
               || preg_match('/^<html[\s>]/i', $trimmed)
               || preg_match('/^<\?php/i', $trimmed)
               || preg_match('/^<\?xml/i', $trimmed)
               || preg_match('/^```[a-zA-Z]/m', $trimmed);
        if ($isCode) {
            $ext  = 'html';
            if (preg_match('/^<\?php/i', $trimmed)) $ext = 'php';
            if (preg_match('/^<\?xml/i', $trimmed)) $ext = 'xml';
            $slug = preg_replace('/[^a-zA-Z0-9\-]/', '-', strtolower(trim($title)));
            $slug = trim($slug, '-') ?: 'file';
            return $this->saveFile($slug . '.' . $ext, $content);
        }

        $html = $this->documentHtml($title, $content);
        $name = 'doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.html';

        // Save under company-specific subfolder — same pattern as saveFile().
        $companySlug = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->companyId ?: 'shared');
        $base = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'generated';
        $dir  = $base . DIRECTORY_SEPARATOR . $companySlug;
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!is_dir($dir) || !is_writable($dir)) {
            return "⚠ Cannot save document — public/generated/{$companySlug}/ is not writable.";
        }
        if (file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $html) === false) {
            return "⚠ Failed to write document file.";
        }
        $url = '/file/' . $companySlug . '/' . $name;

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

    // ── Auto-save agent output to Files ─────────────────────────────────

    /**
     * Automatically save a substantial agent response or task result to the
     * company Files section. Strips tool call blocks and result noise first.
     * If the response contains fenced code blocks (```html, ```css, ```js, etc.),
     * each block is extracted and saved as a real code file via saveFile().
     * No-ops if the content is too short or companyId is unset.
     */
    public function autoSaveOutput(string $title, string $text): void
    {
        if (empty($this->companyId)) return;

        $savedFiles = 0;

        // ── 0. Unclosed GENERATE_FILE blocks ──────────────────────────────
        // executeAll() handles properly-closed [GENERATE_FILE]...[/GENERATE_FILE]
        // blocks. But agents in web chat often omit the closing tag, which makes
        // executeAll() skip the block entirely. Rescue those here by detecting
        // a [GENERATE_FILE] with no matching closing tag and saving the file.
        $hasClosedGf = (bool) preg_match('/\[GENERATE_FILE\][\s\S]*?\[\/GENERATE_FILE\]/si', $text);
        if (!$hasClosedGf && preg_match('/\[GENERATE_FILE\]([\s\S]+)\z/i', $text, $m)) {
            $block    = $m[1];
            $filename = $this->field($block, 'filename');
            $content  = null;
            if (preg_match('/^content:\s*\n([\s\S]*)/mi', $block, $cm)) {
                $content = $cm[1];
            } elseif (preg_match('/^content:\s*(.+)/mi', $block, $cm)) {
                $content = trim($cm[1]);
            }
            if (!empty($filename) && $content !== null && trim($content) !== '') {
                $this->saveFile($filename, trim($content));
                $savedFiles++;
            }
        }

        // If we rescued an unclosed GENERATE_FILE, we're done.
        if ($savedFiles > 0) return;

        // Strip raw tool call blocks (closed form with matching closing tag)
        $clean = preg_replace(
            '/\[(?:GENERATE_FILE|GENERATE_DOC|GENERATE_PPTX|GENERATE_DOCX|GENERATE_XLSX|GENERATE_IMAGE|SEARCH|SEARCH_PERSON|SEND_EMAIL|CREATE_TASK|HIRE_AGENT|FIRE_AGENT|CHECK_SITE)[^\]]*\][\s\S]*?\[\/[^\]]+\]/i',
            '', $text
        );
        // Strip unclosed [GENERATE_FILE] that couldn't be matched above
        $clean = preg_replace('/\[GENERATE_FILE\][\s\S]*/i', '', $clean);
        // Strip TOOL RESULTS echo
        $clean = preg_replace('/══ TOOL RESULTS ══[\s\S]*?══ END OF TOOL RESULTS ══/i', '', $clean);
        // Strip STATUS line
        $clean = preg_replace('/^STATUS:\s*.+$/mi', '', $clean);
        $clean = trim($clean);

        if (mb_strlen($clean) < 100) return;

        $codeExtensions = [
            'html' => 'html', 'htm' => 'html',
            'css'  => 'css',
            'js'   => 'js',   'javascript' => 'js',
            'php'  => 'php',
            'py'   => 'py',   'python' => 'py',
            'sql'  => 'sql',
            'json' => 'json',
            'xml'  => 'xml',
            'ts'   => 'ts',   'typescript' => 'ts',
            'sh'   => 'sh',   'bash' => 'sh',
        ];

        $baseTitle = trim(preg_replace('/\s+/', '_', preg_replace('/[^a-zA-Z0-9\s_\-]/', '', $title))) ?: 'output';

        // ── 1. Fenced code blocks: ```html ... ``` ───────────────────────
        $codePattern = '/```(' . implode('|', array_keys($codeExtensions)) . ')\s*\r?\n([\s\S]*?)```/i';

        if (preg_match_all($codePattern, $clean, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $i => $match) {
                $lang     = strtolower($match[1]);
                $code     = trim($match[2]);
                $ext      = $codeExtensions[$lang] ?? $lang;
                $suffix   = count($matches) > 1 ? '_' . ($i + 1) : '';
                $filename = $baseTitle . $suffix . '.' . $ext;

                if (mb_strlen($code) > 50) {
                    $this->saveFile($filename, $code);
                    $savedFiles++;
                }
            }
        }

        // ── 2. Raw HTML dumped without fences ────────────────────────────
        if ($savedFiles === 0 && preg_match('/(<!DOCTYPE\s+html[\s\S]*?<\/html>|<html[\s\S]*?<\/html>)/i', $clean, $m)) {
            $this->saveFile($baseTitle . '.html', trim($m[1]));
            $savedFiles++;
        }

        // ── 3. Fallback: save as formatted document ───────────────────────
        $prose = trim(preg_replace($codePattern, '', $clean));
        if ($savedFiles === 0 && mb_strlen($clean) >= 300) {
            $this->saveDocument($title, $clean);
        } elseif ($savedFiles > 0 && mb_strlen($prose) >= 300) {
            $this->saveDocument($title . ' — Notes', $prose);
        }
    }

    // ── Tool: Generate PowerPoint Presentation ───────────────────────────

    private function savePptx(string $filename, array $slides, string $theme = 'dark'): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._\-]/', '_', basename($filename));
        if (!preg_match('/\.pptx$/i', $safe)) $safe .= '.pptx';
        if ($safe === '.pptx') $safe = 'presentation.pptx';

        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '_' . $safe;

        $companySlug = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->companyId ?: 'shared');
        $base = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'generated';
        $dir  = $base . DIRECTORY_SEPARATOR . $companySlug;
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!is_dir($dir) || !is_writable($dir)) {
            return "⚠ Cannot save presentation — public/generated/{$companySlug}/ is not writable.";
        }

        $path = $dir . DIRECTORY_SEPARATOR . $name;

        try {
            $service = new PptxService();
            $tmpPath = $service->generate($slides[0]['title'] ?? 'Presentation', $slides, $theme);
            if (!rename($tmpPath, $path)) {
                copy($tmpPath, $path);
                @unlink($tmpPath);
            }
        } catch (\Throwable $e) {
            log_message('error', 'savePptx failed: ' . $e->getMessage());
            return '⚠ Failed to generate presentation: ' . $e->getMessage();
        }

        $url  = '/file/' . $companySlug . '/' . $name;
        $mime = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
        $this->recordArtifact('document', $safe, $url, $mime);

        return "✓ Presentation saved\nFILE_URL: {$url}\nFilename: {$safe}\n(Download and open in Microsoft PowerPoint or LibreOffice Impress)";
    }

    // ── Tool: Generate Word Document (.docx) ─────────────────────────────

    private function saveDocx(string $filename, string $title, string $content): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._\-]/', '_', basename($filename));
        if (!preg_match('/\.docx$/i', $safe)) $safe .= '.docx';
        if ($safe === '.docx') $safe = 'document.docx';

        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '_' . $safe;

        $companySlug = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->companyId ?: 'shared');
        $base = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'generated';
        $dir  = $base . DIRECTORY_SEPARATOR . $companySlug;
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!is_dir($dir) || !is_writable($dir)) {
            return "⚠ Cannot save document — public/generated/{$companySlug}/ is not writable.";
        }

        $path = $dir . DIRECTORY_SEPARATOR . $name;

        try {
            $service = new DocxService();
            $tmpPath = $service->generate($title, $content);
            if (!rename($tmpPath, $path)) {
                copy($tmpPath, $path);
                @unlink($tmpPath);
            }
        } catch (\Throwable $e) {
            log_message('error', 'saveDocx failed: ' . $e->getMessage());
            return '⚠ Failed to generate Word document: ' . $e->getMessage();
        }

        $url  = '/file/' . $companySlug . '/' . $name;
        $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $this->recordArtifact('document', $safe, $url, $mime);

        return "✓ Word document saved\nFILE_URL: {$url}\nFilename: {$safe}\n(Download and open in Microsoft Word or LibreOffice Writer)";
    }

    // ── Tool: Generate Excel Spreadsheet (.xlsx) ─────────────────────────

    private function saveXlsx(string $filename, string $title, array $sheets): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._\-]/', '_', basename($filename));
        if (!preg_match('/\.xlsx$/i', $safe)) $safe .= '.xlsx';
        if ($safe === '.xlsx') $safe = 'spreadsheet.xlsx';

        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '_' . $safe;

        $companySlug = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->companyId ?: 'shared');
        $base = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'generated';
        $dir  = $base . DIRECTORY_SEPARATOR . $companySlug;
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!is_dir($dir) || !is_writable($dir)) {
            return "⚠ Cannot save spreadsheet — public/generated/{$companySlug}/ is not writable.";
        }

        $path = $dir . DIRECTORY_SEPARATOR . $name;

        try {
            $service = new ExcelService();
            $tmpPath = $service->generate($title, $sheets);
            if (!rename($tmpPath, $path)) {
                copy($tmpPath, $path);
                @unlink($tmpPath);
            }
        } catch (\Throwable $e) {
            log_message('error', 'saveXlsx failed: ' . $e->getMessage());
            return '⚠ Failed to generate spreadsheet: ' . $e->getMessage();
        }

        $url  = '/file/' . $companySlug . '/' . $name;
        $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $this->recordArtifact('document', $safe, $url, $mime);

        return "✓ Excel spreadsheet saved\nFILE_URL: {$url}\nFilename: {$safe}\n(Download and open in Microsoft Excel or Google Sheets)";
    }

    // ── Tool: Save Raw File (code deliverable) ───────────────────────────

    private function saveFile(string $filename, string $content): string
    {
        // Strip markdown code fences the agent may have wrapped the content in
        $content = preg_replace('/^\s*```[a-zA-Z]*\s*\n?/', '', $content);
        $content = preg_replace('/\n?```\s*$/', '', $content);
        $content = trim($content);

        $safe = preg_replace('/[^a-zA-Z0-9._\-]/', '_', basename($filename));
        if ($safe === '' || $safe === '.') $safe = 'file.txt';

        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '_' . $safe;

        $ext = strtolower(pathinfo($safe, PATHINFO_EXTENSION));
        $mimes = [
            'html' => 'text/html',  'htm' => 'text/html',
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'json' => 'application/json',
            'txt'  => 'text/plain', 'md'  => 'text/markdown',
            'xml'  => 'text/xml',   'csv' => 'text/csv',
            'php'  => 'text/plain', 'py'  => 'text/plain',
            'sql'  => 'text/plain',
        ];
        $mime = $mimes[$ext] ?? 'text/plain';

        // Save under a company-specific subfolder so each company's Files tab
        // only shows its own files. Served through /file/{companyId}/{name} which
        // sets Content-Type explicitly in PHP (Supabase serves HTML as text/plain).
        $companySlug = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->companyId ?: 'shared');
        $base = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'generated';
        $dir  = $base . DIRECTORY_SEPARATOR . $companySlug;
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!is_dir($dir) || !is_writable($dir)) {
            return "⚠ Cannot save file — public/generated/{$companySlug}/ is not writable.";
        }
        if (file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $content) === false) {
            return "⚠ Failed to write file.";
        }
        $url = '/file/' . $companySlug . '/' . $name;

        $this->recordArtifact('document', $safe, $url, $mime);

        return "✓ File saved\nFILE_URL: {$url}\nFilename: {$safe}";
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
    // ── Tool: Company Report (all companies at once) ─────────────────────

    /**
     * On-demand status of ALL companies (from app/Config/Companies.php):
     * live website status and/or Facebook page metrics.
     * $focus: 'website' | 'facebook' | 'all' (default).
     */
    private function companyReport(string $focus): string
    {
        $companies = \Config\Companies::LIST;
        $out = [];

        if ($focus === 'all' || $focus === 'website') {
            $out[] = '── WEBSITE STATUS (all companies) ──';
            foreach ($companies as $c) {
                $name = $c['name'] ?? '?';
                $url  = $c['website'] ?? '';
                if ($url === '') {
                    $out[] = "  • {$name}: ⚪ no website configured yet";
                    continue;
                }
                $first = strtok($this->siteCheck->check($url), "\n"); // first line = 🟢/🔴 status
                $out[] = "  • {$name}: {$first}";
            }
        }

        if ($focus === 'all' || $focus === 'facebook') {
            $out[] = '── FACEBOOK STATUS ──';
            if ($this->facebook->isConfigured()) {
                $ins = $this->facebook->getPageInsights();
                if ($ins['ok'] ?? false) {
                    $d = $ins['data'];
                    $out[] = "  • Page \"" . ($d['name'] ?? '?') . "\": followers=" . ($d['followers'] ?? 'n/a')
                        . ', 28-day reach=' . ($d['reach_28d'] ?? 'n/a')
                        . ', 28-day engagement=' . ($d['engagement_28d'] ?? 'n/a');
                    foreach (array_slice($d['top'] ?? [], 0, 3) as $i => $t) {
                        $out[] = '      top ' . ($i + 1) . ": reach={$t['reach']}, engagement={$t['engagement']} — \"{$t['excerpt']}\"";
                    }
                } else {
                    $out[] = '  • Facebook error: ' . ($ins['error'] ?? 'unknown');
                }
                $out[] = '  (Note: one Facebook page is connected for now; per-company pages can be added later.)';
            } else {
                $out[] = '  • Facebook not connected (no Page token in .env).';
            }
        }

        return implode("\n", $out);
    }

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

### 👤 Search Real Person
Look up any real person — executives, public figures, professionals, influencers.
Returns a structured profile: knowledge graph, LinkedIn, web results, and recent news.

[SEARCH_PERSON]
name: Full Name Here
context: optional — narrows results, e.g. "Philippines", "CEO", "athlete"
[/SEARCH_PERSON]

### 🩺 Check Site (live website QA)
Visit a REAL web page and get verifiable health facts: HTTP status, load time,
HTTPS/SSL validity, redirects, page title, forms present, and a 404 scan.
Add `scope: site` to discover EVERY page via sitemap.xml and check each one.
It never submits form data.

[CHECK_SITE]
url: https://example.com
scope: site
[/CHECK_SITE]

IMPORTANT — how to read the results, so you don't raise false alarms:
- A form action of "#"/empty/JavaScript is NORMAL (the form submits via JS/AJAX).
  Do NOT report it as a broken form or a 404.
- A GET probe returning 404/405 on a form action is often normal (POST-only
  endpoint). Only call something broken if the PAGE itself returns 4xx/5xx.
- JS-rendered sites (Wix, React) may show "no forms/links in static HTML" — that
  is a limitation of static checking, not a site defect. Use scope: site for pages.
- Report ONLY what the tool actually returned. Never invent statuses or numbers.

### 🏢 Company Report (ALL companies at once)
When asked for the status/report of ALL companies (e.g. "website status of every
company", "give me the Facebook report for all companies", "status of all our
businesses"), use this — it checks each company's live website and the connected
Facebook page in one step. Set focus to `website`, `facebook`, or `all`.

[COMPANY_REPORT]
focus: all
[/COMPANY_REPORT]

### 📘 Post to Facebook Page
Publish a post to the company's connected Facebook Page. Use for announcements,
campaigns, and updates. This posts publicly and immediately — only use it when
the user clearly asks to post.

To post WITH an image, do NOT call GENERATE_IMAGE yourself and do NOT invent an
image URL. Instead give an `image_prompt` and the system generates the image and
attaches it for you in one step:

[POST_FACEBOOK]
message: The text/caption to publish on the Page.
image_prompt: a calming mental-health themed illustration, soft colors
[/POST_FACEBOOK]

For text only, omit image_prompt. If you already have a real public image URL
(e.g. an existing artifact), you may pass `image: https://...` instead.

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

⚠️ IMAGE FALLBACK RULE: If image generation is unavailable or produces only a placeholder (showing the prompt as text on a dark background), offer to create an HTML poster/design instead using GENERATE_FILE. An HTML poster with CSS can look just as professional and can be printed to PDF. Always offer this alternative when image generation fails.

### 💾 Generate File — CODE DELIVERABLES (HTML, CSS, JS, Python, SQL, etc.)
⚠️ CRITICAL RULE: When asked to CREATE any code file, webpage, script, or program,
you MUST use this tool to save it as a real downloadable file.
NEVER paste raw code into the chat message — always use GENERATE_FILE instead.
This applies to: HTML pages, CSS stylesheets, JavaScript files, Python scripts,
SQL queries, JSON configs, XML files, and any other code or text file.

[GENERATE_FILE]
filename: facebook-homepage.html
content:
<!DOCTYPE html>
<html lang="en">
...complete file content here (no code fences, no markdown — raw file content only)...
</html>
[/GENERATE_FILE]

Examples of when to use GENERATE_FILE (not inline code):
- "create a Facebook homepage in HTML/CSS" → use GENERATE_FILE with filename: facebook.html
- "write a Python scraper" → use GENERATE_FILE with filename: scraper.py
- "build a landing page" → use GENERATE_FILE with filename: landing.html
- "write a SQL migration" → use GENERATE_FILE with filename: migration.sql

### 📊 Generate PowerPoint Presentation
Create a real .pptx file that can be opened in Microsoft PowerPoint or LibreOffice Impress.
Use this for slide decks, pitch decks, reports, proposals, or any presentation.

[GENERATE_PPTX]
filename: company-overview.pptx
theme: dark
---
title: Company Overview
subtitle: Positive Nation LLC — 2026
type: title
---
title: Executive Summary
content:
• Q2 revenue grew 23% year-over-year
• 50,000 new community members joined
• Launched Positive Nation Economy beta
---
title: Key Metrics
content:
• Monthly Active Users: 125,000
• Engagement Rate: 67%
• Revenue: $2.4M
---
title: Next Steps
content:
• Scale the Positive Nation Economy
• Launch ambassador program
• Expand to 3 new markets
[/GENERATE_PPTX]

Rules for GENERATE_PPTX:
- Use `---` (three dashes alone on a line) to separate slides
- First slide usually has `type: title` with a subtitle
- All other slides use `type: content` (or omit type — content is default)
- List bullets with • or - on separate lines under `content:`
- `theme: dark` (navy + gold, default) or `theme: light` (white + gold)
- NEVER paste raw slide content in the chat — always use GENERATE_PPTX

### 📝 Generate Word Document (.docx)
Create a real .docx file that opens in Microsoft Word or LibreOffice Writer.
Supports headings, bullet lists, numbered lists, bold, italic, and page breaks.

[GENERATE_DOCX]
filename: q2-report.docx
title: Q2 2026 Marketing Report
content:
# Executive Summary

This report covers **Q2 2026** performance for Positive Nation LLC.

## Key Highlights

- Revenue grew 23% year-over-year
- 50,000 new community members joined
- Launched Positive Nation Economy beta

## Financials

### Revenue
Total revenue for Q2 was *$2.4M*, exceeding our target by 12%.

### Next Steps
- Scale the Positive Nation Economy
- Launch ambassador program
[/GENERATE_DOCX]

Rules for GENERATE_DOCX:
- `# Heading` / `## Subheading` / `### Sub-subheading` → heading levels
- `- item` or `• item` → bullet list
- `1. item` → numbered list
- `**text**` → bold, `*text*` → italic
- `---` alone on a line → page break
- NEVER paste raw document text in the chat — always use GENERATE_DOCX

### 📊 Generate Excel Spreadsheet (.xlsx)
Create a real .xlsx file that opens in Microsoft Excel or Google Sheets.
Use this for data tables, reports, budgets, trackers, org data — anything tabular.
Supports multiple sheets, styled headers, alternating rows, auto-filter, and frozen header row.

[GENERATE_XLSX]
filename: q2-sales-report.xlsx
title: Q2 2026 Sales Report

sheet: Summary
headers: Metric, Value, Change
row: Total Revenue, $2,400,000, +23%
row: New Customers, 1250, +18%
row: Churn Rate, 3.2%, -1.1%
row: Avg Deal Size, $1920, +4%

sheet: By Region
headers: Region, Revenue, Customers, Growth
row: North America, $1,200,000, 620, +28%
row: Europe, $740,000, 390, +19%
row: Asia Pacific, $460,000, 240, +15%
[/GENERATE_XLSX]

Rules for GENERATE_XLSX:
- Use `sheet: Name` to start each sheet (or omit for a single unnamed sheet)
- `headers:` is a comma-separated list of column headers
- Each `row:` is a comma-separated list of values matching the headers
- Wrap values containing commas in quotes: `row: "Smith, John", $50000`
- Pure numbers (no $ or %) are stored as Excel numbers (sortable/summable)
- NEVER paste raw table data in the chat — always use GENERATE_XLSX

### 📄 Generate Document (reports and proposals in markdown)
For text documents, reports, and proposals — NOT for code files (use GENERATE_FILE for code).
Saved to the Files library and can be printed to PDF.

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
- ⚠️ NEVER paste raw code (HTML/CSS/JS/Python/SQL/etc.) directly into the chat — ALWAYS use GENERATE_FILE
- ⚠️ When asked for a table, spreadsheet, or data export — use GENERATE_XLSX, not a markdown table in the chat
- ⚠️ After GENERATE_FILE, GENERATE_DOC, GENERATE_XLSX, GENERATE_PPTX, or GENERATE_DOCX saves a file, do NOT repeat or show the raw content again — just confirm the filename and say it is ready
- When fully done, end your response with exactly:
  STATUS: done | [one sentence summary of what was accomplished]
DOCS;
    }
}
