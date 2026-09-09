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

$dashboardController = new DashboardController();
$assignments = $dashboardController->getJunkshopAssignments(Auth::userId());
$pageTitle = 'Matched Requests';
$currentPage = 'matched-requests';
$userDisplayName = Auth::userName();
ob_start();
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4 p-lg-5">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
            <div>
                <p class="eyebrow mb-1">Junkshop operations</p>
                <h2 class="fw-bold mb-1">Matched Requests</h2>
                <p class="text-muted mb-0">Review assigned pickup requests, accept or decline them, schedule collection, and complete final settlements.</p>
            </div>
            <a href="<?php echo APP_URL; ?>/user-junkshop/dashboard.php" class="btn btn-outline-secondary">Back to dashboard</a>
        </div>

        <div id="assignment-feedback" class="alert d-none" role="status" aria-live="polite"></div>

        <div class="row g-4" id="assignment-list">
        <?php if (empty($assignments)): ?>
            <div class="empty-state" data-empty-assignments>
                <div class="display-6 text-muted"><i class="bi bi-inbox"></i></div>
                <h5 class="mt-3 mb-2 fw-bold">No matched requests yet</h5>
                <p class="text-muted mb-0">New pickup requests will appear here as soon as they are matched to your junkshop.</p>
            </div>
        <?php else: ?>
                <?php foreach ($assignments as $assignment): ?>
                    <?php $statusClass = strtolower((string)($assignment['assignment_status'] ?? 'Matched')); ?>
                    <div class="col-12" data-assignment-card data-assignment-id="<?php echo (int)($assignment['assignment_id'] ?? 0); ?>">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                                    <div>
                                        <div class="small text-muted">Booking reference</div>
                                        <h5 class="fw-bold mb-1"><?php echo Validator::escape($assignment['booking_reference'] ?? ''); ?></h5>
                                        <div class="small text-muted"><?php echo Validator::escape($assignment['materials_summary'] ?? ''); ?></div>
                                    </div>
                                    <span class="status-badge <?php echo $statusClass === 'accepted' ? 'approved' : ($statusClass === 'declined' ? 'rejected' : 'pending'); ?>" data-live-assignment-status><?php echo Validator::escape($assignment['assignment_status'] ?? 'Matched'); ?></span>
                                </div>

                                <div class="row g-3 small mb-3">
                                    <div class="col-md-3"><div class="text-muted">Status</div><strong data-live-current-status><?php echo Validator::escape($assignment['current_status'] ?? 'Matched'); ?></strong></div>
                                    <div class="col-md-3"><div class="text-muted">Seller</div><strong><?php echo Validator::escape($assignment['seller_name'] ?? ''); ?></strong></div>
                                    <div class="col-md-3"><div class="text-muted">Pickup</div><strong><?php echo Validator::escape($assignment['preferred_pickup_date'] ?? ''); ?></strong><br><?php echo Validator::escape($assignment['preferred_pickup_time'] ?? ''); ?></div>
                                    <div class="col-md-3"><div class="text-muted">Distance</div><strong><?php echo isset($assignment['distance_km']) ? number_format((float)$assignment['distance_km'], 2) . ' km' : 'Pending'; ?></strong></div>
                                </div>

                                <div class="small text-muted mb-3"><?php echo Validator::escape($assignment['pickup_address'] ?? ''); ?>, <?php echo Validator::escape($assignment['barangay'] ?? ''); ?></div>

                                <?php if (($assignment['assignment_status'] ?? '') === 'Matched'): ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button type="button" class="btn btn-success accept-request" data-assignment-id="<?php echo (int)($assignment['assignment_id'] ?? 0); ?>">Accept</button>
                                        <button type="button" class="btn btn-outline-danger decline-request" data-assignment-id="<?php echo (int)($assignment['assignment_id'] ?? 0); ?>">Decline</button>
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
window.addEventListener('DOMContentLoaded', function () {
    const apiUrl = '<?php echo APP_URL; ?>/user-junkshop/api/junkshop-operations.php';
    const feedback = document.getElementById('assignment-feedback');
    const assignmentList = document.getElementById('assignment-list');
    const confirmation = window.ecopick.setupActionConfirmation({ modalId: 'confirmMatchedRequestModal' });
    const successModalElement = document.getElementById('matchedRequestSuccessModal');
    const successModal = bootstrap.Modal.getOrCreateInstance(successModalElement);
    const successMessage = successModalElement.querySelector('[data-success-message]');

    function showFeedback(message, isSuccess) {
        if (!feedback) return;
        feedback.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-danger');
        feedback.textContent = message;
        feedback.classList.remove('d-none');
    }

    function sendFormData(payload) {
        const formData = new FormData();
        Object.entries(payload).forEach(([key, value]) => formData.append(key, value));
        return fetch(apiUrl, { method: 'POST', body: formData, credentials: 'same-origin' });
    }

    function showSuccessAndReload(message) {
        successMessage.textContent = message || 'Action completed successfully.';
        successModal.show();
        window.setTimeout(function () { window.location.reload(); }, 900);
    }

    assignmentList?.addEventListener('click', async function (event) {
        const button = event.target.closest('.accept-request, .decline-request');
        if (!button) return;
        const action = button.classList.contains('accept-request') ? 'accept' : 'decline';
        const actionLabel = action === 'accept' ? 'accept' : 'decline';
        confirmation.open('Are you sure you want to ' + actionLabel + ' this pickup request?', async function () {
            button.disabled = true;
            const response = await sendFormData({ _csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || '<?php echo CSRF::token(); ?>', action: action, assignment_id: button.dataset.assignmentId });
            const payload = await response.json();
            if (!payload.success) { showFeedback(payload.message || 'Unable to update this request.', false); button.disabled = false; return false; }
            showFeedback(payload.message || 'Request updated.', true);
            showSuccessAndReload(payload.message || 'Request updated successfully.');
            return true;
        });
    });

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]));
    }

    function appendLiveAssignments(assignments) {
        if (!assignmentList || !Array.isArray(assignments)) return 0;
        let appended = 0;
        assignments.forEach(function (assignment) {
            const assignmentId = Number(assignment.assignment_id || 0);
            if (!assignmentId || assignmentList.querySelector('[data-assignment-id="' + assignmentId + '"]')) return;
            assignmentList.querySelector('[data-empty-assignments]')?.remove();
            assignmentList.insertAdjacentHTML('beforeend', '<div class="col-12" data-assignment-card data-assignment-id="' + assignmentId + '"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3"><div><div class="small text-muted">Booking reference</div><h5 class="fw-bold mb-1">' + escapeHtml(assignment.booking_reference) + '</h5><div class="small text-muted">' + escapeHtml(assignment.materials_summary) + '</div></div><span class="status-badge pending" data-live-assignment-status>Matched</span></div><div class="row g-3 small mb-3"><div class="col-md-3"><div class="text-muted">Status</div><strong data-live-current-status>' + escapeHtml(assignment.current_status || 'Matched') + '</strong></div><div class="col-md-3"><div class="text-muted">Seller</div><strong>' + escapeHtml(assignment.seller_name) + '</strong></div><div class="col-md-3"><div class="text-muted">Pickup</div><strong>' + escapeHtml(assignment.preferred_pickup_date) + '</strong><br>' + escapeHtml(assignment.preferred_pickup_time) + '</div><div class="col-md-3"><div class="text-muted">Distance</div><strong>' + Number(assignment.distance_km || 0).toFixed(2) + ' km</strong></div></div><div class="small text-muted mb-3">' + escapeHtml(assignment.pickup_address) + ', ' + escapeHtml(assignment.barangay) + '</div><div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-success accept-request" data-assignment-id="' + assignmentId + '">Accept</button><button type="button" class="btn btn-outline-danger decline-request" data-assignment-id="' + assignmentId + '">Decline</button></div></div></div></div>');
            appended += 1;
        });
        return appended;
    }

    function updateLiveAssignments(assignments) {
        if (!assignmentList || !Array.isArray(assignments)) return 0;
        let updated = 0;
        assignments.forEach(function (assignment) {
            const assignmentId = Number(assignment.assignment_id || 0);
            const card = assignmentList.querySelector('[data-assignment-id="' + assignmentId + '"]');
            if (!card) return;
            const assignmentStatus = String(assignment.assignment_status || 'Matched');
            const currentStatus = String(assignment.current_status || 'Matched');
            const assignmentStatusNode = card.querySelector('[data-live-assignment-status]');
            const currentStatusNode = card.querySelector('[data-live-current-status]');
            if (assignmentStatusNode && assignmentStatusNode.textContent !== assignmentStatus) {
                assignmentStatusNode.textContent = assignmentStatus;
                assignmentStatusNode.className = 'status-badge ' + (assignmentStatus.toLowerCase() === 'accepted' ? 'approved' : (assignmentStatus.toLowerCase() === 'declined' ? 'rejected' : 'pending'));
                updated += 1;
            }
            if (currentStatusNode && currentStatusNode.textContent !== currentStatus) {
                currentStatusNode.textContent = currentStatus;
                updated += 1;
            }
        });
        return updated;
    }

    if (window.EcoPickLiveUpdates && assignmentList) {
        window.EcoPickLiveUpdates.startPolling({
            key: 'junkshop-matched-requests',
            url: apiUrl,
            interval: 3000,
            onSuccess: function (payload) {
                const assignments = payload.data?.assignments || [];
                const added = appendLiveAssignments(assignments);
                const updated = updateLiveAssignments(assignments);
                if (added > 0) showFeedback(added === 1 ? 'A new pickup request matched your accepted materials.' : added + ' new pickup requests matched your accepted materials.', true);
                else if (updated > 0) showFeedback('A matched pickup request was updated.', true);
            }
        });
    }

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

    document.querySelectorAll('.mark-for-pickup').forEach(button => {
        button.addEventListener('click', async function () {
            confirmation.open('Are you sure you want to mark this request as out for pickup?', async function () {
                const requestId = button.dataset.pickupRequestId;
                const formData = new FormData();
                formData.append('_csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '<?php echo CSRF::token(); ?>');
                formData.append('action', 'mark-for-pickup');
                formData.append('pickup_request_id', requestId);
                const response = await fetch(apiUrl, { method: 'POST', body: formData, credentials: 'same-origin' });
                const payload = await response.json();
                if (!payload.success) { showFeedback(payload.message || 'Unable to mark pickup as in transit.', false); return false; }
                showFeedback(payload.message || 'Pickup marked for pickup.', true);
                showSuccessAndReload(payload.message || 'Pickup marked for pickup successfully.');
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
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
