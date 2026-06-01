<?php

namespace App\Libraries;

/**
 * EmailService
 * ─────────────────────────────────────────────────────────────
 * Sends emails via Hostinger SMTP using CodeIgniter 4's
 * built-in Email library.
 *
 * .env settings:
 *   smtp.host      = smtp.hostinger.com
 *   smtp.port      = 465
 *   smtp.crypto    = ssl
 *   smtp.user      = your@yourdomain.com
 *   smtp.pass      = yourpassword
 *   smtp.fromEmail = your@yourdomain.com
 *   smtp.fromName  = Mosbat AI
 *
 * Hostinger SMTP ports:
 *   465  with SSL  (recommended)
 *   587  with TLS  (alternative)
 */
class EmailService
{
    private string $fromEmail;
    private string $fromName;
    private array  $smtpConfig;
    private bool   $configured;

    public function __construct()
    {
        $this->fromEmail = (string) getenv('smtp.fromEmail');
        $this->fromName  = (string) getenv('smtp.fromName') ?: 'Mosbat AI';

        $this->smtpConfig = [
            'protocol'   => 'smtp',
            'SMTPHost'   => (string) getenv('smtp.host')   ?: 'smtp.hostinger.com',
            'SMTPPort'   => (int)    getenv('smtp.port')   ?: 465,
            'SMTPCrypto' => (string) getenv('smtp.crypto') ?: 'ssl',
            'SMTPUser'   => (string) getenv('smtp.user'),
            'SMTPPass'   => (string) getenv('smtp.pass'),
            'mailType'   => 'html',
            'charset'    => 'utf-8',
            'wordWrap'   => true,
            'SMTPTimeout'=> 15,
        ];

        $this->configured = !empty($this->fromEmail) && !empty($this->smtpConfig['SMTPUser']);
    }

    /**
     * Send an email. Returns a status string for the agent to read.
     *
     * @param string $to      Recipient email address
     * @param string $subject Email subject line
     * @param string $body    Plain text body (auto-converted to HTML)
     */
    public function send(string $to, string $subject, string $body): string
    {
        if (!$this->configured) {
            log_message('warning', 'EmailService: smtp.fromEmail or smtp.user not set in .env');
            return "⚠ Email unavailable — SMTP not configured in .env (smtp.fromEmail, smtp.user, smtp.pass required)";
        }

        // Validate recipient
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return "⚠ Email not sent — invalid recipient address: {$to}";
        }

        try {
            $mailer = \Config\Services::email();
            $mailer->initialize($this->smtpConfig);
            $mailer->setFrom($this->fromEmail, $this->fromName);
            $mailer->setTo($to);
            $mailer->setSubject($subject);
            $mailer->setMessage($this->toHtml($body));
            $mailer->setAltMessage($body); // plain text fallback

            if ($mailer->send(false)) {
                log_message('info', "EmailService: Sent \"{$subject}\" to {$to}");
                return "✓ Email sent successfully\n  To: {$to}\n  Subject: {$subject}";
            }

            $debug = $mailer->printDebugger(['headers', 'subject', 'body']);
            log_message('error', 'EmailService send failed: ' . $debug);
            return "⚠ Email failed to send — check SMTP credentials in .env";

        } catch (\Throwable $e) {
            log_message('error', 'EmailService exception: ' . $e->getMessage());
            return "⚠ Email error: " . $e->getMessage();
        }
    }

    // ────────────────────────────────────────────────────────────────────

    /**
     * Convert plain text / simple markdown to clean HTML email.
     */
    private function toHtml(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // Headers
        $escaped = preg_replace('/^### (.+)$/m', '<h3 style="color:#0055aa;margin:16px 0 4px;">$1</h3>', $escaped);
        $escaped = preg_replace('/^## (.+)$/m',  '<h2 style="color:#003377;margin:20px 0 6px;">$1</h2>', $escaped);
        $escaped = preg_replace('/^# (.+)$/m',   '<h1 style="color:#001a55;margin:24px 0 8px;">$1</h1>', $escaped);

        // Bold
        $escaped = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $escaped);

        // Bullets
        $escaped = preg_replace('/^[•\-\*] (.+)$/m', '<li style="margin:4px 0;">$1</li>', $escaped);

        // Dividers
        $escaped = preg_replace('/^---+$/m', '<hr style="border:none;border-top:1px solid #ddd;margin:16px 0;">', $escaped);

        // Paragraphs
        $escaped = preg_replace('/\n{2,}/', '</p><p style="margin:10px 0;">', $escaped);
        $escaped = str_replace("\n", '<br>', $escaped);

        return '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;font-size:14px;'
            . 'line-height:1.6;color:#333;max-width:680px;margin:0 auto;padding:24px;">'
            . '<p style="margin:10px 0;">' . $escaped . '</p>'
            . '<hr style="border:none;border-top:1px solid #eee;margin:32px 0 16px;">'
            . '<p style="font-size:11px;color:#999;">Sent by Mosbat AI · Command HQ</p>'
            . '</body></html>';
    }
}
