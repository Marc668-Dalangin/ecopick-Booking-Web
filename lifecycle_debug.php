<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/controllers/PickupRequestController.php';
$db = Database::getInstance();
$pdo = $db->getPDO();

function createTestUser(string $email, string $roleName, string $fullName, string $passwordHash): int
{
    global $pdo;
    $roleId = (int) $pdo->query("SELECT id FROM roles WHERE name = '$roleName'")->fetchColumn();
    if (!$roleId) {
        throw new RuntimeException("Role not found: $roleName");
    }

    $stmt = $pdo->prepare('INSERT INTO accounts (role_id, account_role, email, password_hash, full_name, mobile_number, account_status) VALUES (:role_id, :account_role, :email, :password_hash, :full_name, :mobile_number, :account_status) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), full_name = VALUES(full_name), mobile_number = VALUES(mobile_number), account_status = VALUES(account_status), account_role = VALUES(account_role)');
    $stmt->execute([
        'role_id' => $roleId,
        'account_role' => $roleName,
        'email' => $email,
        'password_hash' => $passwordHash,
        'full_name' => $fullName,
        'mobile_number' => '09912345678',
        'account_status' => 'active',
    ]);

    $existingId = (int) $pdo->query("SELECT id FROM accounts WHERE email = '$email' LIMIT 1")->fetchColumn();
    return $existingId > 0 ? $existingId : (int) $pdo->lastInsertId();
}

function ensureJunkshopProfile(int $accountId, string $businessName): void
{
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO junkshop_profiles (account_id, business_name, owner_name, complete_address, operating_schedule, business_permit_reference, approval_status, is_available, latitude, longitude) VALUES (:account_id, :business_name, :owner_name, :complete_address, :operating_schedule, :business_permit_reference, :approval_status, 1, :latitude, :longitude) ON DUPLICATE KEY UPDATE business_name = VALUES(business_name), approval_status = VALUES(approval_status), is_available = VALUES(is_available), latitude = VALUES(latitude), longitude = VALUES(longitude)');
    $stmt->execute([
        'account_id' => $accountId,
        'business_name' => $businessName,
        'owner_name' => 'Test Owner',
        'complete_address' => '123 Test Street, Lipa City',
        'operating_schedule' => 'Monday–Friday | 8:00 AM–5:00 PM',
        'business_permit_reference' => 'BP-TEST-001',
        'approval_status' => 'approved',
        'latitude' => 14.123,
        'longitude' => 121.123,
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

$sellerId = createTestUser('seller.lifecycle.test@example.com', 'seller', 'Lifecycle Seller', password_hash('Password123', PASSWORD_BCRYPT));
$junkshopId = createTestUser('junkshop.lifecycle.test@example.com', 'junkshop', 'Lifecycle Junkshop', password_hash('Password123', PASSWORD_BCRYPT));
$adminId = createTestUser('admin.lifecycle.test@example.com', 'admin', 'Lifecycle Admin', password_hash('Password123', PASSWORD_BCRYPT));
$pdo->query("UPDATE accounts SET account_status = 'active' WHERE id IN ($sellerId, $junkshopId, $adminId)");
$materialId = (int) $pdo->query("SELECT id FROM recyclable_materials WHERE COALESCE(name, material_name) = 'Newspapers' LIMIT 1")->fetchColumn();
$secondMaterialId = (int) $pdo->query("SELECT id FROM recyclable_materials WHERE id <> $materialId ORDER BY id ASC LIMIT 1")->fetchColumn();
ensureJunkshopProfile($junkshopId, 'Lifecycle Junkshop Co.');
ensureMaterialPrice($junkshopId, $materialId, 18.50);
ensureMaterialPrice($junkshopId, $secondMaterialId, 12.00);

$pickupController = new PickupRequestController();
$tooLightResult = $pickupController->createRequest($sellerId, [
    'items' => [
        [ 'material_id' => $materialId, 'estimated_weight' => 4.9 ],
    ],
    'junkshop_id' => $junkshopId,
    'contact_number' => '639123456789',
    'pickup_address' => '123 Seller Street',
    'approximate_distance_km' => 4.5,
    'seller_lat' => '13.940000',
    'seller_lng' => '121.170000',
    'preferred_pickup_date' => date('Y-m-d', strtotime('+2 days')),
    'preferred_pickup_time' => '10:00 AM',
    'notes' => 'Should fail because under minimum weight',
]);
var_dump('tooLightResult', $tooLightResult);

$createResult = $pickupController->createRequest($sellerId, [
    'items' => [
        [ 'material_id' => $materialId, 'estimated_weight' => 5.5 ],
        [ 'material_id' => $secondMaterialId, 'estimated_weight' => 5.0 ],
    ],
    'junkshop_id' => $junkshopId,
    'contact_number' => '639123456789',
    'pickup_address' => '123 Seller Street',
    'approximate_distance_km' => 4.5,
    'seller_lat' => '13.940000',
    'seller_lng' => '121.170000',
    'preferred_pickup_date' => date('Y-m-d', strtotime('+2 days')),
    'preferred_pickup_time' => '10:00 AM',
    'notes' => 'Lifecycle test request',
]);
var_dump('createResult', $createResult);
