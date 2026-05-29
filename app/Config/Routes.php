<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Main chat interface
$routes->get('/', 'Home::index');
$routes->get('login', 'Home::login');
$routes->post('login', 'Home::doLogin');
$routes->get('logout', 'Home::logout');

// API endpoints (called by Alpine.js frontend)
$routes->group('api', ['filter' => 'auth'], static function ($routes) {
    // Companies registry
    $routes->get('companies', 'Api::companies');

    // Messages
    $routes->get('messages/(:segment)', 'Api::messages/$1');
    $routes->post('messages/(:segment)', 'Api::send/$1');

    // Tasks / Issues
    $routes->get('tasks/(:segment)', 'Api::tasks/$1');
    $routes->get('status', 'Api::status');

    // Attachment proxy — streams Paperclip file content to the browser
    $routes->get('attachment/(:segment)/(:segment)', 'Api::attachment/$1/$2');

    // Cancel a Paperclip issue
    $routes->post('cancel/(:segment)/(:segment)', 'Api::cancel/$1/$2');
});
