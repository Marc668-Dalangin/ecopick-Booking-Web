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
$settings = $pdo->query('SELECT philsms_api_token, philsms_endpoint, philsms_sender_id, sms_enabled FROM fee_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];
$sms_enabled = (int) ($settings['sms_enabled'] ?? 1);
$profileCooldownSetting = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = :setting_key LIMIT 1');
$profileCooldownSetting->execute([':setting_key' => 'profile_cooldown_days']);
$profileCooldownValue = $profileCooldownSetting->fetchColumn();
$profile_cooldown_days = max(0, (int) ($profileCooldownValue === false ? 30 : $profileCooldownValue));
$systemAlert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_sms_settings'])) {
    if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
        $systemAlert = ['type' => 'danger', 'message' => 'Security token expired. Please try again.'];
    } else {
        $sms_enabled = (isset($_POST['sms_enabled']) && (int) $_POST['sms_enabled'] === 1) ? 1 : 0;
        $stmt = $pdo->prepare(
            'UPDATE fee_settings
             SET philsms_api_token = :api_token,
                 philsms_endpoint = :endpoint,
                 philsms_sender_id = :sender_id,
                 sms_enabled = :sms_enabled
             WHERE id = 1'
        );
        $stmt->execute([
            ':api_token' => $_POST['philsms_api_token'] ?? ($settings['philsms_api_token'] ?? null),
            ':endpoint' => $_POST['philsms_endpoint'] ?? ($settings['philsms_endpoint'] ?? 'https://dashboard.philsms.com/api/v3/sms/send'),
            ':sender_id' => $_POST['philsms_sender_id'] ?? ($settings['philsms_sender_id'] ?? 'PhilSMS'),
            ':sms_enabled' => $sms_enabled,
        ]);
        $_SESSION['flash_message'] = 'PhilSMS settings updated successfully.';
        header('Location: profile.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile_cooldown'])) {
    if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
        $systemAlert = ['type' => 'danger', 'message' => 'Security token expired. Please try again.'];
    } else {
        $profile_cooldown_days = filter_var($_POST['profile_cooldown_days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($profile_cooldown_days === false) {
            $systemAlert = ['type' => 'danger', 'message' => 'Profile update cooldown must be a non-negative whole number.'];
            $profile_cooldown_days = 30;
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO system_settings (setting_key, setting_value, updated_at)
                 VALUES (:setting_key, :setting_value, NOW())
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
            );
            $stmt->execute([':setting_key' => 'profile_cooldown_days', ':setting_value' => (string) $profile_cooldown_days]);
            $_SESSION['flash_message'] = 'Profile update cooldown updated successfully.';
            header('Location: profile.php');
            exit;
        }
    }
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
                    <input type="hidden" name="update_profile_cooldown" value="1">
                    <label class="form-label fw-bold" for="profile_cooldown_days">Profile Update Cooldown (in Days)</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="profile_cooldown_days" name="profile_cooldown_days" min="0" step="1" value="<?php echo (int) $profile_cooldown_days; ?>" required>
                        <span class="input-group-text">days</span>
                    </div>
                    <div class="form-text">Sellers and junkshops must wait this many days between profile detail updates.</div>
                    <div class="mt-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Cooldown</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="post" action="">
                    <?php echo CSRF::field(); ?>
                    <input type="hidden" name="update_sms_settings" value="1">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                        <div>
                            <h5 class="fw-bold mb-1">Toggle SMS Notifications</h5>
                            <p class="text-muted mb-0">Toggle platform-wide SMS dispatch for pickup alerts and status updates.</p>
                        </div>
                        <div class="form-check form-switch ms-md-auto mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="sms_enabled" name="sms_enabled" value="1" <?php echo ($sms_enabled === 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-semibold" for="sms_enabled"><?php echo ($sms_enabled === 1) ? 'Enabled' : 'Disabled'; ?></label>
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
