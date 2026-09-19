<?php
/**
 * Shared admin dashboard shell
 */

$adminActive = $activePage ?? 'dashboard';
$adminSettingsActive = in_array($adminActive, ['concerns', 'reports', 'profile', 'partnership-payments', 'backup-import'], true);
$adminUserName = Auth::userName();
$adminUserEmail = Auth::userEmail();
$unreadReportCount = 0;
$unreadConcernCount = 0;
$siteFavicon = APP_URL . '/assets/images/logo.jpg';
if (isset($_SESSION['admin_id']) || (isset($_SESSION[SESSION_USER_ID]) && Auth::userRole() === 'admin')) {
    $pdo = Database::getInstance()->getPDO();
    $stmt = $pdo->query("SELECT COUNT(*) FROM pickup_requests WHERE current_status = 'Completed' AND (admin_viewed_report = 0 OR admin_viewed_report IS NULL)");
    $unreadReportCount = (int) $stmt->fetchColumn();

    $concernStmt = $pdo->query("SELECT COUNT(*) FROM concerns WHERE is_read_admin = 0 OR status = 'Pending'");
    $unreadConcernCount = (int) $concernStmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? Validator::escape($pageTitle) . ' - EcoPick Admin' : 'EcoPick Admin'; ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($siteFavicon, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($siteFavicon, ENT_QUOTES, 'UTF-8'); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="<?php echo APP_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="admin-body">
    <div class="admin-shell d-flex flex-nowrap min-vh-100">
        <aside id="admin-sidebar-scroll" class="admin-sidebar col-lg-2 d-none d-lg-flex flex-column overflow-y-auto">
            <div class="admin-brand">
                <i class="bi bi-recycling"></i>
                <span>EcoPick</span>
            </div>

            <nav class="nav flex-column admin-nav flex-grow-1">
                <a class="nav-link <?php echo $adminActive === 'dashboard' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/dashboard.php">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
                <a class="nav-link <?php echo $adminActive === 'approvals' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/junkshop-approvals.php">
                    <i class="bi bi-building-check"></i>
                    <span>Junkshop Approvals</span><span id="pending-junkshop-badge" class="badge bg-danger rounded-pill ms-2" style="display: none;"></span>
                </a>
                <a class="nav-link <?php echo $adminActive === 'sellers' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/sellers.php">
                    <i class="bi bi-people"></i>
                    <span>Sellers</span>
                </a>
                <a class="nav-link <?php echo $adminActive === 'approved-junkshops' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/approved-junkshops.php">
                    <i class="bi bi-shop-window"></i>
                    <span>Approved Junkshops</span>
                </a>
                <a class="nav-link <?php echo $adminActive === 'fee-config' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/fee-config.php">
                    <i class="bi bi-cash-coin"></i>
                    <span>Fee Configuration</span>
                </a>
                <a class="nav-link <?php echo $adminActive === 'pricing-lists' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/pricing-lists.php">
                    <i class="bi bi-currency-dollar"></i>
                    <span>Pricing Lists</span>
                </a>
                <a class="nav-link <?php echo $adminActive === 'pending-pickups' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/pending-pickup-requests.php">
                    <i class="bi bi-inboxes"></i>
                    <span>Pending Pickup Requests</span>
                </a>
                <div class="admin-nav-group">
                    <button class="nav-link admin-nav-group-toggle w-100 border-0 text-start <?php echo $adminSettingsActive ? 'active' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#adminSettingsMenu" aria-expanded="<?php echo $adminSettingsActive ? 'true' : 'false'; ?>" aria-controls="adminSettingsMenu">
                        <i class="bi bi-sliders2"></i>
                        <span>Others/Settings</span>
                        <i class="bi bi-chevron-down admin-nav-chevron ms-auto" aria-hidden="true"></i>
                    </button>
                    <div class="collapse <?php echo $adminSettingsActive ? 'show' : ''; ?>" id="adminSettingsMenu">
                        <div class="admin-nav-submenu">
                            <a class="nav-link <?php echo $adminActive === 'concerns' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/concerns.php"><i class="bi bi-life-preserver"></i><span>Concerns & Disputes</span><?php if ($unreadConcernCount > 0): ?><span class="badge bg-danger rounded-pill ms-auto" aria-label="<?php echo $unreadConcernCount; ?> unread concerns"><?php echo $unreadConcernCount; ?></span><?php endif; ?></a>
                            <a class="nav-link <?php echo $adminActive === 'reports' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/reports.php"><i class="bi bi-bar-chart"></i><span>Reports</span><?php if ($unreadReportCount > 0): ?><span class="badge bg-danger rounded-pill ms-2" aria-label="<?php echo $unreadReportCount; ?> unread completed transactions"><?php echo $unreadReportCount; ?></span><?php endif; ?></a>
                            <a class="nav-link <?php echo $adminActive === 'profile' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/profile.php"><i class="bi bi-person-circle"></i><span>Admin Profile</span></a>
                            <a class="nav-link <?php echo $adminActive === 'partnership-payments' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/partnership-payments.php"><i class="bi bi-arrow-repeat"></i><span>Renewals & Payments</span></a>
                            <a class="nav-link <?php echo $adminActive === 'backup-import' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/backup_import.php"><i class="bi bi-database-gear"></i><span>Database Maintenance</span></a>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="admin-sidebar-footer">
                <div class="small text-white-50 text-uppercase mb-2">Logged in</div>
                <div class="fw-semibold text-white"><?php echo Validator::escape($adminUserName); ?></div>
                <div class="small text-white-50"><?php echo Validator::escape($adminUserEmail); ?></div>
            </div>
        </aside>

        <div class="admin-main col-12 col-lg-10">
            <header class="admin-topbar">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebarMobile" aria-label="Open navigation">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <p class="eyebrow mb-0">EcoPick Administration</p>
                        <h1 class="page-title mb-0"><?php echo Validator::escape($pageTitle ?? 'Dashboard'); ?></h1>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <div class="d-none d-md-block text-end">
                        <div class="fw-semibold"><?php echo Validator::escape($adminUserName); ?></div>
                        <div class="small text-muted"><?php echo Validator::escape($adminUserEmail); ?></div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/admin-private-dnstl/profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/user-junkshop/logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="admin-content">
                <?php if (!empty($_SESSION['flash_message'] ?? '')): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($_SESSION['flash_type'] ?? 'success', ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
                        <?php echo Validator::escape($_SESSION['flash_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
                <?php endif; ?>

                <div class="offcanvas offcanvas-start" tabindex="-1" id="adminSidebarMobile" aria-labelledby="adminSidebarMobileLabel">
                    <div class="offcanvas-header">
                        <h5 class="offcanvas-title" id="adminSidebarMobileLabel">EcoPick Admin</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                    </div>
                    <div class="offcanvas-body p-0">
                        <nav class="nav flex-column admin-nav px-3 py-3">
                            <a class="nav-link <?php echo $adminActive === 'dashboard' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/dashboard.php">
                                <i class="bi bi-speedometer2"></i>
                                <span>Dashboard</span>
                            </a>
                            <a class="nav-link <?php echo $adminActive === 'approvals' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/junkshop-approvals.php">
                                <i class="bi bi-building-check"></i>
                                <span>Junkshop Approvals</span><span data-pending-junkshop-badge class="badge bg-danger rounded-pill ms-2" style="display: none;"></span>
                            </a>
                            <a class="nav-link <?php echo $adminActive === 'sellers' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/sellers.php">
                                <i class="bi bi-people"></i>
                                <span>Sellers</span>
                            </a>
                            <a class="nav-link <?php echo $adminActive === 'approved-junkshops' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/approved-junkshops.php">
                                <i class="bi bi-shop-window"></i>
                                <span>Approved Junkshops</span>
                            </a>
                            <a class="nav-link <?php echo $adminActive === 'fee-config' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/fee-config.php">
                                <i class="bi bi-cash-coin"></i>
                                <span>Fee Configuration</span>
                            </a>
                            <a class="nav-link <?php echo $adminActive === 'pricing-lists' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/pricing-lists.php">
                                <i class="bi bi-currency-dollar"></i>
                                <span>Pricing Lists</span>
                            </a>
                            <a class="nav-link <?php echo $adminActive === 'pending-pickups' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/pending-pickup-requests.php">
                                <i class="bi bi-inboxes"></i>
                                <span>Pending Pickup Requests</span>
                            </a>
                            <div class="admin-nav-group">
                                <button class="nav-link admin-nav-group-toggle w-100 border-0 text-start <?php echo $adminSettingsActive ? 'active' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#adminSettingsMenuMobile" aria-expanded="<?php echo $adminSettingsActive ? 'true' : 'false'; ?>" aria-controls="adminSettingsMenuMobile">
                                    <i class="bi bi-sliders2"></i>
                                    <span>Others/Settings</span>
                                    <i class="bi bi-chevron-down admin-nav-chevron ms-auto" aria-hidden="true"></i>
                                </button>
                                <div class="collapse <?php echo $adminSettingsActive ? 'show' : ''; ?>" id="adminSettingsMenuMobile">
                                    <div class="admin-nav-submenu">
                                        <a class="nav-link <?php echo $adminActive === 'concerns' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/concerns.php"><i class="bi bi-life-preserver"></i><span>Concerns & Disputes</span><?php if ($unreadConcernCount > 0): ?><span class="badge bg-danger rounded-pill ms-auto" aria-label="<?php echo $unreadConcernCount; ?> unread concerns"><?php echo $unreadConcernCount; ?></span><?php endif; ?></a>
                                        <a class="nav-link <?php echo $adminActive === 'reports' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/reports.php"><i class="bi bi-bar-chart"></i><span>Reports</span><?php if ($unreadReportCount > 0): ?><span class="badge bg-danger rounded-pill ms-2" aria-label="<?php echo $unreadReportCount; ?> unread completed transactions"><?php echo $unreadReportCount; ?></span><?php endif; ?></a>
                                        <a class="nav-link <?php echo $adminActive === 'profile' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/profile.php"><i class="bi bi-person-circle"></i><span>Admin Profile</span></a>
                                        <a class="nav-link <?php echo $adminActive === 'partnership-payments' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/partnership-payments.php"><i class="bi bi-arrow-repeat"></i><span>Renewals & Payments</span></a>
                                        <a class="nav-link <?php echo $adminActive === 'backup-import' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/admin-private-dnstl/backup_import.php"><i class="bi bi-database-gear"></i><span>Database Maintenance</span></a>
                                    </div>
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>

                <?php echo $content ?? ''; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/live-updates.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
    <script>window.EcoPickAdmin = { pendingCountUrl: '<?php echo APP_URL; ?>/admin-private-dnstl/api/get_pending_junkshop_count.php' };</script>
    <script src="<?php echo APP_URL; ?>/assets/js/admin-notifications.js"></script>
    <script>
        window.addEventListener('beforeunload', function() {
            const adminSidebar = document.getElementById('admin-sidebar-scroll');
            if (adminSidebar) {
                sessionStorage.setItem('admin-sidebar-scroll-top', String(adminSidebar.scrollTop));
            }
        });

        window.addEventListener('DOMContentLoaded', function() {
            const adminSidebar = document.getElementById('admin-sidebar-scroll');
            const savedScrollTop = sessionStorage.getItem('admin-sidebar-scroll-top');
            if (adminSidebar && savedScrollTop !== null) {
                adminSidebar.scrollTop = Number(savedScrollTop);
            }
        });
    </script>
</body>
</html>
