<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

try {
    $results = NotificationService::sendRenewalReminders();
    fwrite(STDOUT, "Renewal reminders sent: {$results['sent']}; failed: {$results['failed']}" . PHP_EOL);
    foreach ($results['details'] as $detail) {
        fwrite(STDOUT, $detail . PHP_EOL);
    }
    exit(0);
} catch (Throwable $exception) {
    error_log('Renewal notification runner error: ' . $exception->getMessage());
    fwrite(STDERR, "Renewal reminder run failed." . PHP_EOL);
    exit(1);
}
