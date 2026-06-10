<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\ClaudeService;
use App\Libraries\ImageService;
use App\Models\SupabaseModel;

/**
 * DailyCampaign — Generates a daily motivational Facebook post and saves it to Files.
 *
 * Runs daily at 2:07 PM via Windows Task Scheduler.
 * The generated post + image appear in the Files section ready to copy-paste to Facebook.
 *
 * Manual run:   php spark campaign:daily
 * Dry run:      php spark campaign:daily --dry
 */
class DailyCampaign extends BaseCommand
{
    protected $group       = 'Campaign';
    protected $name        = 'campaign:daily';
    protected $description = 'Generate daily motivational Facebook post for Positive Nation LLC and save to Files';
    protected $usage       = 'campaign:daily [--dry]';
    protected $options     = [
        '--dry' => 'Generate content but do NOT save to Files',
    ];

    private const COMPANY_ID = 'positive_nation';

    private const SYSTEM_PROMPT = <<<PROMPT
You are the social media voice of Positive Nation LLC — a community organization in Batangas, Philippines,
dedicated to uplifting communities through positivity, progress, and collective action.

Your job is to write ONE Facebook post for today's daily campaign.

RULES:
- The post must be about our mission: inspiring people to stay positive, motivated, and united.
- Keep it between 3-5 short paragraphs. Warm, uplifting, community-focused tone.
- End with 3-5 relevant hashtags (e.g. #PositiveNation #Batangas #Community).
- Do NOT include any markdown formatting, headers, or bullet points.
- Do NOT include a subject line or title — start directly with the post body.
- Also generate a SHORT image prompt (1-2 sentences, vivid and visual) that would make a great
  poster image for this post. Put it on its own line at the very end, prefixed with IMAGE_PROMPT:

Example format:
[post text here]

#Hashtag1 #Hashtag2 #Hashtag3

IMAGE_PROMPT: A vibrant sunrise over Batangas Bay with silhouettes of people raising their hands together.
PROMPT;

    public function run(array $params): void
    {
        $isDry = CLI::getOption('dry') !== null;
        $ts    = '[' . date('Y-m-d H:i:s') . ']';

        CLI::write("{$ts} ═══ Daily Campaign started ═══", 'yellow');

        // ── 1. Generate post content via Claude ──────────────────────────
        CLI::write("  → Generating post content…", 'cyan');

        $claude = new ClaudeService();
        $today  = date('l, F j, Y');

        $result   = $claude->chatStream(
            self::SYSTEM_PROMPT,
            [['role' => 'user', 'content' => "Write today's daily post. Today is {$today}."]],
            0.85
        );
        $fullText = trim($result['text'] ?? '');

        if (empty($fullText)) {
            CLI::write("  ✗ Claude returned empty response.", 'red');
            return;
        }

        // ── 2. Split post text and image prompt ──────────────────────────
        $imagePrompt = '';
        $postText    = $fullText;

        if (preg_match('/IMAGE_PROMPT:\s*(.+)$/im', $fullText, $m)) {
            $imagePrompt = trim($m[1]);
            $postText    = trim(preg_replace('/IMAGE_PROMPT:\s*.+$/im', '', $fullText));
        }

        CLI::write("  ✓ Post content generated (" . str_word_count($postText) . " words)", 'green');

        // ── 3. Generate image ────────────────────────────────────────────
        $imageUrl = '';
        $imageAbs = '';

        if ($imagePrompt !== '') {
            CLI::write("  → Generating image…", 'cyan');
            $imgService = new ImageService();
            $imgResult  = $imgService->generate($imagePrompt, '1024x1024');

            if (preg_match('/IMAGE_URL:\s*(\S+)/i', $imgResult, $m)) {
                $imageUrl = trim($m[1]);

                // Resolve to absolute path if local
                if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    $imageAbs = rtrim(FCPATH, '/\\') . str_replace('/', DIRECTORY_SEPARATOR, $imageUrl);
                }

                CLI::write("  ✓ Image ready: {$imageUrl}", 'green');
            } else {
                CLI::write("  ⚠ Image generation failed — saving text only.", 'yellow');
            }
        }

        // ── 4. Build HTML file for Files section ─────────────────────────
        $dateLabel   = date('F j, Y');
        $safePost    = nl2br(htmlspecialchars($postText, ENT_QUOTES, 'UTF-8'));
        $imageTag    = '';

        if ($imageUrl !== '') {
            $displayUrl = htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8');
            $imageTag   = "<div style='margin:24px 0;text-align:center;'>"
                        . "<img src='{$displayUrl}' alt='Campaign Image' "
                        . "style='max-width:100%;border-radius:12px;border:1px solid #e8b454;'>"
                        . "</div>";
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Facebook Post — {$dateLabel}</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 720px; margin: 40px auto; padding: 0 20px; background: #0a0f1e; color: #e0e0e0; }
  h1 { font-size: 16px; color: #e8b454; letter-spacing: .15em; text-transform: uppercase; border-bottom: 1px solid #e8b454; padding-bottom: 10px; }
  .meta { font-size: 12px; color: #888; margin-bottom: 24px; }
  .post-text { background: #111827; border-left: 3px solid #e8b454; padding: 20px 24px; border-radius: 8px; line-height: 1.8; font-size: 15px; white-space: pre-wrap; }
  .copy-btn { display: inline-block; margin-top: 20px; padding: 10px 24px; background: #1877f2; color: #fff; border: none; border-radius: 8px; font-size: 14px; cursor: pointer; text-decoration: none; }
  .copy-btn:hover { background: #1558b0; }
  .tip { margin-top: 16px; font-size: 12px; color: #888; }
</style>
</head>
<body>
  <h1>📣 Positive Nation — Daily Facebook Post</h1>
  <p class="meta">Generated: {$dateLabel} &nbsp;·&nbsp; Positive Nation LLC &nbsp;·&nbsp; Batangas, Philippines</p>
  {$imageTag}
  <div class="post-text" id="postContent">{$safePost}</div>
  <br>
  <button class="copy-btn" onclick="copyPost()">📋 Copy Post Text</button>
  <p class="tip">Paste directly into Facebook. Image is shown above — save it and attach when posting.</p>
  <script>
    function copyPost() {
      var text = document.getElementById('postContent').innerText;
      navigator.clipboard.writeText(text).then(function() {
        alert('Post text copied! Now paste it into Facebook.');
      });
    }
  </script>
</body>
</html>
HTML;

        // ── 5. Save to Files section ──────────────────────────────────────
        if ($isDry) {
            CLI::write("\n--- POST PREVIEW ---", 'light_gray');
            CLI::write($postText, 'white');
            CLI::write("--------------------", 'light_gray');
            CLI::write("  [DRY RUN] Would save to Files section.", 'yellow');
            CLI::write("{$ts} ═══ Dry run complete ═══", 'green');
            return;
        }

        $slug    = self::COMPANY_ID;
        $name    = 'fb_post_' . date('Ymd') . '_' . bin2hex(random_bytes(3)) . '.html';
        $dir     = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'generated' . DIRECTORY_SEPARATOR . $slug;

        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        if (file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $html) === false) {
            CLI::write("  ✗ Failed to write file to {$dir}", 'red');
            return;
        }

        $fileUrl = '/file/' . $slug . '/' . $name;
        $title   = "Facebook Post — {$dateLabel}";

        // Record in Supabase artifacts so it appears in Files
        $sb = new SupabaseModel();
        $sb->createArtifact([
            'company_id' => $slug,
            'agent_id'   => null,
            'type'       => 'document',
            'title'      => $title,
            'file_url'   => $fileUrl,
            'mime'       => 'text/html',
        ]);

        CLI::write("  ✓ Saved to Files: {$fileUrl}", 'green');
        CLI::write("{$ts} ═══ Daily Campaign complete ═══", 'green');
    }
}
