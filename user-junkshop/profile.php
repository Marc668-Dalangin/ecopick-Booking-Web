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
$profileCooldownDays = $controller->getProfileCooldownDays();
$errors = [];
$successMessage = '';

if ($role === 'seller') {
    $currentProfile = $controller->getSellerProfile($userId);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
            $errors[] = 'Invalid security token. Please try again.';
        } else {
            $firstName = strtoupper(trim((string)($_POST['first_name'] ?? '')));
            $lastName = strtoupper(trim((string)($_POST['last_name'] ?? '')));
            $fullName = trim($firstName . ' ' . $lastName);
            $mobileSuffix = trim((string)($_POST['mobile_number'] ?? ''));
            $mobileNumber = preg_match('/^[0-9]{1,9}$/', $mobileSuffix) === 1 ? '09' . $mobileSuffix : '';
            $address = trim((string)($_POST['address'] ?? ''));
            $barangay = trim((string)($_POST['barangay'] ?? ''));

            if (!Validator::required($firstName)) $errors[] = 'First name is required.';
            if (!Validator::required($lastName)) $errors[] = 'Last name is required.';
            if (($firstName !== '' && !preg_match('/^[A-Z\s]+$/', $firstName)) || ($lastName !== '' && !preg_match('/^[A-Z\s]+$/', $lastName))) $errors[] = 'Name fields must contain uppercase letters only.';
            if (!Validator::required($fullName)) $errors[] = 'Full name is required.';
            if (!preg_match('/^[0-9]{1,9}$/', $mobileSuffix)) $errors[] = 'Mobile number must be numbers only up to 9 digits.';
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
            $businessName = strtoupper(trim((string)($_POST['business_name'] ?? '')));
            $ownerName = strtoupper(trim((string)($_POST['owner_name'] ?? '')));
            $mobileSuffix = trim((string)($_POST['mobile_number'] ?? ''));
            $mobileNumber = preg_match('/^[0-9]{1,9}$/', $mobileSuffix) === 1 ? '09' . $mobileSuffix : '';
            $address = trim((string)($_POST['complete_address'] ?? ''));
            $schedule = trim((string)($_POST['operating_schedule'] ?? ''));
            $permit = trim((string)($_POST['business_permit_reference'] ?? ''));
            $gcashAccountName = strtoupper(trim((string)($_POST['gcash_account_name'] ?? '')));
            $gcashAccountNumberSuffix = trim((string)($_POST['gcash_account_number_suffix'] ?? ''));
            $gcashAccountNumber = $gcashAccountNumberSuffix === '' ? '' : '+639' . $gcashAccountNumberSuffix;
            $latitudeInput = trim((string)($_POST['latitude'] ?? ''));
            $longitudeInput = trim((string)($_POST['longitude'] ?? ''));
            $latitude = $latitudeInput === '' ? null : filter_var($latitudeInput, FILTER_VALIDATE_FLOAT);
            $longitude = $longitudeInput === '' ? null : filter_var($longitudeInput, FILTER_VALIDATE_FLOAT);

            if (!Validator::required($businessName)) $errors[] = 'Business name is required.';
            if (!Validator::required($ownerName)) $errors[] = 'Business owner name is required.';
            if (($businessName !== '' && !preg_match('/^[A-Z\s\.\-]+$/', $businessName)) || ($ownerName !== '' && !preg_match('/^[A-Z\s\.\-]+$/', $ownerName))) $errors[] = 'Business and owner names must contain uppercase letters, spaces, dots, or hyphens only.';
            if (!preg_match('/^[0-9]{1,9}$/', $mobileSuffix)) $errors[] = 'Mobile number must be numbers only up to 9 digits.';
            if (!Validator::required($address)) $errors[] = 'Business address is required.';
            if (!Validator::required($schedule)) $errors[] = 'Operating schedule is required.';
            if (!Validator::required($permit)) $errors[] = 'Permit reference is required.';
            if (!Validator::required($gcashAccountName)) $errors[] = 'GCash account name is required.';
            if (strlen($gcashAccountName) < 2 || strlen($gcashAccountName) > 30 || !preg_match('/^(?=[A-Z .]{2,30}$)(?!.*\..*\.)(?!.* {2,})[A-Z]+(?:\.[A-Z]*)?(?: [A-Z]+(?:\.[A-Z]*)?)*$/', $gcashAccountName)) $errors[] = 'GCash account name must use uppercase letters, single spaces, and at most one dot.';
            if (!preg_match('/^[0-9]{9}$/', $gcashAccountNumberSuffix)) $errors[] = 'GCash account number must contain exactly 9 digits after +639.';
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
$profileCooldownUntil = $profileUpdatedAt !== false && $profileCooldownDays > 0 ? $profileUpdatedAt + ($profileCooldownDays * 24 * 60 * 60) : null;
$profileCooldownActive = $profileCooldownUntil !== null && $profileCooldownUntil > time();
$profileCooldownRemainingDays = $profileCooldownActive ? max(1, (int) ceil(($profileCooldownUntil - time()) / 86400)) : 0;
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
                    <div class="alert alert-info" role="alert">You can edit your profile again in <?php echo $profileCooldownRemainingDays; ?> day(s) (Next available date: <?php echo Validator::escape(date('F j, Y', $profileCooldownUntil)); ?>).</div>
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
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo Validator::escape($sellerFirstName); ?>" required style="text-transform: uppercase;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="last_name">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo Validator::escape($sellerLastName); ?>" required style="text-transform: uppercase;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="mobile_number">Mobile Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">09</span>
                                    <input type="text" class="form-control" id="mobile_number" name="mobile_number" value="<?php echo Validator::escape($sellerMobile); ?>" required inputmode="numeric" maxlength="9" pattern="[0-9]{1,9}" placeholder="123456789">
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
                        <div id="availability-feedback" class="alert d-none mb-3" role="alert" aria-live="polite"></div>
                    <fieldset <?php echo $profileFormLocked ? 'disabled' : ''; ?>>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="business_name">Business Name</label>
                                <input type="text" class="form-control" id="business_name" name="business_name" value="<?php echo Validator::escape($currentProfile['business_name'] ?? ''); ?>" required style="text-transform: uppercase;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="owner_name">Owner / Contact Person</label>
                                <input type="text" class="form-control" id="owner_name" name="owner_name" value="<?php echo Validator::escape($currentProfile['owner_name'] ?? ''); ?>" required style="text-transform: uppercase;">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="mobile_number">Mobile Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">09</span>
                                    <input type="text" class="form-control" id="mobile_number" name="mobile_number" value="<?php echo Validator::escape($junkshopMobile); ?>" required inputmode="numeric" maxlength="9" pattern="[0-9]{1,9}" placeholder="123456789">
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
                            <?php
                            $storedGcashNumber = trim((string)($currentProfile['gcash_account_number'] ?? ''));
                            $gcashNumberSuffix = str_starts_with($storedGcashNumber, '+639') ? substr($storedGcashNumber, 4) : (str_starts_with($storedGcashNumber, '639') ? substr($storedGcashNumber, 3) : (str_starts_with($storedGcashNumber, '09') ? substr($storedGcashNumber, 2) : $storedGcashNumber));
                            ?>
                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="gcash_account_name">GCash Account Name <span class="text-danger">*</span></label>
                                <input type="text" name="gcash_account_name" id="gcash_account_name" class="form-control text-uppercase" maxlength="30" value="<?php echo Validator::escape($currentProfile['gcash_account_name'] ?? ''); ?>" required oninput="sanitizeGCashName(this)">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold" for="gcash_account_number_suffix">GCash Account Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light fw-bold text-primary">+639</span>
                                    <input type="text" name="gcash_account_number_suffix" id="gcash_account_number_suffix" class="form-control" maxlength="9" value="<?php echo Validator::escape($gcashNumberSuffix); ?>" required inputmode="numeric" oninput="sanitizeGCashNumber(this)">
                                </div>
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
    function sanitizeGCashName(input) {
        // 1. Force Uppercase
        let val = input.value.toUpperCase();

        // 2. Allow only A-Z, space, and dot
        val = val.replace(/[^A-Z .]/g, '');

        // 3. Prevent consecutive double spaces
        val = val.replace(/ {2,}/g, ' ');

        // 4. Ensure at most 1 dot total in the whole string
        const firstDot = val.indexOf('.');
        if (firstDot !== -1) {
            val = val.slice(0, firstDot + 1) + val.slice(firstDot + 1).replace(/\./g, '');
        }

        // 5. Enforce 30 character max length
        input.value = val.slice(0, 30);
    }

    function sanitizeGCashNumber(input) {
        input.value = input.value.replace(/[^0-9]/g, '').slice(0, 9);
    }

    function validateMobileSuffix(input) {
        if (!input) return false;
        const value = input.value.trim();
        const valid = /^\d{1,9}$/.test(value);
        input.setCustomValidity(valid ? '' : 'Enter 1 to 9 digits after 09.');
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

        document.querySelectorAll('#first_name, #last_name').forEach(function(input) {
            input.addEventListener('input', function() {
                this.value = this.value.toUpperCase().replace(/[^A-Z\s]/g, '');
            });
        });

        document.querySelectorAll('#business_name, #owner_name').forEach(function(input) {
            input.addEventListener('input', function() {
                this.value = this.value.toUpperCase().replace(/[^A-Z\s\.\-]/g, '');
            });
        });

        document.querySelectorAll('#mobile_number').forEach(function(input) {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 9);
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
            latitudeInput.value = Number(lat).toFixed(6);
            longitudeInput.value = Number(lng).toFixed(6);
        }

        function sanitizeReverseGeocodeAddress(data) {
            const address = data && data.address ? data.address : {};
            const road = address.road || address.pedestrian || address.street || '';
            const barangay = address.village || address.suburb || address.neighbourhood || address.quarter || '';
            const city = address.city || address.town || address.municipality || address.city_district || '';
            const province = address.state || address.province || address.region || '';
            const parts = [road, barangay, city, province].filter(function (part) {
                return typeof part === 'string' && part.trim() !== '' && !/^(postal code|zip code|region)$/i.test(part.trim());
            }).map(function (part) {
                return part.trim().replace(/,\s*$/, '');
            });
            if (!parts.length) {
                return '';
            }
            return parts.join(', ');
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
                    const sanitized = sanitizeReverseGeocodeAddress(data);
                    if (sanitized) return sanitized;
                    if (data && typeof data.display_name === 'string') {
                        return sanitizeReverseGeocodeAddress({ address: { road: '', village: '', city: '', state: '' } });
                    }
                    return '';
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
            const weakSignalMessage = 'Weak GPS signal or connection drop detected. Please check location settings or refresh/reload the website.';
            if (!window.isSecureContext) {
                showLocationFeedback('Geolocation requires a secure HTTPS connection on mobile devices.', false);
            } else if (error && (error.code === 2 || error.code === 3 || error.code === 4)) {
                showLocationFeedback(weakSignalMessage, false);
            } else if (error && error.code === 1) {
                showLocationFeedback('Location permission was denied. Please allow location access and try again.', false);
            } else {
                showLocationFeedback(weakSignalMessage, false);
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
    const feedback = document.getElementById('availability-feedback');
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
            if (feedback) {
                feedback.className = 'alert alert-success mb-3';
                feedback.textContent = result.message || 'Shop operational status updated.';
            }
        } catch (error) {
            toggle.checked = !requestedValue;
            if (feedback) {
                feedback.className = 'alert alert-danger mb-3';
                feedback.textContent = error.message || 'Unable to update availability.';
            }
        } finally {
            toggle.disabled = false;
        }
    });
});
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
