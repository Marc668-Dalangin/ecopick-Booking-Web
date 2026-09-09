<?php
/**
 * Database Configuration
 * Local development only
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ecopickdb');
define('DB_CHARSET', 'utf8mb4');

// PDO DSN
define('DB_DSN', 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET);

// Application settings
$httpScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$httpHost = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$serverPort = $_SERVER['SERVER_PORT'] ?? null;
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$projectBasePath = str_contains($scriptName, '/booking-website-lipacity/') ? '/booking-website-lipacity' : '';
if (!str_contains($httpHost, ':') && $serverPort && !in_array((string) $serverPort, ['80', '443'], true)) {
    $httpHost .= ':' . $serverPort;
}
define('APP_URL', rtrim($httpScheme . '://' . $httpHost . $projectBasePath, '/'));
define('APP_ENV', 'development');
define('SESSION_TIMEOUT', 3600); // 1 hour
