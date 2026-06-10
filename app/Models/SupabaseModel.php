<?php

namespace App\Models;

use CodeIgniter\HTTP\CURLRequest;
use Config\Services;

/**
 * SupabaseModel
 * -------------------------------------------------------------
 * Thin wrapper around Supabase's auto-generated PostgREST API.
 * We deliberately don't use the CI4 ORM — Supabase is the
 * single source of truth, and PostgREST is faster/simpler here.
 *
 * All requests use the SERVICE ROLE key (server-side only).
 */
class SupabaseModel
{
    private CURLRequest $http;
    private string $baseUrl;
    private string $projectUrl = '';
    private string $serviceKey = '';
    private array  $headers;
    private string $lastError = '';

    /** Returns the last Supabase error message (empty if none). */
    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function __construct()
    {
        $url = rtrim((string) getenv('supabase.url'), '/');
        $key = (string) getenv('supabase.serviceKey');

        if ($url === '' || $key === '') {
            log_message('error', 'Supabase URL or service key is missing from .env');
        }

        $this->projectUrl = $url;
        $this->serviceKey = $key;
        $this->baseUrl    = $url . '/rest/v1';
        $this->headers = [
            'apikey'        => $key,
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];

        $this->http = Services::curlrequest([
            'timeout'     => 15,
            'http_errors' => false,
        ]);
    }

    // ---- Supabase Storage (shared file hosting) --------------------------

    /**
     * Upload raw bytes to a public Supabase Storage bucket and return the
     * public URL. Returns null on failure (caller can fall back to local).
     * Files uploaded here are reachable from ANY device/server.
     */
    public function uploadToStorage(string $filename, string $bytes, string $mime, string $bucket = 'generated'): ?string
    {
        if ($this->projectUrl === '' || $this->serviceKey === '') {
            return null;
        }

        $this->ensureBucket($bucket);

        $path = ltrim($filename, '/');
        $ch   = curl_init($this->projectUrl . '/storage/v1/object/' . $bucket . '/' . rawurlencode($path));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $bytes,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->serviceKey,
                'apikey: ' . $this->serviceKey,
                'Content-Type: ' . $mime,
                'x-upsert: true',
            ],
        ]);
        $resp   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($status >= 200 && $status < 300) {
            return $this->projectUrl . '/storage/v1/object/public/' . $bucket . '/' . rawurlencode($path);
        }

        log_message('error', 'Supabase storage upload [' . $status . ']: ' . ($err ?: substr((string) $resp, 0, 200)));
        return null;
    }

    /** Create a public bucket if it doesn't exist (ignores "already exists"). */
    private function ensureBucket(string $bucket): void
    {
        $ch = curl_init($this->projectUrl . '/storage/v1/bucket');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['id' => $bucket, 'name' => $bucket, 'public' => true]),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->serviceKey,
                'apikey: ' . $this->serviceKey,
                'Content-Type: application/json',
            ],
        ]);
        curl_exec($ch);   // 200 = created, 409 = already exists — both fine
        curl_close($ch);
    }

    // ---- Generic HTTP helpers --------------------------------------------

    private function get(string $path, array $query = []): array
    {
        $qs     = $query ? '?' . http_build_query($query) : '';
        $url    = $this->baseUrl . $path . $qs;
        $res    = $this->http->get($url, ['headers' => $this->headers]);
        $status = $res->getStatusCode();
        $body   = (string) $res->getBody();
        $data   = json_decode($body, true);
        if ($status >= 400 || !is_array($data)) {
            log_message('error', 'Supabase GET [' . $status . '] ' . $url . ' → ' . substr($body, 0, 300));
        }
        return is_array($data) ? $data : [];
    }

    private function post(string $path, array $payload, array $prefer = ['return=representation']): array
    {
        $headers = $this->headers + ['Prefer' => implode(',', $prefer)];
        $res     = $this->http->post($this->baseUrl . $path, [
            'headers' => $headers,
            'body'    => json_encode($payload),
        ]);
        $status = $res->getStatusCode();
        $body   = (string) $res->getBody();
        $data   = json_decode($body, true);

        if ($status >= 400) {
            $this->lastError = is_array($data)
                ? ($data['message'] ?? $data['hint'] ?? $body)
                : $body;
            log_message('error', 'Supabase POST [' . $status . '] ' . $path . ' → ' . substr($body, 0, 400));
        }

        return is_array($data) ? $data : [];
    }

    private function patch(string $path, array $data): bool
    {
        $res = $this->http->request('PATCH', $this->baseUrl . $path, [
            'headers' => $this->headers + ['Prefer' => 'return=minimal'],
            'body'    => json_encode($data),
        ]);
        return $res->getStatusCode() < 300;
    }

    private function delete(string $path): bool
    {
        $res = $this->http->delete($this->baseUrl . $path, [
            'headers' => $this->headers + ['Prefer' => 'return=minimal'],
        ]);
        return $res->getStatusCode() < 300;
    }

    // ---- Domain methods --------------------------------------------------

    /** Full org tree (flat list — UI builds the hierarchy). */
    public function getOrg(string $division = 'positive_nation'): array
    {
        return $this->get('/agents', [
            'select'   => 'id,slug,name,role_title,parent_id,avatar_emoji,is_active',
            'division' => 'eq.' . $division,
            'order'    => 'created_at.asc',
        ]);
    }

    public function getAgentBySlug(string $slug): ?array
    {
        $rows = $this->get('/agents', [
            'select' => '*',
            'slug'   => 'eq.' . $slug,
            'limit'  => 1,
        ]);
        return $rows[0] ?? null;
    }

    /**
     * Skills assigned to an agent, ordered by priority.
     * Uses PostgREST embedded resource syntax.
     */
    public function getAgentSkills(string $agentId): array
    {
        return $this->get('/agent_skills', [
            'select'    => 'priority,skill:skills(id,slug,name,skill_type,payload,is_active)',
            'agent_id'  => 'eq.' . $agentId,
            'order'     => 'priority.asc',
        ]);
    }

    /** Recent conversation turns, oldest → newest, ready to feed to Claude. */
    public function getRecentTurns(string $agentId, string $sessionId, int $limit = 12): array
    {
        $rows = $this->get('/conversations', [
            'select'     => 'id,role,content,created_at',
            'agent_id'   => 'eq.' . $agentId,
            'session_id' => 'eq.' . $sessionId,
            'role'       => 'in.(user,assistant)',
            'order'      => 'created_at.desc',
            'limit'      => $limit,
        ]);
        return array_reverse($rows);
    }

    /** Save a conversation turn and return its ID. */
    public function saveTurn(string $agentId, string $sessionId, string $role, string $content, ?array $usage = null): ?string
    {
        $result = $this->post('/conversations', [[
            'agent_id'    => $agentId,
            'session_id'  => $sessionId,
            'role'        => $role,
            'content'     => $content,
            'token_usage' => $usage,
        ]]);
        return $result[0]['id'] ?? null;
    }

    /** Delete a single conversation turn by ID. */
    public function deleteTurn(string $id): bool
    {
        return $this->delete('/conversations?id=eq.' . $id);
    }

    /** Long-term conversation memory (one row per agent+session). */
    public function getMemory(string $agentId, string $sessionId): ?array
    {
        $rows = $this->get('/conversation_memory', [
            'select'     => 'summary,turns_covered',
            'agent_id'   => 'eq.' . $agentId,
            'session_id' => 'eq.' . $sessionId,
            'limit'      => 1,
        ]);
        return $rows[0] ?? null;
    }

    /** Upsert the memory summary for an agent+session. */
    public function saveMemory(string $agentId, string $sessionId, string $summary, int $turnsCovered): void
    {
        $headers = $this->headers + ['Prefer' => 'resolution=merge-duplicates,return=minimal'];
        $this->http->post($this->baseUrl . '/conversation_memory?on_conflict=agent_id,session_id', [
            'headers' => $headers,
            'body'    => json_encode([[
                'agent_id'      => $agentId,
                'session_id'    => $sessionId,
                'summary'       => $summary,
                'turns_covered' => $turnsCovered,
                'updated_at'    => date('c'),
            ]]),
        ]);
    }

    public function saveDailyReport(string $agentId, string $summary, array $raw): void
    {
        // Upsert on (agent_id, report_date) — Prefer: resolution=merge-duplicates
        $headers = $this->headers + [
            'Prefer' => 'resolution=merge-duplicates,return=minimal',
        ];
        $this->http->post($this->baseUrl . '/daily_reports?on_conflict=agent_id,report_date', [
            'headers' => $headers,
            'body'    => json_encode([[
                'agent_id'     => $agentId,
                'summary'      => $summary,
                'raw_response' => $raw,
            ]]),
        ]);
    }

    public function getRecentReports(string $agentId, int $limit = 7): array
    {
        return $this->get('/daily_reports', [
            'select'   => 'report_date,summary,created_at',
            'agent_id' => 'eq.' . $agentId,
            'order'    => 'report_date.desc',
            'limit'    => $limit,
        ]);
    }

    /** All skills available in the system, ordered by name. */
    public function getAllSkills(): array
    {
        return $this->get('/skills', [
            'select' => 'id,slug,name,skill_type,payload,is_active',
            'order'  => 'name.asc',
        ]);
    }

    /** Create a new skill and return the created row. */
    public function createSkill(string $name, string $type, string $context): ?array
    {
        $slug   = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($name)));
        $result = $this->post('/skills', [[
            'name'       => trim($name),
            'slug'       => $slug,
            'skill_type' => $type,
            'payload'    => ['context' => $context],
            'is_active'  => true,
        ]]);
        return $result[0] ?? null;
    }

    /** Update an existing skill by ID. */
    public function updateSkill(string $id, string $name, string $type, string $context, bool $isActive): void
    {
        $this->patch('/skills?id=eq.' . $id, [
            'name'       => trim($name),
            'skill_type' => $type,
            'payload'    => ['context' => $context],
            'is_active'  => $isActive,
        ]);
    }

    /** Hard-delete a skill by ID. */
    public function deleteSkill(string $id): void
    {
        $this->delete('/skills?id=eq.' . $id);
    }

    /** Full agent data for a company division (includes system_prompt, temperature, model). */
    public function getCompanyAgents(string $division): array
    {
        return $this->get('/agents', [
            'select'   => 'id,slug,name,role_title,parent_id,avatar_emoji,is_active,system_prompt,temperature,model',
            'division' => 'eq.' . $division,
            'order'    => 'created_at.asc',
        ]);
    }

    /** Update mutable agent fields by ID. */
    public function updateAgent(string $id, array $data): bool
    {
        return $this->patch('/agents?id=eq.' . $id, $data);
    }

    public function createAgent(array $data): ?array
    {
        $result = $this->post('/agents', [$data]);
        return $result[0] ?? null;
    }

    /**
     * Delete an agent and its skill assignments.
     * Re-parents any child agents to this agent's parent (no orphans),
     * and nulls out task assignments (tasks.agent_id is ON DELETE SET NULL).
     */
    public function deleteAgent(string $id): bool
    {
        // Re-parent children: set their parent_id to this agent's parent
        $agent  = $this->getAgentById($id);
        $parent = $agent['parent_id'] ?? null;
        $this->patch('/agents?parent_id=eq.' . $id, ['parent_id' => $parent]);

        // Remove skill assignments first (FK)
        $this->delete('/agent_skills?agent_id=eq.' . $id);

        // Delete the agent
        return $this->delete('/agents?id=eq.' . $id);
    }

    public function getAllAgentTurns(string $agentId, int $limit = 50): array
    {
        $rows = $this->get('/conversations', [
            'select'   => 'role,content,created_at',
            'agent_id' => 'eq.' . $agentId,
            'order'    => 'created_at.desc',
            'limit'    => $limit,
        ]);
        return array_reverse($rows);
    }

    /** Agents with recent conversation activity — used for the activity feed. */
    public function getAgentActivity(string $agentId, int $limit = 5): array
    {
        return $this->get('/conversations', [
            'select'   => 'role,content,created_at',
            'agent_id' => 'eq.' . $agentId,
            'order'    => 'created_at.desc',
            'limit'    => $limit,
        ]);
    }

    /**
     * Assign a skill to an agent. Upserts on (agent_id, skill_id) so
     * re-assigning the same skill just updates the priority.
     */
    public function assignSkillToAgent(string $agentId, string $skillId, int $priority = 100): void
    {
        $headers = $this->headers + [
            'Prefer' => 'resolution=merge-duplicates,return=minimal',
        ];
        $this->http->post(
            $this->baseUrl . '/agent_skills?on_conflict=agent_id,skill_id',
            [
                'headers' => $headers,
                'body'    => json_encode([[
                    'agent_id' => $agentId,
                    'skill_id' => $skillId,
                    'priority' => $priority,
                ]]),
            ]
        );
    }

    /** Remove a specific skill assignment from an agent. */
    public function removeSkillFromAgent(string $agentId, string $skillId): void
    {
        $this->delete('/agent_skills?agent_id=eq.' . $agentId . '&skill_id=eq.' . $skillId);
    }

    // ── TASKS ──────────────────────────────────────────────────────────────

    /** Get a single agent by ID (used by agent runner). */
    public function getAgentById(string $id): ?array
    {
        $rows = $this->get('/agents', [
            'select' => 'id,slug,name,role_title,parent_id,is_active,system_prompt,temperature,model,division',
            'id'     => 'eq.' . $id,
            'limit'  => 1,
        ]);
        return $rows[0] ?? null;
    }

    /** Fetch a single task by ID — used to check cancellation mid-run. */
    public function getTaskById(string $id): ?array
    {
        $rows = $this->get('/tasks', [
            'select' => 'id,status,company_id,title',
            'id'     => 'eq.' . $id,
            'limit'  => 1,
        ]);
        return $rows[0] ?? null;
    }

    /** Create a new task. Returns the created row. */
    public function createTask(array $data): ?array
    {
        $result = $this->post('/tasks', [$data]);
        return $result[0] ?? null;
    }

    /** List tasks for a company, newest first. */
    public function getCompanyTasks(string $companyId, int $limit = 50): array
    {
        return $this->get('/tasks', [
            'select'     => 'id,title,description,status,priority,output,error_message,agent_id,created_at,updated_at,completed_at',
            'company_id' => 'eq.' . $companyId,
            'order'      => 'created_at.desc',
            'limit'      => $limit,
        ]);
    }

    /** Fetch pending tasks for the runner, oldest first (FIFO). */
    public function getPendingTasks(int $limit = 5): array
    {
        return $this->get('/tasks', [
            'select' => '*',
            'status' => 'eq.pending',
            'order'  => 'created_at.asc',
            'limit'  => $limit,
        ]);
    }

    /**
     * Atomically claim a pending task.
     * Uses conditional PATCH (?status=eq.pending) so only one process
     * can claim the task — returns true only if a row was actually updated.
     */
    public function claimTask(string $id): bool
    {
        $headers = $this->headers + ['Prefer' => 'return=representation'];
        $res     = $this->http->request('PATCH',
            $this->baseUrl . '/tasks?id=eq.' . $id . '&status=eq.pending',
            [
                'headers' => $headers,
                'body'    => json_encode([
                    'status'     => 'working',
                    'claimed_at' => date('c'),
                    'started_at' => date('c'),
                ]),
            ]
        );
        $data = json_decode((string) $res->getBody(), true);
        return is_array($data) && count($data) > 0;
    }

    /** Update mutable task fields. */
    public function updateTask(string $id, array $data): bool
    {
        return $this->patch('/tasks?id=eq.' . $id, $data);
    }

    // ── ARTIFACTS (soft-copy file store) ────────────────────────────────

    /** Record a produced file (image, document, etc.). Returns the row or null. */
    public function createArtifact(array $data): ?array
    {
        $result = $this->post('/artifacts', [$data]);
        return $result[0] ?? null;
    }

    /** List a company's stored artifacts, newest first. */
    public function getCompanyArtifacts(string $companyId, int $limit = 100): array
    {
        return $this->get('/artifacts', [
            'select'     => 'id,type,title,file_url,mime,size_bytes,agent_id,created_at',
            'company_id' => 'eq.' . $companyId,
            'order'      => 'created_at.desc',
            'limit'      => $limit,
        ]);
    }

    /**
     * Reset tasks stuck in "working" state longer than $minutes.
     * Happens when the runner crashed mid-execution.
     */
    public function resetStuckTasks(int $minutes = 10): void
    {
        $cutoff = date('c', strtotime("-{$minutes} minutes"));
        $this->http->request('PATCH',
            $this->baseUrl . '/tasks?status=eq.working&claimed_at=lt.' . urlencode($cutoff),
            [
                'headers' => $this->headers + ['Prefer' => 'return=minimal'],
                'body'    => json_encode(['status' => 'pending', 'claimed_at' => null]),
            ]
        );
    }
}
