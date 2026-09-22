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
        $formattedDateSubmitted = self::formatNotificationDate($dateSubmitted);
        $safeDate = htmlspecialchars($formattedDateSubmitted, ENT_QUOTES, 'UTF-8');
        $safeReason = htmlspecialchars($rejectionReason !== '' ? $rejectionReason : 'Payment details could not be verified.', ENT_QUOTES, 'UTF-8');
        $renewalUrl = htmlspecialchars(APP_URL . '/user-junkshop/renewal.php', ENT_QUOTES, 'UTF-8');
        $plainReason = $rejectionReason !== '' ? $rejectionReason : 'Payment details could not be verified.';
        $plainBody = "Dear {$businessName},\n\nYour Partnership Renewal payment submission was rejected.\n\nRenewal Plan: {$planType}\nPayment Method: {$paymentMethod}\nSubmitted Reference No: " . ($referenceNumber !== '' ? $referenceNumber : 'N/A') . "\nDate Submitted: {$formattedDateSubmitted}\nReason for Rejection: {$plainReason}\n\nPlease verify your payment details and submit a new renewal request at {$renewalUrl}.\n\nEcoPick Platform Operations";
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

    public static function sendPaymentDecisionNotification(
        string $recipientEmail,
        string $businessName,
        string $paymentMethod,
        string $referenceNumber,
        float $amountSubmitted,
        float $amountDeducted,
        string $dateSubmitted,
        string $action,
        string $reason = ''
    ): array {
        $recipientEmail = strtolower(trim($recipientEmail));
        $isApproved = $action === 'Approved';
        if (!$isApproved && $action !== 'Rejected') {
            return ['sent' => false, 'error' => 'Invalid payment decision.'];
        }

        $subject = '[EcoPick] Partnership Fee Payment ' . $action;
        $safeName = htmlspecialchars($businessName !== '' ? $businessName : 'Junkshop partner', ENT_QUOTES, 'UTF-8');
        $safeMethod = htmlspecialchars($paymentMethod, ENT_QUOTES, 'UTF-8');
        $safeReference = htmlspecialchars($referenceNumber !== '' ? $referenceNumber : 'N/A', ENT_QUOTES, 'UTF-8');
        $formattedDateSubmitted = self::formatNotificationDate($dateSubmitted);
        $safeDate = htmlspecialchars($formattedDateSubmitted, ENT_QUOTES, 'UTF-8');
        $safeReason = htmlspecialchars($reason !== '' ? $reason : 'Please review your payment details and contact EcoPick support if you need assistance.', ENT_QUOTES, 'UTF-8');
        $statusColor = $isApproved ? '#198754' : '#dc3545';
        $title = $isApproved ? 'Payment Approved' : 'Payment Submission Rejected';
        $message = $isApproved
            ? 'Your fee payment has been verified and approved by the administrator. Your outstanding fee balance has been updated automatically.'
            : 'Your fee payment submission was rejected after administrative verification.';
        $deductionRow = $isApproved
            ? '<tr><td style="padding:10px;border-bottom:1px solid #e9ecef"><strong>Deducted Balance Amount:</strong></td><td style="padding:10px;border-bottom:1px solid #e9ecef;color:#198754;font-weight:bold">₱' . number_format($amountDeducted, 2) . '</td></tr>'
            : '';
        $reasonRow = !$isApproved
            ? '<p style="background:#fff3cd;color:#856404;padding:12px;border-left:4px solid #ffebaA"><strong>Reason / Action Required:</strong> ' . $safeReason . '</p>'
            : '';
        $body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden">'
            . '<div style="background:' . $statusColor . ';color:#fff;padding:20px;text-align:center"><h2 style="margin:0">' . $title . '</h2></div>'
            . '<div style="padding:24px;color:#333;line-height:1.6"><p>Hello <strong>' . $safeName . '</strong>,</p><p>' . $message . '</p>'
            . '<table style="width:100%;border-collapse:collapse;margin:20px 0;background:#f8f9fa">'
            . '<tr><td style="padding:10px;border-bottom:1px solid #e9ecef"><strong>Payment Method:</strong></td><td style="padding:10px;border-bottom:1px solid #e9ecef">' . $safeMethod . '</td></tr>'
            . '<tr><td style="padding:10px;border-bottom:1px solid #e9ecef"><strong>Reference Number:</strong></td><td style="padding:10px;border-bottom:1px solid #e9ecef">' . $safeReference . '</td></tr>'
            . '<tr><td style="padding:10px;border-bottom:1px solid #e9ecef"><strong>Submitted Amount:</strong></td><td style="padding:10px;border-bottom:1px solid #e9ecef">₱' . number_format($amountSubmitted, 2) . '</td></tr>'
            . $deductionRow
            . '<tr><td style="padding:10px"><strong>Date Submitted:</strong></td><td style="padding:10px">' . $safeDate . '</td></tr></table>'
            . $reasonRow . '<p style="margin-top:30px">Regards,<br><strong>EcoPick Operations Team</strong></p></div></div>';
        $plainBody = "Hello {$businessName},\n\n{$message}\n\nPayment Method: {$paymentMethod}\nReference Number: " . ($referenceNumber !== '' ? $referenceNumber : 'N/A') . "\nSubmitted Amount: ₱" . number_format($amountSubmitted, 2) . ($isApproved ? "\nDeducted Balance Amount: ₱" . number_format($amountDeducted, 2) : "\nReason / Action Required: {$reason}") . "\nDate Submitted: {$formattedDateSubmitted}\n\nEcoPick Operations Team";

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
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
            $error = (isset($mailer) && $mailer->ErrorInfo) ? $mailer->ErrorInfo : $exception->getMessage();
            self::logEmail($recipientEmail, $subject, $plainBody, 'Failed', $error);
            error_log('Payment decision email error: ' . $error);
            return ['sent' => false, 'error' => $error];
        }
    }

    public static function sendRenewalApproval(
        string $recipientEmail,
        string $businessName,
        string $planType,
        string $paymentMethod,
        string $referenceNumber,
        string $dateSubmitted,
        float $amount
    ): array {
        try {
            $safeName = htmlspecialchars($businessName !== '' ? $businessName : 'Junkshop partner', ENT_QUOTES, 'UTF-8');
            $safePlan = htmlspecialchars($planType, ENT_QUOTES, 'UTF-8');
            $safeMethod = htmlspecialchars($paymentMethod, ENT_QUOTES, 'UTF-8');
            $safeReference = htmlspecialchars($referenceNumber !== '' ? $referenceNumber : 'N/A', ENT_QUOTES, 'UTF-8');
            $formattedDateSubmitted = self::formatNotificationDate($dateSubmitted);
            $safeDate = htmlspecialchars($formattedDateSubmitted, ENT_QUOTES, 'UTF-8');
            $formattedAmount = number_format($amount, 2);
            $subject = '[EcoPick] Partnership Renewal Payment Approved';
            $plainBody = "Dear {$businessName},\n\nYour Partnership Renewal payment has been approved.\n\nRenewal Plan: {$planType}\nPayment Method: {$paymentMethod}\nReference No: " . ($referenceNumber !== '' ? $referenceNumber : 'N/A') . "\nAmount: PHP {$formattedAmount}\nDate Submitted: {$formattedDateSubmitted}\n\nEcoPick Platform Operations";
            $body = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f4f6f8;color:#333;padding:20px"><div style="max-width:600px;background:#fff;padding:30px;border:1px solid #e0e0e0;margin:0 auto"><h2 style="color:#198754">Partnership Renewal Payment Approved</h2><p>Dear <strong>' . $safeName . '</strong>,</p><p>Your Partnership Renewal payment has been verified and <strong>APPROVED</strong> by the administrator.</p><p><strong>Renewal Plan:</strong> ' . $safePlan . '<br><strong>Payment Method:</strong> ' . $safeMethod . '<br><strong>Reference No:</strong> ' . $safeReference . '<br><strong>Amount:</strong> PHP ' . $formattedAmount . '<br><strong>Date Submitted:</strong> ' . $safeDate . '</p><p>Your partnership status has been updated automatically.</p><p>EcoPick Platform Operations</p></div></body></html>';
            return self::sendHtml($recipientEmail, $businessName, $subject, $body, $plainBody);
        } catch (Throwable $exception) {
            error_log('Renewal approval email error: ' . $exception->getMessage());
            return ['sent' => false, 'error' => $exception->getMessage()];
        }
    }

    public static function sendJunkshopPaymentNotification(int $paymentId, string $action, float $amountDeducted = 0.00, string $rejectionReason = ''): array
    {
        try {
            $data = Database::getInstance()->query(
                'SELECT fp.payment_method, fp.reference_number, fp.amount_submitted,
                    fp.rejection_reason AS db_rejection_reason, fp.created_at,
                        COALESCE(jp.business_name, a.full_name) AS business_name, a.email
                 FROM junkshop_fee_payments fp
                 JOIN accounts a ON a.id = fp.junkshop_id
                 LEFT JOIN junkshop_profiles jp ON jp.account_id = a.id
                 WHERE fp.id = :id LIMIT 1',
                ['id' => $paymentId]
            )->fetch();
            if (!$data || !filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                return ['sent' => false, 'error' => 'Invalid or missing junkshop email address.'];
            }
            $approved = $action === 'Approved';
            $safeName = htmlspecialchars((string) $data['business_name'], ENT_QUOTES, 'UTF-8');
            $safeMethod = htmlspecialchars((string) $data['payment_method'], ENT_QUOTES, 'UTF-8');
            $safeReference = htmlspecialchars($data['reference_number'] ?: 'N/A', ENT_QUOTES, 'UTF-8');
            $submitted = number_format((float) $data['amount_submitted'], 2);
            $deducted = number_format($amountDeducted, 2);
            $date = htmlspecialchars(self::formatNotificationDate((string) $data['created_at']), ENT_QUOTES, 'UTF-8');
            $subject = '[EcoPick] Partnership Fee Payment ' . ($approved ? 'Approved' : 'Rejected');
            $heading = $approved ? 'Payment Approved' : 'Payment Submission Rejected';
            $color = $approved ? '#198754' : '#dc3545';
            $rawReason = $rejectionReason !== '' ? $rejectionReason : (string) ($data['db_rejection_reason'] ?? '');
            $safeReason = htmlspecialchars($rawReason !== '' ? $rawReason : 'Payment details could not be verified.', ENT_QUOTES, 'UTF-8');
            $detail = $approved ? '<br><strong>Deducted Balance Amount:</strong> PHP ' . $deducted : '<br><strong>Rejection Reason:</strong> ' . $safeReason;
            $body = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f4f6f8;color:#333;padding:20px"><div style="max-width:600px;background:#fff;padding:30px;border:1px solid #e0e0e0;margin:0 auto"><h2 style="color:' . $color . '">' . $heading . '</h2><p>Hello <strong>' . $safeName . '</strong>,</p><p>Your fee payment submission was <strong>' . strtoupper($action) . '</strong> after administrative verification.</p><p><strong>Payment Method:</strong> ' . $safeMethod . '<br><strong>Reference Number:</strong> ' . $safeReference . '<br><strong>Submitted Amount:</strong> PHP ' . $submitted . $detail . '<br><strong>Date Submitted:</strong> ' . $date . '</p><p>EcoPick Operations Team</p></div></body></html>';
            $plainBody = "Hello {$data['business_name']},\n\nYour fee payment submission was {$action}.\nPayment Method: {$data['payment_method']}\nSubmitted Amount: PHP {$submitted}\n" . ($approved ? "Deducted Amount: PHP {$deducted}\n" : "Rejection Reason: {$rawReason}\n") . "\nEcoPick Operations Team";
            return self::sendHtml((string) $data['email'], (string) $data['business_name'], $subject, $body, $plainBody);
        } catch (Throwable $exception) {
            error_log('Fee payment notification email error: ' . $exception->getMessage());
            return ['sent' => false, 'error' => $exception->getMessage()];
        }
    }

    private static function sendHtml(string $recipientEmail, string $recipientName, string $subject, string $body, string $plainBody): array
    {
        try {
            $recipientEmail = strtolower(trim($recipientEmail));
            if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) return ['sent' => false, 'error' => 'Invalid recipient email address.'];
            $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
            $config = self::config();
            if (!is_file($autoload) || !$config['enabled'] || $config['smtp_host'] === '' || $config['smtp_username'] === '' || $config['smtp_password'] === '') {
                $error = !is_file($autoload) ? 'Composer autoloader not found.' : 'SMTP is not enabled or is missing configuration.';
                error_log('Payment notification email error: ' . $error);
                return ['sent' => false, 'error' => $error];
            }
            require_once $autoload;
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $config['smtp_host'];
            $mailer->SMTPAuth = true;
            $mailer->Username = $config['smtp_username'];
            $mailer->Password = $config['smtp_password'];
            $mailer->SMTPSecure = strtolower($config['smtp_encryption']) === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Port = (int) $config['smtp_port'];
            $mailer->setFrom($config['smtp_username'], $config['from_name']);
            $mailer->addAddress($recipientEmail, $recipientName !== '' ? $recipientName : 'Junkshop partner');
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $body;
            $mailer->AltBody = $plainBody;
            $sent = $mailer->send();
            self::logEmail($recipientEmail, $subject, $plainBody, $sent ? 'Sent' : 'Failed', $sent ? null : 'SMTP server did not accept the message.');
            return ['sent' => $sent, 'error' => $sent ? null : 'SMTP server did not accept the message.'];
        } catch (Throwable $exception) {
            error_log('Payment notification email error: ' . $exception->getMessage());
            return ['sent' => false, 'error' => $exception->getMessage()];
        }
    }

    private static function formatNotificationDate(string $dateTime): string
    {
        $timestamp = strtotime($dateTime);
        return $timestamp === false ? $dateTime : date('M d, Y h:i A', $timestamp);
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
