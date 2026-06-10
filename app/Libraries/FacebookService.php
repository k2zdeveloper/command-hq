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
        $endpoint = "{$this->baseUrl}/{$this->apiVersion}/{$this->pageId}/photos";

        $ch = curl_init($endpoint);

        if (filter_var($imageSource, FILTER_VALIDATE_URL)) {
            // Public URL — pass directly
            $fields = [
                'url'          => $imageSource,
                'message'      => $message,
                'access_token' => $this->accessToken,
            ];
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        } else {
            // Local file — multipart upload
            $fields = [
                'source'       => new \CURLFile($imageSource),
                'message'      => $message,
                'access_token' => $this->accessToken,
            ];
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($err) {
            log_message('error', "FacebookService: curl error — {$err}");
            return ['ok' => false, 'error' => $err];
        }

        $data = json_decode($body, true);

        if ($status === 200 && !empty($data['post_id'])) {
            log_message('info', "FacebookService: photo posted — post_id={$data['post_id']}");
            return ['ok' => true, 'post_id' => $data['post_id'], 'id' => $data['id'] ?? ''];
        }

        $errMsg = $data['error']['message'] ?? $body;
        log_message('error', "FacebookService: post failed [{$status}] — {$errMsg}");
        return ['ok' => false, 'error' => $errMsg, 'status' => $status];
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
