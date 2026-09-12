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
        'data' => [
            'stats' => [
                'total_sellers' => 0,
                'total_junkshops' => 0,
                'pending_junkshop_applications' => 0,
                'approved_junkshops' => 0,
            ],
            'pending' => [],
            'pending_count' => 0,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (Auth::userRole() !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied.',
        'data' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$controller = new DashboardController();
$input = file_get_contents('php://input');
$payload = [];

if (!empty($input)) {
    $decoded = json_decode($input, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

if (empty($payload)) {
    $payload = $_POST;
}

$csrfToken = $payload['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verify($csrfToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid security token. Please try again.',
            'validation_errors' => ['security' => 'Invalid security token.'],
            'data' => [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $accountId = (int)($payload['account_id'] ?? 0);
    $decision = (string)($payload['decision'] ?? '');

    if ($accountId <= 0) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'A valid junkshop account is required.',
            'validation_errors' => ['account_id' => 'A valid junkshop account is required.'],
            'data' => [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!in_array($decision, ['approved', 'rejected'], true)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid decision value.',
            'validation_errors' => ['decision' => 'Invalid decision value.'],
            'data' => [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = $controller->updateJunkshopApproval($accountId, $decision);
    $stats = $controller->getAdminStats();
    $pending = $controller->listPendingJunkshops();

    echo json_encode([
        'success' => (bool)$result['success'],
        'message' => $result['message'],
        'data' => [
            'stats' => $stats,
            'pending' => $pending,
            'pending_count' => count($pending),
            'status' => $decision,
            'account_id' => $accountId,
        ],
        'validation_errors' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$pending = $controller->listPendingJunkshops();
$stats = $controller->getAdminStats();

echo json_encode([
    'success' => true,
    'message' => 'Pending junkshop list refreshed.',
    'data' => [
        'stats' => $stats,
        'pending' => $pending,
        'pending_count' => count($pending),
    ],
    'timestamp' => time(),
], JSON_UNESCAPED_UNICODE);
