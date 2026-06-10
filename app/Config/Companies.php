<?php

namespace Config;

/**
 * Companies
 * -------------------------------------------------------------
 * Single source of truth for the companies the reports cover.
 * Edit this list as you learn each company's real website and
 * social handles — both the daily board report (report:board) and
 * the on-demand [COMPANY_REPORT] chat tool read from here.
 *
 *   division key  => [
 *       'name'    => Display name,
 *       'website' => Public site URL ('' if none yet — checked live),
 *       'socials' => ['Facebook' => 'https://...', 'Instagram' => '...'],
 *   ]
 */
class Companies
{
    public const LIST = [
        'positive_nation' => [
            'name'     => 'Positive Nation',
            'website'  => 'https://www.positivenation.org/',
            // 'facebook' = the .env key prefix for THIS company's page.
            // Only set it for companies that actually have a page connected.
            'facebook' => 'facebook', // → facebook.pageId / facebook.pageAccessToken
            'socials'  => [
                // 'Instagram' => 'https://www.instagram.com/yourhandle',
            ],
        ],
        'k2z_digital' => [
            'name'    => 'K2Z Digital',
            'website' => '',
            // 'facebook' => 'facebook_k2z',  // add when K2Z's page is connected
            'socials' => [],
        ],
        'zengit' => [
            'name'    => 'Zengit',
            'website' => '',
            // 'facebook' => 'facebook_zengit',
            'socials' => [],
        ],
        'a2zwellness' => [
            'name'    => 'A2Z Wellness',
            'website' => '',
            // 'facebook' => 'facebook_a2z',
            'socials' => [],
        ],
    ];
}
