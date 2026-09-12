<?php
require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (!Auth::check() || Auth::userRole() !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST is required.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : $_POST;
$csrfToken = $payload['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (!is_string($csrfToken) || !CSRF::verify($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$accountId = (int)($payload['id'] ?? $payload['account_id'] ?? 0);
$action = (string)($payload['action'] ?? '');

if ($accountId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid account and action are required.']);
    exit;
}

$database = Database::getInstance();

try {
    $database->beginTransaction();

    $account = $database->query(
        "SELECT a.id, a.email, a.account_role, a.account_status, r.name AS role_name,
                jp.approval_status
         FROM accounts a
         LEFT JOIN roles r ON r.id = a.role_id
         LEFT JOIN junkshop_profiles jp ON jp.account_id = a.id
         WHERE a.id = :id
         FOR UPDATE",
        ['id' => $accountId]
    )->fetch();

    $isJunkshop = $account
        && strtolower((string)($account['account_role'] ?: $account['role_name'])) === 'junkshop';
    $isPending = $account
        && (strtolower((string)$account['account_status']) === 'pending'
            || strtolower((string)$account['approval_status']) === 'pending');

    if (!$isJunkshop || !$isPending) {
        $database->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pending junkshop application not found.']);
        exit;
    }

    if ($action === 'approve') {
        $accountStatement = $database->query(
            "UPDATE accounts
             SET account_status = 'active', updated_at = CURRENT_TIMESTAMP
             WHERE id = :id",
            ['id' => $accountId]
        );

        $database->query(
            "UPDATE junkshop_profiles
             SET approval_status = 'approved', updated_at = CURRENT_TIMESTAMP
             WHERE account_id = :id",
            ['id' => $accountId]
        );
        $message = 'Junkshop application approved.';
    } else {
        $childTables = [
            'notifications' => 'recipient_account_id',
            'concerns' => 'reporter_account_id',
            'junkshop_partnership_payments' => 'junkshop_account_id',
            'junkshop_material_prices' => 'junkshop_account_id',
            'preferred_junkshops' => 'junkshop_id',
            'payment_proofs' => 'seller_account_id',
            'pickup_requests' => 'seller_account_id',
            'transactions' => 'junkshop_id',
            'junkshop_profiles' => 'account_id',
            'password_resets' => null,
        ];

        foreach ($childTables as $table => $column) {
            if ($column === null) {
                continue;
            }
            $database->query("DELETE FROM {$table} WHERE {$column} = :id", ['id' => $accountId]);
        }

        $database->query(
            'INSERT INTO rejected_emails (email) VALUES (:email)',
            ['email' => $account['email']]
        );

        $deleteStatement = $database->query(
            "DELETE FROM accounts
             WHERE id = :id",
            ['id' => $accountId]
        );

        if ($deleteStatement->rowCount() !== 1) {
            $database->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Pending junkshop application not found.']);
            exit;
        }
        $message = 'Junkshop application rejected and account permanently deleted.';
    }

    $database->commit();
    echo json_encode(['success' => true, 'message' => $message, 'id' => $accountId, 'action' => $action]);
} catch (Throwable $exception) {
    $database->rollBack();
    error_log('Junkshop approval processing error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The application could not be processed.']);
}
exit;