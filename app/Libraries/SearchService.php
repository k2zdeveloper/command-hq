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
        // env() checks $_ENV → $_SERVER → getenv() — more reliable than getenv() alone on Windows
        $this->apiKey = (string) (env('serper.apiKey') ?: getenv('serper.apiKey') ?: '');
    }

    /**
     * People search: runs a general + LinkedIn query and returns a structured profile.
     *
     * @param string $name     Full name of the person to research
     * @param string $context  Optional narrowing context (e.g. "Philippines", "CEO", "basketball")
     */
    public function searchPerson(string $name, string $context = ''): string
    {
        if (empty($this->apiKey)) {
            return "⚠ People search unavailable — serper.apiKey not set in .env";
        }

        $nameQ = '"' . trim($name) . '"';
        $ctx   = trim($context);

        // 1) General profile search
        $generalQuery  = $ctx ? "{$nameQ} {$ctx}" : "{$nameQ} profile biography";
        $generalResult = $this->rawSearch($generalQuery, 8, 'search');

        // 2) LinkedIn
        $linkedinQuery  = "{$nameQ} site:linkedin.com/in" . ($ctx ? " {$ctx}" : '');
        $linkedinResult = $this->rawSearch($linkedinQuery, 3, 'search');

        // 3) Recent news
        $newsResult = $this->rawSearch("{$nameQ}" . ($ctx ? " {$ctx}" : ''), 3, 'news');

        return $this->formatPersonProfile($name, $ctx, $generalResult, $linkedinResult, $newsResult);
    }

    private function rawSearch(string $query, int $num, string $type = 'search'): array
    {
        $endpoint = $type === 'news'
            ? 'https://google.serper.dev/news'
            : $this->endpoint;

        $ch = curl_init($endpoint);
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
                'num' => min($num, 10),
                'gl'  => 'us',
                'hl'  => 'en',
            ]),
        ]);
        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 400 || !$response) {
            return [];
        }
        return json_decode($response, true) ?? [];
    }

    private function formatPersonProfile(string $name, string $context, array $general, array $linkedin, array $news): string
    {
        $lines = [];
        $lines[] = "╔══ PERSON PROFILE: {$name}" . ($context ? " ({$context})" : '') . " ══╗";
        $lines[] = '';

        // Knowledge graph — best source for public figures
        if (!empty($general['knowledgeGraph'])) {
            $kg = $general['knowledgeGraph'];
            if (!empty($kg['title']))       $lines[] = "👤 {$kg['title']}" . (!empty($kg['type']) ? " — {$kg['type']}" : '');
            if (!empty($kg['description'])) $lines[] = "   {$kg['description']}";
            if (!empty($kg['attributes'])) {
                foreach (array_slice($kg['attributes'], 0, 6) as $k => $v) {
                    $lines[] = "   {$k}: {$v}";
                }
            }
            $lines[] = '';
        }

        // Direct answer box
        if (!empty($general['answerBox'])) {
            $box = $general['answerBox'];
            $ans = $box['answer'] ?? $box['snippet'] ?? null;
            if ($ans) {
                $lines[] = "📌 Quick Answer: {$ans}";
                $lines[] = '';
            }
        }

        // LinkedIn results
        $liOrganic = $linkedin['organic'] ?? [];
        if (!empty($liOrganic)) {
            $lines[] = "🔗 LinkedIn:";
            foreach (array_slice($liOrganic, 0, 2) as $r) {
                $lines[] = "   • {$r['title']}";
                if (!empty($r['snippet'])) $lines[] = "     {$r['snippet']}";
                if (!empty($r['link']))    $lines[] = "     {$r['link']}";
            }
            $lines[] = '';
        }

        // General web results
        $organic = $general['organic'] ?? [];
        if (!empty($organic)) {
            $lines[] = "🌐 Web Results:";
            foreach (array_slice($organic, 0, 5) as $i => $r) {
                $lines[] = "   " . ($i + 1) . ". {$r['title']}";
                if (!empty($r['snippet'])) $lines[] = "      {$r['snippet']}";
                if (!empty($r['link']))    $lines[] = "      {$r['link']}";
            }
            $lines[] = '';
        }

        // News
        $newsItems = $news['news'] ?? [];
        if (!empty($newsItems)) {
            $lines[] = "📰 Recent News:";
            foreach (array_slice($newsItems, 0, 4) as $n) {
                $lines[] = "   • {$n['title']} — {$n['source']}";
                if (!empty($n['snippet'])) $lines[] = "     {$n['snippet']}";
                if (!empty($n['link']))    $lines[] = "     {$n['link']}";
            }
            $lines[] = '';
        }

        if (count($lines) <= 3) {
            $lines[] = "No public information found for \"{$name}\".";
        }

        $lines[] = "╚══ END PROFILE ══╝";
        return implode("\n", $lines);
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
