<?php
/**
 * Dashboard and profile controller
 */

require_once __DIR__ . '/../../app/bootstrap.php';

class DashboardController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAdminStats()
    {
        try {
            $row = $this->db->query(
                "SELECT
                    (SELECT COUNT(*) FROM accounts a JOIN roles r ON r.id = a.role_id WHERE r.name = 'seller') AS total_sellers,
                    (SELECT COUNT(*) FROM accounts a JOIN roles r ON r.id = a.role_id WHERE r.name = 'junkshop') AS total_junkshops,
                    (SELECT COUNT(*) FROM junkshop_profiles WHERE approval_status = 'pending') AS pending_junkshop_applications,
                    (SELECT COUNT(*) FROM junkshop_profiles WHERE approval_status = 'approved') AS approved_junkshops"
            )->fetch();
            return $row ?: [
                'total_sellers' => 0,
                'total_junkshops' => 0,
                'pending_junkshop_applications' => 0,
                'approved_junkshops' => 0,
            ];
        } catch (Exception $e) {
            error_log('Dashboard stats error: ' . $e->getMessage());
            return [
                'total_sellers' => 0,
                'total_junkshops' => 0,
                'pending_junkshop_applications' => 0,
                'approved_junkshops' => 0,
            ];
        }
    }

    public function getCompletedTransactionMetrics(): array
    {
        $metrics = [
            'platform_revenue' => 0.0,
            'weight_collected_kg' => 0.0,
            'completed_transactions' => 0,
        ];

        try {
            $row = $this->db->query(
                'SELECT
                    COALESCE((SELECT SUM(t.ecopick_service_fee + t.transaction_commission)
                        FROM transactions t
                        JOIN pickup_requests completed_revenue ON completed_revenue.id = t.pickup_request_id
                        WHERE completed_revenue.current_status = :revenue_status), 0) AS platform_revenue,
                    COALESCE((SELECT SUM(tm.actual_weight_kg)
                        FROM transaction_materials tm
                        JOIN transactions completed_weight_transaction ON completed_weight_transaction.id = tm.transaction_id
                        JOIN pickup_requests completed_weight ON completed_weight.id = completed_weight_transaction.pickup_request_id
                        WHERE completed_weight.current_status = :weight_status AND tm.accepted = 1), 0) AS weight_collected_kg,
                    (SELECT COUNT(*) FROM pickup_requests WHERE current_status = :completed_status) AS completed_transactions',
                [
                    'revenue_status' => 'Completed',
                    'weight_status' => 'Completed',
                    'completed_status' => 'Completed',
                ]
            )->fetch();

            if ($row) {
                $metrics['platform_revenue'] = (float) ($row['platform_revenue'] ?? 0);
                $metrics['weight_collected_kg'] = (float) ($row['weight_collected_kg'] ?? 0);
                $metrics['completed_transactions'] = (int) ($row['completed_transactions'] ?? 0);
            }
        } catch (Throwable $e) {
            error_log('Completed transaction metrics error: ' . $e->getMessage());
        }

        return $metrics;
    }

    public function listPendingJunkshops()
    {
        return $this->db->query(
            "SELECT jp.account_id, jp.business_name, a.full_name AS contact_person, a.email, a.mobile_number,
                    jp.complete_address AS address, jp.operating_schedule, jp.business_permit_reference,
                    jp.created_at AS registration_date, jp.approval_status AS status, a.account_status
             FROM junkshop_profiles jp
             JOIN accounts a ON a.id = jp.account_id
             WHERE jp.approval_status = 'pending'
             ORDER BY jp.created_at DESC"
        )->fetchAll();
    }

    public function listSellers()
    {
        return $this->db->query(
            "SELECT a.id, a.full_name, a.email, a.mobile_number,
                    CONCAT(COALESCE(sp.address, ''), IF(sp.barangay IS NOT NULL AND sp.barangay <> '', CONCAT(', ', sp.barangay), '')) AS address,
                    a.account_status, a.created_at
             FROM accounts a
             JOIN roles r ON r.id = a.role_id
             LEFT JOIN sellers sp ON sp.account_id = a.id
             WHERE r.name = 'seller'
             ORDER BY a.full_name ASC"
        )->fetchAll();
    }

    public function updateAccountStatus(int $accountId, string $status): array
    {
        if (!Auth::check() || Auth::userRole() !== 'admin') {
            return ['success' => false, 'message' => 'Admin access required.'];
        }
        if (!in_array($status, ['active', 'inactive'], true) || $accountId === Auth::userId()) {
            return ['success' => false, 'message' => 'Invalid account status change.'];
        }
        $statement = $this->db->query(
            "UPDATE accounts SET account_status = :status WHERE id = :account_id AND account_role IN ('seller', 'junkshop')",
            ['status' => $status, 'account_id' => $accountId]
        );
        return [
            'success' => $statement->rowCount() === 1,
            'message' => $statement->rowCount() === 1 ? 'Account status updated.' : 'Account not found.',
        ];
    }

    public function deleteAccount(int $accountId): array
    {
        if (!Auth::check() || Auth::userRole() !== 'admin') {
            return ['success' => false, 'message' => 'Admin access required.'];
        }
        if ($accountId <= 0 || $accountId === Auth::userId()) {
            return ['success' => false, 'message' => 'This account cannot be deleted.'];
        }

        $statement = $this->db->query(
            "DELETE FROM accounts WHERE id = :account_id AND account_role IN ('seller', 'junkshop')",
            ['account_id' => $accountId]
        );

        return [
            'success' => $statement->rowCount() === 1,
            'message' => $statement->rowCount() === 1 ? 'Account deleted.' : 'Account not found.',
        ];
    }

    public function listApprovedJunkshops()
    {
        return $this->db->query(
            "SELECT jp.account_id, jp.business_name, a.full_name AS contact_person, a.email, a.mobile_number,
                    jp.complete_address AS address, jp.operating_schedule, jp.business_permit_reference,
                    jp.created_at AS registration_date, jp.approval_status AS status
             FROM junkshop_profiles jp
             JOIN accounts a ON a.id = jp.account_id
             WHERE jp.approval_status = 'approved'
             ORDER BY jp.created_at DESC"
        )->fetchAll();
    }

    public function updateJunkshopApproval($accountId, $status)
    {
        $status = in_array($status, ['approved', 'rejected'], true) ? $status : null;
        if ($status === null) {
            return ['success' => false, 'message' => 'Invalid approval status'];
        }

        try {
            $this->db->beginTransaction();
            $profile = $this->db->query(
                'SELECT account_id FROM junkshop_profiles WHERE account_id = :account_id LIMIT 1',
                ['account_id' => (int) $accountId]
            )->fetch();
            if (!$profile) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Junkshop account not found'];
            }
            $expiryDate = null;
            if ($status === 'approved') {
                $expiryDate = (new DateTimeImmutable('now', new DateTimeZone(APP_TIMEZONE)))
                    ->modify('+21 days')
                    ->setTime(23, 59, 59)
                    ->format('Y-m-d H:i:s');
            }
            $this->db->query(
                'UPDATE junkshop_profiles SET approval_status = :status, partnership_expires_at = COALESCE(:expiry_date, partnership_expires_at), renewal_status = CASE WHEN :status_for_expiry = \'approved\' THEN \'Current\' ELSE renewal_status END, updated_at = CURRENT_TIMESTAMP WHERE account_id = :account_id',
                ['status' => $status, 'expiry_date' => $expiryDate, 'status_for_expiry' => $status, 'account_id' => (int) $accountId]
            );
            $this->db->query(
                "UPDATE accounts SET account_status = 'active', updated_at = CURRENT_TIMESTAMP
                 WHERE id = :account_id AND account_status <> 'inactive'",
                ['account_id' => (int) $accountId]
            );
            $this->db->commit();
            if ($profile) {
                return ['success' => true, 'message' => 'Junkshop status updated successfully'];
            }
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Junkshop approval update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update junkshop status'];
        }
    }

    public function getSellerProfile($accountId)
    {
        return $this->db->query(
            "SELECT a.id AS account_id, a.username, a.full_name, a.email, a.mobile_number, a.account_status,
                    sp.address, sp.barangay, a.created_at, NULLIF(sp.updated_at, sp.created_at) AS profile_updated_at
             FROM accounts a
                 LEFT JOIN sellers sp ON sp.account_id = a.id
             WHERE a.id = :account_id AND a.role_id = (SELECT id FROM roles WHERE name = 'seller')",
            ['account_id' => (int) $accountId]
        )->fetch() ?: null;
    }

    public function updateSellerProfile($accountId, $fullName, $mobileNumber, $address, $barangay)
    {
        try {
            $this->db->beginTransaction();
            $account = $this->db->query(
                "SELECT a.id, sp.last_profile_edit
                 FROM accounts a
                 LEFT JOIN sellers sp ON sp.account_id = a.id
                 WHERE a.id = :account_id AND a.role_id = (SELECT id FROM roles WHERE name = 'seller')
                 LIMIT 1 FOR UPDATE",
                ['account_id' => (int) $accountId]
            )->fetch();
            if (!$account) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Seller account not found'];
            }
            if ($account['last_profile_edit'] !== null
                && strtotime((string) $account['last_profile_edit']) > strtotime('-7 days')) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Profile edits are locked for 7 days after an update.'];
            }
            $this->db->query(
                'UPDATE accounts SET full_name = :full_name, mobile_number = :mobile_number, updated_at = CURRENT_TIMESTAMP WHERE id = :account_id',
                ['full_name' => $fullName, 'mobile_number' => $mobileNumber, 'account_id' => (int) $accountId]
            );
            $this->db->query(
                'INSERT INTO sellers (account_id, address, barangay, last_profile_edit) VALUES (:account_id, :address, :barangay, CURRENT_TIMESTAMP)
                 ON DUPLICATE KEY UPDATE address = VALUES(address), barangay = VALUES(barangay), last_profile_edit = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP',
                ['account_id' => (int) $accountId, 'address' => $address, 'barangay' => $barangay]
            );
            $this->db->commit();
            return ['success' => true, 'message' => 'Profile updated successfully.'];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Seller profile update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update profile'];
        }
    }

    public function getJunkshopProfile($accountId)
    {
        try {
            return $this->db->query(
                "SELECT a.id AS account_id, a.username, a.email, a.full_name AS owner_name, a.mobile_number,
                        a.account_status, jp.business_name, jp.complete_address, jp.latitude, jp.longitude, jp.operating_schedule,
                        jp.business_permit_reference, jp.gcash_account_name, jp.gcash_account_number, jp.is_available,
                        jp.approval_status, jp.last_profile_edit AS profile_updated_at
                 FROM accounts a
                 JOIN junkshop_profiles jp ON jp.account_id = a.id
                 WHERE a.id = :account_id AND a.role_id = (SELECT id FROM roles WHERE name = 'junkshop')",
                ['account_id' => (int) $accountId]
            )->fetch() ?: null;
        } catch (Throwable $e) {
            error_log('Junkshop profile read error: ' . $e->getMessage());
            return null;
        }
    }

    public function updateJunkshopProfile($accountId, $businessName, $ownerName, $mobileNumber, $completeAddress, $operatingSchedule, $permitReference, $gcashAccountName = '', $gcashAccountNumber = '', $latitude = null, $longitude = null)
    {
        try {
            $this->db->beginTransaction();
            $account = $this->db->query(
                "SELECT a.id, jp.last_profile_edit
                 FROM accounts a
                 JOIN junkshop_profiles jp ON jp.account_id = a.id
                 WHERE a.id = :account_id AND a.role_id = (SELECT id FROM roles WHERE name = 'junkshop')
                 LIMIT 1 FOR UPDATE",
                ['account_id' => (int) $accountId]
            )->fetch();
            if (!$account) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Junkshop account not found'];
            }
            if ($account['last_profile_edit'] !== null
                && strtotime((string) $account['last_profile_edit']) > strtotime('-7 days')) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Profile edits are locked for 7 days after an update.'];
            }
            $this->db->query(
                'UPDATE accounts SET full_name = :account_owner_name, mobile_number = :mobile_number, updated_at = CURRENT_TIMESTAMP WHERE id = :account_id',
                ['account_owner_name' => $ownerName, 'mobile_number' => $mobileNumber, 'account_id' => (int) $accountId]
            );
            $this->db->query(
                "UPDATE junkshop_profiles
                 SET business_name = :business_name, owner_name = :profile_owner_name, complete_address = :complete_address,
                     latitude = :latitude, longitude = :longitude,
                     operating_schedule = :operating_schedule, business_permit_reference = :permit_reference,
                     gcash_account_name = NULLIF(TRIM(:gcash_name), ''), gcash_account_number = NULLIF(TRIM(:gcash_number), ''),
                     last_profile_edit = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE account_id = :account_id",
                [
                    'business_name' => $businessName,
                    'profile_owner_name' => $ownerName,
                    'complete_address' => $completeAddress,
                    'latitude' => $latitude === null ? null : (float) $latitude,
                    'longitude' => $longitude === null ? null : (float) $longitude,
                    'operating_schedule' => $operatingSchedule,
                    'permit_reference' => $permitReference,
                    'gcash_name' => trim((string) $gcashAccountName),
                    'gcash_number' => trim((string) $gcashAccountNumber),
                    'account_id' => (int) $accountId,
                ]
            );
            $this->db->commit();
            return ['success' => true, 'message' => 'Profile updated successfully.'];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Junkshop profile update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update profile'];
        }
    }

    public function updateJunkshopAvailability(int $accountId, bool $isAvailable): array
    {
        try {
            $this->db->beginTransaction();
            $profile = $this->db->query(
                "SELECT jp.is_available
                 FROM junkshop_profiles jp
                 JOIN accounts a ON a.id = jp.account_id
                 WHERE jp.account_id = :account_id AND a.role_id = (SELECT id FROM roles WHERE name = 'junkshop')
                 LIMIT 1 FOR UPDATE",
                ['account_id' => $accountId]
            )->fetch();
            if (!$profile) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Junkshop account not found.'];
            }

            $this->db->query(
                "UPDATE junkshop_profiles
                 SET is_available = :is_available, updated_at = CURRENT_TIMESTAMP
                 WHERE account_id = :account_id",
                ['is_available' => $isAvailable ? 1 : 0, 'account_id' => $accountId]
            );

            $cancelledCount = 0;
            if (!$isAvailable) {
                $pendingRequests = $this->db->query(
                    "SELECT id, current_status
                     FROM pickup_requests
                     WHERE junkshop_id = :junkshop_id
                       AND current_status IN ('Pending Request', 'Pending', 'Matched')
                     FOR UPDATE",
                    ['junkshop_id' => $accountId]
                )->fetchAll();

                $cancelStatement = $this->db->query(
                    "UPDATE pickup_requests
                     SET current_status = 'Cancelled',
                         cancellation_reason = :cancellation_reason,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE junkshop_id = :junkshop_id
                       AND current_status IN ('Pending Request', 'Pending', 'Matched')",
                    [
                        'cancellation_reason' => 'Shop changed operational status to Unavailable',
                        'junkshop_id' => $accountId,
                    ]
                );
                $cancelledCount = $cancelStatement->rowCount();

                foreach ($pendingRequests as $request) {
                    StatusLogger::logChange(
                        (int) $request['id'],
                        (string) $request['current_status'],
                        'Cancelled',
                        'Junkshop',
                        $accountId
                    );
                }
            }

            $this->db->commit();
            $message = $isAvailable
                ? 'Your junkshop is now available.'
                : 'Your shop status is now Unavailable, and ' . $cancelledCount . ' pending pickup request' . ($cancelledCount === 1 ? '' : 's') . ' were automatically cancelled.';
            return [
                'success' => true,
                'message' => $message,
                'is_available' => $isAvailable,
                'cancelled_count' => $cancelledCount,
            ];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            error_log('Junkshop availability update error: ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Unable to update shop availability.'];
        }
    }

    public function getSellerTransactionHistory(int $sellerId, string $sortOrder = 'DESC'): array
    {
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        return $this->db->query(
            'SELECT t.id, t.pickup_request_id, t.junkshop_id, COALESCE(jp.business_name, junkshop.full_name, \'Junkshop\') AS junkshop_name, COALESCE(SUM(tm.actual_weight_kg), t.actual_weight_kg) AS actual_weight_kg, pr.final_recyclable_value, pr.pickup_collection_fee AS pickup_fee, pr.ecopick_service_fee, pr.final_amount_paid AS final_seller_amount, pr.payment_method, pr.payment_status, t.payment_confirmed_at, pp.id AS payment_proof_id, t.completed_at, pr.booking_reference, pr.current_status, pr.pickup_address, pr.preferred_pickup_date, pr.preferred_pickup_time FROM transactions t JOIN pickup_requests pr ON pr.id = t.pickup_request_id LEFT JOIN transaction_materials tm ON tm.transaction_id = t.id AND tm.accepted = 1 LEFT JOIN junkshop_profiles jp ON jp.account_id = t.junkshop_id LEFT JOIN accounts junkshop ON junkshop.id = t.junkshop_id LEFT JOIN payment_proofs pp ON pp.transaction_id = t.id AND pp.proof_status IN (\'Submitted\', \'Approved\') WHERE t.seller_id = :seller_id GROUP BY t.id, pr.id, jp.account_id, junkshop.id, pp.id ORDER BY t.completed_at ' . $sortOrder . ', pp.uploaded_at ' . $sortOrder,
            ['seller_id' => $sellerId]
        )->fetchAll();
    }

    public function getJunkshopAssignments(int $junkshopId): array
    {
        try {
            return $this->db->query(
                'SELECT pr.id AS pickup_request_id, pr.id AS assignment_id, pr.booking_reference, pr.current_status, pr.contact_number, COALESCE(NULLIF(pr.contact_number, \'\'), NULLIF(a.mobile_number, \'\'), \'\') AS seller_mobile, pr.pickup_address, pr.preferred_pickup_date, pr.preferred_pickup_time, pr.created_at, a.full_name AS seller_name, COALESCE(SUM(pri.estimated_weight), 0) AS estimated_total_weight, GROUP_CONCAT(CONCAT(rm.material_name, " (", FORMAT(pri.estimated_weight, 2), " kg)") ORDER BY rm.material_name SEPARATOR ", ") AS materials_summary, GROUP_CONCAT(CONCAT(pri.id, ":", rm.material_name, ":", FORMAT(pri.estimated_weight, 2)) ORDER BY rm.material_name SEPARATOR "|") AS settlement_items, CASE WHEN pr.current_status = :pending_status THEN :matched_status ELSE pr.current_status END AS assignment_status, NULL AS distance_km, NULL AS assigned_at, NULL AS responded_at FROM pickup_requests pr JOIN accounts a ON a.id = pr.seller_account_id LEFT JOIN pickup_request_items pri ON pri.pickup_request_id = pr.id AND pri.is_removed = 0 LEFT JOIN recyclable_materials rm ON rm.id = pri.material_id WHERE pr.junkshop_id = :junkshop_id AND pr.current_status IN (:pending_status, :pending_legacy_status, :matched_status, :accepted_status, :scheduled_status, :for_pickup_status, :completed_status, :cancelled_status) GROUP BY pr.id ORDER BY pr.created_at DESC',
                [
                    'junkshop_id' => $junkshopId,
                    'pending_status' => 'Pending Request',
                    'pending_legacy_status' => 'Pending',
                    'matched_status' => 'Matched',
                    'accepted_status' => 'Accepted',
                    'scheduled_status' => 'Scheduled',
                    'for_pickup_status' => 'For Pickup',
                    'completed_status' => 'Completed',
                    'cancelled_status' => 'Cancelled',
                ]
            )->fetchAll();
        } catch (Throwable $e) {
            error_log($e->getMessage());
            return [];
        }
    }

    private function getJunkshopRequestStatuses(): array
    {
        return [
            'Pending Request',
            'Pending',
            'Requested',
            'Matched',
            'Accepted',
            'Scheduled',
            'For Pickup',
            'Completed',
            'Cancelled',
            'Cancelled by Seller',
        ];
    }

    public function getJunkshopRequestCount(int $junkshopId): int
    {
        $statuses = ['Pending Request', 'Pending', 'Requested', 'Matched'];
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $params = array_merge([$junkshopId], $statuses);

        return (int) $this->db->query(
            'SELECT COUNT(*)
             FROM pickup_requests pr
             WHERE pr.junkshop_id = ? AND pr.current_status IN (' . $placeholders . ')',
            $params
        )->fetchColumn();
    }

    public function getPendingJunkshopRequests(int $junkshopId): array
    {
        $statuses = $this->getJunkshopRequestStatuses();
        $statusPlaceholders = implode(',', array_fill(0, count($statuses), '?'));
        return $this->db->query(
            'SELECT
                pr.id AS pickup_request_id,
                pr.id AS assignment_id,
                pr.booking_reference,
                pr.current_status,
                pr.contact_number,
                COALESCE(NULLIF(pr.contact_number, \'\'), NULLIF(seller.mobile_number, \'\'), \'\') AS seller_mobile,
                pr.pickup_address,
                pr.seller_lat,
                pr.seller_lng,
                pr.preferred_pickup_date,
                pr.preferred_pickup_time,
                pr.confirmed_pickup_date,
                pr.confirmed_pickup_time,
                pr.approximate_distance_km AS distance_km,
                pr.pickup_fee,
                pr.created_at,
                                pr.updated_at,
                     (SELECT DATE_FORMAT(MAX(bsh.changed_at), \'%b %d, %Y at %h:%i %p\')
                                 FROM booking_status_history bsh
                                 WHERE bsh.pickup_request_id = pr.id
                         AND bsh.new_status = \'Completed\') AS formatted_completed_at,
                     (SELECT DATE_FORMAT(MAX(bsh.changed_at), \'%b %d, %Y at %h:%i %p\')
                                 FROM booking_status_history bsh
                                 WHERE bsh.pickup_request_id = pr.id
                         AND bsh.new_status IN (\'Cancelled\', \'Cancelled by Seller\')) AS formatted_cancelled_at,
                seller.full_name AS seller_name,
                sp.address AS seller_address,
                sp.barangay AS seller_barangay,
                COALESCE(SUM(pri.estimated_weight), 0) AS estimated_total_weight,
                COALESCE(NULLIF(SUM(pri.actual_weight), 0), (SELECT SUM(tm.actual_weight_kg) FROM transaction_materials tm JOIN transactions tx ON tx.id = tm.transaction_id WHERE tx.pickup_request_id = pr.id AND tm.accepted = 1), 0) AS actual_weight,
                GROUP_CONCAT(CONCAT(rm.material_name, " (", FORMAT(pri.estimated_weight, 2), " kg)") ORDER BY rm.material_name SEPARATOR ", ") AS materials_summary,
                GROUP_CONCAT(CONCAT(pri.id, ":", rm.material_name, ":", FORMAT(pri.estimated_weight, 2), ":", FORMAT(COALESCE(jmp.buying_price, 0), 2), ":", IF(jmp.material_id IS NULL, 0, 1)) ORDER BY rm.material_name SEPARATOR "|") AS settlement_items,
                COALESCE((SELECT config_value FROM fee_configurations WHERE config_key = \'ecopick_service_fee_pct\' LIMIT 1), 5.00) AS service_fee_pct,
                CASE pr.current_status
                    WHEN \'Pending Request\' THEN \'Pending\'
                    WHEN \'Pending\' THEN \'Pending\'
                    WHEN \'Requested\' THEN \'Pending\'
                    WHEN \'Accepted\' THEN \'Accepted\'
                    WHEN \'Scheduled\' THEN \'Scheduled\'
                    WHEN \'For Pickup\' THEN \'For Pickup\'
                    WHEN \'Completed\' THEN \'Completed\'
                    ELSE pr.current_status
                END AS assignment_status
            FROM pickup_requests pr
            LEFT JOIN accounts seller ON seller.id = pr.seller_account_id
            LEFT JOIN sellers sp ON sp.account_id = seller.id
            LEFT JOIN pickup_request_items pri ON pri.pickup_request_id = pr.id AND pri.is_removed = 0
            LEFT JOIN recyclable_materials rm ON rm.id = pri.material_id
            LEFT JOIN junkshop_material_prices jmp ON jmp.junkshop_account_id = pr.junkshop_id AND jmp.material_id = pri.material_id AND jmp.available = 1
                WHERE pr.junkshop_id = ?
                        AND pr.current_status IN (' . $statusPlaceholders . ')
                GROUP BY pr.id, seller.id, sp.id
                    ORDER BY pr.created_at DESC, pr.id DESC',
            array_merge([$junkshopId], $statuses)
        )->fetchAll();
    }

    public function getJunkshopCompletedTransactions(int $junkshopId, string $sortOrder = 'DESC'): array
    {
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        return $this->db->query(
            'SELECT t.id, t.pickup_request_id, t.seller_id, seller.full_name AS seller_fullname, pr.pickup_address, COALESCE(SUM(tm.actual_weight_kg), t.actual_weight_kg) AS actual_weight_kg, pr.final_recyclable_value, pr.pickup_collection_fee AS pickup_fee, pr.ecopick_service_fee, pr.final_amount_paid AS final_seller_amount, t.transaction_commission, pr.payment_method, pr.payment_status, t.payment_confirmed_at, pp.id AS payment_proof_id, t.completed_at, pr.booking_reference, GROUP_CONCAT(DISTINCT CONCAT(rm.material_name, \' (\', FORMAT(tm.actual_weight_kg, 2), \' kg x ₱\', FORMAT(tm.buying_price_per_kg, 2), \')\') ORDER BY rm.material_name SEPARATOR \', \') AS materials_summary, GROUP_CONCAT(DISTINCT CONCAT(rm.material_name, \'|||\', FORMAT(tm.actual_weight_kg, 2), \'|||\', COALESCE(tm.`condition`, \'\')) ORDER BY rm.material_name SEPARATOR \'~~~\') AS material_details FROM transactions t JOIN pickup_requests pr ON pr.id = t.pickup_request_id JOIN accounts seller ON seller.id = t.seller_id LEFT JOIN transaction_materials tm ON tm.transaction_id = t.id AND tm.accepted = 1 LEFT JOIN recyclable_materials rm ON rm.id = tm.material_id LEFT JOIN payment_proofs pp ON pp.transaction_id = t.id AND pp.proof_status IN (\'Submitted\', \'Approved\') WHERE t.junkshop_id = :junkshop_id GROUP BY t.id, pr.id, seller.id, pp.id ORDER BY t.completed_at ' . $sortOrder . ', pp.uploaded_at ' . $sortOrder,
            ['junkshop_id' => $junkshopId]
        )->fetchAll();
    }

    public function getSellerCompletedTransactions(int $sellerId, string $sortOrder = 'DESC'): array
    {
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        return $this->db->query(
            'SELECT t.id, t.pickup_request_id, t.junkshop_id, COALESCE(jp.business_name, junkshop.full_name, \'Junkshop\') AS junkshop_name, COALESCE(SUM(tm.actual_weight_kg), t.actual_weight_kg) AS actual_weight_kg, pr.final_recyclable_value, pr.pickup_collection_fee AS pickup_fee, pr.ecopick_service_fee, pr.final_amount_paid AS final_seller_amount, pr.payment_method, pr.payment_status, t.payment_confirmed_at, pp.id AS payment_proof_id, t.completed_at, pr.booking_reference, GROUP_CONCAT(DISTINCT CONCAT(rm.material_name, \' (\', FORMAT(tm.actual_weight_kg, 2), \' kg)\') ORDER BY rm.material_name SEPARATOR \', \') AS materials_summary, GROUP_CONCAT(DISTINCT CONCAT(rm.material_name, \'|||\', FORMAT(tm.actual_weight_kg, 2), \'|||\', COALESCE(tm.`condition`, \'\')) ORDER BY rm.material_name SEPARATOR \'~~~\') AS material_details FROM transactions t JOIN pickup_requests pr ON pr.id = t.pickup_request_id LEFT JOIN transaction_materials tm ON tm.transaction_id = t.id AND tm.accepted = 1 LEFT JOIN recyclable_materials rm ON rm.id = tm.material_id LEFT JOIN junkshop_profiles jp ON jp.account_id = t.junkshop_id LEFT JOIN accounts junkshop ON junkshop.id = t.junkshop_id LEFT JOIN payment_proofs pp ON pp.transaction_id = t.id AND pp.proof_status IN (\'Submitted\', \'Approved\') WHERE t.seller_id = :seller_id GROUP BY t.id, pr.id, jp.account_id, junkshop.id, pp.id ORDER BY t.completed_at ' . $sortOrder . ', pp.uploaded_at ' . $sortOrder,
            ['seller_id' => $sellerId]
        )->fetchAll();
    }

    public function getActiveBookings(int $userId, string $role): array
    {
        $statuses = ['Matched', 'Accepted', 'Scheduled', 'For Pickup'];
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));

        if ($role === 'seller') {
            $sql = 'SELECT pr.id, pr.booking_reference, pr.current_status, pr.pickup_address, pr.preferred_pickup_date, pr.preferred_pickup_time, pr.created_at FROM pickup_requests pr WHERE pr.seller_account_id = :user_id AND pr.current_status IN (' . $placeholders . ') ORDER BY pr.updated_at DESC';
            $params = [$userId];
            foreach ($statuses as $status) {
                $params[] = $status;
            }
            return $this->db->query($sql, $params)->fetchAll();
        }

        if ($role === 'junkshop') {
            $sql = 'SELECT pr.id, pr.booking_reference, pr.current_status, pr.pickup_address, pr.preferred_pickup_date, pr.preferred_pickup_time, pr.created_at FROM pickup_requests pr WHERE pr.junkshop_id = :user_id AND pr.current_status IN (' . $placeholders . ') ORDER BY pr.updated_at DESC';
            $params = [$userId];
            foreach ($statuses as $status) {
                $params[] = $status;
            }
            return $this->db->query($sql, $params)->fetchAll();
        }

        return [];
    }

}
