<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (!Auth::check() || Auth::userRole() !== 'admin') {
    http_response_code(403);
    exit('Admin access required.');
}

$redirectUrl = APP_URL . '/admin-private-dnstl/backup_import.php';
$setFlash = static function (string $message, string $type) use ($redirectUrl): void {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $redirectUrl);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $setFlash('Only POST requests are accepted for database imports.', 'danger');
}

if (!CSRF::verify((string) ($_POST['_csrf_token'] ?? ''))) {
    $setFlash('Invalid security token. Please try again.', 'danger');
}

$upload = $_FILES['backup_file'] ?? null;
if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $setFlash('Please select a valid SQL backup file.', 'danger');
}

$originalName = (string) ($upload['name'] ?? '');
if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'sql') {
    $setFlash('Only .sql backup files can be imported.', 'danger');
}

$sqlContent = file_get_contents((string) ($upload['tmp_name'] ?? ''));
if ($sqlContent === false || trim($sqlContent) === '') {
    $setFlash('The selected SQL backup file is empty or unreadable.', 'danger');
}

$pdo = null;
$foreignKeysDisabled = false;
$importMessage = 'Database import failed. No complete restore was confirmed.';
$importType = 'danger';
try {
    $pdo = Database::getInstance()->getPDO();
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET time_zone = '+00:00'");
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
    $foreignKeysDisabled = true;
    $pdo->exec($sqlContent);

    $statusColumn = $pdo->query("SHOW COLUMNS FROM `pickup_requests` LIKE 'current_status'")->fetch(PDO::FETCH_ASSOC);
    $statusType = (string) ($statusColumn['Type'] ?? '');
    foreach (['Pending', 'Accepted', 'Declined', 'Completed', 'Cancelled'] as $requiredStatus) {
        if (stripos($statusType, "'" . $requiredStatus . "'") === false) {
            throw new RuntimeException('The imported backup does not preserve all pickup request status states.');
        }
    }

    $tableNames = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $tableCount = count($tableNames);
    $rowCount = 0;
    foreach ($tableNames as $tableName) {
        $quotedTable = '`' . str_replace('`', '``', (string) $tableName) . '`';
        $rowCount += (int) $pdo->query('SELECT COUNT(*) FROM ' . $quotedTable)->fetchColumn();
    }

    $importMessage = sprintf('Database import completed successfully: %d tables and %d rows restored.', $tableCount, $rowCount);
    $importType = 'success';
} catch (Throwable $exception) {
    error_log('Database import failed: ' . $exception->getMessage());
    $importMessage = 'Database import failed. No complete restore was confirmed: ' . $exception->getMessage();
} finally {
    if ($foreignKeysDisabled && $pdo instanceof PDO) {
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');
        } catch (Throwable $exception) {
            error_log('Unable to re-enable foreign key checks after import: ' . $exception->getMessage());
        }
    }
    $pdo = null;
}

$setFlash($importMessage, $importType);
