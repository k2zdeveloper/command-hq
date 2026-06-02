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
                'id'          => 'positive_force_media',
                'name'        => 'Positive Force Media',
                'description' => 'Digital media and content amplification. Broadcasting positive narratives at scale.',
                'status'      => 'active',
                'mock'        => true,
                'agents'      => [
                    ['id' => 'pfm-001', 'slug' => 'pfm-director',   'name' => 'Director',        'role_title' => 'Creative Director',    'parent_id' => null,      'is_active' => true ],
                    ['id' => 'pfm-002', 'slug' => 'pfm-content',    'name' => 'Content Agent',   'role_title' => 'Content Strategist',   'parent_id' => 'pfm-001', 'is_active' => true ],
                    ['id' => 'pfm-003', 'slug' => 'pfm-social',     'name' => 'Social Agent',    'role_title' => 'Social Media Manager', 'parent_id' => 'pfm-001', 'is_active' => false],
                    ['id' => 'pfm-004', 'slug' => 'pfm-analytics',  'name' => 'Analytics Agent', 'role_title' => 'Data Analyst',         'parent_id' => 'pfm-001', 'is_active' => true ],
                ],
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
            'positive_force_media' => [
                'id'          => 'positive_force_media',
                'name'        => 'Positive Force Media',
                'description' => 'Digital media and content amplification. Broadcasting positive narratives at scale.',
                'mock'        => true,
            ],
        ];

        if (!isset($map[$id])) {
            return redirect()->to('/')->with('error', 'Company not found');
        }

        $company = $map[$id];

        if ($company['mock']) {
            $agents = [
                ['id' => 'pfm-001', 'slug' => 'pfm-director',  'name' => 'Director',        'role_title' => 'Creative Director',    'parent_id' => null,      'is_active' => true,  'model' => 'claude-sonnet-4-6',        'temperature' => 0.7, 'system_prompt' => 'You are the Creative Director of Positive Force Media, leading a digital media company focused on positive content amplification. Your team includes a Content Strategist, Social Media Manager, and Data Analyst.'],
                ['id' => 'pfm-002', 'slug' => 'pfm-content',   'name' => 'Content Agent',   'role_title' => 'Content Strategist',   'parent_id' => 'pfm-001', 'is_active' => true,  'model' => 'claude-sonnet-4-6',        'temperature' => 0.8, 'system_prompt' => 'You are the Content Strategist for Positive Force Media, responsible for ideating and producing compelling positive content.'],
                ['id' => 'pfm-003', 'slug' => 'pfm-social',    'name' => 'Social Agent',    'role_title' => 'Social Media Manager', 'parent_id' => 'pfm-001', 'is_active' => false, 'model' => 'claude-haiku-4-5-20251001', 'temperature' => 0.8, 'system_prompt' => 'You are the Social Media Manager for Positive Force Media.'],
                ['id' => 'pfm-004', 'slug' => 'pfm-analytics', 'name' => 'Analytics Agent', 'role_title' => 'Data Analyst',         'parent_id' => 'pfm-001', 'is_active' => true,  'model' => 'claude-haiku-4-5-20251001', 'temperature' => 0.3, 'system_prompt' => 'You are the Data Analyst for Positive Force Media, responsible for tracking and reporting on content performance metrics.'],
            ];
        } else {
            $agents = $supabase->getCompanyAgents($id);
        }

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
}
