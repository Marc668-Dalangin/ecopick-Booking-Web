<?php
require 'C:/xampp/htdocs/booking-website-lipacity/app/bootstrap.php';
require 'C:/xampp/htdocs/booking-website-lipacity/app/controllers/PickupRequestController.php';
$db = Database::getInstance();
$pdo = $db->getPDO();

$roles = [
    'seller' => (int) $pdo->query("SELECT id FROM roles WHERE name = 'seller'")->fetchColumn(),
    'junkshop' => (int) $pdo->query("SELECT id FROM roles WHERE name = 'junkshop'")->fetchColumn(),
];

$pdo->exec("DELETE FROM accounts WHERE email IN ('seller.test4@example.com', 'junkshop.test4@example.com')");
$pdo->prepare('INSERT INTO accounts (role_id, account_role, email, password_hash, full_name, mobile_number, account_status) VALUES (:role_id, :account_role, :email, :password_hash, :full_name, :mobile_number, :account_status)')
    ->execute([
        'role_id' => $roles['seller'],
        'account_role' => 'seller',
        'email' => 'seller.test4@example.com',
        'password_hash' => password_hash('pw', PASSWORD_BCRYPT),
        'full_name' => 'Seller',
        'mobile_number' => '09912345678',
        'account_status' => 'active',
    ]);
$sellerId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO accounts (role_id, account_role, email, password_hash, full_name, mobile_number, account_status) VALUES (:role_id, :account_role, :email, :password_hash, :full_name, :mobile_number, :account_status)')
    ->execute([
        'role_id' => $roles['junkshop'],
        'account_role' => 'junkshop',
        'email' => 'junkshop.test4@example.com',
        'password_hash' => password_hash('pw', PASSWORD_BCRYPT),
        'full_name' => 'Junkshop',
        'mobile_number' => '09912345678',
        'account_status' => 'active',
    ]);
$junkshopId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO junkshop_profiles (account_id, business_name, owner_name, complete_address, operating_schedule, business_permit_reference, approval_status, is_available, latitude, longitude) VALUES (:account_id, :business_name, :owner_name, :complete_address, :operating_schedule, :business_permit_reference, :approval_status, 1, :latitude, :longitude) ON DUPLICATE KEY UPDATE business_name = VALUES(business_name), approval_status = VALUES(approval_status), is_available = VALUES(is_available), latitude = VALUES(latitude), longitude = VALUES(longitude)')
    ->execute([
        'account_id' => $junkshopId,
        'business_name' => 'Junkshop Co.',
        'owner_name' => 'Owner',
        'complete_address' => '123 Test',
        'operating_schedule' => 'Mon-Fri',
        'business_permit_reference' => 'BP',
        'approval_status' => 'approved',
        'latitude' => 14.123,
        'longitude' => 121.123,
    ]);

$materialId = (int) $pdo->query("SELECT id FROM recyclable_materials WHERE COALESCE(name, material_name) = 'Newspapers' LIMIT 1")->fetchColumn();
$secondMaterialId = (int) $pdo->query("SELECT id FROM recyclable_materials WHERE id <> $materialId ORDER BY id ASC LIMIT 1")->fetchColumn();
$pdo->prepare('INSERT INTO junkshop_material_prices (junkshop_account_id, material_id, buying_price, available) VALUES (:junkshop_account_id, :material_id, :buying_price, 1) ON DUPLICATE KEY UPDATE buying_price = VALUES(buying_price), available = VALUES(available)')
    ->execute(['junkshop_account_id' => $junkshopId, 'material_id' => $materialId, 'buying_price' => '18.50']);
$pdo->prepare('INSERT INTO junkshop_material_prices (junkshop_account_id, material_id, buying_price, available) VALUES (:junkshop_account_id, :material_id, :buying_price, 1) ON DUPLICATE KEY UPDATE buying_price = VALUES(buying_price), available = VALUES(available)')
    ->execute(['junkshop_account_id' => $junkshopId, 'material_id' => $secondMaterialId, 'buying_price' => '12.00']);

$controller = new PickupRequestController();
$result = $controller->createRequest($sellerId, [
    'items' => [
        ['material_id' => $materialId, 'estimated_weight' => 5.5],
        ['material_id' => $secondMaterialId, 'estimated_weight' => 5.0],
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
var_export($result);
