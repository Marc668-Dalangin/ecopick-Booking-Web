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
            $stmt = $this->db->call('sp_get_admin_dashboard_stats');
            $row = $stmt->fetch();
            if ($stmt instanceof PDOStatement) {
                $stmt->closeCursor();
            }
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
                        WHERE completed_weight.current_status = :weight_status), 0) AS weight_collected_kg,
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
        return $this->fetchAll('sp_list_pending_junkshops');
    }

    public function listSellers()
    {
        return $this->fetchAll('sp_list_sellers');
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
        return $this->fetchAll('sp_list_approved_junkshops');
    }

    public function updateJunkshopApproval($accountId, $status)
    {
        $status = in_array($status, ['approved', 'rejected'], true) ? $status : null;
        if ($status === null) {
            return ['success' => false, 'message' => 'Invalid approval status'];
        }

        try {
            $stmt = $this->db->call('sp_update_junkshop_approval', [$accountId, $status]);
            $row = $stmt->fetch();
            if ($stmt instanceof PDOStatement) {
                $stmt->closeCursor();
            }

            $message = $row['p_result'] ?? 'success';
            if ($message === 'success') {
                return ['success' => true, 'message' => 'Junkshop status updated successfully'];
            }

            return ['success' => false, 'message' => $message];
        } catch (Exception $e) {
            error_log('Junkshop approval update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update junkshop status'];
        }
    }

    public function getSellerProfile($accountId)
    {
        return $this->fetchOne('sp_get_seller_profile', [$accountId]);
    }

    public function updateSellerProfile($accountId, $fullName, $mobileNumber, $address, $barangay)
    {
        try {
            $stmt = $this->db->call('sp_update_seller_profile', [$accountId, $fullName, $mobileNumber, $address, $barangay]);
            $row = $stmt->fetch();
            if ($stmt instanceof PDOStatement) {
                $stmt->closeCursor();
            }

            $message = $row['p_result'] ?? 'success';
            return ['success' => $message === 'success', 'message' => $message === 'success' ? 'Profile updated successfully.' : $message];
        } catch (Exception $e) {
            error_log('Seller profile update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update profile'];
        }
    }

    public function getJunkshopProfile($accountId)
    {
        return $this->fetchOne('sp_get_junkshop_profile', [$accountId]);
    }

    public function updateJunkshopProfile($accountId, $businessName, $ownerName, $mobileNumber, $completeAddress, $operatingSchedule, $permitReference, $gcashAccountName = '', $gcashAccountNumber = '')
    {
        try {
            $stmt = $this->db->call('sp_update_junkshop_profile', [
                $accountId,
                $businessName,
                $ownerName,
                $mobileNumber,
                $completeAddress,
                $operatingSchedule,
                $permitReference,
                trim((string) $gcashAccountName),
                trim((string) $gcashAccountNumber),
            ]);
            $row = $stmt->fetch();
            if ($stmt instanceof PDOStatement) {
                $stmt->closeCursor();
            }

            $message = $row['p_result'] ?? 'success';
            return ['success' => $message === 'success', 'message' => $message === 'success' ? 'Profile updated successfully.' : $message];
        } catch (Exception $e) {
            error_log('Junkshop profile update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update profile'];
        }
    }

    public function getSellerTransactionHistory(int $sellerId): array
    {
        return $this->db->query(
            'SELECT t.id, t.pickup_request_id, t.junkshop_id, t.actual_weight_kg, t.final_recyclable_value, t.pickup_fee, t.ecopick_service_fee, t.final_seller_amount, t.payment_method, t.payment_status, t.payment_confirmed_at, pp.id AS payment_proof_id, t.completed_at, pr.booking_reference, pr.current_status, pr.pickup_address, pr.barangay, pr.preferred_pickup_date, pr.preferred_pickup_time FROM transactions t JOIN pickup_requests pr ON pr.id = t.pickup_request_id LEFT JOIN payment_proofs pp ON pp.transaction_id = t.id AND pp.proof_status IN (\'Submitted\', \'Approved\') WHERE t.seller_id = :seller_id ORDER BY t.completed_at DESC, pp.uploaded_at DESC',
            ['seller_id' => $sellerId]
        )->fetchAll();
    }

    public function getJunkshopAssignments(int $junkshopId): array
    {
        return $this->db->query(
            'SELECT ja.id AS assignment_id, ja.pickup_request_id, pr.booking_reference, pr.current_status, pr.pickup_location_name, pr.pickup_address, pr.barangay, pr.preferred_pickup_date, pr.preferred_pickup_time, pr.created_at, a.full_name AS seller_name, COALESCE(SUM(pri.estimated_weight), 0) AS estimated_total_weight, GROUP_CONCAT(CONCAT(rm.material_name, " (", FORMAT(pri.estimated_weight, 2), " kg)") ORDER BY rm.material_name SEPARATOR ", ") AS materials_summary, GROUP_CONCAT(CONCAT(pri.id, ":", rm.material_name, ":", FORMAT(pri.estimated_weight, 2)) ORDER BY rm.material_name SEPARATOR "|") AS settlement_items, MAX(t.id) AS transaction_id, MAX(t.payment_method) AS payment_method, MAX(t.payment_status) AS payment_status, MAX(pp.id) AS payment_proof_id, ja.status AS assignment_status, ja.distance_km, ja.assigned_at, ja.responded_at FROM junkshop_assignments ja JOIN pickup_requests pr ON pr.id = ja.pickup_request_id JOIN accounts a ON a.id = pr.seller_account_id LEFT JOIN pickup_request_items pri ON pri.pickup_request_id = pr.id LEFT JOIN recyclable_materials rm ON rm.id = pri.material_id LEFT JOIN transactions t ON t.pickup_request_id = pr.id LEFT JOIN payment_proofs pp ON pp.transaction_id = t.id AND pp.proof_status IN (\'Submitted\', \'Approved\') WHERE ja.junkshop_id = :junkshop_id GROUP BY ja.id ORDER BY ja.assigned_at DESC',
            ['junkshop_id' => $junkshopId]
        )->fetchAll();
    }

    public function getJunkshopCompletedTransactions(int $junkshopId): array
    {
        return $this->db->query(
            'SELECT t.id, t.pickup_request_id, t.seller_id, t.actual_weight_kg, t.final_recyclable_value, t.pickup_fee, t.ecopick_service_fee, t.final_seller_amount, t.transaction_commission, t.payment_method, t.payment_status, t.payment_confirmed_at, pp.id AS payment_proof_id, t.completed_at, pr.booking_reference, GROUP_CONCAT(CONCAT(rm.material_name, \' (\', FORMAT(tm.actual_weight_kg, 2), \' kg x \\u20b1\', FORMAT(tm.buying_price_per_kg, 2), \')\') ORDER BY rm.material_name SEPARATOR \', \') AS materials_summary FROM transactions t JOIN pickup_requests pr ON pr.id = t.pickup_request_id LEFT JOIN transaction_materials tm ON tm.transaction_id = t.id LEFT JOIN recyclable_materials rm ON rm.id = tm.material_id LEFT JOIN payment_proofs pp ON pp.transaction_id = t.id AND pp.proof_status IN (\'Submitted\', \'Approved\') WHERE t.junkshop_id = :junkshop_id GROUP BY t.id, pp.id ORDER BY t.completed_at DESC, pp.uploaded_at DESC',
            ['junkshop_id' => $junkshopId]
        )->fetchAll();
    }

    public function getSellerCompletedTransactions(int $sellerId): array
    {
        return $this->db->query(
            'SELECT t.id, t.pickup_request_id, t.junkshop_id, t.actual_weight_kg, t.final_recyclable_value, t.pickup_fee, t.ecopick_service_fee, t.final_seller_amount, t.payment_method, t.payment_status, t.payment_confirmed_at, pp.id AS payment_proof_id, t.completed_at, pr.booking_reference, GROUP_CONCAT(CONCAT(rm.material_name, \' (\', FORMAT(tm.actual_weight_kg, 2), \' kg)\') ORDER BY rm.material_name SEPARATOR \', \') AS materials_summary FROM transactions t JOIN pickup_requests pr ON pr.id = t.pickup_request_id LEFT JOIN transaction_materials tm ON tm.transaction_id = t.id LEFT JOIN recyclable_materials rm ON rm.id = tm.material_id LEFT JOIN payment_proofs pp ON pp.transaction_id = t.id AND pp.proof_status IN (\'Submitted\', \'Approved\') WHERE t.seller_id = :seller_id GROUP BY t.id, pp.id ORDER BY t.completed_at DESC, pp.uploaded_at DESC',
            ['seller_id' => $sellerId]
        )->fetchAll();
    }

    public function getActiveBookings(int $userId, string $role): array
    {
        $statuses = ['Matched', 'Accepted', 'Scheduled', 'For Pickup'];
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));

        if ($role === 'seller') {
            $sql = 'SELECT pr.id, pr.booking_reference, pr.current_status, pr.pickup_address, pr.barangay, pr.preferred_pickup_date, pr.preferred_pickup_time, pr.created_at FROM pickup_requests pr WHERE pr.seller_account_id = :user_id AND pr.current_status IN (' . $placeholders . ') ORDER BY pr.updated_at DESC';
            $params = [$userId];
            foreach ($statuses as $status) {
                $params[] = $status;
            }
            return $this->db->query($sql, $params)->fetchAll();
        }

        if ($role === 'junkshop') {
            $sql = 'SELECT pr.id, pr.booking_reference, pr.current_status, pr.pickup_address, pr.barangay, pr.preferred_pickup_date, pr.preferred_pickup_time, pr.created_at FROM pickup_requests pr JOIN junkshop_assignments ja ON ja.pickup_request_id = pr.id WHERE ja.junkshop_id = :user_id AND pr.current_status IN (' . $placeholders . ') ORDER BY pr.updated_at DESC';
            $params = [$userId];
            foreach ($statuses as $status) {
                $params[] = $status;
            }
            return $this->db->query($sql, $params)->fetchAll();
        }

        return [];
    }

    private function fetchAll($procedureName, $params = [])
    {
        try {
            $stmt = $this->db->call($procedureName, $params);
            $rows = $stmt->fetchAll();
            if ($stmt instanceof PDOStatement) {
                $stmt->closeCursor();
            }
            return $rows;
        } catch (Exception $e) {
            error_log('Stored procedure fetch error: ' . $e->getMessage());
            return [];
        }
    }

    private function fetchOne($procedureName, $params = [])
    {
        try {
            $stmt = $this->db->call($procedureName, $params);
            $row = $stmt->fetch();
            if ($stmt instanceof PDOStatement) {
                $stmt->closeCursor();
            }
            return $row ?: null;
        } catch (Exception $e) {
            error_log('Stored procedure read error: ' . $e->getMessage());
            return null;
        }
    }
}
