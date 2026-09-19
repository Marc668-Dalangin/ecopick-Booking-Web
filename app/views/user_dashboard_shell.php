<?php
/**
 * Shared dashboard shell for seller and junkshop users.
 */
require_once __DIR__ . '/../controllers/NotificationController.php';

$userDisplayName = $userDisplayName ?? Auth::userName();
$userRole = Auth::userRole();
$currentPage = $currentPage ?? 'dashboard';
$notificationCount = (int) (new NotificationController())->unreadCount(Auth::userId());
$isExpiredJunkshop = $userRole === 'junkshop' && (bool) ($_SESSION['is_expired'] ?? false);
$siteFavicon = APP_URL . '/assets/images/logo.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? Validator::escape($pageTitle) . ' - EcoPick' : 'EcoPick'; ?></title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($siteFavicon, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($siteFavicon, ENT_QUOTES, 'UTF-8'); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <?php if ($currentPage === 'profile' && Auth::userRole() === 'junkshop'): ?><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><?php endif; ?>
    <link href="<?php echo APP_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="user-dashboard-body">
    <div class="user-dashboard-shell d-flex flex-nowrap min-vh-100">
        <aside class="user-sidebar col-lg-2 d-none d-lg-flex flex-column">
            <div class="user-brand">
                <i class="bi bi-recycling"></i>
                <span>EcoPick</span>
            </div>

            <nav class="nav flex-column user-nav">
                <a class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/dashboard.php">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
                <?php if (Auth::userRole() === 'seller'): ?>
                    <a class="nav-link <?php echo $currentPage === 'current-bookings' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/current-bookings.php">
                        <i class="bi bi-calendar2-check"></i>
                        <span>Current Bookings</span>
                    </a>
                    <a class="nav-link <?php echo $currentPage === 'transaction-history' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/transaction-history.php">
                        <i class="bi bi-receipt"></i>
                        <span>Transaction History</span>
                    </a>
                    <a class="nav-link <?php echo $currentPage === 'partner-prices' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/partner-junkshops.php">
                        <i class="bi bi-shop-window"></i>
                        <span>Partner Prices</span>
                    </a>
                <?php else: ?>
                    <a class="nav-link <?php echo $currentPage === 'renewal' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/renewal.php"><i class="bi bi-arrow-repeat"></i><span>Partnership Renewal</span></a>
                    <a class="nav-link <?php echo $currentPage === 'materials-prices' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/material-prices.php">
                        <i class="bi bi-recycle"></i>
                        <span>Materials & Prices</span>
                    </a>
                    <a class="nav-link <?php echo $currentPage === 'matched-requests' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/matched-requests.php">
                        <i class="bi bi-broadcast"></i>
                        <span>Matched Requests <span id="matched-requests-badge" class="badge bg-danger rounded-pill ms-2" style="display: none;">0</span></span>
                    </a>
                    <a class="nav-link <?php echo $currentPage === 'completed-transactions' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/completed-transactions.php">
                        <i class="bi bi-journal-check"></i>
                        <span>Completed Transactions</span>
                    </a>
                <?php endif; ?>
                <a class="nav-link <?php echo $currentPage === 'notifications' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/notifications.php"><i class="bi bi-bell"></i><span>Notifications <span class="badge rounded-pill bg-danger <?php echo $notificationCount > 0 ? '' : 'd-none'; ?>" data-notification-count><?php echo $notificationCount; ?></span></span></a>
                <a class="nav-link <?php echo $currentPage === 'support' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/support.php"><i class="bi bi-life-preserver"></i><span>Support & Concerns</span></a>
                <a class="nav-link <?php echo $currentPage === 'profile' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/profile.php">
                    <i class="bi bi-person-circle"></i>
                    <span>Profile</span>
                </a>
            </nav>

            <div class="user-sidebar-footer">
                <div class="small text-white-50 text-uppercase mb-2">Account</div>
                <div class="fw-semibold text-white"><?php echo Validator::escape($userDisplayName); ?></div>
                <div class="small text-white-50"><?php echo ucfirst(Validator::escape($userRole)); ?></div>
            </div>
        </aside>

        <div class="user-main col-12 col-lg-10">
            <?php if ($isExpiredJunkshop): ?>
                <div class="alert alert-warning text-center m-0 rounded-0 fw-bold sticky-top"><i class="fas fa-exclamation-triangle me-2"></i> Your partnership subscription has expired. Please pay the renewal fee to reactivate your account features.</div>
            <?php endif; ?>
            <header class="user-topbar">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#userSidebarMobile" aria-label="Open navigation">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <p class="eyebrow mb-0">EcoPick Member Portal</p>
                        <h1 class="page-title mb-0"><?php echo Validator::escape($pageTitle ?? 'Dashboard'); ?></h1>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <div class="d-none d-md-block text-end">
                        <div class="fw-semibold"><?php echo Validator::escape($userDisplayName); ?></div>
                        <div class="small text-muted"><?php echo ucfirst(Validator::escape($userRole)); ?></div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary position-relative" type="button" data-notification-toggle data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications">
                            <i class="bi bi-bell"></i>
                            <span class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle <?php echo $notificationCount > 0 ? '' : 'd-none'; ?>" data-notification-count><?php echo $notificationCount; ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end notification-dropdown-menu" data-notification-menu style="min-width: 320px;">
                            <li class="dropdown-header">Notifications</li>
                            <li data-notification-empty class="px-3 py-2 text-muted small <?php echo $notificationCount > 0 ? 'd-none' : ''; ?>">No unread notifications.</li>
                        </ul>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/user-junkshop/profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/user-junkshop/logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="user-content">
                <?php if (!empty($_SESSION['flash_message'] ?? '')): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($_SESSION['flash_type'] ?? 'success', ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
                        <?php echo Validator::escape($_SESSION['flash_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
                <?php endif; ?>

                <div class="offcanvas offcanvas-start" tabindex="-1" id="userSidebarMobile" aria-labelledby="userSidebarMobileLabel">
                    <div class="offcanvas-header">
                        <h5 class="offcanvas-title" id="userSidebarMobileLabel">EcoPick</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                    </div>
                    <div class="offcanvas-body p-0">
                        <nav class="nav flex-column user-nav px-3 py-3">
                            <a class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/dashboard.php">
                                <i class="bi bi-speedometer2"></i>
                                <span>Dashboard</span>
                            </a>
                            <?php if (Auth::userRole() === 'seller'): ?>
                                <a class="nav-link <?php echo $currentPage === 'current-bookings' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/current-bookings.php">
                                    <i class="bi bi-calendar2-check"></i>
                                    <span>Current Bookings</span>
                                </a>
                                <a class="nav-link <?php echo $currentPage === 'transaction-history' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/transaction-history.php">
                                    <i class="bi bi-receipt"></i>
                                    <span>Transaction History</span>
                                </a>
                                <a class="nav-link <?php echo $currentPage === 'partner-prices' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/partner-junkshops.php">
                                    <i class="bi bi-shop-window"></i>
                                    <span>Partner Prices</span>
                                </a>
                            <?php else: ?>
                                <a class="nav-link" href="<?php echo APP_URL; ?>/user-junkshop/renewal.php"><i class="bi bi-arrow-repeat"></i><span>Partnership Renewal</span></a>
                                <a class="nav-link <?php echo $currentPage === 'materials-prices' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/material-prices.php">
                                    <i class="bi bi-recycle"></i>
                                    <span>Materials & Prices</span>
                                </a>
                                <a class="nav-link <?php echo $currentPage === 'matched-requests' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/matched-requests.php">
                                    <i class="bi bi-broadcast"></i>
                                    <span>Matched Requests <span class="badge bg-danger rounded-pill ms-2" data-matched-requests-badge style="display: none;">0</span></span>
                                </a>
                                <a class="nav-link <?php echo $currentPage === 'completed-transactions' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/completed-transactions.php">
                                    <i class="bi bi-journal-check"></i>
                                    <span>Completed Transactions</span>
                                </a>
                            <?php endif; ?>
                            <a class="nav-link" href="<?php echo APP_URL; ?>/user-junkshop/notifications.php"><i class="bi bi-bell"></i><span>Notifications <span class="badge rounded-pill bg-danger <?php echo $notificationCount > 0 ? '' : 'd-none'; ?>" data-notification-count><?php echo $notificationCount; ?></span></span></a>
                            <a class="nav-link" href="<?php echo APP_URL; ?>/user-junkshop/support.php"><i class="bi bi-life-preserver"></i><span>Support & Concerns</span></a>
                            <a class="nav-link <?php echo $currentPage === 'profile' ? 'active' : ''; ?>" href="<?php echo APP_URL; ?>/user-junkshop/profile.php">
                                <i class="bi bi-person-circle"></i>
                                <span>Profile</span>
                            </a>
                        </nav>
                    </div>
                </div>

                <?php echo $content ?? ''; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/live-updates.js"></script>
    <script>
        window.ecopickNotificationUrl = '<?php echo APP_URL; ?>/api/notifications/fetch-latest.php';
        window.ecopickMarkReadUrl = '<?php echo APP_URL; ?>/api/notifications/mark-read.php';
        window.ecopickCsrfToken = '<?php echo Validator::escape(CSRF::token()); ?>';
    </script>
    <script src="<?php echo APP_URL; ?>/assets/js/notifications.js"></script>
    <?php if ($userRole === 'junkshop'): ?>
        <script>
            window.ecopickPendingRequestsCountUrl = '<?php echo APP_URL; ?>/user-junkshop/api/get_pending_requests_count.php';
        </script>
        <script src="<?php echo APP_URL; ?>/assets/js/matched-requests-badge.js"></script>
    <?php endif; ?>
    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>
