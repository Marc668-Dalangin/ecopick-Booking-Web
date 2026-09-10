<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/controllers/PaymentController.php';

header('Content-Type: application/json; charset=UTF-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired.', 'redirect' => APP_URL . '/user-junkshop/login.php'], JSON_UNESCAPED_UNICODE);
    exit;
}

$controller = new PaymentController();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($action === 'view-proof' && $method === 'GET') {
    $controller->streamProof(Auth::userId(), (string) Auth::userRole(), (int) ($_GET['proof_id'] ?? 0));
    exit;
}

if ($method !== 'POST' || !CSRF::verify($_POST['_csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payment request.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'upload-proof' && Auth::userRole() === 'seller') {
    $result = $controller->uploadGcashProof(
        Auth::userId(),
        (int) ($_POST['transaction_id'] ?? 0),
        $_FILES['payment_proof'] ?? []
    );
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'confirm-payment' && Auth::userRole() === 'junkshop') {
    $result = $controller->confirmPayment(Auth::userId(), (int) ($_POST['transaction_id'] ?? 0));
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(403);
echo json_encode(['success' => false, 'message' => 'This payment action is not available for your account.'], JSON_UNESCAPED_UNICODE);
