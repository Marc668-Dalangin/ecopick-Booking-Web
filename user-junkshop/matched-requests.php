<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/DashboardController.php';

if (!Auth::check()) {
    header('Location: ' . APP_URL . '/user-junkshop/login.php');
    exit;
}

if (Auth::userRole() !== 'junkshop') {
    header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
    exit;
}

if (!empty($_SESSION['is_expired'])) {
    $pageTitle = 'Matched Requests';
    $currentPage = 'matched-requests';
    $userDisplayName = Auth::userName();
    ob_start();
    ?>
    <div class="card border-0 shadow-sm"><div class="card-body p-4 p-lg-5 text-center">
        <i class="bi bi-lock-fill display-5 text-warning"></i>
        <h2 class="fw-bold mt-3">Matched Requests Locked</h2>
        <p class="text-muted mb-0">These features are locked because your partnership subscription has expired. Pay the renewal fee to reactivate them.</p>
    </div></div>
    <?php
    $content = ob_get_clean();
    require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
    exit;
}

$dashboardController = new DashboardController();
$assignments = $dashboardController->getPendingJunkshopRequests(Auth::userId());
$pageTitle = 'Matched Requests';
$currentPage = 'matched-requests';
$userDisplayName = Auth::userName();
$formatPickupDate = static function ($date): string {
    $timestamp = strtotime((string) $date);
    return $timestamp === false ? '' : date('M d, Y', $timestamp);
};
$formatPickupTime = static function ($time): string {
    $timestamp = strtotime((string) $time);
    return $timestamp === false ? '' : date('h:i A', $timestamp);
};
$activeTrackingAssignment = null;
foreach ($assignments as $assignment) {
    if (($assignment['current_status'] ?? '') === 'For Pickup') {
        $activeTrackingAssignment = $assignment;
        break;
    }
}
ob_start();
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4 p-lg-5">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
            <div>
                <p class="eyebrow mb-1">Junkshop operations</p>
                <h2 class="fw-bold mb-1">Matched Requests</h2>
                <p class="text-muted mb-0">Review assigned pickup requests, accept or decline them, schedule collection, and complete final settlements.</p>
            </div>
            <a href="<?php echo APP_URL; ?>/user-junkshop/dashboard.php" class="btn btn-outline-secondary">Back to dashboard</a>
        </div>

        <div id="assignment-feedback" class="alert d-none" role="status" aria-live="polite"></div>

        <div id="live-tracking-container" style="display: <?php echo $activeTrackingAssignment !== null ? 'block' : 'none'; ?>;" data-booking-id="<?php echo (int) ($activeTrackingAssignment['pickup_request_id'] ?? 0); ?>">
            <div class="tracking-info">
                <p><strong class="text-danger">Seller location:</strong> <span id="seller-address-text"></span></p>
                <p><strong class="text-danger">Your Current Location:</strong> <span id="junkshop-address-text">Fetching...</span></p>
                <p><strong class="text-danger">Exact distance:</strong> <span id="live-distance">Calculating...</span></p>
                <div id="location-error-note" class="alert alert-warning d-none mt-2" role="alert"></div>
            </div>
        </div>

        <div class="row g-4" id="assignment-list">
        <?php if (empty($assignments)): ?>
            <div class="empty-state" data-empty-assignments>
                <div class="display-6 text-muted"><i class="bi bi-inbox"></i></div>
                <h5 class="mt-3 mb-2 fw-bold">No matched requests yet</h5>
                <p class="text-muted mb-0">New pickup requests will appear here as soon as they are matched to your junkshop.</p>
            </div>
        <?php else: ?>
                <?php foreach ($assignments as $assignment): ?>
                    <?php $currentStatus = (string)($assignment['current_status'] ?? 'Matched'); ?>
                    <?php $isCancelled = in_array($currentStatus, ['Cancelled', 'Cancelled by Seller'], true); ?>
                    <?php $statusClass = strtolower((string)($assignment['assignment_status'] ?? 'Matched')); ?>
                    <?php $pickupDate = $formatPickupDate($assignment['confirmed_pickup_date'] ?? $assignment['preferred_pickup_date'] ?? ''); ?>
                    <?php $pickupTime = $formatPickupTime($assignment['confirmed_pickup_time'] ?? $assignment['preferred_pickup_time'] ?? ''); ?>
                    <?php $terminalTimestamp = $isCancelled ? ($assignment['formatted_cancelled_at'] ?? '') : ($currentStatus === 'Completed' ? ($assignment['formatted_completed_at'] ?? '') : ''); ?>
                    <div class="col-12" data-assignment-card data-assignment-id="<?php echo (int)($assignment['assignment_id'] ?? 0); ?>">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                                    <div>
                                        <div class="small text-muted">Booking reference</div>
                                        <h5 class="fw-bold mb-1"><?php echo Validator::escape($assignment['booking_reference'] ?? ''); ?></h5>
                                        <div class="small text-muted"><?php echo Validator::escape($assignment['materials_summary'] ?? ''); ?></div>
                                    </div>
                                    <?php if ($isCancelled): ?><span class="badge bg-danger" data-live-assignment-status>Cancelled by Seller<?php if ($terminalTimestamp !== ''): ?><small class="d-block fw-normal"><?php echo Validator::escape($terminalTimestamp); ?></small><?php endif; ?></span><?php else: ?><span class="status-badge <?php echo $statusClass === 'accepted' ? 'approved' : ($statusClass === 'declined' ? 'rejected' : 'pending'); ?>" data-live-assignment-status><?php echo Validator::escape($assignment['assignment_status'] ?? 'Matched'); ?><?php if ($terminalTimestamp !== ''): ?><small class="d-block fw-normal"><?php echo Validator::escape($terminalTimestamp); ?></small><?php endif; ?></span><?php endif; ?>
                                </div>

                                <div class="row g-3 small mb-3">
                                    <div class="col-md-4"><div class="text-muted">Seller</div><strong><?php echo Validator::escape($assignment['seller_name'] ?? ''); ?></strong></div>
                                    <div class="col-md-4"><div class="text-muted">Mobile Number</div><?php $sellerMobile = trim((string)($assignment['seller_mobile'] ?? $assignment['contact_number'] ?? '')); ?><?php if ($sellerMobile !== ''): ?><strong><a href="tel:<?php echo htmlspecialchars($sellerMobile, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($sellerMobile, ENT_QUOTES, 'UTF-8'); ?></a></strong> <a href="sms:<?php echo htmlspecialchars($sellerMobile, ENT_QUOTES, 'UTF-8'); ?>" class="ms-1" aria-label="SMS seller"><i class="bi bi-chat-dots"></i></a><?php else: ?><span class="text-muted">Not provided</span><?php endif; ?></div>
                                    <div class="col-md-4"><div class="text-muted">Pickup</div><strong><?php echo Validator::escape($pickupDate); ?></strong><br><?php echo Validator::escape($pickupTime); ?></div>
                                    <div class="col-md-4"><div class="text-muted">Approximate Distance</div><strong><?php echo isset($assignment['distance_km']) ? number_format((float)$assignment['distance_km'], 2) . ' km' : 'Pending'; ?></strong></div>
                                    <div class="col-md-4"><div class="text-muted">Actual Weight</div><?php if ((float)($assignment['actual_weight'] ?? 0) > 0): ?><span class="badge bg-success font-monospace fs-6"><?php echo number_format((float)$assignment['actual_weight'], 2); ?> kg</span><?php else: ?><span class="badge bg-secondary">Pending Weight</span><?php endif; ?></div>
                                </div>

                                <div class="small text-muted mb-3"><?php echo Validator::escape($assignment['pickup_address'] ?? ''); ?></div>

                                <?php $booking = ['id' => (int)($assignment['pickup_request_id'] ?? 0), 'status' => strtolower(str_replace(' ', '_', (string)($assignment['current_status'] ?? ''))), 'seller_lat' => $assignment['seller_lat'] ?? null, 'seller_lng' => $assignment['seller_lng'] ?? null]; ?>
                                <?php if (strtolower($booking['status']) === 'for_pickup'): ?>
                                    <div id="seller-map-<?php echo $booking['id']; ?>" class="seller-location-map" data-lat="<?php echo Validator::escape($booking['seller_lat']); ?>" data-lng="<?php echo Validator::escape($booking['seller_lng']); ?>" style="height: 300px; width: 100%; border-radius: 8px; margin-top: 15px;"></div>
                                <?php endif; ?>

                                <?php if (in_array(($assignment['current_status'] ?? ''), ['Pending Request', 'Pending', 'Requested', 'Matched'], true) || (($assignment['assignment_status'] ?? '') === 'Matched')): ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-success accept-request" data-assignment-id="<?php echo (int)($assignment['assignment_id'] ?? 0); ?>">Accept</button>
                                        <button type="button" class="btn btn-outline-danger decline-request" data-assignment-id="<?php echo (int)($assignment['assignment_id'] ?? 0); ?>">Decline</button>
                                    </div>
                                <?php elseif (($assignment['current_status'] ?? '') === 'Accepted'): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button" class="btn btn-primary schedule-request" data-pickup-request-id="<?php echo (int)($assignment['pickup_request_id'] ?? 0); ?>" data-scheduled-date="<?php echo Validator::escape($assignment['preferred_pickup_date'] ?? ''); ?>" data-scheduled-time="<?php echo Validator::escape($assignment['preferred_pickup_time'] ?? ''); ?>">Mark as Scheduled</button>
                                    </div>
                                <?php elseif (($assignment['assignment_status'] ?? '') === 'Accepted' && ($assignment['current_status'] ?? '') === 'Accepted'): ?>
                                    <form class="row g-3 align-items-end schedule-form" data-pickup-request-id="<?php echo (int)($assignment['pickup_request_id'] ?? 0); ?>">
                                        <?php echo CSRF::field(); ?>
                                        <input type="hidden" name="action" value="schedule">
                                        <input type="hidden" name="pickup_request_id" value="<?php echo (int)($assignment['pickup_request_id'] ?? 0); ?>">
                                        <div class="col-md-5">
                                            <label class="form-label" for="schedule-date-<?php echo (int)($assignment['pickup_request_id'] ?? 0); ?>">Pickup date</label>
                                            <input type="date" class="form-control" id="schedule-date-<?php echo (int)($assignment['pickup_request_id'] ?? 0); ?>" name="scheduled_date" required>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label" for="schedule-time-<?php echo (int)($assignment['pickup_request_id'] ?? 0); ?>">Pickup time</label>
                                            <select class="form-select" id="schedule-time-<?php echo (int)($assignment['pickup_request_id'] ?? 0); ?>" name="scheduled_time" required>
                                                <option value="">Choose time</option>
                                                <option value="8:00 AM">8:00 AM</option>
                                                <option value="9:00 AM">9:00 AM</option>
                                                <option value="10:00 AM">10:00 AM</option>
                                                <option value="11:00 AM">11:00 AM</option>
                                                <option value="12:00 PM">12:00 PM</option>
                                                <option value="1:00 PM">1:00 PM</option>
                                                <option value="2:00 PM">2:00 PM</option>
                                                <option value="3:00 PM">3:00 PM</option>
                                                <option value="4:00 PM">4:00 PM</option>
                                                <option value="5:00 PM">5:00 PM</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-primary w-100">Schedule</button>
                                        </div>
                                    </form>
                                <?php elseif (($assignment['current_status'] ?? '') === 'Scheduled'): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button" class="btn btn-primary mark-for-pickup" data-pickup-request-id="<?php echo (int)($assignment['pickup_request_id'] ?? 0); ?>">Mark For Pickup</button>
                                    </div>
                                <?php elseif (($assignment['current_status'] ?? '') === 'For Pickup'): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button" class="btn btn-success complete-transaction" data-pickup-request-id="<?php echo (int)($assignment['pickup_request_id'] ?? 0); ?>"><i class="bi bi-eye"></i> View Details / Record Weight</button>
                                    </div>
                                <?php elseif (($assignment['current_status'] ?? '') === 'Completed'): ?>
                                <?php elseif (($assignment['current_status'] ?? '') === 'For Pickup' && empty($assignment['transaction_id'])): ?>
                                    <form class="row g-3 settlement-form" data-pickup-request-id="<?php echo (int)($assignment['pickup_request_id'] ?? 0); ?>">
                                        <?php echo CSRF::field(); ?>
                                        <input type="hidden" name="action" value="complete-transaction">
                                        <input type="hidden" name="pickup_request_id" value="<?php echo (int)($assignment['pickup_request_id'] ?? 0); ?>">
                                        <div class="col-12">
                                            <div class="small text-muted mb-2">Actual accepted materials</div>
                                            <?php foreach (explode('|', (string)($assignment['settlement_items'] ?? '')) as $settlementItem): ?>
                                                <?php $settlementParts = explode(':', $settlementItem, 3); if (count($settlementParts) !== 3) continue; ?>
                                                <div class="row g-2 align-items-end mb-2 settlement-material-row" data-item-id="<?php echo (int)$settlementParts[0]; ?>">
                                                    <div class="col-md-5"><label class="form-label small">Material</label><input type="text" class="form-control" value="<?php echo Validator::escape($settlementParts[1]); ?>" readonly></div>
                                                    <div class="col-md-3"><label class="form-label small">Actual weight (kg)</label><input type="number" class="form-control material-weight-input" min="0" step="0.01" required></div>
                                                    <div class="col-md-2"><div class="form-check mb-2"><input class="form-check-input material-accepted-input" type="checkbox" checked><label class="form-check-label">Accepted</label></div></div>
                                                    <div class="col-md-2"><label class="form-label small">Condition notes</label><input type="text" class="form-control material-condition-input" maxlength="255" placeholder="Condition"></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="col-md-5">
                                            <label class="form-label" for="condition-<?php echo (int)($assignment['pickup_request_id'] ?? 0); ?>">Material condition notes</label>
                                            <input type="text" class="form-control" id="condition-<?php echo (int)($assignment['pickup_request_id'] ?? 0); ?>" name="material_condition_notes" maxlength="255" placeholder="Good, mixed, slightly damp...">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label" for="payment-method-<?php echo (int)($assignment['pickup_request_id'] ?? 0); ?>">Payment method</label>
                                            <select class="form-select" id="payment-method-<?php echo (int)($assignment['pickup_request_id'] ?? 0); ?>" name="payment_method" required><option value="Cash">Cash</option><option value="GCash">GCash</option></select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label" for="payment-status-<?php echo (int)($assignment['pickup_request_id'] ?? 0); ?>">Payment status</label>
                                            <select class="form-select" id="payment-status-<?php echo (int)($assignment['pickup_request_id'] ?? 0); ?>" name="payment_status" required><option value="Unpaid">Unpaid</option><option value="Paid">Paid</option></select>
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="submit" class="btn btn-success w-100">Complete</button>
                                        </div>
                                        <div class="col-12">
                                            <div class="card border-0 bg-light">
                                                <div class="card-body py-3">
                                                    <div class="small text-muted mb-2">Settlement preview</div>
                                                    <div class="row g-2 small">
                                                        <div class="col-md-3"><div class="text-muted">Final value</div><strong class="final-recyclable-value">₱0.00</strong></div>
                                                        <div class="col-md-3"><div class="text-muted">Pickup fee</div><strong class="final-pickup-fee">₱0.00</strong></div>
                                                        <div class="col-md-3"><div class="text-muted">Service fee</div><strong class="final-service-fee">₱0.00</strong></div>
                                                        <div class="col-md-3"><div class="text-muted">Seller payout</div><strong class="final-seller-amount text-success">₱0.00</strong></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                <?php elseif (!empty($assignment['transaction_id']) && in_array(($assignment['current_status'] ?? ''), ['For Pickup', 'Completed'], true)): ?>
                                    <div class="border rounded-3 p-3 payment-review-panel" data-transaction-id="<?php echo (int)($assignment['transaction_id'] ?? 0); ?>">
                                        <div class="small text-muted">Manual payment review</div>
                                        <div class="mt-1">Method: <strong><?php echo Validator::escape($assignment['payment_method'] ?? ''); ?></strong> · Status: <strong class="payment-status-label"><?php echo Validator::escape($assignment['payment_status'] ?? 'Unpaid'); ?></strong></div>
                                        <?php if (!empty($assignment['payment_proof_id'])): ?><a class="btn btn-sm btn-outline-secondary mt-2" href="<?php echo APP_URL; ?>/user-junkshop/api/payment.php?action=view-proof&amp;proof_id=<?php echo (int)$assignment['payment_proof_id']; ?>" target="_blank" rel="noopener">Review GCash proof</a><?php endif; ?>
                                        <?php if (($assignment['payment_status'] ?? '') === 'Unpaid'): ?><button type="button" class="btn btn-sm btn-success confirm-payment mt-2" data-transaction-id="<?php echo (int)($assignment['transaction_id'] ?? 0); ?>">Confirm payment as Paid</button><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>
</div>
<div class="modal fade" id="confirmMatchedRequestModal" tabindex="-1" aria-labelledby="confirmMatchedRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmMatchedRequestModalLabel">Confirm action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" data-confirm-message>Confirm this action?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" data-confirm-continue>Confirm</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="matchedRequestSuccessModal" tabindex="-1" aria-labelledby="matchedRequestSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="text-success display-6 mb-2"><i class="bi bi-check-circle-fill"></i></div>
                <h5 class="modal-title" id="matchedRequestSuccessModalLabel">Success</h5>
                <p class="mb-0 mt-2" data-success-message>Action completed successfully.</p>
            </div>
        </div>
    </div>
</div>
<script>
window.ecopickMatchedRequestsApiUrl = '<?php echo APP_URL; ?>/user-junkshop/api/get_matched_requests.php';
window.addEventListener('DOMContentLoaded', function () {
    const apiUrl = '<?php echo APP_URL; ?>/user-junkshop/api/junkshop-operations.php';
    const matchedRequestsApiUrl = '<?php echo APP_URL; ?>/user-junkshop/api/get_matched_requests.php';
    const feedback = document.getElementById('assignment-feedback');
    const assignmentList = document.getElementById('assignment-list');
    const confirmation = window.ecopick.setupActionConfirmation({ modalId: 'confirmMatchedRequestModal' });
    const successModalElement = document.getElementById('matchedRequestSuccessModal');
    const successModal = bootstrap.Modal.getOrCreateInstance(successModalElement);
    const successMessage = successModalElement.querySelector('[data-success-message]');
    const trackingContainer = document.getElementById('live-tracking-container');
    const sellerAddressText = document.getElementById('seller-address-text');
    const junkshopAddressText = document.getElementById('junkshop-address-text');
    const liveDistance = document.getElementById('live-distance');
    const locationErrorNote = document.getElementById('location-error-note');
    const locationErrorMessage = '<strong>Location Access Required:</strong> Please ensure your device GPS is turned ON in settings and location permissions are ALLOWED for this website in your browser settings. Once enabled, reload the page to view your location and exact distance.';
    const sellerMaps = new Map();
    const highPrecisionGeoOptions = {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0
    };
    let trackingIntervalId = null;
    let trackingRequestId = null;
    let previousRequestKeys = new Set();
    let hasLoadedRequests = false;

    function updateMatchedRequestBadges(count, hasNewRequest) {
        const safeCount = Math.max(0, Number.parseInt(count, 10) || 0);
        document.querySelectorAll('#matched-requests-badge, [data-matched-requests-badge]').forEach(function (badge) {
            badge.textContent = String(safeCount);
            badge.style.display = safeCount > 0 ? 'inline-block' : 'none';
            if (hasNewRequest) {
                badge.classList.add('pulse');
                window.setTimeout(function () { badge.classList.remove('pulse'); }, 2000);
            }
        });
    }
    let locationUpdateInProgress = false;
    let lastGeocodedJunkshopLocation = null;

    function showLocationError() {
        if (!locationErrorNote) return;
        locationErrorNote.innerHTML = '<strong>Location service issue:</strong> Weak GPS signal or connection drop detected. Please check location settings or refresh/reload the website.';
        locationErrorNote.classList.remove('d-none');
    }

    function hideLocationError() {
        locationErrorNote?.classList.add('d-none');
    }

    function updateLiveLocation(requestId, lat, lng, sellerLat, sellerLng) {
        const distance = calculateDistance(sellerLat, sellerLng, lat, lng);
        reverseGeocodeJunkshopLocation(lat, lng);
        if (liveDistance) liveDistance.textContent = distance.toFixed(2) + ' km';

        const data = new FormData();
        data.append('_csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '<?php echo CSRF::token(); ?>');
        data.append('booking_id', String(requestId));
        data.append('lat', String(lat));
        data.append('lng', String(lng));
        locationUpdateInProgress = true;
        fetch('<?php echo APP_URL; ?>/user-junkshop/api/update_junkshop_live_location.php', { method: 'POST', body: data, credentials: 'same-origin' })
            .catch(function () {})
            .finally(function () { locationUpdateInProgress = false; });
    }

    function removeSellerMap(requestId) {
        const mapElement = document.getElementById('seller-map-' + requestId);
        const map = sellerMaps.get(String(requestId));
        if (map) {
            map.remove();
            sellerMaps.delete(String(requestId));
        }
        if (mapElement) mapElement.remove();
    }

    function initializeSellerMaps() {
        document.querySelectorAll('.seller-location-map').forEach(function (container) {
            const requestId = container.id.replace('seller-map-', '');
            if (sellerMaps.has(requestId)) return;
            const sellerLat = Number(container.dataset.lat);
            const sellerLng = Number(container.dataset.lng);
            if (!Number.isFinite(sellerLat) || sellerLat < -90 || sellerLat > 90 || !Number.isFinite(sellerLng) || sellerLng < -180 || sellerLng > 180) return;
            const map = L.map(container).setView([sellerLat, sellerLng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
            L.marker([sellerLat, sellerLng]).addTo(map);
            sellerMaps.set(requestId, map);
            window.setTimeout(function () { map.invalidateSize(); }, 0);
        });
    }

    function calculateDistance(lat1, lon1, lat2, lon2) {
        const earthRadiusKm = 6371;
        const latitudeDelta = (lat2 - lat1) * Math.PI / 180;
        const longitudeDelta = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(latitudeDelta / 2) ** 2
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(longitudeDelta / 2) ** 2;
        return Number((earthRadiusKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))).toFixed(2));
    }

    function formatFullAddress(addressObj) {
        const address = addressObj || {};
        return [
            address.village || address.suburb || address.neighbourhood || address.quarter || '',
            address.city || address.town || address.municipality || '',
            address.state || address.province || address.region || ''
        ].filter(Boolean).join(', ');
    }

    function reverseGeocodeSellerLocation(lat, lng) {
        const url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng);
        fetch(url, { headers: { Accept: 'application/json' } })
            .then(response => response.ok ? response.json() : null)
            .then(data => {
                const address = formatFullAddress(data?.address);
                if (address && sellerAddressText) sellerAddressText.textContent = address;
            })
            .catch(function () {});
    }

    function reverseGeocodeJunkshopLocation(lat, lng) {
        if (lastGeocodedJunkshopLocation && calculateDistance(lastGeocodedJunkshopLocation.lat, lastGeocodedJunkshopLocation.lng, lat, lng) < 0.05) return;
        lastGeocodedJunkshopLocation = { lat: lat, lng: lng };
        const url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng);
        fetch(url, { headers: { Accept: 'application/json' } })
            .then(response => response.ok ? response.json() : null)
            .then(data => {
                const address = formatFullAddress(data?.address);
                if (address && junkshopAddressText) junkshopAddressText.textContent = address;
            })
            .catch(function () {});
    }

    function showLiveTracking(payload, requestId) {
        const sellerLat = parseFloat(payload.seller_lat);
        const sellerLng = parseFloat(payload.seller_lng);
        if (!Number.isFinite(sellerLat) || sellerLat < -90 || sellerLat > 90 || !Number.isFinite(sellerLng) || sellerLng < -180 || sellerLng > 180) {
            showFeedback('Pickup marked, but the seller location is unavailable.', false);
            return;
        }

        trackingContainer.style.display = 'block';
        sellerAddressText.textContent = 'Fetching...';
        reverseGeocodeSellerLocation(sellerLat, sellerLng);
        trackingRequestId = requestId;
        if (trackingIntervalId !== null) window.clearInterval(trackingIntervalId);
        if (!navigator.geolocation) {
            showLocationError();
            junkshopAddressText.textContent = 'Geolocation unavailable';
            return;
        }
        const updateCurrentLocation = function () {
            if (locationUpdateInProgress) return;
            navigator.geolocation.getCurrentPosition(function (position) {
                const junkshopLat = Number.parseFloat(position.coords.latitude);
                const junkshopLng = Number.parseFloat(position.coords.longitude);
                if (!Number.isFinite(junkshopLat) || !Number.isFinite(junkshopLng)) return;
                hideLocationError();
                updateLiveLocation(requestId, Number(junkshopLat.toFixed(6)), Number(junkshopLng.toFixed(6)), sellerLat, sellerLng);
            }, function (error) {
                const weakSignalMessage = 'Weak GPS signal or connection drop detected. Please check location settings or refresh/reload the website.';
                if (error && (error.code === 2 || error.code === 3 || error.code === 4)) {
                    showLocationError();
                    if (trackingRequestId === requestId && junkshopAddressText) junkshopAddressText.textContent = 'Location unavailable';
                } else {
                    showLocationError();
                    if (trackingRequestId === requestId && junkshopAddressText) junkshopAddressText.textContent = 'Unable to access current location';
                }
                if (trackingRequestId === requestId && junkshopAddressText) {
                    junkshopAddressText.textContent = junkshopAddressText.textContent || 'Location unavailable';
                }
                if (error && (error.code === 2 || error.code === 3 || error.code === 4) && window.bootstrap?.Toast) {
                    // no-op: handled inline with alert feedback
                }
            }, highPrecisionGeoOptions);
        };
        function initAutoLocation() {
            updateCurrentLocation();
        }
        initAutoLocation();
        trackingIntervalId = window.setInterval(updateCurrentLocation, 10000);
    }

    function showFeedback(message, isSuccess) {
        if (!feedback) return;
        feedback.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
        feedback.innerHTML = '<span>' + escapeHtml(message) + '</span><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        feedback.classList.add('alert-dismissible', 'fade', 'show');
        feedback.classList.remove('d-none');
    }

    function sendFormData(payload, signal) {
        const formData = new FormData();
        Object.entries(payload).forEach(([key, value]) => formData.append(key, value));
        return fetch(apiUrl, { method: 'POST', body: formData, credentials: 'same-origin', signal: signal || undefined });
    }

    function showSuccessAndReload(message) {
        successMessage.textContent = message || 'Action completed successfully.';
        successModal.show();
        window.setTimeout(function () { window.location.reload(); }, 900);
    }

    function startLiveLocationWatch(requestId, sellerLat, sellerLng) {
        if (trackingRequestId === requestId || !Number.isFinite(Number(sellerLat)) || !Number.isFinite(Number(sellerLng))) return;
        showLiveTracking({ seller_lat: sellerLat, seller_lng: sellerLng }, requestId);
    }

    <?php if ($activeTrackingAssignment !== null): ?>
    showLiveTracking(<?php echo json_encode([
        'seller_lat' => $activeTrackingAssignment['seller_lat'] ?? null,
        'seller_lng' => $activeTrackingAssignment['seller_lng'] ?? null,
        'seller_address' => $activeTrackingAssignment['seller_address'] ?? null,
    ], JSON_UNESCAPED_SLASHES); ?>, <?php echo (int) $activeTrackingAssignment['pickup_request_id']; ?>);
    <?php endif; ?>

    assignmentList?.addEventListener('click', async function (event) {
        const button = event.target.closest('.accept-request, .decline-request, .schedule-request, .mark-for-pickup, .complete-transaction');
        if (!button) return;
        if (button.classList.contains('schedule-request')) {
            openScheduleModal(Number(button.dataset.pickupRequestId || 0));
            return;
        }
        if (button.classList.contains('complete-transaction')) {
            openCompletionModal(Number(button.dataset.pickupRequestId || 0));
            return;
        }
        if (button.classList.contains('mark-for-pickup')) {
            openPickupModal(Number(button.dataset.pickupRequestId || 0));
            return;
        }
        const action = button.classList.contains('accept-request') ? 'accept' : (button.classList.contains('decline-request') ? 'decline' : (button.classList.contains('schedule-request') ? 'schedule' : 'mark-for-pickup'));
        const actionLabel = action === 'accept' ? 'accept' : (action === 'decline' ? 'decline' : (action === 'schedule' ? 'mark this request as scheduled' : 'mark this request as for pickup'));
        const pickupRequestId = Number(button.dataset.pickupRequestId || button.dataset.assignmentId || 0);
            confirmation.open('Are you sure you want to ' + actionLabel + (action === 'accept' || action === 'decline' ? ' this pickup request?' : '?'), async function () {
            button.disabled = true;
            const requestId = Number.isFinite(pickupRequestId) ? pickupRequestId : 0;
            const response = await sendFormData({ _csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || '<?php echo CSRF::token(); ?>', action: action, pickup_request_id: requestId, assignment_id: requestId, scheduled_date: button.dataset.scheduledDate || '', scheduled_time: button.dataset.scheduledTime || '' });
            const payload = await response.json();
            if (!payload.success) { showFeedback(payload.message || 'Unable to update this request.', false); button.disabled = false; return false; }
            showFeedback(payload.message || 'Request updated.', true);
            if (action === 'mark-for-pickup') {
                const trackingRequest = payload.data?.requests?.find(function (request) { return Number(request.pickup_request_id) === requestId; });
                showLiveTracking(trackingRequest || payload, requestId);
                return true;
            }
            showSuccessAndReload(payload.message || 'Request updated successfully.');
            return true;
        });
    });

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]));
    }

    function formatPickupDate(value) {
        const match = String(value ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!match) return '';
        const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
        return date.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    }

    function formatPickupTime(value) {
        const match = String(value ?? '').trim().match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?$/i);
        if (!match) return '';
        let hours = Number(match[1]);
        const minutes = match[2];
        const meridiem = match[3]?.toUpperCase();
        if (meridiem) {
            hours = hours % 12 + (meridiem === 'PM' ? 12 : 0);
        }
        const suffix = hours >= 12 ? 'PM' : 'AM';
        const displayHour = hours % 12 || 12;
        return displayHour + ':' + minutes + ' ' + suffix;
    }

    function getEstimatedNetAmount(request) {
        const grossAmount = String(request?.settlement_items || '').split('|').filter(Boolean).reduce(function (total, item) {
            const parts = item.split(':');
            return total + (Number(parts[2] || 0) * Number(parts[3] || 0));
        }, 0);
        const pickupFee = Number(request?.pickup_fee || 0);
        const serviceFee = grossAmount * (Number(request?.service_fee_pct || 5) / 100);
        return Math.max(0, grossAmount - pickupFee - serviceFee);
    }

    function formatPhilippineMobile(value) {
        const digits = String(value || '').replace(/\D/g, '');
        if (/^09\d{9}$/.test(digits)) return '+63' + digits.slice(1);
        if (/^9\d{9}$/.test(digits)) return '+63' + digits;
        if (/^63(9\d{9})$/.test(digits)) return '+' + digits;
        return 'Not provided';
    }

    const modalActionLoadingOverlayId = 'matched-requests-loading-overlay';
    let modalActionLoadingOverlayStyleInjected = false;

    function ensureModalActionLoadingOverlay() {
        let overlay = document.getElementById(modalActionLoadingOverlayId);
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = modalActionLoadingOverlayId;
            overlay.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-50 opacity-0 pe-none';
            overlay.style.zIndex = '2000';
            overlay.style.transition = 'opacity 0.2s ease-in-out';
            overlay.innerHTML = '<div class="spinner-border text-light" role="status" aria-hidden="true"><span class="visually-hidden">Loading...</span></div>';
            document.body.appendChild(overlay);
        }
        if (!modalActionLoadingOverlayStyleInjected) {
            const overlayStyle = document.createElement('style');
            overlayStyle.textContent = '#' + modalActionLoadingOverlayId + '.show { opacity: 1; }';
            document.head.appendChild(overlayStyle);
            modalActionLoadingOverlayStyleInjected = true;
        }
        return overlay;
    }

    function setModalActionLoading(button, labelText) {
        if (!button) return;
        const originalMarkup = button.dataset.originalMarkup || button.innerHTML;
        button.dataset.originalMarkup = originalMarkup;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' + (labelText || 'Processing...');
        ensureModalActionLoadingOverlay().classList.add('show');
    }

    function clearModalActionLoading(button) {
        if (!button) return;
        const originalMarkup = button.dataset.originalMarkup || '';
        button.disabled = false;
        if (originalMarkup) {
            button.innerHTML = originalMarkup;
        }
        delete button.dataset.originalMarkup;
        const overlay = document.getElementById(modalActionLoadingOverlayId);
        overlay?.classList.remove('show');
    }

    async function openPickupModal(requestId) {
        const response = await fetch(matchedRequestsApiUrl + '?action=list-matched', { credentials: 'same-origin' });
        const payload = await response.json();
        const request = (payload.data?.requests || []).find(item => getPickupRequestId(item) === requestId);
        if (!request || request.current_status !== 'Scheduled') {
            showFeedback('This pickup is no longer scheduled or could not be loaded.', false);
            return;
        }
        const firstModal = document.createElement('div');
        firstModal.className = 'modal fade';
        firstModal.innerHTML = '<div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form novalidate><div class="modal-header"><h5 class="modal-title">Collector Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="text-muted small">Enter the collector who will handle this pickup.</p><div class="mb-3"><label class="form-label" for="collector-first-name">First Name</label><input type="text" class="form-control" id="collector-first-name" maxlength="50" pattern="[A-Z]+" autocomplete="off" required></div><div><label class="form-label" for="collector-last-name">Last Name</label><input type="text" class="form-control" id="collector-last-name" maxlength="50" pattern="[A-Z]+" autocomplete="off" required></div><div class="invalid-feedback">Use uppercase letters only, without spaces or symbols.</div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Proceed</button></div></form></div></div>';
        document.body.appendChild(firstModal);
        const firstInstance = bootstrap.Modal.getOrCreateInstance(firstModal);
        const form = firstModal.querySelector('form');
        const inputs = Array.from(firstModal.querySelectorAll('input'));
        inputs.forEach(function (input) {
            input.addEventListener('input', function () {
                input.value = input.value.toUpperCase().replace(/[^A-Z]/g, '');
                input.setCustomValidity(/^[A-Z]+$/.test(input.value) ? '' : 'Use uppercase letters only.');
            });
        });
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }
            const collectorName = inputs[0].value + ' ' + inputs[1].value;
            const netAmount = getEstimatedNetAmount(request);
            const secondModal = document.createElement('div');
            secondModal.className = 'modal fade';
            secondModal.innerHTML = '<div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirm Pickup Assignment</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-3"><dt class="col-6">Collector</dt><dd class="col-6 text-end fw-bold">' + escapeHtml(collectorName) + '</dd><dt class="col-6">Seller mobile</dt><dd class="col-6 text-end" data-seller-mobile>' + escapeHtml(formatPhilippineMobile(request.seller_mobile || request.contact_number)) + '</dd><dt class="col-6">Net amount to receive</dt><dd class="col-6 text-end fw-bold">₱' + netAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</dd></dl><div class="alert alert-info small mb-0">Confirming will update request status to \'For Pickup\' and send an SMS notification when the platform SMS setting is enabled.</div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-back-to-edit>Back to Edit</button><button type="button" class="btn btn-primary" data-confirm-pickup>Confirm &amp; Send SMS</button></div></div></div>';
            firstModal.dataset.keepOpen = 'true';
            firstInstance.hide();
            document.body.appendChild(secondModal);
            const secondInstance = bootstrap.Modal.getOrCreateInstance(secondModal);
            secondInstance.show();
            secondModal.querySelector('[data-back-to-edit]').addEventListener('click', function () {
                secondInstance.hide();
                firstInstance.show();
            });
            secondModal.querySelector('[data-confirm-pickup]').addEventListener('click', async function () {
                const confirmButton = secondModal.querySelector('[data-confirm-pickup]');
                const controller = new AbortController();
                const safetyTimeout = window.setTimeout(function () {
                    controller.abort();
                    clearModalActionLoading(confirmButton);
                    showFeedback('The pickup assignment request timed out. Please try again.', false);
                }, 10000);
                setModalActionLoading(confirmButton, 'Processing...');
                try {
                    const result = await sendFormData({ _csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || '<?php echo CSRF::token(); ?>', action: 'mark-for-pickup', pickup_request_id: requestId, collector_first_name: inputs[0].value, collector_last_name: inputs[1].value }, controller.signal);
                    const resultPayload = await result.json();
                    if (!resultPayload.success) {
                        showFeedback(resultPayload.message || 'Unable to mark this pickup.', false);
                        clearModalActionLoading(confirmButton);
                        return;
                    }
                    firstModal.dataset.keepOpen = 'false';
                    secondInstance.hide();
                    const smsDispatchState = ['Sent', 'Disabled', 'Bypassed'].includes(resultPayload.sms_status);
                    showFeedback(resultPayload.message || 'Pickup marked as For Pickup.', resultPayload.success && smsDispatchState);
                    if (resultPayload.sms_status === 'Sent') {
                        window.setTimeout(function () { window.location.reload(); }, 1800);
                    }
                } catch (error) {
                    if (error?.name !== 'AbortError') {
                        showFeedback('Unable to mark this pickup right now. Please try again.', false);
                    }
                    clearModalActionLoading(confirmButton);
                } finally {
                    window.clearTimeout(safetyTimeout);
                    clearModalActionLoading(confirmButton);
                }
            });
            secondModal.addEventListener('hidden.bs.modal', function () { secondModal.remove(); });
        });
        firstModal.addEventListener('hidden.bs.modal', function () {
            if (firstModal.dataset.keepOpen !== 'true') firstModal.remove();
        });
        firstInstance.show();
    }

    function getPickupRequestId(assignment) {
        const value = Number(assignment?.pickup_request_id ?? assignment?.assignment_id ?? 0);
        return Number.isFinite(value) ? value : 0;
    }

    function openScheduleModal(requestId) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = '<div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="schedule-modal-form"><div class="modal-header"><h5 class="modal-title">Set Pickup Schedule</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="form-label">Confirmed pickup date</label><input class="form-control mb-3" name="scheduled_date" type="date" min="<?php echo date('Y-m-d'); ?>" required><label class="form-label">Confirmed pickup time</label><input class="form-control" name="scheduled_time" type="time" required></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Set Schedule</button></div></form></div></div>';
        document.body.appendChild(modal);
        const instance = bootstrap.Modal.getOrCreateInstance(modal);
        instance.show();
        modal.querySelector('form').addEventListener('submit', async function (event) {
            event.preventDefault();
            if (!event.target.checkValidity()) { event.target.classList.add('was-validated'); return; }
            const submitButton = modal.querySelector('button[type="submit"]');
            const controller = new AbortController();
            const safetyTimeout = window.setTimeout(function () {
                controller.abort();
                clearModalActionLoading(submitButton);
                showFeedback('The schedule request timed out. Please try again.', false);
            }, 10000);
            setModalActionLoading(submitButton, 'Setting schedule...');
            try {
                const form = new FormData(event.target);
                form.append('_csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '<?php echo CSRF::token(); ?>');
                form.append('action', 'schedule');
                form.append('pickup_request_id', requestId);
                const response = await fetch(apiUrl, { method: 'POST', body: form, credentials: 'same-origin', signal: controller.signal });
                const payload = await response.json();
                if (!payload.success) { showFeedback(payload.message || 'Unable to set the pickup schedule.', false); clearModalActionLoading(submitButton); return; }
                instance.hide();
                showSuccessAndReload(payload.message || 'Pickup schedule confirmed.');
            } catch (error) {
                if (error?.name !== 'AbortError') {
                    showFeedback('Unable to set the pickup schedule right now. Please try again.', false);
                }
                clearModalActionLoading(submitButton);
            } finally {
                window.clearTimeout(safetyTimeout);
                clearModalActionLoading(submitButton);
            }
        });
        modal.addEventListener('hidden.bs.modal', function () { modal.remove(); });
    }

    async function openCompletionModal(requestId) {
        const response = await fetch(apiUrl + '?action=list-matched', { credentials: 'same-origin' });
        const payload = await response.json();
        const request = (payload.data?.requests || []).find(item => getPickupRequestId(item) === requestId);
        if (!request) { showFeedback('Unable to load the pickup materials.', false); return; }
        const rows = String(request.settlement_items || '').split('|').filter(Boolean).map((item, index) => {
            const parts = item.split(':');
            const standardPrice = Number(parts[3] || 0);
            const isMaterialListed = parts[4] === '1';
            const priceValue = isMaterialListed ? standardPrice.toFixed(2) : '';
            const itemId = Number(parts[0] || 0);
            const priceInputId = index === 0 ? 'actual-price-input' : 'actual-price-input-' + itemId;
            const priceMarkup = isMaterialListed
                ? '<input type="hidden" class="actual-price-input" name="actual_price_per_kg" id="' + priceInputId + '" data-item-id="' + itemId + '" value="' + priceValue + '">'
                : '<label class="form-label">Buying Price per kg (₱) (Custom Material)</label><input type="number" step="0.01" min="0" class="form-control actual-price-input" name="actual_price_per_kg" id="' + priceInputId + '" data-item-id="' + itemId + '" required>';
            return '<div class="row g-2 mb-2 settlement-material-row align-items-end" data-item-id="' + itemId + '"><div class="col-md-3 material-name"><label class="form-label">' + escapeHtml(parts[1] || 'Material') + '</label><input class="form-control" value="' + escapeHtml(parts[2] || '') + ' kg estimated" readonly></div><div class="col-md-3 material-entry-field"><label class="form-label">Actual Weight (kg)</label><input class="form-control actual-weight-input actual-weight" data-item-id="' + itemId + '" type="number" min="0" step="0.01" required></div><div class="col-md-3 material-entry-field">' + priceMarkup + '</div><div class="col-md-2 material-entry-field"><label class="form-label">Condition</label><input class="form-control material-condition" type="text" maxlength="120" placeholder="Good, Mixed, Contaminated"></div><div class="col-md-1 d-flex gap-1 mb-1"><button type="button" class="btn btn-sm btn-outline-danger remove-material" title="Remove material"><i class="bi bi-trash"></i><span class="visually-hidden">Remove</span></button><button type="button" class="btn btn-sm btn-outline-secondary undo-material" title="Undo removal" style="display: none;"><i class="bi bi-arrow-counterclockwise"></i><span class="visually-hidden">Undo</span></button></div></div>';
        }).join('');
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = '<div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><form id="completion-form"><div class="modal-header"><h5 class="modal-title">Complete Transaction</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="small text-muted mb-3">Enter the actual weight and condition received for each material.</div>' + rows + '<div class="row g-3 mt-2"><div class="col-md-4"><label class="form-label">Pickup collection fee</label><div class="form-control-plaintext fw-bold">₱' + Number(request.pickup_fee || 0).toFixed(2) + ' <small class="text-muted">(Auto-calculated from Pickup Request)</small></div></div><div class="col-md-4"><label class="form-label">Payment method</label><select class="form-select" name="payment_method" required><option value="Cash">Cash</option></select></div><div class="col-md-4"><label class="form-label">Payment status</label><select class="form-select" name="payment_status" required><option value="Unpaid">Unpaid</option><option value="Paid">Paid</option></select></div></div><div class="d-flex flex-column gap-2 mt-3 small"><div class="d-flex justify-content-between align-items-center gap-3"><span>Actual recyclable value</span><strong class="live-final-value">₱0.00</strong></div><div class="d-flex justify-content-between align-items-center gap-3"><span>Pickup / Collection fee</span><span class="text-danger live-pickup-fee">- ₱0.00</span></div><div class="d-flex justify-content-between align-items-center gap-3"><span>Ecopick service fee</span><span class="text-danger live-service-fee">- ₱0.00</span></div><div class="d-flex justify-content-between align-items-center gap-3 border-top pt-2 mt-1"><strong>Final net amount</strong><strong class="live-net-amount">₱0.00</strong></div></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Complete Transaction</button></div></form></div></div>';
        const actualWeight = Number(request.actual_weight || 0);
        const actualWeightSummary = document.createElement('div');
        actualWeightSummary.className = 'd-flex justify-content-between align-items-center bg-light rounded p-3 mb-3';
        actualWeightSummary.innerHTML = '<span class="fw-semibold">Actual Weight</span><strong class="badge ' + (actualWeight > 0 ? 'bg-success' : 'bg-secondary') + ' font-monospace fs-6">' + (actualWeight > 0 ? actualWeight.toFixed(2) + ' kg' : 'Pending Weight') + '</strong>';
        modal.querySelector('.modal-body')?.prepend(actualWeightSummary);
        document.body.appendChild(modal);
        const instance = bootstrap.Modal.getOrCreateInstance(modal);
        instance.show();
        const confirmModal = document.createElement('div');
        confirmModal.className = 'modal fade';
        confirmModal.id = 'completeTransactionModal';
        confirmModal.setAttribute('tabindex', '-1');
        confirmModal.setAttribute('aria-labelledby', 'completeTransactionModalLabel');
        confirmModal.setAttribute('aria-hidden', 'true');
        confirmModal.innerHTML = '<div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="completeTransactionModalLabel">Confirm Transaction Completion</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p>Are you sure you want to finalize and mark this transaction as completed? This action cannot be undone.</p><dl class="row mb-0 small"><dt class="col-7">Final material payout</dt><dd class="col-5 text-end" data-confirm-final-payout>₱0.00</dd><dt class="col-7">Pickup fee</dt><dd class="col-5 text-end" data-confirm-pickup-fee>- ₱0.00</dd><dt class="col-7">EcoPick service fee</dt><dd class="col-5 text-end" data-confirm-service-fee>- ₱0.00</dd></dl></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Review Details</button><button type="button" id="btn-final-complete-submit" class="btn btn-success">Yes, Complete Transaction</button></div></div></div>';
        document.body.appendChild(confirmModal);
        const confirmInstance = bootstrap.Modal.getOrCreateInstance(confirmModal);
        let completionConfirmed = false;
        const formatMoney = value => '₱' + Number(value || 0).toFixed(2);
        const updatePreview = function () {
            const actualRecyclableValue = Array.from(modal.querySelectorAll('.actual-weight-input')).reduce((total, input) => {
                if (input.disabled) return total;
                const priceInput = modal.querySelector('.actual-price-input[data-item-id="' + input.dataset.itemId + '"]');
                return total + (Number(input.value || 0) * Number(priceInput?.value || 0));
            }, 0);
            const pickupFee = Number(request.pickup_fee || 0);
            const actualServiceFee = actualRecyclableValue * (Number(request.service_fee_pct || 5) / 100);
            const finalNetAmount = actualRecyclableValue - pickupFee - actualServiceFee;
            modal.querySelector('.live-final-value').textContent = formatMoney(actualRecyclableValue);
            modal.querySelector('.live-pickup-fee').textContent = '- ' + formatMoney(pickupFee);
            modal.querySelector('.live-service-fee').textContent = '- ' + formatMoney(actualServiceFee);
            modal.querySelector('.live-net-amount').textContent = formatMoney(finalNetAmount);
        };
        modal.querySelectorAll('.actual-price-input, .actual-weight-input, .material-condition').forEach(input => input.addEventListener('input', updatePreview));
        modal.addEventListener('click', function (event) {
            const actionButton = event.target.closest('.remove-material, .undo-material');
            if (!actionButton) return;
            const row = actionButton.closest('.settlement-material-row');
            if (!row) return;
            const isRemoving = actionButton.classList.contains('remove-material');
            row.classList.toggle('opacity-50', isRemoving);
            row.querySelector('.material-name')?.classList.toggle('text-decoration-line-through', isRemoving);
            row.querySelectorAll('input').forEach(input => { input.disabled = isRemoving; });
            row.querySelectorAll('.material-entry-field').forEach(field => { field.style.display = isRemoving ? 'none' : ''; });
            row.querySelector('.remove-material').style.display = isRemoving ? 'none' : '';
            row.querySelector('.undo-material').style.display = isRemoving ? '' : 'none';
            updatePreview();
        });
        modal.querySelector('form').addEventListener('submit', async function (event) {
            event.preventDefault();
            if (!event.target.checkValidity()) { event.target.classList.add('was-validated'); return; }
            const activeActualWeight = Array.from(modal.querySelectorAll('.actual-weight-input:not(:disabled)')).reduce((total, input) => total + (Number.isFinite(Number(input.value)) ? Number(input.value) : 0), 0);
            if (activeActualWeight < 3) {
                showFeedback('The total actual weight must be at least 3 kg to complete this transaction.', false);
                return;
            }
            const materialSettlements = Array.from(modal.querySelectorAll('.actual-weight-input:not(:disabled)')).map(input => ({ pickup_request_item_id: Number(input.dataset.itemId), actual_weight_kg: Number(input.value), buying_price_per_kg: Number(modal.querySelector('.actual-price-input[data-item-id="' + input.dataset.itemId + '"]')?.value || 0), material_condition: input.closest('.settlement-material-row')?.querySelector('.material-condition')?.value || '', accepted: Number(input.value) > 0 }));
            if (!completionConfirmed) {
                confirmModal.querySelector('[data-confirm-final-payout]').textContent = modal.querySelector('.live-final-value').textContent;
                confirmModal.querySelector('[data-confirm-pickup-fee]').textContent = modal.querySelector('.live-pickup-fee').textContent;
                confirmModal.querySelector('[data-confirm-service-fee]').textContent = modal.querySelector('.live-service-fee').textContent;
                confirmInstance.show();
                return;
            }
            completionConfirmed = false;
            const confirmSubmitButton = confirmModal.querySelector('#btn-final-complete-submit');
            const controller = new AbortController();
            const safetyTimeout = window.setTimeout(function () {
                controller.abort();
                clearModalActionLoading(confirmSubmitButton);
                showFeedback('The transaction completion request timed out. Please try again.', false);
            }, 10000);
            setModalActionLoading(confirmSubmitButton, 'Processing...');
            try {
                const firstPrice = materialSettlements[0]?.buying_price_per_kg ?? '';
                const result = await sendFormData({ _csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || '<?php echo CSRF::token(); ?>', action: 'complete-transaction', pickup_request_id: requestId, actual_price_per_kg: firstPrice, material_settlements: JSON.stringify(materialSettlements), payment_method: modal.querySelector('[name="payment_method"]').value, payment_status: modal.querySelector('[name="payment_status"]').value }, controller.signal);
                const resultPayload = await result.json();
                if (!resultPayload.success) { showFeedback(resultPayload.message || 'Unable to complete this transaction.', false); clearModalActionLoading(confirmSubmitButton); return; }
                removeSellerMap(requestId);
                instance.hide();
                showSuccessAndReload(resultPayload.message);
            } catch (error) {
                if (error?.name !== 'AbortError') {
                    showFeedback('Unable to complete this transaction right now. Please try again.', false);
                }
                clearModalActionLoading(confirmSubmitButton);
            } finally {
                window.clearTimeout(safetyTimeout);
                clearModalActionLoading(confirmSubmitButton);
            }
        });
        confirmModal.querySelector('#btn-final-complete-submit').addEventListener('click', function () {
            completionConfirmed = true;
            confirmInstance.hide();
            modal.querySelector('form').requestSubmit();
        });
        modal.addEventListener('hidden.bs.modal', function () { modal.remove(); });
        confirmModal.addEventListener('hidden.bs.modal', function () { confirmModal.remove(); });
    }

    function renderLifecycleControls(request, requestId) {
        const status = request.current_status || 'Pending Request';
        if (status === 'Cancelled' || status === 'Cancelled by Seller') {
            return '';
        }
        if (status === 'Accepted') {
            return '<button type="button" class="btn btn-primary schedule-request" data-pickup-request-id="' + requestId + '">Set Schedule</button>';
        }
        if (status === 'Pending Request' || status === 'Pending' || status === 'Requested' || status === 'Matched') {
            return '<button type="button" class="btn btn-success accept-request" data-pickup-request-id="' + requestId + '" data-assignment-id="' + requestId + '">Accept</button><button type="button" class="btn btn-outline-danger decline-request" data-pickup-request-id="' + requestId + '" data-assignment-id="' + requestId + '">Decline</button>';
        }
        if (status === 'Scheduled') {
            return '<button type="button" class="btn btn-primary mark-for-pickup" data-pickup-request-id="' + requestId + '">Mark as For Pickup</button>';
        }
        if (status === 'For Pickup') {
            return '<button type="button" class="btn btn-success complete-transaction" data-pickup-request-id="' + requestId + '"><i class="bi bi-eye"></i> View Details / Record Weight</button>';
        }
        if (status === 'Completed') {
            return '';
        }
        return '';
    }

    function renderMatchedRequests(requests) {
        if (!assignmentList) return;
        sellerMaps.forEach(function (map) { map.remove(); });
        sellerMaps.clear();
        if (!requests.length) {
            assignmentList.innerHTML = '<div class="empty-state" data-empty-assignments><div class="display-6 text-muted"><i class="bi bi-inbox"></i></div><h5 class="mt-3 mb-2 fw-bold">No matched requests yet</h5><p class="text-muted mb-0">New pickup requests will appear here as soon as they are matched to your junkshop.</p></div>';
            return;
        }

        assignmentList.innerHTML = requests.map(function (request) {
            const requestId = getPickupRequestId(request);
            const status = request.current_status || 'Pending Request';
            const cancelled = status === 'Cancelled' || status === 'Cancelled by Seller';
            const statusClass = status === 'Completed' ? 'approved' : (['Pending Request', 'Pending', 'Requested', 'Matched'].includes(status) ? 'pending' : 'scheduled');
            const terminalTimestamp = status === 'Completed' ? request.formatted_completed_at : (cancelled ? request.formatted_cancelled_at : '');
            const statusLabel = cancelled ? 'Cancelled by Seller' : status;
            const statusMarkup = '<span class="' + (cancelled ? 'badge bg-danger' : 'status-badge ' + statusClass) + '">' + escapeHtml(statusLabel) + (terminalTimestamp ? '<small class="d-block fw-normal">' + escapeHtml(terminalTimestamp) + '</small>' : '') + '</span>';
            const distance = request.distance_km === null || request.distance_km === undefined ? 'Pending' : Number(request.distance_km).toFixed(2) + ' km';
            const pickupDate = formatPickupDate(request.confirmed_pickup_date || request.preferred_pickup_date || '');
            const pickupTime = formatPickupTime(request.confirmed_pickup_time || request.preferred_pickup_time || '');
            const mapMarkup = status.toLowerCase().replace(/ /g, '_') === 'for_pickup'
                ? '<div id="seller-map-' + requestId + '" class="seller-location-map" data-lat="' + escapeHtml(request.seller_lat) + '" data-lng="' + escapeHtml(request.seller_lng) + '" style="height: 300px; width: 100%; border-radius: 8px; margin-top: 15px;"></div>'
                : '';
            const actualWeight = Number(request.actual_weight || 0);
            const actualWeightMarkup = actualWeight > 0 ? '<span class="badge bg-success font-monospace fs-6">' + actualWeight.toFixed(2) + ' kg</span>' : '<span class="badge bg-secondary">Pending Weight</span>';
            const sellerMobile = request.seller_mobile || request.contact_number || '';
            const contactMarkup = formatPhilippineMobile(sellerMobile) !== 'Not provided' ? '<strong><a href="tel:' + escapeHtml(formatPhilippineMobile(sellerMobile)) + '">' + escapeHtml(formatPhilippineMobile(sellerMobile)) + '</a></strong> <a href="sms:' + escapeHtml(formatPhilippineMobile(sellerMobile)) + '" class="ms-1" aria-label="SMS seller"><i class="bi bi-chat-dots"></i></a>' : '<span class="text-muted">Not provided</span>';
            return '<div class="col-12" data-assignment-card data-assignment-id="' + requestId + '"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3"><div><div class="small text-muted">Booking reference</div><h5 class="fw-bold mb-1">' + escapeHtml(request.booking_reference) + '</h5><div class="small text-muted">' + escapeHtml(request.materials_summary || '') + '</div></div>' + statusMarkup + '</div><div class="row g-3 small mb-3"><div class="col-md-4"><div class="text-muted">Seller</div><strong>' + escapeHtml(request.seller_name) + '</strong></div><div class="col-md-4"><div class="text-muted">Mobile Number</div>' + contactMarkup + '</div><div class="col-md-4"><div class="text-muted">Pickup</div><strong>' + escapeHtml(pickupDate) + '</strong><br>' + escapeHtml(pickupTime) + '</div><div class="col-md-4"><div class="text-muted">Approximate Distance</div><strong>' + escapeHtml(distance) + '</strong></div><div class="col-md-4"><div class="text-muted">Actual Weight</div>' + actualWeightMarkup + '</div></div><div class="small text-muted mb-3">' + escapeHtml(request.pickup_address) + '</div>' + mapMarkup + '<div class="d-flex flex-wrap gap-2">' + renderLifecycleControls(request, requestId) + '</div></div></div></div>';
        }).join('');
        initializeSellerMaps();
    }

    async function fetchMatchedRequests() {
        try {
            const response = await fetch(matchedRequestsApiUrl, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const payload = await response.json();
            if (payload.session_expired && payload.redirect) {
                window.location.href = payload.redirect;
                return;
            }
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Unable to refresh matched requests.');
            const requests = payload.data?.requests || [];
            const requestKeys = new Set(requests.map(function (request) {
                return String(getPickupRequestId(request)) + ':' + String(request.current_status || '') + ':' + String(request.updated_at || '');
            }));
            const hasNewRequest = hasLoadedRequests && requests.some(function (request) {
                return !previousRequestKeys.has(String(getPickupRequestId(request)) + ':' + String(request.current_status || '') + ':' + String(request.updated_at || ''));
            });
            if (!hasLoadedRequests || hasNewRequest || requestKeys.size !== previousRequestKeys.size) {
                renderMatchedRequests(requests);
            }
            previousRequestKeys = requestKeys;
            hasLoadedRequests = true;
            updateMatchedRequestBadges(payload.data?.count || 0, hasNewRequest);
            requests.forEach(function (request) {
                if (request.current_status === 'For Pickup') startLiveLocationWatch(getPickupRequestId(request), request.seller_lat, request.seller_lng);
            });
        } catch (error) {
            showFeedback(error.message || 'Unable to refresh matched requests.', false);
        }
    }

    fetchMatchedRequests();
    window.setInterval(fetchMatchedRequests, 5000);

    document.querySelectorAll('.schedule-form').forEach(form => {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }
            confirmation.open('Confirm this pickup schedule?', async function () {
                const formData = new FormData(form);
                formData.append('_csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '<?php echo CSRF::token(); ?>');
                const response = await fetch(apiUrl, { method: 'POST', body: formData, credentials: 'same-origin' });
                const payload = await response.json();
                if (!payload.success) { showFeedback(payload.message || 'Unable to schedule this pickup.', false); return false; }
                showFeedback(payload.message || 'Pickup scheduled successfully.', true);
                showSuccessAndReload(payload.message || 'Pickup scheduled successfully.');
                return true;
            });
        });
    });

    document.querySelectorAll('.settlement-form').forEach(form => {
        const requestId = form.dataset.pickupRequestId;
        const getMaterialSettlements = () => Array.from(form.querySelectorAll('.settlement-material-row')).map(row => ({
            pickup_request_item_id: Number(row.dataset.itemId || 0),
            material_name: row.querySelector('input[readonly]')?.value || 'Material',
            actual_weight_kg: Number(row.querySelector('.material-weight-input')?.value || 0),
            accepted: Boolean(row.querySelector('.material-accepted-input')?.checked),
            condition_notes: row.querySelector('.material-condition-input')?.value || ''
        }));
        const resetPreview = () => {
            form.querySelector('.final-recyclable-value').textContent = '₱0.00';
            form.querySelector('.final-pickup-fee').textContent = '₱0.00';
            form.querySelector('.final-service-fee').textContent = '₱0.00';
            form.querySelector('.final-seller-amount').textContent = '₱0.00';
        };

        form.querySelectorAll('.material-weight-input, .material-accepted-input, .material-condition-input').forEach(input => input.addEventListener('input', async function () {
            const materialSettlements = getMaterialSettlements();
            if (!materialSettlements.some(material => material.accepted && material.actual_weight_kg > 0)) {
                resetPreview();
                return;
            }

            const previewData = new FormData();
            previewData.append('_csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '<?php echo CSRF::token(); ?>');
            previewData.append('action', 'preview-settlement');
            previewData.append('pickup_request_id', requestId);
            previewData.append('material_settlements', JSON.stringify(materialSettlements));
            const response = await fetch(apiUrl, { method: 'POST', body: previewData, credentials: 'same-origin' });
            const payload = await response.json();
            if (!payload.success || !payload.data) return;
            form.querySelector('.final-recyclable-value').textContent = '₱' + Number(payload.data.final_recyclable_value || 0).toFixed(2);
            form.querySelector('.final-pickup-fee').textContent = '₱' + Number(payload.data.pickup_fee || 0).toFixed(2);
            form.querySelector('.final-service-fee').textContent = '₱' + Number(payload.data.ecopick_service_fee || 0).toFixed(2);
            form.querySelector('.final-seller-amount').textContent = '₱' + Number(payload.data.final_seller_amount || 0).toFixed(2);
        }));

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }
            confirmation.open('Are you sure you want to finalize and complete this transaction?', async function () {
                const formData = new FormData(form);
                formData.append('_csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '<?php echo CSRF::token(); ?>');
                formData.set('pickup_request_id', requestId);
                formData.append('material_settlements', JSON.stringify(getMaterialSettlements()));
                const response = await fetch(apiUrl, { method: 'POST', body: formData, credentials: 'same-origin' });
                const payload = await response.json();
                if (!payload.success) { showFeedback(payload.message || 'Unable to complete the transaction.', false); return false; }
                showFeedback(payload.message || 'Transaction completed successfully.', true);
                showSuccessAndReload(payload.message || 'Transaction completed successfully.');
                return true;
            });
        });
    });

    document.querySelectorAll('.confirm-payment').forEach(button => {
        button.addEventListener('click', async function () {
            const data = new FormData();
            data.append('_csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '<?php echo CSRF::token(); ?>');
            data.append('action', 'confirm-payment');
            data.append('transaction_id', button.dataset.transactionId);
            button.disabled = true;
            try {
                const response = await fetch('<?php echo APP_URL; ?>/user-junkshop/api/payment.php', { method: 'POST', body: data, credentials: 'same-origin' });
                const payload = await response.json();
                if (!response.ok || !payload.success) { showFeedback(payload.message || 'Payment could not be confirmed.', false); button.disabled = false; return; }
                showFeedback(payload.message, true);
                button.closest('.payment-review-panel').querySelector('.payment-status-label').textContent = 'Paid';
                button.remove();
            } catch (requestError) { showFeedback('Unable to confirm payment right now.', false); button.disabled = false; }
        });
    });
});
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
