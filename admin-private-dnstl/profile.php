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
$maintenanceSettingStmt = $pdo->query(
    "SELECT setting_key, setting_value
     FROM system_settings
     WHERE setting_key IN ('maintenance_mode', 'maintenance_scheduled_date', 'maintenance_scheduled_time', 'maintenance_message')"
);
$maintenanceSettings = $maintenanceSettingStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$maintenance_mode = (string) ($maintenanceSettings['maintenance_mode'] ?? 'off');
$maintenance_scheduled_date = (string) ($maintenanceSettings['maintenance_scheduled_date'] ?? '');
$maintenance_scheduled_time = (string) ($maintenanceSettings['maintenance_scheduled_time'] ?? '');
$maintenance_message = (string) ($maintenanceSettings['maintenance_message'] ?? 'The system is currently undergoing scheduled maintenance. Please check back soon.');
$maintenance_reset_timestamp = (string) ($maintenanceSettings['maintenance_reset_timestamp'] ?? '0');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_maintenance_settings'])) {
    if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
        $systemAlert = ['type' => 'danger', 'message' => 'Security token expired. Please try again.'];
    } else {
        $previousMaintenanceMode = $maintenance_mode;
        $maintenance_mode = in_array($_POST['maintenance_mode'] ?? '', ['off', 'on', 'scheduled'], true)
            ? (string) $_POST['maintenance_mode']
            : 'off';
        $maintenance_scheduled_date = trim((string) ($_POST['maintenance_scheduled_date'] ?? ''));
        $maintenance_scheduled_time = trim((string) ($_POST['maintenance_scheduled_time'] ?? ''));
        $maintenance_message = trim((string) ($_POST['maintenance_message'] ?? ''));
        if ($maintenance_message === '') {
            $maintenance_message = 'The system is currently undergoing scheduled maintenance. Please check back soon.';
        }
        if (strlen($maintenance_message) > 2000) {
            $systemAlert = ['type' => 'danger', 'message' => 'The maintenance message must be 2,000 characters or fewer.'];
        } elseif ($maintenance_mode === 'scheduled' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $maintenance_scheduled_date) || !preg_match('/^\d{2}:\d{2}$/', $maintenance_scheduled_time))) {
            $systemAlert = ['type' => 'danger', 'message' => 'Scheduled maintenance requires a valid date and time.'];
        } else {
            $saveMaintenanceSetting = $pdo->prepare(
                "INSERT INTO system_settings (setting_key, setting_value, updated_at)
                 VALUES (:setting_key, :setting_value, NOW())
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
            );
            foreach ([
                'maintenance_mode' => $maintenance_mode,
                'maintenance_scheduled_date' => $maintenance_scheduled_date,
                'maintenance_scheduled_time' => $maintenance_scheduled_time,
                'maintenance_message' => $maintenance_message,
            ] as $settingKey => $settingValue) {
                $saveMaintenanceSetting->execute([':setting_key' => $settingKey, ':setting_value' => $settingValue]);
            }
            if ($previousMaintenanceMode !== 'off' && $maintenance_mode === 'off') {
                $pdo->prepare(
                    "INSERT INTO system_settings (setting_key, setting_value, updated_at)
                     VALUES ('maintenance_reset_timestamp', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                     ON DUPLICATE KEY UPDATE setting_value = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP"
                )->execute();
            }
            $_SESSION['flash_message'] = 'Maintenance settings updated successfully.';
            header('Location: profile.php');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_broadcast'])) {
    if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
        $systemAlert = ['type' => 'danger', 'message' => 'Security token expired. Please try again.'];
    } else {
        $broadcastDate = trim((string) ($_POST['broadcast_date'] ?? ''));
        $broadcastTime = trim((string) ($_POST['broadcast_time'] ?? ''));
        $broadcastDateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $broadcastDate . ' ' . $broadcastTime, new DateTimeZone(APP_TIMEZONE));
        if (!$broadcastDateTime || $broadcastDateTime->format('Y-m-d H:i') !== $broadcastDate . ' ' . $broadcastTime) {
            $systemAlert = ['type' => 'danger', 'message' => 'Enter a valid maintenance date and time.'];
        } else {
            $broadcastResult = MailerService::broadcastMaintenanceNotice($broadcastDate, $broadcastTime);
            $systemAlert = [
                'type' => $broadcastResult['failed'] > 0 ? 'warning' : 'success',
                'message' => 'Broadcast complete: ' . $broadcastResult['sent'] . ' sent, ' . $broadcastResult['failed'] . ' failed out of ' . $broadcastResult['total'] . ' recipients.',
            ];
            if (!empty($broadcastResult['error'])) {
                $systemAlert = ['type' => 'danger', 'message' => $broadcastResult['error']];
            }
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
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-cone-striped me-2"></i>System Maintenance Mode</h5>
                <p class="text-muted mb-4">Administrators remain signed in while seller and junkshop portals are temporarily unavailable.</p>
                <form method="post" action="">
                    <?php echo CSRF::field(); ?>
                    <input type="hidden" name="update_maintenance_settings" value="1">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="maintenance_mode">Mode</label>
                            <select class="form-select" id="maintenance_mode" name="maintenance_mode">
                                <option value="off" <?php echo $maintenance_mode === 'off' ? 'selected' : ''; ?>>Off</option>
                                <option value="on" <?php echo $maintenance_mode === 'on' ? 'selected' : ''; ?>>On now</option>
                                <option value="scheduled" <?php echo $maintenance_mode === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="maintenance_scheduled_date">Start date</label>
                            <input type="date" class="form-control" id="maintenance_scheduled_date" name="maintenance_scheduled_date" value="<?php echo Validator::escape($maintenance_scheduled_date); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" for="maintenance_scheduled_time">Start time</label>
                            <input type="time" class="form-control" id="maintenance_scheduled_time" name="maintenance_scheduled_time" value="<?php echo Validator::escape($maintenance_scheduled_time); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="maintenance_message">Maintenance message</label>
                            <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="3" maxlength="2000" required><?php echo Validator::escape($maintenance_message); ?></textarea>
                        </div>
                    </div>
                    <div class="mt-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-warning"><i class="bi bi-save me-1"></i>Save Maintenance Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-envelope-paper me-2"></i>Email Notification Broadcast</h5>
                <p class="text-muted mb-4">Send an operational message to seller and junkshop accounts with valid email addresses.</p>
                <form method="post" action="">
                    <?php echo CSRF::field(); ?>
                    <input type="hidden" name="send_broadcast" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="broadcast_date">Maintenance date</label>
                            <input type="date" class="form-control" id="broadcast_date" name="broadcast_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="broadcast_time">Maintenance time</label>
                            <input type="time" class="form-control" id="broadcast_time" name="broadcast_time" required>
                        </div>
                    </div>
                    <div class="mt-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Send Broadcast</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';
