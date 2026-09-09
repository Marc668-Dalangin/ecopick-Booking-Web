<?php
/**
 * Environment-configured email delivery for booking notifications.
 *
 * The application uses PHP's configured mail transport. SMTP values are read
 * from .env so the transport can be replaced without changing application code.
 */
class MailerService
{
    public static function sendBookingStatus(string $recipientEmail, string $recipientName, int $pickupRequestId, string $status): bool
    {
        $recipientEmail = trim($recipientEmail);
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $config = self::config();
        if (!$config['enabled']) {
            return true;
        }

        $subject = $config['subject_prefix'] . ' Booking status updated';
        $safeName = trim($recipientName) !== '' ? trim($recipientName) : 'EcoPick member';
        $message = "Hello {$safeName},\n\nYour EcoPick booking #{$pickupRequestId} is now {$status}.\n\nPlease sign in to EcoPick to view the latest details.\n\nEcoPick Team";
        $headers = [
            'From: ' . $config['from_name'] . ' <' . $config['from_address'] . '>',
            'Reply-To: ' . $config['from_address'],
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        try {
            if ($config['smtp_host'] !== '') {
                ini_set('SMTP', $config['smtp_host']);
                ini_set('smtp_port', (string) $config['smtp_port']);
            }
            $sent = mail($recipientEmail, $subject, $message, implode("\r\n", $headers));
            if (!$sent) {
                error_log('Booking notification email was not accepted by the configured mail transport.');
            }
            return $sent;
        } catch (Throwable $exception) {
            error_log('Booking notification email error: ' . $exception->getMessage());
            return false;
        }
    }

    private static function config(): array
    {
        $env = self::loadEnv();
        return [
            'enabled' => filter_var($env['MAIL_ENABLED'] ?? getenv('MAIL_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
            'from_address' => self::headerValue($env['MAIL_FROM_ADDRESS'] ?? getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@localhost', 'no-reply@localhost'),
            'from_name' => self::headerValue($env['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: 'EcoPick', 'EcoPick'),
            'subject_prefix' => self::headerValue($env['MAIL_SUBJECT_PREFIX'] ?? getenv('MAIL_SUBJECT_PREFIX') ?: '[EcoPick]', '[EcoPick]'),
            'smtp_host' => $env['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: '',
            'smtp_port' => $env['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: '587',
            'smtp_username' => $env['SMTP_USERNAME'] ?? getenv('SMTP_USERNAME') ?: '',
            'smtp_password' => $env['SMTP_PASSWORD'] ?? getenv('SMTP_PASSWORD') ?: '',
            'smtp_encryption' => $env['SMTP_ENCRYPTION'] ?? getenv('SMTP_ENCRYPTION') ?: 'tls',
        ];
    }

    private static function loadEnv(): array
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }
            if ($key !== '') {
                $values[$key] = $value;
            }
        }
        return $values;
    }

    private static function headerValue(string $value, string $fallback): string
    {
        $value = trim(str_replace(["\r", "\n"], '', $value));
        return $value !== '' ? $value : $fallback;
    }
}
