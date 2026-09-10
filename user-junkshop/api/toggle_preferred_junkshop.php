<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/controllers/MaterialPriceController.php';

header('Content-Type: application/json; charset=UTF-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.', 'session_expired' => true, 'redirect' => APP_URL . '/user-junkshop/login.php']);
    exit;
}

if (Auth::userRole() !== 'seller') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only sellers can manage preferred junkshops.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are supported.']);
    exit;
}

if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please try again.']);
    exit;
}

$junkshopId = (int) ($_POST['junkshop_id'] ?? 0);
if ($junkshopId < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid junkshop is required.']);
    exit;
}

$result = (new MaterialPriceController())->togglePreferredJunkshop((int) Auth::userId(), $junkshopId);
if (empty($result['success'])) {
    http_response_code(422);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);