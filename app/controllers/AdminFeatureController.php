<?php
/** Admin renewals, support, and reporting workflows. */
class AdminFeatureController
{
    private Database $db;

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

    public function listPartnershipPayments(): array
    {
        $this->requireAdmin();
        return $this->db->query(
            'SELECT p.id, p.junkshop_account_id, p.payment_type, p.amount, p.payment_method, p.payment_status, p.payment_reference, p.due_at, p.paid_at, p.confirmed_at, p.created_at, jp.business_name, jp.partnership_expires_at, jp.renewal_status FROM junkshop_partnership_payments p JOIN junkshop_profiles jp ON jp.account_id = p.junkshop_account_id ORDER BY p.payment_status = \'Confirmed\', p.created_at DESC'
        )->fetchAll();
    }

    public function getDefaultJunkshopExpiryDays(): int
    {
        $value = $this->db->query(
            "SELECT config_value FROM fee_configurations WHERE config_key = 'default_junkshop_expiry_days' LIMIT 1"
        )->fetchColumn();
        $days = (int) $value;
        return in_array($days, [21, 30], true) ? $days : 30;
    }

    public function updateDefaultJunkshopExpiryDays(int $days): array
    {
        $this->requireAdmin();
        if (!in_array($days, [21, 30], true)) {
            return ['success' => false, 'message' => 'Select a valid default expiration period.'];
        }

        try {
            $this->db->query(
                "INSERT INTO fee_configurations (config_key, config_value, description)
                 VALUES ('default_junkshop_expiry_days', :days, 'Default partnership expiration period for newly approved junkshops.')
                 ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = CURRENT_TIMESTAMP",
                ['days' => $days]
            );
            return ['success' => true, 'message' => 'Default expiration period updated.'];
        } catch (Throwable $exception) {
            error_log('Default junkshop expiry update error: ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Unable to update the default expiration period.'];
        }
    }

    public function listApprovedJunkshops(): array
    {
        $this->requireAdmin();
        return $this->db->query(
            "SELECT a.id AS account_id, a.account_status, jp.business_name,
                    jp.partnership_expires_at, jp.renewal_status,
                    CASE WHEN jp.partnership_expires_at IS NOT NULL
                            AND jp.partnership_expires_at <= CURRENT_TIMESTAMP
                         THEN 'Expired' ELSE a.account_status END AS display_status
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

    public function reconcilePartnershipPayment(int $paymentId, string $status, string $reference = ''): array
    {
        $this->requireAdmin();
        if (!in_array($status, ['Paid', 'Confirmed'], true)) {
            return ['success' => false, 'message' => 'Invalid payment status.'];
        }
        try {
            $this->db->beginTransaction();
            $payment = $this->db->query('SELECT * FROM junkshop_partnership_payments WHERE id = :id FOR UPDATE', ['id' => $paymentId])->fetch();
            if (!$payment) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Payment not found.'];
            }
            if ($payment['payment_status'] === 'Confirmed' && $status !== 'Confirmed') {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Payment is already confirmed.'];
            }
            $this->db->query(
                'UPDATE junkshop_partnership_payments SET payment_status = :payment_status, payment_reference = :reference, paid_at = COALESCE(paid_at, CURRENT_TIMESTAMP), confirmed_at = IF(:confirmed_status = \'Confirmed\', CURRENT_TIMESTAMP, confirmed_at), recorded_by_account_id = :admin_id WHERE id = :id',
                ['payment_status' => $status, 'confirmed_status' => $status, 'reference' => trim($reference) !== '' ? trim($reference) : null, 'admin_id' => Auth::userId(), 'id' => $paymentId]
            );
            if ($status === 'Confirmed') {
                $this->db->query(
                    'UPDATE junkshop_profiles SET partnership_expires_at = DATE_ADD(CASE WHEN partnership_expires_at IS NULL OR partnership_expires_at <= CURRENT_TIMESTAMP THEN CURRENT_TIMESTAMP ELSE partnership_expires_at END, INTERVAL 365 DAY), renewal_status = \'Current\' WHERE account_id = :account_id',
                    ['account_id' => (int) $payment['junkshop_account_id']]
                );
                $profile = $this->db->query(
                    'SELECT partnership_expires_at, renewal_status FROM junkshop_profiles WHERE account_id = :account_id',
                    ['account_id' => (int) $payment['junkshop_account_id']]
                )->fetch();
                if (!$profile || $profile['partnership_expires_at'] === null || $profile['renewal_status'] !== 'Current') {
                    throw new RuntimeException('Junkshop profile was not updated.');
                }
            }
            $saved = $this->db->query('SELECT payment_status FROM junkshop_partnership_payments WHERE id = :id', ['id' => $paymentId])->fetchColumn();
            if ($saved !== $status) {
                throw new RuntimeException('Partnership payment state was not persisted.');
            }
            $this->db->commit();
            return ['success' => true, 'message' => 'Partnership payment reconciled.'];
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
        } elseif (preg_match('/^\d{2}:\d{2}$/', $expiryTime) === 1) {
            $expiryTime .= ':00';
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $expiryDate . ' ' . $expiryTime);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $date->format('Y-m-d H:i:s') !== $expiryDate . ' ' . $expiryTime) {
            return ['success' => false, 'message' => 'Enter a valid expiry date and time.'];
        }
        $expiryDateTime = $date->format('Y-m-d H:i:s');
        try {
            $this->db->beginTransaction();
            $statement = $this->db->query(
                'UPDATE junkshop_profiles SET partnership_expires_at = :expiry_date, renewal_status = CASE WHEN :expiry_date_status <= CURRENT_TIMESTAMP THEN \'Expired\' WHEN :expiry_date_due <= DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 DAY) THEN \'Due\' ELSE \'Current\' END WHERE account_id = :account_id AND approval_status = \'approved\'',
                ['expiry_date' => $expiryDateTime, 'expiry_date_status' => $expiryDateTime, 'expiry_date_due' => $expiryDateTime, 'account_id' => $junkshopAccountId]
            );
            $this->db->query(
                "UPDATE accounts SET account_status = 'active', updated_at = CURRENT_TIMESTAMP
                 WHERE id = :account_id AND EXISTS (SELECT 1 FROM junkshop_profiles WHERE account_id = :profile_account_id AND approval_status = 'approved')",
                ['account_id' => $junkshopAccountId, 'profile_account_id' => $junkshopAccountId]
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

    public function createRenewalPayment(int $junkshopAccountId, string $method, string $reference = ''): array
    {
        if (!Auth::check() || Auth::userRole() !== 'junkshop' || Auth::userId() !== $junkshopAccountId) {
            return ['success' => false, 'message' => 'You cannot create this payment record.'];
        }
        if (!in_array($method, ['Cash', 'GCash'], true)) {
            return ['success' => false, 'message' => 'Select a valid payment method.'];
        }
        $fee = $this->db->query('SELECT config_value FROM fee_configurations WHERE config_key = :key LIMIT 1', ['key' => 'junkshop_renewal_fee'])->fetchColumn();
        $statement = $this->db->query('INSERT INTO junkshop_partnership_payments (junkshop_account_id, payment_type, amount, payment_method, payment_status, payment_reference, due_at) VALUES (:account_id, \'Renewal\', :amount, :method, \'Paid\', :reference, CURRENT_TIMESTAMP)', ['account_id' => $junkshopAccountId, 'amount' => (float) $fee, 'method' => $method, 'reference' => trim($reference) !== '' ? trim($reference) : null]);
        if ($statement->rowCount() !== 1) {
            return ['success' => false, 'message' => 'Renewal payment could not be recorded.'];
        }
        return ['success' => true, 'message' => 'Renewal payment submitted for admin confirmation.'];
    }

    public function listConcerns(?int $reporterId = null): array
    {
        if ($reporterId === null) $this->requireAdmin();
        $sql = 'SELECT c.*, a.full_name AS reporter_name, a.email AS reporter_email FROM concerns c JOIN accounts a ON a.id = c.reporter_account_id';
        $params = [];
        if ($reporterId !== null) { $sql .= ' WHERE c.reporter_account_id = :reporter_id'; $params['reporter_id'] = $reporterId; }
        return $this->db->query($sql . ' ORDER BY c.created_at DESC', $params)->fetchAll();
    }

    public function submitConcern(int $reporterId, string $subject, string $description): array
    {
        if (trim($subject) === '' || trim($description) === '') return ['success' => false, 'message' => 'Subject and description are required.'];
        $statement = $this->db->query('INSERT INTO concerns (reporter_account_id, subject, description) VALUES (:reporter_id, :subject, :description)', ['reporter_id' => $reporterId, 'subject' => mb_substr(trim($subject), 0, 160), 'description' => trim($description)]);
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

    public function getReport(string $startDate, string $endDate): array
    {
        $this->requireAdmin();
        $row = $this->db->query('SELECT COUNT(*) AS completed_pickups, COALESCE(SUM(ecopick_service_fee), 0) AS service_fees, COALESCE(SUM(transaction_commission), 0) AS commissions, COALESCE(SUM(final_recyclable_value), 0) AS recyclable_value FROM transactions WHERE completed_at >= :start_date AND completed_at < DATE_ADD(:end_date, INTERVAL 1 DAY)', ['start_date' => $startDate, 'end_date' => $endDate])->fetch();
        return $row ?: ['completed_pickups' => 0, 'service_fees' => 0, 'commissions' => 0, 'recyclable_value' => 0];
    }
}