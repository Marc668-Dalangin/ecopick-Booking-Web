<?php
/**
 * Seller Registration Page
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/RegistrationController.php';

// Redirect if already logged in
Auth::redirectIfAuthenticated();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $data = [
            'first_name' => $_POST['first_name'] ?? '',
            'last_name' => $_POST['last_name'] ?? '',
            'username' => $_POST['username'] ?? '',
            'email' => $_POST['email'] ?? '',
            'mobile_number' => $_POST['mobile_number'] ?? '',
            'address' => $_POST['address'] ?? '',
            'barangay' => $_POST['barangay'] ?? '',
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'terms' => $_POST['terms'] ?? ''
        ];

        $controller = new RegistrationController();
        $result = $controller->registerSeller($data);

        if ($result['success']) {
            $success = true;
        } else {
            $errors = $result['errors'];
        }
    }
}

$pageTitle = 'Register as Seller';
?>
<?php require_once __DIR__ . '/../app/views/header.php'; ?>

<div class="container-lg py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h2 class="card-title text-center mb-4 fw-bold">
                        <i class="bi bi-person-badge"></i> Register as Seller
                    </h2>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill"></i>
                            <strong>Success!</strong> Your account has been created.
                            <a href="<?php echo APP_URL; ?>/user-junkshop/login.php" class="alert-link">Login now</a>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>

                        <div class="text-center">
                            <a href="<?php echo APP_URL; ?>/user-junkshop/login.php" class="btn btn-primary">
                                Go to Login
                            </a>
                        </div>
                    <?php else: ?>
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <strong>Please fix the following errors:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo Validator::escape($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" novalidate>
                            <!-- CSRF Token -->
                            <?php echo CSRF::field(); ?>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        class="form-control uppercase-input"
                                        id="first_name"
                                        name="first_name"
                                        value="<?php echo isset($_POST['first_name']) ? Validator::escape($_POST['first_name']) : ''; ?>"
                                        required
                                        pattern="[A-Za-z\s]+"
                                        title="Letters and spaces only"
                                        placeholder="Juan"
                                    >
                                </div>
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        class="form-control uppercase-input"
                                        id="last_name"
                                        name="last_name"
                                        value="<?php echo isset($_POST['last_name']) ? Validator::escape($_POST['last_name']) : ''; ?>"
                                        required
                                        pattern="[A-Za-z\s]+"
                                        title="Letters and spaces only"
                                        placeholder="Dela Cruz"
                                    >
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($_POST['username']) ? Validator::escape($_POST['username']) : ''; ?>" required minlength="5" maxlength="100" pattern="^(?=.{5,100}$)(?!.*\s)[A-Z]?[a-z0-9\W_]+$" placeholder="Marc123">
                            </div>

                            <!-- Email -->
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input 
                                    type="email" 
                                    class="form-control" 
                                    id="email" 
                                    name="email"
                                    value="<?php echo isset($_POST['email']) ? Validator::escape($_POST['email']) : ''; ?>"
                                    required
                                    placeholder="your@email.com"
                                >
                                <small class="text-muted">We'll never share your email.</small>
                            </div>

                            <!-- Mobile Number -->
                            <div class="mb-3">
                                <label for="mobile_number" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">09</span>
                                    <input
                                        type="tel"
                                        class="form-control"
                                        id="mobile_number"
                                        name="mobile_number"
                                        value="<?php echo isset($_POST['mobile_number']) ? Validator::escape($_POST['mobile_number']) : ''; ?>"
                                        required
                                        inputmode="numeric"
                                        maxlength="9"
                                        pattern="[0-9]*"
                                        placeholder="123456789"
                                        aria-describedby="mobile_number_help"
                                    >
                                </div>
                                <div id="mobile_number_help" class="form-text">Enter the remaining 9 digits only. The 09 prefix is fixed.</div>
                            </div>

                            <!-- Address -->
                            <div class="mb-3">
                                <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    id="address" 
                                    name="address"
                                    value="<?php echo isset($_POST['address']) ? Validator::escape($_POST['address']) : ''; ?>"
                                    required
                                    placeholder="Street address or location details"
                                >
                            </div>

                            <!-- Barangay -->
                            <div class="mb-3">
                                <label for="barangay" class="form-label">Barangay <span class="text-danger">*</span></label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    id="barangay" 
                                    name="barangay"
                                    value="<?php echo isset($_POST['barangay']) ? Validator::escape($_POST['barangay']) : ''; ?>"
                                    required
                                    placeholder="Your barangay"
                                >
                            </div>

                            <!-- Password -->
                            <div class="mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
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
                                        id="togglePassword1"
                                        data-target="password"
                                        data-password-toggle-ready="false"
                                        aria-label="Show password"
                                        aria-pressed="false"
                                    >
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">At least 8 characters</small>
                            </div>

                            <!-- Confirm Password -->
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input 
                                        type="password" 
                                        class="form-control" 
                                        id="confirm_password" 
                                        name="confirm_password"
                                        required
                                        placeholder="••••••••"
                                    >
                                    <button 
                                        class="btn btn-outline-secondary password-toggle" 
                                        type="button" 
                                        id="togglePassword2"
                                        data-target="confirm_password"
                                        data-password-toggle-ready="false"
                                        aria-label="Show password"
                                        aria-pressed="false"
                                    >
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Terms & Conditions -->
                            <div class="mb-4 form-check">
                                <input 
                                    type="checkbox" 
                                    class="form-check-input" 
                                    id="terms" 
                                    name="terms"
                                    required
                                >
                                <label class="form-check-label" for="terms">
                                    I agree to the terms and conditions <span class="text-danger">*</span>
                                </label>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-person-plus"></i> Create Account
                            </button>
                        </form>

                        <!-- Login Link -->
                        <div class="text-center">
                            <p class="text-muted mb-0">
                                Already have an account?
                                <a href="<?php echo APP_URL; ?>/user-junkshop/login.php" class="text-decoration-none">
                                    Login here
                                </a>
                            </p>
                        </div>

                        <!-- Back Link -->
                        <div class="text-center mt-3">
                            <a href="<?php echo APP_URL; ?>/user-junkshop/register.php" class="text-muted text-decoration-none small">
                                <i class="bi bi-arrow-left"></i> Back to registration types
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .uppercase-input {
        text-transform: uppercase;
    }
</style>

<script>
    function validateUsername(input) {
        if (!input) return false;
        const valid = /^(?=.{5,100}$)(?!.*\s)[A-Z]?[a-z0-9\W_]+$/.test(input.value);
        input.setCustomValidity(valid ? '' : 'Use at least 5 characters with uppercase only at the beginning and no spaces.');
        return valid;
    }

    function validateMobileSuffix(input) {
        if (!input) return false;
        const digits = (input.value || '').replace(/\D/g, '').slice(0, 9);
        input.value = digits;
        const valid = /^\d{9}$/.test(digits);
        input.setCustomValidity(valid ? '' : 'Enter exactly 9 digits after 09.');
        return valid;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const usernameInput = document.getElementById('username');
        if (usernameInput) {
            usernameInput.addEventListener('input', function() {
                validateUsername(this);
            });
            usernameInput.addEventListener('blur', function() {
                validateUsername(this);
            });
        }

        ['first_name', 'last_name'].forEach((id) => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('input', function() {
                    this.value = this.value.replace(/[^a-zA-Z\s]/g, '').toUpperCase();
                });
            }
        });

        const mobileInput = document.getElementById('mobile_number');
        if (mobileInput) {
            mobileInput.addEventListener('input', function() {
                validateMobileSuffix(this);
            });
            mobileInput.addEventListener('blur', function() {
                validateMobileSuffix(this);
            });

            const form = mobileInput.closest('form');
            if (form) {
                form.addEventListener('submit', function(event) {
                    if (!validateMobileSuffix(mobileInput)) {
                        event.preventDefault();
                        mobileInput.reportValidity();
                    }
                });
            }
        }
    });
</script>

<?php require_once __DIR__ . '/../app/views/footer.php'; ?>
