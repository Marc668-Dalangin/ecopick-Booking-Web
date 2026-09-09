<?php
/**
 * Material and buying price controller.
 */

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../services/MatchingEngine.php';

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
        $result = $this->executeResult('sp_add_junkshop_material_price', [$accountId, (int) $materialId, (string) $buyingPrice, (int) $available]);
        return $this->triggerMatching($result, (int) $accountId);
    }

    public function updateMaterialPrice($accountId, $priceId, $buyingPrice, $available = 1)
    {
        $result = $this->executeResult('sp_update_junkshop_material_price', [$accountId, (int) $priceId, (string) $buyingPrice, (int) $available]);
        return $this->triggerMatching($result, (int) $accountId);
    }

    public function removeMaterialPrice($accountId, $priceId)
    {
        $result = $this->executeResult('sp_remove_junkshop_material_price', [$accountId, (int) $priceId]);
        return $this->triggerMatching($result, (int) $accountId);
    }

    public function listApprovedJunkshopsWithPrices()
    {
        return array_values(array_filter(
            $this->fetchAll('sp_list_approved_junkshops_with_prices'),
            static function (array $junkshop): bool {
                $expiry = trim((string) ($junkshop['partnership_expires_at'] ?? ''));
                return $expiry === '' || $expiry >= date('Y-m-d');
            }
        ));
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

    private function triggerMatching(array $result, int $accountId): array
    {
        if (($result['success'] ?? false) === true) {
            $result['matches'] = MatchingEngine::onJunkshopMaterialsUpdated($accountId);
        }
        return $result;
    }
}
