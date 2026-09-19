<?php
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::check() || !in_array(Auth::userRole(), ['seller', 'junkshop'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}
$role = Auth::userRole();
$accountId = (int) Auth::userId();
session_write_close();

$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT);
if (!$bookingId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid booking.']);
    exit;
}

try {
    $ownership = $role === 'seller' ? 'pr.seller_account_id = :account_id' : 'pr.junkshop_id = :account_id';
    $database = Database::getInstance();
    $pdo = $database->getPDO();
    $stmt = $pdo->prepare(
        "SELECT pr.current_status, pr.seller_lat, pr.seller_lng, pr.junkshop_lat, pr.junkshop_lng, pr.collector_lat, pr.collector_lng
         FROM pickup_requests pr
         WHERE pr.id = :booking_id AND {$ownership}
         LIMIT 1",
    );
    $stmt->execute(['booking_id' => $bookingId, 'account_id' => $accountId]);
    $request = $stmt->fetch();
    if (!$request) {
        $stmt = null;
        $pdo = null;
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Booking not found.']);
        exit;
    }

    $collectorLat = $request['collector_lat'] !== null ? (float) $request['collector_lat'] : ($request['junkshop_lat'] !== null ? (float) $request['junkshop_lat'] : null);
    $collectorLng = $request['collector_lng'] !== null ? (float) $request['collector_lng'] : ($request['junkshop_lng'] !== null ? (float) $request['junkshop_lng'] : null);

    echo json_encode([
        'success' => true,
        'status' => $request['current_status'],
        'seller_lat' => $request['seller_lat'] !== null ? (float) $request['seller_lat'] : null,
        'seller_lng' => $request['seller_lng'] !== null ? (float) $request['seller_lng'] : null,
        'junkshop_lat' => $request['junkshop_lat'] !== null ? (float) $request['junkshop_lat'] : null,
        'junkshop_lng' => $request['junkshop_lng'] !== null ? (float) $request['junkshop_lng'] : null,
        'collector_lat' => $collectorLat,
        'collector_lng' => $collectorLng,
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
