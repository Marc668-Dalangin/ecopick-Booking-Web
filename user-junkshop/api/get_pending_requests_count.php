<?php
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Please log in again.',
        'session_expired' => true,
        'redirect' => APP_URL . '/user-junkshop/login.php',
        'count' => 0,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (Auth::userRole() !== 'junkshop') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Only junkshops can access pending request counts.',
        'count' => 0,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!empty($_SESSION['is_expired'])) {
    echo json_encode(['success' => true, 'count' => 0], JSON_UNESCAPED_UNICODE);
    exit;
}

$count = Database::getInstance()->query(
    'SELECT COUNT(*) FROM pickup_requests WHERE junkshop_id = :junkshop_id AND current_status = :status',
    [
        'junkshop_id' => Auth::userId(),
        'status' => 'Pending Request',
    ]
)->fetchColumn();

echo json_encode([
    'success' => true,
    'count' => (int) $count,
], JSON_UNESCAPED_UNICODE);
