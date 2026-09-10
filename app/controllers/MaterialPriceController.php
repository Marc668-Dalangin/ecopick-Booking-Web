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
        return $this->fetchAll('sp_list_active_materials');
    }

    public function getJunkshopMaterialPrices($accountId)
    {
        return $this->fetchAll('sp_get_junkshop_material_prices', [$accountId]);
    }

    public function addMaterialPrice($accountId, $materialId, $buyingPrice, $available = 1)
    {
        return $this->executeResult('sp_add_junkshop_material_price', [$accountId, (int) $materialId, (string) $buyingPrice, (int) $available]);
    }

    public function updateMaterialPrice($accountId, $priceId, $buyingPrice, $available = 1)
    {
        return $this->executeResult('sp_update_junkshop_material_price', [$accountId, (int) $priceId, (string) $buyingPrice, (int) $available]);
    }

    public function removeMaterialPrice($accountId, $priceId)
    {
        return $this->executeResult('sp_remove_junkshop_material_price', [$accountId, (int) $priceId]);
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
        return $this->fetchAll('sp_get_admin_price_overview');
    }

    public function getJunkshopApprovalStatus($accountId)
    {
        $profile = $this->fetchOne('sp_get_junkshop_profile', [$accountId]);
        return strtolower((string)($profile['approval_status'] ?? 'pending'));
    }

    private function fetchAll($procedureName, $params = [])
    {
        try {
            $stmt = $this->db->call($procedureName, $params);
            $rows = $stmt->fetchAll();
            if ($stmt instanceof PDOStatement) {
                $stmt->closeCursor();
            }
            return $rows;
        } catch (Exception $e) {
            error_log('Material price fetch error: ' . $e->getMessage());
            return [];
        }
    }

    private function fetchOne($procedureName, $params = [])
    {
        try {
            $stmt = $this->db->call($procedureName, $params);
            $row = $stmt->fetch();
            if ($stmt instanceof PDOStatement) {
                $stmt->closeCursor();
            }
            return $row ?: null;
        } catch (Exception $e) {
            error_log('Material price read error: ' . $e->getMessage());
            return null;
        }
    }

    private function executeResult($procedureName, $params = [])
    {
        try {
            $stmt = $this->db->call($procedureName, $params);
            $row = $stmt->fetch();
            if ($stmt instanceof PDOStatement) {
                $stmt->closeCursor();
            }

            return [
                'success' => ($row['p_result'] ?? 'success') === 'success',
                'message' => ($row['p_result'] ?? 'success') === 'success' ? 'Success.' : ($row['p_result'] ?? 'Unable to process request.'),
                'result' => $row,
            ];
        } catch (Exception $e) {
            error_log('Material price write error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Unable to process your request at the moment.',
                'result' => null,
            ];
        }
    }

}
