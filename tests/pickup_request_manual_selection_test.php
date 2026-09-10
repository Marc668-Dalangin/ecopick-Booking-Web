<?php
require_once __DIR__ . '/../app/controllers/PickupRequestController.php';

$controller = new PickupRequestController();
$method = new ReflectionMethod(PickupRequestController::class, 'normalizeRequestData');
$method->setAccessible(true);

$data = [
    'items' => [
        ['material_id' => 1, 'estimated_weight' => 12.5],
    ],
    'pickup_address' => '123 Main St',
    'pickup_location_name' => 'Home',
    'approximate_distance_km' => 4.5,
    'barangay' => 'San Jose',
    'preferred_pickup_date' => '2026-09-12',
    'preferred_pickup_time' => '9:00 AM',
    'notes' => 'Please call first',
    'junkshop_id' => 5,
];

$normalized = $method->invoke($controller, $data);
if ((int)($normalized['junkshop_id'] ?? 0) !== 5) {
    fwrite(STDERR, "manual-selection test failed: junkshop_id was not normalized\n");
    exit(1);
}

echo "manual-selection test passed\n";
