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
        $payload = json_encode([
            'model'       => $modelOverride ?: $this->model,
            'max_tokens'  => $this->maxTokens,
            'temperature' => $temperature,
            'system'      => $systemPrompt,
            'messages'    => $messages,
        ]);

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
        $payload = json_encode([
            'model'       => $modelOverride ?: $this->model,
            'max_tokens'  => $this->maxTokens,
            'temperature' => $temperature,
            'system'      => $systemPrompt,
            'messages'    => $messages,
            'stream'      => true,
        ]);

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
     * Returns the filesystem path for a task cancellation signal file.
     * Written by TaskApi::cancel, read by AgentRun's abort callback.
     */
    public static function cancelSignalPath(string $taskId): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mosbat_cancel_' . preg_replace('/[^a-z0-9_-]/i', '', $taskId);
    }
}
