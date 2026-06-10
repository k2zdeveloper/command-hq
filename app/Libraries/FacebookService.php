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

    /**
     * @param string $envPrefix Which .env keys to read, so each company can
     *   have its own page. Default 'facebook' → facebook.pageId /
     *   facebook.pageAccessToken. A second company could use e.g. 'facebook_k2z'
     *   → facebook_k2z.pageId / facebook_k2z.pageAccessToken.
     */
    public function __construct(string $envPrefix = 'facebook')
    {
        $this->pageId      = trim((string)(env("{$envPrefix}.pageId")          ?: ''), "\"' \t");
        $this->accessToken = trim((string)(env("{$envPrefix}.pageAccessToken") ?: ''), "\"' \t");
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

        $ch = curl_init($endpoint);

        if (filter_var($imageSource, FILTER_VALIDATE_URL)) {
            // A just-generated image may not be globally fetchable yet — wait
            // until it actually resolves, then let Facebook fetch the URL
            // (the url method uses the same transport as text posts, which works
            // reliably; a multipart byte-upload misbehaves in some web contexts).
            if (!$this->waitUntilAvailable($imageSource)) {
                return ['ok' => false, 'error' => 'image could not be retrieved (storage not ready / 404): ' . $imageSource];
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'url'          => $imageSource,
                'message'      => $message,
                'access_token' => $this->accessToken,
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        } elseif (is_file($imageSource)) {
            // Local file on disk — multipart upload of the bytes.
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'source'       => new \CURLFile($imageSource),
                'message'      => $message,
                'access_token' => $this->accessToken,
            ]);
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
     * Confirm an image URL actually resolves to a real image before we ask
     * Facebook to fetch it. A freshly-generated image (just uploaded to
     * Supabase Storage) can 404 for a moment, so we retry a few times.
     * Returns true once it responds 200 with a non-trivial body.
     */
    private function waitUntilAvailable(string $url, int $attempts = 5): bool
    {
        for ($i = 1; $i <= $attempts; $i++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_RANGE          => '0-2048', // just need to confirm it exists
                CURLOPT_USERAGENT      => 'MosbatAI/1.0',
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body !== false && $code >= 200 && $code < 400 && strlen($body) > 512) {
                return true;
            }
            log_message('warning', "FacebookService: image not ready attempt {$i}/{$attempts} "
                . "[{$code}] (" . strlen((string) $body) . " bytes) — {$url}");
            if ($i < $attempts) {
                sleep(2);
            }
        }
        return false;
    }

    /**
     * Read Page engagement metrics for the daily board report:
     *   • followers
     *   • 28-day page reach + engagement
     *   • per-post reach / reactions / clicks / shares
     *   • the top posts by reach
     *
     * Uses the CURRENT (non-deprecated) Graph metric names:
     *   page:  page_impressions_unique (reach), page_post_engagements
     *   post:  post_impressions_unique (reach), post_clicks,
     *          post_reactions_by_type_total (reactions)
     * Requires a Page token with `read_insights` + `pages_read_engagement`.
     *
     * @return array{ok: bool, data?: array, error?: string}
     */
    public function getPageInsights(int $recentPosts = 8): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Facebook not configured (no Page Access Token in .env)'];
        }
        $tok = urlencode($this->accessToken);
        $base = "{$this->baseUrl}/{$this->apiVersion}/{$this->pageId}";

        // 1) Page basics: name + followers
        $page = $this->getJson("{$base}?fields=name,fan_count,followers_count&access_token={$tok}");
        if (!($page['ok'] ?? false)) {
            return ['ok' => false, 'error' => $page['error'] ?? 'page fetch failed'];
        }

        // 2) Page-level reach + engagement over the last 28 days
        $reach28 = $eng28 = null;
        $pm = $this->getJson("{$base}/insights?metric=page_impressions_unique,page_post_engagements&period=days_28&access_token={$tok}");
        foreach (($pm['data']['data'] ?? []) as $m) {
            $v = end($m['values'])['value'] ?? null;  // most recent value
            if ($m['name'] === 'page_impressions_unique') $reach28 = $v;
            if ($m['name'] === 'page_post_engagements')   $eng28   = $v;
        }

        // 3) Recent posts, then per-post insights
        $posts = $this->getJson("{$base}/posts?fields=message,created_time,shares&limit={$recentPosts}&access_token={$tok}");
        $recent = [];
        foreach (($posts['data']['data'] ?? []) as $p) {
            $pid = $p['id'] ?? null;
            $reach = $clicks = $reactions = 0;
            if ($pid) {
                $pi = $this->getJson("{$this->baseUrl}/{$this->apiVersion}/{$pid}/insights"
                    . "?metric=post_impressions_unique,post_clicks,post_reactions_by_type_total&access_token={$tok}");
                foreach (($pi['data']['data'] ?? []) as $m) {
                    $val = $m['values'][0]['value'] ?? 0;
                    if ($m['name'] === 'post_impressions_unique') $reach  = (int) $val;
                    if ($m['name'] === 'post_clicks')             $clicks = (int) $val;
                    if ($m['name'] === 'post_reactions_by_type_total') {
                        $reactions = is_array($val) ? array_sum($val) : (int) $val;
                    }
                }
            }
            $recent[] = [
                'date'       => $p['created_time'] ?? '',
                'excerpt'    => mb_substr((string) ($p['message'] ?? '(no text)'), 0, 70),
                'reach'      => $reach,
                'reactions'  => $reactions,
                'clicks'     => $clicks,
                'shares'     => $p['shares']['count'] ?? 0,
                'engagement' => $reactions + $clicks + (int) ($p['shares']['count'] ?? 0),
            ];
        }

        // 4) Top posts by reach (then engagement)
        $top = $recent;
        usort($top, static fn ($a, $b) => ($b['reach'] <=> $a['reach']) ?: ($b['engagement'] <=> $a['engagement']));
        $top = array_slice($top, 0, 3);

        return ['ok' => true, 'data' => [
            'name'           => $page['data']['name']            ?? '',
            'followers'      => $page['data']['followers_count'] ?? ($page['data']['fan_count'] ?? 0),
            'reach_28d'      => $reach28,
            'engagement_28d' => $eng28,
            'recent'         => $recent,
            'top'            => $top,
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
