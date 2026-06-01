<?php

namespace App\Libraries;

/**
 * ImageService
 * ─────────────────────────────────────────────────────────────
 * Generates images from text prompts and saves them LOCALLY so the
 * browser always loads a stable, same-origin file (no referrer
 * blocks, no expiring links).
 *
 * Providers:
 *   1. Pollinations.ai  — FREE, no key (default). Fetched server-side.
 *   2. OpenAI DALL·E 3  — used automatically if openai.apiKey is set.
 *
 * Saved to:  public/generated/img_xxx.jpg   → served at  /generated/img_xxx.jpg
 *
 * Agents call this via the [GENERATE_IMAGE] tool tag.
 */
class ImageService
{
    private string $apiKey;
    private string $openaiEndpoint = 'https://api.openai.com/v1/images/generations';

    public function __construct()
    {
        $this->apiKey = (string) getenv('openai.apiKey');
    }

    public function generate(string $prompt, string $size = '1024x1024'): string
    {
        if (!empty($this->apiKey)) {
            return $this->generateDalle($prompt, $size);
        }
        return $this->generatePollinations($prompt, $size);
    }

    // ── FREE: Pollinations.ai (fetched server-side, saved locally) ───────

    private function generatePollinations(string $prompt, string $size): string
    {
        [$w, $h] = $this->parseSize($size);
        $seed = random_int(1, 999999);

        $remoteUrl = 'https://image.pollinations.ai/prompt/'
                   . rawurlencode($prompt)
                   . "?width={$w}&height={$h}&seed={$seed}&nologo=true&model=flux";

        $local = $this->downloadAndSave($remoteUrl);

        if ($local['ok']) {
            log_message('info', 'ImageService: Pollinations saved ' . $local['url']);
            return "✓ Image generated (free · Pollinations.ai)\n"
                 . "IMAGE_URL: {$local['url']}\n"
                 . "(Tip: add openai.apiKey to .env for higher-quality DALL·E 3 images.)";
        }

        return "⚠ Image generation failed — {$local['error']}\n"
             . "The free image service may be busy. Try again, or add openai.apiKey to .env for reliable DALL·E 3.";
    }

    // ── PAID: OpenAI DALL·E 3 (also saved locally) ───────────────────────

    private function generateDalle(string $prompt, string $size): string
    {
        if (!in_array($size, ['1024x1024', '1792x1024', '1024x1792'])) {
            $size = '1024x1024';
        }

        $ch = curl_init($this->openaiEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model'   => 'dall-e-3',
                'prompt'  => $prompt,
                'n'       => 1,
                'size'    => $size,
                'quality' => 'standard',
            ]),
        ]);
        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return "⚠ Image generation failed — network error: {$error}";
        }
        $data = json_decode($response, true);
        if ($status >= 400 || !is_array($data)) {
            $msg = $data['error']['message'] ?? substr((string) $response, 0, 200);
            return "⚠ Image generation failed ({$status}): {$msg}";
        }
        $remoteUrl = $data['data'][0]['url'] ?? null;
        if (!$remoteUrl) {
            return "⚠ Image generation returned no URL.";
        }

        // Download the temporary DALL·E URL and host it locally (permanent)
        $local = $this->downloadAndSave($remoteUrl);
        $url   = $local['ok'] ? $local['url'] : $remoteUrl; // fallback to remote if save fails

        return "✓ Image generated successfully (DALL·E 3)\nIMAGE_URL: {$url}";
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Fetch an image URL server-side and save it under public/generated/.
     * Returns ['ok'=>bool, 'url'=>localUrl, 'error'=>string].
     */
    private function downloadAndSave(string $remoteUrl): array
    {
        $ch = curl_init($remoteUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_CONNECTTIMEOUT => 15,
            // Send a normal UA, no browser referrer (avoids the anonymous-endpoint block)
            CURLOPT_USERAGENT      => 'MosbatAI/1.0 (+server)',
        ]);
        $body    = curl_exec($ch);
        $status  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ctype   = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $err     = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['ok' => false, 'url' => '', 'error' => "network error: {$err}"];
        }
        if ($status >= 400 || $body === false || $body === '') {
            return ['ok' => false, 'url' => '', 'error' => "provider returned HTTP {$status}"];
        }
        // If it's not an image (e.g. a JSON error page), bail with the message
        if (stripos($ctype, 'image/') === false) {
            $snippet = substr(is_string($body) ? $body : '', 0, 160);
            return ['ok' => false, 'url' => '', 'error' => "provider did not return an image: {$snippet}"];
        }

        // Pick extension from content-type
        $ext = 'jpg';
        if (stripos($ctype, 'png') !== false)  $ext = 'png';
        if (stripos($ctype, 'webp') !== false) $ext = 'webp';

        $dir = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'generated';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return ['ok' => false, 'url' => '', 'error' => 'cannot write to public/generated/ (check permissions)'];
        }

        $name = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = $dir . DIRECTORY_SEPARATOR . $name;

        if (file_put_contents($path, $body) === false) {
            return ['ok' => false, 'url' => '', 'error' => 'failed to save image file'];
        }

        return ['ok' => true, 'url' => '/generated/' . $name, 'error' => ''];
    }

    private function parseSize(string $size): array
    {
        if (preg_match('/^(\d+)x(\d+)$/', $size, $m)) {
            $w = min(max((int) $m[1], 256), 1536);
            $h = min(max((int) $m[2], 256), 1536);
            return [$w, $h];
        }
        return [1024, 1024];
    }
}
