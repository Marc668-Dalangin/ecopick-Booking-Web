<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/AdminFeatureController.php';

Auth::requireLogin();
if (Auth::userRole() !== 'junkshop') {
    header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
    exit;
}

$controller = new AdminFeatureController();
$db = Database::getInstance();
$plans = [
    '1_month' => ['config_key' => 'renewal_fee_1_month', 'label' => '1 Month', 'description' => 'Short-term partnership renewal.'],
    '6_months' => ['config_key' => 'renewal_fee_6_months', 'label' => '6 Months', 'description' => 'Half-year partnership renewal.'],
    '1_year' => ['config_key' => 'renewal_fee_1_year', 'label' => '1 Year', 'description' => 'Best value for a full year.'],
];

$feedback = $_SESSION['renewal_feedback'] ?? null;
unset($_SESSION['renewal_feedback']);
$profile = $db->query(
    'SELECT business_name, partnership_expires_at, renewal_status
     FROM junkshop_profiles
     WHERE account_id = :account_id',
    ['account_id' => Auth::userId()]
)->fetch();
$now = new DateTime();
$expDate = !empty($profile['partnership_expires_at']) ? new DateTime($profile['partnership_expires_at']) : null;
$isSubscriptionActive = ($expDate !== null && $expDate > $now);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_renewal'])) {
    if ($isSubscriptionActive) {
        $feedback = ['success' => false, 'message' => 'Renewal is available after your current partnership expires.'];
    } elseif (!CSRF::verify()) {
        $_SESSION['renewal_feedback'] = ['success' => false, 'message' => 'Your session expired. Please try again.'];
    } else {
        $selectedPlan = trim((string) ($_POST['selected_plan'] ?? ''));
        $paymentMethod = trim((string) ($_POST['payment_method'] ?? ''));
        $validPaymentMethods = ['Cash', 'GCash'];

        if (isset($plans[$selectedPlan]) && in_array($paymentMethod, $validPaymentMethods, true)) {
            $feedback = $controller->createRenewalPayment(
                Auth::userId(),
                $paymentMethod,
                $plans[$selectedPlan]['config_key']
            );
        } else {
            $feedback = ['success' => false, 'message' => 'Please select a valid plan and payment method.'];
        }

    }

    $_SESSION['renewal_feedback'] = $feedback;
    header('Location: ' . APP_URL . '/user-junkshop/renewal.php');
    exit;
}

$feeRows = $db->query(
    'SELECT config_key, config_value
     FROM fee_configurations
     WHERE config_key IN (:one_month, :six_months, :one_year)',
    [
        'one_month' => 'renewal_fee_1_month',
        'six_months' => 'renewal_fee_6_months',
        'one_year' => 'renewal_fee_1_year',
    ]
)->fetchAll();
foreach ($feeRows as $feeRow) {
    $key = (string) $feeRow['config_key'];
    foreach ($plans as &$plan) {
        if ($plan['config_key'] === $key) {
            $plan['amount'] = (float) $feeRow['config_value'];
        }
    }
}
unset($plan);

$payments = $db->query(
    'SELECT payment_type, amount, payment_method, payment_status, created_at
     FROM junkshop_partnership_payments
     WHERE junkshop_account_id = :account_id
     ORDER BY created_at DESC',
    ['account_id' => Auth::userId()]
)->fetchAll();

$pageTitle = 'Partnership Renewal';
$currentPage = 'renewal';
$userDisplayName = Auth::userName();
ob_start();
?>
<div class="alert alert-info d-flex align-items-center small mb-4" role="alert">
    <i class="bi bi-info-circle-fill me-2 fs-5"></i>
    <div><strong>3-Week Free Trial:</strong> Newly approved junkshop accounts receive a 3-week free trial. Standard renewal options apply after the trial expires.</div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h2 class="fw-bold mb-1">Partnership Renewal</h2>
                <p class="text-muted">Keep your EcoPick partnership current.</p>
                <?php if ($feedback): ?>
                    <div class="alert alert-<?php echo $feedback['success'] ? 'success' : 'danger'; ?>" role="alert">
                        <?php echo Validator::escape($feedback['message']); ?>
                    </div>
                <?php endif; ?>
                <dl class="mb-4">
                    <dt>Partnership status</dt>
                    <dd><?php echo Validator::escape($profile['renewal_status'] ?? 'Current'); ?></dd>
                    <dt>Expiry date</dt>
                    <dd><?php echo Validator::escape($profile['partnership_expires_at'] ?? 'Not assigned'); ?></dd>
                </dl>
                <form method="post">
                    <?php echo CSRF::field(); ?>
                    <input type="hidden" name="selected_plan" id="selected_plan_input" required>
                    <fieldset>
                        <legend class="h5 fw-bold">Choose a renewal plan</legend>
                        <div class="row g-3 mb-4">
                            <?php foreach ($plans as $key => $plan): ?>
                                <div class="col-12">
                                    <button class="plan-card border rounded p-3 d-flex justify-content-between align-items-center w-100 bg-white text-start" type="button" data-plan-key="<?php echo Validator::escape($key); ?>" aria-pressed="false">
                                        <span><strong><?php echo Validator::escape($plan['label']); ?></strong><small class="d-block text-muted"><?php echo Validator::escape($plan['description']); ?></small></span>
                                        <span class="fw-bold text-nowrap">₱<?php echo number_format((float) ($plan['amount'] ?? 0), 2); ?></span>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <div class="mb-3">
                        <label for="payment_method" class="form-label fw-bold">Select Payment Method</label>
                        <select name="payment_method" id="payment_method" class="form-select" required onchange="togglePaymentButtons()">
                            <option value="" selected disabled>Select method</option>
                            <option value="Cash">Cash</option>
                            <option value="GCash">GCash</option>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary d-none" id="cash_payment_button" type="submit" name="submit_renewal" value="1" <?php echo $isSubscriptionActive ? 'disabled' : ''; ?>>Submit renewal payment</button>
                        <button class="btn btn-primary d-none" id="gcash_payment_button" type="submit" name="submit_renewal" value="1" <?php echo $isSubscriptionActive ? 'disabled' : ''; ?>>Proceed to checkout</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-3">Payment records</h4>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Type</th><th>Amount</th><th>Method</th><th>Status</th><th>Submitted</th></tr></thead>
                        <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo Validator::escape($payment['payment_type']); ?></td>
                                <td>₱<?php echo number_format((float) $payment['amount'], 2); ?></td>
                                <td><?php echo Validator::escape($payment['payment_method'] ?: '-'); ?></td>
                                <td><?php echo Validator::escape($payment['payment_status']); ?></td>
                                <td><?php echo Validator::escape($payment['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$payments): ?><tr><td colspan="5" class="text-center text-muted py-4">No payment records found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function togglePaymentButtons() {
        const paymentMethod = document.getElementById('payment_method').value;
        const subscriptionActive = <?php echo $isSubscriptionActive ? 'true' : 'false'; ?>;
        document.getElementById('cash_payment_button').classList.toggle('d-none', paymentMethod !== 'Cash');
        document.getElementById('gcash_payment_button').classList.toggle('d-none', paymentMethod !== 'GCash');
        document.getElementById('cash_payment_button').disabled = subscriptionActive;
        document.getElementById('gcash_payment_button').disabled = subscriptionActive;
    }

    document.querySelectorAll('.plan-card').forEach((card) => {
        card.addEventListener('click', () => {
            document.querySelectorAll('.plan-card').forEach((planCard) => {
                planCard.classList.remove('border-primary', 'border-3', 'shadow-lg');
                planCard.setAttribute('aria-pressed', 'false');
            });
            card.classList.add('border-primary', 'border-3', 'shadow-lg');
            card.setAttribute('aria-pressed', 'true');
            document.getElementById('selected_plan_input').value = card.dataset.planKey;
        });
    });
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
