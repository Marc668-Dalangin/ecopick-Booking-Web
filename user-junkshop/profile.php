<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/DashboardController.php';

if (!Auth::check()) {
    header('Location: ' . APP_URL . '/user-junkshop/login.php');
    exit;
}

if (Auth::userRole() === 'admin') {
    header('Location: ' . APP_URL . '/admin-private-dnstl/dashboard.php');
    exit;
}

$controller = new DashboardController();
$role = Auth::userRole();
$userId = Auth::userId();
$isExpiredJunkshop = $role === 'junkshop' && !empty($_SESSION['is_expired']);
$errors = [];
$successMessage = '';

if ($role === 'seller') {
    $currentProfile = $controller->getSellerProfile($userId);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
            $errors[] = 'Invalid security token. Please try again.';
        } else {
            $firstName = trim((string)($_POST['first_name'] ?? ''));
            $lastName = trim((string)($_POST['last_name'] ?? ''));
            $fullName = trim($firstName . ' ' . $lastName);
            $mobileNumber = Validator::normalizeMobileNumber(($_POST['mobile_number'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $barangay = trim((string)($_POST['barangay'] ?? ''));

            if (!Validator::required($firstName)) $errors[] = 'First name is required.';
            if (!Validator::required($lastName)) $errors[] = 'Last name is required.';
            if (!Validator::required($fullName)) $errors[] = 'Full name is required.';
            if (!Validator::required($mobileNumber) || !Validator::mobileNumber($mobileNumber)) $errors[] = 'Valid mobile number is required.';
            if (!Validator::required($address)) $errors[] = 'Address is required.';
            if (!Validator::required($barangay)) $errors[] = 'Barangay is required.';

            if (empty($errors)) {
                $result = $controller->updateSellerProfile($userId, $fullName, $mobileNumber, $address, $barangay);
                if ($result['success']) {
                    $_SESSION['flash_message'] = $result['message'];
                    $_SESSION['flash_type'] = 'success';
                    header('Location: ' . APP_URL . '/user-junkshop/profile.php');
                    exit;
                }
                $errors[] = $result['message'];
            }
        }
    }
} else {
    $currentProfile = $controller->getJunkshopProfile($userId);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($isExpiredJunkshop) {
            $errors[] = 'Profile editing is locked because your partnership subscription has expired.';
        } elseif (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
            $errors[] = 'Invalid security token. Please try again.';
        } else {
            $businessName = trim((string)($_POST['business_name'] ?? ''));
            $ownerName = trim((string)($_POST['owner_name'] ?? ''));
            $mobileNumber = Validator::normalizeMobileNumber(($_POST['mobile_number'] ?? ''));
            $address = trim((string)($_POST['complete_address'] ?? ''));
            $schedule = trim((string)($_POST['operating_schedule'] ?? ''));
            $permit = trim((string)($_POST['business_permit_reference'] ?? ''));
            $gcashAccountName = trim((string)($_POST['gcash_account_name'] ?? ''));
            $gcashAccountNumber = preg_replace('/\D+/', '', trim((string)($_POST['gcash_account_number'] ?? '')));
            $latitudeInput = trim((string)($_POST['latitude'] ?? ''));
            $longitudeInput = trim((string)($_POST['longitude'] ?? ''));
            $latitude = $latitudeInput === '' ? null : filter_var($latitudeInput, FILTER_VALIDATE_FLOAT);
            $longitude = $longitudeInput === '' ? null : filter_var($longitudeInput, FILTER_VALIDATE_FLOAT);

            if (!Validator::required($businessName)) $errors[] = 'Business name is required.';
            if (!Validator::required($ownerName)) $errors[] = 'Business owner name is required.';
            if (!Validator::required($mobileNumber) || !Validator::mobileNumber($mobileNumber)) $errors[] = 'Valid mobile number is required.';
            if (!Validator::required($address)) $errors[] = 'Business address is required.';
            if (!Validator::required($schedule)) $errors[] = 'Operating schedule is required.';
            if (!Validator::required($permit)) $errors[] = 'Permit reference is required.';
            if ($gcashAccountName !== '' && strlen($gcashAccountName) > 120) $errors[] = 'GCash account name is too long.';
            if ($gcashAccountNumber !== '' && !preg_match('/^09\d{9}$/', $gcashAccountNumber)) $errors[] = 'GCash account number must be an 11-digit Philippine mobile number.';
            if ($latitudeInput !== '' && ($latitude === false || !is_finite((float) $latitude) || $latitude < -90 || $latitude > 90)) $errors[] = 'Latitude must be between -90 and 90.';
            if ($longitudeInput !== '' && ($longitude === false || !is_finite((float) $longitude) || $longitude < -180 || $longitude > 180)) $errors[] = 'Longitude must be between -180 and 180.';
            if (($latitudeInput === '') !== ($longitudeInput === '')) $errors[] = 'Both latitude and longitude are required for a saved location.';

            if (empty($errors)) {
                $result = $controller->updateJunkshopProfile($userId, $businessName, $ownerName, $mobileNumber, $address, $schedule, $permit, $gcashAccountName, $gcashAccountNumber, $latitude, $longitude);
                if ($result['success']) {
                    $_SESSION['flash_message'] = $result['message'];
                    $_SESSION['flash_type'] = 'success';
                    header('Location: ' . APP_URL . '/user-junkshop/profile.php');
                    exit;
                }
                $errors[] = $result['message'];
            }
        }
    }
}

$profileUpdatedAt = !empty($currentProfile['profile_updated_at']) ? strtotime((string) $currentProfile['profile_updated_at']) : false;
$profileCooldownActive = $profileUpdatedAt !== false && $profileUpdatedAt > strtotime('-7 days');
$profileCooldownUntil = $profileCooldownActive ? $profileUpdatedAt + (7 * 24 * 60 * 60) : null;
$profileFormLocked = $isExpiredJunkshop || $profileCooldownActive;

$pageTitle = 'Profile';
$currentPage = 'profile';
$userDisplayName = Auth::userName();
ob_start();
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-person-circle"></i> Account</h5>
                <div class="text-center mb-3">
                    <div class="profile-avatar profile-avatar-md rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center">
                        <i class="bi bi-person"></i>
                    </div>
                </div>
                <div class="text-center">
                    <div class="fw-semibold"><?php echo Validator::escape(Auth::userName()); ?></div>
                    <div class="text-muted">@<?php echo Validator::escape($currentProfile['username'] ?? ''); ?></div>
                    <div class="text-muted"><?php echo ucfirst(Validator::escape($role)); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-4"><i class="bi bi-pencil-square"></i> Edit Profile</h4>

                <?php if ($isExpiredJunkshop): ?>
                    <div class="alert alert-warning" role="alert">Your profile is read-only while your partnership subscription is expired.</div>
                <?php endif; ?>
                <?php if ($profileCooldownActive): ?>
                    <div class="alert alert-info" role="alert">Profile information edits are locked until <?php echo Validator::escape(date('M d, Y g:i A', $profileCooldownUntil)); ?>. You can update your profile again after the 7-day cooldown. Shop operational status can still be changed.</div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo Validator::escape($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" novalidate>
                    <?php echo CSRF::field(); ?>
                    <fieldset <?php echo $profileFormLocked ? 'disabled' : ''; ?>>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="registered_email">Email Address</label>
                        <input type="email" class="form-control bg-light" id="registered_email" value="<?php echo htmlspecialchars($currentProfile['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly disabled>
                        <small class="text-muted">Registered email addresses cannot be modified directly.</small>
                    </div>

                    <?php if ($role === 'seller'): ?>
                        <?php
                        $sellerFullName = trim((string)($currentProfile['full_name'] ?? Auth::userName()));
                        $sellerNameParts = preg_split('/\s+/', $sellerFullName, 2);
                        $sellerFirstName = $sellerNameParts[0] ?? '';
                        $sellerLastName = $sellerNameParts[1] ?? '';
                        $sellerMobile = preg_replace('/\D+/', '', (string)($currentProfile['mobile_number'] ?? ''));
                        if (preg_match('/^09/', $sellerMobile) === 1) {
                            $sellerMobile = substr($sellerMobile, 2);
                        }
                        ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="first_name">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo Validator::escape($sellerFirstName); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="last_name">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo Validator::escape($sellerLastName); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="mobile_number">Mobile Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">09</span>
                                    <input type="tel" class="form-control" id="mobile_number" name="mobile_number" value="<?php echo Validator::escape($sellerMobile); ?>" required inputmode="numeric" maxlength="9" pattern="[0-9]*" placeholder="123456789">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="address">Address</label>
                                <input type="text" class="form-control" id="address" name="address" value="<?php echo Validator::escape($currentProfile['address'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="barangay">Barangay</label>
                                <input type="text" class="form-control" id="barangay" name="barangay" value="<?php echo Validator::escape($currentProfile['barangay'] ?? ''); ?>" required>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php
                        $junkshopMobile = preg_replace('/\D+/', '', (string)($currentProfile['mobile_number'] ?? ''));
                        if (preg_match('/^09/', $junkshopMobile) === 1) {
                            $junkshopMobile = substr($junkshopMobile, 2);
                        }
                        ?>
                    </fieldset>
                        <div class="card mb-3 border-0 shadow-sm">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <h6 class="mb-0 fw-bold">Shop Operational Status</h6>
                                    <small class="text-muted" id="availability-status-text">
                                        <?php echo ((int) ($currentProfile['is_available'] ?? 1) === 1) ? 'Status: Available (Accepting pickup requests)' : 'Status: Unavailable (Not accepting pickup requests)'; ?>
                                    </small>
                                </div>
                                <div class="form-check form-switch form-switch-lg">
                                    <input class="form-check-input" type="checkbox" role="switch" id="toggleAvailability" <?php echo ((int) ($currentProfile['is_available'] ?? 1) === 1) ? 'checked' : ''; ?> <?php echo $isExpiredJunkshop ? 'disabled' : ''; ?>>
                                    <label class="form-check-label fw-bold ms-2" for="toggleAvailability" id="toggleLabel">
                                        <?php echo ((int) ($currentProfile['is_available'] ?? 1) === 1) ? 'Available' : 'Unavailable'; ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                    <fieldset <?php echo $profileFormLocked ? 'disabled' : ''; ?>>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="business_name">Business Name</label>
                                <input type="text" class="form-control" id="business_name" name="business_name" value="<?php echo Validator::escape($currentProfile['business_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="owner_name">Owner / Contact Person</label>
                                <input type="text" class="form-control" id="owner_name" name="owner_name" value="<?php echo Validator::escape($currentProfile['owner_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="mobile_number">Mobile Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">09</span>
                                    <input type="tel" class="form-control" id="mobile_number" name="mobile_number" value="<?php echo Validator::escape($junkshopMobile); ?>" required inputmode="numeric" maxlength="9" pattern="[0-9]*" placeholder="123456789">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="address">Address</label>
                                <input type="text" class="form-control" id="address" name="complete_address" value="<?php echo Validator::escape($currentProfile['complete_address'] ?? ''); ?>" required>
                            </div>
                            </div>
                        </fieldset>
                            <div class="col-12">
                                <hr>
                                <h5 class="fw-bold mb-3">Junkshop Location</h5>
                                <button type="button" id="btn-track-location" class="btn btn-primary"><i class="bi bi-geo-alt-fill me-1"></i>Track your junkshop location</button>
                                <div id="location-feedback" class="alert d-none mt-3 mb-0" role="alert" aria-live="polite"></div>
                                <div class="row g-3 mt-1">
                                    <input type="hidden" id="latitude" name="latitude" value="<?php echo htmlspecialchars($currentProfile['latitude'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" id="longitude" name="longitude" value="<?php echo htmlspecialchars($currentProfile['longitude'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="col-12">
                                        <div id="junkshop-profile-map" style="height: 250px; width: 100%; border-radius: 8px; margin-top: 10px; overflow: hidden; position: relative; isolation: isolate; z-index: 0;"></div>
                                    </div>
                                </div>
                            </div>
                    <fieldset <?php echo $profileFormLocked ? 'disabled' : ''; ?>>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Operating Days</label>
                                <div class="row g-2 mb-2">
                                    <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                                        <div class="col-6 col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input operating-day" type="checkbox" name="operating_days[]" value="<?php echo $day; ?>" id="day_<?php echo strtolower($day); ?>">
                                                <label class="form-check-label" for="day_<?php echo strtolower($day); ?>"><?php echo $day; ?></label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="select_weekdays">Select Monday–Saturday</button>
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="opening_time" class="form-label">Opening Time <span class="text-danger">*</span></label>
                                    <select class="form-select" id="opening_time" name="opening_time" required>
                                        <?php foreach (['8:00 AM','8:30 AM','9:00 AM','9:30 AM','10:00 AM','10:30 AM','11:00 AM','11:30 AM','12:00 PM','12:30 PM','1:00 PM','1:30 PM','2:00 PM','2:30 PM','3:00 PM','3:30 PM','4:00 PM','4:30 PM','5:00 PM'] as $time): ?>
                                            <option value="<?php echo $time; ?>"><?php echo $time; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="closing_time" class="form-label">Closing Time <span class="text-danger">*</span></label>
                                    <select class="form-select" id="closing_time" name="closing_time" required>
                                        <?php foreach (['8:00 AM','8:30 AM','9:00 AM','9:30 AM','10:00 AM','10:30 AM','11:00 AM','11:30 AM','12:00 PM','12:30 PM','1:00 PM','1:30 PM','2:00 PM','2:30 PM','3:00 PM','3:30 PM','4:00 PM','4:30 PM','5:00 PM','5:30 PM','6:00 PM','6:30 PM','7:00 PM','7:30 PM','8:00 PM'] as $time): ?>
                                            <option value="<?php echo $time; ?>"><?php echo $time; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <input type="hidden" id="operating_schedule" name="operating_schedule" value="<?php echo Validator::escape($currentProfile['operating_schedule'] ?? ''); ?>">
                            <div class="col-md-6">
                                <label class="form-label" for="business_permit_reference">Permit/Registration Reference</label>
                                <input type="text" class="form-control" id="business_permit_reference" name="business_permit_reference" value="<?php echo Validator::escape($currentProfile['business_permit_reference'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="gcash_account_name">GCash Account Name</label>
                                <input type="text" class="form-control" id="gcash_account_name" name="gcash_account_name" value="<?php echo Validator::escape($currentProfile['gcash_account_name'] ?? ''); ?>" maxlength="120">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="gcash_account_number">GCash Account Number</label>
                                <input type="tel" class="form-control" id="gcash_account_number" name="gcash_account_number" value="<?php echo Validator::escape($currentProfile['gcash_account_number'] ?? ''); ?>" maxlength="11" inputmode="numeric" placeholder="09XXXXXXXXX">
                                <div class="form-text">Used for manual GCash settlement verification only.</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary" <?php echo $profileFormLocked ? 'disabled' : ''; ?>><i class="bi bi-save"></i> Save Changes</button>
                        <a href="<?php echo APP_URL; ?>/user-junkshop/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                    </fieldset>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    function validateMobileSuffix(input) {
        if (!input) return false;
        const value = input.value.trim();
        const valid = /^\d{9}$/.test(value);
        input.setCustomValidity(valid ? '' : 'Enter exactly 9 digits after 09.');
        return valid;
    }

    function parseOperatingSchedule(rawSchedule) {
        const orderedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        const normalized = String(rawSchedule || '')
            .trim()
            .replace(/\?/g, '–')
            .replace(/\s*[-–]\s*/g, '–');

        if (!normalized) {
            return { selectedDays: [], openingTime: '', closingTime: '' };
        }

        const pipeMatch = normalized.match(/^(.*?)\s*\|\s*(.*)$/);
        const daySegment = pipeMatch ? pipeMatch[1].trim() : normalized;
        const timeSegment = pipeMatch ? pipeMatch[2].trim() : '';
        const selectedDays = new Set();

        if (daySegment) {
            const segments = daySegment
                .split(',')
                .map((part) => part.trim())
                .filter(Boolean);

            for (const segment of segments) {
                const rangeMatch = segment.match(/^(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\s*–\s*(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)$/i);
                if (rangeMatch) {
                    const startIndex = orderedDays.findIndex((day) => day.toLowerCase() === rangeMatch[1].toLowerCase());
                    const endIndex = orderedDays.findIndex((day) => day.toLowerCase() === rangeMatch[2].toLowerCase());
                    if (startIndex !== -1 && endIndex !== -1) {
                        const start = Math.min(startIndex, endIndex);
                        const end = Math.max(startIndex, endIndex);
                        for (let i = start; i <= end; i++) {
                            selectedDays.add(orderedDays[i]);
                        }
                        continue;
                    }
                }

                const exactDay = orderedDays.find((day) => day.toLowerCase() === segment.toLowerCase());
                if (exactDay) {
                    selectedDays.add(exactDay);
                }
            }
        }

        const timeMatch = timeSegment.match(/^(\d{1,2}:\d{2}\s*(?:AM|PM))\s*–\s*(\d{1,2}:\d{2}\s*(?:AM|PM))$/i);

        return {
            selectedDays: orderedDays.filter((day) => selectedDays.has(day)),
            openingTime: timeMatch ? timeMatch[1].trim() : '',
            closingTime: timeMatch ? timeMatch[2].trim() : ''
        };
    }

    function buildOperatingSchedule() {
        const selectedDays = Array.from(document.querySelectorAll('.operating-day:checked')).map((node) => node.value);
        const openingTime = document.getElementById('opening_time')?.value || '';
        const closingTime = document.getElementById('closing_time')?.value || '';
        const hiddenField = document.getElementById('operating_schedule');

        if (!hiddenField) return;

        if (!selectedDays.length || !openingTime || !closingTime) {
            hiddenField.value = '';
            return;
        }

        const orderedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        const sorted = selectedDays.slice().sort((a, b) => orderedDays.indexOf(a) - orderedDays.indexOf(b));
        const contiguousStart = orderedDays.indexOf(sorted[0]);
        const contiguousEnd = orderedDays.indexOf(sorted[sorted.length - 1]);
        const isContiguous = sorted.length === contiguousEnd - contiguousStart + 1;
        const displayDays = isContiguous ? `${sorted[0]}–${sorted[sorted.length - 1]}` : sorted.join(', ');

        hiddenField.value = `${displayDays} | ${openingTime}–${closingTime}`;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const existingSchedule = document.getElementById('operating_schedule')?.value || '';
        const parsed = parseOperatingSchedule(existingSchedule);

        document.querySelectorAll('.operating-day').forEach(function(checkbox) {
            checkbox.checked = parsed.selectedDays.includes(checkbox.value);
        });

        if (parsed.openingTime) {
            const openingSelect = document.getElementById('opening_time');
            if (openingSelect) {
                openingSelect.value = parsed.openingTime;
            }
        }

        if (parsed.closingTime) {
            const closingSelect = document.getElementById('closing_time');
            if (closingSelect) {
                closingSelect.value = parsed.closingTime;
            }
        }

        document.querySelectorAll('#mobile_number').forEach(function(input) {
            input.addEventListener('input', function() {
                validateMobileSuffix(this);
            });
            input.addEventListener('blur', function() {
                validateMobileSuffix(this);
            });
            input.closest('form')?.addEventListener('submit', function(event) {
                if (!validateMobileSuffix(input)) {
                    event.preventDefault();
                    input.reportValidity();
                }
            });
        });

        const scheduleDays = document.querySelectorAll('.operating-day');
        scheduleDays.forEach((input) => {
            input.addEventListener('change', buildOperatingSchedule);
        });
        ['opening_time', 'closing_time'].forEach((id) => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('change', buildOperatingSchedule);
            }
        });

        document.getElementById('select_weekdays')?.addEventListener('click', function() {
            document.querySelectorAll('.operating-day').forEach((input) => {
                const day = input.value;
                input.checked = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'].includes(day);
            });
            buildOperatingSchedule();
        });

        buildOperatingSchedule();
    });

    document.addEventListener('DOMContentLoaded', function() {
        const trackButton = document.getElementById('btn-track-location');
        const highPrecisionGeoOptions = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        };
        const latitudeInput = document.getElementById('latitude');
        const longitudeInput = document.getElementById('longitude');
        const addressInput = document.getElementById('address');
        const mapElement = document.getElementById('junkshop-profile-map');
        const locationFeedback = document.getElementById('location-feedback');
        let junkshopMap = null;
        let junkshopMarker = null;

        if (!trackButton || !latitudeInput || !longitudeInput || !addressInput || !mapElement) return;

        function setCoordinates(lat, lng) {
            latitudeInput.value = Number(lat).toFixed(8);
            longitudeInput.value = Number(lng).toFixed(8);
        }

        function reverseGeocodeJunkshopLocation(lat, lng) {
            const url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng) + '&zoom=18&addressdetails=1&accept-language=en';
            return fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'Accept-Language': 'en'
                }
            })
                .then(function(response) {
                    if (!response.ok) throw new Error('Reverse geocoding failed.');
                    return response.json();
                })
                .then(function(data) {
                    if (data.display_name) return data.display_name;
                    const address = data.address || {};
                    return [
                        address.road,
                        address.house_number,
                        address.neighbourhood || address.suburb || address.village,
                        address.city || address.town || address.municipality,
                        address.state || address.region,
                        address.country
                    ].filter(Boolean).join(', ');
                });
        }

        function showLocationFeedback(message, success) {
            if (!locationFeedback) return;
            locationFeedback.className = 'alert mt-3 mb-0 ' + (success ? 'alert-success' : 'alert-danger');
            locationFeedback.textContent = message;
        }

        function initializeJunkshopMap(lat, lng) {
            if (typeof L === 'undefined') return;

            if (!junkshopMap) {
                junkshopMap = L.map(mapElement).setView([lat, lng], 18);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(junkshopMap);
            } else {
                junkshopMap.setView([lat, lng], 18);
            }

            if (!junkshopMarker) {
                junkshopMarker = L.marker([lat, lng], { draggable: true }).addTo(junkshopMap);
                junkshopMarker.on('dragend', function() {
                    const position = junkshopMarker.getLatLng();
                    setCoordinates(position.lat, position.lng);
                    reverseGeocodeJunkshopLocation(position.lat, position.lng)
                        .then(function(address) {
                            if (address) addressInput.value = address;
                        })
                        .catch(function() {});
                });
            } else {
                junkshopMarker.setLatLng([lat, lng]);
            }
            window.requestAnimationFrame(function() {
                if (junkshopMap) junkshopMap.invalidateSize(true);
            });
        }

        function resetTrackButton() {
            trackButton.disabled = false;
            trackButton.innerHTML = '<i class="bi bi-geo-alt-fill me-1"></i>Track your junkshop location';
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
            resetTrackButton();
        }

        function initializeSavedLocation() {
            const savedLatitude = Number.parseFloat(latitudeInput.value);
            const savedLongitude = Number.parseFloat(longitudeInput.value);
            if (Number.isFinite(savedLatitude) && Number.isFinite(savedLongitude)) {
                initializeJunkshopMap(savedLatitude, savedLongitude);
                return true;
            }
            return false;
        }

        if (!initializeSavedLocation()) {
            window.addEventListener('load', initializeSavedLocation, { once: true });
        }

        trackButton.addEventListener('click', function() {
            if (typeof L === 'undefined') {
                alert('The map service is currently unavailable. Please try again later.');
                return;
            }
            if (!window.isSecureContext) {
                handleLocationError({ code: 0 });
                return;
            }
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by this browser.');
                resetTrackButton();
                return;
            }
            trackButton.disabled = true;
            trackButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Locating...';
            navigator.geolocation.getCurrentPosition(async function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                setCoordinates(lat, lng);
                initializeJunkshopMap(lat, lng);
                let address = '';
                try {
                    address = await reverseGeocodeJunkshopLocation(lat, lng);
                } catch (error) {
                    console.warn('Reverse geocode failed:', error);
                }
                if (!address) address = addressInput.value.trim();
                if (address) addressInput.value = address;

                try {
                    const response = await fetch('update_junkshop_location.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                        body: new URLSearchParams({latitude: latitudeInput.value, longitude: longitudeInput.value, address: address})
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Unable to update location.');
                    }
                    if (address) addressInput.value = address;
                    showLocationFeedback(result.message, true);
                } catch (error) {
                    showLocationFeedback(error.message || 'Unable to update location.', false);
                } finally {
                    resetTrackButton();
                }
            }, handleLocationError, highPrecisionGeoOptions);
        });
    });
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('toggleAvailability');
    const statusText = document.getElementById('availability-status-text');
    const label = document.getElementById('toggleLabel');
    const csrfToken = document.querySelector('input[name="_csrf_token"]')?.value || '';
    if (!toggle) return;

    toggle.addEventListener('change', async function () {
        const requestedValue = toggle.checked;
        toggle.disabled = true;
        try {
            const response = await fetch('<?php echo APP_URL; ?>/user-junkshop/api/toggle-availability.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: new URLSearchParams({_csrf_token: csrfToken, is_available: requestedValue ? '1' : '0'})
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Unable to update availability.');
            toggle.checked = Boolean(result.is_available);
            label.textContent = toggle.checked ? 'Available' : 'Unavailable';
            statusText.textContent = toggle.checked ? 'Status: Available (Accepting pickup requests)' : 'Status: Unavailable (Not accepting pickup requests)';
        } catch (error) {
            toggle.checked = !requestedValue;
            window.alert(error.message);
        } finally {
            toggle.disabled = false;
        }
    });
});
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
