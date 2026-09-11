<?php
/**
 * Phase 4A seller pickup request controller.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

class PickupRequestController
{
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
            return ['success' => false, 'message' => 'Please correct the highlighted fields.', 'validation_errors' => $errors];
        }

        try {
            $stmt = $this->db->call('sp_create_pickup_request', [
                (int) $sellerAccountId,
                (int) ($normalized['junkshop_id'] ?? 0),
                json_encode($normalized['items'], JSON_UNESCAPED_SLASHES),
                $normalized['pickup_address'],
                $normalized['barangay'],
                $normalized['preferred_pickup_date'],
                $normalized['preferred_pickup_time'],
                $photoPath,
                $normalized['notes'] !== '' ? $normalized['notes'] : null,
            ]);
            $result = $stmt->fetch();
            $this->db->closeProcedureCursor($stmt);

            if (($result['p_result'] ?? '') !== 'success') {
                if ($photoPath !== null) {
                    $this->deletePhoto($photoPath);
                }
                return ['success' => false, 'message' => $result['p_result'] ?? 'Unable to create the pickup request.', 'validation_errors' => []];
            }

            $requestId = (int) ($result['p_request_id'] ?? 0);

            $this->db->query(
                'UPDATE pickup_requests SET pickup_location_name = :location_name, approximate_distance_km = :distance_km, seller_lat = :seller_lat, seller_lng = :seller_lng WHERE id = :request_id AND seller_account_id = :seller_id',
                [
                    'location_name' => $normalized['pickup_location_name'],
                    'distance_km' => $normalized['approximate_distance_km'],
                    'seller_lat' => $normalized['seller_lat'],
                    'seller_lng' => $normalized['seller_lng'],
                    'request_id' => $requestId,
                    'seller_id' => (int) $sellerAccountId,
                ]
            );

            if ($requestId > 0) {
                StatusLogger::logChange($requestId, null, 'Pending Request', 'Seller', (int) $sellerAccountId);
            }

            return [
                'success' => true,
                'message' => 'Pickup request submitted successfully.',
                'validation_errors' => [],
                'request' => [
                    'id' => $requestId,
                    'booking_reference' => $result['p_booking_reference'],
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

    public function listSellerRequests($sellerAccountId)
    {
        return $this->fetchAll('sp_get_seller_pickup_requests', [(int) $sellerAccountId]);
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
        $rows = $this->fetchAll('sp_get_pickup_request_details', [(int) $requestId, (int) $sellerAccountId]);
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
                'SELECT pri.id, COALESCE(tm.buying_price_per_kg, pri.estimated_buying_price_per_kg, CASE WHEN pr.current_status <> \'Pending Request\' THEN jmp.buying_price END) AS matched_price_per_kg, COALESCE(tm.final_material_value, pri.estimated_material_value, CASE WHEN pr.current_status <> \'Pending Request\' THEN ROUND(pri.estimated_weight * jmp.buying_price, 2) END) AS estimated_value, tm.final_material_value AS actual_value, pri.estimated_buying_price_per_kg, pri.estimated_material_value, pri.estimate_snapshot_at FROM pickup_request_items pri JOIN pickup_requests pr ON pr.id = pri.pickup_request_id LEFT JOIN junkshop_material_prices jmp ON jmp.junkshop_account_id = pr.junkshop_id AND jmp.material_id = pri.material_id AND jmp.available = 1 LEFT JOIN transaction_materials tm ON tm.pickup_request_item_id = pri.id WHERE pri.id IN (' . $placeholders . ') ORDER BY tm.id DESC',
                $itemIds
            )->fetchAll();
            foreach ($snapshotRows as $snapshotRow) {
                $snapshotByItemId[(int) $snapshotRow['id']] = $snapshotRow;
            }
        }
        foreach ($rows as $row) {
            $snapshot = $snapshotByItemId[(int) $row['item_id']] ?? [];
            $request['items'][] = [
                'item_id' => (int) $row['item_id'],
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
        $request['status_history'] = $history;
        $request['formatted_pickup_date'] = $request['formatted_pickup_date'] ?? null;
        $request['formatted_pickup_time'] = $request['formatted_pickup_time'] ?? null;
        return $request;
    }

    public function getSellerRequestAssignmentSummary($requestId, $sellerAccountId): array
    {
        $row = $this->db->query(
            'SELECT pr.id AS assignment_id, pr.id AS pickup_request_id, pr.junkshop_id, CASE WHEN pr.current_status = :pending_status THEN :matched_status ELSE pr.current_status END AS assignment_status, pr.pickup_address, pr.barangay, pr.preferred_pickup_date, pr.preferred_pickup_time, jp.business_name, jp.complete_address, jp.owner_name, a.full_name AS junkshop_contact_name FROM pickup_requests pr LEFT JOIN junkshop_profiles jp ON jp.account_id = pr.junkshop_id LEFT JOIN accounts a ON a.id = pr.junkshop_id WHERE pr.id = :pickup_request_id AND pr.seller_account_id = :seller_account_id LIMIT 1',
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
                'SELECT AVG(jmp.buying_price) AS average_buying_price FROM junkshop_material_prices jmp JOIN junkshop_profiles jp ON jp.account_id = jmp.junkshop_account_id WHERE jmp.material_id = :material_id AND jmp.available = 1 AND jp.approval_status = :approval_status',
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
        if ($currentStatus !== 'Pending Request') {
            return ['success' => false, 'message' => 'Cancellation is not allowed once the request has been accepted.'];
        }

        $result = $this->executeResult('sp_cancel_pickup_request', [(int) $requestId, (int) $sellerAccountId], 'Pickup request cancelled successfully.');
        if ($result['success']) {
            StatusLogger::logChange((int) $requestId, $currentStatus, 'Cancelled by Seller', 'Seller', (int) $sellerAccountId);
        }

        return $result;
    }

    public function listAdminPendingRequests()
    {
        return $this->fetchAll('sp_get_admin_pending_pickup_requests');
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
                    $errors[] = "Estimated weight for row {$row} must be greater than zero.";
                }
            }
        }

        if ((int) ($data['junkshop_id'] ?? 0) <= 0) {
            $errors[] = 'Please select a partner junkshop.';
        }
        if (trim($data['pickup_address'] ?? '') === '') {
            $errors[] = 'Pickup address/location is required.';
        }
        if (trim($data['pickup_location_name'] ?? '') === '') {
            $errors[] = 'Pickup location name is required.';
        }
        if (!is_numeric($data['approximate_distance_km'] ?? null) || (float) $data['approximate_distance_km'] < 0) {
            $errors[] = 'Approximate distance must be zero or greater.';
        }
        if (!is_numeric($data['seller_lat'] ?? null) || (float) $data['seller_lat'] < -90 || (float) $data['seller_lat'] > 90 || !is_numeric($data['seller_lng'] ?? null) || (float) $data['seller_lng'] < -180 || (float) $data['seller_lng'] > 180) {
            $errors[] = 'Current location coordinates are required.';
        }
        if (trim($data['barangay'] ?? '') === '') {
            $errors[] = 'Barangay is required.';
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
            'pickup_address' => trim((string) ($data['pickup_address'] ?? '')),
            'pickup_location_name' => trim((string) ($data['pickup_location_name'] ?? '')),
            'approximate_distance_km' => number_format(max(0.0, (float) ($data['approximate_distance_km'] ?? 0)), 2, '.', ''),
            'seller_lat' => number_format((float) ($data['seller_lat'] ?? 0), 8, '.', ''),
            'seller_lng' => number_format((float) ($data['seller_lng'] ?? 0), 8, '.', ''),
            'barangay' => trim((string) ($data['barangay'] ?? '')),
            'preferred_pickup_date' => trim((string) ($data['preferred_pickup_date'] ?? '')),
            'preferred_pickup_time' => trim((string) ($data['preferred_pickup_time'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
        ];
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

    private function fetchAll($procedureName, $params = [])
    {
        try {
            $stmt = $this->db->call($procedureName, $params);
            $rows = $stmt->fetchAll();
            $this->db->closeProcedureCursor($stmt);
            return $rows;
        } catch (Exception $e) {
            error_log('Pickup request read error: ' . $e->getMessage());
            return [];
        }
    }

    private function executeResult($procedureName, $params, $successMessage)
    {
        try {
            $stmt = $this->db->call($procedureName, $params);
            $row = $stmt->fetch();
            $this->db->closeProcedureCursor($stmt);
            $result = $row['p_result'] ?? '';
            return ['success' => $result === 'success', 'message' => $result === 'success' ? $successMessage : ($result ?: 'Unable to process the pickup request.')];
        } catch (Exception $e) {
            error_log('Pickup request write error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to process the pickup request right now.'];
        }
    }
}
