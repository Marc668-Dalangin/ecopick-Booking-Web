<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/controllers/JunkshopAssignmentController.php';
require_once __DIR__ . '/../../app/controllers/BookingLifecycleController.php';
require_once __DIR__ . '/../../app/controllers/DashboardController.php';

header('Content-Type: application/json; charset=UTF-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Please log in again.',
        'session_expired' => true,
        'redirect' => APP_URL . '/user-junkshop/login.php',
        'data' => ['assignments' => []],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (Auth::userRole() !== 'junkshop') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Only approved junkshops can manage requests.',
        'data' => ['assignments' => []],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$assignmentController = new JunkshopAssignmentController();
$bookingLifecycleController = new BookingLifecycleController();
$dashboardController = new DashboardController();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_POST['action'] ?? ($_GET['action'] ?? 'list');

if ($method === 'POST') {
    if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Please try again.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'accept') {
        $pickupRequestId = (int) ($_POST['pickup_request_id'] ?? $_POST['assignment_id'] ?? 0);
        $result = $assignmentController->acceptRequest($pickupRequestId, Auth::userId());
        $result['data'] = ['requests' => $dashboardController->getPendingJunkshopRequests(Auth::userId())];
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'decline') {
        $pickupRequestId = (int) ($_POST['pickup_request_id'] ?? $_POST['assignment_id'] ?? 0);
        $result = $assignmentController->declineRequest($pickupRequestId, Auth::userId());
        $result['data'] = ['requests' => $dashboardController->getPendingJunkshopRequests(Auth::userId())];
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'schedule') {
        $requestId = (int) ($_POST['pickup_request_id'] ?? 0);
        $date = trim((string) ($_POST['scheduled_date'] ?? ''));
        $time = trim((string) ($_POST['scheduled_time'] ?? ''));
        $result = $bookingLifecycleController->schedulePickup($requestId, Auth::userId(), $date, $time);
        $result['data'] = ['requests' => $dashboardController->getPendingJunkshopRequests(Auth::userId())];
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'mark-for-pickup') {
        $requestId = (int) ($_POST['pickup_request_id'] ?? 0);
        $result = $bookingLifecycleController->markForPickup($requestId, Auth::userId());
        $result['data'] = ['requests' => $dashboardController->getPendingJunkshopRequests(Auth::userId())];
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'preview-settlement') {
        $requestId = (int) ($_POST['pickup_request_id'] ?? 0);
        $materialSettlements = json_decode((string) ($_POST['material_settlements'] ?? '[]'), true);
        $result = $bookingLifecycleController->previewFinalSettlement($requestId, Auth::userId(), is_array($materialSettlements) ? $materialSettlements : []);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'complete-transaction') {
        $requestId = (int) ($_POST['pickup_request_id'] ?? 0);
        $materialSettlements = json_decode((string) ($_POST['material_settlements'] ?? '[]'), true);
        $paymentMethod = trim((string) ($_POST['payment_method'] ?? 'Cash'));
        $paymentStatus = trim((string) ($_POST['payment_status'] ?? 'Paid'));
        $paymentReference = trim((string) ($_POST['payment_reference'] ?? ''));
        $pickupCollectionFee = max(0.0, (float) ($_POST['pickup_collection_fee'] ?? 0));
        $notes = trim((string) ($_POST['material_condition_notes'] ?? ''));
        $conditionLines = [];
        if (is_array($materialSettlements)) {
            foreach ($materialSettlements as $material) {
                $condition = trim((string) ($material['condition_notes'] ?? ''));
                if ($condition !== '') {
                    $label = trim((string) ($material['material_name'] ?? 'Material'));
                    $conditionLines[] = $label . ': ' . $condition;
                }
            }
        }
        if (!empty($conditionLines)) {
            $notes = implode("\n", $conditionLines);
        }
        $result = $bookingLifecycleController->completeTransaction($requestId, Auth::userId(), is_array($materialSettlements) ? $materialSettlements : [], $pickupCollectionFee, $paymentMethod, $paymentStatus, $paymentReference, $notes);
        $result['data'] = ['requests' => $dashboardController->getPendingJunkshopRequests(Auth::userId())];
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unsupported junkshop operation.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$requests = $dashboardController->getPendingJunkshopRequests(Auth::userId());
echo json_encode([
    'success' => true,
    'message' => 'Matched requests loaded.',
    'data' => ['requests' => $requests],
    'timestamp' => time(),
], JSON_UNESCAPED_UNICODE);
