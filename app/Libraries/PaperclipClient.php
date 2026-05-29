<?php

namespace App\Libraries;

use Config\Companies;
use CodeIgniter\HTTP\CURLRequest;

/**
 * Thin client around Paperclip's REST API.
 *
 * Every method takes a company key (e.g. 'k2z'); the client looks up the
 * company's ID, CEO agent ID, and API key from Config\Companies and makes
 * the right call.
 *
 * Usage:
 *   $client = new PaperclipClient();
 *   $client->createIssue('k2z', 'Fix the login bug', 'Users cannot sign in...');
 *   $issues = $client->listIssues('k2z');
 */
class PaperclipClient
{
    protected Companies $config;
    protected CURLRequest $http;

    public function __construct()
    {
        $this->config = config('Companies');
        $this->http   = \Config\Services::curlrequest([
            'timeout'         => 15,
            'connect_timeout' => 5,
            'http_errors'     => false,
        ]);
    }

    /**
     * Create a new issue in the given company, assigned to that company's CEO.
     *
     * @return array{ok: bool, status: int, data: mixed}
     */
    public function createIssue(string $companyKey, string $title, string $description, string $priority = 'normal'): array
    {
        $company = $this->config->get($companyKey);
        if (! $company) {
            return ['ok' => false, 'status' => 0, 'data' => ['error' => 'Unknown company: ' . $companyKey]];
        }

        $url = sprintf(
            '%s/api/companies/%s/issues',
            rtrim($this->config->baseUrl, '/'),
            $company['id']
        );

        $payload = [
            'title'            => $title,
            'description'      => $description,
            'status'           => 'todo',
            'assigneeAgentId'  => $company['ceoAgentId'],
            'priority'         => $priority,
        ];

        return $this->request('POST', $url, $company['apiKey'], $payload);
    }

    /**
     * List issues for a company. Optionally filter by status.
     */
    public function listIssues(string $companyKey, ?string $status = null): array
    {
        $company = $this->config->get($companyKey);
        if (! $company) {
            return ['ok' => false, 'status' => 0, 'data' => ['error' => 'Unknown company']];
        }

        $url = sprintf(
            '%s/api/companies/%s/issues',
            rtrim($this->config->baseUrl, '/'),
            $company['id']
        );

        if ($status !== null) {
            $url .= '?status=' . urlencode($status);
        }

        return $this->request('GET', $url, $company['apiKey']);
    }

    /**
     * Get full details of one issue.
     */
    public function getIssue(string $companyKey, string $issueId): array
    {
        $company = $this->config->get($companyKey);
        if (! $company) {
            return ['ok' => false, 'status' => 0, 'data' => ['error' => 'Unknown company']];
        }

        $url = sprintf('%s/api/issues/%s', rtrim($this->config->baseUrl, '/'), $issueId);

        return $this->request('GET', $url, $company['apiKey']);
    }

    /**
     * Get all agent comments for an issue.
     * Returns array of comment objects: {id, body, authorType, createdAt, ...}
     */
    public function getIssueComments(string $companyKey, string $issueId): array
    {
        $company = $this->config->get($companyKey);
        if (! $company) {
            return ['ok' => false, 'status' => 0, 'data' => ['error' => 'Unknown company']];
        }

        $url = sprintf('%s/api/issues/%s/comments', rtrim($this->config->baseUrl, '/'), $issueId);

        return $this->request('GET', $url, $company['apiKey']);
    }

    /**
     * Cancel an issue by setting its status to 'cancelled'.
     * Uses CI4's own CURLRequest (same SSL config that works for GET calls) but
     * without a manual Content-Type header — the json option sets it automatically,
     * avoiding the header conflict that garbled the body on PATCH.
     */
    public function cancelIssue(string $companyKey, string $issueId): array
    {
        $company = $this->config->get($companyKey);
        if (! $company) {
            return ['ok' => false, 'status' => 0, 'data' => ['error' => 'Unknown company']];
        }

        $url = sprintf('%s/api/issues/%s', rtrim($this->config->baseUrl, '/'), $issueId);

        try {
            $response = $this->http->request('PATCH', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $company['apiKey'],
                    'Accept'        => 'application/json',
                ],
                'json' => ['status' => 'cancelled'],
            ]);

            $status = $response->getStatusCode();
            $raw    = (string) $response->getBody();
            $data   = json_decode($raw, true);
            $ok     = $status >= 200 && $status < 300;

            if (! $ok) {
                log_message('error', '[Paperclip] cancelIssue HTTP ' . $status . ' — ' . $raw);
            }

            return [
                'ok'     => $ok,
                'status' => $status,
                'data'   => $data !== null ? $data : ['raw' => $raw],
            ];
        } catch (\Throwable $e) {
            log_message('error', '[Paperclip] cancelIssue exception: ' . $e->getMessage());
            return ['ok' => false, 'status' => 0, 'data' => ['error' => $e->getMessage()]];
        }
    }

    /**
     * List all attachments for an issue. Returns a normalised array of attachment objects.
     */
    public function listIssueAttachments(string $companyKey, string $issueId): array
    {
        $company = $this->config->get($companyKey);
        if (! $company) {
            return ['ok' => false, 'data' => []];
        }

        $url    = sprintf('%s/api/issues/%s/attachments', rtrim($this->config->baseUrl, '/'), $issueId);
        $result = $this->request('GET', $url, $company['apiKey']);

        if (! $result['ok']) {
            return ['ok' => false, 'data' => []];
        }

        $data = $result['data'];
        // API may return a single object or an array
        if (isset($data['id'])) {
            $data = [$data];
        }

        return ['ok' => true, 'data' => is_array($data) ? $data : []];
    }

    /**
     * Fetch the raw binary content of an attachment.
     * Returns ['ok', 'contentType', 'body'] — body is the raw string.
     */
    public function fetchAttachmentContent(string $companyKey, string $attachmentId): array
    {
        $company = $this->config->get($companyKey);
        if (! $company) {
            return ['ok' => false, 'contentType' => '', 'body' => ''];
        }

        $url = sprintf('%s/api/attachments/%s/content', rtrim($this->config->baseUrl, '/'), $attachmentId);

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $company['apiKey'],
            ],
        ];

        try {
            $response    = $this->http->request('GET', $url, $options);
            $status      = $response->getStatusCode();
            $contentType = $response->getHeader('Content-Type') ?? 'application/octet-stream';
            $body        = (string) $response->getBody();

            return [
                'ok'          => $status >= 200 && $status < 300,
                'contentType' => is_object($contentType) ? $contentType->getValue() : (string) $contentType,
                'body'        => $body,
            ];
        } catch (\Throwable $e) {
            log_message('error', '[Paperclip] attachment fetch failed: ' . $e->getMessage());
            return ['ok' => false, 'contentType' => '', 'body' => ''];
        }
    }

    /**
     * Health check — pings each company's API to confirm we can reach them.
     */
    public function health(): array
    {
        $out = [];
        foreach ($this->config->all() as $company) {
            $result = $this->listIssues($company['key']);
            $out[$company['key']] = [
                'ok'         => $result['ok'],
                'statusCode' => $result['status'],
            ];
        }
        return $out;
    }

    /**
     * Low-level HTTP call. Returns a consistent shape.
     */
    protected function request(string $method, string $url, string $apiKey, array $body = []): array
    {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ];

        if (! empty($body) && $method !== 'GET') {
            $options['json'] = $body;
        }

        try {
            $response = $this->http->request($method, $url, $options);
            $status   = $response->getStatusCode();
            $raw      = (string) $response->getBody();
            $data     = json_decode($raw, true);

            return [
                'ok'     => $status >= 200 && $status < 300,
                'status' => $status,
                'data'   => $data !== null ? $data : ['raw' => $raw],
            ];
        } catch (\Throwable $e) {
            log_message('error', '[Paperclip] ' . $e->getMessage());
            return [
                'ok'     => false,
                'status' => 0,
                'data'   => ['error' => $e->getMessage()],
            ];
        }
    }
}
