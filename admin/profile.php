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

$pdo = Database::getInstance()->getPDO();
$smsEnabled = 1;
$systemAlert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_sms_settings'])) {
    if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
        $systemAlert = ['type' => 'danger', 'message' => 'Security token expired. Please try again.'];
    } else {
        $smsEnabled = (isset($_POST['sms_enabled']) && $_POST['sms_enabled'] === '1') ? 1 : 0;
        $pdo->prepare(
            'INSERT INTO fee_settings (id, sms_enabled) VALUES (1, :sms_enabled) ON DUPLICATE KEY UPDATE sms_enabled = VALUES(sms_enabled), updated_at = CURRENT_TIMESTAMP'
        )->execute(['sms_enabled' => $smsEnabled]);
        $systemAlert = [
            'type' => 'success',
            'message' => 'System settings updated: SMS notifications are now ' . ($smsEnabled ? 'Enabled' : 'Disabled') . '.'
        ];
    }
} else {
    $smsEnabled = (int) ($pdo->query('SELECT sms_enabled FROM fee_settings ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 1);
}

$pageTitle = 'Admin Profile';
$activePage = 'profile';
ob_start();
?>
<?php if ($systemAlert): ?>
<div class="alert alert-<?php echo Validator::escape($systemAlert['type']); ?> alert-dismissible fade show" role="alert">
    <?php echo Validator::escape($systemAlert['message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>
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

<div class="row g-4 mt-1">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="post" action="">
                    <?php echo CSRF::field(); ?>
                    <input type="hidden" name="update_sms_settings" value="1">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                        <div>
                            <h5 class="fw-bold mb-1">PhilSMS API Notifications</h5>
                            <p class="text-muted mb-0">Toggle platform-wide SMS dispatch for pickup alerts and status updates.</p>
                        </div>
                        <div class="form-check form-switch ms-md-auto mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="sms_enabled" name="sms_enabled" value="1" <?php echo $smsEnabled ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-semibold" for="sms_enabled"><?php echo $smsEnabled ? 'Enabled' : 'Disabled'; ?></label>
                        </div>
                    </div>
                    <div class="mt-4 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';
