<?php
/**
 * Admin fee configuration controller.
 */

require_once __DIR__ . '/../../app/bootstrap.php';

class AdminFeeController
{
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
            'SELECT id, config_key, config_value, description, updated_at FROM fee_configurations ORDER BY config_key ASC'
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
        if ($key === '') {
            return ['success' => false, 'message' => 'Configuration key is required.'];
        }
        if ($key === 'renewal_notice_days' && ($newValue < 1 || $newValue > 30 || floor($newValue) !== $newValue)) {
            return ['success' => false, 'message' => 'Renewal notice lead time must be a whole number between 1 and 30 days.'];
        }

        try {
            $statement = $this->db->query(
                'UPDATE fee_configurations SET config_value = :config_value, updated_at = CURRENT_TIMESTAMP WHERE config_key = :config_key',
                [
                    'config_value' => number_format($newValue, 2, '.', ''),
                    'config_key' => $key,
                ]
            );

            if ($statement->rowCount() === 0) {
                return ['success' => false, 'message' => 'Fee configuration key not found.'];
            }

            return ['success' => true, 'message' => 'Fee configuration updated successfully.'];
        } catch (Throwable $e) {
            error_log('Fee configuration update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update the fee configuration.'];
        }
    }
}
