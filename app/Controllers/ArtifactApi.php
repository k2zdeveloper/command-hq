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

        $items = (new SupabaseModel())->getCompanyArtifacts($companyId, 100);

        return $this->response->setJSON(['ok' => true, 'artifacts' => $items]);
    }
}
