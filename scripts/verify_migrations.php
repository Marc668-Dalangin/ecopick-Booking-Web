<?php
require_once __DIR__ . '/../app/bootstrap.php';

$expectedTables = [
    'accounts',
    'sellers',
    'junkshop_profiles',
    'recyclable_materials',
    'junkshop_material_prices',
    'pickup_requests',
    'pickup_request_items',
    'pickup_request_status_history',
    'junkshop_assignments',
    'fee_configurations',
    'fee_settings',
    'transactions',
    'booking_status_history',
    'transaction_payments',
    'junkshop_partnership_payments',
    'partnership_renewals',
    'payment_records',
    'payment_proofs',
    'payment_status_history',
    'concerns',
    'concern_status_history',
    'notifications',
    'email_logs',
    'schema_migrations',
];

$expectedColumns = [
    'pickup_requests' => ['id', 'booking_reference', 'seller_account_id', 'current_status'],
    'junkshop_assignments' => ['id', 'pickup_request_id', 'junkshop_id', 'status', 'distance_km'],
    'fee_configurations' => ['id', 'config_key', 'config_value', 'description'],
    'fee_settings' => ['id', 'expiration_notice_lead_days', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption'],
    'transactions' => ['id', 'pickup_request_id', 'junkshop_id', 'seller_id', 'actual_weight_kg'],
    'junkshop_profiles' => ['account_id', 'partnership_expires_at', 'renewal_status', 'gcash_account_name', 'gcash_account_number', 'last_expiration_notice_sent'],
    'pickup_request_items' => ['id', 'pickup_request_id', 'estimated_buying_price_per_kg', 'estimated_material_value'],
    'transaction_payments' => ['id', 'transaction_id', 'payment_purpose', 'payment_status'],
    'partnership_renewals' => ['id', 'junkshop_account_id', 'plan_type', 'payment_method', 'amount', 'reference_number', 'receipt_image', 'rejection_reason', 'status', 'created_at'],
    'payment_records' => ['id', 'junkshop_id', 'renewal_id', 'transaction_type', 'payment_method', 'amount', 'reference_number', 'receipt_image', 'status', 'created_at'],
    'payment_proofs' => ['id', 'transaction_id', 'seller_account_id', 'proof_status'],
    'concerns' => ['id', 'reporter_account_id', 'status'],
    'notifications' => ['id', 'recipient_account_id', 'related_pickup_request_id'],
    'email_logs' => ['id', 'recipient_email', 'subject', 'body', 'status', 'error_message', 'created_at'],
    'booking_status_history' => ['id', 'pickup_request_id', 'previous_status', 'new_status', 'responsible_party', 'user_id', 'changed_at'],
];

$requiredConfigKeys = ['ecopick_service_fee_pct', 'default_pickup_fee', 'junkshop_commission_pct'];

$requiredCascadeRules = [
    'sellers.account_id',
    'junkshop_profiles.account_id',
    'junkshop_material_prices.junkshop_account_id',
    'pickup_requests.seller_account_id',
    'pickup_request_status_history.changed_by_account_id',
    'junkshop_assignments.junkshop_id',
    'transactions.junkshop_id',
    'transactions.seller_id',
    'booking_status_history.user_id',
    'junkshop_partnership_payments.junkshop_account_id',
    'payment_proofs.seller_account_id',
    'payment_status_history.acting_account_id',
    'concerns.reporter_account_id',
    'notifications.recipient_account_id',
    'concern_status_history.acting_account_id',
    'transaction_materials.pickup_request_item_id',
];

$db = Database::getInstance();
$pdo = $db->getPDO();
$issues = [];

foreach ($expectedTables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($stmt->fetchColumn() === false) {
        $issues[] = "Missing table: $table";
        continue;
    }

    if (!isset($expectedColumns[$table])) {
        continue;
    }

    try {
        $metadata = $pdo->query("SHOW COLUMNS FROM `$table`");
        $existingColumns = [];
        while ($row = $metadata->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[] = $row['Field'];
        }

        foreach ($expectedColumns[$table] as $column) {
            if (!in_array($column, $existingColumns, true)) {
                $issues[] = "Missing column $column in $table";
            }
        }
    } catch (Throwable $e) {
        $issues[] = "Unable to inspect table $table: " . $e->getMessage();
    }
}

$configRows = $pdo->query("SELECT config_key FROM fee_configurations")->fetchAll(PDO::FETCH_COLUMN);
foreach ($requiredConfigKeys as $configKey) {
    if (!in_array($configKey, $configRows, true)) {
        $issues[] = "Missing config key: $configKey";
    }
}

$foreignKeys = $pdo->query(
    "SELECT TABLE_NAME, COLUMN_NAME, DELETE_RULE
     FROM information_schema.KEY_COLUMN_USAGE kcu
     JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
       ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
      AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
      AND rc.TABLE_NAME = kcu.TABLE_NAME
     WHERE kcu.CONSTRAINT_SCHEMA = DATABASE()
       AND kcu.REFERENCED_TABLE_NAME = 'accounts'"
)->fetchAll(PDO::FETCH_ASSOC);
$cascadeRules = [];
foreach ($foreignKeys as $foreignKey) {
    $cascadeRules[$foreignKey['TABLE_NAME'] . '.' . $foreignKey['COLUMN_NAME']] = $foreignKey['DELETE_RULE'];
}
foreach ($requiredCascadeRules as $rule) {
    if (($cascadeRules[$rule] ?? null) !== 'CASCADE') {
        $issues[] = "Account foreign key must cascade on delete: $rule";
    }
}

$baselineTable = $pdo->query("SHOW TABLES LIKE 'schema_migrations'")->fetchColumn();
if ($baselineTable === false) {
    $issues[] = 'Missing table: schema_migrations';
} else {
    $baseline = $pdo->query("SELECT version FROM schema_migrations WHERE version = '001'")->fetchColumn();
    if ($baseline !== '001') {
        $issues[] = 'Missing migration baseline marker: 001';
    }
}

if (!empty($issues)) {
    fwrite(STDERR, "Migration verification failed:\n" . implode("\n", $issues) . "\n");
    exit(1);
}

echo "Migration verification passed.\n";
echo "Tables checked: " . count($expectedTables) . "\n";
echo "Lifecycle + fee tables present and configured.\n";
