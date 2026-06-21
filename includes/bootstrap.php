<?php
/**
 * Bootstrap - loaded by every page. Sets up config, DB, session, helpers.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// --- Load config ---
$configFile = BASE_PATH . '/config/config.php';
if (!is_file($configFile)) {
    // Not installed yet -> send to installer (unless already there)
    if (!defined('SKIP_INSTALL_CHECK')) {
        $self = $_SERVER['SCRIPT_NAME'] ?? '';
        if (!str_contains($self, '/install/')) {
            header('Location: ' . dirname($_SERVER['SCRIPT_NAME']) . '/install/');
            exit('Application not installed. Redirecting to installer...');
        }
    }
    $GLOBALS['APP_CONFIG'] = ['app' => ['url' => '', 'name' => 'MemeRadar AI', 'env' => 'production']];
    return;
}

$GLOBALS['APP_CONFIG'] = require $configFile;

// --- Autoload core classes ---
spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once __DIR__ . '/functions.php';

// --- Error handling ---
$debug = (bool) config('app.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

date_default_timezone_set((string) config('app.timezone', 'UTC'));

// --- Secure session ---
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('memeradar_sess');
    session_start();
}

// --- Database ---
try {
    Database::init((array) config('db'));
} catch (Throwable $e) {
    if ($debug) {
        exit('DB connection failed: ' . $e->getMessage());
    }
    http_response_code(500);
    exit('Database connection error. Please try again later.');
}

// Preload settings
Settings::load();

// --- Maintenance mode (skips admin + install) ---
$self = $_SERVER['SCRIPT_NAME'] ?? '';
if (Settings::get('maintenance_mode', '0') === '1'
    && !str_contains($self, '/admin/')
    && !str_contains($self, '/install/')
    && !Auth::adminCheck()) {
    http_response_code(503);
    $siteName = Settings::get('site_name', 'MemeRadar AI');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Maintenance</title>'
        . '<style>body{background:#0f1117;color:#e7e9ee;font-family:sans-serif;text-align:center;padding-top:15%}'
        . 'h1{color:#00ff99}</style></head><body><h1>' . e($siteName) . '</h1>'
        . '<p>We are currently performing scheduled maintenance. Please check back soon.</p></body></html>';
    exit;
}
