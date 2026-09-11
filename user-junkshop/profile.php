<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/DashboardController.php';

if (!Auth::check()) {
    header('Location: ' . APP_URL . '/user-junkshop/login.php');
    exit;
}

if (Auth::userRole() === 'admin') {
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit;
}

$controller = new DashboardController();
$role = Auth::userRole();
$userId = Auth::userId();
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
        if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
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

            if (!Validator::required($businessName)) $errors[] = 'Business name is required.';
            if (!Validator::required($ownerName)) $errors[] = 'Business owner name is required.';
            if (!Validator::required($mobileNumber) || !Validator::mobileNumber($mobileNumber)) $errors[] = 'Valid mobile number is required.';
            if (!Validator::required($address)) $errors[] = 'Business address is required.';
            if (!Validator::required($schedule)) $errors[] = 'Operating schedule is required.';
            if (!Validator::required($permit)) $errors[] = 'Permit reference is required.';
            if ($gcashAccountName !== '' && strlen($gcashAccountName) > 120) $errors[] = 'GCash account name is too long.';
            if ($gcashAccountNumber !== '' && !preg_match('/^09\d{9}$/', $gcashAccountNumber)) $errors[] = 'GCash account number must be an 11-digit Philippine mobile number.';

            if (empty($errors)) {
                $result = $controller->updateJunkshopProfile($userId, $businessName, $ownerName, $mobileNumber, $address, $schedule, $permit, $gcashAccountName, $gcashAccountNumber);
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
                                <label class="form-label" for="complete_address">Address</label>
                                <input type="text" class="form-control" id="complete_address" name="complete_address" value="<?php echo Validator::escape($currentProfile['complete_address'] ?? ''); ?>" required>
                            </div>
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
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
                        <a href="<?php echo APP_URL; ?>/user-junkshop/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
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
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
