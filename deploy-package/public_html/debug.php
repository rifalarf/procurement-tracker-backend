<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP Version: " . phpversion() . "<br>";
echo "__DIR__ : " . __DIR__ . "<br>";

$path = realpath(__DIR__ . '/../../pengadaan_app');
echo "pengadaan_app path resolved: " . ($path ? $path : 'NOT FOUND/INVALID PATH') . "<br>";

if ($path) {
    echo "/vendor/autoload.php exists? " . (file_exists($path . '/vendor/autoload.php') ? 'Yes' : 'No') . "<br>";
    echo "/bootstrap/app.php exists? " . (file_exists($path . '/bootstrap/app.php') ? 'Yes' : 'No') . "<br>";
}

// Try to view error_log
if (file_exists(__DIR__ . '/error_log')) {
    echo "<h3>error_log contents:</h3>";
    echo "<pre>" . htmlspecialchars(file_get_contents(__DIR__ . '/error_log')) . "</pre>";
} else {
    echo "<h3>No error_log file found in " . __DIR__ . "</h3>";
}
