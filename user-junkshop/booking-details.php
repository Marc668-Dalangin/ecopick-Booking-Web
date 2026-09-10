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
$cancellableStatuses = ['Pending Request'];
$canCancel = in_array($request['current_status'], $cancellableStatuses, true);
$currentStatusIndex = array_search($request['current_status'], $statusOrder, true);
$currentStatusIndex = $currentStatusIndex === false ? 0 : $currentStatusIndex;
$confirmedPickupDate = (string) ($request['formatted_pickup_date'] ?? '');
$confirmedPickupTime = (string) ($request['formatted_pickup_time'] ?? '');
ob_start();
?>
<div class="row g-4">
    <div class="col-12"><a href="<?php echo APP_URL; ?>/user-junkshop/current-bookings.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Current bookings</a></div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm"><div class="card-body p-4 p-lg-5"><div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4"><div><p class="eyebrow mb-1">Booking tracking</p><h2 class="fw-bold mb-1"><?php echo Validator::escape($request['booking_reference']); ?></h2><p class="text-muted mb-0">Submitted <?php echo Validator::escape(date('M d, Y g:i A', strtotime($request['created_at']))); ?></p></div><span id="booking-current-status" class="status-badge <?php echo str_contains((string)$request['current_status'], 'Cancelled') ? 'rejected' : 'pending'; ?>"><?php echo Validator::escape($request['current_status']); ?></span></div>
            <div class="alert alert-info" role="note"><i class="bi bi-info-circle me-2"></i>EcoPick is facilitating this request. Registered junkshops handle collection, weighing, assessment, and purchase in later steps.</div>
            <?php if ($canCancel): ?><div class="d-flex justify-content-end mb-4"><button type="button" class="btn btn-sm btn-outline-danger" id="detail-cancel-request"><i class="bi bi-x-circle"></i> Cancel booking</button></div><?php endif; ?>

            <div class="mb-4">
                <h5 class="fw-bold mb-3">Progress</h5>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($statusOrder as $index => $status): ?>
                        <?php $isComplete = $index <= $currentStatusIndex; $isActive = $index === $currentStatusIndex; ?>
                        <div class="flex-fill min-w-120px">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span data-status-step="<?php echo Validator::escape($status); ?>" class="status-step rounded-circle d-inline-flex align-items-center justify-content-center <?php echo $isComplete ? 'bg-success text-white' : ($isActive ? 'bg-primary text-white' : 'bg-light text-muted'); ?>">
                                    <?php echo $isComplete ? '<i class="bi bi-check"></i>' : ($index + 1); ?>
                                </span>
                                <small data-status-label="<?php echo Validator::escape($status); ?>" class="fw-semibold <?php echo $isActive ? 'text-primary' : ($isComplete ? 'text-success' : 'text-muted'); ?>"><?php echo Validator::escape($status); ?></small>
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

        <h5 class="fw-bold mb-4">Status timeline</h5><ol class="status-timeline list-unstyled mb-0"><?php foreach ($request['status_history'] as $history): ?><li class="status-timeline-item"><span class="status-timeline-dot"></span><div><strong><?php echo Validator::escape($history['new_status'] ?? ''); ?></strong><div class="small text-muted"><?php echo Validator::escape(date('M d, Y g:i A', strtotime($history['changed_at'] ?? 'now'))); ?> · <?php echo Validator::escape($history['responsible_party'] ?? 'System'); ?></div><?php if (($history['new_status'] ?? '') === 'Accepted'): ?><small class="text-danger d-block mt-1" data-cancellation-warning>Cancellation is not allowed!</small><?php elseif (($history['new_status'] ?? '') === 'Scheduled' && $confirmedPickupDate !== '' && $confirmedPickupTime !== ''): ?><div class="text-muted small mt-1">Scheduled for: <?php echo Validator::escape($confirmedPickupDate); ?> at <?php echo Validator::escape($confirmedPickupTime); ?></div><?php endif; ?></div></li><?php endforeach; ?></ol>
    </div></div></div>
</div>
<script>
window.addEventListener('DOMContentLoaded', function () {
    let activeBookingInterval = null;
    let currentActiveBookingId = null;
    let pollingInProgress = false;
    const requestId = <?php echo (int) $requestId; ?>;
    const detailsUrl = '<?php echo APP_URL; ?>/user-junkshop/api/pickup-requests.php?action=details&request_id=' + requestId;
    const statusOrder = <?php echo json_encode($statusOrder, JSON_UNESCAPED_UNICODE); ?>;

    const formatTime = (value) => {
        const date = new Date('1970-01-01T' + String(value || '').trim().slice(0, 8));
        return Number.isNaN(date.getTime()) ? value : date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    };

    const formatDateTime = (value) => {
        const date = new Date(String(value || '').replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? String(value || '') : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' ' + date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    };

    function updateProgress(currentStatus) {
        const currentIndex = statusOrder.indexOf(currentStatus);
        document.querySelectorAll('[data-status-step]').forEach((step) => {
            const stepIndex = statusOrder.indexOf(step.dataset.statusStep);
            const complete = currentIndex >= 0 && stepIndex <= currentIndex;
            const active = stepIndex === currentIndex;
            step.classList.remove('bg-success', 'bg-primary', 'bg-light', 'text-white', 'text-muted');
            step.classList.add(complete ? 'bg-success' : active ? 'bg-primary' : 'bg-light', complete || active ? 'text-white' : 'text-muted');
            step.innerHTML = complete ? '<i class="bi bi-check"></i>' : String(stepIndex + 1);
        });
        document.querySelectorAll('[data-status-label]').forEach((label) => {
            const labelIndex = statusOrder.indexOf(label.dataset.statusLabel);
            const active = labelIndex === currentIndex;
            const complete = currentIndex >= 0 && labelIndex <= currentIndex;
            label.classList.remove('text-primary', 'text-success', 'text-muted');
            label.classList.add(active ? 'text-primary' : complete ? 'text-success' : 'text-muted');
        });
    }

    function updateTimeline(history, request) {
        const timeline = document.querySelector('.status-timeline');
        if (!timeline || !Array.isArray(history)) return;
        history.forEach((entry, index) => {
            let item = timeline.children[index];
            if (!item) {
                item = document.createElement('li');
                item.className = 'status-timeline-item';
                item.innerHTML = '<span class="status-timeline-dot"></span><div><strong></strong><div class="small text-muted"></div></div>';
                timeline.appendChild(item);
            }
            const status = String(entry.new_status || '');
            const text = item.querySelector('strong');
            const metadata = item.querySelector('.small.text-muted');
            if (text) text.textContent = status;
            if (metadata) metadata.textContent = formatDateTime(entry.changed_at) + ' · ' + String(entry.responsible_party || 'System');
            let warning = item.querySelector('[data-cancellation-warning]');
            if (status === 'Accepted' && !warning) {
                warning = document.createElement('small');
                warning.className = 'text-danger d-block mt-1';
                warning.dataset.cancellationWarning = 'true';
                warning.textContent = 'Cancellation is not allowed!';
                item.querySelector('div').appendChild(warning);
            }
            if (status !== 'Accepted' && warning) warning.remove();
            if (status === 'Scheduled') {
                let schedule = item.querySelector('#timeline-schedule-text, .mt-1');
                if (!schedule) {
                    schedule = document.createElement('div');
                    schedule.id = 'timeline-schedule-text';
                    schedule.className = 'text-muted small mt-1';
                    item.querySelector('div').appendChild(schedule);
                }
                const date = request.formatted_pickup_date || request.confirmed_pickup_date || '';
                const time = request.formatted_pickup_time || request.confirmed_pickup_time || '';
                schedule.textContent = date && time ? 'Scheduled for: ' + date + ' at ' + time : '';
            }
        });
    }

    function updateItemPrices(items) {
        if (!Array.isArray(items)) return;
        const rows = document.querySelectorAll('.table-responsive tbody tr');
        items.forEach((item, index) => {
            const row = rows[index];
            if (!row || row.cells.length < 5) return;
            const price = item.matched_price_per_kg;
            const value = item.actual_value ?? item.estimated_value;
            row.cells[3].textContent = price === null || price === undefined ? 'Pending' : '₱' + Number(price).toFixed(2) + '/kg';
            row.cells[4].textContent = value === null || value === undefined ? 'Pending' : '₱' + Number(value).toFixed(2);
        });
    }

    function applyBookingUpdate(request) {
        const status = document.getElementById('booking-current-status');
        if (status) {
            status.textContent = request.current_status || '';
            status.classList.toggle('rejected', request.current_status === 'Cancelled');
            status.classList.toggle('pending', request.current_status !== 'Cancelled');
        }
        updateProgress(request.current_status);
        updateTimeline(request.status_history, request);
        updateItemPrices(request.items);
    }

    function stopBookingPolling() {
        if (activeBookingInterval !== null) {
            clearInterval(activeBookingInterval);
            activeBookingInterval = null;
        }
        currentActiveBookingId = null;
    }

    async function pollActiveBooking() {
        if (!currentActiveBookingId || pollingInProgress || document.hidden) return;
        pollingInProgress = true;
        try {
            const response = await fetch(detailsUrl, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const payload = await response.json();
            if (payload.session_expired && payload.redirect) {
                window.location.href = payload.redirect;
                return;
            }
            if (response.ok && payload.success && payload.data?.request) applyBookingUpdate(payload.data.request);
        } catch (error) {
            console.error('Failed to refresh booking details:', error);
        } finally {
            pollingInProgress = false;
        }
    }

    function startBookingPolling(bookingId) {
        stopBookingPolling();
        currentActiveBookingId = Number(bookingId);
        pollActiveBooking();
        activeBookingInterval = setInterval(pollActiveBooking, 3000);
    }

    document.querySelectorAll('dt').forEach((label) => {
        const value = label.nextElementSibling;
        if (label.textContent.trim() === 'Preferred time' && value) value.textContent = formatTime(value.textContent);
    });

    document.getElementById('bookingDetailsModal')?.addEventListener('hidden.bs.modal', stopBookingPolling);
    window.addEventListener('pagehide', stopBookingPolling, { once: true });
    startBookingPolling(requestId);

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
