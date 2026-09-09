<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/controllers/NotificationController.php';

header('Content-Type: application/json; charset=UTF-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Please log in again.',
        'session_expired' => true,
        'redirect' => APP_URL . '/user-junkshop/login.php',
        'data' => ['unread_count' => 0, 'notifications' => []],
    ]);
    exit;
}

$controller = new NotificationController();
echo json_encode([
    'success' => true,
    'data' => [
        'unread_count' => $controller->unreadCount(Auth::userId()),
        'notifications' => $controller->latestUnreadForUser(Auth::userId()),
    ],
    'timestamp' => time(),
]);