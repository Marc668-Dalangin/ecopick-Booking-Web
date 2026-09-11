<?php
/**
 * Material and buying price controller.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

class MaterialPriceController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function listActiveMaterials()
    {
        return $this->db->query(
            'SELECT id, material_name, category, unit_of_measure, is_active, created_at, updated_at
             FROM recyclable_materials WHERE is_active = 1 ORDER BY category ASC, material_name ASC'
        )->fetchAll();
    }

    public function getJunkshopMaterialPrices($accountId)
    {
        return $this->db->query(
            'SELECT jmp.id, jmp.junkshop_account_id, jmp.material_id, rm.material_name, rm.category,
                    rm.unit_of_measure, jmp.buying_price, jmp.available, jmp.created_at, jmp.updated_at
             FROM junkshop_material_prices jmp
             JOIN recyclable_materials rm ON rm.id = jmp.material_id
             WHERE jmp.junkshop_account_id = :account_id
             ORDER BY rm.category ASC, rm.material_name ASC',
            ['account_id' => (int) $accountId]
        )->fetchAll();
    }

    public function addMaterialPrice($accountId, $materialId, $buyingPrice, $available = 1)
    {
        return $this->writeMaterialPrice(
            'INSERT INTO junkshop_material_prices (junkshop_account_id, material_id, buying_price, available)
             VALUES (:account_id, :material_id, :buying_price, :available)',
            ['account_id' => (int) $accountId, 'material_id' => (int) $materialId,
             'buying_price' => (string) $buyingPrice, 'available' => (int) $available],
            'Material already added. Please update the existing price instead.'
        );
    }

    public function updateMaterialPrice($accountId, $priceId, $buyingPrice, $available = 1)
    {
        return $this->writeMaterialPrice(
            'UPDATE junkshop_material_prices SET buying_price = :buying_price, available = :available,
             updated_at = CURRENT_TIMESTAMP WHERE id = :price_id AND junkshop_account_id = :account_id',
            ['account_id' => (int) $accountId, 'price_id' => (int) $priceId,
             'buying_price' => (string) $buyingPrice, 'available' => (int) $available],
            'Price record not found for this junkshop.'
        );
    }

    public function removeMaterialPrice($accountId, $priceId)
    {
        return $this->writeMaterialPrice(
            'DELETE FROM junkshop_material_prices WHERE id = :price_id AND junkshop_account_id = :account_id',
            ['account_id' => (int) $accountId, 'price_id' => (int) $priceId],
            'Price record not found for this junkshop.'
        );
    }

    public function listApprovedJunkshopsWithPrices(int $sellerAccountId = 0)
    {
        return array_values(array_filter(
            $this->db->query(
                "SELECT
                    jp.account_id AS junkshop_account_id,
                    jp.business_name,
                    jp.partnership_expires_at,
                    jp.complete_address AS location,
                    jp.operating_schedule,
                    a.full_name AS contact_person,
                    a.mobile_number,
                    rm.material_name,
                    jmp.material_id,
                    rm.category,
                    rm.unit_of_measure,
                    jmp.buying_price,
                    jmp.available,
                    jmp.id AS price_id,
                    CASE WHEN pj.id IS NOT NULL THEN 1 ELSE 0 END AS is_preferred,
                    IFNULL((
                        SELECT 1
                        FROM pickup_requests active_pr
                        WHERE active_pr.junkshop_id = a.id
                          AND active_pr.seller_account_id = :seller_account_id
                          AND active_pr.current_status IN ('Pending Request', 'Accepted', 'Scheduled', 'For Pickup')
                        LIMIT 1
                    ), 0) AS has_active_request
                FROM junkshop_profiles jp
                JOIN accounts a ON a.id = jp.account_id
                LEFT JOIN junkshop_material_prices jmp
                    ON jmp.junkshop_account_id = jp.account_id
                   AND jmp.available = 1
                LEFT JOIN recyclable_materials rm
                    ON rm.id = jmp.material_id
                   AND rm.is_active = 1
                LEFT JOIN preferred_junkshops pj
                    ON pj.junkshop_id = jp.account_id
                   AND pj.seller_id = :preferred_seller_id
                WHERE a.account_status = 'active'
                  AND jp.approval_status = 'approved'
                  AND (jp.partnership_expires_at IS NULL OR jp.partnership_expires_at >= CURRENT_DATE)
                ORDER BY is_preferred DESC, jp.business_name ASC, rm.category ASC, rm.material_name ASC",
                [
                    'seller_account_id' => (int) $sellerAccountId,
                    'preferred_seller_id' => (int) $sellerAccountId,
                ]
            )->fetchAll(),
            static function (array $junkshop): bool {
                $expiry = trim((string) ($junkshop['partnership_expires_at'] ?? ''));
                return $expiry === '' || $expiry >= date('Y-m-d');
            }
        ));
    }

    public function togglePreferredJunkshop(int $sellerAccountId, int $junkshopAccountId)
    {
        $db = $this->db;

        try {
            $db->beginTransaction();

            $junkshop = $db->query(
                "SELECT jp.account_id
                 FROM junkshop_profiles jp
                 JOIN accounts a ON a.id = jp.account_id
                 WHERE jp.account_id = :junkshop_id
                   AND a.account_status = 'active'
                   AND jp.approval_status = 'approved'
                   AND (jp.partnership_expires_at IS NULL OR jp.partnership_expires_at >= CURRENT_DATE)
                 LIMIT 1",
                ['junkshop_id' => $junkshopAccountId]
            )->fetch();

            if (!$junkshop) {
                $db->rollBack();
                return ['success' => false, 'message' => 'Junkshop is not available for preference selection.'];
            }

            $existing = $db->query(
                'SELECT id FROM preferred_junkshops WHERE seller_id = :seller_id AND junkshop_id = :junkshop_id LIMIT 1',
                ['seller_id' => $sellerAccountId, 'junkshop_id' => $junkshopAccountId]
            )->fetch();

            if ($existing) {
                $db->query(
                    'DELETE FROM preferred_junkshops WHERE seller_id = :seller_id AND junkshop_id = :junkshop_id',
                    ['seller_id' => $sellerAccountId, 'junkshop_id' => $junkshopAccountId]
                );
                $isPreferred = false;
            } else {
                $db->query(
                    'INSERT INTO preferred_junkshops (seller_id, junkshop_id) VALUES (:seller_id, :junkshop_id)',
                    ['seller_id' => $sellerAccountId, 'junkshop_id' => $junkshopAccountId]
                );
                $isPreferred = true;
            }

            $db->commit();
            return ['success' => true, 'message' => 'Preferred junkshop updated.', 'is_preferred' => $isPreferred];
        } catch (Throwable $exception) {
            $db->rollBack();
            error_log('Preferred junkshop toggle error: ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Unable to update preferred junkshop.'];
        }
    }

    public function getAdminPriceOverview()
    {
        return $this->db->query(
            "SELECT jp.account_id AS junkshop_account_id, jp.business_name, jp.complete_address AS location,
                    jp.operating_schedule, a.full_name AS contact_person, a.email, a.mobile_number,
                    rm.material_name, rm.category, rm.unit_of_measure, jmp.buying_price, jmp.available,
                    jmp.id AS price_id, jmp.updated_at
             FROM junkshop_profiles jp
             JOIN accounts a ON a.id = jp.account_id
             JOIN junkshop_material_prices jmp ON jmp.junkshop_account_id = jp.account_id
             JOIN recyclable_materials rm ON rm.id = jmp.material_id
             WHERE jp.approval_status = 'approved' AND rm.is_active = 1
             ORDER BY jp.business_name ASC, rm.category ASC, rm.material_name ASC"
        )->fetchAll();
    }

    public function getJunkshopApprovalStatus($accountId)
    {
        $profile = $this->db->query(
            'SELECT approval_status FROM junkshop_profiles WHERE account_id = :account_id LIMIT 1',
            ['account_id' => (int) $accountId]
        )->fetch();
        return strtolower((string)($profile['approval_status'] ?? 'pending'));
    }

    private function writeMaterialPrice(string $sql, array $params, string $missingMessage): array
    {
        try {
            $this->db->beginTransaction();
            $isInsert = str_starts_with($sql, 'INSERT');
            $isDelete = str_starts_with($sql, 'DELETE');
            $approved = $this->db->query(
                "SELECT account_id FROM junkshop_profiles WHERE account_id = :account_id AND approval_status = 'approved' LIMIT 1",
                ['account_id' => (int) $params['account_id']]
            )->fetch();
            if (!$approved) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Only approved junkshops can manage materials and prices.', 'result' => null];
            }

            if (array_key_exists('material_id', $params)) {
                $existing = $this->db->query(
                    'SELECT id FROM junkshop_material_prices WHERE junkshop_account_id = :account_id AND material_id = :material_id LIMIT 1',
                    ['account_id' => (int) $params['account_id'], 'material_id' => (int) $params['material_id']]
                )->fetch();
            } else {
                $existing = $this->db->query(
                    'SELECT id FROM junkshop_material_prices WHERE id = :price_id AND junkshop_account_id = :account_id LIMIT 1',
                    ['price_id' => (int) $params['price_id'], 'account_id' => (int) $params['account_id']]
                )->fetch();
            }
            if ((!$isInsert && !$existing) || ($isInsert && $existing)) {
                $this->db->rollBack();
                return ['success' => false, 'message' => $missingMessage, 'result' => null];
            }

            $statement = $this->db->query($sql, $params);
            $this->db->commit();
            $success = !$isDelete || $statement->rowCount() > 0;
            return ['success' => $success, 'message' => $success ? 'Success.' : $missingMessage, 'result' => null];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Material price write error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Unable to process your request at the moment.',
                'result' => null,
            ];
        }
    }

}
