<?php
/**
 * Matching Engine Service
 *
 * Responsible for locating suitable junkshop candidates for a seller pickup request.
 */

class MatchingEngine
{
    public const MAX_MATCH_RADIUS_KM = 15.0;

    public static function evaluateMatches(int $junkshopId, int $sellerRequestId): ?array
    {
        if ($junkshopId <= 0 || $sellerRequestId <= 0) {
            return null;
        }

        $db = Database::getInstance();
        $request = $db->query(
            'SELECT pr.id, pr.current_status, pr.approximate_distance_km, a.account_status AS seller_account_status FROM pickup_requests pr JOIN accounts a ON a.id = pr.seller_account_id WHERE pr.id = :request_id LIMIT 1',
            ['request_id' => $sellerRequestId]
        )->fetch();
        if (!$request || !in_array((string) $request['current_status'], ['Pending Request'], true) || $request['seller_account_status'] !== 'active') {
            return null;
        }

        $junkshop = $db->query(
            'SELECT jp.account_id AS junkshop_id, jp.business_name FROM junkshop_profiles jp JOIN accounts a ON a.id = jp.account_id WHERE jp.account_id = :junkshop_id AND jp.approval_status = :approval_status AND jp.is_available = 1 AND (jp.partnership_expires_at IS NULL OR jp.partnership_expires_at > CURRENT_TIMESTAMP) AND a.account_status = :account_status AND a.role_id = (SELECT id FROM roles WHERE name = \'junkshop\') LIMIT 1',
            ['junkshop_id' => $junkshopId, 'approval_status' => 'approved', 'account_status' => 'active']
        )->fetch();
        if (!$junkshop || (float) ($request['approximate_distance_km'] ?? 0) > self::MAX_MATCH_RADIUS_KM) {
            return null;
        }

        $requestedMaterials = $db->query(
            'SELECT material_id FROM pickup_request_items WHERE pickup_request_id = :request_id',
            ['request_id' => $sellerRequestId]
        )->fetchAll();
        $materialIds = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['material_id'], $requestedMaterials)));
        if (empty($materialIds)) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($materialIds), '?'));
        $params = array_merge([$junkshopId], $materialIds);
        $priceRow = $db->query(
            'SELECT COUNT(DISTINCT material_id) AS matched_count FROM junkshop_material_prices WHERE junkshop_account_id = ? AND material_id IN (' . $placeholders . ') AND available = 1',
            $params
        )->fetch();
        if ((int) ($priceRow['matched_count'] ?? 0) !== count($materialIds)) {
            return null;
        }

        return [
            'pickup_request_id' => $sellerRequestId,
            'junkshop_id' => $junkshopId,
            'distance_km' => (float) ($request['approximate_distance_km'] ?? 0),
            'business_name' => (string) ($junkshop['business_name'] ?? 'Partner Junkshop'),
        ];
    }

    public static function onNewPickupRequestCreated(int $sellerRequestId, array $materialIds, float $approximateDistanceKm): array
    {
        $db = Database::getInstance();
        $request = $db->query(
            'SELECT junkshop_id FROM pickup_requests WHERE id = :request_id LIMIT 1',
            ['request_id' => $sellerRequestId]
        )->fetch();
        $selectedJunkshopId = (int) ($request['junkshop_id'] ?? 0);
        if ($selectedJunkshopId > 0) {
            $selectedMatch = self::evaluateMatches($selectedJunkshopId, $sellerRequestId);
            if ($selectedMatch !== null) {
                self::saveMatch($sellerRequestId, $selectedMatch);
                return [$selectedMatch];
            }
        }

        $matches = self::findMatches($sellerRequestId, $materialIds, $approximateDistanceKm);
        if (empty($matches)) {
            return [];
        }

        $bestMatch = $matches[0];
        self::saveMatch($sellerRequestId, $bestMatch);
        return [$bestMatch];
    }

    public static function onJunkshopMaterialsUpdated(int $junkshopId): array
    {
        $db = Database::getInstance();
        $requests = $db->query(
            "SELECT DISTINCT pr.id, pr.approximate_distance_km FROM pickup_requests pr JOIN accounts a ON a.id = pr.seller_account_id JOIN pickup_request_items pri ON pri.pickup_request_id = pr.id AND pri.is_removed = 0 WHERE pr.current_status = 'Pending Request' AND a.account_status = 'active' ORDER BY pr.created_at ASC"
        )->fetchAll();

        $created = [];
        foreach ($requests as $request) {
            $match = self::evaluateMatches($junkshopId, (int) $request['id']);
            if ($match === null) {
                continue;
            }
            self::saveMatch((int) $request['id'], $match);
            $created[] = $match;
        }

        return $created;
    }

    private static function saveMatch(int $pickupRequestId, array $match): void
    {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $inserted = $db->query(
                'INSERT INTO junkshop_assignments (pickup_request_id, junkshop_id, status, distance_km, assigned_at, responded_at) SELECT :pickup_request_id, :junkshop_id, :status, :distance_km, CURRENT_TIMESTAMP, NULL FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM junkshop_assignments WHERE pickup_request_id = :existing_request_id AND junkshop_id = :existing_junkshop_id)',
                [
                    'pickup_request_id' => $pickupRequestId,
                    'junkshop_id' => (int) $match['junkshop_id'],
                    'status' => 'Matched',
                    'distance_km' => number_format((float) $match['distance_km'], 2, '.', ''),
                    'existing_request_id' => $pickupRequestId,
                    'existing_junkshop_id' => (int) $match['junkshop_id'],
                ]
            )->rowCount();

            if ($inserted === 1) {
                $db->query(
                    "UPDATE pickup_requests SET current_status = 'Matched', updated_at = CURRENT_TIMESTAMP WHERE id = :request_id AND current_status = 'Pending Request'",
                    ['request_id' => $pickupRequestId]
                );
                $db->query(
                    'UPDATE pickup_request_items pri JOIN junkshop_material_prices jmp ON jmp.material_id = pri.material_id AND jmp.junkshop_account_id = :junkshop_id AND jmp.available = 1 SET pri.estimated_buying_price_per_kg = jmp.buying_price, pri.estimated_material_value = ROUND(COALESCE(pri.estimated_weight_kg, pri.estimated_weight) * jmp.buying_price, 2), pri.estimate_snapshot_at = CURRENT_TIMESTAMP WHERE pri.pickup_request_id = :request_id',
                    ['junkshop_id' => (int) $match['junkshop_id'], 'request_id' => $pickupRequestId]
                );
                StatusLogger::logChange($pickupRequestId, 'Pending Request', 'Matched', 'System');
            }
            $db->commit();
        } catch (Throwable $exception) {
            $db->rollBack();
            error_log('Matching assignment error: ' . $exception->getMessage());
        }
    }

    /**
     * Return candidate junkshops that accept every requested material, sorted by closeness.
     *
     * @return array<int, array{junkshop_id:int,distance_km:float,business_name:string}>
     */
    public static function findMatches(int $pickupRequestId, array $materialIds, float $approximateDistanceKm): array
    {
        $materialIds = array_values(array_unique(array_filter(array_map('intval', $materialIds), static fn (int $id): bool => $id > 0)));
        if ($pickupRequestId <= 0 || empty($materialIds)) {
            return [];
        }

        $db = Database::getInstance();
        $approximateDistanceKm = max(0.0, $approximateDistanceKm);

        $sql = "
            SELECT
                jp.account_id AS junkshop_id,
                jp.business_name
        ";

        $materialPlaceholders = implode(',', array_fill(0, count($materialIds), '?'));
        $sql .= ",
            COUNT(DISTINCT jmp.material_id) AS matched_material_count
        ";

        $sql .= "
            FROM junkshop_material_prices jmp
            JOIN junkshop_profiles jp ON jp.account_id = jmp.junkshop_account_id
            JOIN accounts a ON a.id = jp.account_id
            WHERE jp.approval_status = 'approved'
                            AND (jp.partnership_expires_at IS NULL OR jp.partnership_expires_at > CURRENT_TIMESTAMP)
              AND a.account_status = 'active'
              AND jmp.material_id IN ($materialPlaceholders)
              AND jmp.available = 1
              AND a.role_id = (SELECT id FROM roles WHERE name = 'junkshop')
            GROUP BY jp.account_id, jp.business_name
            HAVING COUNT(DISTINCT jmp.material_id) = " . count($materialIds) . "
        ";

        $stmt = $db->query($sql, $materialIds);
        $rows = $stmt->fetchAll();

        $candidates = [];
        foreach ($rows as $row) {
            $junkshopId = (int) ($row['junkshop_id'] ?? 0);
            if ($junkshopId <= 0) {
                continue;
            }

            $distance = $approximateDistanceKm;
            if ($distance <= self::MAX_MATCH_RADIUS_KM) {
                $candidates[] = [
                    'junkshop_id' => $junkshopId,
                    'distance_km' => $distance,
                    'business_name' => (string) ($row['business_name'] ?? 'Partner Junkshop'),
                ];
            }
        }

        usort($candidates, static fn (array $left, array $right): int => $left['distance_km'] <=> $right['distance_km']);

        $filtered = [];
        foreach ($candidates as $candidate) {
            $existing = $db->query(
                'SELECT COUNT(*) AS assignment_count FROM junkshop_assignments WHERE pickup_request_id = :pickup_request_id AND junkshop_id = :junkshop_id',
                [
                    'pickup_request_id' => $pickupRequestId,
                    'junkshop_id' => $candidate['junkshop_id'],
                ]
            );

            $existingCount = (int) ($existing->fetch()['assignment_count'] ?? 0);
            if ($existingCount === 0) {
                $filtered[] = $candidate;
            }
        }

        return $filtered;
    }
}
