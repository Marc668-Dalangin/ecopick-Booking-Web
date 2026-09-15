<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/controllers/DashboardController.php';

header('Content-Type: application/json; charset=UTF-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Please log in again.',
        'session_expired' => true,
        'redirect' => APP_URL . '/admin-private-dnstl/login.php',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (Auth::userRole() !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST requests are required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = file_get_contents('php://input');
$payload = json_decode($input, true);
$payload = is_array($payload) ? $payload : $_POST;
$csrfToken = (string) ($payload['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

if (!CSRF::verify($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please try again.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$accountId = (int) ($payload['account_id'] ?? 0);
$action = (string) ($payload['action'] ?? '');
$controller = new DashboardController();

if ($accountId <= 0 || !in_array($action, ['activate', 'deactivate', 'delete'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid account action.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = $action === 'delete'
    ? $controller->deleteAccount($accountId)
    : $controller->updateAccountStatus($accountId, $action === 'activate' ? 'active' : 'inactive');

if (!$result['success']) {
    http_response_code(422);
}

echo json_encode([
    'success' => (bool) $result['success'],
    'message' => $result['message'],
    'data' => [
        'account_id' => $accountId,
        'action' => $action,
        'account_status' => $action === 'activate' ? 'active' : ($action === 'deactivate' ? 'inactive' : null),
    ],
], JSON_UNESCAPED_UNICODE);
