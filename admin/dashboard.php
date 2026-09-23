<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/DashboardController.php';
require_once __DIR__ . '/../app/services/PlatformAnalytics.php';

if (!Auth::check()) {
    header('Location: ' . APP_URL . '/admin-private-dnstl/login.php');
    exit;
}

if (Auth::userRole() !== 'admin') {
    header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
    exit;
}

$dashboardController = new DashboardController();
$analytics = new PlatformAnalytics();
$stats = $dashboardController->getAdminStats();
$completedMetrics = $dashboardController->getCompletedTransactionMetrics();
$platformSummary = $analytics->getPlatformSummary(date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
$pageTitle = 'Dashboard';
$activePage = 'dashboard';

ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="small text-muted" aria-live="polite" id="last-updated">Last updated just now</div>
</div>
<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="stat-card" data-stat-card="total_sellers">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="stat-icon"><i class="bi bi-people"></i></div>
                <span class="badge bg-success-subtle text-success">Sellers</span>
            </div>
            <div class="display-6 fw-bold mb-1" data-stat-value="total_sellers"><?php echo number_format((int)($stats['total_sellers'] ?? 0)); ?></div>
            <div class="text-muted">Total Sellers</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card" data-stat-card="total_junkshops">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="stat-icon"><i class="bi bi-shop"></i></div>
                <span class="badge bg-primary-subtle text-primary">Partners</span>
            </div>
            <div class="display-6 fw-bold mb-1" data-stat-value="total_junkshops"><?php echo number_format((int)($stats['total_junkshops'] ?? 0)); ?></div>
            <div class="text-muted">Total Junkshops</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card" data-stat-card="pending_junkshop_applications">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                <span class="badge bg-warning-subtle text-warning">Review</span>
            </div>
            <div class="display-6 fw-bold mb-1" data-stat-value="pending_junkshop_applications"><?php echo number_format((int)($stats['pending_junkshop_applications'] ?? 0)); ?></div>
            <div class="text-muted">Pending Junkshop Applications</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card" data-stat-card="approved_junkshops">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                <span class="badge bg-info-subtle text-info">Verified</span>
            </div>
            <div class="display-6 fw-bold mb-1" data-stat-value="approved_junkshops"><?php echo number_format((int)($stats['approved_junkshops'] ?? 0)); ?></div>
            <div class="text-muted">Approved Junkshops</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="stat-card bg-primary-subtle border-primary-subtle">
            <div class="d-flex justify-content-between align-items-center mb-3"><div class="stat-icon"><i class="bi bi-currency-dollar"></i></div><span class="badge bg-primary text-white">Revenue</span></div>
            <div class="display-6 fw-bold mb-1" data-metric-value="platform_revenue">₱<?php echo number_format((float)($completedMetrics['platform_revenue'] ?? 0), 2); ?></div>
            <div class="text-muted">Platform Revenue</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card bg-success-subtle border-success-subtle">
            <div class="d-flex justify-content-between align-items-center mb-3"><div class="stat-icon"><i class="bi bi-weight"></i></div><span class="badge bg-success text-white">Collected</span></div>
            <div class="display-6 fw-bold mb-1" data-metric-value="weight_collected_kg"><?php echo number_format((float)($completedMetrics['weight_collected_kg'] ?? 0), 2); ?> kg</div>
            <div class="text-muted">Weight Collected</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card bg-warning-subtle border-warning-subtle">
            <div class="d-flex justify-content-between align-items-center mb-3"><div class="stat-icon"><i class="bi bi-receipt"></i></div><span class="badge bg-warning text-dark">Transactions</span></div>
            <div class="display-6 fw-bold mb-1" data-metric-value="completed_transactions"><?php echo number_format((int)($completedMetrics['completed_transactions'] ?? 0)); ?></div>
            <div class="text-muted">Completed Transactions</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="stat-card bg-info-subtle border-info-subtle">
            <div class="d-flex justify-content-between align-items-center mb-3"><div class="stat-icon"><i class="bi bi-hourglass-split"></i></div><span class="badge bg-info text-white">Approvals</span></div>
            <div class="display-6 fw-bold mb-1"><?php echo number_format((int)($platformSummary['pending_junkshop_approvals'] ?? 0)); ?></div>
            <div class="text-muted">Pending Approvals</div>
        </div>
    </div>
</div>

<div class="row g-4 align-items-stretch">
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0 fw-bold"><i class="bi bi-speedometer2"></i> Platform Overview</h4>
                    <span class="badge bg-success-subtle text-success">Live Data</span>
                </div>
                <p class="text-muted mb-4">The dashboard foundation is active and pulling counts directly from the database.</p>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="dashboard-card--feature">
                            <div class="icon-wrap mb-3"><i class="bi bi-building-check"></i></div>
                            <h6 class="fw-bold mb-1">Junkshop Approvals</h6>
                            <p class="text-muted small mb-2">Review pending partnerships and approve or reject them.</p>
                            <a href="<?php echo APP_URL; ?>/admin-private-dnstl/junkshop-approvals.php" class="btn btn-sm btn-primary">Open Approvals</a>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="dashboard-card--feature">
                            <div class="icon-wrap mb-3"><i class="bi bi-people"></i></div>
                            <h6 class="fw-bold mb-1">Seller Directory</h6>
                            <p class="text-muted small mb-2">View registered sellers and their account details.</p>
                            <a href="<?php echo APP_URL; ?>/admin-private-dnstl/sellers.php" class="btn btn-sm btn-primary">View Sellers</a>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="dashboard-card--feature">
                            <div class="icon-wrap mb-3"><i class="bi bi-shop-window"></i></div>
                            <h6 class="fw-bold mb-1">Approved Junkshops</h6>
                            <p class="text-muted small mb-2">See the active partner junkshops approved by the admin.</p>
                            <a href="<?php echo APP_URL; ?>/admin-private-dnstl/approved-junkshops.php" class="btn btn-sm btn-primary">View Partners</a>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="dashboard-card--feature">
                            <div class="icon-wrap mb-3"><i class="bi bi-cash-coin"></i></div>
                            <h6 class="fw-bold mb-1">Fee Configuration</h6>
                            <p class="text-muted small mb-2">Tune platform fee settings and commissions for pickup operations.</p>
                            <a href="<?php echo APP_URL; ?>/admin-private-dnstl/fee-config.php" class="btn btn-sm btn-primary">Open Fees</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-4"><i class="bi bi-shield-check"></i> Admin Access</h4>
                <div class="list-group list-group-flush">
                    <div class="list-group-item px-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">Administrator</span>
                            <span class="badge bg-success text-white">Active</span>
                        </div>
                    </div>
                    <div class="list-group-item px-0 py-3">
                        <div class="text-muted small mb-1">Full name</div>
                        <div class="fw-semibold"><?php echo Validator::escape(Auth::userName()); ?></div>
                    </div>
                    <div class="list-group-item px-0 py-3">
                        <div class="text-muted small mb-1">Email address</div>
                        <div class="fw-semibold"><?php echo Validator::escape(Auth::userEmail()); ?></div>
                    </div>
                    <div class="list-group-item px-0 py-3 border-0">
                        <div class="d-grid gap-2">
                            <a href="<?php echo APP_URL; ?>/admin-private-dnstl/profile.php" class="btn btn-outline-primary"><i class="bi bi-person-circle"></i> View Profile</a>
                            <a href="<?php echo APP_URL; ?>/user-junkshop/logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const updateTimestamp = () => {
            const region = document.getElementById('last-updated');
            if (!region) return;
            const now = new Date();
            region.textContent = 'Last updated ' + now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
        };

        const updateStats = (payload) => {
            if (!payload || !payload.data || !payload.data.stats) return;
            const stats = payload.data.stats;
            Object.entries(stats).forEach(([key, value]) => {
                const node = document.querySelector('[data-stat-value="' + key + '"]');
                if (node) {
                    node.textContent = Number(value || 0).toLocaleString();
                }
            });
            const metrics = payload.data.completed_metrics || {};
            const revenueNode = document.querySelector('[data-metric-value="platform_revenue"]');
            const weightNode = document.querySelector('[data-metric-value="weight_collected_kg"]');
            const completedNode = document.querySelector('[data-metric-value="completed_transactions"]');
            if (revenueNode) revenueNode.textContent = '₱' + Number(metrics.platform_revenue || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (weightNode) weightNode.textContent = Number(metrics.weight_collected_kg || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kg';
            if (completedNode) completedNode.textContent = Number(metrics.completed_transactions || 0).toLocaleString();
            updateTimestamp();
        };

        if (window.EcoPickLiveUpdates && window.EcoPickLiveUpdates.startPolling) {
            window.EcoPickLiveUpdates.startPolling({
                key: 'admin-dashboard',
                url: '<?php echo APP_URL; ?>/admin-private-dnstl/api/dashboard.php',
                interval: 3000,
                onSuccess: function (payload) {
                    if (payload && payload.session_expired && payload.redirect) {
                        window.location.href = payload.redirect;
                        return;
                    }
                    updateStats(payload);
                },
                onError: function () {
                    updateTimestamp();
                }
            });
        }
    });
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';
