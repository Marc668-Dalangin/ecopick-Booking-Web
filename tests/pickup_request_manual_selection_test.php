<?php
require_once __DIR__ . '/../app/controllers/PickupRequestController.php';

$controller = (new ReflectionClass(PickupRequestController::class))->newInstanceWithoutConstructor();
$method = new ReflectionMethod(PickupRequestController::class, 'normalizeRequestData');
$method->setAccessible(true);

$feeCases = [
    [0.0, 5.0, 0.0],
    [0.4, 5.0, 0.0],
    [7.14, 5.0, 70.0],
    [7.5, 5.0, 75.0],
];
foreach ($feeCases as [$distance, $rate, $expected]) {
    $actual = FeeCalculator::calculatePickupFee($distance, $rate);
    if ($actual !== $expected) {
        fwrite(STDERR, "manual-selection test failed: pickup fee for {$distance} km was {$actual}, expected {$expected}\n");
        exit(1);
    }
}

$data = [
    'items' => [
        ['material_id' => 1, 'estimated_weight' => 12.5],
    ],
    'pickup_address' => '123 Main St',
    'approximate_distance_km' => 4.5,
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
