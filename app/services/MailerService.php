<?php
require_once dirname(__DIR__, 2) . '/config/mail.php';

/**
 * Environment-configured email delivery for booking notifications.
 *
 * The application uses PHP's configured mail transport. SMTP values are read
 * from .env so the transport can be replaced without changing application code.
 */
class MailerService
{
    public static function sendRegistrationOtp(string $recipientEmail, string $recipientName, string $otp): bool
    {
        $recipientEmail = strtolower(trim($recipientEmail));
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $otp)) {
            return false;
        }

        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            error_log('Registration OTP email error: Composer autoloader not found.');
            return false;
        }

        require_once $autoload;

        try {
            $config = self::config();
            if ($config['smtp_host'] === '' || $config['smtp_username'] === '' || $config['smtp_password'] === '') {
                error_log('Registration OTP email error: SMTP configuration is incomplete.');
                return false;
            }

            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $config['smtp_host'];
            $mailer->SMTPAuth = true;
            $mailer->Username = $config['smtp_username'];
            $mailer->Password = $config['smtp_password'];
            $mailer->SMTPSecure = strtolower($config['smtp_encryption']) === 'ssl'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Port = (int) $config['smtp_port'];
            if (self::isLocalEnvironment()) {
                $mailer->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }
            $mailer->setFrom($config['smtp_username'], $config['from_name']);
            $mailer->addAddress($recipientEmail, trim($recipientName) !== '' ? trim($recipientName) : 'EcoPick member');
            $mailer->isHTML(true);
            $mailer->Subject = 'EcoPick Email Verification Code';
            $mailer->Body = '<p>Hello ' . htmlspecialchars($recipientName ?: 'EcoPick member', ENT_QUOTES, 'UTF-8') . ',</p>'
                . '<p>Your EcoPick verification code is:</p>'
                . '<p style="font-size:28px;font-weight:700;letter-spacing:6px">' . $otp . '</p>'
                . '<p>This code expires in 10 minutes. If you did not create an account, you can ignore this email.</p>';
            $mailer->AltBody = "Your EcoPick verification code is {$otp}. This code expires in 10 minutes.";
            return $mailer->send();
        } catch (Throwable $exception) {
            error_log('Registration OTP email error: ' . $exception->getMessage());
            return false;
        }
    }

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

    public static function sendRenewalNotice(string $recipientEmail, string $recipientName, string $expirationDate): array
    {
        $recipientEmail = strtolower(trim($recipientEmail));
        $subject = '[EcoPick] Partnership renewal reminder';
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            self::logEmail($recipientEmail ?: 'invalid-recipient', $subject, 'Renewal notice was not sent.', 'Failed', 'Invalid recipient email address.');
            return ['sent' => false, 'error' => 'Invalid recipient email address.'];
        }

        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            self::logEmail($recipientEmail, $subject, 'Renewal notice was not sent.', 'Failed', 'Composer autoloader not found.');
            error_log('Renewal notice email error: Composer autoloader not found.');
            return ['sent' => false, 'error' => 'Composer autoloader not found.'];
        }
        require_once $autoload;

        $config = self::config();
        $subject = $config['subject_prefix'] . ' Partnership renewal reminder';
        $safeName = htmlspecialchars(trim($recipientName) !== '' ? trim($recipientName) : 'Junkshop partner', ENT_QUOTES, 'UTF-8');
        $safeExpiry = htmlspecialchars($expirationDate, ENT_QUOTES, 'UTF-8');
        $renewalUrl = htmlspecialchars(APP_URL . '/user-junkshop/renewal.php', ENT_QUOTES, 'UTF-8');
        $body = '<p>Hello ' . $safeName . ',</p>'
                . '<p>Your EcoPick junkshop subscription will expire on <strong>' . $safeExpiry . '</strong>.</p>'
                . '<p>Please log in to your EcoPick account and open the Partnership Renewal page to submit your renewal payment before this date:</p>'
                . '<p><a href="' . $renewalUrl . '">' . $renewalUrl . '</a></p>'
                . '<p>EcoPick Team</p>';
        $plainBody = "Hello {$recipientName},\n\nYour EcoPick junkshop subscription will expire on {$expirationDate}. Please log in to your EcoPick account and open {$renewalUrl} to submit your renewal payment before this date.\n\nEcoPick Team";

        if (!$config['enabled'] || $config['smtp_host'] === '' || $config['smtp_username'] === '' || $config['smtp_password'] === '') {
            $message = 'SMTP is not enabled or is missing host, username, or app password.';
            self::logEmail($recipientEmail, $subject, $plainBody, 'Failed', $message);
            error_log('Renewal notice email error: ' . $message);
            return ['sent' => false, 'error' => $message];
        }

        try {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $config['smtp_host'];
            $mailer->SMTPAuth = true;
            $mailer->Username = $config['smtp_username'];
            $mailer->Password = $config['smtp_password'];
            $mailer->SMTPSecure = strtolower($config['smtp_encryption']) === 'ssl'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Port = (int) $config['smtp_port'];
            if (self::isLocalEnvironment()) {
                $mailer->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }
            $mailer->setFrom($config['smtp_username'], $config['from_name']);
            $mailer->addAddress($recipientEmail, trim($recipientName) !== '' ? trim($recipientName) : 'Junkshop partner');
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->AltBody = $plainBody;
            $sent = $mailer->send();
            self::logEmail($recipientEmail, $subject, $plainBody, $sent ? 'Sent' : 'Failed', $sent ? null : 'SMTP server did not accept the message.');
            return ['sent' => $sent, 'error' => $sent ? null : 'SMTP server did not accept the message.'];
        } catch (Throwable $exception) {
            $errorMessage = (isset($mailer) && $mailer->ErrorInfo)
                ? $mailer->ErrorInfo
                : $exception->getMessage();
            error_log('Renewal notice email error: ' . $errorMessage);
            self::logEmail($recipientEmail, $subject, $plainBody, 'Failed', $errorMessage);
            return ['sent' => false, 'error' => $errorMessage];
        }
    }

    public static function sendRenewalRejection(
        string $recipientEmail,
        string $businessName,
        string $planType,
        string $paymentMethod,
        string $referenceNumber,
        string $dateSubmitted,
        string $rejectionReason
    ): array {
        $recipientEmail = strtolower(trim($recipientEmail));
        $subject = '[EcoPick] Partnership Renewal Payment Issue';
        $safeName = htmlspecialchars(trim($businessName) !== '' ? $businessName : 'Junkshop partner', ENT_QUOTES, 'UTF-8');
        $safePlan = htmlspecialchars($planType, ENT_QUOTES, 'UTF-8');
        $safeMethod = htmlspecialchars($paymentMethod, ENT_QUOTES, 'UTF-8');
        $safeReference = htmlspecialchars($referenceNumber !== '' ? $referenceNumber : 'N/A', ENT_QUOTES, 'UTF-8');
        $safeDate = htmlspecialchars($dateSubmitted, ENT_QUOTES, 'UTF-8');
        $safeReason = htmlspecialchars($rejectionReason, ENT_QUOTES, 'UTF-8');
        $renewalUrl = htmlspecialchars(APP_URL . '/user-junkshop/renewal.php', ENT_QUOTES, 'UTF-8');
        $plainBody = "Dear {$businessName},\n\nYour Partnership Renewal payment submission was rejected.\n\nRenewal Plan: {$planType}\nPayment Method: {$paymentMethod}\nSubmitted Reference No: " . ($referenceNumber !== '' ? $referenceNumber : 'N/A') . "\nDate Submitted: {$dateSubmitted}\nReason for Rejection: {$rejectionReason}\n\nPlease verify your payment details and submit a new renewal request at {$renewalUrl}.\n\nEcoPick Platform Operations";
        $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;background:#f4f6f8;color:#333;margin:0;padding:20px}.container{max-width:600px;background:#fff;padding:30px;border-radius:8px;border:1px solid #e0e0e0;margin:0 auto}.header{border-bottom:2px solid #dc3545;padding-bottom:15px;margin-bottom:20px}.header h2{color:#dc3545;margin:0}.details-box{background:#f8f9fa;padding:15px;border-left:4px solid #dc3545;margin:20px 0}.btn{display:inline-block;padding:12px 24px;background:#198754;color:#fff!important;text-decoration:none;border-radius:5px;font-weight:bold;margin-top:15px}.footer{margin-top:30px;font-size:12px;color:#6c757d;border-top:1px solid #eee;padding-top:15px}</style></head><body><div class="container"><div class="header"><h2>Partnership Renewal Payment Issue</h2></div><p>Dear <strong>' . $safeName . '</strong>,</p><p>We reviewed your recent Partnership Renewal payment submission and were unable to verify your payment details.</p><div class="details-box"><p><strong>Renewal Plan:</strong> ' . $safePlan . '</p><p><strong>Payment Method:</strong> ' . $safeMethod . '</p><p><strong>Submitted Reference No:</strong> ' . $safeReference . '</p><p><strong>Date Submitted:</strong> ' . $safeDate . '</p><p style="color:#dc3545"><strong>Reason for Rejection:</strong> ' . $safeReason . '</p></div><p>Your submission lock has been removed. Please verify your payment receipt details and submit a new renewal request.</p><a href="' . $renewalUrl . '" class="btn">Return to Renewal Page</a><div class="footer"><p>This is an automated notification from EcoPick Platform Operations. Please do not reply directly to this email.</p></div></div></body></html>';

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            self::logEmail($recipientEmail ?: 'invalid-recipient', $subject, $plainBody, 'Failed', 'Invalid recipient email address.');
            return ['sent' => false, 'error' => 'Invalid recipient email address.'];
        }
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $config = self::config();
        if (!is_file($autoload) || !$config['enabled'] || $config['smtp_host'] === '' || $config['smtp_username'] === '' || $config['smtp_password'] === '') {
            $error = !is_file($autoload) ? 'Composer autoloader not found.' : 'SMTP is not enabled or is missing configuration.';
            self::logEmail($recipientEmail, $subject, $plainBody, 'Failed', $error);
            return ['sent' => false, 'error' => $error];
        }
        require_once $autoload;
        try {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $config['smtp_host'];
            $mailer->SMTPAuth = true;
            $mailer->Username = $config['smtp_username'];
            $mailer->Password = $config['smtp_password'];
            $mailer->SMTPSecure = strtolower($config['smtp_encryption']) === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Port = (int) $config['smtp_port'];
            $mailer->setFrom($config['smtp_username'], $config['from_name']);
            $mailer->addAddress($recipientEmail, $businessName !== '' ? $businessName : 'Junkshop partner');
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->AltBody = $plainBody;
            $sent = $mailer->send();
            self::logEmail($recipientEmail, $subject, $plainBody, $sent ? 'Sent' : 'Failed', $sent ? null : 'SMTP server did not accept the message.');
            return ['sent' => $sent, 'error' => $sent ? null : 'SMTP server did not accept the message.'];
        } catch (Throwable $exception) {
            self::logEmail($recipientEmail, $subject, $plainBody, 'Failed', $exception->getMessage());
            error_log('Renewal rejection email error: ' . $exception->getMessage());
            return ['sent' => false, 'error' => $exception->getMessage()];
        }
    }

    private static function logEmail(string $recipientEmail, string $subject, string $body, string $status, ?string $errorMessage = null): void
    {
        try {
            Database::getInstance()->query(
                'INSERT INTO email_logs (recipient_email, subject, body, status, error_message)
                 VALUES (:recipient_email, :subject, :body, :status, :error_message)',
                [
                    'recipient_email' => $recipientEmail,
                    'subject' => $subject,
                    'body' => $body,
                    'status' => $status,
                    'error_message' => $errorMessage,
                ]
            );
        } catch (Throwable $exception) {
            error_log('Email audit log error: ' . $exception->getMessage());
        }
    }

    private static function config(): array
    {
        $env = self::loadEnv();
        $databaseSettings = self::databaseSettings();
        return [
            'enabled' => filter_var($env['MAIL_ENABLED'] ?? getenv('MAIL_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            'from_address' => self::headerValue($env['MAIL_FROM_ADDRESS'] ?? getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@localhost', 'no-reply@localhost'),
            'from_name' => self::headerValue($env['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME') ?: 'EcoPick', 'EcoPick'),
            'subject_prefix' => self::headerValue($env['MAIL_SUBJECT_PREFIX'] ?? getenv('MAIL_SUBJECT_PREFIX') ?: '[EcoPick]', '[EcoPick]'),
            'smtp_host' => self::configuredValue($env, 'SMTP_HOST', $databaseSettings['smtp_host'] ?? null, SMTP_HOST),
            'smtp_port' => self::configuredValue($env, 'SMTP_PORT', $databaseSettings['smtp_port'] ?? null, (string) SMTP_PORT),
            'smtp_username' => self::configuredValue($env, 'SMTP_USERNAME', $databaseSettings['smtp_user'] ?? null, SMTP_USER),
            'smtp_password' => self::configuredValue($env, 'SMTP_PASSWORD', $databaseSettings['smtp_pass'] ?? null, SMTP_APP_PASSWORD),
            'smtp_encryption' => self::configuredValue($env, 'SMTP_ENCRYPTION', $databaseSettings['smtp_encryption'] ?? null, SMTP_ENCRYPTION),
        ];
    }

    private static function configuredValue(array $env, string $environmentKey, mixed $databaseValue, string $fallback): string
    {
        $environmentValue = trim((string) ($env[$environmentKey] ?? getenv($environmentKey) ?: ''));
        if ($environmentValue !== '') {
            return $environmentValue;
        }

        $databaseValue = trim((string) ($databaseValue ?? ''));
        return $databaseValue !== '' ? $databaseValue : $fallback;
    }

    private static function databaseSettings(): array
    {
        try {
            $settings = Database::getInstance()->query(
                'SELECT smtp_host, smtp_port, smtp_user, smtp_pass, smtp_encryption
                 FROM fee_settings
                 WHERE id = 1
                 LIMIT 1'
            )->fetch();
            return is_array($settings) ? $settings : [];
        } catch (Throwable $exception) {
            error_log('SMTP settings lookup error: ' . $exception->getMessage());
            return [];
        }
    }

    private static function isLocalEnvironment(): bool
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? parse_url(APP_URL, PHP_URL_HOST) ?? ''));
        $host = preg_replace('/:\d+$/', '', $host) ?: $host;
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
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
