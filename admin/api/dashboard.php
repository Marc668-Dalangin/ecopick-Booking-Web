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
        'redirect' => APP_URL . '/admin/login.php',
        'data' => [
            'stats' => [
                'total_sellers' => 0,
                'total_junkshops' => 0,
                'pending_junkshop_applications' => 0,
                'approved_junkshops' => 0,
            ],
            'completed_metrics' => [
                'platform_revenue' => 0,
                'weight_collected_kg' => 0,
                'completed_transactions' => 0,
            ],
            'pending' => [],
            'pending_count' => 0,
        ],
    ]);
    exit;
}

if (Auth::userRole() !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied.',
        'data' => [],
    ]);
    exit;
}

$controller = new DashboardController();
$stats = $controller->getAdminStats();
$completedMetrics = $controller->getCompletedTransactionMetrics();
$pending = $controller->listPendingJunkshops();

echo json_encode([
    'success' => true,
    'message' => 'Dashboard refreshed successfully.',
    'data' => [
        'stats' => $stats,
        'completed_metrics' => $completedMetrics,
        'pending' => $pending,
        'pending_count' => count($pending),
    ],
    'timestamp' => time(),
]);
