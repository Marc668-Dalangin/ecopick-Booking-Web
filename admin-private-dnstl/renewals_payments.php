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

$controller = new AdminFeatureController();
$feedback = null;
$action = (string) ($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::verify()) {
    if ($action === 'default_expiry') {
        $feedback = $controller->updateDefaultJunkshopExpiryDays((int) ($_POST['default_junkshop_expiry_days'] ?? 0));
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

$defaultExpiryDays = $controller->getDefaultJunkshopExpiryDays();
$junkshops = $controller->listApprovedJunkshops();
$payments = $controller->listPartnershipPayments();
$commissionPayments = $controller->listCommissionPayments();
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
        <?php if ($feedback): ?>
            <div class="alert alert-<?php echo $feedback['success'] ? 'success' : 'danger'; ?>"><?php echo Validator::escape($feedback['message']); ?></div>
        <?php endif; ?>

        <div class="border rounded p-3 mb-4">
            <h4 class="h5 fw-bold mb-1">Default Junkshop Expiration Period</h4>
            <p class="text-muted small mb-3">This setting applies only to junkshops approved after the change. Existing accounts keep their assigned date.</p>
            <form method="post" class="row g-2 align-items-end">
                <?php echo CSRF::field(); ?>
                <input type="hidden" name="action" value="default_expiry">
                <div class="col-sm-6 col-md-4">
                    <label class="form-label" for="default_junkshop_expiry_days">Period</label>
                    <select class="form-select" id="default_junkshop_expiry_days" name="default_junkshop_expiry_days" required>
                        <option value="21" <?php echo $defaultExpiryDays === 21 ? 'selected' : ''; ?>>3 Weeks (21 Days)</option>
                        <option value="30" <?php echo $defaultExpiryDays === 30 ? 'selected' : ''; ?>>1 Month (30 Days)</option>
                    </select>
                </div>
                <div class="col-auto"><button class="btn btn-primary" type="submit">Save default</button></div>
            </form>
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
        <form method="post">
            <div class="modal-header"><h5 class="modal-title" id="expiryModalLabel">Modify expiration</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <?php echo CSRF::field(); ?><input type="hidden" name="action" value="expiry"><input type="hidden" name="junkshop_account_id" id="expiry_account_id">
                <p class="text-muted" id="expiry_junkshop_name"></p>
                <label class="form-label" for="expiry_preset">Quick preset</label>
                <select class="form-select mb-3" id="expiry_preset"><option value="">Select a preset</option><option value="21">+3 Weeks (21 Days)</option><option value="30">+1 Month (30 Days)</option></select>
                <label class="form-label" for="custom_expiry_date">Custom expiration date</label>
                <input class="form-control" type="date" id="custom_expiry_date" name="custom_expiry_date">
                <div class="form-check form-switch mb-2 mt-3">
                    <input class="form-check-input" type="checkbox" id="toggle_expiry_time" name="enable_expiry_time" value="1">
                    <label class="form-check-label fw-bold" for="toggle_expiry_time">Enable Expiration Time</label>
                </div>
                <div id="expiry_time_container" class="mb-3" style="display: none;">
                    <label class="form-label" for="custom_expiry_time">Expiration Time (12-hr format)</label>
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
    const modal = document.getElementById('expiryModal');
    const preset = document.getElementById('expiry_preset');
    const dateInput = document.getElementById('custom_expiry_date');
    const timeToggle = document.getElementById('toggle_expiry_time');
    const timeContainer = document.getElementById('expiry_time_container');
    const timeInput = document.getElementById('custom_expiry_time');
    const clearTimeButton = document.getElementById('btn-clear-time');
    let currentExpiry = '';
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
    preset.addEventListener('change', function () {
        if (!preset.value) return;
        const baseDate = currentExpiry ? currentExpiry.replace(' ', 'T') : '';
        const base = baseDate && new Date(baseDate) > new Date() ? new Date(baseDate) : new Date();
        base.setDate(base.getDate() + Number(preset.value));
        dateInput.value = base.toISOString().slice(0, 10);
        timeToggle.checked = true;
        updateTimeVisibility();
        timeInput.value = base.toTimeString().slice(0, 5);
    });
});
</script>
<?php $content = ob_get_clean(); require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';