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
            'SELECT DISTINCT id, COALESCE(NULLIF(name, \'\'), material_name) AS material_name, category, description, examples, preparation_notes, unit_of_measure, is_active, created_at, updated_at
             FROM recyclable_materials WHERE is_active = 1 ORDER BY category ASC, material_name ASC'
        )->fetchAll();
    }

    public function getJunkshopMaterialPrices($accountId)
    {
        return $this->db->query(
            'SELECT jmp.id, jmp.junkshop_account_id, jmp.material_id, COALESCE(rm.name, rm.material_name) AS material_name, rm.category,
                    rm.description, rm.examples, rm.preparation_notes, rm.unit_of_measure, jmp.buying_price, jmp.available, jmp.created_at, jmp.updated_at
             FROM junkshop_material_prices jmp
             JOIN recyclable_materials rm ON rm.id = jmp.material_id
             WHERE jmp.junkshop_account_id = :account_id
             ORDER BY rm.category ASC, material_name ASC',
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
                "SELECT DISTINCT
                    a.id AS junkshop_account_id,
                    jp.business_name,
                    jp.is_available,
                    jp.partnership_expires_at,
                    jp.complete_address AS location,
                    jp.complete_address AS address,
                    CAST(jp.latitude AS DECIMAL(11,8)) AS latitude,
                    CAST(jp.longitude AS DECIMAL(11,8)) AS longitude,
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
                FROM accounts a
                JOIN junkshop_profiles jp ON jp.account_id = a.id
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
                  AND (jp.partnership_expires_at IS NULL OR jp.partnership_expires_at > CURRENT_TIMESTAMP)
                ORDER BY is_preferred DESC, jp.business_name ASC, rm.category ASC, rm.material_name ASC",
                [
                    'seller_account_id' => (int) $sellerAccountId,
                    'preferred_seller_id' => (int) $sellerAccountId,
                ]
            )->fetchAll(),
            static function (array $junkshop): bool {
                $expiry = trim((string) ($junkshop['partnership_expires_at'] ?? ''));
                return $expiry === '' || ($expiry !== '0000-00-00 00:00:00' && strtotime($expiry) > time());
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
                   AND jp.is_available = 1
                   AND (jp.partnership_expires_at IS NULL OR jp.partnership_expires_at > CURRENT_TIMESTAMP)
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
                    rm.id AS material_id, COALESCE(NULLIF(rm.name, ''), rm.material_name) AS material_name,
                    rm.category, rm.description, rm.examples, rm.preparation_notes, rm.unit_of_measure,
                    jmp.buying_price, jmp.available, jmp.id AS price_id, jmp.updated_at,
                    stats.avg_price, stats.min_price, stats.max_price
             FROM junkshop_profiles jp
             JOIN accounts a ON a.id = jp.account_id
             JOIN junkshop_material_prices jmp ON jmp.junkshop_account_id = jp.account_id
             JOIN recyclable_materials rm ON rm.id = jmp.material_id
             LEFT JOIN (
                 SELECT material_id, AVG(buying_price) AS avg_price, MIN(buying_price) AS min_price, MAX(buying_price) AS max_price
                 FROM junkshop_material_prices
                 WHERE available = 1
                 GROUP BY material_id
             ) stats ON stats.material_id = rm.id
             WHERE jp.approval_status = 'approved' AND rm.is_active = 1
             ORDER BY jp.business_name ASC,
                      FIELD(rm.category, 'PAPER', 'CARDBOARD', 'PLASTIC', 'METAL', 'GLASS'),
                      material_name ASC"
        )->fetchAll();
    }

    public function getAdminMaterialCatalog(): array
    {
        return $this->db->query(
            "SELECT rm.id AS material_id,
                    rm.category,
                    COALESCE(NULLIF(rm.name, ''), rm.material_name) AS material_name,
                    rm.description,
                    rm.examples,
                    rm.preparation_notes,
                    rm.is_active,
                    rm.unit_of_measure,
                    AVG(jmp.buying_price) AS avg_price,
                    MIN(jmp.buying_price) AS min_price,
                    MAX(jmp.buying_price) AS max_price
             FROM recyclable_materials rm
             LEFT JOIN junkshop_material_prices jmp
                ON jmp.material_id = rm.id AND jmp.available = 1
             WHERE rm.is_active = 1
             GROUP BY rm.id, rm.category, rm.name, rm.material_name, rm.description,
                      rm.examples, rm.preparation_notes, rm.is_active, rm.unit_of_measure
             ORDER BY FIELD(rm.category, 'PAPER', 'CARDBOARD', 'PLASTIC', 'METAL', 'GLASS'),
                      material_name ASC"
        )->fetchAll();
    }

    public function createAdminMaterial(array $data): array
    {
        return $this->writeAdminMaterial('INSERT INTO recyclable_materials (material_name, name, category, description, examples, preparation_notes, unit_of_measure, is_active) VALUES (:material_name, :name, :category, :description, :examples, :preparation_notes, :unit_of_measure, 1)', $data, 'Material created successfully.');
    }

    public function updateAdminMaterial(int $materialId, array $data): array
    {
        $data['material_id'] = $materialId;
        return $this->writeAdminMaterial('UPDATE recyclable_materials SET material_name = :material_name, name = :name, category = :category, description = :description, examples = :examples, preparation_notes = :preparation_notes, unit_of_measure = :unit_of_measure, updated_at = CURRENT_TIMESTAMP WHERE id = :material_id', $data, 'Material updated successfully.');
    }

    private function writeAdminMaterial(string $sql, array $data, string $successMessage): array
    {
        $allowedCategories = ['PAPER', 'CARDBOARD', 'PLASTIC', 'METAL', 'GLASS'];
        $name = trim((string) ($data['name'] ?? ''));
        $category = strtoupper(trim((string) ($data['category'] ?? '')));
        if ($name === '' || !in_array($category, $allowedCategories, true)) {
            return ['success' => false, 'message' => 'A valid category and material name are required.'];
        }

        try {
            $this->db->query($sql, [
                'material_id' => (int) ($data['material_id'] ?? 0),
                'material_name' => $name,
                'name' => $name,
                'category' => $category,
                'description' => trim((string) ($data['description'] ?? '')),
                'examples' => trim((string) ($data['examples'] ?? '')),
                'preparation_notes' => trim((string) ($data['preparation_notes'] ?? '')),
                'unit_of_measure' => trim((string) ($data['unit_of_measure'] ?? 'kg')) ?: 'kg',
            ]);
            return ['success' => true, 'message' => $successMessage];
        } catch (Throwable $exception) {
            error_log('Admin material write error: ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Unable to save the material. The category/name may already exist.'];
        }
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
