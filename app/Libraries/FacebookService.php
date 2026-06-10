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
