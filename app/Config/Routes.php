<?php

namespace Config;

// Create a new instance of our RouteCollection class.
$routes = Services::routes();

/**
 * Mosbat AI Command Center — Routes
 *
 * Public:
 *   GET  /                       → org chart (mission control)
 *   GET  /agent/(:slug)          → chat view for an agent
 *
 * API (JSON, used by fetch):
 *   POST /api/chat               → send a user message; get assistant reply
 *   GET  /api/history/(:slug)    → load recent turns for an agent
 *   GET  /api/org                → org tree as JSON (used by the chart)
 *   GET  /api/reports/(:slug)    → latest daily reports for an agent
 */
$routes->get('/',                         'Dashboard::index');
$routes->get('company/(:segment)',        'Dashboard::company/$1');

$routes->group('api', ['namespace' => 'App\Controllers'], static function ($routes) {
    $routes->post('chat',                 'ChatApi::send');
    $routes->post('chat/stream',          'ChatApi::stream');
    $routes->get('history/(:segment)',    'ChatApi::history/$1');
    $routes->get('org',                   'ChatApi::org');
    $routes->get('reports/(:segment)',    'ChatApi::reports/$1');

    // Skills — agent assignment
    $routes->get('skills/(:segment)',     'ChatApi::skills/$1');
    $routes->get('all-skills',            'ChatApi::allSkills');
    $routes->post('skills/assign',        'ChatApi::assignSkill');
    $routes->post('skills/remove',        'ChatApi::removeSkill');

    // Skills — global CRUD (settings panel)
    $routes->post('skills/create',        'ChatApi::createSkill');
    $routes->post('skills/update',        'ChatApi::updateSkillById');
    $routes->post('skills/delete-skill',  'ChatApi::deleteSkillById');

    // Activity feed
    $routes->get('activity/(:segment)',   'ChatApi::activity/$1');

    // Agent profile management
    $routes->get('agents/company/(:segment)', 'ChatApi::companyAgents/$1');
    $routes->post('agents/update',             'ChatApi::updateAgent');

    $routes->post('agents/create',              'ChatApi::createAgent');
    $routes->post('agents/delete',              'ChatApi::deleteAgent');
    $routes->get('agent-history/(:segment)',    'ChatApi::agentHistory/$1');

    // Tasks — autonomous agent work
    $routes->get('tasks/(:segment)',            'TaskApi::list/$1');
    $routes->post('tasks/create',               'TaskApi::create');
    $routes->post('tasks/update',               'TaskApi::update');
    $routes->post('tasks/cancel',               'TaskApi::cancel');

    // Artifacts — stored soft-copy files (images, documents)
    $routes->get('artifacts/(:segment)',        'ArtifactApi::list/$1');
});
