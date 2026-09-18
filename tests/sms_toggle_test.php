<?php
require_once __DIR__ . '/../includes/philsms_service.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE fee_settings (id INTEGER PRIMARY KEY, sms_enabled TINYINT(1) NOT NULL DEFAULT 1, philsms_api_token TEXT, philsms_endpoint TEXT, philsms_sender_id TEXT);');
$pdo->exec("INSERT INTO fee_settings (id, sms_enabled, philsms_api_token, philsms_endpoint, philsms_sender_id) VALUES (1, 0, 'demo-token', 'https://dashboard.philsms.com/api/v3/sms/send', 'PhilSMS');");

$result = sendPhilSMS('09123456789', 'Reminder test', $pdo);

if (($result['status'] ?? '') !== 'Disabled' || empty($result['bypassed'])) {
    fwrite(STDERR, "Expected disabled bypass result but got: " . json_encode($result) . PHP_EOL);
    exit(1);
}

echo "PASS: SMS disabled bypass captured.\n";
