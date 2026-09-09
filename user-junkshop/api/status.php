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
        'redirect' => APP_URL . '/user-junkshop/login.php',
        'data' => [
            'approval_status' => 'pending',
        ],
    ]);
    exit;
}

if (Auth::userRole() === 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Admin accounts do not use the junkshop status API.',
        'data' => [],
    ]);
    exit;
}

$controller = new DashboardController();
$profile = Auth::userRole() === 'seller'
    ? $controller->getSellerProfile(Auth::userId())
    : $controller->getJunkshopProfile(Auth::userId());

$status = strtolower((string)($profile['approval_status'] ?? 'pending'));

if (Auth::userRole() === 'seller') {
    $status = 'active';
}

echo json_encode([
    'success' => true,
    'message' => 'Approval status refreshed.',
    'data' => [
        'role' => Auth::userRole(),
        'approval_status' => $status,
        'profile' => $profile,
    ],
    'timestamp' => time(),
]);
