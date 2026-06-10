<?php

namespace App\Libraries;

/**
 * ImageService — generates images via external AI APIs.
 *
 * Priority:
 *   1. OpenAI (gpt-image-1 → dall-e-3 → dall-e-2)  — only if openai.apiKey is set
 *   2. Pollinations.ai (free, no key, FLUX model)   — real AI images
 *   3. Local GD renderer  — text-on-gradient placeholder, no internet needed
 *   4. Local SVG renderer — fallback if GD extension isn't loaded
 */
class ImageService
{
    private string $apiKey;
    private string $openaiEndpoint = 'https://api.openai.com/v1/images/generations';

    public function __construct()
    {
        $raw = (string) (env('openai.apiKey') ?: getenv('openai.apiKey') ?: '');
        $this->apiKey = trim($raw, "\"' \t");
    }

    public function generate(string $prompt, string $size = '1024x1024'): string
    {
        // Try OpenAI first if a key is configured
        if ($this->apiKey !== '') {
            $result = $this->generateOpenAI($prompt, $size);
            if (str_starts_with($result, '✓')) {
                return $result;
            }
            log_message('warning', 'ImageService: OpenAI failed, falling back to local renderer');
        }

        // Pollinations.ai — free real AI image generation (FLUX model, no key needed)
        $result = $this->generatePollinations($prompt, $size);
        if (str_starts_with($result, '✓')) {
            return $result;
        }
        log_message('warning', 'ImageService: Pollinations failed, falling back to local renderer');

        // Local generation — text-on-gradient placeholder, always works
        return $this->generateLocal($prompt, $size);
    }

    // ── OpenAI path (optional) ────────────────────────────────────────────

    private function generateOpenAI(string $prompt, string $size): string
    {
        $d3Size = in_array($size, ['1024x1024', '1792x1024', '1024x1792']) ? $size : '1024x1024';

        $models = [
            ['model' => 'gpt-image-1', 'payload' => [
                'model' => 'gpt-image-1', 'prompt' => $prompt, 'n' => 1, 'size' => '1024x1024',
            ]],
            ['model' => 'dall-e-3', 'payload' => [
                'model' => 'dall-e-3', 'prompt' => $prompt, 'n' => 1, 'size' => $d3Size, 'quality' => 'standard',
            ]],
            ['model' => 'dall-e-2', 'payload' => [
                'model' => 'dall-e-2', 'prompt' => $prompt, 'n' => 1, 'size' => '1024x1024',
            ]],
        ];

        foreach ($models as $cfg) {
            $ch = curl_init($this->openaiEndpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($cfg['payload']),
            ]);
            $response = curl_exec($ch);
            $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                log_message('warning', "ImageService OpenAI/{$cfg['model']}: curl error — {$curlErr}");
                continue;
            }

            $data   = json_decode($response, true);
            $errMsg = is_array($data) ? ($data['error']['message'] ?? '') : '';
            $errCode = is_array($data) ? ($data['error']['code'] ?? '') : '';

            // Any 4xx on a specific model → try the next one
            if ($status >= 400) {
                log_message('warning', "ImageService OpenAI/{$cfg['model']}: HTTP {$status} — {$errMsg}");
                continue;
            }
            if (!is_array($data)) {
                log_message('warning', "ImageService OpenAI/{$cfg['model']}: non-JSON response");
                continue;
            }

            // URL response
            if (!empty($data['data'][0]['url'])) {
                $local = $this->downloadAndSave($data['data'][0]['url']);
                $url   = $local['ok'] ? $local['url'] : $data['data'][0]['url'];
                return "✓ Image generated ({$cfg['model']})\nIMAGE_URL: {$url}";
            }
            // Base64 response (gpt-image-1)
            if (!empty($data['data'][0]['b64_json'])) {
                $local = $this->saveRawBytes(base64_decode($data['data'][0]['b64_json'], true), 'png');
                if ($local['ok']) return "✓ Image generated ({$cfg['model']})\nIMAGE_URL: {$local['url']}";
            }
        }

        log_message('warning', 'ImageService: all OpenAI models failed, falling back to Pollinations');
        return "⚠ All OpenAI models unavailable";
    }

    // ── Pollinations.ai (free, no API key required) ──────────────────────

    private function generatePollinations(string $prompt, string $size): string
    {
        [$w, $h] = $this->parseSize($size);
        $w = min($w, 1344);
        $h = min($h, 1344);
        $seed = abs(crc32($prompt)) % 999999;

        // Try models in order — flux-schnell is fastest, turbo is lightest on queue
        $models = ['flux', 'flux-schnell', 'turbo'];

        foreach ($models as $model) {
            $url = 'https://image.pollinations.ai/prompt/' . rawurlencode($prompt)
                 . '?width=' . $w . '&height=' . $h
                 . '&seed=' . $seed . '&model=' . $model . '&nologo=true';

            // Attempt twice per model (once retry on 402 queue-full)
            for ($attempt = 0; $attempt < 2; $attempt++) {
                if ($attempt > 0) sleep(10); // wait for queue slot before retry

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT        => 90,
                    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; MosbatAI/1.0)',
                    CURLOPT_HTTPHEADER     => ['Referer: https://pollinations.ai'],
                ]);
                $body   = curl_exec($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $ctype  = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                $err    = curl_error($ch);
                curl_close($ch);

                if ($err) {
                    log_message('warning', "ImageService Pollinations/{$model}: curl error: {$err}");
                    break; // curl error → try next model immediately
                }

                if ($status === 200 && $body && str_starts_with($ctype, 'image/')) {
                    $ext   = stripos($ctype, 'png') !== false ? 'png' : (stripos($ctype, 'webp') !== false ? 'webp' : 'jpg');
                    $local = $this->saveRawBytes($body, $ext);
                    if ($local['ok']) {
                        return "✓ Image generated (Pollinations · " . strtoupper($model) . ")\nIMAGE_URL: {$local['url']}";
                    }
                }

                if ($status === 402) {
                    log_message('info', "ImageService Pollinations/{$model}: queue full (attempt {$attempt}), will retry");
                    continue; // retry after sleep
                }

                // Any other error → try next model
                log_message('warning', "ImageService Pollinations/{$model}: status={$status} ctype={$ctype}");
                break;
            }
        }

        return "⚠ Pollinations unavailable — all models busy or timed out";
    }

    // ── Local renderer ────────────────────────────────────────────────────

    private function generateLocal(string $prompt, string $size): string
    {
        // Prefer GD (richer result)
        if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
            $r = $this->renderGd($prompt);
            if ($r['ok']) {
                return "✓ Image generated (local · AI Vision)\nIMAGE_URL: {$r['url']}";
            }
        }

        // SVG fallback — always works, no extension needed
        $r = $this->renderSvg($prompt, $size);
        if ($r['ok']) {
            return "✓ Image generated (local · AI Vision)\nIMAGE_URL: {$r['url']}";
        }

        return "⚠ Image generation failed — could not write to public/generated/. Check directory permissions.";
    }

    // ── GD PNG renderer ───────────────────────────────────────────────────

    private function renderGd(string $prompt): array
    {
        $W = 768; $H = 768;
        $img = imagecreatetruecolor($W, $H);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        $s = $this->colorScheme($prompt);
        [$fr, $fg, $fb] = $s['from'];
        [$tr, $tg, $tb] = $s['to'];
        [$ar, $ag, $ab] = $s['accent'];
        $cx = (int)($W / 2);  $cy = (int)($H / 2);

        // 1. Vertical gradient background
        for ($y = 0; $y < $H; $y++) {
            $t = $y / $H;
            $c = imagecolorallocate($img,
                (int)($fr + ($tr - $fr) * $t),
                (int)($fg + ($tg - $fg) * $t),
                (int)($fb + ($tb - $fb) * $t)
            );
            imageline($img, 0, $y, $W - 1, $y, $c);
        }

        // 2. Radial glow
        $maxR = (int)(min($W, $H) * 0.44);
        for ($r = $maxR; $r > 0; $r -= 5) {
            $alpha = (int)(127 - (1 - $r / $maxR) * 95);
            $c = imagecolorallocatealpha($img, $ar, $ag, $ab, max(0, min(127, $alpha)));
            imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $c);
        }

        // 3. Subtle grid
        $gc = imagecolorallocatealpha($img, 90, 110, 170, 116);
        for ($x = 0; $x < $W; $x += 44) imageline($img, $x, 0, $x, $H - 1, $gc);
        for ($y = 0; $y < $H; $y += 44) imageline($img, 0, $y, $W - 1, $y, $gc);

        // 4. Stars (deterministic from prompt)
        mt_srand(crc32($prompt));
        for ($i = 0; $i < 130; $i++) {
            $sx = mt_rand(0, $W - 1);  $sy = mt_rand(0, $H - 1);
            $br = mt_rand(160, 255);   $al = mt_rand(35, 85);
            $sc = imagecolorallocatealpha($img, $br, $br, $br, $al);
            $sz = mt_rand(1, 3);
            if ($sz === 1) imagesetpixel($img, $sx, $sy, $sc);
            else           imagefilledellipse($img, $sx, $sy, $sz, $sz, $sc);
        }

        // 5. Decorative rings
        $r1 = imagecolorallocatealpha($img, $ar, $ag, $ab, 100);
        $r2 = imagecolorallocatealpha($img, $ar, $ag, $ab, 112);
        imageellipse($img, $cx, $cy, (int)($W * 0.54), (int)($H * 0.54), $r1);
        imageellipse($img, $cx, $cy, (int)($W * 0.76), (int)($H * 0.76), $r2);

        // 6. Word-wrap prompt
        $font    = 5;
        $cw      = imagefontwidth($font);
        $ch      = imagefontheight($font);
        $maxCols = (int)(($W * 0.76) / $cw);
        $wrapped = wordwrap($prompt, $maxCols, "\n", true);
        $lines   = array_slice(explode("\n", $wrapped), 0, 5);
        $lh      = $ch + 7;
        $tH      = count($lines) * $lh;
        $boxPadX = (int)($W * 0.10);  $boxPadY = 20;
        $boxW    = $W - $boxPadX * 2;
        $boxH    = $tH + $boxPadY * 2;
        $boxX    = $boxPadX;
        $boxY    = (int)(($H - $boxH) / 2);

        // Text card background
        $bg   = imagecolorallocatealpha($img, 0, 0, 0, 52);
        $bord = imagecolorallocatealpha($img, $ar, $ag, $ab, 78);
        $top  = imagecolorallocatealpha($img, $ar, $ag, $ab, 45);
        imagefilledrectangle($img, $boxX, $boxY, $boxX + $boxW, $boxY + $boxH, $bg);
        imagerectangle($img, $boxX, $boxY, $boxX + $boxW, $boxY + $boxH, $bord);
        imageline($img, $boxX + 12, $boxY, $boxX + $boxW - 12, $boxY, $top);

        // Text
        $tc = imagecolorallocate($img, 248, 248, 252);
        $startY = $boxY + $boxPadY;
        foreach ($lines as $i => $line) {
            $lw = strlen($line) * $cw;
            $tx = (int)(($W - $lw) / 2);
            imagestring($img, $font, $tx, $startY + $i * $lh, $line, $tc);
        }

        // Label
        $lbl = imagecolorallocatealpha($img, $ar, $ag, $ab, 55);
        imagestring($img, 1, 10, 10, 'AI VISION', $lbl);

        // Save
        $name = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
        $dir  = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'generated';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!is_dir($dir) || !is_writable($dir)) {
            imagedestroy($img);
            return ['ok' => false, 'error' => 'cannot write to public/generated/'];
        }
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        $ok   = imagepng($img, $path);
        imagedestroy($img);
        if (!$ok) return ['ok' => false, 'error' => 'imagepng() failed'];

        // Try Supabase upload
        $bytes = @file_get_contents($path);
        if ($bytes) {
            $cloud = (new \App\Models\SupabaseModel())->uploadToStorage($name, $bytes, 'image/png');
            if ($cloud) { @unlink($path); return ['ok' => true, 'url' => $cloud]; }
        }

        return ['ok' => true, 'url' => '/generated/' . $name];
    }

    // ── SVG renderer (no extension needed) ───────────────────────────────

    private function renderSvg(string $prompt, string $size): array
    {
        [$w, $h] = $this->parseSize($size);
        $w = min($w, 1024);  $h = min($h, 1024);

        $s    = $this->colorScheme($prompt);
        $acc  = sprintf('#%02x%02x%02x', ...$s['accent']);
        $from = sprintf('#%02x%02x%02x', ...$s['from']);
        $to   = sprintf('#%02x%02x%02x', ...$s['to']);

        // Word-wrap
        $words = preg_split('/\s+/', trim($prompt));
        $lines = [];  $line = '';
        foreach ($words as $word) {
            $test = $line ? "$line $word" : $word;
            if (mb_strlen($test) > 36 && $line !== '') { $lines[] = htmlspecialchars($line, ENT_XML1, 'UTF-8'); $line = $word; }
            else $line = $test;
        }
        if ($line) $lines[] = htmlspecialchars($line, ENT_XML1, 'UTF-8');
        $lines = array_slice($lines, 0, 5);

        $fs = $w >= 800 ? 26 : 20;
        $lh = $fs + 12;
        $bH = count($lines) * $lh;
        $sy = ($h - $bH) / 2 + $fs;
        $cx = $w / 2;  $cy = $h / 2;

        $spans = '';
        foreach ($lines as $i => $l) {
            $dy = $i === 0 ? 0 : $lh;
            $spans .= "<tspan x=\"{$cx}\" dy=\"{$dy}\">{$l}</tspan>";
        }

        // Deterministic stars
        srand(crc32($prompt));
        $stars = '';
        for ($i = 0; $i < 90; $i++) {
            $sx = rand(0, $w);  $sy2 = rand(0, $h);
            $sr = round(rand(1, 4) / 2, 1);
            $so = round(rand(2, 9) / 10, 1);
            $stars .= "<circle cx=\"{$sx}\" cy=\"{$sy2}\" r=\"{$sr}\" fill=\"white\" opacity=\"{$so}\"/>";
        }

        $bY   = (int) round(($h - $bH) / 2 - 24);
        $bW   = (int) round($w * 0.80);
        $bX   = (int) round($w * 0.10);
        $bHH  = $bH + 48;
        $lx1  = $bX + 12;  $lx2 = $bX + $bW - 12;
        // Pre-compute ellipse radii (arithmetic not allowed inside heredoc {})
        $rx1  = round($w * 0.42);  $ry1 = round($h * 0.42);
        $rx2  = round($w * 0.27);  $ry2 = round($h * 0.27);
        $rx3  = round($w * 0.38);  $ry3 = round($h * 0.38);

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1" gradientUnits="objectBoundingBox">
      <stop offset="0%" stop-color="{$from}"/>
      <stop offset="100%" stop-color="{$to}"/>
    </linearGradient>
    <radialGradient id="glow" cx="50%" cy="50%" r="50%">
      <stop offset="0%" stop-color="{$acc}" stop-opacity="0.28"/>
      <stop offset="100%" stop-color="{$acc}" stop-opacity="0"/>
    </radialGradient>
    <pattern id="grid" width="44" height="44" patternUnits="userSpaceOnUse">
      <path d="M44 0L0 0 0 44" fill="none" stroke="rgba(100,120,200,0.07)" stroke-width="0.6"/>
    </pattern>
  </defs>
  <rect width="{$w}" height="{$h}" fill="url(#bg)"/>
  <rect width="{$w}" height="{$h}" fill="url(#grid)"/>
  <ellipse cx="{$cx}" cy="{$cy}" rx="{$rx1}" ry="{$ry1}" fill="url(#glow)"/>
  {$stars}
  <ellipse cx="{$cx}" cy="{$cy}" rx="{$rx2}" ry="{$ry2}" fill="none" stroke="{$acc}" stroke-opacity="0.14" stroke-width="1"/>
  <ellipse cx="{$cx}" cy="{$cy}" rx="{$rx3}" ry="{$ry3}" fill="none" stroke="{$acc}" stroke-opacity="0.09" stroke-width="1"/>
  <rect x="{$bX}" y="{$bY}" width="{$bW}" height="{$bHH}" rx="12" fill="rgba(0,0,0,0.42)" stroke="{$acc}" stroke-opacity="0.22" stroke-width="1"/>
  <line x1="{$lx1}" y1="{$bY}" x2="{$lx2}" y2="{$bY}" stroke="{$acc}" stroke-opacity="0.40" stroke-width="1.5"/>
  <text font-family="Arial,Helvetica,sans-serif" font-size="{$fs}" fill="white" fill-opacity="0.92" text-anchor="middle" y="{$sy}">{$spans}</text>
  <text x="14" y="22" font-family="monospace" font-size="9" fill="{$acc}" fill-opacity="0.45" letter-spacing="2">AI VISION</text>
</svg>
SVG;

        $name = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.svg';
        $dir  = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'generated';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!is_dir($dir) || !is_writable($dir)) return ['ok' => false, 'error' => 'cannot write directory'];
        if (file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $svg) === false) return ['ok' => false, 'error' => 'write failed'];
        return ['ok' => true, 'url' => '/generated/' . $name];
    }

    // ── Color scheme from prompt keywords ────────────────────────────────

    private function colorScheme(string $prompt): array
    {
        $p = strtolower($prompt);
        if (preg_match('/sunset|fire|flame|red|hot|warm|desert|lava|mars|orange/', $p))
            return ['from' => [28, 8, 4],  'to' => [14, 4, 2],  'accent' => [220, 75, 25]];
        if (preg_match('/ocean|sea|water|river|blue|sky|rain|ice|arctic|cold/', $p))
            return ['from' => [4, 12, 34], 'to' => [2, 6, 22],  'accent' => [28, 115, 210]];
        if (preg_match('/forest|jungle|nature|plant|tree|green|grass|emerald/', $p))
            return ['from' => [4, 18, 8],  'to' => [2, 10, 4],  'accent' => [25, 170, 75]];
        if (preg_match('/space|galaxy|cosmos|star|universe|nebula|astro|cosmic/', $p))
            return ['from' => [4, 2, 18],  'to' => [2, 1, 12],  'accent' => [115, 55, 245]];
        if (preg_match('/gold|luxury|royal|crown|wealth|yellow|sun|bright/', $p))
            return ['from' => [18, 13, 2], 'to' => [10, 7, 1],  'accent' => [215, 175, 35]];
        if (preg_match('/neon|cyber|tech|digital|hacker|matrix|electric|ai/', $p))
            return ['from' => [2, 12, 6],  'to' => [1, 6, 3],   'accent' => [0, 225, 100]];
        if (preg_match('/pink|rose|cherry|romantic|love|heart|purple|violet/', $p))
            return ['from' => [20, 5, 14], 'to' => [12, 3, 9],  'accent' => [200, 60, 180]];
        // Default — app indigo
        return ['from' => [5, 7, 28], 'to' => [2, 3, 18], 'accent' => [99, 102, 241]];
    }

    // ── Shared save helpers ───────────────────────────────────────────────

    private function saveRawBytes(string $bytes, string $ext): array
    {
        if (!$bytes) return ['ok' => false, 'url' => '', 'error' => 'empty bytes'];
        $ctype = 'image/' . $ext;
        $name  = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $cloud = (new \App\Models\SupabaseModel())->uploadToStorage($name, $bytes, $ctype);
        if ($cloud) return ['ok' => true, 'url' => $cloud, 'error' => ''];
        $dir = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'generated';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $bytes) === false)
            return ['ok' => false, 'url' => '', 'error' => 'write failed'];
        return ['ok' => true, 'url' => '/generated/' . $name, 'error' => ''];
    }

    private function downloadAndSave(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ctype  = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($status >= 400 || !$body) return ['ok' => false, 'url' => ''];
        $ext = stripos($ctype, 'png') !== false ? 'png' : (stripos($ctype, 'webp') !== false ? 'webp' : 'jpg');
        return $this->saveRawBytes($body, $ext);
    }

    private function parseSize(string $size): array
    {
        if (preg_match('/^(\d+)x(\d+)$/', $size, $m))
            return [min(max((int)$m[1], 256), 1536), min(max((int)$m[2], 256), 1536)];
        return [1024, 1024];
    }
}
