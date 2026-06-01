<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\SupabaseModel;
use App\Libraries\ClaudeService;
use App\Libraries\ToolDispatcher;
use Throwable;

/**
 * AgentRun — Autonomous Agent Task Runner with Tool Support
 * ─────────────────────────────────────────────────────────────
 * Processes pending tasks and gives agents access to:
 *   - Web search (Serper.dev)
 *   - Email sending (Hostinger SMTP)
 *   - Sub-task creation (agent-to-agent delegation)
 *
 * Cron (every minute):
 *   * * * * * cd /var/www/html && php spark agent:run >> /var/log/agent-runner.log 2>&1
 *
 * Docker cron:
 *   * * * * * docker exec {container} php spark agent:run >> /var/log/agent-runner.log 2>&1
 *
 * Manual test (dry run, no execution):
 *   php spark agent:run --dry
 */
class AgentRun extends BaseCommand
{
    protected $group       = 'Agents';
    protected $name        = 'agent:run';
    protected $description = 'Process pending autonomous agent tasks with web search and email tools';
    protected $usage       = 'agent:run [--limit <n>] [--dry]';
    protected $options     = [
        '--limit' => 'Max tasks per run (default: 5)',
        '--dry'   => 'Preview pending tasks without executing',
    ];

    private const MAX_TOOL_TURNS = 8; // max back-and-forth turns with tools per task

    public function run(array $params): void
    {
        $ts = '[' . date('Y-m-d H:i:s') . ']';
        CLI::write("{$ts} ═══ Agent Runner started ═══", 'yellow');

        $limit = (int) CLI::getOption('limit') ?: 5;
        $isDry = CLI::getOption('dry') !== null;
        $sb    = new SupabaseModel();

        // ── Crash recovery ───────────────────────────────────────────────
        $sb->resetStuckTasks(10);
        CLI::write("  Recovery check complete.", 'dark_gray');

        // ── Fetch pending tasks ──────────────────────────────────────────
        $tasks = $sb->getPendingTasks($limit);

        if (empty($tasks)) {
            CLI::write("{$ts} No pending tasks.", 'green');
            return;
        }

        CLI::write("  Found " . count($tasks) . " task(s).", 'cyan');

        if ($isDry) {
            foreach ($tasks as $t) {
                CLI::write("  [DRY] [{$t['priority']}] {$t['title']} — {$t['id']}", 'light_gray');
            }
            return;
        }

        // ── Process each task ────────────────────────────────────────────
        foreach ($tasks as $task) {
            $this->processTask($sb, $task);
        }

        CLI::write("{$ts} ═══ Agent Runner complete ═══", 'green');
    }

    // ─────────────────────────────────────────────────────────────────────

    private function processTask(SupabaseModel $sb, array $task): void
    {
        $id    = $task['id'];
        $title = $task['title'];

        CLI::write("\n  → [{$task['priority']}] {$title}", 'cyan');

        // Claim atomically — skip if another process already took it
        if (!$sb->claimTask($id)) {
            CLI::write("    Skipped (already claimed).", 'yellow');
            return;
        }

        // Resolve agent
        $agent = null;
        if (!empty($task['agent_id'])) {
            $agent = $sb->getAgentById($task['agent_id']);
        }

        if (!$agent) {
            CLI::write("    Failed — agent not found.", 'red');
            $sb->updateTask($id, [
                'status'        => 'failed',
                'error_message' => 'Assigned agent not found.',
                'completed_at'  => date('c'),
                'claimed_at'    => null,
            ]);
            return;
        }

        CLI::write("    Agent: {$agent['name']} | Model: {$agent['model']}", 'dark_gray');

        // Build dispatcher with this company's context
        $companyId  = $task['company_id'] ?? '';
        $dispatcher = new ToolDispatcher($companyId, $agent['id']);

        // Build system prompt (role + skills + tool docs)
        $skillRows    = $sb->getAgentSkills($agent['id']);
        $systemPrompt = $this->buildSystemPrompt($agent, $skillRows);

        // Load prior turns for this task (resume support)
        $sessionId = 'task_' . $id;
        $history   = $sb->getRecentTurns($agent['id'], $sessionId, 6);

        // Seed messages
        $messages   = [];
        foreach ($history as $h) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $this->buildTaskMessage($task)];

        // ── Signal file for instant cancellation ────────────────────────
        $signalFile = ClaudeService::cancelSignalPath($id);
        @unlink($signalFile); // Clear any stale signal from a previous run

        // Abort callback — checked every 2 seconds inside chatStream()
        $shouldAbort = function () use ($signalFile): bool {
            return file_exists($signalFile);
        };

        // ── Tool execution loop ──────────────────────────────────────────
        $claude      = new ClaudeService();
        $finalOutput = '';
        $totalTokens = 0;
        $result      = [];

        try {
            for ($turn = 1; $turn <= self::MAX_TOOL_TURNS; $turn++) {
                // DB cancellation check before each turn
                $current = $sb->getTaskById($id);
                if ($current && $current['status'] === 'cancelled') {
                    CLI::write("    Cancelled (DB) before turn {$turn} — stopping.", 'yellow');
                    @unlink($signalFile);
                    return;
                }

                CLI::write("    Turn {$turn}/" . self::MAX_TOOL_TURNS . " — streaming Claude…", 'dark_gray');

                // Use chatStream with abort callback for instant cancellation
                $result      = $claude->chatStream(
                    $systemPrompt,
                    $messages,
                    (float) ($agent['temperature'] ?? 0.7),
                    $agent['model'] ?? null,
                    null,         // no chunk callback needed
                    $shouldAbort  // abort callback — checked every 2s mid-stream
                );
                $response    = $result['text'];
                $finalOutput = $response;
                $totalTokens += ($result['usage']['output_tokens'] ?? 0);

                // Check if agent used any tools
                if (!$dispatcher->hasTools($response)) {
                    CLI::write("    No tools called — task complete.", 'green');
                    break;
                }

                CLI::write("    Tools detected — executing…", 'dark_gray');

                // Signal file check before tool execution
                if (file_exists($signalFile)) {
                    CLI::write("    Cancelled (signal) before tools — stopping.", 'yellow');
                    @unlink($signalFile);
                    $sb->updateTask($id, ['status' => 'cancelled', 'claimed_at' => null]);
                    return;
                }

                // Execute tools, get results
                $toolResults = $dispatcher->executeAll($response);

                if (empty($toolResults)) {
                    CLI::write("    Tool parsing failed — stopping.", 'yellow');
                    break;
                }

                $messages[] = ['role' => 'assistant', 'content' => $response];
                $messages[] = ['role' => 'user',      'content' => $toolResults];

                if (stripos($response, 'STATUS: done') !== false) {
                    CLI::write("    Agent declared done.", 'green');
                    break;
                }
            }

            // Final cancellation check before saving output
            if (file_exists($signalFile)) {
                CLI::write("    Cancelled (signal) before save — discarding output.", 'yellow');
                @unlink($signalFile);
                $sb->updateTask($id, ['status' => 'cancelled', 'claimed_at' => null]);
                return;
            }
            $current = $sb->getTaskById($id);
            if ($current && $current['status'] === 'cancelled') {
                CLI::write("    Cancelled (DB) before save — discarding output.", 'yellow');
                @unlink($signalFile);
                return;
            }

            // Save conversation and mark done
            $sb->saveTurn($agent['id'], $sessionId, 'user',      $this->buildTaskMessage($task));
            $sb->saveTurn($agent['id'], $sessionId, 'assistant', $finalOutput, $result['usage'] ?? []);

            $sb->updateTask($id, [
                'status'       => 'done',
                'output'       => $finalOutput,
                'completed_at' => date('c'),
                'claimed_at'   => null,
            ]);

            @unlink($signalFile);
            CLI::write("    ✓ Done. Tokens: {$totalTokens}", 'green');

        } catch (Throwable $e) {
            @unlink($signalFile);
            $error = $e->getMessage();

            // __CANCELLED__ is thrown by chatStream when abort callback fires
            if ($error === '__CANCELLED__') {
                CLI::write("    ✓ Cancelled mid-stream by user.", 'yellow');
                $sb->updateTask($id, [
                    'status'       => 'cancelled',
                    'claimed_at'   => null,
                    'completed_at' => date('c'),
                ]);
                return;
            }

            CLI::write("    ✗ Failed: {$error}", 'red');
            log_message('error', "AgentRun task {$id} failed: {$error}");

            $current = $sb->getTaskById($id);
            if (!$current || $current['status'] !== 'cancelled') {
                $sb->updateTask($id, [
                    'status'        => 'failed',
                    'error_message' => $error,
                    'completed_at'  => date('c'),
                    'claimed_at'    => null,
                ]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────

    private function buildSystemPrompt(array $agent, array $skillRows): string
    {
        $parts = [];

        // 1. Agent's own role/identity
        $parts[] = $agent['system_prompt'] ?? "You are {$agent['name']}, {$agent['role_title']}.";

        // 2. Autonomous task mode instructions
        $parts[] = "\n\n---\n## AUTONOMOUS TASK MODE\n"
            . "You are working independently. No human is present.\n"
            . "Complete the assigned task thoroughly. Produce real, usable output.\n"
            . "Do not ask questions — make reasonable decisions and proceed.\n"
            . "Be concise and action-oriented: lead with the result, use short bullets, no filler.\n"
            . "Always finish your tool calls — never leave one half-written.";

        // 3. Tool documentation
        $parts[] = ToolDispatcher::toolDocs();

        // 4. Skill context blocks
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
            $parts[] = "\n\n---\n## ADDITIONAL CONTEXT (SKILLS)\n" . implode("\n\n", $skillBlocks);
        }

        return implode('', $parts);
    }

    private function buildTaskMessage(array $task): string
    {
        $priority = strtoupper($task['priority'] ?? 'MEDIUM');
        $lines    = [
            "## ASSIGNED TASK [{$priority} PRIORITY]",
            "**Title:** {$task['title']}",
        ];

        if (!empty($task['description'])) {
            $lines[] = "\n**Description:**\n{$task['description']}";
        }

        $lines[] = "\n**Assigned at:** {$task['created_at']}";
        $lines[] = "\nPlease complete this task. Use tools as needed. "
            . "Produce complete, professional output ready for the chairman to review.";

        return implode("\n", $lines);
    }
}
