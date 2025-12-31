<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
// Path: public_html/pengadaan -> public_html -> home -> pengadaan_app
if (file_exists($maintenance = __DIR__.'/../../pengadaan_app/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
// IMPORTANT: Path to Laravel app folder (2 levels up from public_html/pengadaan)
require __DIR__.'/../../pengadaan_app/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../../pengadaan_app/bootstrap/app.php')
    ->handleRequest(Request::capture());
