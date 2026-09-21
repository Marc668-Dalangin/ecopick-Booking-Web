<?php
/**
 * Admin Login Page
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/LoginController.php';
$siteFavicon = APP_URL . '/assets/images/logo.png';

// Redirect if already logged in as admin
if (Auth::check() && Auth::userRole() === 'admin') {
    header('Location: ' . APP_URL . '/admin-private-dnstl/dashboard.php');
    exit;
}

// Redirect non-admin logged-in users
if (Auth::check() && Auth::userRole() !== 'admin') {
    Auth::logout();
}

// Handle form submission
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = Validator::sanitizeEmail($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $controller = new LoginController();
        $result = $controller->authenticate($email, $password);

        if ($result['success']) {
            // Check if user is admin
            if ($result['role'] === 'admin') {
                header('Location: ' . APP_URL . '/admin-private-dnstl/dashboard.php');
                exit;
            } else {
                // Non-admin trying to access admin panel
                Auth::logout();
                $error = 'Admin access only';
            }
        } else {
            $error = $result['error'];
        }
    }
}

$pageTitle = 'Admin Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoPick - Admin Login</title>
    <link rel="icon" type="image/png" sizes="256x256" href="<?php echo htmlspecialchars($siteFavicon, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo htmlspecialchars($siteFavicon, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-green: #0a8f5c;
            --primary-teal: #1a9e7a;
            --dark-text: #2c3e50;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--dark-text);
            background: linear-gradient(135deg, rgba(10, 143, 92, 0.1) 0%, rgba(26, 158, 122, 0.05) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .btn-primary {
            background-color: var(--primary-green);
            border-color: var(--primary-green);
        }

        .btn-primary:hover {
            background-color: var(--primary-teal);
            border-color: var(--primary-teal);
        }

        .form-control:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 0.2rem rgba(10, 143, 92, 0.25);
        }

        .admin-header {
            color: #dc3545;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold admin-header mb-2">
                                <i class="bi bi-shield-lock"></i> Admin Panel
                            </h2>
                            <p class="text-muted small">EcoPick Administration</p>
                        </div>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <?php echo Validator::escape($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" novalidate>
                            <!-- CSRF Token -->
                            <?php echo CSRF::field(); ?>

                            <!-- Email Field -->
                            <div class="mb-3">
                                <label for="email" class="form-label">Admin Email</label>
                                <input 
                                    type="email" 
                                    class="form-control" 
                                    id="email" 
                                    name="email" 
                                    value="<?php echo isset($_POST['email']) ? Validator::escape($_POST['email']) : ''; ?>"
                                    required
                                    placeholder="admin"
                                >
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
                            </div>

                            <!-- Login Button -->
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-box-arrow-in-right"></i> Admin Login
                            </button>
                        </form>

                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/password-toggle.js" defer></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
</body>
</html>
