<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/controllers/PickupRequestController.php';

header('Content-Type: application/json; charset=UTF-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.', 'session_expired' => true, 'redirect' => APP_URL . '/user-junkshop/login.php', 'data' => ['requests' => []]], JSON_UNESCAPED_UNICODE);
    exit;
}

if (Auth::userRole() !== 'seller') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only sellers can access pickup requests.', 'data' => ['requests' => []]], JSON_UNESCAPED_UNICODE);
    exit;
}

$sellerId = (int) Auth::userId();
$controller = new PickupRequestController();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_POST['action'] ?? ($_GET['action'] ?? 'list');

if ($method !== 'POST') {
    session_write_close();
}

try {
if ($method === 'POST') {
    if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Please try again.', 'validation_errors' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    session_write_close();

    if ($action === 'create') {
        $items = [];
        $materialIds = (array) ($_POST['material_id'] ?? []);
        $weights = (array) ($_POST['estimated_weight'] ?? []);
        foreach ($materialIds as $index => $materialId) {
            $items[] = [
                'material_id' => $materialId,
                'estimated_weight' => $weights[$index] ?? '',
            ];
        }

        $result = $controller->createRequest($sellerId, [
            'items' => $items,
            'junkshop_id' => $_POST['junkshop_id'] ?? 0,
            'contact_number' => $_POST['contact_number'] ?? '',
            'pickup_address' => $_POST['pickup_address'] ?? '',
            'approximate_distance_km' => $_POST['approximate_distance_km'] ?? '',
            'seller_lat' => $_POST['seller_lat'] ?? '',
            'seller_lng' => $_POST['seller_lng'] ?? '',
            'preferred_pickup_date' => $_POST['preferred_pickup_date'] ?? '',
            'preferred_pickup_time' => $_POST['preferred_pickup_time'] ?? '',
            'notes' => $_POST['notes'] ?? '',
        ], $_FILES['photo'] ?? null);

        if (!empty($result['success'])) {
            $result['data'] = ['requests' => [], 'request' => $result['request']];
            unset($result['request']);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'cancel') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $result = $controller->cancelRequest($requestId, $sellerId);
        $result['validation_errors'] = [];
        $result['data'] = ['requests' => $controller->listSellerRequests($sellerId)];
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unsupported pickup request action.', 'validation_errors' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'details') {
    $request = $controller->getSellerRequestDetails((int) ($_GET['request_id'] ?? 0), $sellerId);
    if ($request === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pickup request not found.', 'data' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'Pickup request loaded.', 'data' => ['request' => $request], 'validation_errors' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$requests = $controller->listSellerRequests($sellerId);
echo json_encode(['success' => true, 'message' => 'Pickup requests loaded.', 'data' => ['requests' => $requests], 'validation_errors' => [], 'timestamp' => time()], JSON_UNESCAPED_UNICODE);
} catch (PDOException $exception) {
    error_log('Pickup request database error: ' . $exception->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'The pickup request service is temporarily unavailable. Please try again later.', 'validation_errors' => [], 'data' => ['requests' => []]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('Pickup request handler error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to process the pickup request right now.', 'validation_errors' => [], 'data' => ['requests' => []]], JSON_UNESCAPED_UNICODE);
}
