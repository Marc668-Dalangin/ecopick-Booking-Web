<?php
/**
 * Booking lifecycle controller.
 *
 * Handles the final booking status transitions and transaction settlement logic.
 */

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/FeeCalculator.php';

class BookingLifecycleController
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function schedulePickup(int $pickupRequestId, int $junkshopAccountId, string $scheduledDate, string $scheduledTime): array
    {
        $pickupRequest = $this->getPickupRequestById($pickupRequestId, $junkshopAccountId, 'Accepted');
        if ($pickupRequest === null) {
            return ['success' => false, 'message' => 'Pickup request not found.'];
        }

        if (($pickupRequest['current_status'] ?? '') !== 'Accepted') {
            return ['success' => false, 'message' => 'Only accepted pickup requests can be scheduled.'];
        }

        $scheduledDate = trim($scheduledDate);
        $scheduledTime = trim($scheduledTime);
        $timezone = new DateTimeZone('Asia/Manila');
        $date = DateTime::createFromFormat('!Y-m-d', $scheduledDate, $timezone);
        $time = DateTime::createFromFormat('!H:i', $scheduledTime, $timezone);
        if (!$time || $time->format('H:i') !== $scheduledTime) {
            $time = null;
            foreach (['!g:i A', '!h:i A'] as $timeFormat) {
                $candidate = DateTime::createFromFormat($timeFormat, strtoupper($scheduledTime), $timezone);
                if ($candidate && $candidate->format($timeFormat === '!g:i A' ? 'g:i A' : 'h:i A') === strtoupper($scheduledTime)) {
                    $time = $candidate;
                    break;
                }
            }
        }
        if (!$date || $date->format('Y-m-d') !== $scheduledDate || $date < new DateTime('today', $timezone) || !$time) {
            return ['success' => false, 'message' => 'A valid pickup schedule is required.'];
        }
        $confirmedTime = $time->format('H:i:s');

        try {
            $statement = $this->db->query(
                'UPDATE pickup_requests SET confirmed_pickup_date = :confirmed_date, confirmed_pickup_time = :confirmed_time, current_status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :pickup_request_id AND current_status = :expected_status',
                [
                    'confirmed_date' => $scheduledDate,
                    'confirmed_time' => $confirmedTime,
                    'status' => 'Scheduled',
                    'pickup_request_id' => $pickupRequestId,
                    'expected_status' => 'Accepted',
                ]
            );

            if ($statement->rowCount() === 0) {
                return ['success' => false, 'message' => 'Unable to schedule the pickup.'];
            }

            StatusLogger::logChange($pickupRequestId, 'Accepted', 'Scheduled', 'Junkshop', $junkshopAccountId);

            return ['success' => true, 'message' => 'Pickup scheduled successfully.'];
        } catch (Throwable $e) {
            error_log('Schedule pickup error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to schedule the pickup.'];
        }
    }

    public function markForPickup(int $pickupRequestId, int $junkshopAccountId): array
    {
        $pickupRequest = $this->getPickupRequestById($pickupRequestId, $junkshopAccountId, 'Scheduled');
        if ($pickupRequest === null) {
            return ['success' => false, 'message' => 'Pickup request not found.'];
        }

        if (($pickupRequest['current_status'] ?? '') !== 'Scheduled') {
            return ['success' => false, 'message' => 'Only scheduled pickups can be marked as for pickup.'];
        }

        try {
            $statement = $this->db->query(
                'UPDATE pickup_requests SET current_status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :pickup_request_id AND current_status = :expected_status',
                [
                    'status' => 'For Pickup',
                    'pickup_request_id' => $pickupRequestId,
                    'expected_status' => 'Scheduled',
                ]
            );

            if ($statement->rowCount() === 0) {
                return ['success' => false, 'message' => 'Unable to update the pickup request status.'];
            }

            StatusLogger::logChange($pickupRequestId, 'Scheduled', 'For Pickup', 'Junkshop', $junkshopAccountId);

            return ['success' => true, 'message' => 'Pickup is now marked as for pickup.'];
        } catch (Throwable $e) {
            error_log('Mark for pickup error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update the pickup status.'];
        }
    }

    public function previewFinalSettlement(int $pickupRequestId, int $junkshopAccountId, array $materialSettlements): array
    {
        $pickupRequest = $this->getPickupRequestById($pickupRequestId, $junkshopAccountId, 'For Pickup');
        if ($pickupRequest === null) {
            return ['success' => false, 'message' => 'Pickup request not found.'];
        }

        if (($pickupRequest['current_status'] ?? '') !== 'For Pickup') {
            return ['success' => false, 'message' => 'Only pickups marked as For Pickup can be settled.'];
        }

        $assignment = $this->getAcceptedAssignment($pickupRequestId, $junkshopAccountId);
        if ($assignment === null) {
            return ['success' => false, 'message' => 'No accepted junkshop assignment was found for this request.'];
        }

        $preparedMaterials = $this->prepareMaterialSettlements($pickupRequestId, (int) $assignment['junkshop_id'], $materialSettlements);
        if (!$preparedMaterials['success']) {
            return $preparedMaterials;
        }
        $pickupFee = $this->getPickupFeeConfig();
        $settlement = FeeCalculator::calculateMaterialSettlement($preparedMaterials['materials'], $pickupFee);

        return ['success' => true, 'message' => 'Final settlement preview ready.', 'data' => $settlement];
    }

    public function completeTransaction(int $pickupRequestId, int $junkshopAccountId, array $materialSettlements, float $pickupCollectionFee, string $paymentMethod = 'Cash', string $paymentStatus = 'Paid', string $paymentReference = '', string $materialConditionNotes = ''): array
    {
        $pickupRequest = $this->getPickupRequestById($pickupRequestId, $junkshopAccountId, 'For Pickup');
        if ($pickupRequest === null) {
            return ['success' => false, 'message' => 'Pickup request not found.'];
        }

        if (($pickupRequest['current_status'] ?? '') !== 'For Pickup') {
            return ['success' => false, 'message' => 'Only pickups marked as For Pickup can be completed.'];
        }

        $assignment = $this->getAcceptedAssignment($pickupRequestId, $junkshopAccountId);
        if ($assignment === null) {
            return ['success' => false, 'message' => 'No accepted junkshop assignment was found for this request.'];
        }

        $preparedMaterials = $this->prepareMaterialSettlements($pickupRequestId, (int) $assignment['junkshop_id'], $materialSettlements);
        if (!$preparedMaterials['success']) {
            return $preparedMaterials;
        }
        if ($paymentMethod !== 'Cash' || !in_array($paymentStatus, ['Unpaid', 'Paid'], true)) {
            return ['success' => false, 'message' => 'A valid payment method and payment status are required.'];
        }

        $settlement = FeeCalculator::calculateMaterialSettlement($preparedMaterials['materials'], $pickupCollectionFee);

        try {
            $this->db->beginTransaction();
            $rowCount = $this->db->query(
                'INSERT INTO transactions (pickup_request_id, junkshop_id, seller_id, actual_weight_kg, material_condition_notes, final_recyclable_value, pickup_fee, ecopick_service_fee, final_seller_amount, transaction_commission, payment_method, payment_status, payment_reference) VALUES (:pickup_request_id, :junkshop_id, :seller_id, :actual_weight_kg, :material_condition_notes, :final_recyclable_value, :pickup_fee, :ecopick_service_fee, :final_seller_amount, :transaction_commission, :payment_method, :payment_status, :payment_reference)',
                [
                    'pickup_request_id' => $pickupRequestId,
                    'junkshop_id' => (int) $assignment['junkshop_id'],
                    'seller_id' => (int) $pickupRequest['seller_account_id'],
                    'actual_weight_kg' => number_format((float) $settlement['actual_weight_kg'], 2, '.', ''),
                    'material_condition_notes' => $materialConditionNotes !== '' ? trim($materialConditionNotes) : null,
                    'final_recyclable_value' => number_format((float) $settlement['final_recyclable_value'], 2, '.', ''),
                    'pickup_fee' => number_format((float) $settlement['pickup_fee'], 2, '.', ''),
                    'ecopick_service_fee' => number_format((float) $settlement['ecopick_service_fee'], 2, '.', ''),
                    'final_seller_amount' => number_format((float) $settlement['final_seller_amount'], 2, '.', ''),
                    'transaction_commission' => number_format((float) $settlement['transaction_commission'], 2, '.', ''),
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'payment_reference' => $paymentReference !== '' ? trim($paymentReference) : null,
                ]
            )->rowCount();

            if ($rowCount === 0) {
                throw new RuntimeException('Unable to record the completed transaction.');
            }

            $transactionId = (int) $this->db->getPDO()->lastInsertId();
            foreach ($preparedMaterials['materials'] as $material) {
                $this->db->query(
                    'UPDATE pickup_request_items SET actual_weight = :actual_weight, material_condition = :material_condition WHERE id = :pickup_request_item_id AND pickup_request_id = :pickup_request_id',
                    [
                        'actual_weight' => number_format((float) $material['actual_weight_kg'], 2, '.', ''),
                        'material_condition' => trim((string) ($material['material_condition'] ?? '')),
                        'pickup_request_item_id' => $material['pickup_request_item_id'],
                        'pickup_request_id' => $pickupRequestId,
                    ]
                );
                $this->db->query(
                    'INSERT INTO transaction_materials (transaction_id, pickup_request_item_id, material_id, actual_weight_kg, buying_price_per_kg, final_material_value, accepted) VALUES (:transaction_id, :pickup_request_item_id, :material_id, :actual_weight_kg, :buying_price_per_kg, :final_material_value, :accepted)',
                    [
                        'transaction_id' => $transactionId,
                        'pickup_request_item_id' => $material['pickup_request_item_id'],
                        'material_id' => $material['material_id'],
                        'actual_weight_kg' => number_format($material['actual_weight_kg'], 2, '.', ''),
                        'buying_price_per_kg' => number_format($material['buying_price_per_kg'], 2, '.', ''),
                        'final_material_value' => number_format($material['final_material_value'], 2, '.', ''),
                        'accepted' => $material['accepted'] ? 1 : 0,
                    ]
                );
            }

            {
                $statusStatement = $this->db->query(
                    'UPDATE pickup_requests SET current_status = :status, final_recyclable_value = :final_recyclable_value, pickup_collection_fee = :pickup_collection_fee, ecopick_service_fee = :ecopick_service_fee, final_amount_paid = :final_amount_paid, payment_method = :payment_method, payment_status = :payment_status, updated_at = CURRENT_TIMESTAMP WHERE id = :pickup_request_id AND current_status = :expected_status',
                    [
                        'status' => 'Completed',
                        'final_recyclable_value' => number_format((float) $settlement['final_recyclable_value'], 2, '.', ''),
                        'pickup_collection_fee' => number_format((float) $settlement['pickup_fee'], 2, '.', ''),
                        'ecopick_service_fee' => number_format((float) $settlement['ecopick_service_fee'], 2, '.', ''),
                        'final_amount_paid' => number_format((float) $settlement['final_seller_amount'], 2, '.', ''),
                        'payment_method' => $paymentMethod,
                        'payment_status' => $paymentStatus,
                        'pickup_request_id' => $pickupRequestId,
                        'expected_status' => 'For Pickup',
                    ]
                );

                if ($statusStatement->rowCount() === 0) {
                    throw new RuntimeException('Unable to update the pickup request status.');
                }

                StatusLogger::logChange($pickupRequestId, 'For Pickup', 'Completed', 'Junkshop', (int) $assignment['junkshop_id']);
            }

            $this->db->query(
                'INSERT INTO transaction_payments (transaction_id, payment_purpose, amount, payment_method, payment_status, payment_reference, paid_at, confirmed_at, recorded_by_account_id) VALUES (:transaction_id, :payment_purpose, :amount, :payment_method, :payment_status, :payment_reference, IF(:paid_status = 1, CURRENT_TIMESTAMP, NULL), IF(:confirmed_status = 1, CURRENT_TIMESTAMP, NULL), :recorded_by_account_id)',
                [
                    'transaction_id' => $transactionId,
                    'payment_purpose' => 'Seller Payout',
                    'amount' => number_format((float) $settlement['final_seller_amount'], 2, '.', ''),
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'payment_reference' => $paymentReference !== '' ? trim($paymentReference) : null,
                    'paid_status' => in_array($paymentStatus, ['Paid', 'Confirmed'], true) ? 1 : 0,
                    'confirmed_status' => $paymentStatus === 'Confirmed' ? 1 : 0,
                    'recorded_by_account_id' => $junkshopAccountId,
                ]
            );
            $this->db->query(
                'INSERT INTO transaction_payments (transaction_id, payment_purpose, amount, payment_method, payment_status, payment_reference, paid_at, confirmed_at, recorded_by_account_id) VALUES (:transaction_id, :payment_purpose, :amount, :payment_method, :payment_status, :payment_reference, IF(:paid_status = 1, CURRENT_TIMESTAMP, NULL), IF(:confirmed_status = 1, CURRENT_TIMESTAMP, NULL), :recorded_by_account_id)',
                [
                    'transaction_id' => $transactionId,
                    'payment_purpose' => 'EcoPick Commission',
                    'amount' => number_format((float) $settlement['transaction_commission'], 2, '.', ''),
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'payment_reference' => $paymentReference !== '' ? trim($paymentReference) : null,
                    'paid_status' => in_array($paymentStatus, ['Paid', 'Confirmed'], true) ? 1 : 0,
                    'confirmed_status' => $paymentStatus === 'Confirmed' ? 1 : 0,
                    'recorded_by_account_id' => $junkshopAccountId,
                ]
            );
            $this->db->commit();

            return [
                'success' => true,
                'message' => $paymentStatus === 'Unpaid' ? 'GCash settlement recorded and awaiting seller proof.' : 'Transaction completed and recorded successfully.',
                'data' => $settlement,
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            error_log('Complete transaction error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to complete the transaction.'];
        }
    }

    private function getPickupRequestById(int $pickupRequestId, int $junkshopAccountId, string $currentStatus = 'Accepted'): ?array
    {
        $row = $this->db->query(
            'SELECT pr.id, pr.booking_reference, pr.seller_account_id, pr.current_status, pr.confirmed_pickup_date, pr.confirmed_pickup_time, pr.pickup_address, pr.barangay, pr.preferred_pickup_date, pr.preferred_pickup_time, pr.photo_path, pr.notes, pr.created_at, pr.updated_at FROM pickup_requests pr WHERE pr.id = :pickup_request_id AND pr.junkshop_id = :junkshop_id AND pr.current_status = :current_status LIMIT 1',
            [
                'pickup_request_id' => $pickupRequestId,
                'junkshop_id' => $junkshopAccountId,
                'current_status' => $currentStatus,
            ]
        )->fetch();

        return $row ?: null;
    }

    private function getAcceptedAssignment(int $pickupRequestId, int $junkshopAccountId): ?array
    {
        $row = $this->db->query(
            'SELECT pr.id AS pickup_request_id, pr.junkshop_id, pr.current_status AS status FROM pickup_requests pr WHERE pr.id = :pickup_request_id AND pr.junkshop_id = :junkshop_id AND pr.current_status = :status LIMIT 1',
            [
                'pickup_request_id' => $pickupRequestId,
                'junkshop_id' => $junkshopAccountId,
                'status' => 'For Pickup',
            ]
        )->fetch();

        return $row ?: null;
    }

    private function prepareMaterialSettlements(int $pickupRequestId, int $junkshopId, array $submittedMaterials): array
    {
        $row = $this->db->query(
            'SELECT id AS pickup_request_item_id, material_id FROM pickup_request_items WHERE pickup_request_id = :pickup_request_id ORDER BY id ASC',
            ['pickup_request_id' => $pickupRequestId]
        )->fetchAll();
        if (empty($row) || empty($submittedMaterials)) {
            return ['success' => false, 'message' => 'Every requested material requires a settlement line.'];
        }

        $submittedByItem = [];
        foreach ($submittedMaterials as $material) {
            $itemId = (int) ($material['pickup_request_item_id'] ?? 0);
            if ($itemId > 0) {
                $submittedByItem[$itemId] = $material;
            }
        }

        $prepared = [];
        foreach ($row as $item) {
            $itemId = (int) $item['pickup_request_item_id'];
            if (!isset($submittedByItem[$itemId])) {
                return ['success' => false, 'message' => 'Every requested material requires a settlement line.'];
            }
            $submitted = $submittedByItem[$itemId];
            $materialCondition = trim((string) ($submitted['material_condition'] ?? ''));
            if ($materialCondition === '') {
                return ['success' => false, 'message' => 'A material condition is required for every assessed material.'];
            }
            $accepted = (bool) ($submitted['accepted'] ?? true);
            $weight = max(0.0, (float) ($submitted['actual_weight_kg'] ?? 0.0));
            $priceRow = $this->db->query(
                'SELECT buying_price FROM junkshop_material_prices WHERE junkshop_account_id = :junkshop_id AND material_id = :material_id AND available = 1 LIMIT 1',
                ['junkshop_id' => $junkshopId, 'material_id' => (int) $item['material_id']]
            )->fetch();
            if (!$priceRow && $accepted) {
                return ['success' => false, 'message' => 'A valid buying price is required for every accepted material.'];
            }
            if (!$accepted || $weight <= 0.0) {
                $accepted = false;
                $weight = 0.0;
            }
            $price = (float) ($priceRow['buying_price'] ?? 0.0);
            $prepared[] = [
                'pickup_request_item_id' => $itemId,
                'material_id' => (int) $item['material_id'],
                'actual_weight_kg' => $weight,
                'material_condition' => $materialCondition,
                'buying_price_per_kg' => $price,
                'final_material_value' => round($weight * $price, 2),
                'accepted' => $accepted,
            ];
        }

        if (!array_filter($prepared, static fn (array $material): bool => $material['accepted'])) {
            return ['success' => false, 'message' => 'At least one material must be accepted with a positive actual weight.'];
        }

        return ['success' => true, 'materials' => $prepared];
    }

    private function getPickupFeeConfig(): float
    {
        $row = $this->db->query(
            'SELECT config_value FROM fee_configurations WHERE config_key = :config_key LIMIT 1',
            ['config_key' => 'default_pickup_fee']
        )->fetch();

        if ($row === false || !isset($row['config_value'])) {
            return 50.00;
        }

        return (float) $row['config_value'];
    }
}
