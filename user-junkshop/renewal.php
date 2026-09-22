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
    'SELECT id, business_name, partnership_expires_at, renewal_status
     FROM junkshop_profiles
     WHERE account_id = :account_id',
    ['account_id' => Auth::userId()]
)->fetch();
$now = new DateTime();
$expDate = !empty($profile['partnership_expires_at']) ? new DateTime($profile['partnership_expires_at']) : null;
$isExpired = $expDate === null || $expDate <= $now;
$pendingRequest = $db->query(
    "SELECT id, created_at, plan_type, amount, payment_method
     FROM partnership_renewals
     WHERE junkshop_account_id = :account_id
       AND status IN ('Pending', 'Pending Reconciliation')
     ORDER BY created_at DESC
     LIMIT 1",
    ['account_id' => Auth::userId()]
)->fetch();
$hasPendingRequest = !empty($pendingRequest);
$canSubmitRenewal = $isExpired && !$hasPendingRequest;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['process_renewal_submit']) || isset($_POST['submit_renewal']))) {
    if (!$canSubmitRenewal) {
        $feedback = ['success' => false, 'message' => $hasPendingRequest
            ? 'You already have a pending renewal request awaiting Admin reconciliation.'
            : 'Renewal submission is available after your partnership plan expires.'];
    } elseif (!CSRF::verify()) {
        $_SESSION['renewal_feedback'] = ['success' => false, 'message' => 'Your session expired. Please try again.'];
    } else {
        $postedPlanType = trim((string) ($_POST['plan_type'] ?? ''));
        $selectedPlan = match ($postedPlanType) {
            'Monthly', '1 Month' => '1_month',
            'Quarterly', '6 Months' => '6_months',
            'Annual', '1 Year' => '1_year',
            default => trim((string) ($_POST['selected_plan'] ?? '')),
        };
        $paymentMethod = trim((string) ($_POST['payment_method'] ?? ''));
        $validPaymentMethods = ['Cash', 'GCash'];

        if (isset($plans[$selectedPlan])
            && in_array($paymentMethod, $validPaymentMethods, true)) {
            $feedback = $controller->createRenewalPayment(
                Auth::userId(),
                $paymentMethod,
                $plans[$selectedPlan]['config_key'],
                (string) ($_POST['reference_number'] ?? ''),
                $_FILES['receipt_image'] ?? null
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
    'SELECT payment_type, amount, payment_method, payment_status, NULL AS reference_number, NULL AS receipt_image, created_at
     FROM junkshop_partnership_payments
     WHERE junkshop_account_id = :account_id
    UNION ALL
     SELECT transaction_type AS payment_type, amount, payment_method, status AS payment_status, reference_number, receipt_image, created_at
     FROM payment_records
     WHERE junkshop_id = :payment_account_id
     ORDER BY created_at DESC',
    ['account_id' => Auth::userId(), 'payment_account_id' => Auth::userId()]
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
                <?php if (!$isExpired && $expDate): ?>
                    <div class="alert alert-info" role="alert">
                        Your partnership plan is currently active until <strong><?php echo Validator::escape($expDate->format('F j, Y g:i A')); ?></strong>. Renewal submission will open once your plan expires.
                    </div>
                <?php elseif ($hasPendingRequest): ?>
                    <div class="alert alert-warning" role="alert">
                        You have an ongoing renewal request submitted on <strong><?php echo Validator::escape((new DateTime($pendingRequest['created_at']))->format('F j, Y g:i A')); ?></strong> currently pending Admin reconciliation. You cannot submit another renewal request until your pending submission is Approved or Rejected by Admin.
                    </div>
                <?php endif; ?>
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
                <form id="renewalForm" action="renewal.php" method="POST" enctype="multipart/form-data">
                    <?php echo CSRF::field(); ?>
                    <div class="mb-4">
                        <label for="plan_type" class="form-label fw-bold">Choose a renewal plan</label>
                        <select name="plan_type" id="plan_type" class="form-select" required <?php echo $canSubmitRenewal ? '' : 'disabled'; ?>>
                            <option value="" disabled>Select plan</option>
                            <option value="Monthly" selected data-amount="<?php echo number_format((float) ($plans['1_month']['amount'] ?? 0), 2, '.', ''); ?>">Monthly Plan - ₱<?php echo number_format((float) ($plans['1_month']['amount'] ?? 0), 2); ?></option>
                            <option value="Quarterly" data-amount="<?php echo number_format((float) ($plans['6_months']['amount'] ?? 0), 2, '.', ''); ?>">Quarterly Plan - ₱<?php echo number_format((float) ($plans['6_months']['amount'] ?? 0), 2); ?></option>
                            <option value="Annual" data-amount="<?php echo number_format((float) ($plans['1_year']['amount'] ?? 0), 2, '.', ''); ?>">Annual Plan - ₱<?php echo number_format((float) ($plans['1_year']['amount'] ?? 0), 2); ?></option>
                        </select>
                    </div>
                    <input type="hidden" name="amount" id="plan_amount" value="<?php echo number_format((float) ($plans['1_month']['amount'] ?? 0), 2, '.', ''); ?>">
                    <div class="mb-3">
                        <div class="form-label fw-bold">Select Payment Method</div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" value="Cash" id="pay_cash" checked required <?php echo $canSubmitRenewal ? '' : 'disabled'; ?>>
                            <label class="form-check-label" for="pay_cash">Cash Payment</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" value="GCash" id="pay_gcash" required <?php echo $canSubmitRenewal ? '' : 'disabled'; ?>>
                            <label class="form-check-label" for="pay_gcash">GCash Payment</label>
                        </div>
                    </div>
                    <div id="gcashFields" class="border rounded p-3 mb-3 d-none">
                        <div class="mb-3">
                            <label for="reference_number" class="form-label fw-bold">GCash Reference Number</label>
                            <input type="text" name="reference_number" id="reference_number" class="form-control" inputmode="numeric" maxlength="13" minlength="13" pattern="[0-9]{13}" placeholder="e.g. 1002345678901" oninput="sanitizeGcashReference(this)" autocomplete="off" disabled>
                        </div>
                        <div>
                            <label for="receipt_image" class="form-label fw-bold">Receipt Screenshot</label>
                            <input type="file" name="receipt_image" id="receipt_image" class="form-control" accept="image/jpeg,image/png,image/webp" disabled>
                            <div class="form-text">JPG, PNG, or WEBP only, maximum 3 MB.</div>
                        </div>
                    </div>
                    <button type="submit" name="process_renewal_submit" value="1" class="btn btn-primary mt-3" <?php echo $canSubmitRenewal ? '' : 'disabled'; ?>>Submit Renewal Payment</button>
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
                        <thead><tr><th>Type</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th><th>Submitted</th></tr></thead>
                        <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo Validator::escape($payment['payment_type']); ?></td>
                                <td>₱<?php echo number_format((float) $payment['amount'], 2); ?></td>
                                <td><?php echo Validator::escape($payment['payment_method'] ?: '-'); ?></td>
                                <td><?php echo Validator::escape($payment['reference_number'] ?: '-'); ?></td>
                                <td><?php echo Validator::escape($payment['payment_status']); ?></td>
                                <td><?php echo Validator::escape($payment['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$payments): ?><tr><td colspan="6" class="text-center text-muted py-4">No payment records found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    const paymentMethodInputs = document.querySelectorAll('input[name="payment_method"]');
    const gcashFields = document.getElementById('gcashFields');
    const referenceInput = document.getElementById('reference_number');
    const receiptInput = document.getElementById('receipt_image');
    const maxReceiptSize = 3 * 1024 * 1024;
    const allowedReceiptTypes = ['image/jpeg', 'image/png', 'image/webp'];

    function sanitizeGcashReference(input) {
        input.value = input.value.replace(/[^0-9]/g, '').slice(0, 13);
    }

    function updatePaymentFields() {
        const isGcash = document.querySelector('input[name="payment_method"]:checked')?.value === 'GCash';
        gcashFields.classList.toggle('d-none', !isGcash);
        referenceInput.disabled = !isGcash;
        receiptInput.disabled = !isGcash;
        referenceInput.required = isGcash;
        receiptInput.required = isGcash;
        if (!isGcash) {
            referenceInput.value = '';
            receiptInput.value = '';
        }
    }

    paymentMethodInputs.forEach((input) => input.addEventListener('change', updatePaymentFields));
    document.getElementById('renewalForm').addEventListener('submit', function (event) {
        const isGcash = document.querySelector('input[name="payment_method"]:checked')?.value === 'GCash';
        if (isGcash && !/^\d{13}$/.test(referenceInput.value)) {
            event.preventDefault();
            alert('The GCash reference number must contain exactly 13 digits.');
            referenceInput.focus();
        }
    });
    receiptInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        if (this.files.length !== 1 || file.size > maxReceiptSize || !allowedReceiptTypes.includes(file.type)) {
            alert('Please select one JPG, PNG, or WEBP receipt image no larger than 3 MB.');
            this.value = '';
        }
    });
    updatePaymentFields();

    document.getElementById('plan_type').addEventListener('change', function () {
        document.getElementById('plan_amount').value = this.options[this.selectedIndex].dataset.amount || '0.00';
    });
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
