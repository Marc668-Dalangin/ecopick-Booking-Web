<?php

if (!Auth::check() || Auth::userRole() === 'admin') {
    return;
}

$maintenanceScript = basename(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')));
if (in_array($maintenanceScript, ['login.php', 'logout.php', 'forgot-password.php', 'reset-password.php', 'verify_otp.php'], true)) {
    return;
}

$maintenanceSettings = [];
try {
    $maintenanceSettings = Database::getInstance()->query(
        "SELECT setting_key, setting_value
         FROM system_settings
         WHERE setting_key IN ('maintenance_mode', 'maintenance_scheduled_date', 'maintenance_scheduled_time', 'maintenance_message')"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $exception) {
    error_log('Maintenance mode settings error: ' . $exception->getMessage());
    return;
}

$maintenanceMode = strtolower((string) ($maintenanceSettings['maintenance_mode'] ?? 'off'));
$maintenanceActive = $maintenanceMode === 'on';
if ($maintenanceMode === 'scheduled') {
    $scheduledAt = trim((string) ($maintenanceSettings['maintenance_scheduled_date'] ?? ''))
        . ' ' . trim((string) ($maintenanceSettings['maintenance_scheduled_time'] ?? ''));
    $scheduledDate = DateTimeImmutable::createFromFormat('Y-m-d H:i', $scheduledAt, new DateTimeZone(APP_TIMEZONE));
    $maintenanceActive = $scheduledDate instanceof DateTimeImmutable
        && $scheduledDate->format('Y-m-d H:i') === $scheduledAt
        && $scheduledDate <= new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE));
}
if (!$maintenanceActive || !in_array(Auth::userRole(), ['seller', 'junkshop'], true)) {
    return;
}

$message = trim((string) ($maintenanceSettings['maintenance_message'] ?? ''));
$message = $message !== '' ? $message : 'The system is currently undergoing scheduled maintenance. Please check back soon.';
http_response_code(503);
header('Retry-After: 3600');
echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Scheduled Maintenance - EcoPick</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container min-vh-100 d-flex align-items-center justify-content-center py-5"><div class="card border-0 shadow-sm" style="max-width: 600px"><div class="card-body p-5 text-center"><div class="display-5 text-warning mb-3"><i class="bi bi-tools"></i></div><h1 class="h3 mb-3">Scheduled maintenance</h1><p class="text-muted mb-0">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></div></div></main></body></html>';
exit;