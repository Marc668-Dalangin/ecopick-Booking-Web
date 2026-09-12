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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = 'Invalid security token. Please try again.';
        $_SESSION['flash_type'] = 'danger';
    } else {
        $accountId = (int)($_POST['account_id'] ?? 0);
        $status = $_POST['decision'] ?? '';
        $result = $controller->updateJunkshopApproval($accountId, $status);

        $_SESSION['flash_message'] = $result['message'];
        $_SESSION['flash_type'] = $result['success'] ? 'success' : 'danger';
    }

    header('Location: ' . APP_URL . '/admin/junkshop-approvals.php');
    exit;
}

$pendingJunkshops = $controller->listPendingJunkshops();
$pageTitle = 'Junkshop Approvals';
$activePage = 'approvals';
ob_start();
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold"><i class="bi bi-building-check"></i> Pending Junkshop Applications</h4>
                <p class="text-muted mb-0">Review and act on each junkshop application before access is granted.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-warning text-dark" id="pending-count-badge"><?php echo count($pendingJunkshops); ?> pending</span>
                <span class="small text-muted" aria-live="polite" id="approval-last-updated">Last updated just now</span>
            </div>
        </div>

        <div id="live-feedback" class="alert d-none mt-3" role="status" aria-live="polite"></div>

        <?php if (empty($pendingJunkshops)): ?>
            <div class="empty-state">
                <div class="display-6 text-muted"><i class="bi bi-inboxes"></i></div>
                <h5 class="mt-3 mb-2 fw-bold">No pending junkshop applications</h5>
                <p class="text-muted mb-0">There are no junkshop applications awaiting admin review at the moment.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Business</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Address</th>
                            <th>Schedule</th>
                            <th>Permit No.</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingJunkshops as $junkshop): ?>
                            <tr data-junkshop-row="<?php echo (int)($junkshop['account_id'] ?? 0); ?>">
                                <td class="fw-semibold"><?php echo Validator::escape($junkshop['business_name'] ?? ''); ?></td>
                                <td><?php echo Validator::escape($junkshop['contact_person'] ?? ''); ?></td>
                                <td><?php echo Validator::escape($junkshop['email'] ?? ''); ?></td>
                                <td><?php echo Validator::escape($junkshop['mobile_number'] ?? ''); ?></td>
                                <td><?php echo Validator::escape($junkshop['address'] ?? ''); ?></td>
                                <td><?php echo Validator::escape($junkshop['operating_schedule'] ?? ''); ?></td>
                                <td><?php echo Validator::escape($junkshop['business_permit_reference'] ?? ''); ?></td>
                                <td><?php echo Validator::escape(date('M d, Y', strtotime($junkshop['registration_date'] ?? date('Y-m-d')))); ?></td>
                                <td><span class="status-badge pending"><?php echo Validator::escape(strtoupper($junkshop['status'] ?? 'pending')); ?></span></td>
                                <td>
                                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                                        <form method="POST" action="" class="approval-form d-inline" data-action="approve">
                                            <?php echo CSRF::field(); ?>
                                            <input type="hidden" name="account_id" value="<?php echo (int)($junkshop['account_id'] ?? 0); ?>">
                                            <input type="hidden" name="decision" value="approved">
                                            <button type="submit" class="btn btn-success btn-sm decision-btn btn-approve-junkshop" data-id="<?php echo (int)($junkshop['account_id'] ?? 0); ?>" data-confirmation="Approve this junkshop application?">
                                                <i class="bi bi-check-circle"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" action="" class="approval-form d-inline" data-action="reject">
                                            <?php echo CSRF::field(); ?>
                                            <input type="hidden" name="account_id" value="<?php echo (int)($junkshop['account_id'] ?? 0); ?>">
                                            <input type="hidden" name="decision" value="rejected">
                                            <button type="submit" class="btn btn-outline-danger btn-sm decision-btn btn-reject-junkshop" data-id="<?php echo (int)($junkshop['account_id'] ?? 0); ?>" data-confirmation="Are you sure? This will permanently delete the account.">
                                                <i class="bi bi-x-circle"></i> Reject
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="confirmApprovalModal" tabindex="-1" aria-labelledby="confirmApprovalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmApprovalModalLabel">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="confirmApprovalText">Are you sure you want to change the junkshop status?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmApprovalAction">Continue</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const statusBadgeClasses = {
            approved: 'status-badge approved',
            rejected: 'status-badge rejected',
            pending: 'status-badge pending'
        };
        const csrfToken = document.querySelector('input[name="_csrf_token"]')?.value || '';
        const confirmationModal = window.ecopick && window.ecopick.setupActionConfirmation
            ? window.ecopick.setupActionConfirmation({
                modalId: 'confirmApprovalModal',
                confirmButtonSelector: '#confirmApprovalAction',
                messageSelector: '#confirmApprovalText',
                cancelButtonSelector: '[data-bs-dismiss="modal"]'
            })
            : null;

        const setLastUpdated = () => {
            const region = document.getElementById('approval-last-updated');
            if (!region) return;
            const now = new Date();
            region.textContent = 'Last updated ' + now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
        };

        const renderPendingTable = (payload) => {
            const tableBody = document.querySelector('tbody');
            if (!tableBody || !payload || !payload.data || !Array.isArray(payload.data.pending)) return;

            const pending = payload.data.pending;
            const count = pending.length;
            const badge = document.getElementById('pending-count-badge');
            if (badge) {
                badge.textContent = count + (count === 1 ? ' pending' : ' pending');
            }

            if (!count) {
                tableBody.innerHTML = '<tr><td colspan="10"><div class="empty-state"><div class="display-6 text-muted"><i class="bi bi-inboxes"></i></div><h5 class="mt-3 mb-2 fw-bold">No pending junkshop applications</h5><p class="text-muted mb-0">There are no junkshop applications awaiting admin review at the moment.</p></div></td></tr>';
                setLastUpdated();
                return;
            }

            tableBody.innerHTML = pending.map(function (junkshop) {
                const id = Number(junkshop.account_id || 0);
                const rowStatus = (junkshop.status || 'pending').toLowerCase();
                const statusClass = statusBadgeClasses[rowStatus] || statusBadgeClasses.pending;
                return '<tr data-junkshop-row="' + id + '">' +
                    '<td class="fw-semibold">' + (junkshop.business_name || '') + '</td>' +
                    '<td>' + (junkshop.contact_person || '') + '</td>' +
                    '<td>' + (junkshop.email || '') + '</td>' +
                    '<td>' + (junkshop.mobile_number || '') + '</td>' +
                    '<td>' + (junkshop.address || '') + '</td>' +
                    '<td>' + (junkshop.operating_schedule || '') + '</td>' +
                    '<td>' + (junkshop.business_permit_reference || '') + '</td>' +
                    '<td>' + (junkshop.registration_date ? new Date(junkshop.registration_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '') + '</td>' +
                    '<td><span class="' + statusClass + '">' + String(rowStatus || 'pending').toUpperCase() + '</span></td>' +
                    '<td><div class="d-flex justify-content-end gap-2 flex-wrap"><form method="POST" action="" class="approval-form d-inline" data-action="approve"><input type="hidden" name="_csrf_token" value="' + csrfToken + '"><input type="hidden" name="account_id" value="' + id + '"><button type="submit" class="btn btn-success btn-sm decision-btn btn-approve-junkshop" data-id="' + id + '" data-confirmation="Approve this junkshop application?"><i class="bi bi-check-circle"></i> Approve</button></form><form method="POST" action="" class="approval-form d-inline" data-action="reject"><input type="hidden" name="_csrf_token" value="' + csrfToken + '"><input type="hidden" name="account_id" value="' + id + '"><button type="submit" class="btn btn-outline-danger btn-sm decision-btn btn-reject-junkshop" data-id="' + id + '" data-confirmation="Are you sure? This will permanently delete the account."><i class="bi bi-x-circle"></i> Reject</button></form></div></td>' +
                '</tr>';
            }).join('');

            setLastUpdated();
            bindApprovalButtons();
        };

        const bindApprovalButtons = () => {
            document.querySelectorAll('.btn-approve-junkshop, .btn-reject-junkshop').forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    const form = button.closest('form');
                    const confirmation = button.dataset.confirmation || 'Confirm this action?';

                    if (!confirmationModal || !form) {
                        return;
                    }

                    confirmationModal.open(confirmation, async function () {
                        const payload = {
                            id: button.dataset.id,
                            action: button.classList.contains('btn-reject-junkshop') ? 'reject' : 'approve',
                            _csrf_token: csrfToken
                        };

                        try {
                            const response = await fetch('<?php echo APP_URL; ?>/admin/api/process_junkshop_approval.php', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-Token': payload._csrf_token || ''
                                },
                                body: JSON.stringify(payload)
                            });

                            const json = await response.json();

                            if (json && json.session_expired && json.redirect) {
                                window.location.href = json.redirect;
                                return false;
                            }

                            if (!response.ok || !json.success) {
                                showLiveMessage(json.message || 'The request could not be completed.', false);
                                return false;
                            }

                            const formRow = form.closest('tr');
                            if (formRow) {
                                formRow.remove();
                            }
                            if (!document.querySelector('tbody tr[data-junkshop-row]')) {
                                const tableBody = document.querySelector('tbody');
                                if (tableBody) {
                                    tableBody.innerHTML = '<tr><td colspan="10"><div class="empty-state"><div class="display-6 text-muted"><i class="bi bi-inboxes"></i></div><h5 class="mt-3 mb-2 fw-bold">No pending junkshop applications</h5><p class="text-muted mb-0">There are no junkshop applications awaiting admin review at the moment.</p></div></td></tr>';
                                }
                            }
                            if (window.fetchPendingJunkshopCount) {
                                window.fetchPendingJunkshopCount();
                            }
                            const successMessage = payload.action === 'reject'
                                ? 'Junkshop application successfully rejected and deleted.'
                                : 'Junkshop application successfully approved.';
                            showLiveMessage(successMessage, true);
                            if (json.data && json.data.pending) {
                                renderPendingTable(json);
                            }
                            if (json.data && json.data.stats) {
                                const stats = json.data.stats;
                                Object.entries(stats).forEach(function ([key, value]) {
                                    const node = document.querySelector('[data-stat-value="' + key + '"]');
                                    if (node) {
                                        node.textContent = Number(value || 0).toLocaleString();
                                    }
                                });
                            }
                            setLastUpdated();
                            return true;
                        } catch (error) {
                            console.error('Failed to update junkshop approval:', error);
                            showLiveMessage('Unable to update the junkshop approval right now.', false);
                            return false;
                        }
                    });
                });
            });
        };

        const showLiveMessage = (message, isSuccess) => {
            const target = document.getElementById('live-feedback');
            if (!target) return;
            target.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-danger') + ' mt-3';
            target.textContent = message;
            target.classList.remove('d-none');
            setTimeout(function () {
                target.classList.add('d-none');
                target.textContent = '';
            }, 3500);
        };

        bindApprovalButtons();

        if (window.EcoPickLiveUpdates && window.EcoPickLiveUpdates.startPolling) {
            window.EcoPickLiveUpdates.startPolling({
                key: 'admin-junkshop-approvals',
                url: '<?php echo APP_URL; ?>/admin/api/junkshop-approvals.php',
                interval: 5000,
                onSuccess: function (payload) {
                    if (payload && payload.session_expired && payload.redirect) {
                        window.location.href = payload.redirect;
                        return;
                    }
                    if (payload && payload.data && Array.isArray(payload.data.pending)) {
                        renderPendingTable(payload);
                    }
                    setLastUpdated();
                }
            });
        }
    });
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';
