<?php
/**
 * Platform analytics service for admin reporting and dashboard metrics.
 */

class PlatformAnalytics
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Return summary metrics for a date range.
     *
     * @return array<string, float|int>
     */
    public function getPlatformSummary(string $startDate, string $endDate): array
    {
        $summary = [
            'total_completed_transactions' => 0,
            'total_weight_collected_kg' => 0.0,
            'total_ecopick_revenue' => 0.0,
            'pending_junkshop_approvals' => 0,
        ];

        try {
            $transactionRow = $this->db->query(
                'SELECT COUNT(*) AS total_completed_transactions, COALESCE(SUM(actual_weight_kg), 0) AS total_weight_collected_kg, COALESCE(SUM(ecopick_service_fee + transaction_commission), 0) AS total_ecopick_revenue FROM transactions WHERE completed_at BETWEEN :start_date AND :end_date',
                [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]
            )->fetch();

            if ($transactionRow) {
                $summary['total_completed_transactions'] = (int) ($transactionRow['total_completed_transactions'] ?? 0);
                $summary['total_weight_collected_kg'] = (float) ($transactionRow['total_weight_collected_kg'] ?? 0.0);
                $summary['total_ecopick_revenue'] = (float) ($transactionRow['total_ecopick_revenue'] ?? 0.0);
            }

            $approvalRow = $this->db->query(
                'SELECT COUNT(*) AS pending_junkshop_approvals FROM junkshop_profiles WHERE approval_status = :approval_status',
                ['approval_status' => 'pending']
            )->fetch();

            if ($approvalRow) {
                $summary['pending_junkshop_approvals'] = (int) ($approvalRow['pending_junkshop_approvals'] ?? 0);
            }
        } catch (Throwable $e) {
            error_log('Platform analytics error: ' . $e->getMessage());
        }

        return $summary;
    }
}
