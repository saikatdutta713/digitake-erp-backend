<?php
// Secret token protection
if (($_GET['secret'] ?? '') !== 'digitake_hostinger_init_98234') {
    http_response_code(403);
    die('Unauthorized');
}

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
echo "ENV_CONFIGURED_SUCCESSFULLY";
