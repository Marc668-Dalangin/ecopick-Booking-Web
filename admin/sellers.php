<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/DashboardController.php';

if (!Auth::check()) {
    header('Location: ' . APP_URL . '/admin/login.php');
    exit;
}

if (Auth::userRole() !== 'admin') {
    header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
    exit;
}

$controller = new DashboardController();
$feedback = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::verify()) {
    $feedback = $controller->updateAccountStatus((int) ($_POST['account_id'] ?? 0), (string) ($_POST['account_status'] ?? ''));
}
$sellers = $controller->listSellers();
$pageTitle = 'Sellers';
$activePage = 'sellers';
ob_start();
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
    <?php if ($feedback): ?><div class="alert alert-<?php echo $feedback['success'] ? 'success' : 'danger'; ?>" role="alert"><?php echo Validator::escape($feedback['message']); ?></div><?php endif; ?>
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold"><i class="bi bi-people"></i> Sellers List</h4>
                <p class="text-muted mb-0">Registered sellers on the EcoPick platform.</p>
            </div>
            <span class="badge bg-success-subtle text-success"><?php echo count($sellers); ?> total</span>
        </div>
        <?php echo CSRF::field(); ?>

        <?php if (empty($sellers)): ?>
            <div class="empty-state">
                <div class="display-6 text-muted"><i class="bi bi-person-x"></i></div>
                <h5 class="mt-3 mb-2 fw-bold">No sellers found</h5>
                <p class="text-muted mb-0">There are no registered sellers in the database yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Address</th>
                            <th>Status</th>
                            <th>Action</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sellers as $seller): ?>
                            <tr data-account-row data-account-id="<?php echo (int) $seller['id']; ?>">
                                <td class="fw-semibold"><?php echo Validator::escape($seller['full_name'] ?? ''); ?></td>
                                <td><?php echo Validator::escape($seller['email'] ?? ''); ?></td>
                                <td><?php echo Validator::escape($seller['mobile_number'] ?? ''); ?></td>
                                <td><?php echo Validator::escape($seller['address'] ?? ''); ?></td>
                                <td>
                                    <?php $sellerStatus = strtolower((string)($seller['account_status'] ?? 'active')); ?>
                                    <span class="status-badge <?php echo $sellerStatus === 'active' ? 'approved' : 'pending'; ?>" data-account-status><?php echo Validator::escape($sellerStatus); ?></span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm <?php echo $sellerStatus === 'active' ? 'btn-outline-danger' : 'btn-outline-success'; ?>" data-account-action="<?php echo $sellerStatus === 'active' ? 'deactivate' : 'activate'; ?>"><i class="bi <?php echo $sellerStatus === 'active' ? 'bi-person-slash' : 'bi-person-check'; ?>"></i> <?php echo $sellerStatus === 'active' ? 'Deactivate' : 'Activate'; ?></button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-account-action="delete"><i class="bi bi-trash"></i> Delete</button>
                                </td>
                                <td><?php echo Validator::escape(date('M d, Y', strtotime($seller['created_at'] ?? date('Y-m-d')))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<div class="modal fade" id="confirmAccountDeleteModal" tabindex="-1" aria-labelledby="confirmAccountDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmAccountDeleteModalLabel">Delete account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" data-confirm-message>Are you sure you want to permanently delete this account?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" data-confirm-continue>Delete permanently</button>
            </div>
        </div>
    </div>
</div>
<script>
window.addEventListener('DOMContentLoaded', function () {
    const table = document.querySelector('[data-account-row]')?.closest('table');
    const csrfToken = document.querySelector('input[name="_csrf_token"]')?.value || '';
    const apiUrl = '<?php echo APP_URL; ?>/admin/api/account-actions.php';
    const confirmation = window.ecopick.setupActionConfirmation({ modalId: 'confirmAccountDeleteModal' });

    if (!table) return;

    function updateStatus(row, accountStatus) {
        const status = row.querySelector('[data-account-status]');
        const toggle = row.querySelector('[data-account-action="activate"], [data-account-action="deactivate"]');
        const active = accountStatus === 'active';
        status.textContent = active ? 'active' : 'inactive';
        status.classList.toggle('approved', active);
        status.classList.toggle('pending', !active);
        toggle.dataset.accountAction = active ? 'deactivate' : 'activate';
        toggle.classList.toggle('btn-outline-danger', active);
        toggle.classList.toggle('btn-outline-success', !active);
        toggle.innerHTML = active ? '<i class="bi bi-person-slash"></i> Deactivate' : '<i class="bi bi-person-check"></i> Activate';
    }

    async function performAction(row, action) {
        const buttons = row.querySelectorAll('[data-account-action]');
        buttons.forEach(button => { button.disabled = true; });
        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                credentials: 'same-origin',
                body: JSON.stringify({ account_id: row.dataset.accountId, action: action, _csrf_token: csrfToken })
            });
            const payload = await response.json();
            if (payload.session_expired && payload.redirect) { window.location.href = payload.redirect; return true; }
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Account action failed.');
            if (action === 'delete') {
                row.classList.add('fade');
                setTimeout(() => row.remove(), 250);
            } else {
                updateStatus(row, payload.data.account_status);
                buttons.forEach(button => { button.disabled = false; });
            }
            showToast(payload.message, 'success');
            return true;
        } catch (error) {
            buttons.forEach(button => { button.disabled = false; });
            showToast(error.message, 'danger');
            return false;
        }
    }

    table.addEventListener('click', function (event) {
        const button = event.target.closest('[data-account-action]');
        if (!button) return;
        const row = button.closest('[data-account-row]');
        const action = button.dataset.accountAction;
        if (!row) return;
        if (action === 'delete') {
            confirmation.open('Are you sure you want to permanently delete this account?', () => performAction(row, action));
            return;
        }
        performAction(row, action);
    });
});
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';
