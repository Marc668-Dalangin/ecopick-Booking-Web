<?php
/**
 * Fee and pricing calculation engine for EcoPick.
 */

class FeeCalculator
{
    public const DEFAULT_SERVICE_FEE_PCT = 0.05;
    public const DEFAULT_PICKUP_FEE = 5.00;
    public const DEFAULT_JUNKSHOP_COMMISSION_PCT = 0.025;

    /**
     * Fetch all configured fee values keyed by config name.
     *
     * @return array<string, float>
     */
    public static function getConfigs(): array
    {
        $configs = [];
        try {
            $db = Database::getInstance();
            $stmt = $db->query('SELECT config_key, config_value FROM fee_configurations');
            $rows = $stmt->fetchAll();
        } catch (PDOException $exception) {
            error_log('Fee configuration lookup failed: ' . $exception->getMessage());
            return $configs;
        }

        foreach ($rows as $row) {
            $key = (string) ($row['config_key'] ?? '');
            $value = (float) ($row['config_value'] ?? 0.0);
            if ($key !== '') {
                $configs[$key] = $value;
            }
        }

        return $configs;
    }

    public static function getDefaultPickupFee(): float
    {
        try {
            $db = Database::getInstance();
            $configuredFee = $db->query(
                "SELECT setting_value FROM system_settings WHERE setting_key = 'default_pickup_fee' LIMIT 1"
            )->fetchColumn();
            if (is_numeric($configuredFee)) {
                return max(0.0, (float) $configuredFee);
            }

            $legacyFee = $db->query(
                "SELECT config_value FROM fee_configurations WHERE config_key = 'default_pickup_fee' LIMIT 1"
            )->fetchColumn();
            if (is_numeric($legacyFee)) {
                return max(0.0, (float) $legacyFee);
            }
        } catch (PDOException $exception) {
            error_log('Pickup fee lookup failed: ' . $exception->getMessage());
        }

        return self::DEFAULT_PICKUP_FEE;
    }

    public static function calculatePickupFee(float $distanceInKm, float $baseFee): float
    {
        $distance = max(0.0, $distanceInKm);
        $halfKilometerUnits = (int) ceil($distance / 0.5);

        return round($halfKilometerUnits * max(0.0, $baseFee), 2);
    }

    /**
     * Calculate an estimated booking value and net amount for a seller.
     *
     * @param float $estWeight
     * @param float $buyingPricePerKg
     * @param float $pickupFee
     * @return array<string, float>
     */
    public static function calculateEstimate(float $estWeight, float $buyingPricePerKg, float $pickupFee): array
    {
        $weight = max(0.0, $estWeight);
        $buyingPrice = max(0.0, $buyingPricePerKg);
        $pickupFeeValue = max(0.0, $pickupFee);

        $configs = self::getConfigs();
        $serviceFeePct = isset($configs['ecopick_service_fee_pct'])
            ? (float) $configs['ecopick_service_fee_pct'] / 100.0
            : self::DEFAULT_SERVICE_FEE_PCT;

        $estimatedRecyclableValue = $weight * $buyingPrice;
        $ecopickServiceFee = $estimatedRecyclableValue * $serviceFeePct;
        $estimatedNetAmount = $estimatedRecyclableValue - $pickupFeeValue - $ecopickServiceFee;

        return [
            'estimated_recyclable_value' => round($estimatedRecyclableValue, 2),
            'pickup_fee' => round($pickupFeeValue, 2),
            'ecopick_service_fee' => round($ecopickServiceFee, 2),
            'estimated_net_amount' => round($estimatedNetAmount, 2),
            'service_fee_pct' => round($serviceFeePct * 100.0, 2),
        ];
    }

    /**
     * Calculate the final settlement amount based on actual weight and final buying price.
     *
     * @param float $actualWeight
     * @param float $buyingPricePerKg
     * @param float $pickupFee
     * @return array<string, float>
     */
    public static function calculateFinalSettlement(float $actualWeight, float $buyingPricePerKg, float $pickupFee): array
    {
        $weight = max(0.0, $actualWeight);
        $buyingPrice = max(0.0, $buyingPricePerKg);
        $pickupFeeValue = max(0.0, $pickupFee);

        $configs = self::getConfigs();
        $serviceFeePct = isset($configs['ecopick_service_fee_pct'])
            ? (float) $configs['ecopick_service_fee_pct'] / 100.0
            : self::DEFAULT_SERVICE_FEE_PCT;
        $commissionPct = isset($configs['junkshop_commission_pct'])
            ? (float) $configs['junkshop_commission_pct'] / 100.0
            : self::DEFAULT_JUNKSHOP_COMMISSION_PCT;

        $finalRecyclableValue = $weight * $buyingPrice;
        $ecopickServiceFee = $finalRecyclableValue * $serviceFeePct;
        $finalSellerAmount = $finalRecyclableValue - $pickupFeeValue - $ecopickServiceFee;
        $transactionCommission = $finalRecyclableValue * $commissionPct;

        return [
            'final_recyclable_value' => round($finalRecyclableValue, 2),
            'pickup_fee' => round($pickupFeeValue, 2),
            'ecopick_service_fee' => round($ecopickServiceFee, 2),
            'final_seller_amount' => round($finalSellerAmount, 2),
            'transaction_commission' => round($transactionCommission, 2),
            'service_fee_pct' => round($serviceFeePct * 100.0, 2),
            'commission_pct' => round($commissionPct * 100.0, 2),
        ];
    }

    /**
     * Calculate settlement from each accepted material line.
     *
     * @param array<int, array{material_id:int, actual_weight_kg:float, buying_price_per_kg:float}> $materials
     * @return array<string, mixed>
     */
    public static function calculateMaterialSettlement(array $materials, float $pickupFee): array
    {
        $configs = self::getConfigs();
        $serviceFeePct = isset($configs['ecopick_service_fee_pct'])
            ? (float) $configs['ecopick_service_fee_pct'] / 100.0
            : self::DEFAULT_SERVICE_FEE_PCT;
        $commissionPct = isset($configs['junkshop_commission_pct'])
            ? (float) $configs['junkshop_commission_pct'] / 100.0
            : self::DEFAULT_JUNKSHOP_COMMISSION_PCT;

        $lines = [];
        $totalWeight = 0.0;
        $finalValue = 0.0;
        foreach ($materials as $material) {
            $weight = max(0.0, (float) ($material['actual_weight_kg'] ?? 0.0));
            $price = max(0.0, (float) ($material['buying_price_per_kg'] ?? 0.0));
            $value = round($weight * $price, 2);
            $totalWeight += $weight;
            $finalValue += $value;
            $lines[] = [
                'material_id' => (int) ($material['material_id'] ?? 0),
                'actual_weight_kg' => round($weight, 2),
                'buying_price_per_kg' => round($price, 2),
                'final_material_value' => $value,
            ];
        }

        $serviceFee = round($finalValue * $serviceFeePct, 2);
        $pickupFeeValue = round(max(0.0, $pickupFee), 2);
        return [
            'materials' => $lines,
            'actual_weight_kg' => round($totalWeight, 2),
            'final_recyclable_value' => round($finalValue, 2),
            'pickup_fee' => $pickupFeeValue,
            'ecopick_service_fee' => $serviceFee,
            'final_seller_amount' => round($finalValue - $pickupFeeValue - $serviceFee, 2),
            'transaction_commission' => round($finalValue * $commissionPct, 2),
            'service_fee_pct' => round($serviceFeePct * 100.0, 2),
            'commission_pct' => round($commissionPct * 100.0, 2),
        ];
    }
}
