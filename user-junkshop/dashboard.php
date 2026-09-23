<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/DashboardController.php';

Auth::requireLogin();

if (Auth::userRole() === 'admin') {
    header('Location: ' . APP_URL . '/admin-private-dnstl/dashboard.php');
    exit;
}

$controller = new DashboardController();
$userRole = Auth::userRole();
$pageTitle = $userRole === 'seller' ? 'Seller Dashboard' : 'Junkshop Dashboard';
$currentPage = 'dashboard';
$userDisplayName = Auth::userName();

if ($userRole === 'seller') {
    $profile = $controller->getSellerProfile(Auth::userId());
} else {
    $profile = $controller->getJunkshopProfile(Auth::userId());
}

ob_start();
?>
<div class="row g-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <p class="eyebrow mb-1"><?php echo ucfirst(Validator::escape($userRole)); ?> Portal</p>
                        <h2 class="fw-bold mb-1">Welcome, <?php echo Validator::escape($userDisplayName); ?>!</h2>
                        <p class="text-muted mb-0"><?php echo $userRole === 'seller' ? 'Your account is active and ready for upcoming pickup requests.' : 'Your partner status and business profile are shown below.'; ?></p>
                    </div>
                    <a href="<?php echo APP_URL; ?>/user-junkshop/logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($userRole === 'seller'): ?>
        <div class="col-12 mb-3">
            <div class="small text-muted" aria-live="polite" id="member-last-updated">Last updated just now</div>
        </div>
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-person-vcard"></i> Profile Summary</h5>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item px-0 py-2">
                            <div class="text-muted small">Full name</div>
                            <div class="fw-semibold"><?php echo Validator::escape($profile['full_name'] ?? Auth::userName()); ?></div>
                        </div>
                        <div class="list-group-item px-0 py-2">
                            <div class="text-muted small">Email</div>
                            <div class="fw-semibold"><?php echo Validator::escape($profile['email'] ?? Auth::userEmail()); ?></div>
                        </div>
                        <div class="list-group-item px-0 py-2">
                            <div class="text-muted small">Mobile number</div>
                            <div class="fw-semibold"><?php echo Validator::escape($profile['mobile_number'] ?? ''); ?></div>
                        </div>
                        <div class="list-group-item px-0 py-2 border-0">
                            <div class="text-muted small">Account status</div>
                            <span class="status-badge approved"><?php echo Validator::escape(strtoupper($profile['account_status'] ?? 'active')); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-calendar2-check"></i> Quick Actions</h5>
                    <div class="alert alert-info mb-3">EcoPick facilitates your request. Registered junkshops handle collection, weighing, assessment, and purchase in later steps.</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="dashboard-card--feature h-100">
                                <div class="icon-wrap mb-3"><i class="bi bi-calendar2-check"></i></div>
                                <h6 class="fw-bold mb-1">Current Bookings</h6>
                                <p class="text-muted small mb-2">Track your submitted requests and current status.</p>
                                <a class="btn btn-sm btn-outline-primary" href="<?php echo APP_URL; ?>/user-junkshop/current-bookings.php">View bookings</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php $approvalStatus = strtolower((string)($profile['approval_status'] ?? 'pending')); ?>
        <div class="col-12 mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="small text-muted" aria-live="polite" id="member-last-updated">Last updated just now</div>
        </div>
        <div class="col-12">
            <div id="junkshop-status-alert" class="alert <?php echo $approvalStatus === 'pending' ? 'alert-warning' : ($approvalStatus === 'rejected' ? 'alert-danger' : 'alert-success'); ?>">
                <?php if ($approvalStatus === 'pending'): ?>
                    <i class="bi bi-hourglass-split"></i> <strong>Your EcoPick partnership application is awaiting Admin approval.</strong> You will only be able to use approved junkshop features after approval.
                <?php elseif ($approvalStatus === 'rejected'): ?>
                    <i class="bi bi-exclamation-triangle"></i> <strong>Your application was not approved.</strong> Please contact support for more information.
                <?php else: ?>
                    <i class="bi bi-check-circle"></i> <strong>Approved partner:</strong> Your account is active and ready for dashboard operations.
                <?php endif; ?>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-shop"></i> Junkshop Summary</h5>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item px-0 py-2">
                            <div class="text-muted small">Business name</div>
                            <div class="fw-semibold"><?php echo Validator::escape($profile['business_name'] ?? ''); ?></div>
                        </div>
                        <div class="list-group-item px-0 py-2">
                            <div class="text-muted small">Owner / contact person</div>
                            <div class="fw-semibold"><?php echo Validator::escape($profile['owner_name'] ?? ''); ?></div>
                        </div>
                        <div class="list-group-item px-0 py-2">
                            <div class="text-muted small">Email</div>
                            <div class="fw-semibold"><?php echo Validator::escape($profile['email'] ?? ''); ?></div>
                        </div>
                        <div class="list-group-item px-0 py-2 border-0">
                            <div class="text-muted small">Status</div>
                            <span class="status-badge <?php echo $approvalStatus; ?>"><?php echo Validator::escape(strtoupper($approvalStatus)); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-rocket-takeoff"></i> Partner Dashboard</h5>
                    <?php if ($approvalStatus === 'approved'): ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="dashboard-card--feature h-100">
                                    <div class="icon-wrap mb-3"><i class="bi bi-person-circle"></i></div>
                                    <h6 class="fw-bold mb-1">Profile</h6>
                                    <p class="text-muted small mb-0">Manage your business and contact details.</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="dashboard-card--feature h-100">
                                    <div class="icon-wrap mb-3"><i class="bi bi-recycle"></i></div>
                                    <h6 class="fw-bold mb-1">Accepted Materials and Buying Prices</h6>
                                    <p class="text-muted small mb-0">Coming in the next phase.</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="dashboard-card--feature h-100">
                                    <div class="icon-wrap mb-3"><i class="bi bi-broadcast"></i></div>
                                    <h6 class="fw-bold mb-1">Matched Requests</h6>
                                    <p class="text-muted small mb-2">Review assigned pickups, accept or decline them, and schedule collection.</p>
                                    <a class="btn btn-sm btn-outline-primary" href="<?php echo APP_URL; ?>/user-junkshop/matched-requests.php">Open queue</a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="dashboard-card--feature h-100">
                                    <div class="icon-wrap mb-3"><i class="bi bi-calendar3"></i></div>
                                    <h6 class="fw-bold mb-1">Operational Workflow</h6>
                                    <p class="text-muted small mb-2">Move from accepted match to scheduled pickup to final transaction completion.</p>
                                    <a class="btn btn-sm btn-outline-primary" href="<?php echo APP_URL; ?>/user-junkshop/matched-requests.php">Manage pickups</a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="display-6 text-muted"><i class="bi bi-x-circle"></i></div>
                            <h5 class="mt-3 mb-2 fw-bold">Dashboard not yet available</h5>
                            <p class="text-muted mb-0"><?php echo $approvalStatus === 'pending' ? 'Your EcoPick partnership application is awaiting Admin approval.' : 'Please contact support for assistance.'; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const updateTimestamp = () => {
            const region = document.getElementById('member-last-updated');
            if (!region) return;
            const now = new Date();
            region.textContent = 'Last updated ' + now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
        };

        const updateJunkshopStatus = (payload) => {
            if (!payload || !payload.data || !payload.data.approval_status) return;
            const status = String(payload.data.approval_status || 'pending').toLowerCase();
            const badge = document.querySelector('.status-badge');
            const alertBox = document.getElementById('junkshop-status-alert');
            const alertClass = status === 'pending' ? 'alert-warning' : (status === 'rejected' ? 'alert-danger' : 'alert-success');

            if (alertBox) {
                alertBox.className = 'alert ' + alertClass;
                if (status === 'pending') {
                    alertBox.innerHTML = '<i class="bi bi-hourglass-split"></i> <strong>Your EcoPick partnership application is awaiting Admin approval.</strong> You will only be able to use approved junkshop features after approval.';
                } else if (status === 'rejected') {
                    alertBox.innerHTML = '<i class="bi bi-exclamation-triangle"></i> <strong>Your application was not approved.</strong> Please contact support for more information.';
                } else {
                    alertBox.innerHTML = '<i class="bi bi-check-circle"></i> <strong>Approved partner:</strong> Your account is active and ready for dashboard operations.';
                }
            }

            if (badge) {
                badge.className = 'status-badge ' + status;
                badge.textContent = status.toUpperCase();
            }
            updateTimestamp();
        };

        if (window.EcoPickLiveUpdates && window.EcoPickLiveUpdates.startPolling) {
            window.EcoPickLiveUpdates.startPolling({
                key: 'junkshop-status',
                url: '<?php echo APP_URL; ?>/user-junkshop/api/status.php',
                interval: 12000,
                onSuccess: function (payload) {
                    if (payload && payload.session_expired && payload.redirect) {
                        window.location.href = payload.redirect;
                        return;
                    }
                    updateJunkshopStatus(payload);
                }
            });
        }
    });
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
