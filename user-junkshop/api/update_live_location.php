<?php
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (!Auth::check() || Auth::userRole() !== 'junkshop') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only junkshops can update live location.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verify($_POST['_csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid location update request.']);
    exit;
}
$bookingId = (int) ($_POST['booking_id'] ?? 0);
$lat = filter_var($_POST['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($_POST['lng'] ?? null, FILTER_VALIDATE_FLOAT);
if ($bookingId <= 0 || $lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Valid booking and coordinates are required.']);
    exit;
}
try {
    $db = Database::getInstance();
    $statement = $db->query(
        "UPDATE pickup_requests SET junkshop_lat = :lat, junkshop_lng = :lng, last_location_update = CURRENT_TIMESTAMP WHERE id = :booking_id AND junkshop_id = :junkshop_id AND current_status = 'For Pickup'",
        ['lat' => $lat, 'lng' => $lng, 'booking_id' => $bookingId, 'junkshop_id' => Auth::userId()]
    );
    echo json_encode(['success' => $statement->rowCount() > 0]);
} catch (Throwable $exception) {
    error_log('Live location update error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update live location.']);
}
