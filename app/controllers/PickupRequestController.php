<?php
/**
 * Phase 4A seller pickup request controller.
 */

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../services/MatchingEngine.php';
require_once __DIR__ . '/../../includes/philsms_service.php';

class PickupRequestController
{
    private const MINIMUM_PICKUP_WEIGHT_KG = 5.0;
    private const MINIMUM_PICKUP_WEIGHT_MESSAGE = 'Minimum pickup weight requirement is 5 kg. Please enter 5 kg or more to schedule a pickup.';

    private $db;
    private $photoDirectory;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->photoDirectory = __DIR__ . '/../../storage/pickup-photos';
    }

    public function createRequest($sellerAccountId, $data, $photoFile = null)
    {
        $normalized = $this->normalizeRequestData($data);
        $errors = $this->validateRequestData($normalized);

        if (empty($errors)) {
            $duplicateMaterials = count($normalized['items']) !== count(array_unique(array_column($normalized['items'], 'material_id')));
            if ($duplicateMaterials) {
                $errors[] = 'Please choose each recyclable material only once.';
            }
        }

        $photoPath = null;
        if (empty($errors) && $photoFile !== null && ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $photoResult = $this->storePhoto($photoFile);
            if (!$photoResult['success']) {
                $errors[] = $photoResult['message'];
            } else {
                $photoPath = $photoResult['path'];
            }
        }

        if (!empty($errors)) {
            $message = in_array(self::MINIMUM_PICKUP_WEIGHT_MESSAGE, $errors, true)
                ? self::MINIMUM_PICKUP_WEIGHT_MESSAGE
                : 'Please correct the highlighted fields.';
            return ['success' => false, 'message' => $message, 'validation_errors' => $errors];
        }

        $totalEstimatedWeight = array_sum(array_map(
            static fn (array $item): float => (float) $item['estimated_weight'],
            $normalized['items']
        ));
        if ($totalEstimatedWeight < self::MINIMUM_PICKUP_WEIGHT_KG) {
            return ['success' => false, 'message' => self::MINIMUM_PICKUP_WEIGHT_MESSAGE, 'validation_errors' => []];
        }

        try {
            $this->db->beginTransaction();
            $seller = $this->db->query(
                "SELECT id FROM accounts WHERE id = :seller_id AND account_role = 'seller' AND account_status = 'active' LIMIT 1",
                ['seller_id' => (int) $sellerAccountId]
            )->fetch();
            if (!$seller) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Only active sellers can create pickup requests.', 'validation_errors' => []];
            }

            $activeRequest = $this->db->query(
                "SELECT id FROM pickup_requests
                 WHERE seller_account_id = :seller_id AND junkshop_id = :junkshop_id
                   AND current_status IN ('Pending Request', 'Accepted', 'Scheduled', 'For Pickup') LIMIT 1",
                ['seller_id' => (int) $sellerAccountId, 'junkshop_id' => (int) $normalized['junkshop_id']]
            )->fetch();
            if ($activeRequest) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'You already have an active pickup request with this junkshop.', 'validation_errors' => []];
            }

            $junkshop = $this->db->query(
                "SELECT jp.is_available
                 FROM junkshop_profiles jp
                 JOIN accounts a ON a.id = jp.account_id
                 WHERE jp.account_id = :junkshop_id
                   AND a.account_status = 'active'
                   AND jp.approval_status = 'approved'
                   AND (jp.partnership_expires_at IS NULL OR jp.partnership_expires_at > CURRENT_TIMESTAMP)
                 LIMIT 1",
                ['junkshop_id' => (int) $normalized['junkshop_id']]
            )->fetch();
            if (!$junkshop || (int) $junkshop['is_available'] !== 1) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'This junkshop is currently unavailable for pickup requests.', 'validation_errors' => []];
            }

            foreach ($normalized['items'] as $item) {
                $material = $this->db->query(
                    'SELECT id FROM recyclable_materials WHERE id = :material_id AND is_active = 1 LIMIT 1',
                    ['material_id' => (int) $item['material_id']]
                )->fetch();
                if (!$material || (float) $item['estimated_weight'] <= 0) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'Each selected material must have a positive estimated weight.', 'validation_errors' => []];
                }
            }

            $junkshopLocation = $this->db->query(
                'SELECT latitude, longitude FROM junkshop_profiles WHERE account_id = :junkshop_id LIMIT 1',
                ['junkshop_id' => (int) $normalized['junkshop_id']]
            )->fetch();
            if (!$junkshopLocation || !is_numeric($junkshopLocation['latitude']) || !is_numeric($junkshopLocation['longitude'])) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'The selected junkshop has not configured a pickup location yet.', 'validation_errors' => []];
            }

            $calculatedDistance = $this->calculateDistanceInKm(
                (float) $normalized['seller_lat'],
                (float) $normalized['seller_lng'],
                (float) $junkshopLocation['latitude'],
                (float) $junkshopLocation['longitude']
            );
            $perKmRate = $this->getPerKmRate();
            $pickupFee = $this->calculatePickupFee($calculatedDistance, $perKmRate);
            $materialTotal = 0.0;
            foreach ($normalized['items'] as $item) {
                $price = $this->db->query(
                    'SELECT buying_price FROM junkshop_material_prices WHERE junkshop_account_id = :junkshop_id AND material_id = :material_id AND available = 1 LIMIT 1',
                    ['junkshop_id' => (int) $normalized['junkshop_id'], 'material_id' => (int) $item['material_id']]
                )->fetchColumn();
                $materialTotal += (float) ($price ?: 0) * (float) $item['estimated_weight'];
            }
            $serviceFeePct = (float) (FeeCalculator::getConfigs()['ecopick_service_fee_pct'] ?? (FeeCalculator::DEFAULT_SERVICE_FEE_PCT * 100));
            $serviceFee = round($materialTotal * ($serviceFeePct / 100), 2);
            $totalEstimatedAmount = round($materialTotal - $pickupFee - $serviceFee, 2);

            $this->db->query(
                "INSERT INTO pickup_requests
                    (booking_reference, seller_account_id, junkshop_id, current_status, pickup_address,
                     contact_number, preferred_pickup_date, preferred_pickup_time, photo_path, notes,
                     approximate_distance_km, calculated_distance, pickup_fee, total_estimated_amount, seller_lat, seller_lng)
                 VALUES ('', :seller_id, :junkshop_id, 'Pending Request', :pickup_address,
                         :contact_number, :pickup_date, :pickup_time, :photo_path, :notes,
                         :distance_km, :calculated_distance, :pickup_fee, :total_estimated_amount, :seller_lat, :seller_lng)",
                [
                    'seller_id' => (int) $sellerAccountId,
                    'junkshop_id' => (int) $normalized['junkshop_id'],
                    'pickup_address' => $normalized['pickup_address'],
                    'contact_number' => $normalized['contact_number'],
                    'pickup_date' => $normalized['preferred_pickup_date'],
                    'pickup_time' => $normalized['preferred_pickup_time'],
                    'photo_path' => $photoPath,
                    'notes' => $normalized['notes'] !== '' ? $normalized['notes'] : null,
                    'distance_km' => $normalized['approximate_distance_km'],
                    'calculated_distance' => number_format($calculatedDistance, 2, '.', ''),
                    'pickup_fee' => number_format($pickupFee, 2, '.', ''),
                    'total_estimated_amount' => number_format($totalEstimatedAmount, 2, '.', ''),
                    'seller_lat' => $normalized['seller_lat'],
                    'seller_lng' => $normalized['seller_lng'],
                ]
            );
            $requestId = (int) $this->db->getPDO()->lastInsertId();
            $bookingReference = 'ECP-' . date('Ymd') . '-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT);
            $this->db->query(
                'UPDATE pickup_requests SET booking_reference = :booking_reference WHERE id = :request_id',
                ['booking_reference' => $bookingReference, 'request_id' => $requestId]
            );
            foreach ($normalized['items'] as $item) {
                $this->db->query(
                    'INSERT INTO pickup_request_items (pickup_request_id, material_id, estimated_weight, estimated_weight_kg) VALUES (:request_id, :material_id, :estimated_weight, :estimated_weight_kg)',
                    ['request_id' => $requestId, 'material_id' => (int) $item['material_id'], 'estimated_weight' => $item['estimated_weight'], 'estimated_weight_kg' => $item['estimated_weight']]
                );
            }
            $this->db->query(
                "INSERT INTO pickup_request_status_history (pickup_request_id, status, changed_by_account_id)
                 VALUES (:request_id, 'Pending Request', :seller_id)",
                ['request_id' => $requestId, 'seller_id' => (int) $sellerAccountId]
            );
            $this->db->commit();

            if ($requestId > 0) {
                StatusLogger::logChange($requestId, null, 'Pending Request', 'Seller', (int) $sellerAccountId);
                $materialIds = array_values(array_unique(array_map(static fn (array $item): int => (int) ($item['material_id'] ?? 0), $normalized['items'])));
                MatchingEngine::onNewPickupRequestCreated($requestId, $materialIds, (float) $normalized['approximate_distance_km']);
            }

            return [
                'success' => true,
                'message' => 'Pickup request submitted successfully.',
                'validation_errors' => [],
                'request' => [
                    'id' => $requestId,
                    'booking_reference' => $bookingReference,
                ],
            ];
        } catch (Exception $e) {
            if ($photoPath !== null) {
                $this->deletePhoto($photoPath);
            }
            error_log('Pickup request create error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to create the pickup request right now.', 'validation_errors' => []];
        }
    }

    public function listSellerRequests($sellerAccountId, ?string $selectedStatus = null, string $sortOrder = 'DESC')
    {
        $statusGroups = [
            'Pending' => ['Pending Request', 'Pending'],
            'Accepted' => ['Accepted'],
            'Declined' => ['Declined'],
            'Completed' => ['Completed'],
            'Cancelled' => ['Cancelled', 'Cancelled by Seller', 'Cancelled by Junkshop'],
        ];
        $selectedStatus = array_key_exists($selectedStatus ?? '', $statusGroups) ? $selectedStatus : null;
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        $params = ['seller_id' => (int) $sellerAccountId];
        $statusSql = '';
        if ($selectedStatus !== null) {
            $statusPlaceholders = [];
            foreach ($statusGroups[$selectedStatus] as $index => $status) {
                $placeholder = 'status_' . $index;
                $statusPlaceholders[] = ':' . $placeholder;
                $params[$placeholder] = $status;
            }
            $statusSql = ' AND pr.current_status IN (' . implode(', ', $statusPlaceholders) . ')';
        }

        return $this->db->query(
            "SELECT pr.id, pr.booking_reference, pr.current_status, pr.contact_number, pr.pickup_address,
                pr.preferred_pickup_date, pr.preferred_pickup_time, pr.confirmed_pickup_date,
                pr.confirmed_pickup_time, DATE_FORMAT(pr.confirmed_pickup_date, '%b %d, %Y') AS formatted_pickup_date,
                TIME_FORMAT(pr.confirmed_pickup_time, '%h:%i %p') AS formatted_pickup_time, pr.photo_path,
                pr.notes, pr.created_at, pr.updated_at, pr.current_status AS status,
                COALESCE(NULLIF(SUM(pri.actual_weight), 0), (SELECT SUM(tm.actual_weight_kg) FROM transaction_materials tm JOIN transactions tx ON tx.id = tm.transaction_id WHERE tx.pickup_request_id = pr.id AND tm.accepted = 1), 0) AS actual_weight,
                COALESCE(jp.business_name, junkshop.full_name, 'Junkshop') AS junkshop_name,
                COUNT(pri.id) AS item_count,
                COALESCE(SUM(COALESCE(pri.estimated_weight_kg, pri.estimated_weight)), 0) AS estimated_total_weight,
                GROUP_CONCAT(DISTINCT CONCAT(COALESCE(NULLIF(rm.name, ''), rm.material_name), ' (', FORMAT(COALESCE(pri.estimated_weight_kg, pri.estimated_weight), 2), ' kg)') ORDER BY COALESCE(NULLIF(rm.name, ''), rm.material_name) SEPARATOR ', ') AS materials_summary
             FROM pickup_requests pr
             LEFT JOIN pickup_request_items pri ON pri.pickup_request_id = pr.id AND pri.is_removed = 0
             LEFT JOIN recyclable_materials rm ON rm.id = pri.material_id
             LEFT JOIN junkshop_profiles jp ON jp.account_id = pr.junkshop_id
            LEFT JOIN accounts junkshop ON junkshop.id = pr.junkshop_id
             WHERE pr.seller_account_id = :seller_id" . $statusSql . "
             GROUP BY pr.id, jp.account_id, junkshop.id ORDER BY pr.created_at " . $sortOrder . ", pr.id " . $sortOrder,
            $params
        )->fetchAll();
    }

    public function getPendingRequestJunkshopIds(int $sellerAccountId): array
    {
        $rows = $this->db->query(
            "SELECT DISTINCT junkshop_id FROM pickup_requests WHERE seller_account_id = :seller_account_id AND current_status IN ('Pending Request', 'Accepted', 'Scheduled', 'For Pickup') AND junkshop_id IS NOT NULL ORDER BY junkshop_id ASC",
            [
                'seller_account_id' => $sellerAccountId,
            ]
        )->fetchAll();

        return array_values(array_filter(array_map(static fn (array $row): int => (int) ($row['junkshop_id'] ?? 0), $rows), static fn (int $junkshopId): bool => $junkshopId > 0));
    }

    public function getSellerRequestDetails($requestId, $sellerAccountId)
    {
        $rows = $this->db->query(
            "SELECT pr.id, pr.booking_reference, pr.seller_account_id, a.full_name AS seller_name, a.email AS seller_email,
                    pr.current_status, pr.contact_number, pr.pickup_address, pr.approximate_distance_km, pr.seller_lat, pr.seller_lng,
                    pr.preferred_pickup_date, pr.preferred_pickup_time, pr.confirmed_pickup_date, pr.confirmed_pickup_time,
                      DATE_FORMAT(pr.confirmed_pickup_date, '%b %d, %Y') AS formatted_pickup_date,
                      TIME_FORMAT(pr.confirmed_pickup_time, '%h:%i %p') AS formatted_pickup_time, pr.photo_path, pr.notes,
                      t.actual_weight_kg AS final_actual_weight,
                      t.final_recyclable_value,
                      CASE WHEN t.actual_weight_kg > 0 THEN t.final_recyclable_value / t.actual_weight_kg ELSE NULL END AS actual_price_per_kg,
                      t.pickup_fee AS final_pickup_fee,
                      t.ecopick_service_fee AS final_service_fee,
                      t.final_seller_amount AS final_net_amount,
                    pr.created_at, pr.updated_at, pri.id AS item_id, pri.material_id, COALESCE(NULLIF(rm.name, ''), rm.material_name) AS material_name, rm.category,
                    rm.unit_of_measure, COALESCE(pri.estimated_weight_kg, pri.estimated_weight) AS estimated_weight
             FROM pickup_requests pr
             JOIN accounts a ON a.id = pr.seller_account_id
                    JOIN pickup_request_items pri ON pri.pickup_request_id = pr.id AND pri.is_removed = 0
             JOIN recyclable_materials rm ON rm.id = pri.material_id
             LEFT JOIN transactions t ON t.pickup_request_id = pr.id
             WHERE pr.id = :request_id AND pr.seller_account_id = :seller_id AND pri.is_removed = 0
             ORDER BY rm.material_name ASC",
            ['request_id' => (int) $requestId, 'seller_id' => (int) $sellerAccountId]
        )->fetchAll();
        if (empty($rows)) {
            return null;
        }

        $history = $this->db->query(
            'SELECT bsh.id, bsh.previous_status, bsh.new_status, bsh.changed_at, bsh.responsible_party, bsh.user_id FROM booking_status_history bsh JOIN pickup_requests pr ON pr.id = bsh.pickup_request_id WHERE bsh.pickup_request_id = :request_id AND pr.seller_account_id = :seller_account_id ORDER BY bsh.changed_at ASC, bsh.id ASC',
            [
                'request_id' => (int) $requestId,
                'seller_account_id' => (int) $sellerAccountId,
            ]
        )->fetchAll();
        $request = $rows[0];
        $request['items'] = [];
        $itemIds = array_map(static fn (array $row): int => (int) $row['item_id'], $rows);
        $snapshotByItemId = [];
        if (!empty($itemIds)) {
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $snapshotRows = $this->db->query(
                'SELECT pri.id, COALESCE(tm.buying_price_per_kg, pri.estimated_buying_price_per_kg, CASE WHEN pr.current_status <> \'Pending Request\' THEN jmp.buying_price END) AS matched_price_per_kg, COALESCE(tm.final_material_value, pri.estimated_material_value, CASE WHEN pr.current_status <> \'Pending Request\' THEN ROUND(COALESCE(pri.estimated_weight_kg, pri.estimated_weight) * jmp.buying_price, 2) END) AS estimated_value, tm.final_material_value AS actual_value, COALESCE(tm.`condition`, pri.material_condition) AS material_condition, pri.estimated_buying_price_per_kg, pri.estimated_material_value, pri.estimate_snapshot_at FROM pickup_request_items pri JOIN pickup_requests pr ON pr.id = pri.pickup_request_id LEFT JOIN junkshop_material_prices jmp ON jmp.junkshop_account_id = pr.junkshop_id AND jmp.material_id = pri.material_id AND jmp.available = 1 LEFT JOIN transaction_materials tm ON tm.pickup_request_item_id = pri.id AND tm.accepted = 1 WHERE pri.id IN (' . $placeholders . ') AND pri.is_removed = 0 ORDER BY tm.id DESC',
                $itemIds
            )->fetchAll();
            foreach ($snapshotRows as $snapshotRow) {
                $snapshotByItemId[(int) $snapshotRow['id']] = $snapshotRow;
            }
        }
        $estimatedWeight = 0.0;
        $estimatedRecyclableValue = 0.0;
        foreach ($rows as $row) {
            $snapshot = $snapshotByItemId[(int) $row['item_id']] ?? [];
            $itemWeight = (float) $row['estimated_weight'];
            $matchedPrice = $snapshot['matched_price_per_kg'] ?? null;
            $estimatedWeight += $itemWeight;
            if ($matchedPrice !== null) {
                $estimatedRecyclableValue += $itemWeight * (float) $matchedPrice;
            }
            $request['items'][] = [
                'item_id' => (int) $row['item_id'],
                'material_condition' => $snapshot['material_condition'] ?? null,
                'material_id' => (int) $row['material_id'],
                'material_name' => $row['material_name'],
                'category' => $row['category'],
                'unit_of_measure' => $row['unit_of_measure'],
                'estimated_weight' => $row['estimated_weight'],
                'matched_price_per_kg' => $snapshot['matched_price_per_kg'] ?? null,
                'estimated_value' => $snapshot['estimated_value'] ?? null,
                'actual_value' => $snapshot['actual_value'] ?? null,
                'estimated_buying_price_per_kg' => $snapshot['estimated_buying_price_per_kg'] ?? null,
                'estimated_material_value' => $snapshot['estimated_material_value'] ?? null,
                'estimate_snapshot_at' => $snapshot['estimate_snapshot_at'] ?? null,
            ];
        }
        $feeConfigs = FeeCalculator::getConfigs();
        $pickupFee = (float) ($feeConfigs['default_pickup_fee'] ?? FeeCalculator::DEFAULT_PICKUP_FEE);
        $serviceFeePercentage = (float) ($feeConfigs['ecopick_service_fee_pct'] ?? (FeeCalculator::DEFAULT_SERVICE_FEE_PCT * 100));
        $estServiceFee = $estimatedRecyclableValue * ($serviceFeePercentage / 100);
        $request['estimated_recyclable_value'] = round($estimatedRecyclableValue, 2);
        $request['pickup_fee'] = round($pickupFee, 2);
        $request['ecopick_service_fee'] = round($estServiceFee, 2);
        $request['estimated_net_amount'] = round($estimatedRecyclableValue - $pickupFee - $estServiceFee, 2);
        $request['service_fee_percentage'] = round($serviceFeePercentage, 2);
        $request['status_history'] = $history;
        $request['formatted_pickup_date'] = $request['formatted_pickup_date'] ?? null;
        $request['formatted_pickup_time'] = $request['formatted_pickup_time'] ?? null;
        return $request;
    }

    public function getSellerRequestAssignmentSummary($requestId, $sellerAccountId): array
    {
        $row = $this->db->query(
            'SELECT pr.id AS assignment_id, pr.id AS pickup_request_id, pr.junkshop_id, CASE WHEN pr.current_status = :pending_status THEN :matched_status ELSE pr.current_status END AS assignment_status, pr.pickup_address, pr.preferred_pickup_date, pr.preferred_pickup_time, jp.business_name, jp.complete_address, jp.owner_name, a.full_name AS junkshop_contact_name FROM pickup_requests pr LEFT JOIN junkshop_profiles jp ON jp.account_id = pr.junkshop_id LEFT JOIN accounts a ON a.id = pr.junkshop_id WHERE pr.id = :pickup_request_id AND pr.seller_account_id = :seller_account_id LIMIT 1',
            [
                'pickup_request_id' => (int) $requestId,
                'seller_account_id' => (int) $sellerAccountId,
                'pending_status' => 'Pending Request',
                'matched_status' => 'Matched',
            ]
        )->fetch();

        return $row ?: [];
    }

    public function estimateRequestValue(array $items): array
    {
        $normalizedItems = [];
        foreach ($items as $item) {
            if (!isset($item['material_id']) || !isset($item['estimated_weight'])) {
                continue;
            }

            $materialId = (int) ($item['material_id'] ?? 0);
            $estimatedWeight = (float) ($item['estimated_weight'] ?? 0);
            if ($materialId > 0 && $estimatedWeight > 0) {
                $normalizedItems[] = ['material_id' => $materialId, 'estimated_weight' => $estimatedWeight];
            }
        }

        if (empty($normalizedItems)) {
            return [
                'estimated_recyclable_value' => 0.0,
                'pickup_fee' => 0.0,
                'ecopick_service_fee' => 0.0,
                'estimated_net_amount' => 0.0,
                'service_fee_pct' => 0.0,
                'total_weight_kg' => 0.0,
            ];
        }

        $totalWeight = 0.0;
        $materialPriceTotal = 0.0;
        foreach ($normalizedItems as $item) {
            $totalWeight += (float) $item['estimated_weight'];

            $priceRow = $this->db->query(
                'SELECT AVG(jmp.buying_price) AS average_buying_price FROM junkshop_material_prices jmp JOIN junkshop_profiles jp ON jp.account_id = jmp.junkshop_account_id WHERE jmp.material_id = :material_id AND jmp.available = 1 AND jp.approval_status = :approval_status AND (jp.partnership_expires_at IS NULL OR jp.partnership_expires_at > CURRENT_TIMESTAMP)',
                [
                    'material_id' => $item['material_id'],
                    'approval_status' => 'approved',
                ]
            )->fetch();

            $materialPriceTotal += (float) ($priceRow['average_buying_price'] ?? 0.0) * (float) $item['estimated_weight'];
        }

        $averagePricePerKg = $totalWeight > 0 ? ($materialPriceTotal / $totalWeight) : 0.0;
        $pickupFee = (float) (FeeCalculator::getConfigs()['default_pickup_fee'] ?? FeeCalculator::DEFAULT_PICKUP_FEE);
        $estimate = FeeCalculator::calculateEstimate($totalWeight, $averagePricePerKg, $pickupFee);

        return [
            'estimated_recyclable_value' => (float) $estimate['estimated_recyclable_value'],
            'pickup_fee' => (float) $estimate['pickup_fee'],
            'ecopick_service_fee' => (float) $estimate['ecopick_service_fee'],
            'estimated_net_amount' => (float) $estimate['estimated_net_amount'],
            'service_fee_pct' => (float) $estimate['service_fee_pct'],
            'total_weight_kg' => round($totalWeight, 2),
        ];
    }

    public function cancelRequest($requestId, $sellerAccountId)
    {
        $request = $this->db->query(
            'SELECT current_status FROM pickup_requests WHERE id = :request_id AND seller_account_id = :seller_account_id LIMIT 1',
            [
                'request_id' => (int) $requestId,
                'seller_account_id' => (int) $sellerAccountId,
            ]
        )->fetch();

        if (!$request) {
            return ['success' => false, 'message' => 'Pickup request not found.'];
        }

        $currentStatus = (string) $request['current_status'];
        if (!in_array($currentStatus, ['Matched', 'Accepted'], true)) {
            return ['success' => false, 'message' => 'Cancellation is not allowed for scheduled pickups.'];
        }

        try {
            $this->db->beginTransaction();
            $statement = $this->db->query(
                "UPDATE pickup_requests SET current_status = 'Cancelled', updated_at = CURRENT_TIMESTAMP
                 WHERE id = :request_id AND seller_account_id = :seller_id AND current_status IN ('Matched', 'Accepted')",
                ['request_id' => (int) $requestId, 'seller_id' => (int) $sellerAccountId]
            );
            if ($statement->rowCount() !== 1) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'The pickup request could not be cancelled.'];
            }
            $this->db->query(
                "INSERT INTO pickup_request_status_history (pickup_request_id, status, changed_by_account_id)
                 VALUES (:request_id, 'Cancelled', :seller_id)",
                ['request_id' => (int) $requestId, 'seller_id' => (int) $sellerAccountId]
            );
            $this->db->commit();
            $result = ['success' => true, 'message' => 'Pickup request cancelled successfully.'];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            error_log('Pickup request cancellation error: ' . $exception->getMessage());
            $result = ['success' => false, 'message' => 'Unable to process the pickup request right now.'];
        }
        if ($result['success']) {
            StatusLogger::logChange((int) $requestId, $currentStatus, 'Cancelled', 'Seller', (int) $sellerAccountId);
        }

        return $result;
    }

    public function listAdminPendingRequests()
    {
        return $this->db->query(
                "SELECT pr.id, pr.booking_reference, pr.current_status, a.full_name AS seller_name, a.email AS seller_email,
                    jp.business_name AS junkshop_name,
                    pr.contact_number, pr.pickup_address, pr.preferred_pickup_date, pr.preferred_pickup_time,
                    pr.photo_path, pr.notes, pr.created_at, pr.updated_at, COALESCE(SUM(COALESCE(pri.estimated_weight_kg, pri.estimated_weight)), 0) AS estimated_total_weight,
                    GROUP_CONCAT(DISTINCT CONCAT(COALESCE(NULLIF(rm.name, ''), rm.material_name), ' (', FORMAT(COALESCE(pri.estimated_weight_kg, pri.estimated_weight), 2), ' kg)') ORDER BY COALESCE(NULLIF(rm.name, ''), rm.material_name) SEPARATOR ', ') AS materials_summary
             FROM pickup_requests pr
             JOIN accounts a ON a.id = pr.seller_account_id
             LEFT JOIN junkshop_profiles jp ON jp.account_id = pr.junkshop_id
             JOIN pickup_request_items pri ON pri.pickup_request_id = pr.id AND pri.is_removed = 0
             JOIN recyclable_materials rm ON rm.id = pri.material_id
             WHERE pr.current_status IN ('Pending Request', 'Pending', 'Matched')
             GROUP BY pr.id ORDER BY pr.created_at ASC"
        )->fetchAll();
    }

    public function validateRequestData($data)
    {
        $errors = [];
        if (empty($data['items']) || !is_array($data['items'])) {
            $errors[] = 'Add at least one recyclable material.';
        } else {
            foreach ($data['items'] as $index => $item) {
                $row = $index + 1;
                if ((int) ($item['material_id'] ?? 0) <= 0) {
                    $errors[] = "Choose a recyclable material for row {$row}.";
                }
                if (!is_numeric($item['estimated_weight'] ?? null) || (float) $item['estimated_weight'] <= 0) {
                    $errors[] = "Enter a positive estimated weight for row {$row}.";
                }
            }
        }

        if (empty($errors) && !empty($data['items']) && is_array($data['items'])) {
            $totalWeight = array_sum(array_map(
                static fn (array $item): float => (float) ($item['estimated_weight'] ?? 0),
                $data['items']
            ));
            if ($totalWeight < self::MINIMUM_PICKUP_WEIGHT_KG) {
                $errors[] = self::MINIMUM_PICKUP_WEIGHT_MESSAGE;
            }
        }

        if ((int) ($data['junkshop_id'] ?? 0) <= 0) {
            $errors[] = 'Please select a partner junkshop.';
        }
        if (!preg_match('/^639\d{9}$/', (string) ($data['contact_number'] ?? ''))) {
            $errors[] = 'Enter a valid Philippine mobile number.';
        }
        if (trim($data['pickup_address'] ?? '') === '') {
            $errors[] = 'Pickup address/location is required.';
        }
        if (!is_numeric($data['approximate_distance_km'] ?? null) || (float) $data['approximate_distance_km'] < 0) {
            $errors[] = 'Approximate distance must be zero or greater.';
        }
        if (!is_numeric($data['seller_lat'] ?? null) || (float) $data['seller_lat'] < -90 || (float) $data['seller_lat'] > 90 || !is_numeric($data['seller_lng'] ?? null) || (float) $data['seller_lng'] < -180 || (float) $data['seller_lng'] > 180) {
            $errors[] = 'Current location coordinates are required.';
        }

        $date = trim((string) ($data['preferred_pickup_date'] ?? ''));
        $dateObject = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            $errors[] = 'Choose a valid preferred pickup date.';
        } elseif ($dateObject < new DateTime('today')) {
            $errors[] = 'Preferred pickup date cannot be in the past.';
        }

        if (trim($data['preferred_pickup_time'] ?? '') === '') {
            $errors[] = 'Preferred pickup time is required.';
        }
        return $errors;
    }

    private function normalizeRequestData($data)
    {
        $items = [];
        foreach ((array) ($data['items'] ?? []) as $item) {
            $items[] = [
                'material_id' => (int) ($item['material_id'] ?? 0),
                'estimated_weight' => number_format((float) ($item['estimated_weight'] ?? 0), 2, '.', ''),
            ];
        }
        return [
            'items' => $items,
            'junkshop_id' => (int) ($data['junkshop_id'] ?? 0),
            'contact_number' => formatPhilippineMobileNumber((string) ($data['contact_number'] ?? '')) ?? '',
            'pickup_address' => trim((string) ($data['pickup_address'] ?? '')),
            'approximate_distance_km' => number_format(max(0.0, (float) ($data['approximate_distance_km'] ?? 0)), 2, '.', ''),
            'seller_lat' => number_format((float) ($data['seller_lat'] ?? 0), 8, '.', ''),
            'seller_lng' => number_format((float) ($data['seller_lng'] ?? 0), 8, '.', ''),
            'preferred_pickup_date' => trim((string) ($data['preferred_pickup_date'] ?? '')),
            'preferred_pickup_time' => trim((string) ($data['preferred_pickup_time'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ];
    }

    private function calculateDistanceInKm(float $sellerLatitude, float $sellerLongitude, float $junkshopLatitude, float $junkshopLongitude): float
    {
        $earthRadius = 6371.0;
        $latitudeDifference = deg2rad($junkshopLatitude - $sellerLatitude);
        $longitudeDifference = deg2rad($junkshopLongitude - $sellerLongitude);
        $a = sin($latitudeDifference / 2) ** 2
            + cos(deg2rad($sellerLatitude)) * cos(deg2rad($junkshopLatitude)) * sin($longitudeDifference / 2) ** 2;
        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    private function calculatePickupFee(float $distanceInKm, float $perKmRate): float
    {
        $effectiveKilometers = max(1, (int) ceil(max(0.0, $distanceInKm)));

        return round($effectiveKilometers * max(0.0, $perKmRate), 2);
    }

    private function getPerKmRate(): float
    {
        $perKmRate = FeeCalculator::DEFAULT_PICKUP_FEE;
        try {
            $configuredRate = $this->db->query(
                "SELECT config_value FROM fee_configurations WHERE config_key = 'default_pickup_fee' LIMIT 1"
            )->fetchColumn();
            if (is_numeric($configuredRate)) {
                $perKmRate = (float) $configuredRate;
            }
        } catch (PDOException $exception) {
            error_log('Pickup fee lookup failed: ' . $exception->getMessage());
        }

        return max(0.0, $perKmRate);
    }

    private function storePhoto($file)
    {
        $maxSize = 5 * 1024 * 1024;
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'The recyclable-material photo could not be uploaded.'];
        }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'The recyclable-material photo must be 5 MB or smaller.'];
        }
        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            return ['success' => false, 'message' => 'The recyclable-material photo upload is invalid.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!isset($allowed[$mime])) {
            return ['success' => false, 'message' => 'Photo must be a JPG, JPEG, PNG, or WEBP image.'];
        }

        if (!is_dir($this->photoDirectory) && !mkdir($this->photoDirectory, 0750, true)) {
            return ['success' => false, 'message' => 'Photo storage is currently unavailable.'];
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        $target = $this->photoDirectory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return ['success' => false, 'message' => 'The recyclable-material photo could not be saved.'];
        }

        return ['success' => true, 'path' => 'storage/pickup-photos/' . $filename];
    }

    private function deletePhoto($relativePath)
    {
        $filename = basename((string) $relativePath);
        if ($filename !== '') {
            @unlink($this->photoDirectory . DIRECTORY_SEPARATOR . $filename);
        }
    }

}
