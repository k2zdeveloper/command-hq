<?php

namespace App\Libraries;

/**
 * FacebookService — posts photos and text to a Facebook Page via Graph API.
 *
 * Credentials stored in .env:
 *   facebook.pageId          — the Page ID
 *   facebook.pageAccessToken — long-lived or short-lived Page Access Token
 */
class FacebookService
{
    private string $pageId;
    private string $accessToken;
    private string $apiVersion = 'v19.0';
    private string $baseUrl    = 'https://graph.facebook.com';

    public function __construct()
    {
        $this->pageId      = trim((string)(env('facebook.pageId')      ?: ''), "\"' \t");
        $this->accessToken = trim((string)(env('facebook.pageAccessToken') ?: ''), "\"' \t");
    }

    public function isConfigured(): bool
    {
        return $this->pageId !== '' && $this->accessToken !== '';
    }

    /**
     * Post a photo with a caption to the Facebook Page.
     * $imageSource can be a public URL or an absolute local file path.
     */
    public function postPhoto(string $imageSource, string $message): array
    {
        $imageSource = trim($imageSource);
        // Agents sometimes copy the whole "IMAGE_URL: https://..." line — strip the label.
        $imageSource = preg_replace('/^IMAGE_URL:\s*/i', '', $imageSource);
        $imageSource = trim($imageSource);
        $endpoint    = "{$this->baseUrl}/{$this->apiVersion}/{$this->pageId}/photos";

        // Prefer uploading the bytes ourselves. Asking Facebook to fetch a URL
        // is unreliable for freshly-created images (it may 'Missing or invalid
        // image file' before the file is globally available). Downloading the
        // bytes and uploading them as a multipart 'source' avoids that race.
        $tmpFile = null;
        $localFile = null;

        if (filter_var($imageSource, FILTER_VALIDATE_URL)) {
            $bytes = $this->download($imageSource);
            if ($bytes !== null) {
                $ext     = $this->extFromUrl($imageSource);
                $tmpFile = tempnam(sys_get_temp_dir(), 'fbimg_') . '.' . $ext;
                file_put_contents($tmpFile, $bytes);
                $localFile = $tmpFile;
            }
        } elseif (is_file($imageSource)) {
            $localFile = $imageSource;
        }

        $ch = curl_init($endpoint);

        if ($localFile !== null && is_file($localFile)) {
            // Multipart upload of the actual image bytes — most reliable.
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'source'       => new \CURLFile($localFile),
                'message'      => $message,
                'access_token' => $this->accessToken,
            ]);
        } elseif (filter_var($imageSource, FILTER_VALIDATE_URL)) {
            // Fallback: let Facebook fetch the URL.
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'url'          => $imageSource,
                'message'      => $message,
                'access_token' => $this->accessToken,
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        } else {
            return ['ok' => false, 'error' => "Image not found or unreadable: {$imageSource}"];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($tmpFile !== null) {
            @unlink($tmpFile);
        }

        if ($err) {
            log_message('error', "FacebookService: curl error — {$err}");
            return ['ok' => false, 'error' => $err];
        }

        $data = json_decode($body, true);

        if ($status === 200 && (!empty($data['post_id']) || !empty($data['id']))) {
            $pid = $data['post_id'] ?? $data['id'];
            log_message('info', "FacebookService: photo posted — {$pid}");
            return ['ok' => true, 'post_id' => $pid, 'id' => $data['id'] ?? ''];
        }

        $errMsg = $data['error']['message'] ?? $body;
        log_message('error', "FacebookService: photo post failed [{$status}] — {$errMsg}");
        return ['ok' => false, 'error' => $errMsg, 'status' => $status];
    }

    /**
     * Download a URL's bytes via cURL, with retries. A freshly-generated image
     * (e.g. just uploaded to Supabase Storage) can take a moment to become
     * fetchable, so we retry a few times before giving up.
     * Returns null only if all attempts fail.
     */
    private function download(string $url, int $attempts = 4): ?string
    {
        for ($i = 1; $i <= $attempts; $i++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => false, // our own image; avoid XAMPP CA-bundle issues
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_USERAGENT      => 'MosbatAI/1.0',
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            // A valid image is non-trivial in size; tiny bodies are error JSON.
            if ($body !== false && $code >= 200 && $code < 300 && strlen($body) > 512) {
                return $body;
            }
            log_message('warning', "FacebookService: image download attempt {$i}/{$attempts} failed "
                . "[{$code}] {$err} (" . strlen((string) $body) . " bytes) — {$url}");
            if ($i < $attempts) {
                sleep(2); // wait for the just-uploaded object to become available
            }
        }
        return null;
    }

    /** Best-effort image extension from a URL path (defaults to jpg). */
    private function extFromUrl(string $url): string
    {
        $ext = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? $ext : 'jpg';
    }

    /**
     * Read basic Page insights for the daily board report.
     * Returns follower count + the engagement of recent posts.
     * Requires a Page Access Token with `read_insights` / `pages_read_engagement`.
     *
     * @return array{ok: bool, data?: array, error?: string}
     */
    public function getPageInsights(int $recentPosts = 5): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Facebook not configured (no Page Access Token in .env)'];
        }

        // 1) Page-level: name + follower/fan count
        $pageUrl = "{$this->baseUrl}/{$this->apiVersion}/{$this->pageId}"
            . "?fields=name,fan_count,followers_count&access_token=" . urlencode($this->accessToken);
        $page = $this->getJson($pageUrl);
        if (!($page['ok'] ?? false)) {
            return ['ok' => false, 'error' => $page['error'] ?? 'page fetch failed'];
        }

        // 2) Recent posts + their reach/engagement
        $postsUrl = "{$this->baseUrl}/{$this->apiVersion}/{$this->pageId}/posts"
            . "?fields=message,created_time,shares,"
            . "insights.metric(post_impressions,post_engaged_users)"
            . "&limit={$recentPosts}&access_token=" . urlencode($this->accessToken);
        $posts = $this->getJson($postsUrl);

        $recent = [];
        foreach (($posts['data']['data'] ?? []) as $p) {
            $impr = $eng = null;
            foreach (($p['insights']['data'] ?? []) as $m) {
                if ($m['name'] === 'post_impressions')  $impr = $m['values'][0]['value'] ?? null;
                if ($m['name'] === 'post_engaged_users') $eng  = $m['values'][0]['value'] ?? null;
            }
            $recent[] = [
                'date'        => $p['created_time'] ?? '',
                'excerpt'     => mb_substr((string)($p['message'] ?? '(no text)'), 0, 80),
                'impressions' => $impr,
                'engaged'     => $eng,
                'shares'      => $p['shares']['count'] ?? 0,
            ];
        }

        return ['ok' => true, 'data' => [
            'name'      => $page['data']['name']            ?? '',
            'followers' => $page['data']['followers_count'] ?? ($page['data']['fan_count'] ?? null),
            'recent'    => $recent,
        ]];
    }

    /** GET a Graph API URL and decode JSON. */
    private function getJson(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($err)            return ['ok' => false, 'error' => $err];
        $data = json_decode($body, true);
        if (!is_array($data)) return ['ok' => false, 'error' => 'invalid response'];
        if ($status >= 400)  return ['ok' => false, 'error' => $data['error']['message'] ?? "HTTP {$status}"];

        return ['ok' => true, 'data' => $data];
    }

    /**
     * Post text-only to the Facebook Page feed (no image).
     */
    public function postText(string $message): array
    {
        $endpoint = "{$this->baseUrl}/{$this->apiVersion}/{$this->pageId}/feed";

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POSTFIELDS     => http_build_query([
                'message'      => $message,
                'access_token' => $this->accessToken,
            ]),
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($err) {
            return ['ok' => false, 'error' => $err];
        }

        $data = json_decode($body, true);

        if ($status === 200 && !empty($data['id'])) {
            log_message('info', "FacebookService: text posted — id={$data['id']}");
            return ['ok' => true, 'id' => $data['id']];
        }

        $errMsg = $data['error']['message'] ?? $body;
        log_message('error', "FacebookService: text post failed [{$status}] — {$errMsg}");
        return ['ok' => false, 'error' => $errMsg, 'status' => $status];
    }
}
