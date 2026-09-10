<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/controllers/MaterialPriceController.php';

header('Content-Type: application/json; charset=UTF-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Please log in again.',
        'session_expired' => true,
        'redirect' => APP_URL . '/user-junkshop/login.php',
        'data' => ['materials' => [], 'prices' => []],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (Auth::userRole() !== 'junkshop') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Only approved junkshops may manage material prices.',
        'data' => ['materials' => [], 'prices' => []],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$controller = new MaterialPriceController();
$accountId = Auth::userId();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Please try again.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $materialId = (int)($_POST['material_id'] ?? 0);
    $priceId = (int)($_POST['price_id'] ?? 0);
    $amount = trim((string)($_POST['buying_price'] ?? ''));

    if ($action === 'add' || $action === 'update') {
        if ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid positive buying price.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $amount = number_format((float) $amount, 2, '.', '');
    }

    if ($action === 'add') {
        $result = $controller->addMaterialPrice($accountId, $materialId, $amount, 1);
    } elseif ($action === 'update') {
        $result = $controller->updateMaterialPrice($accountId, $priceId, $amount, 1);
    } elseif ($action === 'remove') {
        $result = $controller->removeMaterialPrice($accountId, $priceId);
    } else {
        $result = ['success' => false, 'message' => 'Unsupported action.'];
    }

    $prices = $controller->getJunkshopMaterialPrices($accountId);
    $materials = $controller->listActiveMaterials();

    echo json_encode([
        'success' => (bool)($result['success'] ?? false),
        'message' => $result['message'] ?? 'Request processed.',
        'data' => [
            'materials' => $materials,
            'prices' => $prices,
            'matches' => $result['matches'] ?? [],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$approvalStatus = $controller->getJunkshopApprovalStatus($accountId);
if ($approvalStatus !== 'approved') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Your junkshop is not approved to manage materials and prices yet.',
        'data' => ['materials' => [], 'prices' => []],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$materials = $controller->listActiveMaterials();
$prices = $controller->getJunkshopMaterialPrices($accountId);

echo json_encode([
    'success' => true,
    'message' => 'Material and price list loaded.',
    'data' => ['materials' => $materials, 'prices' => $prices],
    'timestamp' => time(),
], JSON_UNESCAPED_UNICODE);
