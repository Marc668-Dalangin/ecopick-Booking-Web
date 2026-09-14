<?php
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::check() || Auth::userRole() !== 'junkshop') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}
$junkshopId = (int) Auth::userId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verify()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}
session_write_close();

$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$lat = trim((string) ($_POST['lat'] ?? ''));
$lng = trim((string) ($_POST['lng'] ?? ''));
$coordinatePattern = '/^-?(?:\d+)(?:\.\d+)?$/';
$latValue = is_numeric($lat) ? (float) $lat : null;
$lngValue = is_numeric($lng) ? (float) $lng : null;
if (!$bookingId || !preg_match($coordinatePattern, $lat) || !preg_match($coordinatePattern, $lng) || $latValue === null || $lngValue === null || $latValue < -90 || $latValue > 90 || $lngValue < -180 || $lngValue > 180) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid location.']);
    exit;
}

try {
    $database = Database::getInstance();
    $pdo = $database->getPDO();
    $stmt = $pdo->prepare(
        "UPDATE pickup_requests
         SET junkshop_lat = :lat, junkshop_lng = :lng
         WHERE id = :booking_id AND junkshop_id = :junkshop_id AND current_status <> 'Completed'",
    );
    $stmt->execute(['lat' => $lat, 'lng' => $lng, 'booking_id' => $bookingId, 'junkshop_id' => $junkshopId]);
    $updated = $stmt->rowCount();
    echo json_encode(['success' => $updated > 0]);
} catch (Throwable $exception) {
    $stmt = null;
    $pdo = null;
    error_log('Junkshop live location update failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update location.']);
}
$stmt = null;
$pdo = null;
