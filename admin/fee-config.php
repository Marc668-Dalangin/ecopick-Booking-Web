<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/AdminFeeController.php';

if (!Auth::check()) {
    header('Location: ' . APP_URL . '/admin-private-dnstl/login.php');
    exit;
}

if (Auth::userRole() !== 'admin') {
    header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
    exit;
}

$controller = new AdminFeeController();
$feedback = ['success' => false, 'message' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
        $feedback = ['success' => false, 'message' => 'Invalid security token. Please try again.'];
    } else {
        $configKey = trim((string) ($_POST['config_key'] ?? ''));
        $value = (float) ($_POST['config_value'] ?? 0.0);
        $feedback = $controller->updateFeeConfiguration($configKey, $value);
    }
}

$feeConfigs = $controller->getFeeConfigurations();
$feeLabels = [
    'default_pickup_fee' => 'Default Pickup Fee',
    'ecopick_service_fee_pct' => 'Ecopick Service Fee %',
    'junkshop_commission_pct' => 'Junkshop Commission %',
    'junkshop_registration_fee' => 'Junkshop Registration Fee',
    'renewal_fee_1_month' => 'Junkshop Renewal Fee - 1 month',
    'renewal_fee_6_months' => 'Junkshop Renewal Fee - 6 months',
    'renewal_fee_1_year' => 'Junkshop Renewal Fee - 1 year',
];
$feeMap = [];
foreach ($feeConfigs as $config) {
    $feeMap[(string) ($config['config_key'] ?? '')] = $config;
}

$pageTitle = 'Fee Configuration';
$activePage = 'fee-config';
ob_start();
?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-3"><i class="bi bi-cash-coin"></i> Fee Configuration</h4>
                <p class="text-muted mb-4">Adjust the default EcoPick platform fee, pickup fee, and junkshop commission percentages used across the marketplace.</p>

                <?php if (!empty($feedback['message'])): ?>
                    <div class="alert <?php echo $feedback['success'] ? 'alert-success' : 'alert-danger'; ?>" role="alert"><?php echo Validator::escape($feedback['message']); ?></div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <?php echo CSRF::field(); ?>
                    <div class="mb-3">
                        <label class="form-label" for="config_key">Configuration</label>
                        <select class="form-select" id="config_key" name="config_key" required>
                            <option value="">Select a value</option>
                            <option value="ecopick_service_fee_pct"><?php echo $feeLabels['ecopick_service_fee_pct']; ?></option>
                            <option value="default_pickup_fee"><?php echo $feeLabels['default_pickup_fee']; ?></option>
                            <option value="junkshop_commission_pct"><?php echo $feeLabels['junkshop_commission_pct']; ?></option>
                            <option value="junkshop_registration_fee"><?php echo $feeLabels['junkshop_registration_fee']; ?></option>
                            <option value="renewal_fee_1_month"><?php echo $feeLabels['renewal_fee_1_month']; ?></option>
                            <option value="renewal_fee_6_months"><?php echo $feeLabels['renewal_fee_6_months']; ?></option>
                            <option value="renewal_fee_1_year"><?php echo $feeLabels['renewal_fee_1_year']; ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="config_value">Value</label>
                        <input type="number" class="form-control" id="config_value" name="config_value" step="0.01" min="0" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Update fee</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-3"><i class="bi bi-sliders"></i> Current configuration</h4>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Setting</th>
                                <th>Current value</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feeConfigs as $config): ?>
                                <?php $configKey = (string) ($config['config_key'] ?? ''); ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo Validator::escape($feeLabels[$configKey] ?? $configKey); ?></td>
                                    <td><?php echo $configKey === 'ecopick_service_fee_pct' || $configKey === 'junkshop_commission_pct' ? '' : '₱'; ?><?php echo number_format((float)($config['config_value'] ?? 0), 2); ?><?php echo $configKey === 'ecopick_service_fee_pct' || $configKey === 'junkshop_commission_pct' ? '%' : ''; ?></td>
                                    <td class="small text-muted"><?php echo Validator::escape(date('M d, Y', strtotime($config['updated_at'] ?? date('Y-m-d')))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';
