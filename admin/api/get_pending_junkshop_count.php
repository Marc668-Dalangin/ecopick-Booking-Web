<?php
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (!Auth::check() || Auth::userRole() !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

$count = Database::getInstance()->query(
        "SELECT COUNT(*)
         FROM accounts a
         LEFT JOIN roles r ON r.id = a.role_id
         LEFT JOIN junkshop_profiles jp ON jp.account_id = a.id
         WHERE LOWER(COALESCE(NULLIF(a.account_role, ''), r.name, '')) = 'junkshop'
             AND (
                     LOWER(COALESCE(a.account_status, '')) = 'pending'
                     OR LOWER(COALESCE(jp.approval_status, '')) = 'pending'
             )"
)->fetchColumn();

echo json_encode(['count' => (int)$count]);
exit;