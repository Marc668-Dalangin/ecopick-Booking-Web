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
$feeService = new JunkshopFeeService();
$plans = [
    '1_month' => ['config_key' => 'renewal_fee_1_month', 'label' => '1 Month', 'description' => 'Short-term partnership renewal.'],
    '6_months' => ['config_key' => 'renewal_fee_6_months', 'label' => '6 Months', 'description' => 'Half-year partnership renewal.'],
    '1_year' => ['config_key' => 'renewal_fee_1_year', 'label' => '1 Year', 'description' => 'Best value for a full year.'],
];

$feedback = $_SESSION['renewal_feedback'] ?? null;
unset($_SESSION['renewal_feedback']);
$gateWarning = $_SESSION['subscription_gate_warning'] ?? null;
unset($_SESSION['subscription_gate_warning']);
$profile = $db->query(
    "SELECT jp.id, jp.business_name, jp.partnership_expires_at, jp.renewal_status,
            jp.has_used_welcome_bonus,
            (SELECT COUNT(*) FROM partnership_renewals pr
             WHERE pr.junkshop_account_id = jp.account_id AND pr.status = 'Approved') AS approved_subscription_count,
            (SELECT COUNT(*) FROM partnership_renewals registration
                            WHERE registration.junkshop_account_id = jp.account_id
                                AND registration.renewal_type = 'Registration'
                                AND registration.status = 'Approved') AS approved_registration_count
     FROM junkshop_profiles jp
     WHERE jp.account_id = :account_id",
    ['account_id' => Auth::userId()]
)->fetch();
$isFirstTime = $profile && (int) $profile['approved_subscription_count'] === 0;
$hasApprovedRegistration = $profile && (int) $profile['approved_registration_count'] > 0;
$now = new DateTime();
$expDate = !empty($profile['partnership_expires_at']) ? new DateTime($profile['partnership_expires_at']) : null;
$isExpired = $expDate === null || $expDate <= $now;
$displayStatus = $isFirstTime ? 'Pending Registration' : ($isExpired ? 'Expired' : 'Active');
$displayExpiry = $isFirstTime ? 'Not assigned' : ($expDate ? $expDate->format('M d, Y g:i A') : 'N/A');
$sectionTitle = $isFirstTime ? 'Select Registration Plan' : 'Choose a renewal plan';
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
$pendingFeePayment = $db->query(
    "SELECT 1 FROM junkshop_fee_payments
     WHERE junkshop_id = :junkshop_id AND status = 'Pending'
     LIMIT 1",
    ['junkshop_id' => Auth::userId()]
)->fetchColumn();
$hasPendingFeePayment = $pendingFeePayment !== false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_fee_payment'])) {
    $feedback = CSRF::verify()
        ? (!$hasApprovedRegistration
            ? ['success' => false, 'message' => 'An approved registration plan is required before outstanding fee payments can be submitted.']
            : ($hasPendingFeePayment
                ? ['success' => false, 'message' => 'You currently have a payment submission pending admin verification. You may submit another payment only after your pending request is Approved or Rejected.']
                : $controller->createFeePayment(Auth::userId(), (string) ($_POST['fee_payment_method'] ?? ''), (string) ($_POST['amount_submitted'] ?? ''), (string) ($_POST['fee_reference_number'] ?? ''), $_FILES['fee_receipt_image'] ?? null)))
        : ['success' => false, 'message' => 'Your session expired. Please try again.'];
    $_SESSION['renewal_feedback'] = $feedback;
    header('Location: ' . APP_URL . '/user-junkshop/renewal.php');
    exit;
}

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
$feeSummary = $feeService->getOutstandingSummary(Auth::userId());
$completedTransactions = $feeService->getCompletedTransactions(Auth::userId());
$feePayments = $db->query(
    'SELECT payment_method, reference_number, amount_submitted, amount_deducted, status, receipt_image, created_at
     FROM junkshop_fee_payments WHERE junkshop_id = :junkshop_id ORDER BY created_at DESC, id DESC',
    ['junkshop_id' => Auth::userId()]
)->fetchAll();

$pageTitle = 'Partnership Renewal';
$currentPage = 'renewal';
$userDisplayName = Auth::userName();
ob_start();
?>
<?php if ($gateWarning): ?>
    <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-lock-fill me-2 fs-5"></i>
        <div><?php echo Validator::escape($gateWarning); ?></div>
    </div>
<?php endif; ?>
<?php if ($isFirstTime): ?>
    <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
        <i class="bi bi-gift-fill me-2 fs-4"></i>
        <div><strong>First-Time Bonus:</strong> Subscribe to any plan today (1 Month, 6 Months, or 1 Year) and get an <strong>EXTRA 3 Weeks (21 Days)</strong> added to your plan for free!</div>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="h4 fw-bold mb-3">Outstanding EcoPick Fees</h2>
        <div class="row g-3">
            <div class="col-12 col-md-6 col-xl-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Total Outstanding Balance</div><strong class="fs-4">₱<?php echo number_format($feeSummary['total_outstanding'], 2); ?></strong></div></div>
            <div class="col-12 col-md-6 col-xl-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Service Fee Subtotal</div><strong>₱<?php echo number_format($feeSummary['service_fee_subtotal'], 2); ?></strong></div></div>
            <div class="col-12 col-md-6 col-xl-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Commission Subtotal</div><strong>₱<?php echo number_format($feeSummary['commission_subtotal'], 2); ?></strong></div></div>
            <div class="col-12 col-md-6 col-xl-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Maximum Allowed Fee Limit</div><strong>₱<?php echo number_format($feeSummary['maximum_allowed'], 2); ?></strong><div class="small text-muted mt-1">Remaining: ₱<?php echo number_format($feeSummary['remaining_allowance'], 2); ?></div></div></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-4"><span class="small text-muted">Approved payments deducted: ₱<?php echo number_format($feeSummary['approved_fee_payments'], 2); ?></span><?php if (!$hasPendingFeePayment): ?><div class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="<?php echo !$hasApprovedRegistration ? 'Requires an approved registration plan before outstanding fee payments can be submitted.' : ''; ?>"><button type="button" class="btn btn-primary<?php echo !$hasApprovedRegistration ? ' disabled' : ''; ?>"<?php echo !$hasApprovedRegistration ? ' disabled aria-disabled="true"' : ' data-bs-toggle="modal" data-bs-target="#feePaymentModal"'; ?>>Pay Outstanding Fees</button></div><?php endif; ?></div>
        <?php if (!$hasPendingFeePayment && !$hasApprovedRegistration): ?><small class="text-muted d-block mt-1"><i class="bi bi-info-circle me-1"></i>Requires an approved registration plan before outstanding fee payments can be submitted.</small><?php endif; ?>
        <?php if ($hasPendingFeePayment): ?><div class="alert alert-warning mt-4 mb-0" role="alert">You currently have a payment submission pending admin verification. You may submit another payment only after your pending request is Approved or Rejected.</div><?php endif; ?>
        <?php if ($feeSummary['is_locked']): ?><div class="alert alert-danger mt-4 mb-0" role="alert">Your outstanding fees have reached the maximum allowed limit. Settle your balance to accept new pickup requests.</div><?php endif; ?>
    </div>
</div>

<div class="modal fade" id="feePaymentModal" tabindex="-1" aria-labelledby="feePaymentModalLabel" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="post" enctype="multipart/form-data" id="feePaymentForm"><div class="modal-header"><h5 class="modal-title" id="feePaymentModalLabel">Pay Outstanding Fees</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body">
    <?php echo CSRF::field(); ?><input type="hidden" name="submit_fee_payment" value="1">
    <div class="mb-3"><label class="form-label fw-bold" for="fee_amount_submitted">Amount to submit</label><input class="form-control" type="number" name="amount_submitted" id="fee_amount_submitted" min="0.01" max="<?php echo number_format((float) $feeSummary['total_outstanding'], 2, '.', ''); ?>" step="0.01" value="<?php echo number_format((float) $feeSummary['total_outstanding'], 2, '.', ''); ?>" required></div>
    <div class="mb-3"><label class="form-label fw-bold" for="fee_payment_method">Payment method</label><select class="form-select" name="fee_payment_method" id="fee_payment_method" required><option value="Cash">Cash</option><option value="GCash">GCash</option></select></div>
    <div id="feeGcashFields" class="d-none"><div class="mb-3"><label class="form-label fw-bold" for="fee_reference_number">GCash reference number</label><input class="form-control" type="text" name="fee_reference_number" id="fee_reference_number" inputmode="numeric" maxlength="13" minlength="13" pattern="[0-9]{13}" autocomplete="off"></div><div class="mb-3"><label class="form-label fw-bold" for="fee_receipt_image">Receipt screenshot</label><input class="form-control" type="file" name="fee_receipt_image" id="fee_receipt_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><div class="form-text">JPG, JPEG, PNG, or WEBP only, maximum 3 MB.</div></div></div>
    </div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Submit Payment</button></div></form></div></div></div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold text-white"><i class="bi bi-table me-2"></i>Completed Transactions Contributing to Fees</h6>
        <button class="btn btn-outline-light btn-sm fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCompletedTransactions" aria-expanded="true" aria-controls="collapseCompletedTransactions" id="btnToggleTransactions">
            <i class="bi bi-chevron-up me-1" id="iconToggleTransactions"></i>
            <span id="textToggleTransactions">Hide Details</span>
        </button>
    </div>
    <div class="collapse show" id="collapseCompletedTransactions">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light"><tr><th>Booking</th><th>Seller</th><th>Date</th><th>Payment</th><th>Reference</th><th>Weight (kg)</th><th>Value (₱)</th><th>Pickup fee</th><th class="text-end text-success">EcoPick service fee</th><th class="text-warning-emphasis">Commission</th><th>Seller net</th></tr></thead>
                <tbody>
                <?php foreach ($completedTransactions as $transaction): ?>
                    <tr>
                        <td><?php echo Validator::escape($transaction['booking_reference']); ?></td>
                        <td><?php echo Validator::escape($transaction['seller_name']); ?></td>
                        <td><?php echo Validator::escape(date('M d, Y g:i A', strtotime($transaction['completed_at']))); ?></td>
                        <td><?php echo Validator::escape($transaction['payment_method'] ?: '-'); ?></td>
                        <td><?php echo Validator::escape($transaction['reference_number'] ?: '-'); ?></td>
                        <td><?php echo number_format((float) $transaction['actual_weight_kg'], 2); ?></td>
                        <td>₱<?php echo number_format((float) $transaction['final_recyclable_value'], 2); ?></td>
                        <td>₱<?php echo number_format((float) $transaction['pickup_fee'], 2); ?></td>
                        <td>₱<?php echo number_format((float) $transaction['ecopick_service_fee'], 2); ?></td>
                        <td>₱<?php echo number_format((float) $transaction['transaction_commission'], 2); ?></td>
                        <td>₱<?php echo number_format((float) $transaction['final_seller_amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$completedTransactions): ?><tr><td colspan="11" class="text-center text-muted py-4">No completed transactions found.</td></tr><?php endif; ?>
                </tbody>
                </table>
            </div>
        </div>
    </div>
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
                    <dd>
                        <span class="badge <?php echo $isFirstTime ? 'bg-secondary' : ($displayStatus === 'Active' ? 'bg-success' : 'bg-danger'); ?>">
                            <?php echo Validator::escape($displayStatus); ?>
                        </span>
                    </dd>
                    <dt>Expiry date</dt>
                    <dd><?php echo Validator::escape($displayExpiry); ?></dd>
                </dl>
                <form id="renewalForm" action="renewal.php" method="POST" enctype="multipart/form-data">
                    <?php echo CSRF::field(); ?>
                    <div class="mb-4">
                        <label for="plan_type" class="form-label fw-bold"><?php echo Validator::escape($sectionTitle); ?></label>
                        <select name="plan_type" id="plan_type" class="form-select" required <?php echo $canSubmitRenewal ? '' : 'disabled'; ?>>
                            <option value="" disabled>Select plan</option>
                            <option value="Monthly" selected data-amount="<?php echo number_format((float) ($plans['1_month']['amount'] ?? 0), 2, '.', ''); ?>">Monthly Plan<?php echo $isFirstTime ? ' (1 Month)' : ''; ?> - ₱<?php echo number_format((float) ($plans['1_month']['amount'] ?? 0), 2); ?><?php echo $isFirstTime ? ' + 3 weeks (21 days)' : ''; ?></option>
                            <option value="Quarterly" data-amount="<?php echo number_format((float) ($plans['6_months']['amount'] ?? 0), 2, '.', ''); ?>">Quarterly Plan<?php echo $isFirstTime ? ' (6 Months)' : ''; ?> - ₱<?php echo number_format((float) ($plans['6_months']['amount'] ?? 0), 2); ?><?php echo $isFirstTime ? ' + 3 weeks (21 days)' : ''; ?></option>
                            <option value="Annual" data-amount="<?php echo number_format((float) ($plans['1_year']['amount'] ?? 0), 2, '.', ''); ?>">Annual Plan<?php echo $isFirstTime ? ' (1 Year)' : ''; ?> - ₱<?php echo number_format((float) ($plans['1_year']['amount'] ?? 0), 2); ?><?php echo $isFirstTime ? ' + 3 weeks (21 days)' : ''; ?></option>
                        </select>
                        <?php if ($isFirstTime): ?>
                            <div id="selectedPlanBonus" class="badge bg-success mt-2">Includes Bonus: + 3 weeks (21 days) free</div>
                        <?php endif; ?>
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
                    <button type="submit" name="process_renewal_submit" value="1" class="btn btn-primary mt-3" <?php echo $canSubmitRenewal ? '' : 'disabled'; ?>><?php echo $hasApprovedRegistration ? 'Submit Renewal Payment' : 'Submit Registration Payment'; ?></button>
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
                                <td><?php echo Validator::escape(date('M d, Y g:i A', strtotime($payment['created_at']))); ?></td>
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
    function addPaymentRecordsCollapse() {
        const heading = Array.from(document.querySelectorAll('h4')).find(function (element) {
            return element.textContent.trim() === 'Payment records';
        });
        const panel = heading?.nextElementSibling;
        if (!heading || !panel || !panel.classList.contains('table-responsive')) return;
        panel.id = 'paymentRecordsCollapse';
        panel.classList.add('collapse', 'show');
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'btn btn-sm btn-outline-secondary ms-2 align-middle';
        toggle.setAttribute('data-bs-toggle', 'collapse');
        toggle.setAttribute('data-bs-target', '#paymentRecordsCollapse');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-controls', 'paymentRecordsCollapse');
        toggle.innerHTML = '<i class="bi bi-chevron-up me-1"></i> Hide Details';
        heading.appendChild(toggle);
        panel.addEventListener('shown.bs.collapse', function () {
            toggle.innerHTML = '<i class="bi bi-chevron-up me-1"></i> Hide Details';
            toggle.setAttribute('aria-expanded', 'true');
        });
        panel.addEventListener('hidden.bs.collapse', function () {
            toggle.innerHTML = '<i class="bi bi-chevron-down me-1"></i> Show Details';
            toggle.setAttribute('aria-expanded', 'false');
        });
    }

    addPaymentRecordsCollapse();

    const completedTransactionsCollapse = document.getElementById('collapseCompletedTransactions');
    const toggleTransactionsText = document.getElementById('textToggleTransactions');
    const toggleTransactionsIcon = document.getElementById('iconToggleTransactions');

    completedTransactionsCollapse?.addEventListener('shown.bs.collapse', function () {
        toggleTransactionsText.textContent = 'Hide Details';
        toggleTransactionsIcon.className = 'bi bi-chevron-up me-1';
    });
    completedTransactionsCollapse?.addEventListener('hidden.bs.collapse', function () {
        toggleTransactionsText.textContent = 'Show Details';
        toggleTransactionsIcon.className = 'bi bi-chevron-down me-1';
    });

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

    const planType = document.getElementById('plan_type');
    const selectedPlanBonus = document.getElementById('selectedPlanBonus');
    planType.addEventListener('change', function () {
        document.getElementById('plan_amount').value = this.options[this.selectedIndex].dataset.amount || '0.00';
        if (selectedPlanBonus) {
            selectedPlanBonus.classList.remove('d-none');
            selectedPlanBonus.textContent = 'Includes Bonus: + 3 weeks (21 days) free';
        }
    });
    const feeMethod = document.getElementById('fee_payment_method');
    const feeGcashFields = document.getElementById('feeGcashFields');
    const feeReference = document.getElementById('fee_reference_number');
    const feeReceipt = document.getElementById('fee_receipt_image');
    function updateFeePaymentFields() {
        const isGcash = feeMethod.value === 'GCash';
        feeGcashFields.classList.toggle('d-none', !isGcash);
        feeReference.required = isGcash;
        feeReceipt.required = isGcash;
        if (!isGcash) { feeReference.value = ''; feeReceipt.value = ''; }
    }
    feeMethod.addEventListener('change', updateFeePaymentFields);
    feeReference.addEventListener('input', function () { this.value = this.value.replace(/[^0-9]/g, '').slice(0, 13); });
    feeReceipt.addEventListener('change', function () { const file = this.files[0]; if (file && (file.size > 3 * 1024 * 1024 || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))) { alert('Please select one JPG, JPEG, PNG, or WEBP image no larger than 3 MB.'); this.value = ''; } });
    document.getElementById('feePaymentForm').addEventListener('submit', function (event) { if (feeMethod.value === 'GCash' && !/^\d{13}$/.test(feeReference.value)) { event.preventDefault(); alert('The GCash reference number must contain exactly 13 digits.'); } });
    updateFeePaymentFields();
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
