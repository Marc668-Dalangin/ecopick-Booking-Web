<?php
require_once __DIR__ . '/../app/bootstrap.php';

$token = trim((string)($_GET['token'] ?? ''));
$email = strtolower(trim((string)($_GET['email'] ?? '')));
$error = Session::get('password_reset_error');
$success = Session::get('password_reset_success');
Session::unset('password_reset_error');
Session::unset('password_reset_success');

$tokenIsValid = false;
if ($success === null && preg_match('/^[a-f0-9]{64}$/', $token) && Validator::email($email)) {
    $reset = Database::getInstance()->query(
        'SELECT email FROM password_resets WHERE email = :email AND token = :token AND expires_at > UTC_TIMESTAMP() LIMIT 1',
        ['email' => $email, 'token' => $token]
    )->fetch();
    $tokenIsValid = (bool) $reset;
}

if (!$tokenIsValid && $success === null && $error === null) {
    $error = 'This password reset link is invalid or has expired. Please request a new one.';
}

$pageTitle = 'Reset Password';
?>
<?php require_once __DIR__ . '/../app/views/header.php'; ?>

<div class="container-lg py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h2 class="card-title text-center mb-4 fw-bold">
                        <i class="bi bi-shield-lock"></i> Reset Password
                    </h2>

                    <?php if ($success !== null): ?>
                        <div class="alert alert-success" role="alert">
                            <?php echo Validator::escape($success); ?>
                        </div>
                        <a href="<?php echo APP_URL; ?>/user-junkshop/login.php" class="btn btn-primary w-100">
                            <i class="bi bi-box-arrow-in-right"></i> Go to Login
                        </a>
                    <?php elseif ($error !== null): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo Validator::escape($error); ?>
                        </div>
                        <a href="<?php echo APP_URL; ?>/user-junkshop/forgot-password.php" class="btn btn-primary w-100">
                            <i class="bi bi-envelope"></i> Request a New Link
                        </a>
                    <?php elseif ($tokenIsValid): ?>
                        <form method="POST" action="<?php echo APP_URL; ?>/user-junkshop/reset_password_handler.php" novalidate>
                            <?php echo CSRF::field(); ?>
                            <input type="hidden" name="token" value="<?php echo Validator::escape($token); ?>">
                            <input type="hidden" name="email" value="<?php echo Validator::escape($email); ?>">

                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required minlength="8" autocomplete="new-password" placeholder="••••••••">
                                    <button class="btn btn-outline-secondary password-toggle" type="button" data-target="password" data-password-toggle-ready="false" aria-label="Show password" aria-pressed="false">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">At least 8 characters</small>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password" placeholder="••••••••">
                                    <button class="btn btn-outline-secondary password-toggle" type="button" data-target="confirm_password" data-password-toggle-ready="false" aria-label="Show password" aria-pressed="false">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-check2-circle"></i> Reset Password
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($success === null && !$tokenIsValid): ?>
                        <div class="text-center mt-3">
                            <a href="<?php echo APP_URL; ?>/user-junkshop/login.php" class="text-decoration-none">Back to Login</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/views/footer.php'; ?>
