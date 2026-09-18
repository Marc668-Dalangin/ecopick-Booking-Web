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
        $feedback = $controller->reconcilePartnershipPayment((int) $_POST['payment_id'], (string) $_POST['payment_status'], (string) ($_POST['payment_reference'] ?? ''));
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
$junkshops = $loadRenewalData(
    static fn (): array => $controller->listApprovedJunkshops(),
    []
);
$payments = $loadRenewalData(
    static fn (): array => $controller->listPartnershipPayments(),
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
            <span class="badge bg-warning-subtle text-warning"><?php echo count(array_filter($payments, fn ($payment) => $payment['payment_status'] !== 'Confirmed')); ?> outstanding</span>
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
                        <td><?php echo Validator::escape($junkshop['partnership_expires_at'] ?: 'Not assigned'); ?></td>
                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#expiryModal" data-account-id="<?php echo (int) $junkshop['account_id']; ?>" data-junkshop-name="<?php echo Validator::escape($junkshop['business_name']); ?>" data-current-expiry="<?php echo Validator::escape($junkshop['partnership_expires_at'] ?: ''); ?>">Extend / Modify Expiry</button></td>
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

<div class="card border-0 shadow-sm mt-4"><div class="card-body p-4"><h4 class="h5 fw-bold mb-3">Partnership Payment Reconciliation</h4><div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Junkshop</th><th>Type</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($payments as $payment): ?><tr><td><?php echo Validator::escape($payment['business_name']); ?></td><td><?php echo Validator::escape($payment['payment_type']); ?></td><td>₱<?php echo number_format((float) $payment['amount'], 2); ?></td><td><?php echo Validator::escape($payment['payment_status']); ?></td><td><?php if ($payment['payment_status'] !== 'Confirmed'): ?><form method="post" class="d-flex gap-2"><?php echo CSRF::field(); ?><input type="hidden" name="action" value="reconcile"><input type="hidden" name="payment_id" value="<?php echo (int) $payment['id']; ?>"><input class="form-control form-control-sm" name="payment_reference" placeholder="Reference"><select class="form-select form-select-sm" name="payment_status"><option>Confirmed</option><option>Paid</option></select><button class="btn btn-sm btn-primary">Reconcile</button></form><?php else: ?><span class="text-success">Settled</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
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
});
</script>
<?php $content = ob_get_clean(); require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';