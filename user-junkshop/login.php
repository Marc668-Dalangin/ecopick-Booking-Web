<?php
/**
 * Public Login Page
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/LoginController.php';

// Redirect if already logged in
Auth::redirectIfAuthenticated();

// Handle form submission
$error = '';
$showPendingMessage = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $input = trim((string)($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        $controller = new LoginController();
        $result = $controller->authenticate($input, $password);

        if ($result['success']) {
            // Redirect based on role
            if (Auth::userRole() === 'admin') {
                header('Location: ' . APP_URL . '/admin/dashboard.php');
            } else {
                header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
            }
            exit;
        } else {
            $error = $result['error'];
            $showPendingMessage = isset($result['pending']) && $result['pending'];
        }
    }
}

$pageTitle = 'Login';
?>
<?php require_once __DIR__ . '/../app/views/header.php'; ?>

<div class="container-lg py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h2 class="card-title text-center mb-4 fw-bold">
                        <i class="bi bi-box-arrow-in-right"></i> Login to EcoPick
                    </h2>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <?php echo Validator::escape($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($showPendingMessage): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <i class="bi bi-info-circle-fill"></i>
                            <strong>Pending Approval:</strong> Your junkshop account is awaiting EcoPick admin approval. You will be able to login once approved.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" novalidate>
                        <!-- CSRF Token -->
                        <?php echo CSRF::field(); ?>

                        <!-- Email or Username Field -->
                        <div class="mb-3">
                            <label for="email" class="form-label">Email or Username</label>
                            <input 
                                type="text"
                                class="form-control" 
                                id="email" 
                                name="email" 
                                value="<?php echo isset($_POST['email']) ? Validator::escape($_POST['email']) : ''; ?>"
                                required
                                placeholder="your@email.com or Marc123"
                            >
                            <small class="text-muted">Use either your registered email address or username.</small>
                        </div>

                        <!-- Password Field -->
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <input 
                                    type="password" 
                                    class="form-control" 
                                    id="password" 
                                    name="password" 
                                    required
                                    placeholder="••••••••"
                                >
                                <button 
                                    class="btn btn-outline-secondary password-toggle" 
                                    type="button" 
                                    id="togglePassword"
                                    data-target="password"
                                    data-password-toggle-ready="false"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                >
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="text-end mt-2">
                                <a href="<?php echo APP_URL; ?>/user-junkshop/forgot-password.php" class="text-decoration-none">Forgot Password?</a>
                            </div>
                        </div>

                        <!-- Remember Me -->
                        <div class="mb-3 form-check">
                            <input 
                                type="checkbox" 
                                class="form-check-input" 
                                id="rememberMe" 
                                name="remember_me"
                            >
                            <label class="form-check-label" for="rememberMe">
                                Remember me (for future phases)
                            </label>
                        </div>

                        <!-- Login Button -->
                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </button>
                    </form>

                    <!-- Links -->
                    <div class="text-center">
                        <p class="text-muted mb-0">
                            Don't have an account?
                            <a href="<?php echo APP_URL; ?>/user-junkshop/register.php" class="text-decoration-none">
                                Register here
                            </a>
                        </p>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (sessionStorage.getItem('ecopick_reset_notif_state') === '1') {
            for (let i = localStorage.length - 1; i >= 0; i -= 1) {
                const key = localStorage.key(i);
                if (key && key.indexOf('ecopick_notif_state_') === 0) {
                    localStorage.removeItem(key);
                }
            }
            sessionStorage.removeItem('ecopick_reset_notif_state');
        }

        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        }
    });
</script>

<?php require_once __DIR__ . '/../app/views/footer.php'; ?>
