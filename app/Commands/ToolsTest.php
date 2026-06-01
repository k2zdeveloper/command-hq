<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\SearchService;
use App\Libraries\EmailService;

/**
 * ToolsTest — Verify web search and email work correctly
 *
 * Usage:
 *   php spark tools:test                        ← tests both
 *   php spark tools:test --search="AI agents"   ← search only
 *   php spark tools:test --email=test@mail.com  ← email only
 *   php spark tools:test --skip-search          ← email only
 *   php spark tools:test --skip-email           ← search only
 */
class ToolsTest extends BaseCommand
{
    protected $group       = 'Agents';
    protected $name        = 'tools:test';
    protected $description = 'Test web search (Serper) and email (Hostinger SMTP)';
    protected $usage       = 'tools:test [--search="query"] [--email=address] [--skip-search] [--skip-email]';
    protected $options     = [
        '--search'      => 'Custom search query to test',
        '--email'       => 'Email address to send test to (default: smtp.fromEmail from .env)',
        '--skip-search' => 'Skip the search test',
        '--skip-email'  => 'Skip the email test',
    ];

    public function run(array $params): void
    {
        CLI::write('');
        CLI::write('╔══════════════════════════════════════╗', 'cyan');
        CLI::write('║   Mosbat AI — Tools Test Runner      ║', 'cyan');
        CLI::write('╚══════════════════════════════════════╝', 'cyan');
        CLI::write('');

        $skipSearch = CLI::getOption('skip-search') !== null;
        $skipEmail  = CLI::getOption('skip-email')  !== null;

        if (!$skipSearch) {
            $this->testSearch();
        }

        if (!$skipEmail) {
            $this->testEmail();
        }

        CLI::write('');
        CLI::write('═══ Test Complete ═══', 'yellow');
        CLI::write('');
    }

    // ─────────────────────────────────────────────────────────────────────

    private function testSearch(): void
    {
        CLI::write('── Web Search Test ──────────────────────', 'yellow');

        $apiKey = (string) getenv('serper.apiKey');
        if (empty($apiKey)) {
            CLI::write('  ✗ SKIP — serper.apiKey is not set in .env', 'red');
            CLI::write('    → Sign up free at https://serper.dev', 'dark_gray');
            CLI::write('    → Add: serper.apiKey = YOUR_KEY to .env', 'dark_gray');
            CLI::write('');
            return;
        }

        $query = (string) CLI::getOption('search') ?: 'AI agent platforms 2025';
        CLI::write("  Query: \"{$query}\"", 'cyan');
        CLI::write('  Calling Serper.dev API…', 'dark_gray');

        $start  = microtime(true);
        $search = new SearchService();
        $result = $search->search($query, 3);
        $ms     = round((microtime(true) - $start) * 1000);

        if (str_starts_with($result, '⚠')) {
            CLI::write("  ✗ {$result}", 'red');
        } else {
            CLI::write("  ✓ Search returned results in {$ms}ms", 'green');
            CLI::write('');
            // Show first 8 lines of result
            $lines = array_slice(explode("\n", $result), 0, 8);
            foreach ($lines as $line) {
                CLI::write("  {$line}", 'light_gray');
            }
        }
        CLI::write('');
    }

    // ─────────────────────────────────────────────────────────────────────

    private function testEmail(): void
    {
        CLI::write('── Email Test (Hostinger SMTP) ──────────', 'yellow');

        $smtpUser = (string) getenv('smtp.user');
        $smtpPass = (string) getenv('smtp.pass');
        $fromEmail= (string) getenv('smtp.fromEmail');

        if (empty($smtpUser) || empty($smtpPass)) {
            CLI::write('  ✗ SKIP — smtp.user or smtp.pass is not set in .env', 'red');
            CLI::write('    → Add your Hostinger email credentials to .env', 'dark_gray');
            CLI::write('');
            return;
        }

        $to      = (string) CLI::getOption('email') ?: ((string) getenv('chairman.email') ?: $fromEmail);
        $subject = 'Mosbat AI — SMTP Test ' . date('Y-m-d H:i:s');
        $body    = "# Email Test — Success\n\n"
            . "Your Hostinger SMTP is configured correctly.\n\n"
            . "**Sent at:** " . date('Y-m-d H:i:s') . "\n"
            . "**From:** {$fromEmail}\n"
            . "**SMTP Host:** " . getenv('smtp.host') . ":" . getenv('smtp.port') . "\n\n"
            . "---\n"
            . "This is an automated test from Mosbat AI Command HQ.\n"
            . "Your agents can now send real emails autonomously.";

        CLI::write("  From:    {$fromEmail}", 'cyan');
        CLI::write("  To:      {$to}", 'cyan');
        CLI::write("  Subject: {$subject}", 'cyan');
        CLI::write('  Connecting to smtp.hostinger.com…', 'dark_gray');

        $start  = microtime(true);
        $email  = new EmailService();
        $result = $email->send($to, $subject, $body);
        $ms     = round((microtime(true) - $start) * 1000);

        if (str_starts_with($result, '✓')) {
            CLI::write("  ✓ {$result} ({$ms}ms)", 'green');
            CLI::write("  → Check your inbox at: {$to}", 'dark_gray');
        } else {
            CLI::write("  ✗ {$result}", 'red');
            CLI::write('', '');
            CLI::write('  Common fixes:', 'yellow');
            CLI::write('    1. Verify smtp.user and smtp.pass are correct', 'dark_gray');
            CLI::write('    2. Make sure the email account exists in Hostinger', 'dark_gray');
            CLI::write('    3. Try smtp.port = 587 and smtp.crypto = tls', 'dark_gray');
            CLI::write('    4. Check Hostinger → Email → Manage → SMTP settings', 'dark_gray');
        }
        CLI::write('');
    }
}
