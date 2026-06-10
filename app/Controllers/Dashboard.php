<?php

namespace App\Controllers;

use App\Models\SupabaseModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $supabase = new SupabaseModel();

        $chairman = [
            'name'     => getenv('chairman.name')    ?: 'Mosbat',
            'title'    => getenv('chairman.title')   ?: 'Chairman & CEO',
            'initials' => getenv('chairman.initials') ?: 'M',
            'logo'     => '/logos/mosbat.svg',
        ];

        $companies = [
            [
                'id'          => 'positive_nation',
                'name'        => 'Positive Nation LLC',
                'description' => 'Community growth. Rewarding positivity. Operating the Positive Nation Economy.',
                'status'      => 'active',
                'mock'        => false,
                'logo'        => '/logos/positive-nation.svg',
                'agents'      => $supabase->getOrg('positive_nation'),
            ],
            [
                'id'          => 'k2z_digital',
                'name'        => 'K2Z Digital',
                'description' => 'Digital strategy, marketing, and technology solutions.',
                'status'      => 'active',
                'mock'        => false,
                'logo'        => '/logos/k2z-digital.png',
                'agents'      => $supabase->getOrg('k2z_digital'),
            ],
            [
                'id'          => 'zengit',
                'name'        => 'Zengit',
                'description' => 'Powering growth through technology and innovation.',
                'status'      => 'active',
                'mock'        => false,
                'logo'        => '/logos/zengit.png',
                'agents'      => $supabase->getOrg('zengit'),
            ],
        ];

        $totalAgents = array_sum(array_map(fn($c) => count($c['agents']), $companies));

        return view('dashboard', [
            'chairman'    => $chairman,
            'companies'   => $companies,
            'totalAgents' => $totalAgents,
        ]);
    }

    public function company(string $id)
    {
        $supabase = new SupabaseModel();

        $map = [
            'positive_nation' => [
                'id'          => 'positive_nation',
                'name'        => 'Positive Nation LLC',
                'description' => 'Community growth. Rewarding positivity. Operating the Positive Nation Economy.',
                'mock'        => false,
                'logo'        => '/logos/positive-nation.svg',
            ],
            'k2z_digital' => [
                'id'          => 'k2z_digital',
                'name'        => 'K2Z Digital',
                'description' => 'Digital strategy, marketing, and technology solutions.',
                'mock'        => false,
                'logo'        => '/logos/k2z-digital.png',
            ],
            'zengit' => [
                'id'          => 'zengit',
                'name'        => 'Zengit',
                'description' => 'Powering growth through technology and innovation.',
                'mock'        => false,
                'logo'        => '/logos/zengit.png',
            ],
        ];

        if (!isset($map[$id])) {
            return redirect()->to('/')->with('error', 'Company not found');
        }

        $company = $map[$id];

        $agents = $supabase->getCompanyAgents($id);

        $root = null;
        foreach ($agents as $a) {
            if (empty($a['parent_id'])) { $root = $a; break; }
        }
        if (!$root && !empty($agents)) {
            $root = $agents[0];
        }

        $chairman = [
            'name'     => getenv('chairman.name')     ?: 'Mosbat',
            'title'    => getenv('chairman.title')    ?: 'Chairman & CEO',
            'initials' => getenv('chairman.initials') ?: 'M',
            'logo'     => '/logos/mosbat.svg',
        ];

        return view('company', [
            'company'  => $company,
            'agents'   => $agents,
            'root'     => $root,
            'chairman' => $chairman,
        ]);
    }

    /**
     * Serve a file from public/generated/ with explicit Content-Type headers.
     * Using a PHP controller guarantees correct MIME regardless of web server
     * configuration (Supabase, nginx, PHP built-in server all behave differently).
     */
    public function serveFile(): \CodeIgniter\HTTP\ResponseInterface
    {
        // Read the URI directly — CI4 route capture loses slashes when $1 is passed
        // to the method, so we parse /file/{company}/{filename} from the raw path.
        $uriPath = $this->request->getUri()->getPath();        // e.g. /file/k2z_digital/foo.html
        $relative = ltrim(preg_replace('#^/file/#', '', $uriPath), '/'); // k2z_digital/foo.html

        // Split into at most 2 segments: company + filename
        $parts = explode('/', $relative, 2);
        if (count($parts) === 2 && $parts[1] !== '') {
            $company = preg_replace('/[^a-zA-Z0-9_\-]/', '', $parts[0]);
            $name    = basename($parts[1]);
            $path    = FCPATH . 'generated' . DIRECTORY_SEPARATOR . $company . DIRECTORY_SEPARATOR . $name;
        } else {
            $name = basename($parts[0]);
            $path = FCPATH . 'generated' . DIRECTORY_SEPARATOR . $name;
        }

        if (!file_exists($path) || !is_readable($path)) {
            return $this->response->setStatusCode(404)->setBody('File not found.');
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mimes = [
            'html' => 'text/html; charset=UTF-8',
            'htm'  => 'text/html; charset=UTF-8',
            'css'  => 'text/css; charset=UTF-8',
            'js'   => 'application/javascript; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
            'svg'  => 'image/svg+xml',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'txt'  => 'text/plain; charset=UTF-8',
            'csv'  => 'text/csv; charset=UTF-8',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf'  => 'application/pdf',
        ];
        $mime = $mimes[$ext] ?? 'application/octet-stream';

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody(file_get_contents($path));
    }
}
