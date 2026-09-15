<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/MaterialPriceController.php';

$timeOptions = ['8:00 AM', '8:30 AM', '9:00 AM', '9:30 AM', '10:00 AM', '10:30 AM', '11:00 AM', '11:30 AM', '12:00 PM', '12:30 PM', '1:00 PM', '1:30 PM', '2:00 PM', '2:30 PM', '3:00 PM', '3:30 PM', '4:00 PM', '4:30 PM', '5:00 PM', '5:30 PM', '6:00 PM'];

if (!Auth::check()) {
    header('Location: ' . APP_URL . '/user-junkshop/login.php');
    exit;
}

if (Auth::userRole() !== 'seller') {
    header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
    exit;
}

$controller = new MaterialPriceController();
$junkshops = $controller->listApprovedJunkshopsWithPrices(Auth::userId());
$materials = $controller->listActiveMaterials();
$feeConfigs = FeeCalculator::getConfigs();
$perKmRate = (float)($feeConfigs['default_pickup_fee'] ?? FeeCalculator::DEFAULT_PICKUP_FEE);
$serviceFeePct = (float)($feeConfigs['ecopick_service_fee_pct'] ?? (FeeCalculator::DEFAULT_SERVICE_FEE_PCT * 100));

$pageTitle = 'Partner Junkshops and Buying Prices';
$currentPage = 'partner-prices';
$userDisplayName = Auth::userName();

$materialMap = [];
foreach ($materials as $material) {
    $materialMap[(int)($material['id'] ?? 0)] = $material;
}

$rowsByJunkshop = [];
foreach ($junkshops as $row) {
    $junkshopId = (int)($row['junkshop_account_id'] ?? 0);
    $rowsByJunkshop[$junkshopId][] = $row;
}

ob_start();
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h4 class="mb-1 fw-bold"><i class="bi bi-shop-window"></i> Partner Junkshops and Buying Prices</h4>
                <p class="text-muted mb-0">Only approved EcoPick partner junkshops are listed here. Final recyclable value depends on actual accepted materials, weight, and condition.</p>
            </div>
            <span class="badge bg-success-subtle text-success"><?php echo count($rowsByJunkshop); ?> partners</span>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label" for="filter-junkshop-name">Filter by Junkshop Name</label>
                <input type="text" class="form-control" id="filter-junkshop-name" placeholder="Search by business name">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="filter-material">Filter by Material</label>
                <select class="form-select" id="filter-material">
                    <option value="">All materials</option>
                    <?php foreach ($materials as $material): ?>
                        <option value="<?php echo Validator::escape($material['material_name'] ?? ''); ?>"><?php echo Validator::escape($material['material_name'] ?? ''); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if (empty($rowsByJunkshop)): ?>
            <div class="empty-state">
                <div class="display-6 text-muted"><i class="bi bi-shop"></i></div>
                <h5 class="mt-3 mb-2 fw-bold">No approved partner junkshops yet</h5>
                <p class="text-muted mb-0">Approved EcoPick partner junkshops and their live buying prices will appear here once they add accepted materials.</p>
            </div>
        <?php else: ?>
            <div id="seller-price-list" class="row g-4">
                <?php foreach ($rowsByJunkshop as $junkshopId => $rows): ?>
                    <?php $junkshop = $rows[0]; ?>
                    <div class="col-lg-6 seller-junkshop-card" data-junkshop-id="<?php echo (int)($junkshop['junkshop_account_id'] ?? 0); ?>" data-junkshop-name="<?php echo Validator::escape(strtolower((string)($junkshop['business_name'] ?? ''))); ?>" data-is-preferred="<?php echo !empty($junkshop['is_preferred']) ? '1' : '0'; ?>">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body p-4 position-relative">
                                <button type="button" class="btn btn-link p-0 position-absolute top-0 start-0 m-3 preferred-star" data-junkshop-id="<?php echo (int)($junkshop['junkshop_account_id'] ?? 0); ?>" aria-label="<?php echo !empty($junkshop['is_preferred']) ? 'Remove preferred junkshop' : 'Set as preferred junkshop'; ?>" title="<?php echo !empty($junkshop['is_preferred']) ? 'Remove preferred junkshop' : 'Set as preferred junkshop'; ?>">
                                    <i class="bi <?php echo !empty($junkshop['is_preferred']) ? 'bi-star-fill text-warning' : 'bi-star text-muted'; ?> preferred-star-icon"></i>
                                </button>
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                    <div>
                                        <h5 class="fw-bold mb-1"><?php echo Validator::escape($junkshop['business_name'] ?? ''); ?></h5>
                                        <div class="small text-muted"><?php echo Validator::escape($junkshop['location'] ?? ''); ?></div>
                                    </div>
                                    <span class="badge bg-success-subtle text-success">Approved</span>
                                </div>

                                <div class="mb-3 small text-muted">
                                    <div><strong>Contact:</strong> <?php echo Validator::escape($junkshop['contact_person'] ?? ''); ?></div>
                                    <div><strong>Operating hours:</strong> <?php echo Validator::escape($junkshop['operating_schedule'] ?? ''); ?></div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Material</th>
                                                <th>Category</th>
                                                <th class="text-end">Buying Price</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $hasPriceRows = false; foreach ($rows as $row): ?>
                                                <?php if (!empty($row['material_name'])): ?>
                                                    <?php $hasPriceRows = true; ?>
                                                    <tr data-material-name="<?php echo Validator::escape(strtolower((string)($row['material_name'] ?? ''))); ?>">
                                                        <td><?php echo Validator::escape($row['material_name'] ?? ''); ?></td>
                                                        <td><?php echo Validator::escape($row['category'] ?? ''); ?></td>
                                                        <td class="text-end fw-semibold">₱<?php echo number_format((float)($row['buying_price'] ?? 0), 2); ?> / <?php echo Validator::escape($row['unit_of_measure'] ?? 'kg'); ?></td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <?php if (!$hasPriceRows): ?>
                                                <tr>
                                                    <td colspan="3" class="text-muted fst-italic text-center py-3">Prices not yet listed</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-end align-items-center gap-2 mt-3">
                                    <button type="button" class="btn <?php echo !empty($junkshop['is_preferred']) ? 'btn-outline-secondary' : 'btn-outline-primary'; ?> toggle-preferred" data-junkshop-id="<?php echo (int)($junkshop['junkshop_account_id'] ?? 0); ?>" data-is-preferred="<?php echo !empty($junkshop['is_preferred']) ? '1' : '0'; ?>">
                                        <?php echo !empty($junkshop['is_preferred']) ? 'Remove Preferred' : 'Set as Preferred'; ?>
                                    </button>
                                    <?php if (!empty($junkshop['has_active_request'])): ?>
                                        <button type="button" class="btn btn-secondary disabled" data-junkshop-id="<?php echo (int)($junkshop['junkshop_account_id'] ?? 0); ?>" disabled>
                                            <i class="bi bi-clock-history"></i> Request Pending
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-primary request-pickup-btn" data-junkshop-id="<?php echo (int)($junkshop['junkshop_account_id'] ?? 0); ?>" data-junkshop-lat="<?php echo htmlspecialchars((string)($junkshop['latitude'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-junkshop-lng="<?php echo htmlspecialchars((string)($junkshop['longitude'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-junkshop-address="<?php echo htmlspecialchars((string)($junkshop['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="bi bi-plus-circle"></i> Request Pickup
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<div class="modal fade" id="sellerPickupRequestModal" tabindex="-1" aria-labelledby="sellerPickupRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sellerPickupRequestModalLabel">Request Pickup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="pickup-request-form-status" class="alert d-none" role="alert" aria-live="polite"></div>
                <form id="pickup-request-form" enctype="multipart/form-data" novalidate>
                    <?php echo CSRF::field(); ?>
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="junkshop_id" id="selected-junkshop-id" value="0">
                    <label class="form-label" for="junkshop_id">Selected Junkshop</label>
                    <select class="form-select mb-4" id="junkshop_id">
                        <option value="">Choose a junkshop</option>
                        <?php foreach ($rowsByJunkshop as $junkshopRows): ?>
                            <?php $junkshopOption = $junkshopRows[0]; ?>
                            <option value="<?php echo (int)($junkshopOption['junkshop_account_id'] ?? 0); ?>" data-lat="<?php echo htmlspecialchars((string)($junkshopOption['latitude'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-lng="<?php echo htmlspecialchars((string)($junkshopOption['longitude'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-address="<?php echo htmlspecialchars((string)($junkshopOption['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)($junkshopOption['business_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small id="pickup-fee-note" class="text-muted d-block mt-1">Note: Pickup fee rate is ₱<?php echo number_format($perKmRate, 2); ?> per km based on active system configuration.</small>

                    <div class="mb-4">
                        <h6 class="fw-bold mb-2">Materials</h6>
                        <div id="material-rows"></div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-material-row">
                            <i class="bi bi-plus-lg"></i> Add material
                        </button>
                    </div>

                    <div class="card border-0 bg-light-subtle mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0">Estimated calculation</h6>
                                <span class="badge bg-primary-subtle text-primary">Live estimate</span>
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <div class="d-flex justify-content-between align-items-center gap-3">
                                    <span>Material Total</span>
                                    <span class="fw-semibold" id="calc-material-total">₱0.00</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center gap-3">
                                    <span>Pickup / Collection fee</span>
                                    <span class="text-danger" id="calc-pickup-fee">- ₱0.00</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center gap-3">
                                    <span>EcoPick service fee</span>
                                    <span class="text-danger" id="calc-service-fee">- ₱0.00</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center gap-3 border-top pt-2 mt-1">
                                    <strong>Estimated Total Payout</strong>
                                    <strong id="calc-estimated-total">₱0.00</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><label class="form-label" for="pickup_address">Address <span class="text-danger">*</span></label><div class="input-group"><input class="form-control" id="pickup_address" name="pickup_address" required maxlength="255" readonly placeholder="Use Get Current Location"><button type="button" class="btn btn-primary" id="btn-get-location">Get Current Location</button></div><input type="hidden" id="seller_lat" name="seller_lat"><input type="hidden" id="seller_lng" name="seller_lng"><div id="location-error-note" class="alert alert-warning d-none mt-2" role="alert"></div><div id="junkshop-location-info" class="mt-2 text-muted"></div><div id="approx-distance-container" class="fw-bold text-primary mt-1"></div></div>
                        <div class="col-12"><div id="pickup-map" style="height: 250px; width: 100%; display: none; margin-bottom: 15px; z-index: 1; touch-action: none;"></div></div>
                        <div class="col-md-4"><label class="form-label" for="approximate_distance_km">Approximate distance (km) <span class="text-danger">*</span></label><input type="number" min="0" max="15" step="0.01" class="form-control" id="approximate_distance_km" name="approximate_distance_km" required placeholder="Example: 4.50"></div>
                        <div class="col-md-4"><label class="form-label" for="preferred_pickup_date">Preferred pickup date <span class="text-danger">*</span></label><input type="date" class="form-control" id="preferred_pickup_date" name="preferred_pickup_date" required></div>
                        <div class="col-md-4"><label class="form-label" for="preferred_pickup_time">Preferred pickup time <span class="text-danger">*</span></label><select class="form-select" id="preferred_pickup_time" name="preferred_pickup_time" required><option value="">Choose a preferred time</option><?php foreach ($timeOptions as $time): ?><option value="<?php echo Validator::escape($time); ?>"><?php echo Validator::escape($time); ?></option><?php endforeach; ?></select></div>
                        <div class="col-12"><label class="form-label" for="photo">Optional recyclable-material photo</label><input type="file" class="form-control" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><div class="form-text">JPG, JPEG, PNG, or WEBP only; maximum 5 MB.</div></div>
                        <div class="col-12"><label class="form-label" for="notes">Optional notes</label><textarea class="form-control" id="notes" name="notes" rows="4" maxlength="2000" placeholder="Add useful access or material details."></textarea></div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit pickup request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="submitPickupConfirmModal" tabindex="-1" aria-labelledby="submitPickupConfirmModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="submitPickupConfirmModalLabel">Confirm Pickup Request</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p>Are you sure you want to send this request?</p><dl class="row mb-0 small"><dt class="col-6">Junkshop</dt><dd class="col-6 text-end" id="confirm-pickup-junkshop">-</dd><dt class="col-6">Measured distance</dt><dd class="col-6 text-end" id="confirm-pickup-distance">-</dd><dt class="col-6">Pickup fee</dt><dd class="col-6 text-end" id="confirm-pickup-fee">-</dd><dt class="col-6">Service fee</dt><dd class="col-6 text-end" id="confirm-pickup-service-fee">-</dd><dt class="col-6 fw-bold">Net estimated payout</dt><dd class="col-6 text-end fw-bold" id="confirm-pickup-net">-</dd></dl></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Edit Request</button><button type="button" class="btn btn-primary" id="btn-confirm-submit-pickup">Yes, Submit Request</button></div></div></div></div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="pickupRequestToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" data-toast-message>Pickup request submitted successfully.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    let pickupMap = null;
    let pickupMarker = null;

    document.addEventListener('DOMContentLoaded', function () {
        const highPrecisionGeoOptions = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        };
        const nameFilter = document.getElementById('filter-junkshop-name');
        const materialFilter = document.getElementById('filter-material');
        const cards = Array.from(document.querySelectorAll('.seller-junkshop-card'));
        const form = document.getElementById('pickup-request-form');
        const rows = document.getElementById('material-rows');
        const status = document.getElementById('pickup-request-form-status');
        const hiddenJunkshopId = document.getElementById('selected-junkshop-id');
        const junkshopSelect = document.getElementById('junkshop_id');
        const modalEl = document.getElementById('sellerPickupRequestModal');
        const pickupModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const submitConfirmModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('submitPickupConfirmModal'));
        let pickupSubmissionConfirmed = false;
        const successToast = bootstrap.Toast.getOrCreateInstance(document.getElementById('pickupRequestToast'));
        const materialOptions = <?php echo json_encode($materials, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const pricesByJunkshop = <?php
            $pricesByJunkshop = [];
            foreach ($rowsByJunkshop as $junkshopId => $junkshopRows) {
                $pricesByJunkshop[$junkshopId] = [];
                foreach ($junkshopRows as $priceRow) {
                    if (!empty($priceRow['material_id']) && isset($priceRow['buying_price'])) {
                        $pricesByJunkshop[$junkshopId][(int)$priceRow['material_id']] = (float)$priceRow['buying_price'];
                    }
                }
            }
            echo json_encode($pricesByJunkshop, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>;
        const perKmRate = Number(<?php echo json_encode($perKmRate); ?>);
        const serviceFeePct = Number(<?php echo json_encode($serviceFeePct); ?>);
        const apiUrl = '<?php echo APP_URL; ?>/user-junkshop/api/pickup-requests.php';
        const preferredApiUrl = '<?php echo APP_URL; ?>/user-junkshop/api/toggle_preferred_junkshop.php';
        const materialsApiUrl = '<?php echo APP_URL; ?>/user-junkshop/api/get_junkshop_materials.php';
        const csrfToken = '<?php echo CSRF::token(); ?>';
        const getLocationButton = document.getElementById('btn-get-location');
        const pickupAddress = document.getElementById('pickup_address');
        const sellerLatInput = document.getElementById('seller_lat');
        const sellerLngInput = document.getElementById('seller_lng');
        const pickupMapElement = document.getElementById('pickup-map');
        const junkshopLocationInfo = document.getElementById('junkshop-location-info');
        const approximateDistanceInfo = document.getElementById('approx-distance-container');
        const approximateDistanceInput = document.getElementById('approximate_distance_km');
        const locationErrorNote = document.getElementById('location-error-note');
        const locationErrorMessage = '<strong>Location Access Required:</strong> Please ensure your device GPS is turned ON in settings and location permissions are ALLOWED for this website in your browser settings. Once enabled, reload the page to view your location and exact distance.';
        let rowIndex = 0;
        let selectedPrices = {};
        let distanceInKm = 0;

        function showLocationError() {
            if (!locationErrorNote) return;
            locationErrorNote.innerHTML = locationErrorMessage;
            locationErrorNote.classList.remove('d-none');
        }

        function hideLocationError() {
            locationErrorNote?.classList.add('d-none');
        }

        function calculateHaversineDistance(sellerLatitude, sellerLongitude, junkshopLatitude, junkshopLongitude) {
            const R = 6371;
            const dLat = (junkshopLatitude - sellerLatitude) * Math.PI / 180;
            const dLng = (junkshopLongitude - sellerLongitude) * Math.PI / 180;
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
                + Math.cos(sellerLatitude * Math.PI / 180) * Math.cos(junkshopLatitude * Math.PI / 180)
                * Math.sin(dLng / 2) * Math.sin(dLng / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        function calculateDistance() {
            const sellerLat = parseFloat(sellerLatInput.value);
            const sellerLng = parseFloat(sellerLngInput.value);
            const selectedOption = junkshopSelect.options[junkshopSelect.selectedIndex];
            const junkLat = parseFloat(selectedOption.getAttribute('data-lat'));
            const junkLng = parseFloat(selectedOption.getAttribute('data-lng'));

            if (!isNaN(sellerLat) && !isNaN(sellerLng) && Number.isFinite(junkLat) && Number.isFinite(junkLng) && junkLat !== 0 && junkLng !== 0) {
                distanceInKm = Number(calculateHaversineDistance(sellerLat, sellerLng, junkLat, junkLng).toFixed(2));
                approximateDistanceInfo.classList.remove('text-warning');
                approximateDistanceInfo.textContent = 'Approximate Distance: ' + distanceInKm.toFixed(2) + ' km';
                approximateDistanceInput.value = distanceInKm.toFixed(2);
                updateEstimateSummary();
                return;
            }

            approximateDistanceInfo.classList.add('text-warning');
            approximateDistanceInfo.textContent = Number.isFinite(junkLat) && Number.isFinite(junkLng) && junkLat !== 0 && junkLng !== 0
                ? 'Set your pickup location to calculate distance.'
                : "Junkshop hasn't saved a profile location yet.";
            approximateDistanceInput.value = '';
            distanceInKm = 0;
            updateEstimateSummary();
        }

        function selectJunkshopLocation(button) {
            if (junkshopSelect) {
                const selectedJunkshopId = button.dataset.junkshopId || '';
                const selectedOption = Array.from(junkshopSelect.options).find(function (option) {
                    return option.value === selectedJunkshopId;
                });
                if (selectedOption) {
                    selectedOption.selected = true;
                    junkshopLocationInfo.textContent = 'Junkshop location: ' + (selectedOption.getAttribute('data-address') || 'Address unavailable');
                }
            } else {
                junkshopLocationInfo.textContent = 'Junkshop location: ' + (button.dataset.junkshopAddress || 'Address unavailable');
            }
            calculateDistance();
        }

        function updatePickupAddress(lat, lng) {
            return fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`, { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    const address = data.address || {};
                    const road = address.road || address.pedestrian || address.street;
                    const barangay = address.village || address.suburb || address.neighbourhood || address.quarter;
                    const city = address.city || address.town || address.municipality || address.city_district;
                    const province = address.state || address.region;
                    const completeAddress = [road, barangay, city, province].filter(Boolean).join(', ');
                    pickupAddress.value = completeAddress || data.display_name || '';
                });
        }

        function initializePickupMap(lat, lng) {
            pickupMapElement.style.display = 'block';
            if (!pickupMap) {
                pickupMap = L.map('pickup-map').setView([lat, lng], 18);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(pickupMap);
            }
            if (pickupMarker) pickupMarker.setLatLng([lat, lng]);
            else {
                pickupMarker = L.marker([lat, lng], { draggable: true }).addTo(pickupMap);
                pickupMarker.on('dragend', function () {
                    const position = pickupMarker.getLatLng();
                    sellerLatInput.value = position.lat;
                    sellerLngInput.value = position.lng;
                    calculateDistance();
                    updatePickupAddress(position.lat, position.lng).catch(function () {});
                });
            }
            pickupMap.invalidateSize();
        }

        modalEl.addEventListener('shown.bs.modal', function () {
            if (pickupMap) pickupMap.invalidateSize();
        });

        function resetLocationButton() {
            getLocationButton.disabled = false;
            getLocationButton.textContent = 'Get Current Location';
        }

        function handleLocationError(error) {
            showLocationError();
            resetLocationButton();
        }

        function updateCurrentLocation() {
            if (!navigator.geolocation) {
                showLocationError();
                resetLocationButton();
                return;
            }
            navigator.geolocation.getCurrentPosition(function (position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                sellerLatInput.value = lat;
                sellerLngInput.value = lng;
                hideLocationError();
                calculateDistance();
                initializePickupMap(lat, lng);
                updatePickupAddress(lat, lng).catch(function () {}).finally(resetLocationButton);
            }, handleLocationError, highPrecisionGeoOptions);
        }

        function initAutoLocation() {
            updateCurrentLocation();
        }

        getLocationButton?.addEventListener('click', function () {
            getLocationButton.disabled = true;
            getLocationButton.textContent = 'Locating...';
            updateCurrentLocation();
        });

        initAutoLocation();

        function sortPreferredCards() {
            const list = document.getElementById('seller-price-list');
            Array.from(list.querySelectorAll('.seller-junkshop-card'))
                .sort(function (first, second) {
                    const preferredDifference = Number(second.dataset.isPreferred || 0) - Number(first.dataset.isPreferred || 0);
                    return preferredDifference || (first.dataset.junkshopName || '').localeCompare(second.dataset.junkshopName || '');
                })
                .forEach(function (card) { list.appendChild(card); });
        }

        function updatePreferredCard(button, isPreferred) {
            const card = button.closest('.seller-junkshop-card');
            const star = card.querySelector('.preferred-star');
            const icon = card.querySelector('.preferred-star-icon');
            const label = isPreferred ? 'Remove Preferred' : 'Set as Preferred';
            card.dataset.isPreferred = isPreferred ? '1' : '0';
            button.dataset.isPreferred = isPreferred ? '1' : '0';
            button.classList.toggle('btn-outline-secondary', isPreferred);
            button.classList.toggle('btn-outline-primary', !isPreferred);
            button.textContent = label;
            icon.className = 'bi ' + (isPreferred ? 'bi-star-fill text-warning' : 'bi-star text-muted') + ' preferred-star-icon';
            star.setAttribute('aria-label', isPreferred ? 'Remove preferred junkshop' : 'Set as preferred junkshop');
            star.title = isPreferred ? 'Remove preferred junkshop' : 'Set as preferred junkshop';
        }

        function applySellerFilters() {
            const nameValue = (nameFilter?.value || '').toLowerCase().trim();
            const materialValue = (materialFilter?.value || '').toLowerCase().trim();

            cards.forEach(function (card) {
                const businessName = (card.dataset.junkshopName || '').toLowerCase();
                const rowsInCard = card.querySelectorAll('[data-material-name]');
                let visible = true;

                if (nameValue && !businessName.includes(nameValue)) {
                    visible = false;
                }

                if (materialValue) {
                    const matchMaterial = Array.from(rowsInCard).some(function (row) {
                        return (row.dataset.materialName || '').includes(materialValue);
                    });
                    if (!matchMaterial) {
                        visible = false;
                    }
                }

                card.style.display = visible ? '' : 'none';
            });
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>'"]/g, function (character) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'}[character];
            });
        }

        function renderLatestMaterials(card, materials) {
            const tableBody = card.querySelector('table tbody');
            if (!tableBody) return;

            if (!materials.length) {
                tableBody.innerHTML = '<tr><td colspan="3" class="text-muted fst-italic text-center py-3">No materials listed yet</td></tr>';
                return;
            }

            tableBody.innerHTML = materials.map(function (material) {
                const materialName = escapeHtml(material.material_name);
                return '<tr data-material-name="' + escapeHtml(String(material.material_name || '').toLowerCase()) + '">' +
                    '<td>' + materialName + '</td>' +
                    '<td>' + escapeHtml(material.category) + '</td>' +
                    '<td class="text-end fw-semibold">' + formatMoney(material.buying_price) + ' / ' + escapeHtml(material.unit_of_measure || 'kg') + '</td>' +
                '</tr>';
            }).join('');

            pricesByJunkshop[card.dataset.junkshopId] = materials.reduce(function (prices, material) {
                prices[Number(material.material_id)] = Number(material.buying_price || 0);
                return prices;
            }, {});
            applySellerFilters();
        }

        function fetchLatestMaterials(junkshopId) {
            const card = cards.find(function (candidate) {
                return candidate.dataset.junkshopId === String(junkshopId);
            });
            if (!card) return Promise.resolve();

            return fetch(materialsApiUrl + '?junkshop_id=' + encodeURIComponent(junkshopId), {
                credentials: 'same-origin',
                cache: 'no-store'
            })
                .then(function (response) {
                    return response.json().then(function (payload) {
                        if (!response.ok || !payload.success) {
                            throw new Error(payload.message || 'Unable to load materials.');
                        }
                        return payload.materials || [];
                    });
                })
                .then(function (materials) {
                    renderLatestMaterials(card, materials);
                })
                .catch(function (error) {
                    console.error('Failed to refresh junkshop materials:', error);
                });
        }

        const activeJunkshops = new Set();
        const junkshopVisibility = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                const junkshopId = entry.target.dataset.junkshopId;
                if (entry.isIntersecting) {
                    activeJunkshops.add(junkshopId);
                    fetchLatestMaterials(junkshopId);
                } else {
                    activeJunkshops.delete(junkshopId);
                }
            });
        }, { threshold: 0.1 });

        cards.forEach(function (card) { junkshopVisibility.observe(card); });
        setInterval(function () {
            activeJunkshops.forEach(function (junkshopId) { fetchLatestMaterials(junkshopId); });
        }, 5000);

        function formatMoney(value) {
            return '₱' + Number(value || 0).toFixed(2);
        }

        function showStatus(message, success, errors) {
            status.className = 'alert ' + (success ? 'alert-success' : 'alert-danger');
            status.innerHTML = message + (errors && errors.length ? '<ul class="mb-0 mt-2">' + errors.map(function (error) { return '<li>' + String(error) + '</li>'; }).join('') + '</ul>' : '');
            status.classList.remove('d-none');
        }

        function refreshMaterialChoices() {
            const selected = Array.from(document.querySelectorAll('.material-select')).map(function (select) { return select.value; }).filter(Boolean);
            document.querySelectorAll('.material-select').forEach(function (select) {
                select.querySelectorAll('option').forEach(function (option) {
                    option.disabled = !!(option.value && selected.includes(option.value) && option.value !== select.value);
                });
            });
        }

        function updateEstimateSummary() {
            let materialTotal = 0;
            document.querySelectorAll('.material-row').forEach(function (row) {
                const select = row.querySelector('.material-select');
                const weightInput = row.querySelector('.weight-input');
                if (!select || !weightInput) return;
                const materialId = Number(select.value || 0);
                const weight = Number(weightInput.value || 0);
                const price = materialId && selectedPrices[materialId] ? Number(selectedPrices[materialId]) : 0;
                materialTotal += weight * price;
            });

            const wholeKm = Math.floor(distanceInKm);
            const pickupFee = wholeKm * perKmRate;
            const serviceFee = materialTotal * (serviceFeePct / 100);
            const estimatedTotal = materialTotal - pickupFee - serviceFee;
            document.getElementById('calc-material-total').textContent = formatMoney(materialTotal);
            document.getElementById('calc-pickup-fee').textContent = '- ' + formatMoney(pickupFee);
            document.getElementById('calc-service-fee').textContent = '- ' + formatMoney(serviceFee);
            document.getElementById('calc-estimated-total').textContent = formatMoney(estimatedTotal);
        }

        function markJunkshopRequestPending(junkshopId) {
            const button = document.querySelector('button.request-pickup-btn[data-junkshop-id="' + CSS.escape(String(junkshopId)) + '"]');
            if (!button) return;

            button.classList.remove('btn-primary', 'request-pickup-btn');
            button.classList.add('btn-secondary', 'disabled');
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-clock-history"></i> Request Pending';
        }

        function addMaterialRow() {
            const row = document.createElement('div');
            row.className = 'row g-2 align-items-end mb-3 material-row';
            row.dataset.index = String(rowIndex++);
            row.innerHTML = '<div class="col-md-7"><label class="form-label" for="material-' + row.dataset.index + '">Material <span class="text-danger">*</span></label><select class="form-select material-select" id="material-' + row.dataset.index + '" name="material_id[]" required><option value="">Choose material</option>' + materialOptions.map(function (material) { return '<option value="' + material.id + '">' + material.material_name + ' (' + material.unit_of_measure + ')</option>'; }).join('') + '</select></div><div class="col-md-3"><label class="form-label" for="weight-' + row.dataset.index + '">Estimated kg <span class="text-danger">*</span></label><input class="form-control weight-input" id="weight-' + row.dataset.index + '" name="estimated_weight[]" type="number" min="3" step="0.01" required placeholder="0.00"></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-material" aria-label="Remove material row"><i class="bi bi-trash"></i></button></div>';
            rows.appendChild(row);
            refreshMaterialChoices();
            row.querySelectorAll('.material-select, .weight-input').forEach(function (input) {
                input.addEventListener('input', updateEstimateSummary);
                input.addEventListener('change', updateEstimateSummary);
            });
            updateEstimateSummary();
        }

        nameFilter?.addEventListener('input', applySellerFilters);
        materialFilter?.addEventListener('change', applySellerFilters);

        document.querySelectorAll('.toggle-preferred').forEach(function (button) {
            button.addEventListener('click', async function () {
                button.disabled = true;
                const formData = new FormData();
                formData.append('_csrf_token', csrfToken);
                formData.append('junkshop_id', button.dataset.junkshopId || '0');
                try {
                    const response = await fetch(preferredApiUrl, { method: 'POST', body: formData, credentials: 'same-origin' });
                    const payload = await response.json();
                    if (payload.session_expired && payload.redirect) {
                        window.location.href = payload.redirect;
                        return;
                    }
                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'Unable to update preferred junkshop.');
                    }
                    updatePreferredCard(button, Boolean(payload.is_preferred));
                    sortPreferredCards();
                    applySellerFilters();
                } catch (error) {
                    showStatus(error.message || 'Unable to update preferred junkshop.', false, []);
                } finally {
                    button.disabled = false;
                }
            });
        });

        document.querySelectorAll('.request-pickup-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                const selectedJunkshopId = button.dataset.junkshopId || '0';
                status.classList.add('d-none');
                form.reset();
                hiddenJunkshopId.value = selectedJunkshopId;
                selectJunkshopLocation(button);
                selectedPrices = pricesByJunkshop[selectedJunkshopId] || {};
                rows.innerHTML = '';
                addMaterialRow();
                pickupModal.show();
            });
        });

        junkshopSelect?.addEventListener('change', function () {
            const selectedOption = junkshopSelect.options[junkshopSelect.selectedIndex];
            if (!selectedOption || !junkshopSelect.value) return;
            hiddenJunkshopId.value = junkshopSelect.value;
            selectedPrices = pricesByJunkshop[junkshopSelect.value] || {};
            rows.innerHTML = '';
            addMaterialRow();
            junkshopLocationInfo.textContent = 'Junkshop location: ' + (selectedOption.getAttribute('data-address') || 'Address unavailable');
            calculateDistance();
        });

        document.getElementById('add-material-row').addEventListener('click', addMaterialRow);
        rows.addEventListener('change', function () { refreshMaterialChoices(); updateEstimateSummary(); });
        rows.addEventListener('click', function (event) {
            const button = event.target.closest('.remove-material');
            if (!button) return;
            if (document.querySelectorAll('.material-row').length === 1) {
                showStatus('At least one recyclable material is required.', false, []);
                return;
            }
            button.closest('.material-row').remove();
            refreshMaterialChoices();
            updateEstimateSummary();
        });

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            status.classList.add('d-none');
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                showStatus('Please correct the highlighted fields before submitting.', false, []);
                return;
            }

            const totalEstimatedWeight = Array.from(form.querySelectorAll('.material-row')).reduce(function (total, row) {
                const material = row.querySelector('.material-select');
                const weight = row.querySelector('.weight-input');
                const value = Number(weight?.value);
                return material?.value && Number.isFinite(value) && value > 0 ? total + value : total;
            }, 0);
            if (totalEstimatedWeight < 3) {
                showStatus('The total estimated weight must be at least 3 kg to request a pickup.', false, []);
                return;
            }

            const selected = Array.from(document.querySelectorAll('.material-select')).map(function (select) { return select.value; }).filter(Boolean);
            if (selected.length !== new Set(selected).size) {
                showStatus('Please choose each recyclable material only once.', false, []);
                return;
            }

            if (!pickupSubmissionConfirmed) {
                const selectedOption = junkshopSelect.options[junkshopSelect.selectedIndex];
                document.getElementById('confirm-pickup-junkshop').textContent = selectedOption?.textContent || '-';
                document.getElementById('confirm-pickup-distance').textContent = (document.getElementById('approximate_distance_km').value || '-') + ' km';
                document.getElementById('confirm-pickup-fee').textContent = document.getElementById('calc-pickup-fee').textContent;
                document.getElementById('confirm-pickup-service-fee').textContent = document.getElementById('calc-service-fee').textContent;
                document.getElementById('confirm-pickup-net').textContent = document.getElementById('calc-estimated-total').textContent;
                submitConfirmModal.show();
                return;
            }
            pickupSubmissionConfirmed = false;

            try {
                const submittedJunkshopId = hiddenJunkshopId.value;
                const response = await fetch(apiUrl, { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
                const payload = await response.json();
                if (payload.session_expired && payload.redirect) {
                    window.location.href = payload.redirect;
                    return;
                }
                if (!response.ok || !payload.success) {
                    showStatus(payload.message || 'Please correct the form.', false, payload.validation_errors || []);
                    return;
                }

                markJunkshopRequestPending(submittedJunkshopId);
                const toastMessage = document.querySelector('[data-toast-message]');
                toastMessage.textContent = 'Pickup request submitted successfully for selected partner junkshop.';
                successToast.show();
                form.reset();
                rows.innerHTML = '';
                addMaterialRow();
                pickupModal.hide();
            } catch (error) {
                showStatus('Unable to submit the pickup request right now.', false, []);
            }
        });

        document.getElementById('btn-confirm-submit-pickup').addEventListener('click', function () {
            pickupSubmissionConfirmed = true;
            submitConfirmModal.hide();
            form.requestSubmit();
        });

        const dateField = document.getElementById('preferred_pickup_date');
        const today = new Date();
        const localToday = new Date(today.getTime() - today.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
        dateField.min = localToday;
        addMaterialRow();
    });
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
