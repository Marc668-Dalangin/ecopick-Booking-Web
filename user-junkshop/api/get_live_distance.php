<?php
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (!Auth::check() || Auth::userRole() !== 'seller') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only sellers can view live distance.']);
    exit;
}
$sellerId = (int) Auth::userId();
session_write_close();
$bookingId = (int) ($_GET['booking_id'] ?? 0);
if ($bookingId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid booking ID is required.']);
    exit;
}
try {
    $db = Database::getInstance();
    $statement = $db->query(
        "SELECT seller_lat, seller_lng, junkshop_lat, junkshop_lng, current_status FROM pickup_requests WHERE id = :booking_id AND seller_account_id = :seller_id LIMIT 1",
        ['booking_id' => $bookingId, 'seller_id' => $sellerId]
    );
    $booking = $statement->fetch();
    if (!$booking) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Booking not found.']);
        exit;
    }
    $distance = null;
    if ($booking['seller_lat'] !== null && $booking['seller_lng'] !== null && $booking['junkshop_lat'] !== null && $booking['junkshop_lng'] !== null) {
        $latDelta = deg2rad((float) $booking['junkshop_lat'] - (float) $booking['seller_lat']);
        $lngDelta = deg2rad((float) $booking['junkshop_lng'] - (float) $booking['seller_lng']);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad((float) $booking['seller_lat'])) * cos(deg2rad((float) $booking['junkshop_lat'])) * sin($lngDelta / 2) ** 2;
        $distance = round(6371 * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }
    echo json_encode(['distance_km' => $distance, 'status' => $booking['current_status']]);
} catch (Throwable $exception) {
    error_log('Live distance error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to calculate live distance.']);
}
