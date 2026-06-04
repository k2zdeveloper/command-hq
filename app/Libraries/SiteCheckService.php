<?php

namespace App\Libraries;

/**
 * SiteCheckService
 * ─────────────────────────────────────────────────────────────
 * Real, dependency-free website QA. Fetches LIVE pages over cURL
 * and reports hard, verifiable facts an agent can turn into a
 * status report:
 *
 *   • Does the page load?        → HTTP status, load time, redirects
 *   • Is it secure?              → HTTPS / SSL certificate validity
 *   • Do forms exist & work?     → <form> count, method, action,
 *                                  field count, submit button, and
 *                                  whether the action endpoint responds
 *   • Any 404s?                  → crawls same-domain links and flags
 *                                  any that return 4xx / 5xx
 *
 * SAFETY: this never SUBMITS a form (that could create real entries
 * on a live site). It verifies a form exists, is well-formed, and
 * that its action endpoint is reachable.
 */
class SiteCheckService
{
    private int $pageTimeout = 20;
    private int $linkTimeout = 8;
    private int $maxLinks    = 25;
    private int $maxPages    = 40;

    /**
     * QA a site.
     *
     * @param string $url   Page (or site) to check.
     * @param string $scope 'page' (default) → deep check of one page.
     *                      'site' → discover every page via sitemap.xml
     *                      (falls back to on-page links) and report each
     *                      page's HTTP status, plus a deep check of $url.
     */
    public function check(string $url, string $scope = 'page'): string
    {
        $url = trim($url);
        if ($url === '') {
            return '⚠ No URL provided to CHECK_SITE.';
        }
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return "⚠ Invalid URL: {$url}";
        }

        $page = $this->fetch($url);
        if ($page['error'] !== '') {
            return "🔴 PAGE UNREACHABLE: {$url}\n   Error: {$page['error']}";
        }

        $ok      = $page['status'] >= 200 && $page['status'] < 400;
        $icon    = $ok ? '🟢' : '🔴';
        $lines   = [];
        $lines[] = "{$icon} PAGE: {$page['final_url']}";
        $lines[] = "   HTTP status : {$page['status']}" . ($ok ? '' : '  ⚠ NOT OK');
        $lines[] = "   Load time   : {$page['time_ms']} ms" . ($page['time_ms'] > 3000 ? '  ⚠ slow (>3s)' : '');
        $lines[] = "   HTTPS/SSL   : " . ($page['is_https']
            ? ($page['ssl_ok'] ? 'valid certificate' : '⚠ INVALID / untrusted certificate')
            : '⚠ not using HTTPS');
        if ($page['redirects'] > 0) {
            $lines[] = "   Redirects   : {$page['redirects']} hop(s) → final {$page['final_url']}";
        }
        $lines[] = "   Page title  : " . ($page['title'] !== '' ? $page['title'] : '⚠ (no <title> tag)');
        $lines[] = "   Page size   : " . round($page['bytes'] / 1024, 1) . " KB";

        // ── Forms ────────────────────────────────────────────────────
        $forms = $this->detectForms($page['body'], $page['final_url']);
        if (empty($forms)) {
            $lines[] = "   Forms       : none found in static HTML"
                . " (note: JS-rendered forms won't appear here — a browser test is needed to confirm)";
        } else {
            $lines[] = "   Forms       : " . count($forms) . " found";
            foreach ($forms as $i => $f) {
                $n = $i + 1;
                $lines[] = "     • Form {$n}: method={$f['method']}, fields={$f['fields']}, "
                    . "submit=" . ($f['has_submit'] ? 'yes' : 'no explicit submit button (may be JS)');
                $lines[] = "       {$f['action_status']}";
            }
        }

        // ── 404 / broken-link scan ──────────────────────────────────
        $lines[] = $this->scanLinks($page['body'], $page['final_url']);

        // ── Site-wide page sweep (sitemap.xml → on-page links) ───────
        if (in_array(strtolower($scope), ['site', 'all', 'full', 'pages'], true)) {
            $lines[] = '';
            $lines[] = $this->siteSweep($page['final_url']);
        }

        return implode("\n", $lines);
    }

    /** Discover every page (sitemap.xml, else on-page links) and check each. */
    private function siteSweep(string $baseUrl): string
    {
        [$pages, $source] = $this->discoverPages($baseUrl);
        if (empty($pages)) {
            return "── SITE-WIDE PAGE SCAN ──\n"
                . "   ⚠ Could not discover pages (no sitemap.xml and no static links — "
                . "common on JS-rendered sites). Provide page URLs to check them individually.";
        }

        $codes  = $this->multiHead($pages);
        $ok = $bad = 0;
        $rows   = [];
        foreach ($pages as $u) {
            $code = $codes[$u] ?? 0;
            $good = $code >= 200 && $code < 400;
            $good ? $ok++ : $bad++;
            $rows[] = '     ' . ($good ? '🟢' : '🔴') . ' HTTP ' . ($code ?: 'no-response') . " — {$u}";
        }

        $head = "── SITE-WIDE PAGE SCAN (" . count($pages) . " pages via {$source}) ──";
        $sum  = $bad === 0
            ? "   🟢 All {$ok} pages return OK — no 404s."
            : "   🔴 {$bad} of " . count($pages) . " pages have problems:";
        // When all good, keep it short; when problems, list everything.
        if ($bad === 0) {
            return $head . "\n" . $sum;
        }
        return $head . "\n" . $sum . "\n" . implode("\n", $rows);
    }

    /** Returns [pageUrls[], sourceLabel]. Tries sitemap.xml, falls back to links. */
    private function discoverPages(string $baseUrl): array
    {
        $origin = $this->origin($baseUrl);
        $pages  = [];

        // 1) sitemap.xml (handles a sitemap index that points to child sitemaps)
        $sm = $this->raw($origin . '/sitemap.xml', false);
        if ($sm['body'] !== false && str_contains($sm['body'], '<loc')) {
            $locs = $this->parseSitemap($sm['body']);
            // sitemap index → each <loc> is another sitemap; fetch a few
            if (str_contains($sm['body'], '<sitemap')) {
                $child = [];
                foreach (array_slice($locs, 0, 5) as $childUrl) {
                    $c = $this->raw($childUrl, false);
                    if ($c['body'] !== false) {
                        $child = array_merge($child, $this->parseSitemap($c['body']));
                    }
                    if (count($child) >= $this->maxPages) {
                        break;
                    }
                }
                $locs = $child;
            }
            foreach ($locs as $u) {
                if ($this->host($u) === $this->host($origin)) {
                    $pages[strtok($u, '#')] = true;
                }
            }
            if (!empty($pages)) {
                return [array_slice(array_keys($pages), 0, $this->maxPages), 'sitemap.xml'];
            }
        }

        // 2) Fallback: internal links on the page
        $home = $this->raw($baseUrl, false);
        if ($home['body'] !== false) {
            foreach ($this->internalLinks($home['body'], $baseUrl) as $u) {
                $pages[$u] = true;
            }
        }
        return [array_slice(array_keys($pages), 0, $this->maxPages), 'on-page links'];
    }

    /** Extract <loc> URLs from a sitemap or sitemap-index XML body. */
    private function parseSitemap(string $xml): array
    {
        if (!preg_match_all('/<loc>\s*([^<\s]+)\s*<\/loc>/i', $xml, $m)) {
            return [];
        }
        return array_map(static fn ($u) => html_entity_decode(trim($u), ENT_QUOTES, 'UTF-8'), $m[1]);
    }

    // ─────────────────────────────────────────────────────────────────────

    /** Fetch a page; auto-detects SSL cert problems and still returns data. */
    private function fetch(string $url): array
    {
        $res     = $this->raw($url, true);
        $isHttps = stripos($url, 'https://') === 0;
        $sslOk   = true;

        // SSL cert errors: 60 (cert verify), 51 (host mismatch), 35 (handshake)
        if ($res['body'] === false && $isHttps && in_array($res['errno'], [60, 51, 35], true)) {
            $sslOk = false;
            $res   = $this->raw($url, false); // retry insecurely so we can still report
        }

        if ($res['body'] === false) {
            return [
                'error'    => $res['error'] ?: 'request failed',
                'is_https' => $isHttps,
            ];
        }

        $info = $res['info'];
        return [
            'error'     => '',
            'status'    => (int) ($info['http_code'] ?? 0),
            'time_ms'   => (int) round(($info['total_time'] ?? 0) * 1000),
            'final_url' => $info['url'] ?? $url,
            'redirects' => (int) ($info['redirect_count'] ?? 0),
            'bytes'     => strlen($res['body']),
            'body'      => $res['body'],
            'is_https'  => $isHttps,
            'ssl_ok'    => $sslOk,
            'title'     => $this->title($res['body']),
        ];
    }

    /** Low-level cURL GET. $verify toggles SSL peer/host verification. */
    private function raw(string $url, bool $verify): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $this->pageTimeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            CURLOPT_USERAGENT      => 'MosbatAI-SiteQA/1.0 (+https://mosbat.ai)',
            CURLOPT_ENCODING       => '',
        ]);
        $body  = curl_exec($ch);
        $info  = curl_getinfo($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        return ['body' => $body, 'info' => $info, 'error' => $error, 'errno' => $errno];
    }

    /** Pull the <title> text. */
    private function title(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
            return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        }
        return '';
    }

    /** Parse <form> tags: method, action (absolute), field count, submit button. */
    private function detectForms(string $html, string $baseUrl): array
    {
        if (!preg_match_all('/<form\b([^>]*)>(.*?)<\/form>/si', $html, $forms, PREG_SET_ORDER)) {
            return [];
        }

        $out = [];
        foreach ($forms as $f) {
            $attrs = $f[1];
            $inner = $f[2];

            $method = 'GET';
            if (preg_match('/\bmethod\s*=\s*["\']?\s*(post|get)/i', $attrs, $mm)) {
                $method = strtoupper($mm[1]);
            }

            $rawAction = '';
            if (preg_match('/\baction\s*=\s*["\']([^"\']*)["\']/i', $attrs, $am)) {
                $rawAction = trim($am[1]);
            }

            $fields    = preg_match_all('/<(input|textarea|select)\b/i', $inner);
            $hasSubmit = (bool) preg_match('/<button\b[^>]*>|<input\b[^>]*type\s*=\s*["\']?submit/i', $inner);

            $out[] = [
                'method'        => $method,
                'fields'        => (int) $fields,
                'has_submit'    => $hasSubmit,
                'action_status' => $this->actionStatus($rawAction, $baseUrl),
            ];
        }
        return $out;
    }

    /**
     * Describe a form's action target.
     *
     * A `#`, empty, anchor, or javascript: action means the form is submitted
     * by JavaScript/AJAX — that is NORMAL on modern sites (Wix, React, etc.),
     * not a broken form. We only probe a real, off-page URL, and even then we
     * never declare it "broken" (a GET to a POST-only handler can 404/405).
     */
    private function actionStatus(string $rawAction, string $baseUrl): string
    {
        if ($rawAction === '' || $rawAction === '#'
            || str_starts_with($rawAction, '#')
            || stripos($rawAction, 'javascript:') === 0
        ) {
            return 'action: submitted via JavaScript/AJAX (no static endpoint) — '
                . 'this is normal; real submission needs a browser test, not a static check';
        }

        $url = $this->absolute($rawAction, $baseUrl);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return "action: {$rawAction} (relative/unverifiable)";
        }

        $code = $this->headStatus($url, true); // GET probe
        if ($code === 0) {
            return "action: {$url} → ⚠ endpoint unreachable";
        }
        if ($code === 404 || $code === 405) {
            return "action: {$url} → HTTP {$code} on GET "
                . "(often normal — endpoint may be POST-only or JS-handled; not necessarily broken)";
        }
        if ($code >= 200 && $code < 400) {
            return "action: {$url} → reachable (HTTP {$code})";
        }
        return "action: {$url} → ⚠ HTTP {$code}";
    }

    /** Same-domain page URLs found in <a href> on the page (deduped, capped). */
    private function internalLinks(string $html, string $baseUrl): array
    {
        $host = $this->host($baseUrl);
        if (!preg_match_all('/<a\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
            return [];
        }
        $urls = [];
        foreach ($m[1] as $href) {
            $href = trim($href);
            if ($href === '' || preg_match('~^(#|mailto:|tel:|javascript:|data:)~i', $href)) {
                continue;
            }
            $abs = $this->absolute($href, $baseUrl);
            if (!filter_var($abs, FILTER_VALIDATE_URL) || $this->host($abs) !== $host) {
                continue;
            }
            $urls[strtok($abs, '#')] = true;
            if (count($urls) >= $this->maxLinks) {
                break;
            }
        }
        return array_keys($urls);
    }

    /** Crawl same-domain links on the page and flag any that 4xx/5xx. */
    private function scanLinks(string $html, string $baseUrl): string
    {
        $urls = $this->internalLinks($html, $baseUrl);
        if (empty($urls)) {
            return "   Links       : no internal links in static HTML"
                . " (JS-rendered nav — use a site-wide scan via sitemap to check pages)";
        }

        $broken = [];
        foreach ($this->multiHead($urls) as $u => $code) {
            if ($code === 0 || $code >= 400) {
                $broken[] = "      ✗ HTTP " . ($code ?: 'no-response') . " — {$u}";
            }
        }

        $checked = count($urls);
        if (empty($broken)) {
            return "   Links       : 🟢 {$checked} internal link(s) checked, no 404s/errors";
        }
        return "   Links       : 🔴 " . count($broken) . " of {$checked} broken:\n" . implode("\n", $broken);
    }

    /** Parallel HEAD requests → [url => http_code]. Falls back to GET on 405/0. */
    private function multiHead(array $urls): array
    {
        $mh      = curl_multi_init();
        $handles = [];
        foreach ($urls as $u) {
            $ch = $this->headHandle($u);
            curl_multi_add_handle($mh, $ch);
            $handles[$u] = $ch;
        }

        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        $codes  = [];
        $retry  = [];
        foreach ($handles as $u => $ch) {
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            // No response at all → one full-GET retry to be sure.
            if ($code === 0) {
                $retry[] = $u;
            } else {
                $codes[$u] = $code;
            }
        }
        curl_multi_close($mh);

        foreach ($retry as $u) {
            $codes[$u] = $this->headStatus($u, true);
        }
        return $codes;
    }

    /**
     * A handle for a status probe. We use a small ranged GET, NOT a HEAD —
     * many hosts (Wix, some CDNs) return 404/405 to HEAD even when the page
     * is a healthy 200 on GET, which would produce false "broken page" alarms.
     */
    private function headHandle(string $url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $this->linkTimeout,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_RANGE          => '0-2048', // grab just the start
            CURLOPT_USERAGENT      => 'MosbatAI-SiteQA/1.0 (+https://mosbat.ai)',
        ]);
        return $ch;
    }

    /** Single status check; $useGet does a lightweight GET instead of HEAD. */
    private function headStatus(string $url, bool $useGet = false): int
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => !$useGet,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $this->linkTimeout,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'MosbatAI-SiteQA/1.0 (+https://mosbat.ai)',
        ]);
        if ($useGet) {
            curl_setopt($ch, CURLOPT_RANGE, '0-2048'); // grab just the start
        }
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code;
    }

    /** Host without a leading "www." — so www and apex are treated as one site. */
    private function host(string $url): string
    {
        return strtolower(preg_replace('~^www\.~i', '', (string) parse_url($url, PHP_URL_HOST)));
    }

    /** scheme://host[:port] for a URL. */
    private function origin(string $url): string
    {
        $b = parse_url($url);
        if ($b === false || empty($b['scheme']) || empty($b['host'])) {
            return rtrim($url, '/');
        }
        return $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
    }

    /** Resolve a possibly-relative URL against a base URL. */
    private function absolute(string $href, string $base): string
    {
        if (preg_match('~^https?://~i', $href)) {
            return $href;
        }
        $b = parse_url($base);
        if ($b === false || empty($b['scheme']) || empty($b['host'])) {
            return $href;
        }
        $origin = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');

        if (str_starts_with($href, '//')) {
            return $b['scheme'] . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }
        // relative to current directory
        $path = $b['path'] ?? '/';
        $dir  = substr($path, 0, strrpos($path, '/') + 1) ?: '/';
        return $origin . $dir . $href;
    }
}
