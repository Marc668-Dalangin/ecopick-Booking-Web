<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/controllers/DashboardController.php';

header('Content-Type: application/json; charset=UTF-8');

set_exception_handler(static function (Throwable $exception): void {
    error_log('Matched requests polling error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Matched requests are temporarily unavailable.',
        'data' => ['requests' => [], 'count' => 0],
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Please log in again.',
        'session_expired' => true,
        'redirect' => APP_URL . '/user-junkshop/login.php',
        'data' => ['requests' => [], 'count' => 0],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (Auth::userRole() !== 'junkshop') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Only junkshops can access matched requests.',
        'data' => ['requests' => [], 'count' => 0],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!empty($_SESSION['is_expired'])) {
    echo json_encode([
        'success' => true,
        'data' => ['requests' => [], 'count' => 0],
        'timestamp' => time(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

session_write_close();
$dashboardController = new DashboardController();
$junkshopId = (int) Auth::userId();
$requests = $dashboardController->getPendingJunkshopRequests($junkshopId);
$count = $dashboardController->getJunkshopRequestCount($junkshopId);

echo json_encode([
    'success' => true,
    'message' => 'Matched requests loaded.',
    'data' => [
        'requests' => $requests,
        'count' => $count,
    ],
    'timestamp' => time(),
], JSON_UNESCAPED_UNICODE);
