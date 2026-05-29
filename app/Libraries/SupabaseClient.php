<?php

namespace App\Libraries;

use CodeIgniter\HTTP\CURLRequest;

/**
 * Thin client over Supabase's PostgREST API.
 *
 * Why not the CI4 DB layer? Because going through HTTP means we don't need
 * pdo_pgsql or any other PHP DB extension. Works on any vanilla PHP install.
 *
 * Auth: uses the SERVICE ROLE key (server-side only) so RLS is bypassed.
 * Never expose this key to the browser.
 *
 * Docs: https://supabase.com/docs/guides/api
 */
class SupabaseClient
{
    protected string $url;
    protected string $key;
    protected CURLRequest $http;

    public function __construct()
    {
        $this->url = rtrim((string) env('SUPABASE_URL', ''), '/');
        $this->key = (string) env('SUPABASE_SERVICE_KEY', '');

        if ($this->url === '' || $this->key === '') {
            throw new \RuntimeException('Supabase credentials missing. Set SUPABASE_URL and SUPABASE_SERVICE_KEY in .env');
        }

        $this->http = \Config\Services::curlrequest([
            'timeout'         => 30,
            'connect_timeout' => 10,
            'http_errors'     => false,
            'verify'          => false,
        ]);
    }

    /**
     * SELECT rows from a table.
     *
     * @param string $table       Table name (e.g. 'messages')
     * @param array  $filters     PostgREST filters, e.g. ['company_key' => 'eq.k2z']
     * @param array  $opts        ['order' => 'id.asc', 'limit' => 100, 'select' => '*']
     */
    public function select(string $table, array $filters = [], array $opts = []): array
    {
        $query = $filters;
        if (! empty($opts['order']))  $query['order']  = $opts['order'];
        if (! empty($opts['limit']))  $query['limit']  = (string) $opts['limit'];
        if (! empty($opts['select'])) $query['select'] = $opts['select'];

        $url = $this->url . '/rest/v1/' . $table;
        if (! empty($query)) $url .= '?' . http_build_query($query);

        return $this->request('GET', $url);
    }

    /**
     * INSERT a row. Returns the inserted row(s) thanks to the Prefer: return=representation header.
     */
    public function insert(string $table, array $row): array
    {
        $url = $this->url . '/rest/v1/' . $table;
        return $this->request('POST', $url, $row, ['Prefer' => 'return=representation']);
    }

    /**
     * UPDATE rows matching the filter.
     */
    public function update(string $table, array $filters, array $patch): array
    {
        $url = $this->url . '/rest/v1/' . $table;
        if (! empty($filters)) $url .= '?' . http_build_query($filters);
        return $this->request('PATCH', $url, $patch, ['Prefer' => 'return=representation']);
    }

    /**
     * DELETE rows matching the filter.
     */
    public function delete(string $table, array $filters): array
    {
        $url = $this->url . '/rest/v1/' . $table;
        if (! empty($filters)) $url .= '?' . http_build_query($filters);
        return $this->request('DELETE', $url);
    }

    /**
     * Low-level HTTP call. Returns a consistent shape.
     */
    protected function request(string $method, string $url, array $body = [], array $extraHeaders = []): array
    {
        $headers = array_merge([
            'apikey'        => $this->key,
            'Authorization' => 'Bearer ' . $this->key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ], $extraHeaders);

        $options = ['headers' => $headers];

        if (! empty($body) && in_array($method, ['POST', 'PATCH', 'PUT'], true)) {
            $options['body'] = json_encode($body);
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
            log_message('error', '[Supabase] ' . $e->getMessage());
            return [
                'ok'     => false,
                'status' => 0,
                'data'   => ['error' => $e->getMessage()],
            ];
        }
    }
}
