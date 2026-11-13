<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Ensure required runtime directories exist and are writable
foreach ([
    __DIR__ . '/bootstrap/cache',
    __DIR__ . '/storage/framework/cache/data',
    __DIR__ . '/storage/framework/sessions',
    __DIR__ . '/storage/framework/views',
    __DIR__ . '/storage/logs',
    __DIR__ . '/storage/app/public',
    __DIR__ . '/storage/app/private',
] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

// Maintenance mode check
if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Composer autoload
require __DIR__.'/vendor/autoload.php';

// Bootstrap app
$app = require_once __DIR__.'/bootstrap/app.php';

// Handle request
$app->handleRequest(Request::capture());
