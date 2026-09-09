<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/MaterialPriceController.php';

if (!Auth::check()) {
    header('Location: ' . APP_URL . '/user-junkshop/login.php');
    exit;
}
if (Auth::userRole() !== 'seller') {
    header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
    exit;
}

$materialController = new MaterialPriceController();
$materials = $materialController->listActiveMaterials();
$priceOverview = $materialController->listApprovedJunkshopsWithPrices();
$averagePrices = [];
foreach ($priceOverview as $row) {
    $materialId = (int) ($row['material_id'] ?? 0);
    $buyingPrice = (float) ($row['buying_price'] ?? 0.0);
    if ($materialId <= 0 || $buyingPrice <= 0) {
        continue;
    }
    if (!isset($averagePrices[$materialId])) {
        $averagePrices[$materialId] = ['total' => 0.0, 'count' => 0];
    }
    $averagePrices[$materialId]['total'] += $buyingPrice;
    $averagePrices[$materialId]['count'] += 1;
}
$materialAveragePrices = [];
foreach ($averagePrices as $materialId => $details) {
    $materialAveragePrices[(int)$materialId] = $details['count'] > 0 ? round($details['total'] / $details['count'], 2) : 0.0;
}
$pageTitle = 'New Pickup Request';
$currentPage = 'new-pickup';
$userDisplayName = Auth::userName();
$timeOptions = ['8:00 AM', '8:30 AM', '9:00 AM', '9:30 AM', '10:00 AM', '10:30 AM', '11:00 AM', '11:30 AM', '12:00 PM', '12:30 PM', '1:00 PM', '1:30 PM', '2:00 PM', '2:30 PM', '3:00 PM', '3:30 PM', '4:00 PM', '4:30 PM', '5:00 PM', '5:30 PM', '6:00 PM'];
$materialOptions = [];
foreach ($materials as $material) {
    $materialOptions[] = ['id' => (int) $material['id'], 'name' => $material['material_name'], 'unit' => $material['unit_of_measure']];
}
$feeConfig = FeeCalculator::getConfigs();
$defaultPickupFee = isset($feeConfig['default_pickup_fee']) ? (float) $feeConfig['default_pickup_fee'] : FeeCalculator::DEFAULT_PICKUP_FEE;
$defaultServiceFeePct = isset($feeConfig['ecopick_service_fee_pct']) ? (float) $feeConfig['ecopick_service_fee_pct'] : (float) FeeCalculator::DEFAULT_SERVICE_FEE_PCT * 100;

ob_start();
?>
<div class="row justify-content-center">
    <div class="col-12 col-xl-9">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <div class="mb-4">
                    <p class="eyebrow mb-1">Seller booking</p>
                    <h2 class="fw-bold mb-2">New Pickup Request</h2>
                    <p class="text-muted mb-0">Tell us what you would like a registered junkshop to review for a future pickup.</p>
                </div>
                <div class="alert alert-info" role="note"><i class="bi bi-info-circle me-2"></i><strong>Your request will be reviewed and matched with a suitable registered Junkshop in a later step.</strong> EcoPick facilitates the request; registered junkshops handle collection, weighing, assessment, and purchase.</div>
                <div id="request-form-status" class="alert d-none" role="alert" aria-live="polite"></div>
                <form id="pickup-request-form" enctype="multipart/form-data" novalidate>
                    <?php echo CSRF::field(); ?>
                    <input type="hidden" name="action" value="create">
                    <fieldset class="mb-4">
                        <legend class="h5 fw-bold">Materials</legend>
                        <p class="small text-muted">Add each material once and enter an estimated weight in kilograms.</p>
                        <div id="material-rows"></div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-material-row"><i class="bi bi-plus-lg"></i> Add material</button>
                    </fieldset>

                    <div class="card border-0 bg-light-subtle mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold mb-0">Estimated calculation</h5>
                                <span class="badge bg-primary-subtle text-primary">Live estimate</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6 col-xl-3">
                                    <div class="small text-muted">Estimated recyclable value</div>
                                    <div class="fw-bold fs-5" id="calc-estimated-recyclable-value">₱0.00</div>
                                </div>
                                <div class="col-md-6 col-xl-3">
                                    <div class="small text-muted">Pickup fee</div>
                                    <div class="fw-bold fs-5" id="calc-pickup-fee">₱<?php echo number_format($defaultPickupFee, 2); ?></div>
                                </div>
                                <div class="col-md-6 col-xl-3">
                                    <div class="small text-muted">Service fee</div>
                                    <div class="fw-bold fs-5" id="calc-service-fee">₱0.00</div>
                                </div>
                                <div class="col-md-6 col-xl-3">
                                    <div class="small text-muted">Estimated net amount</div>
                                    <div class="fw-bold fs-5 text-success" id="calc-estimated-net-amount">₱0.00</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><label class="form-label" for="pickup_location_name">Location name <span class="text-danger">*</span></label><input class="form-control" id="pickup_location_name" name="pickup_location_name" required maxlength="160" placeholder="Subdivision, landmark, or sitio"></div>
                        <div class="col-md-4"><label class="form-label" for="pickup_address">Pickup address <span class="text-danger">*</span></label><input class="form-control" id="pickup_address" name="pickup_address" required maxlength="255" placeholder="House number, street, subdivision"></div>
                        <div class="col-md-4"><label class="form-label" for="barangay">Barangay <span class="text-danger">*</span></label><input class="form-control" id="barangay" name="barangay" required maxlength="120" placeholder="Barangay name"></div>
                        <div class="col-md-4"><label class="form-label" for="approximate_distance_km">Approximate distance (km) <span class="text-danger">*</span></label><input type="number" min="0" max="15" step="0.01" class="form-control" id="approximate_distance_km" name="approximate_distance_km" required placeholder="Example: 4.50"><div class="form-text">Text-based estimate used for matching; no GPS or map service is used.</div></div>
                        <div class="col-md-6"><label class="form-label" for="preferred_pickup_date">Preferred pickup date <span class="text-danger">*</span></label><input type="date" class="form-control" id="preferred_pickup_date" name="preferred_pickup_date" required></div>
                        <div class="col-md-6"><label class="form-label" for="preferred_pickup_time">Preferred pickup time <span class="text-danger">*</span></label><select class="form-select" id="preferred_pickup_time" name="preferred_pickup_time" required><option value="">Choose a preferred time</option><?php foreach ($timeOptions as $time): ?><option value="<?php echo Validator::escape($time); ?>"><?php echo Validator::escape($time); ?></option><?php endforeach; ?></select></div>
                        <div class="col-12"><label class="form-label" for="photo">Optional recyclable-material photo</label><input type="file" class="form-control" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><div class="form-text">JPG, JPEG, PNG, or WEBP only; maximum 5 MB.</div></div>
                        <div class="col-12"><label class="form-label" for="notes">Optional notes</label><textarea class="form-control" id="notes" name="notes" rows="4" maxlength="2000" placeholder="Add useful access or material details. Do not include payment information."></textarea></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center"><a class="btn btn-outline-secondary" href="<?php echo APP_URL; ?>/user-junkshop/current-bookings.php"><i class="bi bi-calendar2-check"></i> Current bookings</a><button type="submit" class="btn btn-primary" id="submit-pickup-request"><i class="bi bi-send"></i> Submit pickup request</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="confirmPickupRequestModal" tabindex="-1" aria-labelledby="confirmPickupRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmPickupRequestModalLabel">Confirm pickup request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" data-confirm-message>Are you sure you want to submit this pickup request?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" data-confirm-continue>Confirm / Submit</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="pickupRequestSuccessModal" tabindex="-1" aria-labelledby="pickupRequestSuccessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pickupRequestSuccessModalLabel">Request submitted</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="pickup-request-success-message">Pickup request submitted successfully!</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="pickup-request-success-ok">OK</button>
            </div>
        </div>
    </div>
</div>
<script>
window.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('pickup-request-form');
    const rows = document.getElementById('material-rows');
    const status = document.getElementById('request-form-status');
    const materials = <?php echo json_encode($materialOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const averagePrices = <?php echo json_encode($materialAveragePrices, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const serviceFeePct = <?php echo json_encode($defaultServiceFeePct, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const pickupFee = <?php echo json_encode($defaultPickupFee, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const apiUrl = '<?php echo APP_URL; ?>/user-junkshop/api/pickup-requests.php';
    const confirmation = setupActionConfirmation({ modalId: 'confirmPickupRequestModal' });
    const successModalElement = document.getElementById('pickupRequestSuccessModal');
    const successModal = bootstrap.Modal.getOrCreateInstance(successModalElement);
    const successOkButton = document.getElementById('pickup-request-success-ok');
    let rowIndex = 0;

    function formatMoney(value) {
        return '₱' + Number(value || 0).toFixed(2);
    }

    function updateEstimateSummary() {
        let recyclableValue = 0;
        document.querySelectorAll('.material-row').forEach(function (row) {
            const select = row.querySelector('.material-select');
            const weightInput = row.querySelector('.weight-input');
            if (!select || !weightInput) return;
            const materialId = Number(select.value || 0);
            const weight = Number(weightInput.value || 0);
            const price = materialId && averagePrices[materialId] ? Number(averagePrices[materialId]) : 0;
            recyclableValue += weight * price;
        });

        const serviceFee = recyclableValue * (Number(serviceFeePct || 0) / 100);
        const netAmount = recyclableValue - Number(pickupFee || 0) - serviceFee;
        document.getElementById('calc-estimated-recyclable-value').textContent = formatMoney(recyclableValue);
        document.getElementById('calc-pickup-fee').textContent = formatMoney(Number(pickupFee || 0));
        document.getElementById('calc-service-fee').textContent = formatMoney(serviceFee);
        document.getElementById('calc-estimated-net-amount').textContent = formatMoney(netAmount);
    }

    function showStatus(message, success, errors) {
        status.className = 'alert ' + (success ? 'alert-success' : 'alert-danger');
        status.innerHTML = message + (errors && errors.length ? '<ul class="mb-0 mt-2">' + errors.map(error => '<li>' + String(error).replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character])) + '</li>').join('') + '</ul>' : '');
        status.classList.remove('d-none');
        status.focus();
    }

    function addRow() {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-end mb-3 material-row';
        row.dataset.index = String(rowIndex++);
        row.innerHTML = '<div class="col-md-7"><label class="form-label" for="material-' + row.dataset.index + '">Material <span class="text-danger">*</span></label><select class="form-select material-select" id="material-' + row.dataset.index + '" name="material_id[]" required><option value="">Choose material</option>' + materials.map(material => '<option value="' + material.id + '">' + material.name + ' (' + material.unit + ')</option>').join('') + '</select></div><div class="col-md-3"><label class="form-label" for="weight-' + row.dataset.index + '">Estimated kg <span class="text-danger">*</span></label><input class="form-control weight-input" id="weight-' + row.dataset.index + '" name="estimated_weight[]" type="number" min="0.01" step="0.01" required placeholder="0.00"></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-material" aria-label="Remove material row"><i class="bi bi-trash"></i><span class="d-md-none ms-1">Remove</span></button></div>';
        rows.appendChild(row);
        refreshMaterialChoices();
        row.querySelectorAll('.material-select, .weight-input').forEach(function (input) {
            input.addEventListener('input', updateEstimateSummary);
            input.addEventListener('change', updateEstimateSummary);
        });
        updateEstimateSummary();
    }

    function refreshMaterialChoices() {
        const selected = Array.from(document.querySelectorAll('.material-select')).map(select => select.value).filter(Boolean);
        document.querySelectorAll('.material-select').forEach(select => {
            select.querySelectorAll('option').forEach(option => { option.disabled = option.value && selected.includes(option.value) && option.value !== select.value; });
        });
    }

    document.getElementById('add-material-row').addEventListener('click', addRow);
    rows.addEventListener('change', function () { refreshMaterialChoices(); updateEstimateSummary(); });
    rows.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-material');
        if (!button) return;
        if (document.querySelectorAll('.material-row').length === 1) { showStatus('At least one recyclable material is required.', false, []); return; }
        button.closest('.material-row').remove();
        refreshMaterialChoices();
        updateEstimateSummary();
    });

    const dateField = document.getElementById('preferred_pickup_date');
    const today = new Date();
    const localToday = new Date(today.getTime() - today.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    dateField.min = localToday;

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        status.classList.add('d-none');
        if (!form.checkValidity()) { form.classList.add('was-validated'); showStatus('Please correct the highlighted fields before submitting.', false, []); return; }
        const selected = Array.from(document.querySelectorAll('.material-select')).map(select => select.value).filter(Boolean);
        if (selected.length !== new Set(selected).size) { showStatus('Please choose each recyclable material only once.', false, []); return; }
        if (new Date(dateField.value + 'T00:00:00') < new Date(localToday + 'T00:00:00')) { showStatus('Preferred pickup date cannot be in the past.', false, []); return; }
        confirmation.open('Are you sure you want to submit this pickup request?', async function () {
            try {
                const response = await fetch(apiUrl, { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
                const payload = await response.json();
                if (payload.session_expired && payload.redirect) { window.location.href = payload.redirect; return true; }
                if (!response.ok || !payload.success) { showStatus(payload.message || 'Please correct the form.', false, payload.validation_errors || []); return false; }
                form.reset();
                rows.innerHTML = '';
                addRow();
                document.getElementById('pickup-request-success-message').textContent = 'Pickup request submitted successfully! Reference: ' + payload.data.request.booking_reference;
                successModal.show();
                return true;
            } catch (error) {
                showStatus('Unable to submit the pickup request right now.', false, []);
                return false;
            }
        });
    });
    successOkButton.addEventListener('click', function () {
        window.location.href = '<?php echo APP_URL; ?>/user-junkshop/current-bookings.php';
    });
    addRow();
});
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
