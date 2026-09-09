<?php
/**
 * Phase 4A seller pickup request controller.
 */

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../services/MatchingEngine.php';

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
            $matches = [];
            $materialIds = array_map(static fn (array $item): int => (int) $item['material_id'], $normalized['items']);

            $this->db->query(
                'UPDATE pickup_requests SET pickup_location_name = :location_name, approximate_distance_km = :distance_km WHERE id = :request_id AND seller_account_id = :seller_id',
                [
                    'location_name' => $normalized['pickup_location_name'],
                    'distance_km' => $normalized['approximate_distance_km'],
                    'request_id' => $requestId,
                    'seller_id' => (int) $sellerAccountId,
                ]
            );

            if ($requestId > 0) {
                StatusLogger::logChange($requestId, null, 'Pending Request', 'Seller', (int) $sellerAccountId);
            }

            if ($requestId > 0 && !empty($materialIds)) {
                $matches = MatchingEngine::onNewPickupRequestCreated($requestId, $materialIds, (float) $normalized['approximate_distance_km']);
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
                'SELECT id, estimated_buying_price_per_kg, estimated_material_value, estimate_snapshot_at FROM pickup_request_items WHERE id IN (' . $placeholders . ')',
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
                'estimated_buying_price_per_kg' => $snapshot['estimated_buying_price_per_kg'] ?? null,
                'estimated_material_value' => $snapshot['estimated_material_value'] ?? null,
                'estimate_snapshot_at' => $snapshot['estimate_snapshot_at'] ?? null,
            ];
        }
        $request['status_history'] = $history;
        return $request;
    }

    public function getSellerRequestAssignmentSummary($requestId, $sellerAccountId): array
    {
        $row = $this->db->query(
            'SELECT ja.id AS assignment_id, ja.pickup_request_id, ja.junkshop_id, ja.status AS assignment_status, ja.distance_km, ja.assigned_at, ja.responded_at, jp.business_name, jp.complete_address, jp.owner_name, a.full_name AS junkshop_contact_name FROM junkshop_assignments ja JOIN pickup_requests pr ON pr.id = ja.pickup_request_id LEFT JOIN junkshop_profiles jp ON jp.account_id = ja.junkshop_id LEFT JOIN accounts a ON a.id = ja.junkshop_id WHERE ja.pickup_request_id = :pickup_request_id AND pr.seller_account_id = :seller_account_id ORDER BY ja.assigned_at DESC, ja.id DESC LIMIT 1',
            [
                'pickup_request_id' => (int) $requestId,
                'seller_account_id' => (int) $sellerAccountId,
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
        $cancellableStatuses = ['Pending Request', 'Matched', 'Accepted'];
        if (!in_array($currentStatus, $cancellableStatuses, true)) {
            return ['success' => false, 'message' => 'Sellers may only cancel bookings before they are scheduled.'];
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

        if (trim($data['pickup_address'] ?? '') === '') {
            $errors[] = 'Pickup address/location is required.';
        }
        if (trim($data['pickup_location_name'] ?? '') === '') {
            $errors[] = 'Pickup location name is required.';
        }
        if (!is_numeric($data['approximate_distance_km'] ?? null) || (float) $data['approximate_distance_km'] < 0) {
            $errors[] = 'Approximate distance must be zero or greater.';
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
            'pickup_address' => trim((string) ($data['pickup_address'] ?? '')),
            'pickup_location_name' => trim((string) ($data['pickup_location_name'] ?? '')),
            'approximate_distance_km' => number_format(max(0.0, (float) ($data['approximate_distance_km'] ?? 0)), 2, '.', ''),
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
