<?php
/** Admin renewals, support, and reporting workflows. */
class AdminFeatureController
{
    private Database $db;
    private array $columnAvailability = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function requireAdmin(): void
    {
        if (!Auth::check() || Auth::userRole() !== 'admin') {
            http_response_code(403);
            throw new RuntimeException('Admin access required.');
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (!array_key_exists($key, $this->columnAvailability)) {
            $this->columnAvailability[$key] = (bool) $this->db->query(
                'SELECT 1
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name
                 LIMIT 1',
                ['table_name' => $table, 'column_name' => $column]
            )->fetchColumn();
        }
        return $this->columnAvailability[$key];
    }

    public function listPartnershipPayments(): array
    {
        $this->requireAdmin();
        return $this->db->query(
            "SELECT pr.id, pr.junkshop_account_id, COALESCE(jp.business_name, 'Unknown') AS business_name,
                    pr.plan_type, pr.payment_method, pr.amount, pr.reference_number, pr.receipt_image, pr.status, pr.created_at
             FROM partnership_renewals pr
             LEFT JOIN junkshop_profiles jp ON jp.account_id = pr.junkshop_account_id
             ORDER BY pr.created_at DESC"
        )->fetchAll();
    }

    public function getRenewalNoticeDays(): int
    {
        $value = $this->db->query(
            "SELECT expiration_notice_lead_days
             FROM fee_settings
             WHERE id = 1
             LIMIT 1"
        )->fetchColumn();
        $days = (int) $value;
        return $days >= 1 && $days <= 30 ? $days : 1;
    }

    public function updateRenewalNoticeDays(int $days): array
    {
        $this->requireAdmin();
        if ($days < 1 || $days > 30) {
            return ['success' => false, 'message' => 'Enter a notice lead time between 1 and 30 days.'];
        }

        try {
            $this->db->query(
                "INSERT INTO fee_settings (id, expiration_notice_lead_days)
                 VALUES (1, :days)
                 ON DUPLICATE KEY UPDATE expiration_notice_lead_days = VALUES(expiration_notice_lead_days), updated_at = CURRENT_TIMESTAMP",
                ['days' => $days]
            );
            $this->db->query('UPDATE junkshop_profiles SET last_expiration_notice_sent = NULL');
            $this->db->query('DELETE FROM renewal_notification_log');
            return ['success' => true, 'message' => 'Renewal notification lead time updated.'];
        } catch (Throwable $exception) {
            error_log('Renewal notice setting update error: ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Unable to update the renewal notification lead time.'];
        }
    }

    public function listApprovedJunkshops(): array
    {
        $this->requireAdmin();
        $partnershipExpiry = $this->hasColumn('junkshop_profiles', 'partnership_expires_at')
            ? 'jp.partnership_expires_at'
            : 'NULL';
        $renewalStatus = $this->hasColumn('junkshop_profiles', 'renewal_status')
            ? 'jp.renewal_status'
            : "'Current'";
        return $this->db->query(
            "SELECT a.id AS account_id, a.account_status, COALESCE(jp.business_name, 'Unknown') AS business_name,
                    {$partnershipExpiry} AS partnership_expires_at, {$renewalStatus} AS renewal_status,
                    CASE WHEN {$partnershipExpiry} IS NOT NULL
                            AND {$partnershipExpiry} <= CURRENT_TIMESTAMP
                         THEN 'Expired' ELSE COALESCE(a.account_status, 'inactive') END AS display_status
             FROM accounts a
             JOIN junkshop_profiles jp ON jp.account_id = a.id
             WHERE jp.approval_status = 'approved'
             ORDER BY jp.business_name ASC"
        )->fetchAll();
    }

    public function listCommissionPayments(): array
    {
        $this->requireAdmin();
        return $this->db->query(
            'SELECT tp.id, tp.transaction_id, tp.amount, tp.payment_method, tp.payment_status, tp.payment_reference, tp.paid_at, tp.confirmed_at, pr.booking_reference, jp.business_name FROM transaction_payments tp JOIN transactions t ON t.id = tp.transaction_id JOIN pickup_requests pr ON pr.id = t.pickup_request_id JOIN junkshop_profiles jp ON jp.account_id = t.junkshop_id WHERE tp.payment_purpose = \'EcoPick Commission\' ORDER BY tp.payment_status = \'Confirmed\', tp.created_at DESC'
        )->fetchAll();
    }

    public function reconcileCommissionPayment(int $paymentId, string $status, string $reference = ''): array
    {
        $this->requireAdmin();
        if (!in_array($status, ['Paid', 'Confirmed'], true)) return ['success' => false, 'message' => 'Invalid commission status.'];
        if ($paymentId < 1) return ['success' => false, 'message' => 'Commission payment not found.'];
        $reference = trim($reference) !== '' ? trim($reference) : null;
        try {
            $this->db->beginTransaction();
            $payment = $this->db->query(
                'SELECT id, payment_status FROM transaction_payments WHERE id = :id AND payment_purpose = \'EcoPick Commission\' FOR UPDATE',
                ['id' => $paymentId]
            )->fetch();
            if (!$payment) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Commission settlement not found.'];
            }
            if ($payment['payment_status'] === 'Confirmed' && $status !== 'Confirmed') {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Commission settlement is already confirmed.'];
            }
            $statement = $this->db->query(
                'UPDATE transaction_payments SET payment_status = :payment_status, payment_reference = :reference, paid_at = COALESCE(paid_at, CURRENT_TIMESTAMP), confirmed_at = IF(:confirmed_status = \'Confirmed\', CURRENT_TIMESTAMP, confirmed_at), recorded_by_account_id = :admin_id WHERE id = :id AND payment_purpose = \'EcoPick Commission\'',
                ['payment_status' => $status, 'confirmed_status' => $status, 'reference' => $reference, 'admin_id' => Auth::userId(), 'id' => $paymentId]
            );
            if ($statement->rowCount() !== 1 && $payment['payment_status'] !== $status) {
                throw new RuntimeException('Commission settlement update did not affect the expected row.');
            }
            $saved = $this->db->query(
                'SELECT payment_status FROM transaction_payments WHERE id = :id AND payment_purpose = \'EcoPick Commission\'',
                ['id' => $paymentId]
            )->fetchColumn();
            if ($saved !== $status) {
                throw new RuntimeException('Commission settlement state was not persisted.');
            }
            $this->db->commit();
            return ['success' => true, 'message' => 'Commission settlement reconciled.'];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            error_log('Commission payment reconciliation error: ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Payment reconciliation failed.'];
        }
    }

    public function reconcilePartnershipPayment(int $paymentId, string $status, string $reference = '', string $rejectionReason = ''): array
    {
        $this->requireAdmin();
        if (!in_array($status, ['Approved', 'Rejected'], true)) {
            return ['success' => false, 'message' => 'Invalid renewal status.'];
        }
        $rejectionReason = trim($rejectionReason);
        if (strlen($rejectionReason) > 500) {
            return ['success' => false, 'message' => 'A specific rejection reason is required.'];
        }
        try {
            $this->db->beginTransaction();
            $renewal = $this->db->query(
                'SELECT pr.*, a.email, COALESCE(jp.business_name, a.full_name) AS business_name
                 FROM partnership_renewals pr
                 JOIN accounts a ON a.id = pr.junkshop_account_id
                 LEFT JOIN junkshop_profiles jp ON jp.account_id = a.id
                 WHERE pr.id = :id
                 FOR UPDATE',
                ['id' => $paymentId]
            )->fetch();
            if (!$renewal) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Renewal request not found.'];
            }
            if (in_array($renewal['status'], ['Approved', 'Rejected'], true)) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Renewal request has already been reconciled.'];
            }
            $isCashPayment = strcasecmp((string) $renewal['payment_method'], 'Cash') === 0;
            if ($status === 'Rejected' && ($rejectionReason === '' || strlen($rejectionReason) > 500)) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'A specific rejection reason is required for all payment methods.'];
            }
            $this->db->query(
                'UPDATE partnership_renewals SET status = :status, rejection_reason = :rejection_reason, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['status' => $status, 'rejection_reason' => $status === 'Rejected' ? $rejectionReason : null, 'id' => $paymentId]
            );
            $this->db->query(
                'UPDATE payment_records SET status = :status WHERE renewal_id = :renewal_id',
                ['status' => $status, 'renewal_id' => $paymentId]
            );
            if ($status === 'Approved') {
                $interval = match ((string) $renewal['plan_type']) {
                    '6 Months', 'Quarterly' => '6 MONTH',
                    '1 Year', 'Annual' => '1 YEAR',
                    default => '1 MONTH',
                };
                $this->db->query(
                    "UPDATE junkshop_profiles SET partnership_expires_at = DATE_ADD(CASE WHEN partnership_expires_at IS NULL OR partnership_expires_at <= CURRENT_TIMESTAMP THEN CURRENT_TIMESTAMP ELSE partnership_expires_at END, INTERVAL {$interval}), renewal_status = 'Current' WHERE account_id = :account_id",
                    ['account_id' => (int) $renewal['junkshop_account_id']]
                );
            }
            $saved = $this->db->query('SELECT status FROM partnership_renewals WHERE id = :id', ['id' => $paymentId])->fetchColumn();
            if ($saved !== $status) {
                throw new RuntimeException('Partnership payment state was not persisted.');
            }
            $this->db->commit();
            try {
                $emailResult = $status === 'Rejected'
                    ? MailerService::sendRenewalRejection(
                        (string) $renewal['email'],
                        (string) $renewal['business_name'],
                        (string) $renewal['plan_type'],
                        (string) $renewal['payment_method'],
                        (string) ($renewal['reference_number'] ?? ''),
                        (string) $renewal['created_at'],
                        $rejectionReason
                    )
                    : MailerService::sendRenewalApproval(
                        (string) $renewal['email'],
                        (string) $renewal['business_name'],
                        (string) $renewal['plan_type'],
                        (string) $renewal['payment_method'],
                        (string) ($renewal['reference_number'] ?? ''),
                        (string) $renewal['created_at'],
                        (float) $renewal['amount']
                    );
                if (!$emailResult['sent']) {
                    error_log('Renewal decision email was not sent: ' . ($emailResult['error'] ?? 'Unknown error'));
                }
            } catch (Throwable $exception) {
                error_log('Renewal decision email dispatch error: ' . $exception->getMessage());
            }
            return ['success' => true, 'message' => 'Partnership renewal reconciled.'];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            error_log('Partnership payment reconciliation error: ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Payment reconciliation failed.'];
        }
    }

    public function setExpiry(int $junkshopAccountId, string $expiryDate, string $expiryTime = '', bool $enableExpiryTime = false): array
    {
        $this->requireAdmin();
        if ($junkshopAccountId < 1) return ['success' => false, 'message' => 'Junkshop account not found.'];
        $expiryDate = trim($expiryDate);
        $expiryTime = $enableExpiryTime ? trim($expiryTime) : '';
        if ($expiryTime === '') {
            $expiryTime = '23:59:59';
        } else {
            $time = DateTimeImmutable::createFromFormat('!H:i', $expiryTime, new DateTimeZone(APP_TIMEZONE));
            if (!$time) {
                $time = DateTimeImmutable::createFromFormat('!g:i A', strtoupper($expiryTime), new DateTimeZone(APP_TIMEZONE));
            }
            if (!$time) {
                return ['success' => false, 'message' => 'Enter a valid expiry date and time.'];
            }
            $expiryTime = $time->format('H:i:s');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $expiryDate . ' ' . $expiryTime, new DateTimeZone(APP_TIMEZONE));
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $date->format('Y-m-d H:i:s') !== $expiryDate . ' ' . $expiryTime) {
            return ['success' => false, 'message' => 'Enter a valid expiry date and time.'];
        }
        $expiryDateTime = $date->format('Y-m-d H:i:s');
        try {
            $this->db->beginTransaction();
            $statement = $this->db->query(
                'UPDATE junkshop_profiles SET partnership_expires_at = :expiry_date, last_expiration_notice_sent = NULL, renewal_status = CASE WHEN :expiry_date_status <= CURRENT_TIMESTAMP THEN \'Expired\' WHEN :expiry_date_due <= DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 DAY) THEN \'Due\' ELSE \'Current\' END WHERE account_id = :account_id AND approval_status = \'approved\'',
                ['expiry_date' => $expiryDateTime, 'expiry_date_status' => $expiryDateTime, 'expiry_date_due' => $expiryDateTime, 'account_id' => $junkshopAccountId]
            );
            $this->db->query(
                "UPDATE accounts SET account_status = 'active', updated_at = CURRENT_TIMESTAMP
                 WHERE id = :account_id AND EXISTS (SELECT 1 FROM junkshop_profiles WHERE account_id = :profile_account_id AND approval_status = 'approved')",
                ['account_id' => $junkshopAccountId, 'profile_account_id' => $junkshopAccountId]
            );
            $this->db->query(
                'DELETE FROM renewal_notification_log WHERE junkshop_account_id = :account_id',
                ['account_id' => $junkshopAccountId]
            );
            $saved = $this->db->query('SELECT partnership_expires_at FROM junkshop_profiles WHERE account_id = :account_id', ['account_id' => $junkshopAccountId])->fetchColumn();
            if ($statement->rowCount() !== 1 && $saved !== $expiryDateTime) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Junkshop expiry was not updated.'];
            }
            if ($saved !== $expiryDateTime) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Junkshop expiry was not persisted.'];
            }
            $this->db->commit();
            return ['success' => true, 'message' => 'Partnership expiry updated.'];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            error_log('Partnership expiry update error: ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Unable to update partnership expiry.'];
        }
    }

    private function hasDuplicateGcashReference(string $referenceNumber): bool
    {
        $normalizedReference = preg_replace('/\D+/', '', trim($referenceNumber));
        if (!preg_match('/^[0-9]{13}$/', $normalizedReference)) {
            return false;
        }

        $existing = $this->db->query(
                        'SELECT EXISTS (
                                SELECT 1 FROM partnership_renewals
                                WHERE payment_method = :method_renewal
                                    AND reference_number = :reference_renewal
                                    AND status IN (\'Pending\', \'Pending Reconciliation\', \'Approved\')
            ) OR EXISTS (
                SELECT 1 FROM pickup_requests WHERE payment_method = :method_pickup AND reference_number = :reference_pickup
            ) OR EXISTS (
                SELECT 1 FROM transactions WHERE payment_method = :method_transaction AND reference_number = :reference_transaction
            ) OR EXISTS (
                SELECT 1 FROM junkshop_fee_payments
                WHERE payment_method = :method_fee
                  AND reference_number = :reference_fee
                  AND status IN (\'Pending\', \'Approved\')
            ) AS duplicate_found',
            [
                'method_renewal' => 'GCash',
                'reference_renewal' => $normalizedReference,
                'method_pickup' => 'GCash',
                'reference_pickup' => $normalizedReference,
                'method_transaction' => 'GCash',
                'reference_transaction' => $normalizedReference,
                'method_fee' => 'GCash',
                'reference_fee' => $normalizedReference,
            ]
        )->fetchColumn();

        return (bool) $existing;
    }

    public function createRenewalPayment(int $junkshopAccountId, string $method, string $planKey = 'renewal_fee_1_month', string $referenceNumber = '', ?array $receiptFile = null): array
    {
        if (!Auth::check() || Auth::userRole() !== 'junkshop' || Auth::userId() !== $junkshopAccountId) {
            return ['success' => false, 'message' => 'You cannot create this payment record.'];
        }
        $profile = $this->db->query(
            'SELECT id, account_id FROM junkshop_profiles WHERE account_id = :account_id LIMIT 1',
            ['account_id' => $junkshopAccountId]
        )->fetch();
        if (!$profile || (int) $profile['account_id'] !== $junkshopAccountId) {
            return ['success' => false, 'message' => 'Your junkshop profile could not be found. Please contact support.'];
        }
        if (!in_array($method, ['Cash', 'GCash'], true)) {
            return ['success' => false, 'message' => 'Select a valid payment method.'];
        }
        $referenceNumber = trim($referenceNumber);
        if ($method === 'GCash' && !preg_match('/^[0-9]{13}$/', $referenceNumber)) {
            return ['success' => false, 'message' => 'The GCash reference number must contain exactly 13 digits.'];
        }
        if ($method === 'GCash' && $this->hasDuplicateGcashReference($referenceNumber)) {
            return ['success' => false, 'message' => 'This GCash Reference Number has already been used. Duplicate reference numbers are not allowed.'];
        }
        $plans = [
            'renewal_fee_1_month' => '1 Month',
            'renewal_fee_6_months' => '6 Months',
            'renewal_fee_1_year' => '1 Year',
        ];
        if (!isset($plans[$planKey])) {
            return ['success' => false, 'message' => 'Select a valid renewal plan.'];
        }

        $fee = $this->db->query(
            'SELECT config_value FROM fee_configurations WHERE config_key = :key LIMIT 1',
            ['key' => $planKey]
        )->fetchColumn();
        if ($fee === false) {
            return ['success' => false, 'message' => 'The selected renewal plan is unavailable.'];
        }

        $amount = (float) $fee;
        $planType = $plans[$planKey];
        $receiptPath = null;
        $uploadedReceiptPath = null;
        try {
            $this->db->beginTransaction();
            $lockedProfile = $this->db->query(
                'SELECT partnership_expires_at
                 FROM junkshop_profiles
                 WHERE account_id = :account_id
                 FOR UPDATE',
                ['account_id' => $junkshopAccountId]
            )->fetch();
            $lockedExpiry = !empty($lockedProfile['partnership_expires_at'])
                ? new DateTime($lockedProfile['partnership_expires_at'])
                : null;
            if ($lockedExpiry !== null && $lockedExpiry > new DateTime()) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Renewal submission is available after your partnership plan expires.'];
            }
            $pendingRequest = $this->db->query(
                "SELECT id
                 FROM partnership_renewals
                 WHERE junkshop_account_id = :account_id
                   AND status IN ('Pending', 'Pending Reconciliation')
                 LIMIT 1
                 FOR UPDATE",
                ['account_id' => $junkshopAccountId]
            )->fetch();
            if ($pendingRequest) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'You already have a pending renewal request awaiting Admin reconciliation.'];
            }
            if ($method === 'GCash') {
                if (!is_array($receiptFile) || ($receiptFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new InvalidArgumentException('Upload a GCash receipt screenshot.');
                }
                if (($receiptFile['size'] ?? 0) < 1 || $receiptFile['size'] > 3 * 1024 * 1024) {
                    throw new InvalidArgumentException('The GCash receipt must be 3 MB or smaller.');
                }
                if (!is_uploaded_file($receiptFile['tmp_name'] ?? '')) {
                    throw new InvalidArgumentException('The GCash receipt upload is invalid.');
                }
                if (@getimagesize($receiptFile['tmp_name']) === false) {
                    throw new InvalidArgumentException('The GCash receipt must be a valid image.');
                }
                $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($receiptFile['tmp_name']);
                $extensionByMime = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];
                if (!isset($extensionByMime[$mimeType])) {
                    throw new InvalidArgumentException('The GCash receipt must be a JPG, PNG, or WEBP image.');
                }
                $uploadDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'receipts';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
                    throw new RuntimeException('The receipt upload directory could not be created.');
                }
                $fileName = 'gcash_receipt_' . $junkshopAccountId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extensionByMime[$mimeType];
                $uploadedReceiptPath = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;
                if (!move_uploaded_file($receiptFile['tmp_name'], $uploadedReceiptPath)) {
                    throw new RuntimeException('The GCash receipt could not be saved.');
                }
                $receiptPath = 'uploads/receipts/' . $fileName;
            }
            $renewalStatement = $this->db->query(
                'INSERT INTO partnership_renewals (junkshop_account_id, renewal_type, plan_type, payment_method, amount, reference_number, receipt_image, status, created_at)
                 VALUES (:account_id, \'Renewal\', :plan_type, :method, :amount, :reference_number, :receipt_image, \'Pending Reconciliation\', CURRENT_TIMESTAMP)',
                [
                    'account_id' => $junkshopAccountId,
                    'plan_type' => $planType,
                    'method' => $method,
                    'amount' => $amount,
                    'reference_number' => $method === 'GCash' ? $referenceNumber : null,
                    'receipt_image' => $receiptPath,
                ]
            );
            if ($renewalStatement->rowCount() !== 1) {
                throw new RuntimeException('Renewal queue record was not created.');
            }
            $renewalId = (int) $this->db->getPDO()->lastInsertId();
            $paymentStatement = $this->db->query(
                'INSERT INTO payment_records (junkshop_id, renewal_id, transaction_type, payment_method, amount, reference_number, receipt_image, status, created_at)
                 VALUES (:junkshop_id, :renewal_id, :transaction_type, :payment_method, :amount, :reference_number, :receipt_image, \'Pending\', CURRENT_TIMESTAMP)',
                [
                    'junkshop_id' => $junkshopAccountId,
                    'renewal_id' => $renewalId,
                    'transaction_type' => 'Partnership Renewal (' . $planType . ')',
                    'payment_method' => $method,
                    'amount' => $amount,
                    'reference_number' => $method === 'GCash' ? $referenceNumber : null,
                    'receipt_image' => $receiptPath,
                ]
            );
            if ($paymentStatement->rowCount() !== 1) {
                throw new RuntimeException('Payment history record was not created.');
            }
            $this->db->commit();
            $message = $method === 'Cash'
                ? 'Renewal request submitted using Cash payment. Your request has been queued for Partnership Payment Reconciliation.'
                : 'Renewal payment submitted for admin confirmation.';
            return ['success' => true, 'message' => $message];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($uploadedReceiptPath !== null && is_file($uploadedReceiptPath)) {
                unlink($uploadedReceiptPath);
            }
            error_log('Renewal submission error: ' . $exception->getMessage());
            return ['success' => false, 'message' => $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Failed to submit renewal request. Please try again.'];
        }
    }

    public function listFeePayments(): array
    {
        $this->requireAdmin();
        return $this->db->query(
            "SELECT fp.*, COALESCE(jp.business_name, a.full_name, 'Unknown') AS business_name
             FROM junkshop_fee_payments fp
             LEFT JOIN junkshop_profiles jp ON jp.account_id = fp.junkshop_id
             LEFT JOIN accounts a ON a.id = fp.junkshop_id
             ORDER BY fp.created_at DESC, fp.id DESC"
        )->fetchAll();
    }

    public function createFeePayment(int $junkshopId, string $method, string $amount, string $referenceNumber = '', ?array $receiptFile = null): array
    {
        if (!Auth::check() || Auth::userRole() !== 'junkshop' || Auth::userId() !== $junkshopId) {
            return ['success' => false, 'message' => 'You cannot create this payment record.'];
        }
        if (!in_array($method, ['Cash', 'GCash'], true) || !is_numeric($amount) || (float) $amount <= 0) {
            return ['success' => false, 'message' => 'Enter a valid payment amount and method.'];
        }
        $amount = number_format((float) $amount, 2, '.', '');
        $referenceNumber = trim($referenceNumber);
        if ($method === 'GCash' && !preg_match('/^[0-9]{13}$/', $referenceNumber)) {
            return ['success' => false, 'message' => 'The GCash reference number must contain exactly 13 digits.'];
        }
        if ($method === 'GCash' && $this->hasDuplicateGcashReference($referenceNumber)) {
            return ['success' => false, 'message' => 'This GCash Reference Number has already been used in a pending or approved payment.'];
        }
        $pendingFeePayment = $this->db->query(
            "SELECT 1 FROM junkshop_fee_payments
             WHERE junkshop_id = :junkshop_id AND status = 'Pending'
             LIMIT 1",
            ['junkshop_id' => $junkshopId]
        )->fetchColumn();
        if ($pendingFeePayment !== false) {
            return ['success' => false, 'message' => 'You currently have a payment submission pending admin verification. You may submit another payment only after your pending request is Approved or Rejected.'];
        }
        $receiptPath = null;
        $uploadedPath = null;
        try {
            if ($method === 'GCash') {
                if (!is_array($receiptFile) || ($receiptFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new InvalidArgumentException('Upload a GCash receipt screenshot.');
                }
                if (($receiptFile['size'] ?? 0) < 1 || $receiptFile['size'] > 3 * 1024 * 1024 || !is_uploaded_file($receiptFile['tmp_name'] ?? '')) {
                    throw new InvalidArgumentException('The GCash receipt must be a valid image no larger than 3 MB.');
                }
                $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($receiptFile['tmp_name']);
                $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($extensions[$mimeType]) || @getimagesize($receiptFile['tmp_name']) === false) {
                    throw new InvalidArgumentException('The GCash receipt must be a JPG, PNG, or WEBP image.');
                }
                $uploadDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'receipts';
                if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
                    throw new RuntimeException('The receipt upload directory could not be created.');
                }
                $fileName = 'fee_receipt_' . $junkshopId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extensions[$mimeType];
                $uploadedPath = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;
                if (!move_uploaded_file($receiptFile['tmp_name'], $uploadedPath)) {
                    throw new RuntimeException('The GCash receipt could not be saved.');
                }
                $receiptPath = 'uploads/receipts/' . $fileName;
            }
            $this->db->query(
                'INSERT INTO junkshop_fee_payments (junkshop_id, payment_method, reference_number, receipt_image, amount_submitted)
                 VALUES (:junkshop_id, :payment_method, :reference_number, :receipt_image, :amount_submitted)',
                ['junkshop_id' => $junkshopId, 'payment_method' => $method, 'reference_number' => $method === 'GCash' ? $referenceNumber : null, 'receipt_image' => $receiptPath, 'amount_submitted' => $amount]
            );
            return ['success' => true, 'message' => 'Fee payment submitted for admin verification.'];
        } catch (Throwable $exception) {
            if ($uploadedPath !== null && is_file($uploadedPath)) unlink($uploadedPath);
            error_log('Fee payment submission error: ' . $exception->getMessage());
            return ['success' => false, 'message' => $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Failed to submit fee payment. Please try again.'];
        }
    }

    public function reconcileFeePayment(int $paymentId, string $status, string $deductedAmount = '0', string $rejectionReason = ''): array
    {
        $this->requireAdmin();
        if (!in_array($status, ['Approved', 'Rejected'], true)) return ['success' => false, 'message' => 'Invalid fee payment status.'];
        if ($status === 'Approved' && (!is_numeric($deductedAmount) || (float) $deductedAmount <= 0)) return ['success' => false, 'message' => 'Enter a valid deduction amount.'];
        $rejectionReason = trim($rejectionReason);
        if ($status === 'Rejected' && $rejectionReason === '') return ['success' => false, 'message' => 'Select a rejection reason.'];
        if (strlen($rejectionReason) > 1000) return ['success' => false, 'message' => 'The rejection reason is too long.'];
        try {
            $this->db->beginTransaction();
            $payment = $this->db->query('SELECT id, amount_submitted, status FROM junkshop_fee_payments WHERE id = :id FOR UPDATE', ['id' => $paymentId])->fetch();
            if (!$payment || $payment['status'] !== 'Pending') {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Fee payment was not found or has already been reconciled.'];
            }
            $deductedAmount = $status === 'Approved' ? number_format((float) $deductedAmount, 2, '.', '') : '0.00';
            if ($status === 'Approved' && (float) $deductedAmount > (float) $payment['amount_submitted']) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'The deduction cannot exceed the submitted amount.'];
            }
            $this->db->query(
                'UPDATE junkshop_fee_payments SET status = :status, amount_deducted = :amount_deducted, rejection_reason = :rejection_reason, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                ['status' => $status, 'amount_deducted' => $deductedAmount, 'rejection_reason' => $status === 'Rejected' ? $rejectionReason : null, 'id' => $paymentId]
            );
            $this->db->commit();
            try {
                $emailResult = MailerService::sendJunkshopPaymentNotification($paymentId, $status, (float) $deductedAmount, $rejectionReason);
                if (!$emailResult['sent']) {
                    error_log('Fee payment decision email was not sent: ' . ($emailResult['error'] ?? 'Unknown error'));
                }
            } catch (Throwable $exception) {
                error_log('Fee payment decision email dispatch error: ' . $exception->getMessage());
            }
            return ['success' => true, 'message' => $status === 'Approved' ? 'Fee payment approved and deducted from the outstanding balance.' : 'Fee payment rejected.'];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            error_log('Fee payment reconciliation error: ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Fee payment reconciliation failed.'];
        }
    }

    public function getConcernCooldownHours(): int
    {
        $hours = (int) $this->db->query(
            'SELECT concern_cooldown_hours FROM fee_settings ORDER BY id ASC LIMIT 1'
        )->fetchColumn();

        return $hours > 0 ? $hours : 24;
    }

    public function getLatestConcernCreatedAt(int $accountId): ?string
    {
        $createdAt = $this->db->query(
            'SELECT created_at FROM concerns WHERE reporter_account_id = :account_id ORDER BY created_at DESC LIMIT 1',
            ['account_id' => $accountId]
        )->fetchColumn();

        return $createdAt !== false && $createdAt !== null ? (string) $createdAt : null;
    }

    public function isAccountExpired(int $accountId): bool
    {
        $isExpired = $this->db->query(
            "SELECT CASE
                WHEN LOWER(COALESCE(a.account_status, '')) = 'expired' THEN 1
                WHEN COALESCE(a.account_role, r.name) = 'junkshop'
                    AND jp.partnership_expires_at IS NOT NULL
                    AND jp.partnership_expires_at <> '0000-00-00 00:00:00'
                    AND jp.partnership_expires_at <= CURRENT_TIMESTAMP THEN 1
                ELSE 0
             END AS is_expired
             FROM accounts a
             JOIN roles r ON r.id = a.role_id
             LEFT JOIN junkshop_profiles jp ON jp.account_id = a.id
             WHERE a.id = :account_id
             LIMIT 1",
            ['account_id' => $accountId]
        )->fetchColumn();

        return (int) $isExpired === 1;
    }

    public function listConcerns(?int $reporterId = null): array
    {
        if ($reporterId === null) {
            $this->requireAdmin();
        }

        $sql = 'SELECT c.*, a.full_name AS reporter_name, a.email AS reporter_email,
                CASE
                    WHEN COALESCE(jp.business_name, \'\') <> \'\' THEN jp.business_name
                    ELSE a.full_name
                END AS reporter_display_name,
                CASE
                    WHEN a.account_role = :junkshop_role THEN \'Junkshop\'
                    WHEN a.account_role = :seller_role THEN \'Seller\'
                    ELSE \'User\'
                END AS reporter_role
                FROM concerns c
                JOIN accounts a ON a.id = c.reporter_account_id
                LEFT JOIN junkshop_profiles jp ON jp.account_id = a.id
                LEFT JOIN sellers s ON s.account_id = a.id';
        $params = ['junkshop_role' => 'junkshop', 'seller_role' => 'seller'];
        if ($reporterId !== null) {
            $sql .= ' WHERE c.reporter_account_id = :reporter_id';
            $params['reporter_id'] = $reporterId;
        }

        $rows = $this->db->query($sql . ' ORDER BY c.created_at DESC', $params)->fetchAll();
        foreach ($rows as &$row) {
            $row['reporter_name'] = $row['reporter_display_name'] ?: $row['reporter_name'];
            $row['reporter_role'] = $row['reporter_role'] ?? 'Seller';
        }
        unset($row);

        return $rows;
    }

    public function submitConcern(int $reporterId, string $subject, string $description): array
    {
        if ($this->isAccountExpired($reporterId)) {
            return ['success' => false, 'message' => 'Action locked: Cannot send messages while your account is expired.'];
        }

        $subject = trim($subject);
        $description = trim($description);

        if ($subject === '' || $description === '') {
            return ['success' => false, 'message' => 'Subject and description are required.'];
        }

        if (mb_strlen($subject) > 50) {
            return ['success' => false, 'message' => 'Subject must be 50 characters or fewer.'];
        }

        if (mb_strlen($description) > 150) {
            return ['success' => false, 'message' => 'Description must be 150 characters or fewer.'];
        }

        $cooldownHours = $this->getConcernCooldownHours();
        $lastCreatedAt = $this->getLatestConcernCreatedAt($reporterId);

        if ($lastCreatedAt !== null) {
            $lastTimestamp = strtotime($lastCreatedAt);
            $elapsedSeconds = time() - $lastTimestamp;
            $cooldownSeconds = $cooldownHours * 3600;

            if ($elapsedSeconds < $cooldownSeconds) {
                $remainingSeconds = $cooldownSeconds - $elapsedSeconds;
                $hours = intdiv($remainingSeconds, 3600);
                $minutes = intdiv($remainingSeconds % 3600, 60);
                $message = sprintf(
                    'You must wait %d hours and %d minutes before submitting another concern.',
                    $hours,
                    $minutes
                );
                if ($hours === 0) {
                    $message = sprintf('You must wait %d minutes before submitting another concern.', $minutes);
                }
                return ['success' => false, 'message' => $message];
            }
        }

        $statement = $this->db->query(
            'INSERT INTO concerns (reporter_account_id, account_id, subject, description, status, created_at)
             VALUES (:reporter_id, :account_id, :subject, :description, :status, CURRENT_TIMESTAMP)',
            [
                'reporter_id' => $reporterId,
                'account_id' => $reporterId,
                'subject' => mb_substr($subject, 0, 50),
                'description' => mb_substr($description, 0, 150),
                'status' => 'Open',
            ]
        );
        if ($statement->rowCount() !== 1) {
            return ['success' => false, 'message' => 'Your concern could not be submitted.'];
        }
        return ['success' => true, 'message' => 'Your concern was submitted.'];
    }

    public function updateConcern(int $concernId, string $status, string $note = ''): array
    {
        $this->requireAdmin();
        if (!in_array($status, ['Open', 'In Review', 'Resolved', 'Closed'], true)) return ['success' => false, 'message' => 'Invalid concern status.'];
        $concern = $this->db->query('SELECT status FROM concerns WHERE id = :id', ['id' => $concernId])->fetch();
        if (!$concern) return ['success' => false, 'message' => 'Concern not found.'];
        $this->db->beginTransaction();
        try {
            $this->db->query('UPDATE concerns SET status = :status, admin_note = :note, resolved_at = IF(:resolved_status IN (\'Resolved\', \'Closed\'), CURRENT_TIMESTAMP, NULL), resolved_by_account_id = IF(:actor_status IN (\'Resolved\', \'Closed\'), :admin_id, NULL) WHERE id = :id', ['status' => $status, 'resolved_status' => $status, 'actor_status' => $status, 'note' => trim($note) !== '' ? trim($note) : null, 'admin_id' => Auth::userId(), 'id' => $concernId]);
            $this->db->query('INSERT INTO concern_status_history (concern_id, previous_status, new_status, note, acting_account_id) VALUES (:id, :previous, :status, :note, :admin_id)', ['id' => $concernId, 'previous' => $concern['status'], 'status' => $status, 'note' => trim($note) !== '' ? trim($note) : null, 'admin_id' => Auth::userId()]);
            $this->db->commit();
            return ['success' => true, 'message' => 'Concern updated.'];
        } catch (Throwable $exception) { $this->db->rollBack(); return ['success' => false, 'message' => 'Concern update failed.']; }
    }

    public function getReport(string $startDate, string $endDate, string $sortOrder = 'DESC'): array
    {
        $this->requireAdmin();
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        $row = $this->db->query('SELECT COUNT(*) AS completed_pickups, COALESCE(SUM(ecopick_service_fee), 0) AS service_fees, COALESCE(SUM(transaction_commission), 0) AS commissions, COALESCE(SUM(final_recyclable_value), 0) AS recyclable_value FROM transactions WHERE completed_at >= :start_date AND completed_at < DATE_ADD(:end_date, INTERVAL 1 DAY)', ['start_date' => $startDate, 'end_date' => $endDate])->fetch();
        $junkshopReports = $this->db->query(
            "SELECT a.id AS junkshop_id, jp.business_name, a.email, a.mobile_number,
                    COUNT(t.id) AS total_transactions,
                    COALESCE(SUM(t.actual_weight_kg), 0) AS total_weight_kg,
                    COALESCE(SUM(t.ecopick_service_fee), 0) AS total_service_fees,
                    COALESCE(SUM(t.transaction_commission), 0) AS total_commissions,
                    COALESCE(SUM(t.final_recyclable_value), 0) AS total_recyclable_value,
                    COALESCE(SUM(t.pickup_fee), 0) AS total_pickup_fees,
                    COALESCE(SUM(t.final_seller_amount), 0) AS total_seller_amount
             FROM accounts a
             JOIN roles r ON r.id = a.role_id AND r.name = 'junkshop'
             JOIN junkshop_profiles jp ON jp.account_id = a.id
             LEFT JOIN transactions t
                    ON t.junkshop_id = a.id
                   AND t.completed_at >= :start_date
                   AND t.completed_at < DATE_ADD(:end_date, INTERVAL 1 DAY)
             GROUP BY a.id, jp.business_name, a.email, a.mobile_number
            ORDER BY total_service_fees DESC, jp.business_name ASC",
            ['start_date' => $startDate, 'end_date' => $endDate]
        )->fetchAll();

        $transactionRows = $this->db->query(
                "SELECT t.id AS transaction_id, t.junkshop_id, t.actual_weight_kg, t.ecopick_service_fee,
                    t.final_recyclable_value, t.pickup_fee, t.transaction_commission,
                    t.final_seller_amount, t.completed_at, pr.booking_reference,
                    COALESCE(t.payment_method, pr.payment_method) AS payment_method,
                    COALESCE(t.reference_number, pr.reference_number) AS reference_number,
                    COALESCE(t.receipt_image, pr.receipt_image) AS receipt_image,
                    seller.full_name AS seller_name, seller.email AS seller_email,
                    seller.mobile_number AS seller_mobile
             FROM transactions t
             JOIN pickup_requests pr ON pr.id = t.pickup_request_id
             JOIN accounts seller ON seller.id = t.seller_id
             WHERE t.completed_at >= :start_date
               AND t.completed_at < DATE_ADD(:end_date, INTERVAL 1 DAY)
             ORDER BY t.junkshop_id ASC, t.completed_at {$sortOrder}, t.id {$sortOrder}",
            ['start_date' => $startDate, 'end_date' => $endDate]
        )->fetchAll();

        $transactionsByJunkshop = [];
        foreach ($transactionRows as $transaction) {
            $junkshopId = (int) $transaction['junkshop_id'];
            $transactionsByJunkshop[$junkshopId][] = $transaction;
        }

        foreach ($junkshopReports as &$junkshopReport) {
            $junkshopId = (int) $junkshopReport['junkshop_id'];
            $junkshopReport['transactions'] = $transactionsByJunkshop[$junkshopId] ?? [];
        }
        unset($junkshopReport);

        return [
            'completed_pickups' => (int) ($row['completed_pickups'] ?? 0),
            'service_fees' => (float) ($row['service_fees'] ?? 0),
            'commissions' => (float) ($row['commissions'] ?? 0),
            'recyclable_value' => (float) ($row['recyclable_value'] ?? 0),
            'junkshop_reports' => $junkshopReports,
        ];
    }
}