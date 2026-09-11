<?php
/**
 * Admin landing page entry
 */

require_once __DIR__ . '/../app/bootstrap.php';

if (!isset($_SESSION[SESSION_USER_ID]) || ($_SESSION[SESSION_ROLE_NAME] ?? null) !== 'admin' || !Auth::check()) {
    session_unset();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

header('Location: ' . APP_URL . '/admin/dashboard.php');
exit;
