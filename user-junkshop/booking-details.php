<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/PickupRequestController.php';

if (!Auth::check()) { header('Location: ' . APP_URL . '/user-junkshop/login.php'); exit; }
if (Auth::userRole() !== 'seller') { header('Location: ' . APP_URL . '/user-junkshop/dashboard.php'); exit; }

$controller = new PickupRequestController();
$requestId = (int) ($_GET['id'] ?? 0);
$request = $controller->getSellerRequestDetails($requestId, Auth::userId());
if ($request === null) { http_response_code(404); $pageTitle = 'Booking Not Found'; $content = '<div class="alert alert-warning">This booking could not be found in your account.</div>'; require_once __DIR__ . '/../app/views/user_dashboard_shell.php'; exit; }
$pageTitle = 'Booking Details';
$currentPage = 'current-bookings';
$userDisplayName = Auth::userName();
$assignment = $controller->getSellerRequestAssignmentSummary($requestId, Auth::userId());
$statusOrder = ['Pending Request', 'Matched', 'Accepted', 'Scheduled', 'For Pickup', 'Completed'];
$cancellableStatuses = ['Pending Request', 'Matched', 'Accepted'];
$canCancel = in_array($request['current_status'], $cancellableStatuses, true);
$currentStatusIndex = array_search($request['current_status'], $statusOrder, true);
$currentStatusIndex = $currentStatusIndex === false ? 0 : $currentStatusIndex;
ob_start();
?>
<div class="row g-4">
    <div class="col-12"><a href="<?php echo APP_URL; ?>/user-junkshop/current-bookings.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Current bookings</a></div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm"><div class="card-body p-4 p-lg-5"><div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4"><div><p class="eyebrow mb-1">Booking tracking</p><h2 class="fw-bold mb-1"><?php echo Validator::escape($request['booking_reference']); ?></h2><p class="text-muted mb-0">Submitted <?php echo Validator::escape(date('M d, Y g:i A', strtotime($request['created_at']))); ?></p></div><span class="status-badge <?php echo str_contains((string)$request['current_status'], 'Cancelled') ? 'rejected' : 'pending'; ?>"><?php echo Validator::escape($request['current_status']); ?></span></div>
            <div class="alert alert-info" role="note"><i class="bi bi-info-circle me-2"></i>EcoPick is facilitating this request. Registered junkshops handle collection, weighing, assessment, and purchase in later steps.</div>
            <?php if ($canCancel): ?><div class="d-flex justify-content-end mb-4"><button type="button" class="btn btn-sm btn-outline-danger" id="detail-cancel-request"><i class="bi bi-x-circle"></i> Cancel booking</button></div><?php endif; ?>

            <div class="mb-4">
                <h5 class="fw-bold mb-3">Progress</h5>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($statusOrder as $index => $status): ?>
                        <?php $isComplete = $index <= $currentStatusIndex; $isActive = $index === $currentStatusIndex; ?>
                        <div class="flex-fill min-w-120px">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="status-step rounded-circle d-inline-flex align-items-center justify-content-center <?php echo $isComplete ? 'bg-success text-white' : ($isActive ? 'bg-primary text-white' : 'bg-light text-muted'); ?>">
                                    <?php echo $isComplete ? '<i class="bi bi-check"></i>' : ($index + 1); ?>
                                </span>
                                <small class="fw-semibold <?php echo $isActive ? 'text-primary' : ($isComplete ? 'text-success' : 'text-muted'); ?>"><?php echo Validator::escape($status); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <h5 class="fw-bold mb-3">Submitted materials</h5><div class="table-responsive mb-4"><table class="table align-middle"><thead class="table-light"><tr><th>Material</th><th>Category</th><th class="text-end">Estimated weight</th><?php if (!empty($assignment)): ?><th class="text-end">Matched price/kg</th><th class="text-end">Estimated value</th><?php endif; ?></tr></thead><tbody><?php $totalWeight = 0; foreach ($request['items'] as $item): $totalWeight += (float) $item['estimated_weight']; $matchedPrice = $item['matched_price_per_kg'] ?? null; $estimatedValue = $item['actual_value'] ?? ($item['estimated_value'] ?? null); $isPendingMatch = $request['current_status'] === 'Pending Request' && $matchedPrice === null; ?><tr><td><?php echo Validator::escape($item['material_name']); ?></td><td><?php echo Validator::escape($item['category']); ?></td><td class="text-end"><?php echo number_format((float) $item['estimated_weight'], 2); ?> <?php echo Validator::escape($item['unit_of_measure']); ?></td><?php if (!empty($assignment)): ?><td class="text-end"><?php echo $isPendingMatch ? '<span class="text-muted">Pending</span>' : '₱' . number_format((float) $matchedPrice, 2) . '/kg'; ?></td><td class="text-end"><?php echo $estimatedValue !== null ? '₱' . number_format((float) $estimatedValue, 2) : ($isPendingMatch ? '<span class="text-muted">Pending</span>' : '₱0.00'); ?></td><?php endif; ?></tr><?php endforeach; ?></tbody><tfoot><tr><th colspan="2">Estimated total weight</th><th class="text-end"><?php echo number_format($totalWeight, 2); ?> kg</th><?php if (!empty($assignment)): ?><th></th><th></th><?php endif; ?></tr></tfoot></table></div>
            <h5 class="fw-bold mb-3">Pickup information</h5><dl class="row mb-0"><dt class="col-sm-4 text-muted">Location name</dt><dd class="col-sm-8"><?php echo Validator::escape($request['pickup_location_name'] ?? ''); ?></dd><dt class="col-sm-4 text-muted">Address</dt><dd class="col-sm-8"><?php echo Validator::escape($request['pickup_address']); ?></dd><dt class="col-sm-4 text-muted">Barangay</dt><dd class="col-sm-8"><?php echo Validator::escape($request['barangay']); ?></dd><dt class="col-sm-4 text-muted">Approximate distance</dt><dd class="col-sm-8"><?php echo number_format((float)($request['approximate_distance_km'] ?? 0), 2); ?> km</dd><dt class="col-sm-4 text-muted">Preferred date</dt><dd class="col-sm-8"><?php echo Validator::escape($request['preferred_pickup_date']); ?></dd><dt class="col-sm-4 text-muted">Preferred time</dt><dd class="col-sm-8"><?php echo Validator::escape($request['preferred_pickup_time']); ?></dd><dt class="col-sm-4 text-muted">Notes</dt><dd class="col-sm-8"><?php echo $request['notes'] !== null && $request['notes'] !== '' ? nl2br(Validator::escape($request['notes'])) : '<span class="text-muted">None provided</span>'; ?></dd><dt class="col-sm-4 text-muted">Photo</dt><dd class="col-sm-8"><?php echo $request['photo_path'] ? 'Photo uploaded with request' : '<span class="text-muted">None provided</span>'; ?></dd></dl>
        </div></div>
    </div>
    <div class="col-lg-4"><div class="card border-0 shadow-sm"><div class="card-body p-4">
        <h5 class="fw-bold mb-4">Matched junkshop</h5>
        <?php if (!empty($assignment)): ?>
            <div class="bg-light rounded-3 p-3 mb-3">
                <div class="fw-semibold"><?php echo Validator::escape($assignment['business_name'] ?: 'Registered junkshop'); ?></div>
                <div class="small text-muted mt-1"><?php echo Validator::escape($assignment['junkshop_contact_name'] ?: 'Contact person'); ?></div>
                <div class="small text-muted mt-2"><?php echo Validator::escape($assignment['complete_address'] ?: 'Address pending'); ?></div>
                <div class="small text-muted mt-2">Approx. distance: <?php echo isset($assignment['distance_km']) ? number_format((float) $assignment['distance_km'], 2) . ' km' : 'Pending'; ?></div>
            </div>
        <?php else: ?>
            <div class="alert alert-secondary mb-0">No matched junkshop is assigned for this request yet.</div>
        <?php endif; ?>

        <h5 class="fw-bold mb-4">Status timeline</h5><ol class="status-timeline list-unstyled mb-0"><?php foreach ($request['status_history'] as $history): ?><li class="status-timeline-item"><span class="status-timeline-dot"></span><div><strong><?php echo Validator::escape($history['new_status'] ?? ''); ?></strong><div class="small text-muted"><?php echo Validator::escape(date('M d, Y g:i A', strtotime($history['changed_at'] ?? 'now'))); ?> · <?php echo Validator::escape($history['responsible_party'] ?? 'System'); ?></div><?php if (($history['new_status'] ?? '') === 'Scheduled' && !empty($request['confirmed_pickup_date']) && !empty($request['confirmed_pickup_time'])): ?><small class="text-muted d-block">Scheduled for: <?php echo Validator::escape($request['confirmed_pickup_date']); ?> at <?php echo Validator::escape($request['confirmed_pickup_time']); ?></small><?php endif; ?></div></li><?php endforeach; ?></ol>
    </div></div></div>
</div>
<script>
window.addEventListener('DOMContentLoaded', function () {
    const formatTime = (value) => {
        const date = new Date('1970-01-01T' + String(value || '').trim().slice(0, 8));
        return Number.isNaN(date.getTime()) ? value : date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    };

    document.querySelectorAll('dt').forEach((label) => {
        const value = label.nextElementSibling;
        if (label.textContent.trim() === 'Preferred time' && value) value.textContent = formatTime(value.textContent);
    });

    document.querySelectorAll('.status-timeline-item small').forEach((schedule) => {
        schedule.textContent = schedule.textContent.replace(/Scheduled for:\s*(\d{4}-\d{2}-\d{2})\s+at\s+(\d{1,2}:\d{2}:?\d{0,2})$/, (_, date, time) => 'Scheduled for: ' + new Date(date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' at ' + formatTime(time));
    });
});
</script>
<?php if ($canCancel): ?>
<script>
window.addEventListener('DOMContentLoaded', function () {
    document.getElementById('detail-cancel-request')?.addEventListener('click', async function () {
        if (!window.confirm('Cancel this booking? This action cannot be undone.')) return;
        const data = new FormData();
        data.append('_csrf_token', '<?php echo CSRF::token(); ?>');
        data.append('action', 'cancel');
        data.append('request_id', '<?php echo (int) $requestId; ?>');
        const response = await fetch('<?php echo APP_URL; ?>/user-junkshop/api/pickup-requests.php', { method: 'POST', body: data, credentials: 'same-origin' });
        const payload = await response.json();
        if (payload.success) window.location.reload();
        else window.alert(payload.message || 'The booking could not be cancelled.');
    });
});
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
