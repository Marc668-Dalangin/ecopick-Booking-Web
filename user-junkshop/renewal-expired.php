<?php
require_once __DIR__ . '/../app/bootstrap.php';

$pageTitle = 'Partnership Expired';
require_once __DIR__ . '/../app/views/header.php';
?>
<div class="container-lg py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5 text-center">
                    <h1 class="h3 fw-bold mb-3">Partnership period expired</h1>
                    <p class="text-muted mb-4">Your junkshop account has been deactivated because its partnership period has ended. Please contact EcoPick administration to renew your partnership.</p>
                    <a class="btn btn-primary" href="<?php echo APP_URL; ?>/user-junkshop/login.php">Back to login</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../app/views/footer.php'; ?>