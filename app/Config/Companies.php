<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Companies registry — central place to add/remove companies the boss can command.
 *
 * To add a new company:
 *   1. Add an entry to $companies below.
 *   2. Add its API key to .env as COMPANY_<KEY>_API_KEY=...
 *   3. Restart the app. Done — no other code changes needed.
 */
class Companies extends BaseConfig
{
    /**
     * Paperclip base URL — set in .env as PAPERCLIP_BASE_URL
     */
    public string $baseUrl = '';

    /**
     * Registry of all spoke companies.
     *
     * Each entry:
     *   id          → Paperclip company UUID
     *   name        → Display name in UI
     *   tag         → Short subtitle (e.g. "SEO · CA")
     *   ceoName     → Display name for the CEO agent
     *   ceoAgentId  → Paperclip agent UUID for the CEO
     *   apiKeyEnv   → name of the .env variable holding this company's bearer token
     *   avatarClass → CSS class for the avatar gradient (av-a, av-b, av-c, av-d)
     *   initial     → Single letter shown in avatar circle
     */
    public array $companies = [
        'k2z' => [
            'id'          => 'acd82ccd-95d2-476f-98d5-541398fa6dbf',
            'name'        => 'MosbatLLC',
            'tag'         => 'SEO · CA',
            'ceoName'     => 'CEO Atlas',
            'ceoAgentId'  => 'ba96d449-4f55-4c2f-a886-f30203ed0d9b',
            'apiKeyEnv'   => 'COMPANY_K2Z_API_KEY',
            'avatarClass' => 'av-a',
            'initial'     => 'M',
        ],
        'a2zeng' => [
            'id'          => 'fcfd3b05-76be-4c56-9fd2-9541372ac79e',
            'name'        => 'PositiveNation',
            'tag'         => 'Wellness · TH',
            'ceoName'     => 'CEO Mira',
            'ceoAgentId'  => '6d20473f-f4ad-4c8c-8be0-51e4f32a3ae7',
            'apiKeyEnv'   => 'COMPANY_A2ZENG_API_KEY',
            'avatarClass' => 'av-b',
            'initial'     => 'P',
        ],
        // Add more companies here:
        // 'dentech' => [
        //     'id'          => '...',
        //     'name'        => 'DenTech Hub',
        //     'tag'         => 'Dental',
        //     'ceoName'     => 'CEO Orion',
        //     'ceoAgentId'  => '...',
        //     'apiKeyEnv'   => 'COMPANY_DENTECH_API_KEY',
        //     'avatarClass' => 'av-c',
        //     'initial'     => 'D',
        // ],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->baseUrl = env('PAPERCLIP_BASE_URL', 'https://paperclip-zwun.srv1511497.hstgr.cloud');
    }

    /**
     * Get a company config by its key (e.g. 'k2z').
     */
    public function get(string $key): ?array
    {
        if (! isset($this->companies[$key])) {
            return null;
        }

        $company = $this->companies[$key];
        $company['key']    = $key;
        $company['apiKey'] = env($company['apiKeyEnv'], '');
        return $company;
    }

    /**
     * Get all companies as an array (without API keys exposed).
     */
    public function all(): array
    {
        $out = [];
        foreach ($this->companies as $key => $company) {
            $company['key'] = $key;
            unset($company['apiKeyEnv']);
            $out[] = $company;
        }
        return $out;
    }
}
