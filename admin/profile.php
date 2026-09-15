<?php
require_once __DIR__ . '/../app/bootstrap.php';

if (!Auth::check()) {
    header('Location: ' . APP_URL . '/admin-private-dnstl/login.php');
    exit;
}

if (Auth::userRole() !== 'admin') {
    header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
    exit;
}

$pageTitle = 'Admin Profile';
$activePage = 'profile';
ob_start();
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4 text-center">
                <div class="profile-avatar profile-avatar-lg rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center mb-3">
                    <i class="bi bi-person-circle"></i>
                </div>
                <h4 class="fw-bold mb-1"><?php echo Validator::escape(Auth::userName()); ?></h4>
                <div class="text-muted mb-3">EcoPick Administrator</div>
                <span class="badge bg-success text-white">Authorized Admin</span>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-4"><i class="bi bi-person-vcard"></i> Profile Summary</h4>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted small d-block mb-1">Full name</label>
                        <div class="fw-semibold"><?php echo Validator::escape(Auth::userName()); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small d-block mb-1">Role</label>
                        <div class="fw-semibold">Administrator</div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small d-block mb-1">Email</label>
                        <div class="fw-semibold"><?php echo Validator::escape(Auth::userEmail()); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small d-block mb-1">Access level</label>
                        <div class="fw-semibold">Full platform management</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';
