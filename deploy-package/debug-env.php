<?php
/**
 * Debug script - Comprehensive check
 * HAPUS SETELAH SELESAI!
 */

define('LARAVEL_PATH', __DIR__ . '/../../pengadaan_app');

echo "<h1>🔍 Comprehensive Debug</h1>";
echo "<pre>";

// 1. Check .env file
$envPath = LARAVEL_PATH . '/.env';
echo "=== 1. File .env ===\n";
echo "Path: $envPath\n";
echo "Exists: " . (file_exists($envPath) ? "✓" : "✗") . "\n\n";

// 2. Parse .env manually
echo "=== 2. Parsing .env manually ===\n";
$envContent = file_get_contents($envPath);
$lines = explode("\n", $envContent);
$envVars = [];

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line) || strpos($line, '#') === 0)
        continue;
    if (strpos($line, '=') === false)
        continue;

    list($key, $value) = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value, " \t\n\r\0\x0B\"'");
    $envVars[$key] = $value;

    // Show DB related vars
    if (strpos($key, 'DB_') === 0 || strpos($key, 'APP_') === 0) {
        $displayValue = (strpos($key, 'PASSWORD') !== false) ? '***hidden***' : $value;
        echo "  $key = $displayValue\n";
    }
}
echo "\n";

// 3. Check bootstrap/cache
echo "=== 3. Cache Files ===\n";
$cacheDir = LARAVEL_PATH . '/bootstrap/cache';
if (is_dir($cacheDir)) {
    $cacheFiles = scandir($cacheDir);
    foreach ($cacheFiles as $file) {
        if ($file !== '.' && $file !== '..' && $file !== '.gitignore') {
            $fullPath = $cacheDir . '/' . $file;
            echo "  📄 $file (" . filesize($fullPath) . " bytes)\n";
        }
    }
    if (count($cacheFiles) <= 3) {
        echo "  ✓ No cache files (good!)\n";
    }
} else {
    echo "  ✗ Cache directory not found\n";
}
echo "\n";

// 4. Test database connection directly
echo "=== 4. Direct Database Test ===\n";
$dbHost = $envVars['DB_HOST'] ?? 'localhost';
$dbName = $envVars['DB_DATABASE'] ?? '';
$dbUser = $envVars['DB_USERNAME'] ?? '';
$dbPass = $envVars['DB_PASSWORD'] ?? '';

echo "Host: $dbHost\n";
echo "Database: $dbName\n";
echo "Username: $dbUser\n";
echo "Password: " . (empty($dbPass) ? "(empty)" : "***set***") . "\n\n";

try {
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✅ Database Connection: SUCCESS!\n";

    // Test query
    $stmt = $pdo->query("SELECT 1");
    echo "✅ Test Query: SUCCESS!\n";

} catch (PDOException $e) {
    echo "❌ Database Connection: FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Try loading Laravel
echo "=== 5. Laravel Bootstrap Test ===\n";
try {
    require LARAVEL_PATH . '/vendor/autoload.php';
    echo "✓ Autoloader loaded\n";

    // Load Dotenv manually
    $dotenv = Dotenv\Dotenv::createImmutable(LARAVEL_PATH);
    $dotenv->load();
    echo "✓ Dotenv loaded\n";

    // Check if env() works now
    echo "\nAfter Dotenv load:\n";
    echo "  APP_ENV: " . (getenv('APP_ENV') ?: $_ENV['APP_ENV'] ?? 'not set') . "\n";
    echo "  DB_DATABASE: " . (getenv('DB_DATABASE') ?: $_ENV['DB_DATABASE'] ?? 'not set') . "\n";
    echo "  DB_USERNAME: " . (getenv('DB_USERNAME') ?: $_ENV['DB_USERNAME'] ?? 'not set') . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";

// Show solution
echo "<h2>📋 Next Steps</h2>";
echo "<ol>";
echo "<li>If Database Connection is SUCCESS, the issue is Laravel bootstrap</li>";
echo "<li>If there are cache files, delete them in File Manager</li>";
echo "<li>Make sure there's NO config.php in bootstrap/cache/</li>";
echo "</ol>";
?>