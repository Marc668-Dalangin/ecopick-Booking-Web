<?php
/**
 * Admin fee configuration controller.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

class AdminFeeController
{
    private const ACTIVE_FEE_KEYS = [
        'default_pickup_fee',
        'ecopick_service_fee_pct',
        'junkshop_commission_pct',
        'junkshop_registration_fee',
        'renewal_fee_1_month',
        'renewal_fee_6_months',
        'renewal_fee_1_year',
    ];

    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Verify admin access for all public methods of this controller.
     */
    public function ensureAdminAccess(): void
    {
        if (!Auth::check()) {
            http_response_code(401);
            throw new RuntimeException('Authentication required.');
        }

        if (Auth::userRole() !== 'admin') {
            http_response_code(403);
            throw new RuntimeException('Admin access required.');
        }
    }

    /**
     * Fetch all fee configuration entries.
     *
     * @return array<int, array<string,mixed>>
     */
    public function getFeeConfigurations(): array
    {
        $this->ensureAdminAccess();

        $rows = $this->db->query(
            'SELECT id, config_key, config_value, description, updated_at
             FROM fee_configurations
             WHERE config_key IN (:pickup_fee, :service_fee, :commission, :registration_fee, :renewal_1_month, :renewal_6_months, :renewal_1_year)
             ORDER BY id ASC',
            [
                'pickup_fee' => 'default_pickup_fee',
                'service_fee' => 'ecopick_service_fee_pct',
                'commission' => 'junkshop_commission_pct',
                'registration_fee' => 'junkshop_registration_fee',
                'renewal_1_month' => 'renewal_fee_1_month',
                'renewal_6_months' => 'renewal_fee_6_months',
                'renewal_1_year' => 'renewal_fee_1_year',
            ]
        )->fetchAll();

        return $rows;
    }

    /**
     * Update a specific fee configuration value.
     */
    public function updateFeeConfiguration(string $configKey, float $newValue): array
    {
        $this->ensureAdminAccess();

        $key = trim($configKey);
        if (!in_array($key, self::ACTIVE_FEE_KEYS, true)) {
            return ['success' => false, 'message' => 'Configuration key is required.'];
        }

        try {
            $this->db->beginTransaction();
            $statement = $this->db->query(
                'UPDATE fee_configurations SET config_value = :config_value, updated_at = CURRENT_TIMESTAMP WHERE config_key = :config_key',
                [
                    'config_value' => number_format($newValue, 2, '.', ''),
                    'config_key' => $key,
                ]
            );

            $configExists = $this->db->query(
                'SELECT id FROM fee_configurations WHERE config_key = :config_key LIMIT 1',
                ['config_key' => $key]
            )->fetchColumn();
            if (!$configExists) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Fee configuration key not found.'];
            }

            if ($key === 'default_pickup_fee') {
                $this->db->query(
                    'INSERT INTO system_settings (setting_key, setting_value, updated_at)
                     VALUES (:setting_key, :setting_value, CURRENT_TIMESTAMP)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP',
                    ['setting_key' => $key, 'setting_value' => number_format($newValue, 2, '.', '')]
                );
            }

            $this->db->commit();
            return ['success' => true, 'message' => 'Fee configuration updated successfully.'];
        } catch (Throwable $e) {
            $this->db->rollBack();
            error_log('Fee configuration update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update the fee configuration.'];
        }
    }
}
