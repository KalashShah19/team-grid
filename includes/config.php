<?php
/**
 * Team-Grid Configuration
 * Core configuration, paths, and constants
 */

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_start();

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Base paths
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('DATA_PATH', ROOT_PATH . 'data' . DIRECTORY_SEPARATOR);
define('LOGS_PATH', DATA_PATH . 'logs' . DIRECTORY_SEPARATOR);
define('INCLUDES_PATH', ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR);

// Base URL detection
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$baseDir = rtrim($scriptDir, '/\\');
// Handle subdirectory (admin, api)
if (preg_match('#/(admin|api)$#', $baseDir)) {
    $baseDir = dirname($baseDir);
}
// Normalize any Windows backslashes introduced by dirname()
$baseDir = str_replace('\\', '/', $baseDir);
$baseDir = rtrim($baseDir, '/');
if ($baseDir === '/' || $baseDir === '.' || $baseDir === '\\') {
    $baseDir = '';
}
define('BASE_URL', $protocol . '://' . $host . $baseDir);

// App constants
define('APP_NAME', 'Team-Grid');
define('APP_VERSION', '1.0.0');

// Default admin
define('DEFAULT_ADMIN_FIRST_NAME', 'Admin');
define('DEFAULT_ADMIN_LAST_NAME', '');

// Notification polling interval (milliseconds)
define('NOTIFICATION_POLL_INTERVAL', 4000);

// Anti-spam: minimum seconds between comments
define('COMMENT_COOLDOWN', 2);

// Data files
define('USERS_FILE', DATA_PATH . 'users.json');
define('POLLS_FILE', DATA_PATH . 'polls.json');
define('MATCHES_FILE', DATA_PATH . 'matches.json');
define('NOTIFICATIONS_FILE', DATA_PATH . 'notifications.json');
define('PAYMENTS_FILE', DATA_PATH . 'payments.json');
define('FORMATIONS_FILE', DATA_PATH . 'formations.json');
define('TEAM_SELECTIONS_FILE', DATA_PATH . 'team_selections.json');

// Log files
define('LOGIN_LOG_FILE', LOGS_PATH . 'logins.json');
define('ACTION_LOG_FILE', LOGS_PATH . 'actions.json');
define('ERROR_LOG_FILE', LOGS_PATH . 'errors.json');

// Ensure data directories exist
if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0777, true);
if (!is_dir(LOGS_PATH)) mkdir(LOGS_PATH, 0777, true);

// Initialize data files if they don't exist
$dataFiles = [
    USERS_FILE => '[]',
    POLLS_FILE => '[]',
    MATCHES_FILE => '[]',
    NOTIFICATIONS_FILE => '[]',
    PAYMENTS_FILE => '[]',
    FORMATIONS_FILE => '[]',
    TEAM_SELECTIONS_FILE => '[]',
    LOGIN_LOG_FILE => '[]',
    ACTION_LOG_FILE => '[]',
    ERROR_LOG_FILE => '[]',
];

foreach ($dataFiles as $file => $default) {
    if (!file_exists($file)) {
        file_put_contents($file, $default);
    }
}

// Timezone
date_default_timezone_set('Asia/Kolkata');
