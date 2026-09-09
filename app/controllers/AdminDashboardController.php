<?php
/**
 * Admin dashboard controller.
 */

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../services/PlatformAnalytics.php';

class AdminDashboardController
{
    private Database $db;
    private PlatformAnalytics $analytics;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->analytics = new PlatformAnalytics();
    }

    public function ensureAdminAccess(): void
    {
        if (!Auth::check()) {
            http_response_code(401);
            throw new RuntimeException('Authentication required.');
        }

        if (Auth::userRole() !== 'admin') {
            http_response_code(403);
            throw new RuntimeException('Admin access required.');
        }
    }

    public function getPlatformSummary(string $startDate, string $endDate): array
    {
        $this->ensureAdminAccess();
        return $this->analytics->getPlatformSummary($startDate, $endDate);
    }

    public function getAllUsers(?string $roleFilter = null): array
    {
        $this->ensureAdminAccess();

        $sql = 'SELECT a.id, a.email, a.full_name, a.account_role, a.account_status, a.mobile_number, a.created_at FROM accounts a';
        $params = [];

        if ($roleFilter !== null && trim($roleFilter) !== '') {
            $sql .= ' WHERE a.account_role = :role_filter';
            $params['role_filter'] = trim($roleFilter);
        }

        $sql .= ' ORDER BY a.created_at DESC';

        return $this->db->query($sql, $params)->fetchAll();
    }

    public function getPlatformBookings(?string $statusFilter = null): array
    {
        $this->ensureAdminAccess();

        $sql = 'SELECT pr.id, pr.booking_reference, pr.current_status, pr.seller_account_id, a.full_name AS seller_name, pr.pickup_address, pr.barangay, pr.preferred_pickup_date, pr.preferred_pickup_time, pr.created_at FROM pickup_requests pr JOIN accounts a ON a.id = pr.seller_account_id';
        $params = [];

        if ($statusFilter !== null && trim($statusFilter) !== '') {
            $sql .= ' WHERE pr.current_status = :status_filter';
            $params['status_filter'] = trim($statusFilter);
        }

        $sql .= ' ORDER BY pr.created_at DESC';

        return $this->db->query($sql, $params)->fetchAll();
    }

    public function getCompletedTransactions(): array
    {
        $this->ensureAdminAccess();

        $sql = 'SELECT t.id, t.pickup_request_id, t.junkshop_id, t.seller_id, t.actual_weight_kg, t.final_recyclable_value, t.pickup_fee, t.ecopick_service_fee, t.final_seller_amount, t.transaction_commission, t.completed_at, pr.booking_reference FROM transactions t JOIN pickup_requests pr ON pr.id = t.pickup_request_id ORDER BY t.completed_at DESC';

        return $this->db->query($sql)->fetchAll();
    }
}
