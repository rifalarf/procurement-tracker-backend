<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Menggunakan absolute path agar tidak bingung dengan relative path
$appPath = '/home/matf2269/pengadaan_app';

if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $appPath.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once $appPath.'/bootstrap/app.php')
    ->handleRequest(Request::capture());
