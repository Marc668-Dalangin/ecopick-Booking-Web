<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/controllers/DashboardController.php';

header('Content-Type: application/json; charset=UTF-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.', 'session_expired' => true, 'redirect' => APP_URL . '/user-junkshop/login.php']);
    exit;
}
if (Auth::userRole() !== 'junkshop') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only junkshops can update availability.']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !CSRF::verify($_POST['_csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid availability update request.']);
    exit;
}

$result = (new DashboardController())->updateJunkshopAvailability(
    (int) Auth::userId(),
    ($_POST['is_available'] ?? '') === '1'
);
if (empty($result['success'])) {
    http_response_code(422);
}
echo json_encode($result, JSON_UNESCAPED_UNICODE);