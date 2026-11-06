<?php
declare(strict_types=1);

require_once __DIR__ . '/environment.php';

const APP_NAME = 'TESCON Membership';
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$appBasePath = $scriptName === '' ? '' : dirname(dirname($scriptName));
$appBasePath = $appBasePath === '/' || $appBasePath === '.' ? '' : rtrim($appBasePath, '/');
define('APP_BASE_URL', $appBasePath);

define('DB_HOST', getenv('DB_HOST') ?: 'sql200.infinityfree.com');
define('DB_NAME', getenv('DB_NAME') ?: 'if0_43376513_tescon_membership');
define('DB_USER', getenv('DB_USER') ?: 'if0_43376513');
define('DB_PASS', getenv('DB_PASS') ?: 'Uxx3mIy59PN5t');

const SESSION_IDLE_TIMEOUT = 1800; 
const PAGE_SIZE = 20; 
define('APP_ENV', getenv('APP_ENV') ?: 'production');

// Control PHP error visibility based on environment.
// In production, errors are logged but never displayed to users.
if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

date_default_timezone_set('UTC');