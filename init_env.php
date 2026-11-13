<?php
// Secret token protection
if (($_GET['secret'] ?? '') !== 'digitake_hostinger_init_98234') {
    http_response_code(403);
    die('Unauthorized');
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== Hostinger Backend Initializer ===\n\n";

// 1. Create runtime directories
$dirs = [
    __DIR__ . '/bootstrap/cache',
    __DIR__ . '/storage/framework/cache/data',
    __DIR__ . '/storage/framework/sessions',
    __DIR__ . '/storage/framework/views',
    __DIR__ . '/storage/logs',
    __DIR__ . '/storage/app/public',
    __DIR__ . '/storage/app/private',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        if (@mkdir($dir, 0777, true)) {
            echo "[OK] Created directory: " . basename($dir) . "\n";
        } else {
            echo "[FAIL] Could not create directory: $dir\n";
        }
    } else {
        echo "[EXISTS] Directory: " . basename($dir) . "\n";
    }
    @chmod($dir, 0777);
}

// 2. Remove blocking files
if (file_exists(__DIR__ . '/composer.lock')) {
    if (@unlink(__DIR__ . '/composer.lock')) {
        echo "[OK] Deleted untracked composer.lock\n";
    } else {
        echo "[WARN] Could not delete composer.lock\n";
    }
} else {
    echo "[INFO] No composer.lock found\n";
}

if (file_exists(__DIR__ . '/default.php')) {
    @unlink(__DIR__ . '/default.php');
    echo "[OK] Removed default.php\n";
}

// 3. Write production .env
$env = <<<EOT
APP_NAME=DigitakeERP
APP_ENV=production
APP_KEY=base64:xa1vHLhbHlBc4ABU/fkxM5PNVXdNSgnYLC6UDUhT8xc=
APP_DEBUG=false
APP_URL=https://erp.digitakeweb.com

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u273317821_erp
DB_USERNAME=u273317821_erp
DB_PASSWORD=DigitakeErp2026!Secure

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database
EOT;

file_put_contents(__DIR__ . '/.env', $env);
echo "[OK] .env configured successfully\n";

// 4. Test database connection
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=u273317821_erp', 'u273317821_erp', 'DigitakeErp2026!Secure', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    $stmt = $pdo->query('SELECT COUNT(*) as cnt FROM users');
    $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;
    echo "[OK] Database connected! Users in DB: $userCount\n";
} catch (Exception $e) {
    echo "[ERROR] Database connection failed: " . $e->getMessage() . "\n";
}

// 5. Try git reset if shell is allowed
if (function_exists('shell_exec')) {
    $gitStatus = @shell_exec('git status 2>&1');
    echo "[INFO] Git status:\n" . ($gitStatus ?: 'empty') . "\n";
    $gitClean = @shell_exec('git checkout -- . 2>&1');
    echo "[INFO] Git checkout result:\n" . ($gitClean ?: 'none') . "\n";
}

echo "\nINIT_COMPLETE_SUCCESS\n";
