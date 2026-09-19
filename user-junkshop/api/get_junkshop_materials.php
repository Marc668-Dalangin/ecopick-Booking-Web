<?php
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}

if (Auth::userRole() !== 'seller') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only sellers can view junkshop materials.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET requests are supported.']);
    exit;
}

$junkshopId = (int)($_GET['junkshop_id'] ?? 0);
if ($junkshopId < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid junkshop is required.']);
    exit;
}

try {
    $database = Database::getInstance();
    $materials = $database->query(
        "SELECT DISTINCT jmp.id AS price_id, jmp.material_id, COALESCE(NULLIF(rm.name, ''), rm.material_name) AS material_name, rm.category,
                rm.description, rm.examples, rm.preparation_notes, rm.unit_of_measure, jmp.buying_price
         FROM junkshop_material_prices jmp
         JOIN recyclable_materials rm ON rm.id = jmp.material_id AND rm.is_active = 1
         JOIN junkshop_profiles jp ON jp.account_id = jmp.junkshop_account_id
         JOIN accounts a ON a.id = jp.account_id AND a.account_status = 'active'
         WHERE jmp.junkshop_account_id = :junkshop_id
           AND jmp.available = 1
           AND jp.approval_status = 'approved'
           AND (jp.partnership_expires_at IS NULL OR jp.partnership_expires_at > CURRENT_TIMESTAMP)
         ORDER BY rm.category ASC, material_name ASC",
        ['junkshop_id' => $junkshopId]
    )->fetchAll();

    echo json_encode(['success' => true, 'materials' => $materials], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('Junkshop materials endpoint error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load junkshop materials.']);
}
exit;
