<?php

namespace App\Controllers;

use App\Libraries\PaperclipClient;
use App\Models\MessageModel;
use CodeIgniter\HTTP\ResponseInterface;

class Api extends BaseController
{
    protected MessageModel $messages;
    protected PaperclipClient $paperclip;

    public function __construct()
    {
        $this->messages  = new MessageModel();
        $this->paperclip = new PaperclipClient();
    }

    /**
     * GET /api/companies
     * Returns the list of companies the boss can talk to.
     */
    public function companies(): ResponseInterface
    {
        $companies = config('Companies')->all();

        // Count unread (boss-side: messages from CEO since last read)
        // For MVP we just return 0; can hook up later with a read_at column.
        foreach ($companies as &$c) {
            $c['unread']     = 0;
            $c['openTasks']  = 0;
            $c['doneTasks']  = 0;
            $c['agentCount'] = 0;
        }

        return $this->response->setJSON(['data' => $companies]);
    }

    /**
     * GET /api/messages/{companyKey}
     * Syncs pending replies then returns the thread.
     * syncReplies() returns the thread directly — no second Supabase read needed.
     */
    public function messages(string $companyKey): ResponseInterface
    {
        $company = config('Companies')->get($companyKey);
        if (! $company) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Unknown company']);
        }

        // syncReplies returns the (possibly updated) thread — avoids a second DB read.
        $thread = $this->syncReplies($companyKey);

        // Determine pending state (unresolved boss issue within last 30 min).
        $syncedIssueIds = [];
        foreach ($thread as $msg) {
            if ($msg['role'] === 'ceo' && ! empty($msg['issue_id']) && ! empty($msg['issue_meta']['synced'])) {
                $syncedIssueIds[$msg['issue_id']] = true;
            }
        }

        $pending = false;
        $cutoff  = gmdate('Y-m-d\TH:i:s\Z', time() - 1800);
        foreach ($thread as $msg) {
            if ($msg['role'] === 'boss'
                && ! empty($msg['issue_id'])
                && ! isset($syncedIssueIds[$msg['issue_id']])
                && ($msg['created_at'] ?? '') >= $cutoff
            ) {
                $pending = true;
                break;
            }
        }

        foreach ($thread as &$msg) {
            unset($msg['issue_meta']);
        }

        // Tell the frontend how soon to poll again.
        // Pending issue → 5 s; everything resolved → 30 s (reduces idle load 6×).
        $pollIn = $pending ? 5000 : 30000;

        return $this->response->setJSON(['data' => $thread, 'pending' => $pending, 'pollIn' => $pollIn]);
    }

    /**
     * Pulls new agent comments + attachments from Paperclip for unresolved issues only.
     *
     * Optimisations vs the previous version:
     *  - Skips issues that already have a synced CEO reply with attachments fetched
     *    (array_key_exists check on 'attachments') → 0 Paperclip calls for resolved issues.
     *  - Only polls issues created within the last 2 hours so very old unresolved
     *    issues don't hammer Paperclip on every request.
     *  - Returns the thread itself so the caller doesn't need a second DB read.
     *  - Re-fetches from DB only when new messages were actually written.
     */
    private function syncReplies(string $companyKey): array
    {
        $thread = $this->messages->thread($companyKey);

        // Build lookup structures in one pass.
        $issueIds         = [];
        $storedCommentIds = [];
        $resolvedIssueIds = []; // issues with synced reply AND attachments already fetched

        foreach ($thread as $msg) {
            if (! empty($msg['issue_id'])) {
                $issueIds[$msg['issue_id']] = true;
            }

            $meta = $msg['issue_meta'] ?? null;

            if (is_array($meta) && ! empty($meta['commentId'])) {
                $storedCommentIds[$meta['commentId']] = true;
            }

            // An issue is fully resolved once we have a synced reply AND we've
            // checked for attachments (the 'attachments' key is present, even if null).
            if ($msg['role'] === 'ceo'
                && ! empty($msg['issue_id'])
                && is_array($meta)
                && ! empty($meta['synced'])
                && array_key_exists('attachments', $meta)
            ) {
                $resolvedIssueIds[$msg['issue_id']] = true;
            }
        }

        // Build a quick lookup: issueId → boss message created_at
        $issueCreatedAt = [];
        foreach ($thread as $msg) {
            if ($msg['role'] === 'boss' && ! empty($msg['issue_id'])) {
                $issueCreatedAt[$msg['issue_id']] = $msg['created_at'] ?? '';
            }
        }

        $cutoff      = gmdate('Y-m-d\TH:i:s\Z', time() - 7200); // 2-hour window
        $dirty       = false; // true if we wrote anything new to Supabase

        foreach (array_keys($issueIds) as $issueId) {
            // Skip issues that are fully resolved — no Paperclip call needed.
            if (isset($resolvedIssueIds[$issueId])) {
                continue;
            }

            // Skip issues older than 2 hours that haven't resolved yet — avoid
            // hammering Paperclip for stale/stuck issues on every poll.
            if (! empty($issueCreatedAt[$issueId]) && $issueCreatedAt[$issueId] < $cutoff) {
                continue;
            }

            // Fetch comments from Paperclip (one call per unresolved issue).
            $commentResult = $this->paperclip->getIssueComments($companyKey, $issueId);
            if (! $commentResult['ok'] || ! is_array($commentResult['data'])) {
                continue;
            }

            // Fetch attachments (one call per unresolved issue).
            $attachments = $this->normaliseAttachments(
                $this->paperclip->listIssueAttachments($companyKey, $issueId)
            );

            // Backfill attachments onto existing synced CEO messages missing them.
            foreach ($thread as $msg) {
                $meta = $msg['issue_meta'] ?? null;
                if ($msg['role'] === 'ceo'
                    && ($msg['issue_id'] ?? '') === $issueId
                    && is_array($meta)
                    && ! empty($meta['synced'])
                    && ! array_key_exists('attachments', $meta)
                ) {
                    $newMeta = $meta;
                    $newMeta['attachments'] = $attachments ?: null;
                    $this->messages->updateMeta((int) $msg['id'], $newMeta);
                    $dirty = true;
                }
            }

            // Store new agent comments.
            foreach ($commentResult['data'] as $comment) {
                $commentId = $comment['id'] ?? null;
                if (! $commentId || isset($storedCommentIds[$commentId])) {
                    continue;
                }
                if (($comment['authorType'] ?? '') !== 'agent') {
                    continue;
                }

                $this->messages->add(
                    $companyKey,
                    'ceo',
                    $comment['body'] ?? '',
                    $issueId,
                    ['commentId' => $commentId, 'synced' => true, 'attachments' => $attachments ?: null]
                );

                $storedCommentIds[$commentId] = true;
                $dirty = true;
            }
        }

        // Only re-read from Supabase if we wrote something new; otherwise reuse
        // the thread we already fetched — saves one DB round-trip per quiet poll.
        return $dirty ? $this->messages->thread($companyKey) : $thread;
    }

    /**
     * Converts a Paperclip attachments API response into a clean array for storage.
     */
    private function normaliseAttachments(array $result): array
    {
        if (! $result['ok'] || empty($result['data'])) {
            return [];
        }

        $out = [];
        foreach ($result['data'] as $att) {
            if (empty($att['id'])) {
                continue;
            }
            $out[] = [
                'id'          => $att['id'],
                'filename'    => $att['originalFilename'] ?? 'attachment',
                'contentType' => $att['contentType'] ?? 'application/octet-stream',
                'byteSize'    => $att['byteSize'] ?? 0,
            ];
        }

        return $out;
    }

    /**
     * POST /api/messages/{companyKey}
     * Send a new command from the boss.
     * Body: { text: string, priority?: 'low'|'normal'|'high'|'urgent' }
     */
    public function send(string $companyKey): ResponseInterface
    {
        $company = config('Companies')->get($companyKey);
        if (! $company) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Unknown company']);
        }

        $payload  = $this->request->getJSON(true) ?? [];
        $text     = trim($payload['text'] ?? '');
        $priority = $payload['priority'] ?? 'normal';

        if ($text === '') {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Empty message']);
        }

        // Validate against Paperclip's enum; default to medium
        $validPriorities = ['critical', 'high', 'medium', 'low'];
        $paperclipPriority = in_array($priority, $validPriorities, true) ? $priority : 'medium';

        // 1. Store the boss's message (issue_id attached after Paperclip confirms)
        $bossMsg = $this->messages->add($companyKey, 'boss', $text);

        // 2. Create an issue in Paperclip — title = first line, description = full text
        $lines = explode("\n", $text, 2);
        $title = mb_substr(trim($lines[0]), 0, 120);

        $result = $this->paperclip->createIssue($companyKey, $title, $text, $paperclipPriority);

        if (! $result['ok']) {
            $this->messages->add(
                $companyKey,
                'system',
                'Failed to deliver to ' . $company['ceoName'] . ' (HTTP ' . $result['status'] . ')'
            );

            return $this->response->setStatusCode(502)->setJSON([
                'error'   => 'Paperclip rejected the message',
                'detail'  => $result['data'],
                'bossMsg' => $bossMsg,
            ]);
        }

        // 3. Attach the Paperclip issue ID to the boss message so syncReplies can poll it
        $issueData = $result['data'];
        $issueId   = $issueData['id'] ?? ($issueData['data']['id'] ?? null);

        if ($bossMsg && $issueId) {
            $this->messages->attachIssue((int) $bossMsg['id'], $issueId);
            $bossMsg['issue_id'] = $issueId;
        }

        return $this->response->setJSON(['bossMsg' => $bossMsg]);
    }

    /**
     * GET /api/tasks/{companyKey}
     * Returns the live list of issues from Paperclip for that company.
     */
    public function tasks(string $companyKey): ResponseInterface
    {
        $result = $this->paperclip->listIssues($companyKey);
        return $this->response->setJSON($result);
    }

    /**
     * GET /api/status
     * Pings each company's API to confirm reachability.
     */
    public function status(): ResponseInterface
    {
        return $this->response->setJSON($this->paperclip->health());
    }

    /**
     * POST /api/cancel/{companyKey}/{issueId}
     * Cancels a Paperclip issue and records a system message in the thread.
     */
    public function cancel(string $companyKey, string $issueId): ResponseInterface
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $issueId)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid issue ID']);
        }

        $company = config('Companies')->get($companyKey);
        if (! $company) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Unknown company']);
        }

        $result = $this->paperclip->cancelIssue($companyKey, $issueId);

        if (! $result['ok']) {
            return $this->response->setStatusCode(502)->setJSON([
                'error'          => 'Could not cancel issue',
                'paperclipStatus'=> $result['status'],
                'detail'         => $result['data'],
            ]);
        }

        $identifier = $result['data']['identifier'] ?? $issueId;

        $sysMsg = $this->messages->add(
            $companyKey,
            'system',
            'Task ' . $identifier . ' cancelled.'
        );

        return $this->response->setJSON(['ok' => true, 'sysMsg' => $sysMsg]);
    }

    /**
     * GET /api/attachment/{companyKey}/{attachmentId}
     * Proxies a Paperclip attachment to the browser so the user doesn't need
     * to be authenticated in Paperclip to open or download it.
     */
    public function attachment(string $companyKey, string $attachmentId): ResponseInterface
    {
        // Validate UUID format to prevent path traversal
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $attachmentId)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid attachment ID']);
        }

        $company = config('Companies')->get($companyKey);
        if (! $company) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Unknown company']);
        }

        $result = $this->paperclip->fetchAttachmentContent($companyKey, $attachmentId);

        if (! $result['ok'] || $result['body'] === '') {
            return $this->response->setStatusCode(502)->setJSON(['error' => 'Could not fetch attachment']);
        }

        $download = (bool) $this->request->getGet('download');
        $filename = basename(str_replace('"', '', (string) ($this->request->getGet('filename') ?? 'attachment')));

        $disposition = $download
            ? 'attachment; filename="' . $filename . '"'
            : 'inline';

        return $this->response
            ->setHeader('Content-Type', $result['contentType'])
            ->setHeader('Content-Disposition', $disposition)
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setBody($result['body']);
    }
}
