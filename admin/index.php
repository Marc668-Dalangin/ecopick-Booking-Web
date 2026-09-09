<?php
/**
 * Admin landing page entry
 */

require_once __DIR__ . '/../app/bootstrap.php';

if (!Auth::check()) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

$role = Auth::userRole();

if ($role === 'admin') {
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit;
}

if ($role === 'seller' || $role === 'junkshop') {
    header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
    exit;
}

Auth::logout();
header('Location: ' . APP_URL . '/admin/login.php');
exit;
