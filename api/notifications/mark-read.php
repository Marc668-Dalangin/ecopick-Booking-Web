<?php
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Session expired.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'POST requests are required.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!CSRF::verify($csrfToken)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$notificationId = (int) ($payload['notification_id'] ?? 0);
if ($notificationId <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'A valid notification is required.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$statement = Database::getInstance()->query(
    'UPDATE notifications SET is_read = 1, read_at = COALESCE(read_at, CURRENT_TIMESTAMP) WHERE id = :id AND recipient_account_id = :account_id',
    [
        'id' => $notificationId,
        'account_id' => Auth::userId(),
    ]
);

echo json_encode([
    'success' => $statement->rowCount() >= 0,
    'message' => 'Notification marked as read.',
    'data' => ['notification_id' => $notificationId],
], JSON_UNESCAPED_UNICODE);
