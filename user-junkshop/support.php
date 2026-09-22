<?php
require_once __DIR__ . '/../app/bootstrap.php'; require_once __DIR__ . '/../app/controllers/AdminFeatureController.php';
Auth::requireLogin();
$controller = new AdminFeatureController();
$is_expired = $controller->isAccountExpired((int) Auth::userId());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_expired) {
        $_SESSION['flash_message'] = 'Action locked: Cannot send messages while your account is expired.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: support.php');
        exit;
    }

    if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = 'Security token expired. Please try again.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: support.php');
        exit;
    }

    $result = $controller->submitConcern(
        Auth::userId(),
        (string) ($_POST['subject'] ?? ''),
        (string) ($_POST['description'] ?? '')
    );

    $_SESSION['flash_message'] = $result['message'];
    $_SESSION['flash_type'] = $result['success'] ? 'success' : 'danger';

    header('Location: support.php');
    exit;
}

$concerns = $controller->listConcerns(Auth::userId());
$pageTitle = 'Support & Concerns';
$currentPage = 'support';
$userDisplayName = Auth::userName();
ob_start();
?><?php if ($is_expired): ?><div class="alert alert-warning d-flex align-items-center mb-3" role="alert"><i class="bi bi-lock-fill me-2 fs-4"></i><div><strong>Account Expired:</strong> Your account subscription or access period has expired. This page is currently locked in read-only mode. You may view your previous support history and concerns, but you cannot submit new messages or create new concerns.</div></div><?php endif; ?><div class="row g-4"><div class="col-lg-5"><div class="card border-0 shadow-sm"><div class="card-body p-4"><h2 class="fw-bold mb-1">Submit a concern</h2><p class="text-muted mb-4">Send a basic ticket to the EcoPick admin team.</p><form method="post" novalidate><fieldset<?php echo $is_expired ? ' disabled' : ''; ?>><?php echo CSRF::field(); ?><label class="form-label" for="subject">Subject</label><input class="form-control mb-2" id="subject" name="subject" maxlength="50" required><div class="small text-muted mb-3"><span id="subjectCount">0</span>/50</div><label class="form-label" for="description">Description</label><textarea class="form-control mb-2" id="description" name="description" rows="6" maxlength="150" required></textarea><div class="small text-muted mb-3"><span id="descriptionCount">0</span>/150</div><button type="submit" class="btn btn-primary"<?php echo $is_expired ? ' disabled' : ''; ?>>Submit concern</button></fieldset></form></div></div></div><div class="col-lg-7"><div class="card border-0 shadow-sm"><div class="card-body p-4"><h4 class="fw-bold mb-3">My submitted concerns</h4><?php foreach ($concerns as $concern): $createdAt = !empty($concern['created_at']) ? date('Y-m-d h:i A', strtotime($concern['created_at'])) : 'Unknown'; ?><div class="border-bottom py-3"><div class="d-flex justify-content-between align-items-center gap-3"><strong><?php echo Validator::escape($concern['subject']); ?></strong><span class="badge text-bg-secondary"><?php echo Validator::escape($concern['status']); ?></span></div><div class="small text-muted mb-2"><?php echo Validator::escape($createdAt); ?></div><p class="mb-1 text-muted"><?php echo nl2br(Validator::escape($concern['description'])); ?></p><?php if (!empty($concern['admin_note'])): ?><div class="small"><strong>Admin:</strong> <?php echo Validator::escape($concern['admin_note']); ?></div><?php endif; ?></div><?php endforeach; ?><?php if (!$concerns): ?><p class="text-muted mb-0">No concerns submitted.</p><?php endif; ?></div></div></div></div><script>
document.addEventListener('DOMContentLoaded', function () {
    const counters = [
        { input: document.getElementById('subject'), counter: document.getElementById('subjectCount'), max: 50 },
        { input: document.getElementById('description'), counter: document.getElementById('descriptionCount'), max: 150 }
    ];

    counters.forEach(({ input, counter, max }) => {
        if (!input || !counter) return;
        const update = () => {
            const length = (input.value || '').length;
            counter.textContent = String(length);
            counter.classList.toggle('text-danger', length > max);
        };
        input.addEventListener('input', update);
        update();
    });
});
</script><?php $content = ob_get_clean(); require_once __DIR__ . '/../app/views/user_dashboard_shell.php';