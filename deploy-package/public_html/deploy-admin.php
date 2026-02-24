<?php
/**
 * Laravel Deployment Admin Panel for cPanel Shared Hosting
 * 
 * IMPORTANT: DELETE this file after deployment is complete!
 * 
 * Usage: https://pengadaan.matrifix.site/deploy-admin.php?key=YOUR_ACCESS_KEY
 */

define('ACCESS_KEY', 'deploy-pengadaan-2024-secure');
define('LARAVEL_PATH', '/home/matf2269/pengadaan_app');

if (!isset($_GET['key']) || $_GET['key'] !== ACCESS_KEY) {
    http_response_code(403);
    die('Access Denied. Invalid or missing key.');
}

require LARAVEL_PATH . '/vendor/autoload.php';
$app = require_once LARAVEL_PATH . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$action = $_GET['action'] ?? null;
$output = '';
$success = true;

if ($action) {
    ob_start();
    
    try {
        switch ($action) {
            case 'update':
                echo "=== Updating Existing Project ===\n\n";
                echo "1. Clearing all caches...\n";
                $kernel->call('config:clear');
                $kernel->call('cache:clear');
                $kernel->call('route:clear');
                $kernel->call('view:clear');
                echo "   ✓ Caches cleared\n\n";
                echo "2. Running migrations (if any)...\n";
                $kernel->call('migrate', ['--force' => true]);
                echo $kernel->output();
                echo "   ✓ Migrations complete\n\n";
                echo "3. Optimizing for production...\n";
                $kernel->call('config:cache');
                $kernel->call('route:cache');
                $kernel->call('view:cache');
                echo "   ✓ Optimization complete\n\n";
                echo "=== UPDATE COMPLETE ===\n";
                break;
            
            case 'fresh-install':
                echo "=== Fresh Installation ===\n\n";
                echo "⚠️  WARNING: This will DELETE all existing data!\n\n";
                echo "1. Running fresh migrations...\n";
                $kernel->call('migrate:fresh', ['--force' => true]);
                echo $kernel->output();
                echo "   ✓ Fresh migrations complete\n\n";
                echo "2. Running database seeders...\n";
                $kernel->call('db:seed', ['--force' => true]);
                echo $kernel->output();
                echo "   ✓ Seeders complete\n\n";
                echo "3. Clearing caches...\n";
                $kernel->call('config:clear');
                $kernel->call('cache:clear');
                echo "   ✓ Caches cleared\n\n";
                echo "4. Optimizing for production...\n";
                $kernel->call('config:cache');
                $kernel->call('route:cache');
                $kernel->call('view:cache');
                echo "   ✓ Optimization complete\n\n";
                echo "=== FRESH INSTALL COMPLETE ===\n";
                break;
            
            case 'migrate':
                echo "Running migrations...\n";
                $kernel->call('migrate', ['--force' => true]);
                echo $kernel->output();
                break;
            
            case 'migrate-fresh':
                echo "⚠️  Running fresh migrations (DELETES ALL DATA)...\n";
                $kernel->call('migrate:fresh', ['--force' => true]);
                echo $kernel->output();
                break;
            
            case 'migrate-rollback':
                echo "Rolling back last migration...\n";
                $kernel->call('migrate:rollback', ['--force' => true]);
                echo $kernel->output();
                break;
            
            case 'seed':
                echo "Running all seeders...\n";
                $kernel->call('db:seed', ['--force' => true]);
                echo $kernel->output();
                break;
            
            case 'seed-users':
                echo "Running UserSeeder...\n";
                $kernel->call('db:seed', ['--class' => 'UserSeeder', '--force' => true]);
                echo $kernel->output();
                break;
            
            case 'seed-master':
                echo "Running master data seeders...\n";
                $kernel->call('db:seed', ['--class' => 'DepartmentSeeder', '--force' => true]);
                $kernel->call('db:seed', ['--class' => 'StatusSeeder', '--force' => true]);
                $kernel->call('db:seed', ['--class' => 'BuyerSeeder', '--force' => true]);
                echo $kernel->output();
                break;
            
            case 'cache-clear':
                echo "Clearing all caches...\n";
                $kernel->call('config:clear');
                $kernel->call('cache:clear');
                $kernel->call('route:clear');
                $kernel->call('view:clear');
                echo "   ✓ All caches cleared\n";
                break;
            
            case 'cache-optimize':
                echo "Optimizing caches for production...\n";
                $kernel->call('config:cache');
                $kernel->call('route:cache');
                $kernel->call('view:cache');
                echo "   ✓ Optimization complete\n";
                break;
            
            case 'storage-link':
                $target = LARAVEL_PATH . '/storage/app/public';
                $link = __DIR__ . '/storage';
                if (file_exists($link) || is_link($link)) { unlink($link); }
                if (symlink($target, $link)) {
                    echo "✓ Storage link created!\n";
                } else {
                    echo "✗ Failed to create storage link\n";
                    $success = false;
                }
                break;
            
            case 'status':
                echo "=== Application Status ===\n\n";
                echo "Laravel Path: " . LARAVEL_PATH . "\n";
                echo "Document Root: " . __DIR__ . "\n\n";
                echo "Environment:\n";
                echo "  APP_ENV: " . env('APP_ENV', 'not set') . "\n";
                echo "  APP_DEBUG: " . (env('APP_DEBUG') ? 'true' : 'false') . "\n";
                echo "  APP_URL: " . env('APP_URL', 'not set') . "\n\n";
                echo "Database:\n";
                echo "  DB_HOST: " . env('DB_HOST', 'not set') . "\n";
                echo "  DB_DATABASE: " . env('DB_DATABASE', 'not set') . "\n\n";
                try {
                    $pdo = new PDO(
                        sprintf('mysql:host=%s;dbname=%s', env('DB_HOST'), env('DB_DATABASE')),
                        env('DB_USERNAME'), env('DB_PASSWORD')
                    );
                    echo "Database Connection: ✓ Connected\n";
                } catch (Exception $e) {
                    echo "Database Connection: ✗ Failed - " . $e->getMessage() . "\n";
                }
                echo "\nStorage Permissions:\n";
                echo "  storage/: " . (is_writable(LARAVEL_PATH . '/storage') ? '✓ Writable' : '✗ Not writable') . "\n";
                echo "  bootstrap/cache/: " . (is_writable(LARAVEL_PATH . '/bootstrap/cache') ? '✓ Writable' : '✗ Not writable') . "\n";
                break;
            
            case 'migration-status':
                echo "Migration Status:\n";
                $kernel->call('migrate:status');
                echo $kernel->output();
                break;
            
            default:
                echo "Unknown action: $action\n";
                $success = false;
        }
    } catch (Exception $e) {
        echo "\n\n❌ ERROR: " . $e->getMessage() . "\n";
        $success = false;
    }
    
    $output = ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deploy Admin - Procurement Status</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; padding: 20px; color: #fff; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { text-align: center; margin-bottom: 30px; font-size: 1.8rem; background: linear-gradient(90deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .warning-banner { background: linear-gradient(90deg, #f093fb, #f5576c); color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        .section { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 20px; margin-bottom: 20px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
        .section h2 { font-size: 1.1rem; margin-bottom: 15px; color: #a8b2d1; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }
        .btn-group { display: flex; flex-wrap: wrap; gap: 10px; }
        .btn { display: inline-block; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px; transition: all 0.3s ease; border: none; cursor: pointer; }
        .btn-primary { background: linear-gradient(90deg, #667eea, #764ba2); color: white; }
        .btn-success { background: linear-gradient(90deg, #11998e, #38ef7d); color: white; }
        .btn-warning { background: linear-gradient(90deg, #f2994a, #f2c94c); color: #1a1a2e; }
        .btn-danger { background: linear-gradient(90deg, #eb3349, #f45c43); color: white; }
        .btn-info { background: linear-gradient(90deg, #4facfe, #00f2fe); color: #1a1a2e; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(0,0,0,0.3); }
        .output { background: #0d1117; border-radius: 10px; padding: 20px; margin-top: 20px; font-family: monospace; font-size: 13px; line-height: 1.6; white-space: pre-wrap; overflow-x: auto; border: 1px solid #30363d; }
        .output.success { border-left: 4px solid #38ef7d; }
        .output.error { border-left: 4px solid #f45c43; }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .description { font-size: 12px; color: #8892b0; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Deploy Admin Panel</h1>
        <div class="warning-banner">⚠️ DELETE this file after deployment!</div>
        
        <?php if ($output): ?>
        <div class="section">
            <h2>📋 Output</h2>
            <div class="output <?= $success ? 'success' : 'error' ?>"><?= htmlspecialchars($output) ?></div>
        </div>
        <?php endif; ?>
        
        <div class="grid-2">
            <div class="section">
                <h2>⚡ Quick Actions</h2>
                <p class="description">One-click deployment options</p>
                <div class="btn-group">
                    <a href="?key=<?= ACCESS_KEY ?>&action=update" class="btn btn-primary">🔄 Update Existing</a>
                    <a href="?key=<?= ACCESS_KEY ?>&action=fresh-install" class="btn btn-danger" onclick="return confirm('⚠️ DELETE all data?')">🆕 Fresh Install</a>
                </div>
            </div>
            <div class="section">
                <h2>📊 Status</h2>
                <p class="description">Check application status</p>
                <div class="btn-group">
                    <a href="?key=<?= ACCESS_KEY ?>&action=status" class="btn btn-info">ℹ️ App Status</a>
                    <a href="?key=<?= ACCESS_KEY ?>&action=migration-status" class="btn btn-info">📋 Migration Status</a>
                </div>
            </div>
        </div>
        
        <div class="grid-2">
            <div class="section">
                <h2>🗃️ Migrations</h2>
                <div class="btn-group">
                    <a href="?key=<?= ACCESS_KEY ?>&action=migrate" class="btn btn-success">▶️ Run Migrations</a>
                    <a href="?key=<?= ACCESS_KEY ?>&action=migrate-rollback" class="btn btn-warning">↩️ Rollback</a>
                    <a href="?key=<?= ACCESS_KEY ?>&action=migrate-fresh" class="btn btn-danger" onclick="return confirm('DELETE all tables?')">🔥 Fresh</a>
                </div>
            </div>
            <div class="section">
                <h2>🌱 Seeders</h2>
                <div class="btn-group">
                    <a href="?key=<?= ACCESS_KEY ?>&action=seed" class="btn btn-success">🌱 All Seeders</a>
                    <a href="?key=<?= ACCESS_KEY ?>&action=seed-users" class="btn btn-primary">👤 Users</a>
                    <a href="?key=<?= ACCESS_KEY ?>&action=seed-master" class="btn btn-primary">📁 Master Data</a>
                </div>
            </div>
        </div>
        
        <div class="grid-2">
            <div class="section">
                <h2>💾 Cache</h2>
                <div class="btn-group">
                    <a href="?key=<?= ACCESS_KEY ?>&action=cache-clear" class="btn btn-warning">🧹 Clear Cache</a>
                    <a href="?key=<?= ACCESS_KEY ?>&action=cache-optimize" class="btn btn-success">⚡ Optimize</a>
                </div>
            </div>
            <div class="section">
                <h2>📂 Storage</h2>
                <div class="btn-group">
                    <a href="?key=<?= ACCESS_KEY ?>&action=storage-link" class="btn btn-primary">🔗 Create Storage Link</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
