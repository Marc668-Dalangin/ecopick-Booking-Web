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

if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
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
$importMessage = 'Database import failed. The backup could not be applied.';
$importType = 'danger';
try {
    $pdo = Database::getInstance()->getPDO();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
    $foreignKeysDisabled = true;
    $pdo->exec($sqlContent);
    $importMessage = 'Database import completed successfully.';
    $importType = 'success';
} catch (Throwable $exception) {
    error_log('Database import failed: ' . $exception->getMessage());
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