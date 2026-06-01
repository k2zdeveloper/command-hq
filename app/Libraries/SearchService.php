<?php

namespace App\Libraries;

/**
 * SearchService
 * ─────────────────────────────────────────────────────────────
 * Wraps the Serper.dev Google Search API.
 * Agents call this via [SEARCH] tool tags.
 *
 * .env:
 *   serper.apiKey = YOUR_KEY_HERE
 *
 * Sign up free at: https://serper.dev
 * Free tier: 2,500 searches/month
 * Paid:      $50/month for 50,000 searches ($0.001 each)
 */
class SearchService
{
    private string $apiKey;
    private string $endpoint = 'https://google.serper.dev/search';

    public function __construct()
    {
        $this->apiKey = (string) getenv('serper.apiKey');
    }

    /**
     * Search Google and return formatted results for Claude.
     */
    public function search(string $query, int $numResults = 8): string
    {
        if (empty($this->apiKey)) {
            return "⚠ Web search unavailable — serper.apiKey not set in .env";
        }

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'X-API-KEY: '    . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'q'   => $query,
                'num' => min($numResults, 10),
                'gl'  => 'us',
                'hl'  => 'en',
            ]),
        ]);

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            log_message('error', 'SearchService curl error: ' . $error);
            return "⚠ Search failed — network error: {$error}";
        }

        if ($status >= 400 || !$response) {
            log_message('error', "SearchService HTTP {$status} for query: {$query}");
            return "⚠ Search failed — API returned status {$status}";
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return "⚠ Search failed — invalid API response";
        }

        return $this->formatResults($query, $data);
    }

    // ────────────────────────────────────────────────────────────────────

    private function formatResults(string $query, array $data): string
    {
        $lines = [];
        $lines[] = "═══ Search Results: \"{$query}\" ═══";
        $lines[] = '';

        // Direct answer box (when Google gives an instant answer)
        if (!empty($data['answerBox'])) {
            $box = $data['answerBox'];
            $answer = $box['answer'] ?? $box['snippet'] ?? null;
            if ($answer) {
                $lines[] = "📌 DIRECT ANSWER: {$answer}";
                $lines[] = '';
            }
        }

        // Knowledge graph (for people, companies, etc.)
        if (!empty($data['knowledgeGraph']['description'])) {
            $kg = $data['knowledgeGraph'];
            $lines[] = "ℹ {$kg['title']}: {$kg['description']}";
            $lines[] = '';
        }

        // Organic search results
        $organic = $data['organic'] ?? [];
        if (empty($organic)) {
            $lines[] = "No organic results found.";
            return implode("\n", $lines);
        }

        foreach (array_slice($organic, 0, 8) as $i => $result) {
            $n = $i + 1;
            $title   = $result['title']   ?? 'No title';
            $link    = $result['link']    ?? '';
            $snippet = $result['snippet'] ?? 'No description available.';

            $lines[] = "{$n}. {$title}";
            $lines[] = "   🔗 {$link}";
            $lines[] = "   {$snippet}";
            $lines[] = '';
        }

        // Top stories if available
        if (!empty($data['topStories'])) {
            $lines[] = "── Recent News ──";
            foreach (array_slice($data['topStories'], 0, 3) as $story) {
                $lines[] = "• {$story['title']} — {$story['source']}";
                $lines[] = "  🔗 {$story['link']}";
            }
            $lines[] = '';
        }

        $lines[] = "═══ End of search results ═══";

        return implode("\n", $lines);
    }
}
