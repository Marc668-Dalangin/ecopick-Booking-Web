<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/NotificationController.php';

Auth::requireLogin();
$controller = new NotificationController();
$controller->markRead(Auth::userId());
$notifications = $controller->listForUser(Auth::userId());
$pageTitle = 'Notifications';
$currentPage = 'notifications';
$userDisplayName = Auth::userName();
ob_start();
?>
<div class="card border-0 shadow-sm">
	<div class="card-body p-4">
		<h2 class="fw-bold mb-1">Notifications</h2>
		<p class="text-muted mb-4">Your durable EcoPick booking and account updates.</p>
		<?php if (empty($notifications)): ?>
			<div class="empty-state"><i class="bi bi-bell display-6 text-muted"></i><h5 class="mt-3">No notifications yet</h5></div>
		<?php else: ?>
			<div class="list-group list-group-flush">
				<?php foreach ($notifications as $notification): ?>
					<?php $notificationDate = strtotime((string) $notification['created_at']); ?>
					<a class="list-group-item list-group-item-action px-0" href="<?php echo Validator::escape($notification['link_url'] ?: '#'); ?>">
						<div class="d-flex justify-content-between">
							<strong><?php echo Validator::escape($notification['title']); ?></strong>
							<span class="small text-muted"><?php echo date('M j, Y', $notificationDate); ?> &bull; <strong><?php echo date('h:i A', $notificationDate); ?></strong></span>
						</div>
						<div class="text-muted"><?php echo Validator::escape($notification['message']); ?></div>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';