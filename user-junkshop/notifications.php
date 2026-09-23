<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/NotificationController.php';

Auth::requireLogin();
$controller = new NotificationController();
$notifications = $controller->listForUser(Auth::userId());
$controller->markRead(Auth::userId());
$pageTitle = 'Notifications';
$currentPage = 'notifications';
$userDisplayName = Auth::userName();
$notificationTimezone = new DateTimeZone('Asia/Manila');
$parseNotificationDate = static function (string $value) use ($notificationTimezone): ?DateTimeImmutable {
	if ($value === '') return null;

	try {
		return new DateTimeImmutable($value, $notificationTimezone);
	} catch (Exception $exception) {
		return null;
	}
};
ob_start();
?>
<div class="card border-0 shadow-sm">
	<div class="card-body p-4">
		<h2 class="fw-bold mb-1">Notifications</h2>
		<p class="text-muted mb-4">Your durable EcoPick booking and account updates.</p>
		<?php if (empty($notifications)): ?>
			<div class="empty-state"><i class="bi bi-bell display-6 text-muted"></i><h5 class="mt-3">No notifications yet</h5></div>
		<?php else: ?>
			<?php
				$groupedNotifications = [];
				foreach ($notifications as $notification) {
					$createdAt = (string) ($notification['created_at'] ?? '');
					$createdDate = $parseNotificationDate($createdAt);
					$dateKey = $createdDate?->format('Y-m-d') ?? (new DateTimeImmutable('now', $notificationTimezone))->format('Y-m-d');
					$groupedNotifications[$dateKey][] = $notification;
				}
				krsort($groupedNotifications);
			?>
			<div class="notification-date-groups">
				<?php foreach ($groupedNotifications as $dateKey => $dateNotifications): ?>
					<?php
						$groupId = 'notification-group-' . md5($dateKey);
						$unreadCount = 0;
						foreach ($dateNotifications as $notification) {
							$isUnread = !isset($notification['read_at']) || $notification['read_at'] === null || trim((string) $notification['read_at']) === '';
							if ($isUnread) {
								$unreadCount++;
							}
						}
						$groupHasUnread = $unreadCount > 0;
					?>
					<div class="notification-date-group border-bottom pb-2 mb-3" data-date-group="<?php echo Validator::escape($dateKey); ?>">
						<button type="button" class="notification-toggle btn btn-link text-body text-decoration-none d-flex align-items-center justify-content-between w-100 px-0 py-2" data-bs-toggle="collapse" data-bs-target="#<?php echo $groupId; ?>" aria-expanded="<?php echo $groupHasUnread ? 'true' : 'false'; ?>">
							<span class="fw-semibold"><?php echo Validator::escape(DateTimeImmutable::createFromFormat('!Y-m-d', $dateKey, $notificationTimezone)->format('M j, Y')); ?></span>
							<span class="d-flex align-items-center gap-2 text-muted">
								<?php if ($groupHasUnread): ?><span class="badge bg-danger rounded-pill" data-date-unread-badge data-unread-count="<?php echo $unreadCount; ?>"><?php echo $unreadCount; ?> unread</span><?php endif; ?>
								<span class="small"><?php echo count($dateNotifications); ?> item<?php echo count($dateNotifications) === 1 ? '' : 's'; ?></span>
								<i class="bi bi-chevron-<?php echo $groupHasUnread ? 'up' : 'down'; ?> toggle-icon"></i>
							</span>
						</button>
						<div id="<?php echo $groupId; ?>" class="collapse <?php echo $groupHasUnread ? 'show' : ''; ?>">
							<div class="list-group list-group-flush mt-2">
								<?php foreach ($dateNotifications as $notification): ?>
									<?php
										$notificationDate = $parseNotificationDate((string) ($notification['created_at'] ?? ''));
										$isUnread = !isset($notification['read_at']) || $notification['read_at'] === null || trim((string) $notification['read_at']) === '';
									?>
									<a class="list-group-item list-group-item-action px-0 notification-item <?php echo $isUnread ? 'notification-unread bg-light' : ''; ?>" href="<?php echo Validator::escape($notification['link_url'] ?: '#'); ?>" data-notification-id="<?php echo (int) $notification['id']; ?>" data-created-at="<?php echo Validator::escape((string) ($notification['created_at'] ?? '')); ?>" data-is-unread="<?php echo $isUnread ? '1' : '0'; ?>">
										<div class="d-flex justify-content-between align-items-start gap-3">
											<strong><?php echo Validator::escape($notification['title']); ?></strong>
											<span class="small text-muted text-nowrap"><strong><?php echo $notificationDate?->format('h:i A') ?? 'N/A'; ?></strong></span>
										</div>
										<div class="text-muted"><?php echo Validator::escape($notification['message']); ?></div>
									</a>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';