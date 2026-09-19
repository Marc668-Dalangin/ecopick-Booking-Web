<?php
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Please log in again.',
        'session_expired' => true,
        'redirect' => APP_URL . '/user-junkshop/login.php',
        'pending_junkshop_ids' => [],
    ]);
    exit;
}

if (Auth::userRole() !== 'seller') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only sellers can access pickup statuses.', 'pending_junkshop_ids' => []]);
    exit;
}

try {
    $rows = Database::getInstance()->query(
        "SELECT DISTINCT junkshop_id
         FROM pickup_requests
         WHERE seller_account_id = :seller_id
           AND junkshop_id IS NOT NULL
           AND current_status IN ('Pending Request', 'Matched', 'Accepted', 'Scheduled', 'For Pickup')",
        ['seller_id' => (int) Auth::userId()]
    )->fetchAll();
    $pendingIds = array_values(array_map(static fn (array $row): int => (int) $row['junkshop_id'], $rows));
    echo json_encode(['success' => true, 'pending_junkshop_ids' => $pendingIds]);
} catch (Throwable $exception) {
    error_log('Pending pickup status endpoint error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load pickup statuses.', 'pending_junkshop_ids' => []]);
}