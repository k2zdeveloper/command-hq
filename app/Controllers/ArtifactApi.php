<?php

namespace App\Controllers;

use App\Models\SupabaseModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ArtifactApi
 * -------------------------------------------------------
 * Lists soft-copy files produced by agents (images, documents).
 *   GET /api/artifacts/:companyId
 */
class ArtifactApi extends BaseController
{
    public function list(string $companyId): ResponseInterface
    {
        if (empty($companyId)) {
            return $this->response->setStatusCode(400)
                ->setJSON(['ok' => false, 'error' => 'company_id required']);
        }

        // 1. Try Supabase artifacts table (may not exist yet)
        $items = [];
        try {
            $items = (new SupabaseModel())->getCompanyArtifacts($companyId, 100);
        } catch (\Throwable $e) {
            log_message('warning', 'ArtifactApi: Supabase fetch failed: ' . $e->getMessage());
        }

        // 2. Scan company-specific subfolder on local disk.
        // Files are stored as public/generated/{companyId}/filename
        // so each company only sees its own files.
        $companySlug = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $companyId);
        $base        = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'generated';
        $dir         = $base . DIRECTORY_SEPARATOR . $companySlug;

        $imgExts = ['svg', 'png', 'jpg', 'jpeg', 'gif', 'webp'];
        $docExts = ['html', 'htm', 'css', 'js', 'json', 'txt', 'csv', 'md', 'xml', 'py', 'sql', 'pptx', 'docx', 'xlsx', 'pdf'];
        $allExts = array_merge($imgExts, $docExts);

        if (is_dir($dir)) {
            $seen = array_column($items, 'file_url');
            foreach (scandir($dir) ?: [] as $filename) {
                if ($filename === '.' || $filename === '..') continue;
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (!in_array($ext, $allExts)) continue;
                $url = '/file/' . $companySlug . '/' . $filename;
                // Skip if already recorded in Supabase
                $alreadyListed = false;
                foreach ($seen as $s) {
                    if (strpos((string)$s, $filename) !== false) { $alreadyListed = true; break; }
                }
                if ($alreadyListed) continue;
                $cleanName = preg_replace('/^\d{8}_\d{6}_[0-9a-f]{6}_/', '', $filename);
                $type      = in_array($ext, $imgExts) ? 'image' : 'document';
                $mtime     = @filemtime($dir . DIRECTORY_SEPARATOR . $filename);
                $items[]   = [
                    'id'         => md5($companySlug . '/' . $filename),
                    'type'       => $type,
                    'title'      => $cleanName,
                    'file_url'   => $url,
                    'mime'       => null,
                    'agent_id'   => null,
                    'created_at' => $mtime ? date('c', $mtime) : date('c'),
                ];
                $seen[] = $url;
            }
            usort($items, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        }

        return $this->response->setJSON(['ok' => true, 'artifacts' => $items]);
    }
}
