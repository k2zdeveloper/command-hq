<?php

namespace App\Commands;

use App\Libraries\ClaudeService;
use App\Libraries\EmailService;
use App\Libraries\FacebookService;
use App\Libraries\SiteCheckService;
use App\Models\SupabaseModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * DailyBoardReport — the Chairman's daily marketing & social-media report.
 * -------------------------------------------------------------
 * For every company it gathers REAL, verifiable signals:
 *   • website + social-handle reachability (live HTTP check)
 *   • Facebook Page insights  (ONLY if a Page Access Token is set)
 *   • recent content produced (artifacts table)
 *   • task status (pending / working / done / blocked)
 *   • team size / active agents
 * Then the Master CEO AI turns the facts into a board-ready section
 * (Status → Problems → Fixed → Recommendations), and the consolidated
 * report is emailed to the Chairman.
 *
 * It never invents follower/engagement numbers — if a platform isn't
 * connected, it says so and recommends connecting it.
 *
 * USAGE
 *   php spark report:board                 # build + email to chairman.email
 *   php spark report:board --dry           # build + print, do NOT email
 *   php spark report:board --company=zengit
 *
 * SCHEDULE (Windows Task Scheduler, daily):
 *   Program:   C:\path\to\php.exe
 *   Arguments: spark report:board
 *   Start in:  C:\Users\Jherico\Downloads\command-hq-feature-chat-memory-and-ui-updates (2)
 */
class DailyBoardReport extends BaseCommand
{
    protected $group       = 'Mosbat';
    protected $name        = 'report:board';
    protected $description = 'Daily marketing & social-media board report for all companies, emailed to the Chairman.';
    protected $usage       = 'report:board [--dry] [--company=division]';
    protected $options      = [
        '--dry'     => 'Build the report but do NOT email it (prints to console).',
        '--company' => 'Limit to one company division (e.g. positive_nation).',
    ];

    /**
     * Companies to report on — managed in one place: app/Config/Companies.php.
     * Every `website` and `socials` URL is checked live for reachability.
     */
    private const COMPANIES = \Config\Companies::LIST;

    public function run(array $params)
    {
        $dry  = (bool) CLI::getOption('dry');
        $only = CLI::getOption('company');

        $supabase  = new SupabaseModel();
        $siteCheck = new SiteCheckService();
        $facebook  = new FacebookService();

        $companies = self::COMPANIES;
        if ($only && isset($companies[$only])) {
            $companies = [$only => $companies[$only]];
        }

        $today    = date('l, F j, Y');
        $sections = [];

        foreach ($companies as $division => $cfg) {
            CLI::write("Gathering: {$cfg['name']} ({$division})", 'cyan');
            $facts   = $this->gatherFacts($supabase, $siteCheck, $facebook, $division, $cfg);
            $section = $this->analyze($cfg['name'], $facts);
            $sections[] = "## {$cfg['name']}\n\n{$section}";
        }

        $report = "# Daily Board Report — {$today}\n\n"
            . "Prepared by the Master CEO AI for the Chairman of the Board.\n\n"
            . "---\n\n"
            . implode("\n\n---\n\n", $sections)
            . "\n\n---\n\n_Generated automatically by Mosbat AI · Command HQ. "
            . "Social engagement metrics appear only where a platform API is connected; "
            . "everything else is a live reachability and activity check._";

        if ($dry) {
            CLI::write("\n" . $report . "\n");
            CLI::write('[dry run — not emailed]', 'yellow');
            return EXIT_SUCCESS;
        }

        $to = (string) getenv('chairman.email');
        if ($to === '') {
            CLI::error('chairman.email is not set in .env — cannot send.');
            return EXIT_ERROR;
        }

        $subject = "Daily Board Report — {$today}";
        $result  = (new EmailService())->send($to, $subject, $report);
        CLI::write($result, str_starts_with($result, '✓') ? 'green' : 'red');

        // Best-effort: archive a copy under the CEO agent.
        try {
            $ceo = $supabase->getAgentBySlug('pn-ceo') ?: $supabase->getAgentBySlug('pn-master-brain');
            if ($ceo) {
                $supabase->saveDailyReport($ceo['id'], $report, ['type' => 'board_report']);
            }
        } catch (Throwable $e) {
            log_message('warning', 'Board report archive failed: ' . $e->getMessage());
        }

        return EXIT_SUCCESS;
    }

    // ── Gather real, verifiable facts for one company ────────────────────
    private function gatherFacts(
        SupabaseModel    $sb,
        SiteCheckService $site,
        FacebookService  $fb,
        string           $division,
        array            $cfg
    ): string {
        $lines = [];

        // Team
        try {
            $agents = $sb->getCompanyAgents($division);
            $active = array_filter($agents, static fn ($a) => !empty($a['is_active']));
            $lines[] = 'TEAM: ' . count($agents) . ' agents (' . count($active) . ' active).';
        } catch (Throwable $e) {
            $lines[] = 'TEAM: unavailable.';
        }

        // Website health (live)
        if (!empty($cfg['website'])) {
            $lines[] = "WEBSITE CHECK ({$cfg['website']}):";
            $lines[] = $site->check($cfg['website']);
        } else {
            $lines[] = 'WEBSITE: no URL configured for this company yet.';
        }

        // Social handle reachability (live, first line only)
        if (!empty($cfg['socials'])) {
            $lines[] = 'SOCIAL HANDLES (live reachability):';
            foreach ($cfg['socials'] as $label => $url) {
                $first = strtok($site->check($url), "\n");
                $lines[] = "  - {$label}: {$first}";
            }
        } else {
            $lines[] = 'SOCIAL HANDLES: none configured yet. (Add each profile URL to enable live checks.)';
        }

        // Facebook insights — ONLY if a token is configured
        if ($fb->isConfigured()) {
            $ins = $fb->getPageInsights();
            if ($ins['ok'] ?? false) {
                $d = $ins['data'];
                $lines[] = 'FACEBOOK INSIGHTS: page "' . ($d['name'] ?? '?') . '"'
                    . ' — followers=' . ($d['followers'] ?? 'n/a')
                    . ', 28-day reach=' . ($d['reach_28d'] ?? 'n/a')
                    . ', 28-day engagement=' . ($d['engagement_28d'] ?? 'n/a') . '.';
                $lines[] = '  Recent posts (' . count($d['recent'] ?? []) . '):';
                foreach (($d['recent'] ?? []) as $p) {
                    $lines[] = "    • {$p['date']}: reach={$p['reach']}, reactions={$p['reactions']}, "
                        . "clicks={$p['clicks']}, shares={$p['shares']} (engagement={$p['engagement']}) — \"{$p['excerpt']}\"";
                }
                if (!empty($d['top'])) {
                    $lines[] = '  TOP posts by reach:';
                    foreach ($d['top'] as $i => $t) {
                        $lines[] = '    ' . ($i + 1) . ". reach={$t['reach']}, engagement={$t['engagement']} — \"{$t['excerpt']}\"";
                    }
                }
            } else {
                $lines[] = 'FACEBOOK INSIGHTS: configured but failed — ' . ($ins['error'] ?? 'unknown');
            }
        } else {
            $lines[] = 'FACEBOOK INSIGHTS: NOT CONNECTED (no Page Access Token in .env). '
                . 'Real reach/engagement unavailable until connected.';
        }

        // Recent content produced
        try {
            $arts = $sb->getCompanyArtifacts($division, 10);
            $lines[] = 'RECENT CONTENT: ' . count($arts) . ' item(s) produced recently.';
            foreach (array_slice($arts, 0, 5) as $a) {
                $lines[] = "  • [{$a['type']}] " . ($a['title'] ?? '(untitled)') . " ({$a['created_at']})";
            }
        } catch (Throwable $e) {
            $lines[] = 'RECENT CONTENT: unavailable (artifacts table may be empty).';
        }

        // Task status
        try {
            $tasks = $sb->getCompanyTasks($division, 50);
            $by = [];
            foreach ($tasks as $t) {
                $st = $t['status'] ?? 'unknown';
                $by[$st] = ($by[$st] ?? 0) + 1;
            }
            $parts = [];
            foreach ($by as $st => $n) {
                $parts[] = "{$n} {$st}";
            }
            $lines[] = 'TASKS: ' . (empty($parts) ? 'none open' : implode(', ', $parts)) . '.';
        } catch (Throwable $e) {
            $lines[] = 'TASKS: unavailable.';
        }

        return implode("\n", $lines);
    }

    // ── Master CEO AI turns the facts into a board section ───────────────
    private function analyze(string $companyName, string $facts): string
    {
        $system = "You are the Master CEO AI for the Mosbat group of companies, reporting to the Chairman of the Board. "
            . "From the RAW FACTS below, write ONE concise board-report section for a single company. "
            . "Use EXACTLY these four bold headings, in order:\n"
            . "**Status** — 1-2 lines on overall marketing/social health, led by 🟢 (healthy), 🟡 (watch), or 🔴 (problem).\n"
            . "**Problems Found** — bullet the REAL issues visible in the facts, or write 'None detected'.\n"
            . "**Fixed** — anything already verified/auto-resolved this run (e.g. 'Website is live, SSL valid'), or 'Nothing required'.\n"
            . "**Recommendations** — 2-4 concrete, prioritized next actions.\n\n"
            . "Do NOT output the company name or any top-level (#/##) heading — the report already has one. "
            . "Begin directly with the **Status** line.\n"
            . "STRICT RULES: Base every statement ONLY on the facts provided. NEVER invent follower counts, "
            . "reach, or engagement numbers. If social metrics say 'NOT CONNECTED', state that plainly and make "
            . "'connect the platform API' a recommendation. Keep the whole section under 180 words.";

        $user = "COMPANY: {$companyName}\n\nRAW FACTS:\n{$facts}\n\nWrite the board section now.";

        try {
            $claude = new ClaudeService();
            $result = $claude->chat(
                $system,
                [['role' => 'user', 'content' => $user]],
                0.4,
                (string) (getenv('anthropic.model') ?: 'claude-sonnet-4-6')
            );
            return trim($result['text']);
        } catch (Throwable $e) {
            return '_Report generation failed for ' . $companyName . ': ' . $e->getMessage() . '_';
        }
    }
}
