<?php

class JunkshopFeeService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getMaximumFeeThreshold(): float
    {
        $value = $this->db->query(
            'SELECT setting_value FROM system_settings WHERE setting_key = :setting_key LIMIT 1',
            ['setting_key' => 'max_junkshop_fee_threshold']
        )->fetchColumn();

        return max(0.0, (float) ($value === false ? 5000.00 : $value));
    }

    public function getOutstandingSummary(int $junkshopId): array
    {
        $row = $this->db->query(
            'SELECT
                COALESCE(SUM(t.ecopick_service_fee), 0) AS service_fee_subtotal,
                COALESCE(SUM(t.transaction_commission), 0) AS commission_subtotal,
                COUNT(t.id) AS completed_transactions
             FROM transactions t
             JOIN pickup_requests pr ON pr.id = t.pickup_request_id
             WHERE t.junkshop_id = :junkshop_id
               AND pr.current_status = :completed_status',
            ['junkshop_id' => $junkshopId, 'completed_status' => 'Completed']
        )->fetch() ?: [];

        $serviceFeeSubtotal = (float) ($row['service_fee_subtotal'] ?? 0);
        $commissionSubtotal = (float) ($row['commission_subtotal'] ?? 0);
        $approvedPayments = (float) ($this->db->query(
            "SELECT COALESCE(SUM(amount_deducted), 0)
             FROM junkshop_fee_payments
             WHERE junkshop_id = :junkshop_id AND status = 'Approved'",
            ['junkshop_id' => $junkshopId]
        )->fetchColumn() ?: 0);
        $maximumAllowed = $this->getMaximumFeeThreshold();
        $totalOutstanding = max(0.0, $serviceFeeSubtotal + $commissionSubtotal - $approvedPayments);

        return [
            'service_fee_subtotal' => $serviceFeeSubtotal,
            'commission_subtotal' => $commissionSubtotal,
            'approved_fee_payments' => $approvedPayments,
            'total_outstanding' => $totalOutstanding,
            'maximum_allowed' => $maximumAllowed,
            'remaining_allowance' => max(0.0, $maximumAllowed - $totalOutstanding),
            'is_locked' => $totalOutstanding >= $maximumAllowed,
            'completed_transactions' => (int) ($row['completed_transactions'] ?? 0),
        ];
    }

    public function getCompletedTransactions(int $junkshopId): array
    {
        return $this->db->query(
            'SELECT
                t.id AS transaction_id,
                pr.booking_reference,
                seller.full_name AS seller_name,
                t.completed_at,
                COALESCE(t.payment_method, pr.payment_method) AS payment_method,
                COALESCE(t.reference_number, t.payment_reference, pr.reference_number) AS reference_number,
                t.actual_weight_kg,
                t.final_recyclable_value,
                t.pickup_fee,
                t.ecopick_service_fee,
                t.transaction_commission,
                t.final_seller_amount
             FROM transactions t
             JOIN pickup_requests pr ON pr.id = t.pickup_request_id
             JOIN accounts seller ON seller.id = t.seller_id
             WHERE t.junkshop_id = :junkshop_id
               AND pr.current_status = :completed_status
             ORDER BY t.completed_at DESC, t.id DESC',
            ['junkshop_id' => $junkshopId, 'completed_status' => 'Completed']
        )->fetchAll();
    }
}
