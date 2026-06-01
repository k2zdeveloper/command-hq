<?php

namespace App\Commands;

use App\Libraries\ClaudeService;
use App\Models\SupabaseModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Heartbeat — daily 5:00 PM agent check-in
 * -------------------------------------------------------------
 * Wakes the Positive Nation CEO (or any specified agent), sends
 * it a fixed "produce today's summary" prompt with all its skills
 * still injected, and persists the result in `daily_reports`.
 *
 * USAGE
 *   php spark heartbeat:run                # default → pn-ceo
 *   php spark heartbeat:run --slug=pn-cmo  # any agent
 *
 * CRON (run at 17:00 server time):
 *   0 17 * * *  cd /var/www/mosbat-ai && /usr/bin/php spark heartbeat:run >> writable/logs/heartbeat.log 2>&1
 *
 * If you prefer CI4's task scheduler, add this to app/Config/Tasks.php:
 *
 *   $schedule->command('heartbeat:run')->daily('17:00')->named('pn-heartbeat');
 *
 * …and run `php spark tasks:run` from a single cron entry every minute.
 */
class Heartbeat extends BaseCommand
{
    protected $group       = 'Mosbat';
    protected $name        = 'heartbeat:run';
    protected $description = 'Daily 5pm agent check-in. Generates and stores a summary report.';
    protected $usage       = 'heartbeat:run [--slug=pn-ceo]';
    protected $options     = ['--slug' => 'Agent slug to wake (default: pn-ceo)'];

    public function run(array $params)
    {
        $slug = $params['slug'] ?? CLI::getOption('slug') ?? 'pn-ceo';
        CLI::write("Heartbeat starting for: {$slug}", 'cyan');

        $supabase = new SupabaseModel();
        $agent    = $supabase->getAgentBySlug($slug);
        if (!$agent) {
            CLI::error("Agent not found: {$slug}");
            return EXIT_ERROR;
        }

        // Compose system prompt using the same logic as live chat
        $skillRows    = $supabase->getAgentSkills($agent['id']);
        $systemPrompt = $this->composeSystemPrompt($agent, $skillRows);

        $today    = date('l, F j, Y');
        $userMsg  = "It is 5:00 PM on {$today}. Produce today's executive summary for the Positive Nation Economy. "
                  . "Use the structure: (1) Ecosystem Pulse, (2) Wins, (3) Risks, (4) Tomorrow's Priorities. "
                  . "Keep it under 400 words. Reference the granted skills/metrics where relevant.";

        try {
            $claude = new ClaudeService();
            $result = $claude->chat(
                $systemPrompt,
                [['role' => 'user', 'content' => $userMsg]],
                (float) ($agent['temperature'] ?? 0.5),
                $agent['model'] ?? null,
            );
        } catch (Throwable $e) {
            CLI::error('Claude call failed: ' . $e->getMessage());
            log_message('error', 'Heartbeat Claude failure: ' . $e->getMessage());
            return EXIT_ERROR;
        }

        $supabase->saveDailyReport($agent['id'], $result['text'], $result['raw']);

        CLI::write('--- Report ---', 'yellow');
        CLI::write($result['text']);
        CLI::write('--------------', 'yellow');
        CLI::write("Saved to daily_reports for " . date('Y-m-d'), 'green');

        return EXIT_SUCCESS;
    }

    private function composeSystemPrompt(array $agent, array $skillRows): string
    {
        $parts   = [$agent['system_prompt'] ?? ''];
        $blocks  = [];
        foreach ($skillRows as $row) {
            $skill = $row['skill'] ?? null;
            if (!$skill || empty($skill['is_active'])) continue;
            $ctx = $skill['payload']['context'] ?? null;
            if ($ctx) $blocks[] = "### Skill: {$skill['name']}\n{$ctx}";
        }
        if ($blocks) {
            $parts[] = "\n\n---\nADDITIONAL CONTEXT GRANTED TO THIS AGENT:\n" . implode("\n\n", $blocks);
        }
        return implode("\n", $parts);
    }
}
