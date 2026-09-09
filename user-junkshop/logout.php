<?php
/**
 * Logout Handler
 */

require_once __DIR__ . '/../app/bootstrap.php';

// Check if user is logged in
if (!Auth::check()) {
    header('Location: ' . APP_URL . '/user-junkshop/login.php');
    exit;
}

// Logout user
Auth::logout();

// Redirect to login with success message
header('Location: ' . APP_URL . '/user-junkshop/login.php?logout=1');
exit;
