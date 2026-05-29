<?php

namespace App\Models;

use App\Libraries\SupabaseClient;

/**
 * Chat history storage via Supabase REST API.
 *
 * Schema (run this SQL in Supabase SQL Editor):
 *
 *   create table public.messages (
 *     id          bigserial primary key,
 *     company_key text not null,
 *     role        text not null check (role in ('boss','ceo','system')),
 *     text        text not null,
 *     issue_id    text,
 *     issue_meta  jsonb,
 *     created_at  timestamptz not null default now()
 *   );
 *
 *   create index messages_company_key_idx on public.messages (company_key, id);
 *
 *   alter table public.messages enable row level security;
 *   create policy "service_role_all" on public.messages
 *     for all to service_role using (true) with check (true);
 */
class MessageModel
{
    protected SupabaseClient $db;
    protected string $table = 'messages';

    public function __construct()
    {
        $this->db = new SupabaseClient();
    }

    /**
     * Get the chat thread for a company, oldest first.
     */
    public function thread(string $companyKey, int $limit = 100): array
    {
        $result = $this->db->select($this->table,
            ['company_key' => 'eq.' . $companyKey],
            ['order' => 'id.asc', 'limit' => $limit]
        );

        if (! $result['ok']) {
            log_message('error', '[MessageModel] thread failed: ' . json_encode($result['data']));
            return [];
        }

        $rows = is_array($result['data']) ? $result['data'] : [];

        // Normalize: convert issue_meta jsonb into a 'task' field for the front-end
        foreach ($rows as &$row) {
            if (! empty($row['issue_meta'])) {
                $row['task'] = is_array($row['issue_meta']) ? $row['issue_meta'] : json_decode($row['issue_meta'], true);
            }
        }

        return $rows;
    }

    /**
     * Insert a new message and return the inserted row.
     */
    public function add(string $companyKey, string $role, string $text, ?string $issueId = null, ?array $issueMeta = null): ?array
    {
        $payload = [
            'company_key' => $companyKey,
            'role'        => $role,
            'text'        => $text,
            'issue_id'    => $issueId,
            'issue_meta'  => $issueMeta, // Supabase stores arrays as jsonb automatically
        ];

        $result = $this->db->insert($this->table, $payload);

        if (! $result['ok']) {
            log_message('error', '[MessageModel] insert failed: ' . json_encode($result['data']));
            return null;
        }

        // Supabase returns the row inside an array
        $row = is_array($result['data']) && isset($result['data'][0]) ? $result['data'][0] : null;

        if ($row && ! empty($row['issue_meta'])) {
            $row['task'] = is_array($row['issue_meta']) ? $row['issue_meta'] : json_decode($row['issue_meta'], true);
        }

        return $row;
    }

    /**
     * Attach a Paperclip issue ID to an existing message (called after the issue is created).
     */
    public function attachIssue(int $msgId, string $issueId): void
    {
        $this->db->update($this->table, ['id' => 'eq.' . $msgId], ['issue_id' => $issueId]);
    }

    /**
     * Patch the issue_meta JSON of an existing message (e.g. to backfill attachments).
     */
    public function updateMeta(int $msgId, array $meta): void
    {
        $this->db->update($this->table, ['id' => 'eq.' . $msgId], ['issue_meta' => $meta]);
    }

    /**
     * No-op — Supabase tables are pre-provisioned via SQL Editor.
     * Kept for compatibility with old code that called this.
     */
    public function ensureTable(): void
    {
        // Table is created out-of-band via Supabase SQL Editor.
        // See class docblock for the schema.
    }
}
