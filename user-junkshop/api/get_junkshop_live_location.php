<?php
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::check() || !in_array(Auth::userRole(), ['seller', 'junkshop'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT);
if (!$bookingId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid booking.']);
    exit;
}

try {
    $role = Auth::userRole();
    $ownership = $role === 'seller' ? 'pr.seller_account_id = :account_id' : 'pr.junkshop_id = :account_id';
    $database = Database::getInstance();
    $pdo = $database->getPDO();
    $stmt = $pdo->prepare(
        "SELECT pr.current_status, pr.junkshop_lat, pr.junkshop_lng
         FROM pickup_requests pr
         WHERE pr.id = :booking_id AND {$ownership}
         LIMIT 1",
    );
    $stmt->execute(['booking_id' => $bookingId, 'account_id' => (int) Auth::userId()]);
    $request = $stmt->fetch();
    if (!$request) {
        $stmt = null;
        $pdo = null;
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Booking not found.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'status' => $request['current_status'],
        'junkshop_lat' => $request['junkshop_lat'] !== null ? (float) $request['junkshop_lat'] : null,
        'junkshop_lng' => $request['junkshop_lng'] !== null ? (float) $request['junkshop_lng'] : null,
    ]);
} catch (Throwable $exception) {
    $stmt = null;
    $pdo = null;
    error_log('Junkshop live location fetch failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to fetch location.']);
}
$stmt = null;
$pdo = null;
