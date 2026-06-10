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
            'name'    => 'Positive Nation',
            'website' => 'https://www.positivenation.org/',
            'socials' => [
                // 'Facebook'  => 'https://www.facebook.com/yourpage',
                // 'Instagram' => 'https://www.instagram.com/yourhandle',
            ],
        ],
        'k2z_digital' => [
            'name'    => 'K2Z Digital',
            'website' => '',
            'socials' => [],
        ],
        'zengit' => [
            'name'    => 'Zengit',
            'website' => '',
            'socials' => [],
        ],
        'a2zwellness' => [
            'name'    => 'A2Z Wellness',
            'website' => '',
            'socials' => [],
        ],
    ];
}
