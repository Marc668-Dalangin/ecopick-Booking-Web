<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/PickupRequestController.php';

header('Content-Type: application/json; charset=UTF-8');

if (!Auth::check() || Auth::userRole() !== 'seller') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only sellers can cancel bookings.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please try again.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
if (!$bookingId || $bookingId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid booking ID is required.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = Database::getInstance()->getPDO();
    $statusStatement = $pdo->prepare(
        'SELECT current_status AS status FROM pickup_requests WHERE id = :id AND seller_account_id = :seller_id LIMIT 1'
    );
    $statusStatement->execute([
        ':id' => (int) $bookingId,
        ':seller_id' => (int) Auth::userId(),
    ]);
    $booking = $statusStatement->fetch(PDO::FETCH_ASSOC);
    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pickup request not found.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (in_array($booking['status'], ['Scheduled', 'For Pickup', 'Completed', 'Cancelled'], true)) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Cancellation is not allowed!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $controller = new PickupRequestController();
    $result = $controller->cancelRequest((int) $bookingId, (int) Auth::userId());
    if (!$result['success']) {
        http_response_code(409);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('Booking cancellation endpoint error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to cancel the booking right now.'], JSON_UNESCAPED_UNICODE);
}
