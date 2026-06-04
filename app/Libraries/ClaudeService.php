<?php

namespace App\Libraries;

use RuntimeException;

/**
 * ClaudeService
 * -------------------------------------------------------------
 * Server-side proxy to the Anthropic Messages API.
 *
 *   POST https://api.anthropic.com/v1/messages
 *
 * Uses native PHP cURL directly to avoid CI4's shared-service
 * cache, which would inherit SupabaseModel's 15-second timeout.
 *
 * The API key NEVER leaves the server.
 */
class ClaudeService
{
    private string $apiKey;
    private string $model;
    private string $version;
    private string $endpoint;
    private int    $maxTokens;

    public function __construct()
    {
        $this->apiKey    = (string) getenv('anthropic.apiKey');
        $this->model     = (string) (getenv('anthropic.model')      ?: 'claude-sonnet-4-6');
        $this->version   = (string) (getenv('anthropic.apiVersion') ?: '2023-06-01');
        $this->endpoint  = (string) (getenv('anthropic.endpoint')   ?: 'https://api.anthropic.com/v1/messages');
        $this->maxTokens = (int)    (getenv('anthropic.maxTokens')  ?: 1500);

        if ($this->apiKey === '') {
            throw new RuntimeException('Anthropic API key missing in .env (anthropic.apiKey).');
        }
    }

    /**
     * Send a chat completion request.
     *
     * @param string      $systemPrompt  Fully-composed system prompt (role + skills).
     * @param array       $messages      [['role' => 'user'|'assistant', 'content' => '...'], ...]
     * @param float       $temperature
     * @param string|null $modelOverride
     *
     * @return array {text: string, usage: array, raw: array}
     */
    public function chat(
        string  $systemPrompt,
        array   $messages,
        float   $temperature = 0.7,
        ?string $modelOverride = null
    ): array {
        $model   = $modelOverride ?: $this->model;
        $payload = json_encode([
            'model'      => $model,
            'max_tokens' => $this->maxTokens,
            // System prompt sent as a cacheable block. It's large (role +
            // roster + skills + tool docs) and byte-stable across turns of a
            // conversation, so Anthropic prompt caching serves it at ~0.1×
            // input cost on every follow-up turn within the 5-min TTL.
            'system'     => [[
                'type'          => 'text',
                'text'          => $systemPrompt,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages'   => $messages,
            // temperature is only included for models that still accept it —
            // Opus 4.7+ removed sampling params and 400 if they're sent.
        ] + $this->samplingParams($model, $temperature));

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: '         . $this->apiKey,
                'anthropic-version: ' . $this->version,
                'content-type: application/json',
            ],
        ]);

        $body  = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err   = curl_error($ch);
        curl_close($ch);

        if ($body === false || $err !== '') {
            log_message('error', 'Claude cURL error: ' . $err);
            throw new RuntimeException('Claude request failed: ' . $err);
        }

        $data = json_decode($body, true);

        if ($status >= 400 || !is_array($data)) {
            log_message('error', 'Claude API error [' . $status . ']: ' . $body);
            throw new RuntimeException(
                'Claude API error (' . $status . '): ' .
                ($data['error']['message'] ?? 'unknown')
            );
        }

        $text = '';
        foreach (($data['content'] ?? []) as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        return [
            'text'  => trim($text),
            'usage' => $data['usage'] ?? [],
            'raw'   => $data,
        ];
    }

    /**
     * Streaming version — calls $onChunk(string $text) for each token
     * as it arrives, then returns the full text + usage when done.
     *
     * @param callable|null $onChunk     Called with each text chunk as it streams in.
     * @param callable|null $shouldAbort Called every ~2 seconds during streaming.
     *                                   If it returns true, the stream is aborted immediately.
     *                                   Use this for cancellation signal checks.
     */
    public function chatStream(
        string    $systemPrompt,
        array     $messages,
        float     $temperature = 0.7,
        ?string   $modelOverride = null,
        ?callable $onChunk = null,
        ?callable $shouldAbort = null
    ): array {
        $model   = $modelOverride ?: $this->model;
        $payload = json_encode([
            'model'      => $model,
            'max_tokens' => $this->maxTokens,
            // Cacheable system block — see chat() for rationale.
            'system'     => [[
                'type'          => 'text',
                'text'          => $systemPrompt,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages'   => $messages,
            'stream'     => true,
        ] + $this->samplingParams($model, $temperature));

        $buffer    = '';
        $fullText  = '';
        $usage     = [];
        $lastCheck = time();
        $aborted   = false;

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: '         . $this->apiKey,
                'anthropic-version: ' . $this->version,
                'content-type: application/json',
            ],
            CURLOPT_WRITEFUNCTION  => function ($ch, $data) use (
                &$buffer, &$fullText, &$usage, &$lastCheck, &$aborted,
                $onChunk, $shouldAbort
            ) {
                // ── Abort check every 2 seconds ─────────────────────────
                if ($shouldAbort && (time() - $lastCheck >= 2)) {
                    $lastCheck = time();
                    if ($shouldAbort()) {
                        $aborted = true;
                        return 0; // Returning 0 aborts the curl transfer immediately
                    }
                }

                $buffer .= $data;
                while (($nl = strpos($buffer, "\n")) !== false) {
                    $line   = substr($buffer, 0, $nl);
                    $buffer = substr($buffer, $nl + 1);
                    if (!str_starts_with($line, 'data: ')) {
                        continue;
                    }
                    $json = trim(substr($line, 6));
                    if ($json === '' || $json === '[DONE]') {
                        continue;
                    }
                    $event = json_decode($json, true);
                    if (!is_array($event)) {
                        continue;
                    }
                    if ($event['type'] === 'content_block_delta'
                        && ($event['delta']['type'] ?? '') === 'text_delta'
                    ) {
                        $chunk     = $event['delta']['text'] ?? '';
                        $fullText .= $chunk;
                        if ($onChunk) {
                            $onChunk($chunk);
                        }
                    }
                    if ($event['type'] === 'message_delta' && isset($event['usage'])) {
                        $usage = $event['usage'];
                    }
                }
                return strlen($data);
            },
        ]);

        curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        // Abort is not a curl error — it's intentional
        if ($aborted) {
            throw new RuntimeException('__CANCELLED__');
        }

        // A real curl error (network, timeout, etc.)
        if ($err !== '' && !$aborted) {
            throw new RuntimeException('Claude stream error: ' . $err);
        }

        return ['text' => trim($fullText), 'usage' => $usage];
    }

    /**
     * Build the sampling-parameter slice of the payload.
     *
     * Opus 4.7 and later removed `temperature`/`top_p`/`top_k` — sending any of
     * them returns a 400. Everything else (Sonnet 4.6, Haiku 4.5, Opus 4.6 and
     * older) still accepts `temperature`. So we omit it only for the models that
     * reject it, and steer those purely via the system prompt.
     *
     * @return array{temperature?: float}
     */
    private function samplingParams(string $model, float $temperature): array
    {
        if (preg_match('/opus-4-(?:[7-9]|\d{2,})/', $model)) {
            return []; // Opus 4.7+ — no sampling params allowed
        }
        return ['temperature' => $temperature];
    }

    /**
     * Returns the filesystem path for a task cancellation signal file.
     * Written by TaskApi::cancel, read by AgentRun's abort callback.
     */
    public static function cancelSignalPath(string $taskId): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mosbat_cancel_' . preg_replace('/[^a-z0-9_-]/i', '', $taskId);
    }
}
