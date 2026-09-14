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

$pageTitle = 'Database Maintenance';
$activePage = 'backup-import';
ob_start();
?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-3"><i class="bi bi-download"></i> Download Backup</h4>
                <p class="text-muted">Create a complete SQL copy of the current database, including table definitions and records.</p>
                <a class="btn btn-primary" href="<?php echo APP_URL; ?>/admin-private-dnstl/backup_db.php">
                    <i class="bi bi-download"></i> Download Backup
                </a>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-3"><i class="bi bi-upload"></i> Import Database</h4>
                <p class="text-muted">Importing replaces the active database tables and records with the selected SQL backup.</p>
                <form method="post" action="<?php echo APP_URL; ?>/admin-private-dnstl/import_db.php" enctype="multipart/form-data" onsubmit="return confirm('Import this backup and replace the active database? This cannot be undone.');">
                    <?php echo CSRF::field(); ?>
                    <label class="form-label" for="backup_file">SQL backup file</label>
                    <input class="form-control mb-3" type="file" id="backup_file" name="backup_file" accept=".sql,application/sql" required>
                    <button class="btn btn-danger" type="submit"><i class="bi bi-arrow-repeat"></i> Import Database</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/admin_dashboard_shell.php';