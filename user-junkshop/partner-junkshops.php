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
$defaultPickupFee = (float)($feeConfigs['default_pickup_fee'] ?? FeeCalculator::DEFAULT_PICKUP_FEE);
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
                    <div class="col-lg-6 seller-junkshop-card" data-junkshop-name="<?php echo Validator::escape(strtolower((string)($junkshop['business_name'] ?? ''))); ?>" data-is-preferred="<?php echo !empty($junkshop['is_preferred']) ? '1' : '0'; ?>">
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
                                        <button type="button" class="btn btn-primary request-pickup-btn" data-junkshop-id="<?php echo (int)($junkshop['junkshop_account_id'] ?? 0); ?>">
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
                                    <span>Estimated recyclable value</span>
                                    <span class="fw-semibold" id="calc-estimated-recyclable-value">₱0.00</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center gap-3">
                                    <span>Pickup / Collection fee</span>
                                    <span class="text-danger" id="calc-pickup-fee">- ₱0.00</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center gap-3">
                                    <span>Ecopick service fee</span>
                                    <span class="text-danger" id="calc-service-fee">- ₱0.00</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center gap-3 border-top pt-2 mt-1">
                                    <strong>Estimated net amount to receive</strong>
                                    <strong id="calc-estimated-net-amount">₱0.00</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><label class="form-label" for="pickup_address">Complete address <span class="text-danger">*</span></label><div class="input-group"><input class="form-control" id="pickup_address" name="pickup_address" required maxlength="255" readonly placeholder="Use Get Current Location"><button type="button" class="btn btn-primary" id="btn-get-location">Get Current Location</button></div><input type="hidden" id="seller_lat" name="seller_lat"><input type="hidden" id="seller_lng" name="seller_lng"></div>
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
        const nameFilter = document.getElementById('filter-junkshop-name');
        const materialFilter = document.getElementById('filter-material');
        const cards = Array.from(document.querySelectorAll('.seller-junkshop-card'));
        const form = document.getElementById('pickup-request-form');
        const rows = document.getElementById('material-rows');
        const status = document.getElementById('pickup-request-form-status');
        const hiddenJunkshopId = document.getElementById('selected-junkshop-id');
        const modalEl = document.getElementById('sellerPickupRequestModal');
        const pickupModal = bootstrap.Modal.getOrCreateInstance(modalEl);
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
        const defaultPickupFee = <?php echo json_encode($defaultPickupFee); ?>;
        const serviceFeePct = <?php echo json_encode($serviceFeePct); ?>;
        const apiUrl = '<?php echo APP_URL; ?>/user-junkshop/api/pickup-requests.php';
        const preferredApiUrl = '<?php echo APP_URL; ?>/user-junkshop/api/toggle_preferred_junkshop.php';
        const csrfToken = '<?php echo CSRF::token(); ?>';
        const getLocationButton = document.getElementById('btn-get-location');
        const pickupAddress = document.getElementById('pickup_address');
        const sellerLat = document.getElementById('seller_lat');
        const sellerLng = document.getElementById('seller_lng');
        const pickupMapElement = document.getElementById('pickup-map');
        let rowIndex = 0;
        let selectedPrices = {};

        function updatePickupAddress(lat, lng) {
            return fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng) + '&zoom=18&addressdetails=1', { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    const address = data.address || {};
                    const parts = [address.house_number, address.road, address.city || address.town || address.municipality, address.province]
                        .filter(function (part, index, values) { return part && values.indexOf(part) === index; });
                    pickupAddress.value = parts.join(', ') || data.display_name || '';
                });
        }

        function initializePickupMap(lat, lng) {
            pickupMapElement.style.display = 'block';
            if (!pickupMap) {
                pickupMap = L.map('pickup-map').setView([lat, lng], 18);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(pickupMap);
            } else {
                pickupMap.setView([lat, lng], 18);
            }
            if (pickupMarker) pickupMarker.setLatLng([lat, lng]);
            else {
                pickupMarker = L.marker([lat, lng], { draggable: true }).addTo(pickupMap);
                pickupMarker.on('dragend', function () {
                    const position = pickupMarker.getLatLng();
                    sellerLat.value = position.lat;
                    sellerLng.value = position.lng;
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
            if (!window.isSecureContext) {
                alert('Geolocation requires a secure HTTPS connection on mobile devices.');
            } else if (error.code === 1) {
                alert('Location permission was denied. Please allow location access and try again.');
            } else if (error.code === 2 || error.code === 3) {
                alert('Unable to get your location. Please enable GPS and try again.');
            } else {
                alert('Unable to get your current location. Please try again.');
            }
            resetLocationButton();
        }

        getLocationButton?.addEventListener('click', function () {
            if (!window.isSecureContext) {
                handleLocationError({ code: 0 });
                return;
            }
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by this browser.');
                resetLocationButton();
                return;
            }
            getLocationButton.disabled = true;
            getLocationButton.textContent = 'Locating...';
            navigator.geolocation.getCurrentPosition(function (position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                sellerLat.value = lat;
                sellerLng.value = lng;
                initializePickupMap(lat, lng);
                updatePickupAddress(lat, lng).catch(function () {}).finally(resetLocationButton);
            }, handleLocationError, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
        });

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
            let recyclableValue = 0;
            document.querySelectorAll('.material-row').forEach(function (row) {
                const select = row.querySelector('.material-select');
                const weightInput = row.querySelector('.weight-input');
                if (!select || !weightInput) return;
                const materialId = Number(select.value || 0);
                const weight = Number(weightInput.value || 0);
                const price = materialId && selectedPrices[materialId] ? Number(selectedPrices[materialId]) : 0;
                recyclableValue += weight * price;
            });

            const serviceFee = recyclableValue * (Number(serviceFeePct || 0) / 100);
            const netAmount = recyclableValue - Number(defaultPickupFee || 0) - serviceFee;
            document.getElementById('calc-estimated-recyclable-value').textContent = formatMoney(recyclableValue);
            document.getElementById('calc-pickup-fee').textContent = '- ' + formatMoney(Number(defaultPickupFee || 0));
            document.getElementById('calc-service-fee').textContent = '- ' + formatMoney(serviceFee);
            document.getElementById('calc-estimated-net-amount').textContent = formatMoney(netAmount);
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
            row.innerHTML = '<div class="col-md-7"><label class="form-label" for="material-' + row.dataset.index + '">Material <span class="text-danger">*</span></label><select class="form-select material-select" id="material-' + row.dataset.index + '" name="material_id[]" required><option value="">Choose material</option>' + materialOptions.map(function (material) { return '<option value="' + material.id + '">' + material.material_name + ' (' + material.unit_of_measure + ')</option>'; }).join('') + '</select></div><div class="col-md-3"><label class="form-label" for="weight-' + row.dataset.index + '">Estimated kg <span class="text-danger">*</span></label><input class="form-control weight-input" id="weight-' + row.dataset.index + '" name="estimated_weight[]" type="number" min="0.01" step="0.01" required placeholder="0.00"></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-material" aria-label="Remove material row"><i class="bi bi-trash"></i></button></div>';
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
                    window.alert(error.message || 'Unable to update preferred junkshop.');
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
                selectedPrices = pricesByJunkshop[selectedJunkshopId] || {};
                rows.innerHTML = '';
                addMaterialRow();
                pickupModal.show();
            });
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

            const selected = Array.from(document.querySelectorAll('.material-select')).map(function (select) { return select.value; }).filter(Boolean);
            if (selected.length !== new Set(selected).size) {
                showStatus('Please choose each recyclable material only once.', false, []);
                return;
            }

            if (!window.confirm('Are you sure you want to submit this pickup request to this junkshop?')) {
                return;
            }

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
