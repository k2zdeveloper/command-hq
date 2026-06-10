<?php

namespace App\Commands;

use App\Libraries\ClaudeService;
use App\Libraries\EmailService;
use App\Models\SupabaseModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * ReportTrial — one-off trial of the daily company report by email.
 *
 *   php spark report:trial --email christoper@k2zdigital.com
 *   php spark report:trial --email me@x.com --slug pn-ceo
 *
 * Wakes one CEO agent, generates today's executive summary, and emails
 * it. Does NOT alter the scheduled heartbeat. Purely for testing.
 */
class ReportTrial extends BaseCommand
{
    protected $group       = 'Mosbat';
    protected $name        = 'report:trial';
    protected $description = 'Send a single trial daily report to an email address.';
    protected $usage       = 'report:trial --email address [--slug pn-ceo]';
    protected $options      = [
        '--email' => 'Recipient address (required)',
        '--slug'  => 'Agent slug to wake (default: pn-ceo)',
    ];

    public function run(array $params)
    {
        $to   = (string) CLI::getOption('email');
        $slug = (string) CLI::getOption('slug') ?: 'pn-ceo';

        if (empty($to)) {
            CLI::error('--email is required, e.g. report:trial --email you@domain.com');
            return EXIT_ERROR;
        }

        $supabase = new SupabaseModel();
        $agent    = $supabase->getAgentBySlug($slug);
        if (!$agent) {
            CLI::error("Agent not found: {$slug}");
            return EXIT_ERROR;
        }

        $label = $agent['name'] ?? 'Company';
        CLI::write("Generating trial report from {$label} ({$slug})…", 'cyan');

        $skillRows    = $supabase->getAgentSkills($agent['id']);
        $systemPrompt = $this->composeSystemPrompt($agent, $skillRows);

        $today   = date('l, F j, Y');
        $userMsg = "It is 5:00 PM on {$today}. Produce today's executive summary. "
                 . "Use the structure: (1) Pulse, (2) Wins, (3) Risks, (4) Tomorrow's Priorities. "
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
            return EXIT_ERROR;
        }

        CLI::write('--- Report ---', 'yellow');
        CLI::write($result['text']);
        CLI::write('--------------', 'yellow');

        $subject = "{$label} — Daily Report (Trial) · " . date('M j, Y');
        $body    = "# {$label}\n## Daily Executive Report — {$today}\n\n"
                 . "From: {$agent['name']}"
                 . (!empty($agent['role_title']) ? " ({$agent['role_title']})" : '')
                 . "\n\n---\n\n" . $result['text'];

        $status = (new EmailService())->send($to, $subject, $body);
        CLI::write($status, str_starts_with($status, '✓') ? 'green' : 'red');

        return EXIT_SUCCESS;
    }

    private function composeSystemPrompt(array $agent, array $skillRows): string
    {
        $parts  = [$agent['system_prompt'] ?? ''];
        $blocks = [];
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
