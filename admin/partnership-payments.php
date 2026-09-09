<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/AdminFeatureController.php';
if (!Auth::check()) { header('Location: ' . APP_URL . '/admin/login.php'); exit; }
if (Auth::userRole() !== 'admin') { header('Location: ' . APP_URL . '/user-junkshop/dashboard.php'); exit; }
$controller = new AdminFeatureController(); $feedback = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::verify()) {
    if (($_POST['action'] ?? '') === 'reconcile') $feedback = $controller->reconcilePartnershipPayment((int) $_POST['payment_id'], (string) $_POST['payment_status'], (string) ($_POST['payment_reference'] ?? ''));
    if (($_POST['action'] ?? '') === 'commission') $feedback = $controller->reconcileCommissionPayment((int) $_POST['payment_id'], (string) $_POST['payment_status'], (string) ($_POST['payment_reference'] ?? ''));
    if (($_POST['action'] ?? '') === 'expiry') $feedback = $controller->setExpiry((int) $_POST['junkshop_account_id'], (string) $_POST['expiry_date']);
    if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($feedback ?? ['success' => false, 'message' => 'Invalid payment action.']);
        exit;
    }
}
$payments = $controller->listPartnershipPayments(); $commissionPayments = $controller->listCommissionPayments(); $pageTitle = 'Renewals & Payments'; $activePage = 'partnership-payments'; ob_start();
?>
<div class="card border-0 shadow-sm"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-center mb-4"><div><h2 class="fw-bold mb-1">Renewals & Payments</h2><p class="text-muted mb-0">Monitor partnership expiry, outstanding registration or renewal payments, and EcoPick settlements.</p></div><span class="badge bg-warning-subtle text-warning"><?php echo count(array_filter($payments, fn($p) => $p['payment_status'] !== 'Confirmed')); ?> outstanding</span></div>
<?php if ($feedback): ?><div class="alert alert-<?php echo $feedback['success'] ? 'success' : 'danger'; ?>"><?php echo Validator::escape($feedback['message']); ?></div><?php endif; ?>
<div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Junkshop</th><th>Partnership</th><th>Payment</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($payments as $payment): ?><tr><td class="fw-semibold"><?php echo Validator::escape($payment['business_name']); ?><br><span class="small text-muted">Expiry: <?php echo Validator::escape($payment['partnership_expires_at'] ?: 'Not set'); ?></span></td><td><?php echo Validator::escape($payment['payment_type']); ?><br><span class="small text-muted"><?php echo Validator::escape($payment['renewal_status']); ?></span></td><td><?php echo Validator::escape($payment['payment_method'] ?: 'Not specified'); ?><br><span class="small text-muted"><?php echo Validator::escape($payment['payment_reference'] ?: 'No reference'); ?></span></td><td>₱<?php echo number_format((float) $payment['amount'], 2); ?></td><td><span class="badge text-bg-<?php echo $payment['payment_status'] === 'Confirmed' ? 'success' : 'warning'; ?>"><?php echo Validator::escape($payment['payment_status']); ?></span></td><td><?php if ($payment['payment_status'] !== 'Confirmed'): ?><form method="post" class="d-flex gap-2 mb-2"><input type="hidden" name="_csrf_token" value="<?php echo CSRF::token(); ?>"><input type="hidden" name="action" value="reconcile"><input type="hidden" name="payment_id" value="<?php echo (int) $payment['id']; ?>"><input class="form-control form-control-sm" name="payment_reference" placeholder="Reference"><select class="form-select form-select-sm" name="payment_status"><option>Confirmed</option><option>Paid</option></select><button class="btn btn-sm btn-primary">Reconcile</button></form><?php endif; ?><form method="post" class="d-flex gap-2"><input type="hidden" name="_csrf_token" value="<?php echo CSRF::token(); ?>"><input type="hidden" name="action" value="expiry"><input type="hidden" name="junkshop_account_id" value="<?php echo (int) $payment['junkshop_account_id']; ?>"><input class="form-control form-control-sm" type="date" name="expiry_date" value="<?php echo Validator::escape($payment['partnership_expires_at'] ?? ''); ?>"><button class="btn btn-sm btn-outline-secondary">Set expiry</button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<div class="card border-0 shadow-sm mt-4"><div class="card-body p-4"><h4 class="fw-bold mb-3">EcoPick Commission Settlements</h4><div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Booking</th><th>Junkshop</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($commissionPayments as $payment): ?><tr><td><?php echo Validator::escape($payment['booking_reference']); ?></td><td><?php echo Validator::escape($payment['business_name']); ?></td><td>₱<?php echo number_format((float) $payment['amount'], 2); ?></td><td data-payment-status-cell><span class="badge text-bg-<?php echo in_array($payment['payment_status'], ['Confirmed', 'Paid'], true) ? 'success' : 'warning'; ?>" data-payment-status><?php echo Validator::escape($payment['payment_status']); ?></span></td><td data-payment-action-cell><?php if (!in_array($payment['payment_status'], ['Confirmed', 'Paid'], true)): ?><form method="post" class="d-flex gap-2" data-commission-action><input type="hidden" name="_csrf_token" value="<?php echo CSRF::token(); ?>"><input type="hidden" name="action" value="commission"><input type="hidden" name="payment_id" value="<?php echo (int) $payment['id']; ?>"><input class="form-control form-control-sm" name="payment_reference" placeholder="Reference"><select class="form-select form-select-sm" name="payment_status"><option>Confirmed</option><option>Paid</option></select><button class="btn btn-sm btn-primary" type="submit">Approve</button></form><?php else: ?><span class="text-success fw-semibold">Settled</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-commission-action]').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            const formData = new FormData(form);
            const selectedStatus = String(formData.get('payment_status') || 'Paid');
            const row = form.closest('tr');
            const badge = row ? row.querySelector('[data-payment-status]') : null;
            const actionCell = row ? row.querySelector('[data-payment-action-cell]') : null;
            const submitButton = form.querySelector('button[type="submit"]');

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Processing...';
            }

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const payload = await response.json().catch(function () {
                    return { success: false, message: 'Unable to update settlement.' };
                });

                if (!payload || payload.success !== true) {
                    alert(payload.message || 'Unable to update settlement.');
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = 'Approve';
                    }
                    return;
                }

                const newStatus = ['Confirmed', 'Paid'].includes(selectedStatus) ? selectedStatus : 'Paid';
                const isSettled = ['Confirmed', 'Paid'].includes(newStatus);

                if (badge) {
                    badge.textContent = newStatus;
                    badge.className = 'badge ' + (newStatus === 'Confirmed' ? 'text-bg-success' : 'text-bg-warning');
                }

                if (actionCell) {
                    actionCell.innerHTML = isSettled ? '<span class="text-success fw-semibold">Settled</span>' : '<span class="text-muted">Action required</span>';
                }

                form.remove();
            } catch (error) {
                console.error('Commission settlement update failed:', error);
                alert('Commission settlement update failed.');
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Approve';
                }
            }
        });
    });
});
</script>
<?php $content = ob_get_clean(); require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';