<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/PickupRequestController.php';
require_once __DIR__ . '/../app/controllers/DashboardController.php';

if (!Auth::check()) { header('Location: ' . APP_URL . '/user-junkshop/login.php'); exit; }
if (Auth::userRole() !== 'seller') { header('Location: ' . APP_URL . '/user-junkshop/dashboard.php'); exit; }

$pickupController = new PickupRequestController();
$dashboardController = new DashboardController();
$requests = $pickupController->listSellerRequests(Auth::userId());
$transactions = $dashboardController->getSellerTransactionHistory(Auth::userId());
$pageTitle = 'Current Bookings';
$currentPage = 'current-bookings';
$userDisplayName = Auth::userName();
$cancellableStatuses = ['Pending Request'];
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
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3"><div><div class="small text-muted">Booking reference</div><h5 class="fw-bold mb-1"><a href="<?php echo APP_URL; ?>/user-junkshop/booking-details.php?id=<?php echo (int) $request['id']; ?>"><?php echo Validator::escape($request['booking_reference']); ?></a></h5><div class="small text-muted"><?php echo Validator::escape($request['materials_summary']); ?></div></div><span class="status-badge <?php echo strtolower($request['current_status']) === 'cancelled' ? 'rejected' : 'pending'; ?>"><?php echo Validator::escape($request['current_status']); ?></span></div>
                    <div class="row g-3 mt-1 small"><div class="col-md-3"><div class="text-muted">Estimated total</div><strong><?php echo number_format((float) $request['estimated_total_weight'], 2); ?> kg</strong></div><div class="col-md-3"><div class="text-muted"><?php echo in_array($request['current_status'], ['Scheduled', 'For Pickup', 'Completed'], true) && !empty($request['confirmed_pickup_date']) ? 'Confirmed pickup' : 'Preferred pickup'; ?></div><strong><?php echo Validator::escape(in_array($request['current_status'], ['Scheduled', 'For Pickup', 'Completed'], true) && !empty($request['confirmed_pickup_date']) ? $request['confirmed_pickup_date'] : $request['preferred_pickup_date']); ?></strong><br><?php echo Validator::escape(in_array($request['current_status'], ['Scheduled', 'For Pickup', 'Completed'], true) && !empty($request['confirmed_pickup_time']) ? $request['confirmed_pickup_time'] : $request['preferred_pickup_time']); ?></div><div class="col-md-4"><div class="text-muted">Location</div><strong><?php echo Validator::escape($request['barangay']); ?></strong><br><?php echo Validator::escape($request['pickup_address']); ?></div><div class="col-md-2"><div class="text-muted">Created</div><strong><?php echo Validator::escape(date('M d, Y', strtotime($request['created_at']))); ?></strong></div></div>
                    <?php if (($request['current_status'] ?? '') === 'For Pickup'): ?><div class="small text-primary live-distance" data-booking-id="<?php echo (int) $request['id']; ?>">Junkshop location is being updated...</div><?php endif; ?><div class="d-flex gap-2 justify-content-end mt-3"><a class="btn btn-sm btn-outline-primary" href="<?php echo APP_URL; ?>/user-junkshop/booking-details.php?id=<?php echo (int) $request['id']; ?>"><i class="bi bi-eye"></i> View details</a><?php if (in_array($request['current_status'], $cancellableStatuses, true)): ?><button type="button" class="btn btn-sm btn-outline-danger cancel-request" data-request-id="<?php echo (int) $request['id']; ?>" data-reference="<?php echo Validator::escape($request['booking_reference']); ?>"><i class="bi bi-x-circle"></i> Cancel</button><?php endif; ?></div>
                </article>
            <?php endforeach; ?><?php endif; ?>
        </div>
        <?php if (!empty($transactions)): ?>
            <section class="mt-5" aria-labelledby="transaction-history-heading">
                <h3 id="transaction-history-heading" class="h5 fw-bold mb-3">Completed transactions and payment</h3>
                <?php foreach ($transactions as $transaction): ?>
                    <article class="border rounded-3 p-3 mb-3">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <strong><?php echo Validator::escape($transaction['booking_reference']); ?></strong>
                            <span class="badge text-bg-<?php echo ($transaction['payment_status'] ?? '') === 'Paid' ? 'success' : 'warning'; ?>"><?php echo Validator::escape($transaction['payment_status'] ?? 'Unpaid'); ?></span>
                        </div>
                        <div class="small text-muted mt-2">Final seller amount: <strong>₱<?php echo number_format((float)$transaction['final_seller_amount'], 2); ?></strong> · Method: <?php echo Validator::escape($transaction['payment_method'] ?? 'Pending'); ?></div>
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
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
<div class="modal fade" id="cancelRequestModal" tabindex="-1" aria-labelledby="cancelRequestModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="cancelRequestModalLabel">Cancel pickup request?</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="mb-0" id="cancel-request-message">This request will be marked Cancelled.</p></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep request</button><button type="button" class="btn btn-danger" id="confirm-cancel-request">Cancel request</button></div></div></div></div>
<script>
window.addEventListener('DOMContentLoaded', function () {
    const list = document.getElementById('current-bookings-list');
    const feedback = document.getElementById('bookings-feedback');
    const apiUrl = '<?php echo APP_URL; ?>/user-junkshop/api/pickup-requests.php';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '<?php echo CSRF::token(); ?>';
    const modalElement = document.getElementById('cancelRequestModal');
    const modal = modalElement ? new bootstrap.Modal(modalElement) : null;
    let pendingId = null;
    let lastPayload = null;
    function setupLiveDistancePolling() {
        document.querySelectorAll('.live-distance').forEach(function (node) {
            const poll = function () { fetch('<?php echo APP_URL; ?>/user-junkshop/api/get_live_distance.php?booking_id=' + encodeURIComponent(node.dataset.bookingId), { credentials: 'same-origin' }).then(response => response.json()).then(payload => { if (payload.distance_km !== null && payload.distance_km !== undefined) node.textContent = 'Junkshop is currently ' + Number(payload.distance_km).toFixed(2) + ' km away'; }).catch(function () {}); };
            poll();
            window.setInterval(poll, 5000);
        });
    }

    function escapeHtml(value) { return String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character])); }
    function formatDate(value) { const date = new Date(String(value || '').slice(0, 10) + 'T00:00:00'); return Number.isNaN(date.getTime()) ? String(value || '') : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }); }
    function formatTime(value) { const date = new Date('1970-01-01T' + String(value || '').slice(0, 8)); return Number.isNaN(date.getTime()) ? String(value || '') : date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }); }
    function formatBookingDatesAndTimes() { document.querySelectorAll('.booking-row .col-md-3').forEach((column) => { const dateNode = column.querySelector('strong'); const lineBreak = column.querySelector('br'); const timeNode = lineBreak?.nextSibling; if (dateNode) dateNode.textContent = formatDate(dateNode.textContent); if (timeNode && timeNode.nodeType === Node.TEXT_NODE) timeNode.nodeValue = formatTime(timeNode.nodeValue); }); }
    function showFeedback(message, success) { feedback.className = 'alert ' + (success ? 'alert-success' : 'alert-danger'); feedback.textContent = message; feedback.classList.remove('d-none'); }
    function renderRequests(requests) {
        if (!Array.isArray(requests)) return;
        if (!requests.length) { list.innerHTML = '<div class="empty-state"><div class="display-6 text-muted"><i class="bi bi-calendar2-x"></i></div><h5 class="mt-3 mb-2 fw-bold">No pickup requests yet</h5><p class="text-muted mb-3">Your submitted pickup requests will appear here.</p></div>'; return; }
        list.innerHTML = requests.map(request => { const cancelled = request.current_status === 'Cancelled'; const cancellable = request.current_status === 'Pending Request'; const liveDistance = request.current_status === 'For Pickup' ? '<div class="small text-primary live-distance" data-booking-id="' + Number(request.id) + '">Junkshop location is being updated...</div>' : ''; return '<article class="booking-row border rounded-3 p-3 p-lg-4 mb-3" data-request-id="' + Number(request.id) + '"><div class="d-flex justify-content-between align-items-start flex-wrap gap-3"><div><div class="small text-muted">Booking reference</div><h5 class="fw-bold mb-1"><a href="<?php echo APP_URL; ?>/user-junkshop/booking-details.php?id=' + Number(request.id) + '">' + escapeHtml(request.booking_reference) + '</a></h5><div class="small text-muted">' + escapeHtml(request.materials_summary) + '</div></div><span class="status-badge ' + (cancelled ? 'rejected' : 'pending') + '">' + escapeHtml(request.current_status) + '</span></div><div class="row g-3 mt-1 small"><div class="col-md-3"><div class="text-muted">Estimated total</div><strong>' + Number(request.estimated_total_weight || 0).toFixed(2) + ' kg</strong></div><div class="col-md-3"><div class="text-muted">Preferred pickup</div><strong>' + escapeHtml(request.preferred_pickup_date) + '</strong><br>' + escapeHtml(request.preferred_pickup_time) + '</div><div class="col-md-4"><div class="text-muted">Location</div><strong>' + escapeHtml(request.barangay) + '</strong><br>' + escapeHtml(request.pickup_address) + '</div><div class="col-md-2"><div class="text-muted">Created</div><strong>' + escapeHtml(formatDate(request.created_at)) + '</strong></div></div>' + liveDistance + '<div class="d-flex gap-2 justify-content-end mt-3"><a class="btn btn-sm btn-outline-primary" href="<?php echo APP_URL; ?>/user-junkshop/booking-details.php?id=' + Number(request.id) + '"><i class="bi bi-eye"></i> View details</a>' + (cancellable ? '<button type="button" class="btn btn-sm btn-outline-danger cancel-request" data-request-id="' + Number(request.id) + '" data-reference="' + escapeHtml(request.booking_reference) + '"><i class="bi bi-x-circle"></i> Cancel</button>' : '') + '</div></article>'; }).join('');
        formatBookingDatesAndTimes();
        bindCancelButtons();
    }
    function bindCancelButtons() { document.querySelectorAll('.cancel-request').forEach(button => button.addEventListener('click', function () { pendingId = Number(button.dataset.requestId); document.getElementById('cancel-request-message').textContent = 'Cancel ' + button.dataset.reference + '? This action cannot be undone.'; modal?.show(); })); }
    formatBookingDatesAndTimes();
    setupLiveDistancePolling();
    async function loadRequests() { if (document.hidden || document.activeElement?.matches('input, textarea, select')) return; try { const response = await fetch(apiUrl, { credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'} }); const payload = await response.json(); if (payload.session_expired && payload.redirect) { window.location.href = payload.redirect; return; } if (payload.success) { lastPayload = payload; renderRequests(payload.data.requests); setupLiveDistancePolling(); } } catch (error) { console.error('Failed to refresh pickup requests:', error); } }
    document.getElementById('confirm-cancel-request').addEventListener('click', async function () { if (!pendingId) return; const data = new FormData(); data.append('_csrf_token', csrf); data.append('action', 'cancel'); data.append('request_id', String(pendingId)); try { const response = await fetch(apiUrl, { method: 'POST', body: data, credentials: 'same-origin' }); const payload = await response.json(); if (payload.success) { renderRequests(payload.data.requests); showFeedback(payload.message, true); } else { showFeedback(payload.message || 'The request could not be cancelled.', false); } } catch (error) { showFeedback('Unable to cancel the request right now.', false); } finally { pendingId = null; modal?.hide(); } });
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
    if (window.EcoPickLiveUpdates) window.EcoPickLiveUpdates.startPolling({ key: 'seller-current-bookings', url: apiUrl, interval: 5000, onSuccess: payload => { if (payload.success) renderRequests(payload.data.requests); } });
});
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
