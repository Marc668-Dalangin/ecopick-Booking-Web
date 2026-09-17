<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/PickupRequestController.php';
require_once __DIR__ . '/../app/controllers/DashboardController.php';

if (!Auth::check()) { header('Location: ' . APP_URL . '/user-junkshop/login.php'); exit; }
if (Auth::userRole() !== 'seller') { header('Location: ' . APP_URL . '/user-junkshop/dashboard.php'); exit; }

$pickupController = new PickupRequestController();
$dashboardController = new DashboardController();
$requests = $pickupController->listSellerRequests(Auth::userId());
$sortOrder = strtoupper((string) ($_GET['sort_order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$transactions = $dashboardController->getSellerTransactionHistory(Auth::userId(), $sortOrder);
$pageTitle = 'Current Bookings';
$currentPage = 'current-bookings';
$userDisplayName = Auth::userName();
$cancellableStatuses = ['Matched', 'Accepted'];
ob_start();
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4 p-lg-5">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
            <div><p class="eyebrow mb-1">Seller booking</p><h2 class="fw-bold mb-2">Current Bookings</h2><p class="text-muted mb-0">Track requests you submitted. Each request stays with you until the next review step.</p></div>
        </div>
        <div class="alert alert-info" role="note"><i class="bi bi-info-circle me-2"></i>EcoPick is the facilitator. Registered junkshops handle collection, weighing, assessment, and purchase after a later matching step.</div>
        <div id="bookings-feedback" class="alert d-none" role="status" aria-live="polite"></div>
        <div id="current-bookings-list" aria-live="polite">
            <?php if (empty($requests)): ?><div class="empty-state"><div class="display-6 text-muted"><i class="bi bi-calendar2-x"></i></div><h5 class="mt-3 mb-2 fw-bold">No pickup requests yet</h5><p class="text-muted mb-3">Your submitted pickup requests will appear here.</p></div>
            <?php else: ?><?php foreach ($requests as $request): ?>
                <article class="booking-row border rounded-3 p-3 p-lg-4 mb-3" data-request-id="<?php echo (int) $request['id']; ?>">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3"><div><div class="small text-muted">Booking reference</div><h5 class="fw-bold mb-1"><a href="<?php echo APP_URL; ?>/user-junkshop/booking-details.php?id=<?php echo (int) $request['id']; ?>"><?php echo Validator::escape($request['booking_reference']); ?></a></h5><div class="small text-muted"><?php echo Validator::escape($request['materials_summary']); ?></div><div class="small mt-2"><span class="text-muted">Junkshop:</span> <strong><?php echo Validator::escape($request['junkshop_name'] ?? 'Junkshop'); ?></strong></div></div><span class="status-badge <?php echo strtolower($request['current_status']) === 'cancelled' ? 'rejected' : 'pending'; ?>"><?php echo Validator::escape($request['current_status']); ?></span></div>
                    <div class="row g-3 mt-1 small"><div class="col-6 col-lg-3"><div class="text-muted">Estimated total</div><strong><?php echo number_format((float) $request['estimated_total_weight'], 2); ?> kg</strong></div><div class="col-6 col-lg-3"><div class="text-muted">Actual Weight</div><?php if (($request['status'] ?? $request['current_status']) === 'Completed' && !empty($request['actual_weight'])): ?><span class="badge bg-success font-monospace fs-6"><?php echo number_format((float) $request['actual_weight'], 2); ?> kg</span><?php elseif (($request['status'] ?? $request['current_status']) === 'Completed'): ?><span class="badge bg-secondary">Not Specified</span><?php else: ?><span class="badge bg-light text-dark">Awaiting Completion</span><?php endif; ?></div><div class="col-6 col-lg-3"><div class="text-muted"><?php echo in_array($request['current_status'], ['Scheduled', 'For Pickup', 'Completed'], true) && !empty($request['confirmed_pickup_date']) ? 'Confirmed pickup' : 'Preferred pickup'; ?></div><strong><?php echo Validator::escape(in_array($request['current_status'], ['Scheduled', 'For Pickup', 'Completed'], true) && !empty($request['confirmed_pickup_date']) ? $request['confirmed_pickup_date'] : $request['preferred_pickup_date']); ?></strong><br><?php echo Validator::escape(in_array($request['current_status'], ['Scheduled', 'For Pickup', 'Completed'], true) && !empty($request['confirmed_pickup_time']) ? $request['confirmed_pickup_time'] : $request['preferred_pickup_time']); ?></div><div class="col-6 col-lg-3"><div class="text-muted">Created</div><strong><?php echo Validator::escape(date('M d, Y', strtotime($request['created_at']))); ?></strong></div><div class="col-12"><div class="text-muted">Pickup address</div><strong><?php echo Validator::escape($request['pickup_address']); ?></strong></div></div>
                    <?php if (($request['current_status'] ?? '') === 'For Pickup'): ?><div class="small text-primary live-distance" data-booking-id="<?php echo (int) $request['id']; ?>">Junkshop location is being updated...</div><?php endif; ?><div class="d-flex gap-2 justify-content-end mt-3"><button type="button" class="btn btn-sm btn-outline-primary view-booking-details" data-bs-toggle="modal" data-bs-target="#bookingDetailsModal" data-booking-reference="<?php echo Validator::escape($request['booking_reference']); ?>" data-booking-status="<?php echo Validator::escape($request['current_status']); ?>" data-actual-weight="<?php echo ($request['status'] ?? $request['current_status']) === 'Completed' && !empty($request['actual_weight']) ? number_format((float) $request['actual_weight'], 2) . ' kg' : ''; ?>" data-details-url="<?php echo APP_URL; ?>/user-junkshop/booking-details.php?id=<?php echo (int) $request['id']; ?>"><i class="bi bi-eye"></i> View details</button><?php if (in_array($request['current_status'], $cancellableStatuses, true)): ?><button type="button" class="btn btn-sm btn-outline-danger cancel-request" data-request-id="<?php echo (int) $request['id']; ?>" data-reference="<?php echo Validator::escape($request['booking_reference']); ?>"><i class="bi bi-x-circle"></i> Cancel</button><?php endif; ?></div>
                </article>
            <?php endforeach; ?><?php endif; ?>
        </div>
        <?php if (!empty($transactions)): ?>
            <section class="mt-5" aria-labelledby="transaction-history-heading">
                <h3 id="transaction-history-heading" class="h5 fw-bold mb-3">Completed transactions and payment</h3>
                <form method="GET" class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                    <?php foreach ($_GET as $key => $val): ?>
                        <?php if ($key !== 'sort_order' && is_scalar($val)): ?><input type="hidden" name="<?php echo Validator::escape($key); ?>" value="<?php echo Validator::escape((string) $val); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <label for="sort_order" class="form-label mb-0 fw-bold text-nowrap"><i class="bi bi-sort-numeric-down me-1"></i>Sort by Date:</label>
                    <select name="sort_order" id="sort_order" class="form-select form-select-sm auto-submit" onchange="this.form.submit()">
                        <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Newest First (Descending)</option>
                        <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Oldest First (Ascending)</option>
                    </select>
                </form>
                <?php foreach ($transactions as $transaction): ?>
                    <article class="card mb-3 border-0 shadow-sm">
                        <div class="card-header bg-white d-flex align-items-center justify-content-between gap-3 p-3 flex-wrap" role="button" data-bs-toggle="collapse" data-bs-target="#collapseCurrentTxn<?php echo (int) $transaction['id']; ?>" aria-controls="collapseCurrentTxn<?php echo (int) $transaction['id']; ?>">
                            <div class="d-flex align-items-center gap-3 flex-wrap"><span class="fw-bold font-monospace">#<?php echo (int) $transaction['id']; ?></span><span class="fw-semibold"><?php echo Validator::escape($transaction['booking_reference']); ?></span><span class="text-muted small"><?php echo Validator::escape(date('M d, Y g:i A', strtotime($transaction['completed_at']))); ?></span><span class="badge text-bg-<?php echo ($transaction['payment_status'] ?? '') === 'Paid' ? 'success' : 'warning'; ?>"><?php echo Validator::escape($transaction['payment_status'] ?? 'Unpaid'); ?></span></div>
                            <div class="d-flex align-items-center gap-2"><span class="fw-bold text-success">₱<?php echo number_format((float) $transaction['final_seller_amount'], 2); ?></span><button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCurrentTxn<?php echo (int) $transaction['id']; ?>" aria-expanded="false" aria-controls="collapseCurrentTxn<?php echo (int) $transaction['id']; ?>" onclick="event.stopPropagation();"><i class="bi bi-chevron-down me-1"></i>Details</button></div>
                        </div>
                        <div class="collapse" id="collapseCurrentTxn<?php echo (int) $transaction['id']; ?>">
                            <div class="card-body bg-light">
                                <div class="row g-3 small"><div class="col-md-4"><div class="text-muted fw-bold">Junkshop</div><div><?php echo Validator::escape($transaction['junkshop_name'] ?? 'Junkshop'); ?></div></div><div class="col-md-4"><div class="text-muted fw-bold">Completed</div><div><?php echo Validator::escape(date('M d, Y g:i A', strtotime($transaction['completed_at']))); ?></div></div><div class="col-md-4"><div class="text-muted fw-bold">Payment method</div><div><?php echo Validator::escape($transaction['payment_method'] ?? 'Pending'); ?></div></div></div>
                                <div class="small text-muted mt-3">Final seller amount: <strong>₱<?php echo number_format((float)$transaction['final_seller_amount'], 2); ?></strong></div>
                                <?php if (($transaction['payment_method'] ?? '') === 'GCash' && ($transaction['payment_status'] ?? '') === 'Unpaid'): ?>
                                    <form class="gcash-proof-form mt-3" data-transaction-id="<?php echo (int)$transaction['id']; ?>" enctype="multipart/form-data">
                                        <label class="form-label" for="payment-proof-<?php echo (int)$transaction['id']; ?>">Upload GCash payment screenshot</label>
                                        <div class="input-group"><input class="form-control payment-proof-input" id="payment-proof-<?php echo (int)$transaction['id']; ?>" type="file" name="payment_proof" accept="image/jpeg,image/png,.jpg,.jpeg,.png" required><button class="btn btn-outline-primary" type="submit">Submit proof</button></div>
                                        <div class="form-text">JPEG or PNG only, maximum 3 MB.</div>
                                        <div class="small text-danger proof-error mt-1" role="alert"></div>
                                    </form>
                                <?php elseif (!empty($transaction['payment_proof_id'])): ?>
                                    <a class="btn btn-sm btn-outline-secondary mt-3" href="<?php echo APP_URL; ?>/user-junkshop/api/payment.php?action=view-proof&amp;proof_id=<?php echo (int)$transaction['payment_proof_id']; ?>" target="_blank" rel="noopener">View submitted proof</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
<div class="modal fade" id="cancelBookingConfirmModal" tabindex="-1" aria-labelledby="cancelBookingConfirmModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="cancelBookingConfirmModalLabel">Confirm Cancellation</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="mb-0" id="cancel-request-message">Are you sure you want to cancel this pickup booking? This action cannot be undone.</p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Booking</button><button type="button" class="btn btn-danger" id="btn-confirm-cancel">Yes, Cancel Booking</button></div></div></div></div>
<div class="modal fade" id="bookingDetailsModal" tabindex="-1" aria-labelledby="bookingDetailsModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="bookingDetailsModalLabel">Booking details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-sm-5 text-muted">Booking reference</dt><dd class="col-sm-7" id="modal-booking-reference"></dd><dt class="col-sm-5 text-muted">Status</dt><dd class="col-sm-7" id="modal-booking-status"></dd><dt class="col-sm-5 text-muted">Actual Weight</dt><dd class="col-sm-7" id="modal-actual-weight"></dd></dl></div><div class="modal-footer"><a class="btn btn-primary" id="modal-full-details-link" href="#"><i class="bi bi-box-arrow-up-right"></i> Full details</a><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
<script>
window.addEventListener('DOMContentLoaded', function () {
    const list = document.getElementById('current-bookings-list');
    const feedback = document.getElementById('bookings-feedback');
    const apiUrl = '<?php echo APP_URL; ?>/user-junkshop/api/pickup-requests.php';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '<?php echo CSRF::token(); ?>';
    const modalElement = document.getElementById('cancelBookingConfirmModal');
    const modal = modalElement ? new bootstrap.Modal(modalElement) : null;
    let pendingId = null;
    let lastPayload = null;
    const liveDistancePollers = new Map();
    function setupLiveDistancePolling() {
        liveDistancePollers.forEach(function (timerId, bookingId) {
            if (!document.querySelector('.live-distance[data-booking-id="' + bookingId + '"]')) {
                window.clearInterval(timerId);
                liveDistancePollers.delete(bookingId);
            }
        });
        document.querySelectorAll('.live-distance').forEach(function (node) {
            const bookingId = String(node.dataset.bookingId);
            if (liveDistancePollers.has(bookingId)) return;
            const poll = function () { fetch('<?php echo APP_URL; ?>/user-junkshop/api/get_live_distance.php?booking_id=' + encodeURIComponent(node.dataset.bookingId), { credentials: 'same-origin' }).then(response => response.json()).then(payload => { if (payload.distance_km !== null && payload.distance_km !== undefined) node.textContent = 'Junkshop is currently ' + Number(payload.distance_km).toFixed(2) + ' km away'; }).catch(function () {}); };
            poll();
            liveDistancePollers.set(bookingId, window.setInterval(poll, 10000));
        });
    }

    function escapeHtml(value) { return String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character])); }
    function formatDate(value) { const date = new Date(String(value || '').slice(0, 10) + 'T00:00:00'); return Number.isNaN(date.getTime()) ? String(value || '') : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
    function formatTime(value) { const date = new Date('1970-01-01T' + String(value || '').slice(0, 8)); return Number.isNaN(date.getTime()) ? String(value || '') : date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }); }
    function formatBookingDatesAndTimes() { document.querySelectorAll('.booking-row .col-md-3').forEach((column) => { const dateNode = column.querySelector('strong'); const lineBreak = column.querySelector('br'); const timeNode = lineBreak?.nextSibling; if (dateNode) dateNode.textContent = formatDate(dateNode.textContent); if (timeNode && timeNode.nodeType === Node.TEXT_NODE) timeNode.nodeValue = formatTime(timeNode.nodeValue); }); }
    function showFeedback(message, success) { feedback.className = 'alert ' + (success ? 'alert-success' : 'alert-danger'); feedback.textContent = message; feedback.classList.remove('d-none'); }
    function renderJunkshopNames(requests) { (requests || []).forEach(function (request) { const row = list.querySelector('[data-request-id="' + Number(request.id) + '"]'); const summary = row?.querySelector('.small.text-muted'); if (row && summary && !row.querySelector('.junkshop-name')) { const label = document.createElement('div'); label.className = 'small mt-2 junkshop-name'; label.innerHTML = '<span class="text-muted">Junkshop:</span> <strong>' + escapeHtml(request.junkshop_name || 'Junkshop') + '</strong>'; summary.after(label); } }); }
    function actualWeightMarkup(request) { const status = request.status || request.current_status; if (status === 'Completed' && Number(request.actual_weight || 0) > 0) return '<span class="badge bg-success font-monospace fs-6">' + Number(request.actual_weight).toFixed(2) + ' kg</span>'; if (status === 'Completed') return '<span class="badge bg-secondary">Not Specified</span>'; return '<span class="badge bg-light text-dark">Awaiting Completion</span>'; }
    function decorateRenderedRequests(requests) { (requests || []).forEach(function (request) { const row = list.querySelector('[data-request-id="' + Number(request.id) + '"]'); const details = row?.querySelector('.d-flex.gap-2.justify-content-end'); if (!row || !details) return; const detailGrid = row.querySelector('.row.g-3'); if (detailGrid && !detailGrid.querySelector('.actual-weight-value')) { const weight = document.createElement('div'); weight.className = 'col-6 col-lg-3 actual-weight-value'; weight.innerHTML = '<div class="text-muted">Actual Weight</div>' + actualWeightMarkup(request); detailGrid.insertBefore(weight, detailGrid.children[1]); } const link = details.querySelector('a.btn-outline-primary'); if (link && !details.querySelector('.view-booking-details')) { const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-sm btn-outline-primary view-booking-details'; button.dataset.bsToggle = 'modal'; button.dataset.bsTarget = '#bookingDetailsModal'; button.dataset.bookingReference = request.booking_reference || ''; button.dataset.bookingStatus = request.current_status || ''; button.dataset.actualWeight = request.current_status === 'Completed' && Number(request.actual_weight || 0) > 0 ? Number(request.actual_weight).toFixed(2) + ' kg' : ''; button.dataset.detailsUrl = link.href; button.innerHTML = '<i class="bi bi-eye"></i> View details'; link.replaceWith(button); } }); }
    function renderRequests(requests) {
        if (!Array.isArray(requests)) return;
        if (!requests.length) { list.innerHTML = '<div class="empty-state"><div class="display-6 text-muted"><i class="bi bi-calendar2-x"></i></div><h5 class="mt-3 mb-2 fw-bold">No pickup requests yet</h5><p class="text-muted mb-3">Your submitted pickup requests will appear here.</p></div>'; return; }
        list.innerHTML = requests.map(request => { const cancelled = request.current_status === 'Cancelled'; const cancellable = ['Matched', 'Accepted'].includes(request.current_status); const liveDistance = request.current_status === 'For Pickup' ? '<div class="small text-primary live-distance" data-booking-id="' + Number(request.id) + '">Junkshop location is being updated...</div>' : ''; return '<article class="booking-row border rounded-3 p-3 p-lg-4 mb-3" data-request-id="' + Number(request.id) + '"><div class="d-flex justify-content-between align-items-start flex-wrap gap-3"><div><div class="small text-muted">Booking reference</div><h5 class="fw-bold mb-1"><a href="<?php echo APP_URL; ?>/user-junkshop/booking-details.php?id=' + Number(request.id) + '">' + escapeHtml(request.booking_reference) + '</a></h5><div class="small text-muted">' + escapeHtml(request.materials_summary) + '</div></div><span class="status-badge ' + (cancelled ? 'rejected' : 'pending') + '">' + escapeHtml(request.current_status) + '</span></div><div class="row g-3 mt-1 small"><div class="col-md-3"><div class="text-muted">Estimated total</div><strong>' + Number(request.estimated_total_weight || 0).toFixed(2) + ' kg</strong></div><div class="col-md-3"><div class="text-muted">Preferred pickup</div><strong>' + escapeHtml(request.preferred_pickup_date) + '</strong><br>' + escapeHtml(request.preferred_pickup_time) + '</div><div class="col-md-4"><div class="text-muted">Pickup address</div><strong>' + escapeHtml(request.pickup_address) + '</strong></div><div class="col-md-2"><div class="text-muted">Created</div><strong>' + escapeHtml(formatDate(request.created_at)) + '</strong></div></div>' + liveDistance + '<div class="d-flex gap-2 justify-content-end mt-3"><a class="btn btn-sm btn-outline-primary" href="<?php echo APP_URL; ?>/user-junkshop/booking-details.php?id=' + Number(request.id) + '"><i class="bi bi-eye"></i> View details</a>' + (cancellable ? '<button type="button" class="btn btn-sm btn-outline-danger cancel-request" data-request-id="' + Number(request.id) + '" data-reference="' + escapeHtml(request.booking_reference) + '"><i class="bi bi-x-circle"></i> Cancel</button>' : '') + '</div></article>'; }).join('');
        formatBookingDatesAndTimes();
        bindCancelButtons();
    }
    function bindCancelButtons() { document.querySelectorAll('.cancel-request').forEach(button => button.addEventListener('click', function () { pendingId = Number(button.dataset.requestId); document.getElementById('cancel-request-message').textContent = 'Cancel ' + button.dataset.reference + '? This action cannot be undone.'; modal?.show(); })); }
    list.addEventListener('click', function (event) { const button = event.target.closest('.view-booking-details'); if (!button) return; document.getElementById('modal-booking-reference').textContent = button.dataset.bookingReference || ''; document.getElementById('modal-booking-status').textContent = button.dataset.bookingStatus || ''; document.getElementById('modal-actual-weight').innerHTML = button.dataset.actualWeight ? '<span class="badge bg-success font-monospace fs-6">' + escapeHtml(button.dataset.actualWeight) + '</span>' : button.dataset.bookingStatus === 'Completed' ? '<span class="badge bg-secondary">Not Specified</span>' : '<span class="badge bg-light text-dark">Awaiting Completion</span>'; document.getElementById('modal-full-details-link').href = button.dataset.detailsUrl || '#'; });
    formatBookingDatesAndTimes();
    decorateRenderedRequests(<?php echo json_encode($requests, JSON_UNESCAPED_UNICODE); ?>);
    setupLiveDistancePolling();
    async function loadRequests() { if (document.hidden || document.activeElement?.matches('input, textarea, select')) return; try { const response = await fetch(apiUrl, { credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'} }); const payload = await response.json(); if (payload.session_expired && payload.redirect) { window.location.href = payload.redirect; return; } if (payload.success) { lastPayload = payload; renderRequests(payload.data.requests); decorateRenderedRequests(payload.data.requests); setupLiveDistancePolling(); } } catch (error) { console.error('Failed to refresh pickup requests:', error); } }
    document.getElementById('btn-confirm-cancel').addEventListener('click', async function () { if (!pendingId) return; const data = new FormData(); data.append('_csrf_token', csrf); data.append('action', 'cancel'); data.append('request_id', String(pendingId)); try { const response = await fetch(apiUrl, { method: 'POST', body: data, credentials: 'same-origin' }); const payload = await response.json(); if (payload.success) { renderRequests(payload.data.requests); showFeedback(payload.message, true); } else { showFeedback(payload.message || 'The request could not be cancelled.', false); } } catch (error) { showFeedback('Unable to cancel the request right now.', false); } finally { pendingId = null; modal?.hide(); } });
    bindCancelButtons();
    document.querySelectorAll('.gcash-proof-form').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const input = form.querySelector('.payment-proof-input');
            const error = form.querySelector('.proof-error');
            const file = input?.files?.[0];
            error.textContent = '';
            if (!file || !['image/jpeg', 'image/png'].includes(file.type)) { error.textContent = 'Choose a valid JPEG or PNG image.'; return; }
            if (file.size > 3145728) { error.textContent = 'Payment proof must be 3 MB or smaller.'; return; }
            const data = new FormData(form);
            data.append('_csrf_token', csrf);
            data.append('action', 'upload-proof');
            data.append('transaction_id', form.dataset.transactionId);
            try {
                const response = await fetch('<?php echo APP_URL; ?>/user-junkshop/api/payment.php', { method: 'POST', body: data, credentials: 'same-origin' });
                const payload = await response.json();
                if (!response.ok || !payload.success) { error.textContent = payload.message || 'The proof could not be submitted.'; return; }
                showFeedback(payload.message, true);
                form.innerHTML = '<div class="alert alert-success mb-0">Payment proof submitted for junkshop review.</div>';
            } catch (requestError) { error.textContent = 'Unable to submit payment proof right now.'; }
        });
    });
    if (window.EcoPickLiveUpdates) window.EcoPickLiveUpdates.startPolling({ key: 'seller-current-bookings', url: apiUrl, interval: 5000, onSuccess: payload => { if (payload.success) { renderRequests(payload.data.requests); decorateRenderedRequests(payload.data.requests); renderJunkshopNames(payload.data.requests); } } });
});
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
