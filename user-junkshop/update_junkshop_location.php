<?php
require_once __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$respond = static function (bool $success, string $message, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(false, 'Only POST requests are allowed.', 405);
}

$sessionJunkshopId = (int) ($_SESSION['junkshop_id'] ?? 0);
$sessionUserId = defined('SESSION_USER_ID') ? (int) ($_SESSION[SESSION_USER_ID] ?? 0) : 0;
$junkshopId = $sessionJunkshopId > 0 ? $sessionJunkshopId : $sessionUserId;

if ($junkshopId <= 0 || !Auth::check() || Session::getRole() !== 'junkshop') {
    $respond(false, 'An active junkshop session is required.', 401);
}

$latitudeInput = trim((string) ($_POST['latitude'] ?? ''));
$longitudeInput = trim((string) ($_POST['longitude'] ?? ''));
$addressInput = trim((string) ($_POST['address'] ?? ''));
$latitude = filter_var($latitudeInput, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($longitudeInput, FILTER_VALIDATE_FLOAT);

if ($latitude === false || !is_finite((float) $latitude) || $latitude < -90 || $latitude > 90) {
    $respond(false, 'Latitude must be between -90 and 90.', 422);
}
if ($longitude === false || !is_finite((float) $longitude) || $longitude < -180 || $longitude > 180) {
    $respond(false, 'Longitude must be between -180 and 180.', 422);
}
if (strlen($addressInput) > 255) {
    $respond(false, 'Address must not exceed 255 characters.', 422);
}

try {
    $statement = Database::getInstance()->getPDO()->prepare(
        'UPDATE junkshop_profiles
         SET latitude = :latitude,
             longitude = :longitude,
             complete_address = COALESCE(:address, complete_address),
             updated_at = updated_at
         WHERE account_id = :id'
    );
    $statement->bindValue(':latitude', (float) $latitude);
    $statement->bindValue(':longitude', (float) $longitude);
    $statement->bindValue(':address', $addressInput === '' ? null : $addressInput, $addressInput === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $statement->bindValue(':id', $junkshopId, PDO::PARAM_INT);
    $statement->execute();

    if ($statement->rowCount() === 0) {
        $exists = Database::getInstance()->query(
            'SELECT account_id FROM junkshop_profiles WHERE account_id = :id LIMIT 1',
            ['id' => $junkshopId]
        )->fetchColumn();
        if ($exists === false) {
            $respond(false, 'Junkshop profile not found.', 404);
        }
    }

    $respond(true, 'Junkshop location and address updated successfully!');
} catch (Throwable $exception) {
    error_log('Junkshop location update error: ' . $exception->getMessage());
    $respond(false, 'Unable to update junkshop location.', 500);
}