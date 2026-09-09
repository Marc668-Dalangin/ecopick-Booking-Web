<?php
/**
 * Public Registration Page - Choose User Type
 */

require_once __DIR__ . '/../app/bootstrap.php';

// Redirect if already logged in
Auth::redirectIfAuthenticated();

$pageTitle = 'Register';
?>
<?php require_once __DIR__ . '/../app/views/header.php'; ?>

<div class="container-lg py-5">
    <div class="row justify-content-center mb-5">
        <div class="col-lg-8">
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-3">
                    <i class="bi bi-person-check"></i> Join EcoPick
                </h2>
                <p class="text-muted fs-5">
                    Choose your account type to get started
                </p>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <!-- Seller Registration Card -->
        <div class="col-md-6 col-lg-5 mb-4">
            <div class="card shadow-sm border-0 h-100 hover-lift">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <div class="display-4 text-primary mb-3">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <h3 class="card-title fw-bold">Register as Seller</h3>
                    </div>

                    <p class="text-muted text-center mb-4">
                        Sell your recyclable materials to verified junkshops in Lipa City
                    </p>

                    <ul class="list-unstyled text-muted mb-4">
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            Quick and easy registration
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            Get paid by verified junkshops
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            No upfront costs
                        </li>
                        <li>
                            <i class="bi bi-check-circle text-success"></i>
                            Track pickup requests
                        </li>
                    </ul>

                    <a href="<?php echo APP_URL; ?>/user-junkshop/register-seller.php" class="btn btn-primary w-100">
                        <i class="bi bi-person-plus"></i> Register as Seller
                    </a>
                </div>
            </div>
        </div>

        <!-- Junkshop Registration Card -->
        <div class="col-md-6 col-lg-5 mb-4">
            <div class="card shadow-sm border-0 h-100 hover-lift">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <div class="display-4 text-success mb-3">
                            <i class="bi bi-shop"></i>
                        </div>
                        <h3 class="card-title fw-bold">Register Your Junkshop</h3>
                    </div>

                    <p class="text-muted text-center mb-4">
                        Become a verified EcoPick partner and receive pickup requests
                    </p>

                    <ul class="list-unstyled text-muted mb-4">
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            Verified partner status
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            Receive seller requests
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle text-success"></i>
                            Build your reputation
                        </li>
                        <li>
                            <i class="bi bi-check-circle text-success"></i>
                            Grow your business
                        </li>
                    </ul>

                    <a href="<?php echo APP_URL; ?>/user-junkshop/register-junkshop.php" class="btn btn-success w-100">
                        <i class="bi bi-plus-circle"></i> Register Your Junkshop
                    </a>

                    <small class="text-muted d-block text-center mt-3">
                        Requires admin approval
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Link -->
    <div class="row justify-content-center mt-5">
        <div class="col-lg-8">
            <div class="alert alert-light text-center border" role="alert">
                Already have an account?
                <a href="<?php echo APP_URL; ?>/user-junkshop/login.php" class="fw-bold text-decoration-none">
                    Login here
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    .hover-lift {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .hover-lift:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
    }
</style>

<?php require_once __DIR__ . '/../app/views/footer.php'; ?>
