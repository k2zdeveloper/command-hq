<?php

namespace App\Controllers;

use App\Models\SupabaseModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * TaskApi
 * -------------------------------------------------------
 * Manages autonomous tasks assigned to agents.
 * Tasks are processed in the background by AgentRun command.
 *
 * Endpoints:
 *   GET  /api/tasks/:companyId   → list tasks for a company
 *   POST /api/tasks/create       → create a new task
 *   POST /api/tasks/update       → update task status or output
 *   POST /api/tasks/cancel       → cancel a pending/working task
 */
class TaskApi extends BaseController
{
    /** GET /api/tasks/:companyId — list all tasks for a company */
    public function list(string $companyId): ResponseInterface
    {
        if (empty($companyId)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['ok' => false, 'error' => 'company_id required']);
        }

        $tasks = (new SupabaseModel())->getCompanyTasks($companyId, 100);

        return $this->response->setJSON([
            'ok'    => true,
            'tasks' => $tasks,
        ]);
    }

    /** POST /api/tasks/create — create a new task */
    public function create(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];

        $title     = trim((string) ($json['title']      ?? ''));
        $companyId = trim((string) ($json['company_id'] ?? ''));

        if ($title === '' || $companyId === '') {
            return $this->response->setStatusCode(400)
                ->setJSON(['ok' => false, 'error' => 'title and company_id are required']);
        }

        $priority = in_array($json['priority'] ?? '', ['low','medium','high','critical'])
            ? $json['priority']
            : 'medium';

        $data = [
            'title'       => $title,
            'description' => trim((string) ($json['description'] ?? '')),
            'company_id'  => $companyId,
            'agent_id'    => $json['agent_id'] ?: null,
            'priority'    => $priority,
            'status'      => 'pending',
        ];

        $task = (new SupabaseModel())->createTask($data);

        if (!$task) {
            return $this->response->setStatusCode(500)
                ->setJSON(['ok' => false, 'error' => 'Failed to create task']);
        }

        return $this->response->setJSON(['ok' => true, 'task' => $task]);
    }

    /** POST /api/tasks/update — update task fields */
    public function update(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $id   = trim((string) ($json['id'] ?? ''));

        if ($id === '') {
            return $this->response->setStatusCode(400)
                ->setJSON(['ok' => false, 'error' => 'id required']);
        }

        $allowed = ['status', 'output', 'error_message', 'priority', 'title', 'description'];
        $data    = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $json)) {
                $data[$key] = $json[$key];
            }
        }

        if (empty($data)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['ok' => false, 'error' => 'no fields to update']);
        }

        (new SupabaseModel())->updateTask($id, $data);

        return $this->response->setJSON(['ok' => true]);
    }

    /** POST /api/tasks/cancel — cancel a pending or working task */
    public function cancel(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $id   = trim((string) ($json['id'] ?? ''));

        if ($id === '') {
            return $this->response->setStatusCode(400)
                ->setJSON(['ok' => false, 'error' => 'id required']);
        }

        // 1. Update DB status immediately
        (new SupabaseModel())->updateTask($id, [
            'status'       => 'cancelled',
            'completed_at' => date('c'),
        ]);

        // 2. Write signal file — AgentRun reads this mid-stream and aborts instantly
        $signal = \App\Libraries\ClaudeService::cancelSignalPath($id);
        @file_put_contents($signal, date('c'));

        return $this->response->setJSON(['ok' => true, 'signal_written' => file_exists($signal)]);
    }
}
