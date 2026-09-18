<?php
/**
 * Booking lifecycle controller.
 *
 * Handles the final booking status transitions and transaction settlement logic.
 */

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/FeeCalculator.php';
require_once __DIR__ . '/../../includes/philsms_service.php';

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

    public function markForPickup(int $pickupRequestId, int $junkshopAccountId, ?string $collectorFirstName = null, ?string $collectorLastName = null): array
    {
        $pickupRequest = $this->getPickupRequestById($pickupRequestId, $junkshopAccountId, 'Scheduled');
        if ($pickupRequest === null) {
            return ['success' => false, 'message' => 'Pickup request not found.'];
        }

        if (($pickupRequest['current_status'] ?? '') !== 'Scheduled') {
            return ['success' => false, 'message' => 'Only scheduled pickups can be marked as for pickup.'];
        }

        if ($collectorFirstName === null && $collectorLastName === null) {
            $collectorName = 'ECOPICK COLLECTOR';
        } else {
            $collectorFirstName = trim((string) $collectorFirstName);
            $collectorLastName = trim((string) $collectorLastName);
            if (!preg_match('/^[A-Z]+$/', $collectorFirstName) || !preg_match('/^[A-Z]+$/', $collectorLastName)) {
                return ['success' => false, 'message' => 'Collector first and last names must contain uppercase letters only.'];
            }
            $collectorName = $collectorFirstName . ' ' . $collectorLastName;
        }

        try {
            $statement = $this->db->query(
                'UPDATE pickup_requests SET current_status = :status, collector_name = :collector_name, sms_status = :sms_status, sms_error_message = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = :pickup_request_id AND current_status = :expected_status',
                [
                    'status' => 'For Pickup',
                    'collector_name' => $collectorName,
                    'sms_status' => 'Pending',
                    'pickup_request_id' => $pickupRequestId,
                    'expected_status' => 'Scheduled',
                ]
            );

            if ($statement->rowCount() === 0) {
                return ['success' => false, 'message' => 'Unable to update the pickup request status.'];
            }

            StatusLogger::logChange($pickupRequestId, 'Scheduled', 'For Pickup', 'Junkshop', $junkshopAccountId);

            $netAmount = $this->getEstimatedNetAmount($pickupRequestId);
            $smsResult = sendPhilSMS(
                (string) ($pickupRequest['seller_mobile'] ?? $pickupRequest['contact_number'] ?? ''),
                buildPickupSmsMessage(
                    (string) ($pickupRequest['booking_reference'] ?? ('ECP-' . $pickupRequestId)),
                    $collectorName,
                    (string) ($pickupRequest['junkshop_business_name'] ?? 'EcoPick Partner Junkshop'),
                    $netAmount
                ),
                $this->db->getPDO()
            );
            $this->db->query(
                'UPDATE pickup_requests SET sms_status = :sms_status, sms_error_message = :sms_error_message WHERE id = :pickup_request_id',
                [
                    'sms_status' => $smsResult['status'],
                    'sms_error_message' => $smsResult['success'] ? null : $this->formatSmsDiagnostics($smsResult),
                    'pickup_request_id' => $pickupRequestId,
                ]
            );

            return [
                'success' => true,
                'message' => $smsResult['success']
                    ? 'Status updated to For Pickup and SMS notification sent successfully to +' . $smsResult['recipient'] . ' via PhilSMS!'
                    : 'Status updated to For Pickup, but SMS delivery failed: ' . $smsResult['message'],
                'sms_status' => $smsResult['status'],
                'sms_message' => $smsResult['message'],
                'sms_recipient' => $smsResult['recipient'],
                'seller_lat' => isset($pickupRequest['seller_lat']) ? (float) $pickupRequest['seller_lat'] : null,
                'seller_lng' => isset($pickupRequest['seller_lng']) ? (float) $pickupRequest['seller_lng'] : null,
                'seller_address' => (string) ($pickupRequest['pickup_address'] ?? ''),
            ];
        } catch (Throwable $e) {
            error_log('Mark for pickup error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update the pickup status.'];
        }
    }

    private function formatSmsDiagnostics(array $smsResult): string
    {
        $diagnostics = [
            'message' => (string) ($smsResult['message'] ?? 'PhilSMS request failed.'),
            'http_code' => (int) ($smsResult['http_code'] ?? 0),
            'curl_error' => (string) ($smsResult['curl_error'] ?? ''),
            'raw_response' => (string) ($smsResult['raw_response'] ?? ''),
        ];
        return json_encode($diagnostics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $diagnostics['message'];
    }

    private function getEstimatedNetAmount(int $pickupRequestId): float
    {
        $row = $this->db->query(
            'SELECT COALESCE(SUM(pri.estimated_weight * COALESCE(jmp.buying_price, 0)), 0) AS gross_amount, pr.pickup_fee, COALESCE((SELECT config_value FROM fee_configurations WHERE config_key = \'ecopick_service_fee_pct\' LIMIT 1), 5.00) AS service_fee_pct FROM pickup_requests pr LEFT JOIN pickup_request_items pri ON pri.pickup_request_id = pr.id AND pri.is_removed = 0 LEFT JOIN junkshop_material_prices jmp ON jmp.junkshop_account_id = pr.junkshop_id AND jmp.material_id = pri.material_id AND jmp.available = 1 WHERE pr.id = :pickup_request_id GROUP BY pr.id',
            ['pickup_request_id' => $pickupRequestId]
        )->fetch();
        if (!$row) {
            return 0.0;
        }
        $grossAmount = (float) $row['gross_amount'];
        return max(0.0, $grossAmount - (float) $row['pickup_fee'] - ($grossAmount * (float) $row['service_fee_pct'] / 100));
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

    public function completeTransaction(int $pickupRequestId, int $junkshopAccountId, array $materialSettlements, string $paymentMethod = 'Cash', string $paymentStatus = 'Paid', string $paymentReference = '', string $materialConditionNotes = '', ?float $actualPricePerKg = null): array
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

        $preparedMaterials = $this->prepareMaterialSettlements($pickupRequestId, (int) $assignment['junkshop_id'], $materialSettlements, $actualPricePerKg);
        if (!$preparedMaterials['success']) {
            return $preparedMaterials;
        }
        $totalActualWeight = array_sum(array_map(
            static fn (array $material): float => $material['accepted'] ? (float) $material['actual_weight_kg'] : 0.0,
            $preparedMaterials['materials']
        ));
        if ($totalActualWeight < 3) {
            return ['success' => false, 'message' => 'The total actual weight must be at least 3 kg to complete this transaction.'];
        }
        if ($paymentMethod !== 'Cash' || !in_array($paymentStatus, ['Unpaid', 'Paid'], true)) {
            return ['success' => false, 'message' => 'A valid payment method and payment status are required.'];
        }

        $storedPickupFee = max(0.0, (float) ($pickupRequest['pickup_fee'] ?? 0.0));
        $settlement = FeeCalculator::calculateMaterialSettlement($preparedMaterials['materials'], $storedPickupFee);

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
                    'pickup_fee' => number_format($storedPickupFee, 2, '.', ''),
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
                    'UPDATE pickup_request_items SET actual_weight = :actual_weight, material_condition = :material_condition, is_removed = :is_removed WHERE id = :pickup_request_item_id AND pickup_request_id = :pickup_request_id',
                    [
                        'actual_weight' => number_format((float) $material['actual_weight_kg'], 2, '.', ''),
                        'material_condition' => trim((string) ($material['material_condition'] ?? '')),
                        'is_removed' => $material['accepted'] ? 0 : 1,
                        'pickup_request_item_id' => $material['pickup_request_item_id'],
                        'pickup_request_id' => $pickupRequestId,
                    ]
                );
                if (!$material['accepted']) {
                    continue;
                }
                $this->db->query(
                    'INSERT INTO transaction_materials (transaction_id, pickup_request_item_id, material_id, actual_weight_kg, buying_price_per_kg, final_material_value, `condition`, accepted) VALUES (:transaction_id, :pickup_request_item_id, :material_id, :actual_weight_kg, :buying_price_per_kg, :final_material_value, :condition, :accepted)',
                    [
                        'transaction_id' => $transactionId,
                        'pickup_request_item_id' => $material['pickup_request_item_id'],
                        'material_id' => $material['material_id'],
                        'actual_weight_kg' => number_format($material['actual_weight_kg'], 2, '.', ''),
                        'buying_price_per_kg' => number_format($material['buying_price_per_kg'], 2, '.', ''),
                        'final_material_value' => number_format($material['final_material_value'], 2, '.', ''),
                        'condition' => $material['material_condition'] !== '' ? $material['material_condition'] : null,
                        'accepted' => $material['accepted'] ? 1 : 0,
                    ]
                );
            }
            $submittedItemIds = array_map(
                static fn (array $material): int => (int) ($material['pickup_request_item_id'] ?? 0),
                $preparedMaterials['materials']
            );
            $omittedItemIds = array_values(array_diff($this->getPickupRequestItemIds($pickupRequestId), $submittedItemIds));
            if ($omittedItemIds !== []) {
                $placeholders = implode(',', array_fill(0, count($omittedItemIds), '?'));
                $this->db->query(
                    'UPDATE pickup_request_items SET is_removed = 1, actual_weight = 0 WHERE pickup_request_id = ? AND id IN (' . $placeholders . ')',
                    array_merge([$pickupRequestId], $omittedItemIds)
                );
            }

            {
                $statusStatement = $this->db->query(
                    'UPDATE pickup_requests SET current_status = :status, admin_viewed_report = 0, final_recyclable_value = :final_recyclable_value, pickup_collection_fee = :pickup_collection_fee, ecopick_service_fee = :ecopick_service_fee, final_amount_paid = :final_amount_paid, payment_method = :payment_method, payment_status = :payment_status, updated_at = CURRENT_TIMESTAMP WHERE id = :pickup_request_id AND current_status = :expected_status',
                    [
                        'status' => 'Completed',
                        'final_recyclable_value' => number_format((float) $settlement['final_recyclable_value'], 2, '.', ''),
                        'pickup_collection_fee' => number_format($storedPickupFee, 2, '.', ''),
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
            'SELECT pr.id, pr.booking_reference, pr.seller_account_id, pr.current_status, pr.contact_number, COALESCE(NULLIF(pr.contact_number, \'\'), NULLIF(seller.mobile_number, \'\'), \'\') AS seller_mobile, COALESCE(NULLIF(junkshop.business_name, \'\'), NULLIF(junkshop_account.full_name, \'\'), \'EcoPick Partner Junkshop\') AS junkshop_business_name, pr.pickup_fee, pr.confirmed_pickup_date, pr.confirmed_pickup_time, pr.pickup_address, pr.seller_lat, pr.seller_lng, pr.preferred_pickup_date, pr.preferred_pickup_time, pr.photo_path, pr.notes, pr.created_at, pr.updated_at FROM pickup_requests pr JOIN accounts seller ON seller.id = pr.seller_account_id LEFT JOIN junkshop_profiles junkshop ON junkshop.account_id = pr.junkshop_id LEFT JOIN accounts junkshop_account ON junkshop_account.id = pr.junkshop_id WHERE pr.id = :pickup_request_id AND pr.junkshop_id = :junkshop_id AND pr.current_status = :current_status LIMIT 1',
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

    private function prepareMaterialSettlements(int $pickupRequestId, int $junkshopId, array $submittedMaterials, ?float $actualPricePerKg = null): array
    {
        $row = $this->db->query(
            'SELECT id AS pickup_request_item_id, material_id FROM pickup_request_items WHERE pickup_request_id = :pickup_request_id ORDER BY id ASC',
            ['pickup_request_id' => $pickupRequestId]
        )->fetchAll();
        if (empty($row) || empty($submittedMaterials)) {
            return ['success' => false, 'message' => 'Every requested material requires a settlement line.'];
        }

        $requestedItems = [];
        foreach ($row as $item) {
            $requestedItems[(int) $item['pickup_request_item_id']] = $item;
        }

        $submittedByItem = [];
        foreach ($submittedMaterials as $material) {
            $itemId = (int) ($material['pickup_request_item_id'] ?? 0);
            if ($itemId > 0 && isset($requestedItems[$itemId])) {
                $submittedByItem[$itemId] = $material;
            }
        }

        $prepared = [];
        foreach ($submittedByItem as $itemId => $submitted) {
            $item = $requestedItems[$itemId];
            $materialCondition = trim((string) ($submitted['material_condition'] ?? ''));
            $accepted = (bool) ($submitted['accepted'] ?? true);
            $weight = max(0.0, (float) ($submitted['actual_weight_kg'] ?? 0.0));
            $priceRow = $this->db->query(
                'SELECT buying_price FROM junkshop_material_prices WHERE junkshop_account_id = :junkshop_id AND material_id = :material_id AND available = 1 LIMIT 1',
                ['junkshop_id' => $junkshopId, 'material_id' => (int) $item['material_id']]
            )->fetch();
            if (!$accepted || $weight <= 0.0) {
                $accepted = false;
                $weight = 0.0;
            }
            $price = $priceRow
                ? max(0.0, (float) $priceRow['buying_price'])
                : (array_key_exists('buying_price_per_kg', $submitted)
                    ? max(0.0, (float) $submitted['buying_price_per_kg'])
                    : ($actualPricePerKg !== null ? max(0.0, $actualPricePerKg) : 0.0));
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

    private function getPickupRequestItemIds(int $pickupRequestId): array
    {
        $rows = $this->db->query(
            'SELECT id FROM pickup_request_items WHERE pickup_request_id = :pickup_request_id',
            ['pickup_request_id' => $pickupRequestId]
        )->fetchAll();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    private function getPickupFeeConfig(): float
    {
        $row = $this->db->query(
            'SELECT config_value FROM fee_configurations WHERE config_key = :config_key LIMIT 1',
            ['config_key' => 'default_pickup_fee']
        )->fetch();

        if ($row === false || !isset($row['config_value'])) {
            return 0.00;
        }

        return (float) $row['config_value'];
    }
}
