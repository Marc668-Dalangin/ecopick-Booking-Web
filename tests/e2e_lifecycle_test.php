<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/PickupRequestController.php';
require_once __DIR__ . '/../app/controllers/JunkshopAssignmentController.php';
require_once __DIR__ . '/../app/controllers/BookingLifecycleController.php';
require_once __DIR__ . '/../app/services/PlatformAnalytics.php';

$db = Database::getInstance();
$pdo = $db->getPDO();

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fail($message);
    }
}

function createTestUser(string $email, string $roleName, string $fullName, string $passwordHash): int
{
    global $pdo;
    $roleId = (int) $pdo->query("SELECT id FROM roles WHERE name = '$roleName'")->fetchColumn();
    if (!$roleId) {
        throw new RuntimeException("Role not found: $roleName");
    }

    $stmt = $pdo->prepare('INSERT INTO accounts (role_id, account_role, email, password_hash, full_name, mobile_number, account_status) VALUES (:role_id, :account_role, :email, :password_hash, :full_name, :mobile_number, :account_status)');
    $stmt->execute([
        'role_id' => $roleId,
        'account_role' => $roleName,
        'email' => $email,
        'password_hash' => $passwordHash,
        'full_name' => $fullName,
        'mobile_number' => '09912345678',
        'account_status' => 'active',
    ]);

    return (int) $pdo->lastInsertId();
}

function ensureJunkshopProfile(int $accountId, string $businessName): void
{
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO junkshop_profiles (account_id, business_name, owner_name, complete_address, operating_schedule, business_permit_reference, approval_status) VALUES (:account_id, :business_name, :owner_name, :complete_address, :operating_schedule, :business_permit_reference, :approval_status) ON DUPLICATE KEY UPDATE business_name = VALUES(business_name), approval_status = VALUES(approval_status)');
    $stmt->execute([
        'account_id' => $accountId,
        'business_name' => $businessName,
        'owner_name' => 'Test Owner',
        'complete_address' => '123 Test Street, Lipa City',
        'operating_schedule' => 'Monday–Friday | 8:00 AM–5:00 PM',
        'business_permit_reference' => 'BP-TEST-001',
        'approval_status' => 'approved',
    ]);
}

function ensureMaterialPrice(int $junkshopId, int $materialId, float $price): void
{
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO junkshop_material_prices (junkshop_account_id, material_id, buying_price, available) VALUES (:junkshop_id, :material_id, :price, 1) ON DUPLICATE KEY UPDATE buying_price = VALUES(buying_price), available = VALUES(available)');
    $stmt->execute([
        'junkshop_id' => $junkshopId,
        'material_id' => $materialId,
        'price' => number_format($price, 2, '.', ''),
    ]);
}

try {
    $sellerId = createTestUser('seller.lifecycle.test@example.com', 'seller', 'Lifecycle Seller', password_hash('Password123', PASSWORD_BCRYPT));
    $junkshopId = createTestUser('junkshop.lifecycle.test@example.com', 'junkshop', 'Lifecycle Junkshop', password_hash('Password123', PASSWORD_BCRYPT));
    $adminId = createTestUser('admin.lifecycle.test@example.com', 'admin', 'Lifecycle Admin', password_hash('Password123', PASSWORD_BCRYPT));

    $pdo->query("UPDATE accounts SET account_status = 'active' WHERE id IN ($sellerId, $junkshopId, $adminId)");

    $materialId = (int) $pdo->query("SELECT id FROM recyclable_materials WHERE material_name = 'Paper' LIMIT 1")->fetchColumn();
    assertTrue($materialId > 0, 'Paper material should exist for lifecycle test');
    $secondMaterialId = (int) $pdo->query("SELECT id FROM recyclable_materials WHERE id <> $materialId ORDER BY id ASC LIMIT 1")->fetchColumn();
    assertTrue($secondMaterialId > 0, 'A second material should exist for multi-material lifecycle test');

    ensureJunkshopProfile($junkshopId, 'Lifecycle Junkshop Co.');
    ensureMaterialPrice($junkshopId, $materialId, 18.50);
    ensureMaterialPrice($junkshopId, $secondMaterialId, 12.00);

    $pickupController = new PickupRequestController();
    $createResult = $pickupController->createRequest($sellerId, [
        'items' => [
            [ 'material_id' => $materialId, 'estimated_weight' => 5.5 ],
            [ 'material_id' => $secondMaterialId, 'estimated_weight' => 3.0 ],
        ],
        'pickup_address' => '123 Seller Street',
        'approximate_distance_km' => 4.5,
        'preferred_pickup_date' => date('Y-m-d', strtotime('+2 days')),
        'preferred_pickup_time' => '10:00 AM',
        'notes' => 'Lifecycle test request',
    ]);

    assertTrue(!empty($createResult['success']) && $createResult['success'] === true, 'Pickup request should be created successfully');
    $requestId = (int) ($createResult['request']['id'] ?? 0);
    assertTrue($requestId > 0, 'Created request should have valid id');

    $assignmentRow = $pdo->query("SELECT id, status, junkshop_id FROM junkshop_assignments WHERE pickup_request_id = $requestId ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    assertTrue($assignmentRow !== false, 'Matching engine should create a junkshop assignment');
    assertTrue((string) ($assignmentRow['status'] ?? '') === 'Matched', 'Assignment status should start as Matched');
    $snapshotCount = (int) $pdo->query("SELECT COUNT(*) FROM pickup_request_items WHERE pickup_request_id = $requestId AND estimated_buying_price_per_kg IS NOT NULL AND estimated_material_value IS NOT NULL")->fetchColumn();
    assertTrue($snapshotCount === 2, 'Matched junkshop prices should be snapshotted for every requested material');

    $assignmentController = new JunkshopAssignmentController();
    $acceptResult = $assignmentController->acceptRequest((int) $assignmentRow['id'], $junkshopId);
    assertTrue(($acceptResult['success'] ?? false) === true, 'Junkshop should be able to accept the assignment');
    $repeatAcceptResult = $assignmentController->acceptRequest((int) $assignmentRow['id'], $junkshopId);
    assertTrue(($repeatAcceptResult['success'] ?? true) === false, 'A matched assignment must not be accepted twice');

    $pickupState = $pdo->query("SELECT current_status FROM pickup_requests WHERE id = $requestId LIMIT 1")->fetchColumn();
    assertTrue((string) $pickupState === 'Accepted', 'Pickup request should become Accepted after accept');

    $lifecycleController = new BookingLifecycleController();
    $invalidScheduleResult = $lifecycleController->schedulePickup($requestId, $junkshopId, date('Y-m-d', strtotime('-1 day')), 'invalid time');
    assertTrue(($invalidScheduleResult['success'] ?? true) === false, 'Past or malformed schedules must be rejected');
    $scheduleResult = $lifecycleController->schedulePickup($requestId, $junkshopId, date('Y-m-d', strtotime('+3 days')), '09:00 AM');
    assertTrue(($scheduleResult['success'] ?? false) === true, 'Pickup request should schedule successfully');

    $cancelAfterSchedule = $pickupController->cancelRequest($requestId, $sellerId);
    assertTrue(($cancelAfterSchedule['success'] ?? true) === false, 'Seller cancellation must fail after scheduling');
    assertTrue((string) ($pdo->query("SELECT current_status FROM pickup_requests WHERE id = $requestId LIMIT 1")->fetchColumn()) === 'Scheduled', 'Rejected cancellation must preserve Scheduled status');

    $forPickupResult = $lifecycleController->markForPickup($requestId, $junkshopId);
    assertTrue(($forPickupResult['success'] ?? false) === true, 'Pickup request should move to For Pickup');

    $requestItems = $pdo->query("SELECT id FROM pickup_request_items WHERE pickup_request_id = $requestId ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
    assertTrue(count($requestItems) === 2, 'Both requested materials should be persisted');
    $materialSettlements = [
        [
            'pickup_request_item_id' => (int) $requestItems[0],
            'actual_weight_kg' => 4.0,
            'accepted' => true,
        ],
        [
            'pickup_request_item_id' => (int) $requestItems[1],
            'actual_weight_kg' => 2.0,
            'accepted' => true,
        ],
    ];

    $settlementPreview = $lifecycleController->previewFinalSettlement($requestId, $junkshopId, $materialSettlements);
    assertTrue(($settlementPreview['success'] ?? false) === true, 'Settlement preview should be available');
    assertTrue(isset($settlementPreview['data']['final_seller_amount']), 'Settlement preview should include final seller amount');

    $unpaidCompletion = $lifecycleController->completeTransaction($requestId, $junkshopId, $materialSettlements, 'Cash', 'Unpaid', '', 'Material in good condition');
    assertTrue(($unpaidCompletion['success'] ?? true) === false, 'Unpaid transactions must not be marked completed');

    $completeResult = $lifecycleController->completeTransaction($requestId, $junkshopId, $materialSettlements, 'Cash', 'Paid', 'TEST-PAYMENT-001', 'Material in good condition');
    assertTrue(($completeResult['success'] ?? false) === true, 'Transaction should complete successfully');

    $transactionCount = (int) $pdo->query("SELECT COUNT(*) FROM transactions WHERE pickup_request_id = $requestId")->fetchColumn();
    assertTrue($transactionCount === 1, 'Exactly one transaction should be created');
    $transactionMaterialCount = (int) $pdo->query("SELECT COUNT(*) FROM transaction_materials tm JOIN transactions t ON t.id = tm.transaction_id WHERE t.pickup_request_id = $requestId AND tm.accepted = 1")->fetchColumn();
    assertTrue($transactionMaterialCount === 2, 'Both accepted materials should be recorded in settlement');

    $requestStatus = (string) $pdo->query("SELECT current_status FROM pickup_requests WHERE id = $requestId LIMIT 1")->fetchColumn();
    assertTrue($requestStatus === 'Completed', 'Pickup request should end as Completed');

    $auditRows = $pdo->query("SELECT previous_status, new_status FROM booking_status_history WHERE pickup_request_id = $requestId ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $auditTrail = array_map(static fn (array $row): string => (($row['previous_status'] ?? '') === '' ? 'NULL' : $row['previous_status']) . ' -> ' . $row['new_status'], $auditRows);
    assertTrue($auditTrail === [
        'NULL -> Pending Request',
        'Pending Request -> Matched',
        'Matched -> Accepted',
        'Accepted -> Scheduled',
        'Scheduled -> For Pickup',
        'For Pickup -> Completed',
    ], 'Audit trail should record the complete lifecycle in order');

    $auditActorRows = $pdo->query("SELECT new_status, user_id FROM booking_status_history WHERE pickup_request_id = $requestId ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    assertTrue((int) ($auditActorRows[2]['user_id'] ?? 0) === $junkshopId, 'Accepted transition should record the acting junkshop');
    assertTrue((int) ($auditActorRows[3]['user_id'] ?? 0) === $junkshopId, 'Scheduled transition should record the acting junkshop');
    assertTrue((int) ($auditActorRows[4]['user_id'] ?? 0) === $junkshopId, 'For Pickup transition should record the acting junkshop');
    assertTrue((int) ($auditActorRows[5]['user_id'] ?? 0) === $junkshopId, 'Completed transition should record the acting junkshop');

    $registrationFee = $pdo->query("SELECT config_value FROM fee_configurations WHERE config_key = 'junkshop_registration_fee'")->fetchColumn();
    $renewalFee = $pdo->query("SELECT config_value FROM fee_configurations WHERE config_key = 'renewal_fee_1_month'")->fetchColumn();
    assertTrue($registrationFee !== false && $renewalFee !== false, 'Registration and renewal fee configurations should exist');

    $analytics = new PlatformAnalytics();
    $summary = $analytics->getPlatformSummary(date('Y-m-d', strtotime('-30 days')), date('Y-m-d', strtotime('+1 day')));
    assertTrue((int) ($summary['total_completed_transactions'] ?? 0) >= 1, 'Admin analytics should include a completed transaction');

    $pdo->exec("DELETE FROM transactions WHERE pickup_request_id = $requestId");
    $pdo->exec("DELETE FROM junkshop_assignments WHERE pickup_request_id = $requestId");
    $pdo->exec("DELETE FROM pickup_request_items WHERE pickup_request_id = $requestId");
    $pdo->exec("DELETE FROM pickup_request_status_history WHERE pickup_request_id = $requestId");
    $pdo->exec("DELETE FROM booking_status_history WHERE pickup_request_id = $requestId");
    $pdo->exec("DELETE FROM pickup_requests WHERE id = $requestId");
    $pdo->exec("DELETE FROM accounts WHERE id IN ($sellerId, $junkshopId, $adminId)");

    echo "Lifecycle verification passed.\n";
    echo "Created and validated a complete seller-to-admin transaction flow.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "TEST ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
