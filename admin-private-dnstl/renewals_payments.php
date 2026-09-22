<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/AdminFeatureController.php';

if (!Auth::check()) {
    header('Location: ' . APP_URL . '/admin-private-dnstl/login.php');
    exit;
}
if (Auth::userRole() !== 'admin') {
    header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
    exit;
}

$automaticDispatch = null;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    defer_after_response(static function (): void {
        NotificationService::sendRenewalReminders();
    });
}

$controller = new AdminFeatureController();
$feeService = new JunkshopFeeService();
$feedback = null;
$errorMessage = null;
$action = (string) ($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::verify()) {
    if ($action === 'renewal_notice') {
        $feedback = $controller->updateRenewalNoticeDays((int) ($_POST['renewal_notice_days'] ?? 0));
        if ($feedback['success']) {
            defer_after_response(static function (): void {
                NotificationService::sendRenewalReminders();
            });
            $feedback['message'] .= ' Renewal notifications queued for background delivery.';
        }
    } elseif ($action === 'max_fee_threshold') {
        $threshold = trim((string) ($_POST['max_junkshop_fee_threshold'] ?? ''));
        if ($threshold === '' || !is_numeric($threshold) || (float) $threshold < 0) {
            $feedback = ['success' => false, 'message' => 'Enter a valid non-negative maximum fee threshold.'];
        } else {
            $db = Database::getInstance();
            $db->query(
                'INSERT INTO system_settings (setting_key, setting_value)
                 VALUES (:setting_key, :setting_value)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                [
                    'setting_key' => 'max_junkshop_fee_threshold',
                    'setting_value' => number_format((float) $threshold, 2, '.', ''),
                ]
            );
            $feedback = ['success' => true, 'message' => 'Maximum outstanding fee threshold updated.'];
        }
    } elseif ($action === 'expiry') {
        $customExpiryDate = trim((string) ($_POST['custom_expiry_date'] ?? ''));
        $customExpiryTime = trim((string) ($_POST['custom_expiry_time'] ?? ''));
        $enableExpiryTime = isset($_POST['enable_expiry_time']) && $_POST['enable_expiry_time'] === '1';
        $feedback = $controller->setExpiry(
            (int) ($_POST['junkshop_account_id'] ?? 0),
            $customExpiryDate,
            $customExpiryTime,
            $enableExpiryTime
        );
        if ($feedback['success']) {
            defer_after_response(static function (): void {
                NotificationService::sendRenewalReminders();
            });
            $feedback['message'] .= ' Renewal notifications queued for background delivery.';
        }
    } elseif ($action === 'reconcile') {
        $rejectionReason = trim((string) ($_POST['rejection_reason'] ?? $_POST['reason_preset'] ?? ''));
        $customReason = trim((string) ($_POST['rejection_notes'] ?? $_POST['reason_notes'] ?? ''));
        if ((string) ($_POST['payment_status'] ?? '') === 'Rejected' && $rejectionReason === 'Other / Custom Reason' && $customReason !== '') {
            $rejectionReason .= ': ' . $customReason;
        } elseif ($customReason !== '') {
            $rejectionReason .= ' - ' . $customReason;
        }
        $feedback = $controller->reconcilePartnershipPayment((int) $_POST['payment_id'], (string) $_POST['payment_status'], (string) ($_POST['payment_reference'] ?? ''), $rejectionReason);
    } elseif ($action === 'fee_reconcile') {
        $feeReason = trim((string) ($_POST['rejection_reason'] ?? $_POST['reason_preset'] ?? ''));
        $feeCustomReason = trim((string) ($_POST['rejection_notes'] ?? $_POST['reason_notes'] ?? ''));
        if ($feeReason === 'Other / Custom Reason' && $feeCustomReason !== '') {
            $feeReason .= ': ' . $feeCustomReason;
        } elseif ($feeCustomReason !== '') {
            $feeReason .= ' - ' . $feeCustomReason;
        }
        $feedback = $controller->reconcileFeePayment((int) ($_POST['fee_payment_id'] ?? $_POST['payment_id'] ?? 0), (string) ($_POST['fee_status'] ?? $_POST['payment_status'] ?? ''), (string) ($_POST['amount_deducted'] ?? '0'), $feeReason);
    } elseif ($action === 'commission') {
        $feedback = $controller->reconcileCommissionPayment((int) $_POST['payment_id'], (string) $_POST['payment_status'], (string) ($_POST['payment_reference'] ?? ''));
    }
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($feedback ?? ['success' => false, 'message' => 'Invalid action.']);
        exit;
    }
}

if ($feedback === null && is_array($automaticDispatch) && $automaticDispatch['failed'] > 0) {
    $feedback = [
        'success' => false,
        'message' => 'Automatic renewal notification dispatch failed. ' . implode(' ', $automaticDispatch['details']),
    ];
}

$loadRenewalData = static function (callable $loader, mixed $fallback) use (&$errorMessage): mixed {
    try {
        return $loader();
    } catch (PDOException $exception) {
        error_log('Renewals & Payments Error: ' . $exception->getMessage());
        $errorMessage = 'Unable to fetch some renewal records. Please ensure your db.sql schema is updated.';
        return $fallback;
    }
};

$renewalNoticeDays = $loadRenewalData(
    static fn (): int => $controller->getRenewalNoticeDays(),
    1
);
$maximumFeeThreshold = $loadRenewalData(
    static fn (): float => $feeService->getMaximumFeeThreshold(),
    5000.00
);
$junkshops = $loadRenewalData(
    static fn (): array => $controller->listApprovedJunkshops(),
    []
);
$payments = $loadRenewalData(
    static fn (): array => $controller->listPartnershipPayments(),
    []
);
$feePayments = $loadRenewalData(
    static fn (): array => $controller->listFeePayments(),
    []
);
$pageTitle = 'Renewals & Payments';
$activePage = 'partnership-payments';
ob_start();
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Renewals &amp; Payments</h2>
                <p class="text-muted mb-0">Manage partnership periods, renewals, and payment reconciliation.</p>
            </div>
            <span class="badge bg-warning-subtle text-warning"><?php echo count(array_filter($payments, fn ($payment) => !in_array($payment['status'], ['Approved', 'Rejected'], true))); ?> outstanding</span>
        </div>

        <div class="border rounded p-3 mb-4">
            <h4 class="h5 fw-bold mb-1">Renewal Email Notifications</h4>
            <p class="text-muted small mb-3">Approved junkshops receive one email reminder for each subscription expiration.</p>
            <form method="post" class="js-loading-form">
                <?php echo CSRF::field(); ?>
                <input type="hidden" name="action" value="renewal_notice">
                <div class="mb-3">
                    <label for="renewal_notice_days" class="form-label fw-bold">Expiration Notice Lead Time (Days)</label>
                    <input type="number" name="renewal_notice_days" id="renewal_notice_days" class="form-control" min="1" max="30" value="<?php echo (int) $renewalNoticeDays; ?>" required>
                    <small class="text-muted">Example: Setting '1' sends an automated email warning 1 day (24 hours) prior to the exact expiration date.</small>
                </div>
                <button class="btn btn-primary" type="submit">Save notification setting</button>
            </form>
        </div>
        <div class="border rounded p-3 mb-4">
            <h4 class="h5 fw-bold mb-1">Junkshop Outstanding Fee Threshold</h4>
            <p class="text-muted small mb-3">Junkshops cannot accept new pickup requests when completed-transaction fees reach this limit.</p>
            <form method="post" class="js-loading-form">
                <?php echo CSRF::field(); ?>
                <input type="hidden" name="action" value="max_fee_threshold">
                <div class="mb-3">
                    <label for="max_junkshop_fee_threshold" class="form-label fw-bold">Maximum Allowed Outstanding Fees (₱)</label>
                    <input type="number" name="max_junkshop_fee_threshold" id="max_junkshop_fee_threshold" class="form-control" min="0" step="0.01" value="<?php echo number_format((float) $maximumFeeThreshold, 2, '.', ''); ?>" required>
                </div>
                <button class="btn btn-primary" type="submit">Save fee threshold</button>
            </form>
        </div>
        <?php if ($feedback): ?>
            <div class="alert alert-<?php echo $feedback['success'] ? 'success' : 'danger'; ?>"><?php echo Validator::escape($feedback['message']); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-warning" role="alert"><?php echo Validator::escape($errorMessage); ?></div>
        <?php endif; ?>

        <div class="alert alert-info d-flex align-items-center small mb-4" role="alert">
            <i class="bi bi-info-circle-fill me-2 fs-5"></i>
            <div>
                <strong>Default Free Trial:</strong> All newly registered and approved junkshop accounts automatically receive a 3-week free trial upon approval. Once expired, standard renewal options apply.
            </div>
        </div>

        <h4 class="h5 fw-bold mb-3">Junkshop Expiration Management</h4>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light"><tr><th>Junkshop Name</th><th>Account Status</th><th>Expiration Date</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                <?php foreach ($junkshops as $junkshop): ?>
                    <?php $isExpired = $junkshop['display_status'] === 'Expired'; ?>
                    <tr>
                        <td class="fw-semibold"><?php echo Validator::escape($junkshop['business_name']); ?></td>
                        <td><span class="badge text-bg-<?php echo $isExpired ? 'danger' : ($junkshop['account_status'] === 'active' ? 'success' : 'secondary'); ?>"><?php echo Validator::escape(ucfirst($junkshop['display_status'])); ?></span></td>
                        <td><?php echo Validator::escape($junkshop['partnership_expires_at'] ? (new DateTime($junkshop['partnership_expires_at']))->format('M d, Y g:i A') : 'Not assigned'); ?></td>
                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#expiryModal" data-account-id="<?php echo (int) $junkshop['account_id']; ?>" data-junkshop-name="<?php echo Validator::escape($junkshop['business_name']); ?>" data-current-expiry="<?php echo Validator::escape($junkshop['partnership_expires_at'] ?: ''); ?>">Modify Expiry</button></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$junkshops): ?><tr><td colspan="4" class="text-center text-muted py-4">No approved junkshops found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="expiryModal" tabindex="-1" aria-labelledby="expiryModalLabel" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="post" id="modifyExpirationForm" class="js-loading-form" onsubmit="return validateExpirationForm(event)">
            <div class="modal-header"><h5 class="modal-title" id="expiryModalLabel">Modify expiration</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <?php echo CSRF::field(); ?><input type="hidden" name="action" value="expiry"><input type="hidden" name="junkshop_account_id" id="expiry_account_id">
                <p class="text-muted" id="expiry_junkshop_name"></p>
                <label class="form-label" for="expiry_preset">Quick preset</label>
                <select name="expiration_preset" id="expiration_preset" class="form-select form-select-sm"><option value="" selected>Select new duration...</option><option value="1_month">1 Month</option><option value="6_months">6 Months</option><option value="1_year">1 Year</option></select>
                <label class="form-label" for="custom_expiry_date">Custom expiration date</label>
                <input class="form-control" type="date" id="custom_expiry_date" name="custom_expiry_date">
                <div id="expiration_error_msg" class="alert alert-danger d-none small py-2 mb-3">
                    Please either choose a quick preset or enter a custom expiration date.
                </div>
                <div class="form-check form-switch mb-2 mt-3">
                    <input class="form-check-input" type="checkbox" id="toggle_expiry_time" name="enable_expiry_time" value="1">
                    <label class="form-check-label fw-bold" for="toggle_expiry_time">Enable Expiration Time</label>
                </div>
                <div id="expiry_time_container" class="mb-3" style="display: none;">
                    <label class="form-label" for="custom_expiry_time">Expiration Time (24-hour format)</label>
                    <div class="input-group">
                        <input class="form-control" type="time" id="custom_expiry_time" name="custom_expiry_time" disabled>
                        <button type="button" class="btn btn-outline-secondary" id="btn-clear-time" title="Clear Time">
                            <i class="fas fa-times me-1"></i> Clear Time
                        </button>
                    </div>
                </div>
                <small class="text-muted">Disable the toggle to expire at the end of the selected date.</small>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save and reactivate</button></div>
        </form>
    </div></div>
</div>

<div class="card border-0 shadow-sm mt-4"><div class="card-body p-4"><h4 class="h5 fw-bold mb-3">Partnership Payment Reconciliation</h4><div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Junkshop Name / Business Name</th><th>Type of Renewal Plan</th><th>Payment Method</th><th>Amount (₱)</th><th>Reference Number</th><th>Receipt</th><th>Date and Time</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($payments as $payment): ?><tr><td><?php echo Validator::escape($payment['business_name']); ?></td><td><?php echo Validator::escape($payment['plan_type']); ?></td><td><?php echo Validator::escape($payment['payment_method']); ?></td><td>₱<?php echo number_format((float) $payment['amount'], 2); ?></td><td><?php echo $payment['payment_method'] === 'GCash' && $payment['reference_number'] ? Validator::escape($payment['reference_number']) : 'N/A'; ?></td><td><?php if ($payment['payment_method'] === 'GCash' && !empty($payment['receipt_image'])): ?><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#receiptModal" data-receipt-url="<?php echo htmlspecialchars(APP_URL . '/' . $payment['receipt_image'], ENT_QUOTES, 'UTF-8'); ?>">View Receipt</button><?php else: ?>N/A<?php endif; ?></td><td><?php echo Validator::escape((new DateTime($payment['created_at']))->format('M d, Y g:i A')); ?></td><td><?php echo Validator::escape($payment['status']); ?></td><td><?php if (!in_array($payment['status'], ['Approved', 'Rejected'], true)): ?><form method="post" class="d-flex gap-2"><?php echo CSRF::field(); ?><input type="hidden" name="action" value="reconcile"><input type="hidden" name="payment_id" value="<?php echo (int) $payment['id']; ?>"><button class="btn btn-sm btn-success" name="payment_status" value="Approved">Approve</button><button class="btn btn-sm btn-outline-danger" name="payment_status" value="Rejected">Reject</button></form><?php else: ?><span class="text-muted">Completed</span><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$payments): ?><tr><td colspan="9" class="text-center text-muted py-4">No renewal requests found.</td></tr><?php endif; ?></tbody></table></div></div></div>

<div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="receiptModalLabel">GCash Receipt</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body text-center"><img id="receiptModalImage" class="img-fluid" alt="GCash receipt"></div></div></div></div>

<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="post" id="rejectForm"><div class="modal-header"><h5 class="modal-title" id="rejectModalLabel">Reject Renewal Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><?php echo CSRF::field(); ?><input type="hidden" name="action" value="reconcile"><input type="hidden" name="payment_id" id="reject_payment_id"><input type="hidden" name="payment_status" value="Rejected"><label for="rejection_reason" class="form-label fw-bold">Reason for rejection</label><select name="rejection_reason" id="rejection_reason" class="form-select" required><option value="" selected disabled>Select a reason</option><option>GCash Reference Number does not match receipt screenshot</option><option>Uploaded receipt image is unreadable or incomplete</option><option>Payment amount does not match selected plan</option><option value="Custom Reason">Custom Reason</option></select><div id="customReasonGroup" class="mt-3 d-none"><label for="custom_reason" class="form-label fw-bold">Custom reason</label><textarea name="custom_reason" id="custom_reason" class="form-control" rows="3" maxlength="500"></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Reject Payment</button></div></form></div></div></div>

<div class="card border-0 shadow-sm mt-4"><div class="card-body p-4"><h4 class="h5 fw-bold mb-3">Junkshop Fee Payment Verification</h4><div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Junkshop Name / Business Name</th><th>Payment Method</th><th>Reference Number</th><th>Receipt</th><th>Amount Submitted</th><th>Date and Time</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($feePayments as $payment): ?><tr><td><?php echo Validator::escape($payment['business_name']); ?></td><td><?php echo Validator::escape($payment['payment_method']); ?></td><td><?php echo $payment['payment_method'] === 'GCash' && $payment['reference_number'] ? Validator::escape($payment['reference_number']) : '<span class="text-muted">N/A</span>'; ?></td><td><?php if ($payment['payment_method'] === 'GCash' && !empty($payment['receipt_image'])): ?><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#receiptModal" data-receipt-url="<?php echo htmlspecialchars(APP_URL . '/' . $payment['receipt_image'], ENT_QUOTES, 'UTF-8'); ?>">View Receipt</button><?php else: ?><span class="badge bg-secondary">Cash Payment</span><?php endif; ?></td><td>₱<?php echo number_format((float) $payment['amount_submitted'], 2); ?></td><td><?php echo Validator::escape((new DateTime($payment['created_at']))->format('M d, Y g:i A')); ?></td><td><?php echo Validator::escape($payment['status']); ?><?php if ($payment['status'] === 'Approved'): ?> <small class="text-muted">(₱<?php echo number_format((float) $payment['amount_deducted'], 2); ?> deducted)</small><?php endif; ?></td><td><?php if ($payment['status'] === 'Pending'): ?><form method="post" class="d-flex gap-2 align-items-center"><?php echo CSRF::field(); ?><input type="hidden" name="action" value="fee_reconcile"><input type="hidden" name="fee_payment_id" value="<?php echo (int) $payment['id']; ?>"><input class="form-control form-control-sm" type="number" name="amount_deducted" min="0.01" max="<?php echo number_format((float) $payment['amount_submitted'], 2, '.', ''); ?>" step="0.01" placeholder="Deduction" required><button class="btn btn-sm btn-success" name="fee_status" value="Approved">Approve</button><button class="btn btn-sm btn-outline-danger" name="fee_status" value="Rejected" formnovalidate>Reject</button></form><?php else: ?><span class="text-muted">Completed</span><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$feePayments): ?><tr><td colspan="8" class="text-center text-muted py-4">No junkshop fee payments found.</td></tr><?php endif; ?></tbody></table></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function addSectionCollapse(headingText, collapseId, buttonId, placeInHeading) {
        const heading = Array.from(document.querySelectorAll('h4')).find(function (element) {
            return element.textContent.trim() === headingText;
        });
        const panel = heading?.nextElementSibling;
        if (!heading || !panel || !panel.classList.contains('table-responsive')) return;
        const card = heading.closest('.card');
        const cardBody = heading.closest('.card-body');
        if (!card || !cardBody) return;
        const collapse = document.createElement('div');
        collapse.id = collapseId;
        collapse.className = 'collapse show';
        cardBody.classList.remove('p-4');
        cardBody.classList.add('p-0');
        cardBody.appendChild(collapse);
        collapse.appendChild(panel);
        const header = document.createElement('div');
        header.className = 'card-header bg-white py-3 d-flex justify-content-between align-items-center';
        header.style.cursor = 'pointer';
        card.insertBefore(header, cardBody);
        header.appendChild(heading);
        heading.className = 'h5 m-0 fw-bold ' + (headingText.includes('Verification') ? 'text-success' : 'text-primary');
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.id = buttonId;
        toggle.className = 'btn btn-sm btn-light border-0';
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-controls', collapseId);
        toggle.innerHTML = '<i class="bi bi-chevron-up"></i>';
        toggle.setAttribute('aria-label', 'Collapse section');
        header.appendChild(toggle);
        header.setAttribute('data-bs-toggle', 'collapse');
        header.setAttribute('data-bs-target', '#' + collapseId);
        header.setAttribute('aria-expanded', 'true');
        header.setAttribute('aria-controls', collapseId);
        collapse.addEventListener('shown.bs.collapse', function () {
            toggle.innerHTML = '<i class="bi bi-chevron-up"></i>';
            toggle.setAttribute('aria-expanded', 'true');
            toggle.setAttribute('aria-label', 'Collapse section');
        });
        collapse.addEventListener('hidden.bs.collapse', function () {
            toggle.innerHTML = '<i class="bi bi-chevron-down"></i>';
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Expand section');
        });
    }

    addSectionCollapse('Partnership Payment Reconciliation', 'partnershipPaymentsCollapse', 'togglePartnershipPayments', true);
    addSectionCollapse('Junkshop Fee Payment Verification', 'feePaymentsCollapse', 'toggleFeePayments', true);

    const rejectModal = document.getElementById('rejectModal');
    const rejectPaymentId = document.getElementById('reject_payment_id');
    const rejectionReason = document.getElementById('rejection_reason');
    const customReasonGroup = document.getElementById('customReasonGroup');
    const customReason = document.getElementById('custom_reason');
    rejectionReason.name = 'rejection_reason';
    customReason.name = 'rejection_notes';
    document.querySelectorAll('button[name="payment_status"][value="Rejected"]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            rejectModal.querySelector('input[name="action"]').value = 'reconcile';
            rejectPaymentId.value = button.closest('form').querySelector('input[name="payment_id"]').value;
            bootstrap.Modal.getOrCreateInstance(rejectModal).show();
        });
    });
    document.querySelectorAll('button[name="fee_status"][value="Rejected"], .js-reject-fee').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            rejectModal.querySelector('input[name="action"]').value = 'fee_reconcile';
            rejectPaymentId.value = button.dataset.feePaymentId || button.closest('form').querySelector('input[name="fee_payment_id"]').value;
            bootstrap.Modal.getOrCreateInstance(rejectModal).show();
        });
    });
    rejectionReason.innerHTML = '<option value="" selected disabled>Select a reason</option>'
        + '<option>Invalid / Unverifiable GCash Reference Number</option>'
        + '<option>Blurred, Unreadable, or Fraudulent Receipt Screenshot</option>'
        + '<option>GCash Payment Amount Mismatch / Insufficient Amount Transferred</option>'
        + '<option>Cash Amount Mismatch / Insufficient Amount Handed Over</option>'
        + '<option>Cash Handover Unverified / Payment Not Received at Office</option>'
        + '<option>Duplicate Payment Submission</option>'
        + '<option value="Other / Custom Reason">Other / Custom Reason</option>';
    rejectionReason.addEventListener('change', function () {
        const isCustom = rejectionReason.value === 'Other / Custom Reason';
        customReasonGroup.classList.toggle('d-none', !isCustom);
        customReason.required = false;
        if (!isCustom) customReason.value = '';
    });
    document.querySelectorAll('.js-loading-form').forEach(function (form) {
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) return;
            const button = form.querySelector('button[type="submit"]');
            if (!button || button.disabled) return;
            button.disabled = true;
            button.dataset.originalHtml = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...';
        });
    });
    const modal = document.getElementById('expiryModal');
    const preset = document.getElementById('expiration_preset');
    const dateInput = document.getElementById('custom_expiry_date');
    const timeToggle = document.getElementById('toggle_expiry_time');
    const timeContainer = document.getElementById('expiry_time_container');
    const timeInput = document.getElementById('custom_expiry_time');
    const clearTimeButton = document.getElementById('btn-clear-time');
    const expirationErrorMessage = document.getElementById('expiration_error_msg');
    let currentExpiry = '';

    window.clearCustomDate = function () {
        if (preset.value !== '') {
            dateInput.value = '';
            expirationErrorMessage.classList.add('d-none');
        }
    };

    window.clearPreset = function () {
        if (dateInput.value !== '') {
            preset.value = '';
            expirationErrorMessage.classList.add('d-none');
        }
    };

    window.validateExpirationForm = function (event) {
        if (!preset.value && !dateInput.value) {
            event.preventDefault();
            expirationErrorMessage.classList.remove('d-none');
            return false;
        }
        expirationErrorMessage.classList.add('d-none');
        return true;
    };

    function updateTimeVisibility() {
        const enabled = timeToggle.checked;
        timeContainer.style.display = enabled ? '' : 'none';
        timeInput.disabled = !enabled;
    }
    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        currentExpiry = button.getAttribute('data-current-expiry') || '';
        document.getElementById('expiry_account_id').value = button.getAttribute('data-account-id');
        document.getElementById('expiry_junkshop_name').textContent = button.getAttribute('data-junkshop-name');
        preset.value = '';
        const currentDate = currentExpiry.replace(' ', 'T');
        dateInput.value = currentDate.slice(0, 10);
        const currentTime = currentDate.length >= 19 ? currentDate.slice(11, 19) : '';
        timeToggle.checked = currentTime !== '' && currentTime !== '23:59:59' && currentTime !== '00:00:00';
        timeInput.value = timeToggle.checked ? currentTime.slice(0, 5) : '';
        updateTimeVisibility();
    });
    timeToggle.addEventListener('change', updateTimeVisibility);
    clearTimeButton.addEventListener('click', function () {
        timeInput.value = '';
    });
    preset.addEventListener('change', window.clearCustomDate);
    preset.addEventListener('change', function () {
        if (!preset.value) return;
        const baseDate = currentExpiry ? currentExpiry.replace(' ', 'T') : '';
        const base = baseDate && new Date(baseDate) > new Date() ? new Date(baseDate) : new Date();
        const presetDays = { '1_month': 30, '6_months': 180, '1_year': 365 };
        base.setDate(base.getDate() + presetDays[preset.value]);
        dateInput.value = base.toISOString().slice(0, 10);
        timeToggle.checked = true;
        updateTimeVisibility();
        timeInput.value = base.toTimeString().slice(0, 5);
    });
    dateInput.addEventListener('change', window.clearPreset);
    const receiptModal = document.getElementById('receiptModal');
    const receiptModalImage = document.getElementById('receiptModalImage');
    receiptModal.addEventListener('show.bs.modal', function (event) {
        receiptModalImage.src = event.relatedTarget.getAttribute('data-receipt-url');
    });
    receiptModal.addEventListener('hidden.bs.modal', function () {
        receiptModalImage.removeAttribute('src');
    });
});
</script>
<?php $content = ob_get_clean(); require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';