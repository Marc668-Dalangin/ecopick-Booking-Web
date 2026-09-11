<?php
require_once __DIR__ . '/../app/bootstrap.php';

$message = Session::get('password_recovery_message');
$messageType = Session::get('password_recovery_message_type', 'info');
Session::unset('password_recovery_message');
Session::unset('password_recovery_message_type');

$pageTitle = 'Forgot Password';
?>
<?php require_once __DIR__ . '/../app/views/header.php'; ?>

<div class="container-lg py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h2 class="card-title text-center mb-3 fw-bold">
                        <i class="bi bi-key"></i> Forgot Password
                    </h2>
                    <p class="text-muted text-center mb-4">Enter your registered email address to receive a password reset link.</p>

                    <?php if ($message !== null): ?>
                        <div class="alert alert-<?php echo Validator::escape($messageType); ?>" role="alert">
                            <?php echo Validator::escape($message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo APP_URL; ?>/user-junkshop/forgot_password_handler.php" novalidate>
                        <?php echo CSRF::field(); ?>
                        <div class="mb-4">
                            <label for="email" class="form-label">Registered Email</label>
                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                value="<?php echo Validator::escape($_POST['email'] ?? ''); ?>"
                                required
                                autocomplete="email"
                                placeholder="your@email.com"
                            >
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="bi bi-envelope"></i> Send Recovery Email
                        </button>
                    </form>

                    <div class="text-center">
                        <a href="<?php echo APP_URL; ?>/user-junkshop/login.php" class="text-decoration-none">
                            <i class="bi bi-arrow-left"></i> Back to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../app/views/footer.php'; ?>
