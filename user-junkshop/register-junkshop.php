<?php
/**
 * Junkshop Registration Page
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/RegistrationController.php';

// Redirect if already logged in
Auth::redirectIfAuthenticated();

$errors = [];
$success = false;
$pendingRegistration = Session::get('pending_registration');
$otpRequired = is_array($pendingRegistration) && ($pendingRegistration['type'] ?? '') === 'junkshop';
$otpEmail = $otpRequired ? (string) ($pendingRegistration['email'] ?? '') : '';
$otpSentAt = $otpRequired ? (int) ($pendingRegistration['last_otp_sent_at'] ?? Session::get('last_otp_sent_at', 0)) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $selectedDays = $_POST['operating_days'] ?? [];
        $openingTime = trim((string)($_POST['opening_time'] ?? ''));
        $closingTime = trim((string)($_POST['closing_time'] ?? ''));

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $selectedDays = array_values(array_filter($selectedDays, function ($day) use ($days) {
            return in_array($day, $days, true);
        }));

        $scheduleValue = '';
        if (!empty($selectedDays) && !empty($openingTime) && !empty($closingTime)) {
            $range = $selectedDays;
            if (count($range) > 1) {
                $firstIndex = array_search($range[0], $days, true);
                $lastIndex = array_search(end($range), $days, true);
                if ($firstIndex !== false && $lastIndex !== false && $lastIndex - $firstIndex + 1 === count($range)) {
                    $range = [$days[$firstIndex] . '–' . $days[$lastIndex]];
                }
            }
            $scheduleValue = implode(', ', $range) . ' | ' . $openingTime . '–' . $closingTime;
        }

        $data = [
            'business_name' => $_POST['business_name'] ?? '',
            'owner_name' => $_POST['owner_name'] ?? '',
            'username' => $_POST['username'] ?? '',
            'email' => $_POST['email'] ?? '',
            'mobile_number' => $_POST['mobile_number'] ?? '',
            'complete_address' => $_POST['complete_address'] ?? '',
            'operating_schedule' => $scheduleValue,
            'business_permit_reference' => $_POST['business_permit_reference'] ?? '',
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'terms' => $_POST['terms'] ?? ''
        ];

        $controller = new RegistrationController();
        $result = $controller->registerJunkshop($data);

        if (!empty($result['otp_send_failed'])) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please check your email address.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($result['success']) {
            $otpRequired = !empty($result['otp_required']);
            $otpEmail = (string) ($result['email'] ?? $data['email']);
            $otpSentAt = (int) ($result['otp_sent_at'] ?? 0);
            $success = !$otpRequired;
        } else {
            $errors = $result['errors'];
        }
    }
}

$pageTitle = 'Register Your Junkshop';
?>
<?php require_once __DIR__ . '/../app/views/header.php'; ?>

<div class="container-lg py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h2 class="card-title text-center mb-2 fw-bold">
                        <i class="bi bi-shop"></i> Register Your Junkshop
                    </h2>
                    <p class="text-center text-muted text-sm mb-4">
                        Become a verified EcoPick partner
                    </p>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill"></i>
                            <strong>Success!</strong> Your junkshop account has been created and is awaiting admin approval.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>

                        <div class="alert alert-info" role="alert">
                            <i class="bi bi-info-circle-fill"></i>
                            <strong>Pending Approval:</strong> You will receive an email once your account has been reviewed and approved by the EcoPick admin team.
                        </div>

                        <div class="text-center">
                            <a href="<?php echo APP_URL; ?>/user-junkshop/login.php" class="btn btn-primary">
                                Go to Login
                            </a>
                        </div>
                    <?php else: ?>
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <strong>Please fix the following errors:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo Validator::escape($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div id="registrationMessage" class="alert d-none" role="alert"></div>
                        <form id="registrationForm" method="POST" action="" novalidate>
                            <!-- CSRF Token -->
                            <?php echo CSRF::field(); ?>

                            <!-- Business Information -->
                            <h5 class="mb-3 fw-bold">Business Information</h5>

                            <!-- Business Name -->
                            <div class="mb-3">
                                <label for="business_name" class="form-label">Business Name <span class="text-danger">*</span></label>
                                <input 
                                    type="text" 
                                    class="form-control uppercase-input"
                                    id="business_name" 
                                    name="business_name"
                                    value="<?php echo isset($_POST['business_name']) ? Validator::escape($_POST['business_name']) : ''; ?>"
                                    required
                                    placeholder="Your junkshop business name"
                                >
                            </div>

                            <!-- Owner Name -->
                            <div class="mb-3">
                                <label for="owner_name" class="form-label">Owner/Authorized Contact Person <span class="text-danger">*</span></label>
                                <input 
                                    type="text" 
                                    class="form-control uppercase-input"
                                    id="owner_name" 
                                    name="owner_name"
                                    value="<?php echo isset($_POST['owner_name']) ? Validator::escape($_POST['owner_name']) : ''; ?>"
                                    required
                                    placeholder="Full name"
                                >
                            </div>

                            <!-- Complete Address -->
                            <div class="mb-3">
                                <label for="complete_address" class="form-label">Complete Business Address <span class="text-danger">*</span></label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    id="complete_address" 
                                    name="complete_address"
                                    value="<?php echo isset($_POST['complete_address']) ? Validator::escape($_POST['complete_address']) : ''; ?>"
                                    required
                                    placeholder="Street, barangay, city"
                                >
                            </div>

                            <!-- Operating Schedule -->
                            <div class="mb-3">
                                <label class="form-label">Operating Days <span class="text-danger">*</span></label>
                                <div class="row g-2">
                                    <?php $selectedDays = isset($_POST['operating_days']) ? (array) $_POST['operating_days'] : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']; ?>
                                    <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                                        <div class="col-6 col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input operating-day" type="checkbox" name="operating_days[]" value="<?php echo $day; ?>" id="day_<?php echo strtolower($day); ?>" <?php echo in_array($day, $selectedDays, true) ? 'checked' : ''; ?>>
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
                                        <?php $openingTime = $_POST['opening_time'] ?? '8:00 AM'; foreach (['8:00 AM','8:30 AM','9:00 AM','9:30 AM','10:00 AM','10:30 AM','11:00 AM','11:30 AM','12:00 PM','12:30 PM','1:00 PM','1:30 PM','2:00 PM','2:30 PM','3:00 PM','3:30 PM','4:00 PM','4:30 PM','5:00 PM'] as $time): ?>
                                            <option value="<?php echo $time; ?>" <?php echo $openingTime === $time ? 'selected' : ''; ?>><?php echo $time; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="closing_time" class="form-label">Closing Time <span class="text-danger">*</span></label>
                                    <select class="form-select" id="closing_time" name="closing_time" required>
                                        <?php $closingTime = $_POST['closing_time'] ?? '5:00 PM'; foreach (['8:00 AM','8:30 AM','9:00 AM','9:30 AM','10:00 AM','10:30 AM','11:00 AM','11:30 AM','12:00 PM','12:30 PM','1:00 PM','1:30 PM','2:00 PM','2:30 PM','3:00 PM','3:30 PM','4:00 PM','4:30 PM','5:00 PM','5:30 PM','6:00 PM','6:30 PM','7:00 PM','7:30 PM','8:00 PM'] as $time): ?>
                                            <option value="<?php echo $time; ?>" <?php echo $closingTime === $time ? 'selected' : ''; ?>><?php echo $time; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <input type="hidden" id="operating_schedule" name="operating_schedule" value="<?php echo isset($_POST['operating_schedule']) ? Validator::escape($_POST['operating_schedule']) : ''; ?>">

                            <!-- Business Permit Reference -->
                            <div class="mb-3">
                                <label for="business_permit_reference" class="form-label">Business Permit/Registration Number <span class="text-danger">*</span></label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    id="business_permit_reference" 
                                    name="business_permit_reference"
                                    value="<?php echo isset($_POST['business_permit_reference']) ? Validator::escape($_POST['business_permit_reference']) : ''; ?>"
                                    required
                                    placeholder="BIR, DTI, or local permit number"
                                >
                                <small class="text-muted">Required for verification purposes</small>
                            </div>

                            <hr>

                            <!-- Contact Information -->
                            <h5 class="mb-3 fw-bold">Contact Information</h5>

                            <div class="mb-3">
                                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($_POST['username']) ? Validator::escape($_POST['username']) : ''; ?>" required minlength="5" maxlength="100" pattern="^(?=.{5,100}$)(?!.*\s)[A-Z]?[a-z0-9\W_]+$" placeholder="Marc123">
                            </div>

                            <!-- Email -->
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input 
                                    type="email" 
                                    class="form-control" 
                                    id="email" 
                                    name="email"
                                    value="<?php echo isset($_POST['email']) ? Validator::escape($_POST['email']) : ''; ?>"
                                    required
                                    placeholder="your@email.com"
                                >
                                <small class="text-muted">For account recovery and communications</small>
                            </div>

                            <!-- Mobile Number -->
                            <div class="mb-3">
                                <label for="mobile_number" class="form-label">Mobile Number</label>
                                <div class="input-group">
                                    <span class="input-group-text fw-bold">+63 9</span>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="mobile_number"
                                        name="mobile_number"
                                        value="<?php echo isset($_POST['mobile_number']) ? Validator::escape($_POST['mobile_number']) : ''; ?>"
                                        maxlength="9"
                                        placeholder="091234567"
                                        pattern="[0-9]{9}"
                                        required
                                    >
                                </div>
                            </div>

                            <hr>

                            <!-- Account Security -->
                            <h5 class="mb-3 fw-bold">Account Security</h5>

                            <!-- Password -->
                            <div class="mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input 
                                        type="password" 
                                        class="form-control" 
                                        id="password" 
                                        name="password"
                                        required
                                        placeholder="••••••••"
                                    >
                                    <button 
                                        class="btn btn-outline-secondary password-toggle" 
                                        type="button" 
                                        id="togglePassword1"
                                        data-target="password"
                                        data-password-toggle-ready="false"
                                        aria-label="Show password"
                                        aria-pressed="false"
                                    >
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">At least 8 characters</small>
                            </div>

                            <!-- Confirm Password -->
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input 
                                        type="password" 
                                        class="form-control" 
                                        id="confirm_password" 
                                        name="confirm_password"
                                        required
                                        placeholder="••••••••"
                                    >
                                    <button 
                                        class="btn btn-outline-secondary password-toggle" 
                                        type="button" 
                                        id="togglePassword2"
                                        data-target="confirm_password"
                                        data-password-toggle-ready="false"
                                        aria-label="Show password"
                                        aria-pressed="false"
                                    >
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Terms & Conditions -->
                            <div class="mb-4 form-check">
                                <input 
                                    type="checkbox" 
                                    class="form-check-input" 
                                    id="terms" 
                                    name="terms"
                                    required
                                >
                                <label class="form-check-label" for="terms">
                                    I agree to the EcoPick terms and conditions <span class="text-danger">*</span>
                                </label>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" class="btn btn-success w-100 mb-3" id="registrationSubmitButton">
                                <i class="bi bi-plus-circle"></i> Submit Registration
                            </button>
                        </form>

                        <!-- Info Box -->
                        <div class="alert alert-info alert-sm" role="alert">
                            <small>
                                <i class="bi bi-info-circle-fill"></i>
                                <strong>Note:</strong> Your account will be reviewed by EcoPick admins. You'll receive an email when approved.
                            </small>
                        </div>

                        <!-- Login Link -->
                        <div class="text-center">
                            <p class="text-muted mb-0">
                                Already have an account?
                                <a href="<?php echo APP_URL; ?>/user-junkshop/login.php" class="text-decoration-none">
                                    Login here
                                </a>
                            </p>
                        </div>

                        <!-- Back Link -->
                        <div class="text-center mt-3">
                            <a href="<?php echo APP_URL; ?>/user-junkshop/register.php" class="text-muted text-decoration-none small">
                                <i class="bi bi-arrow-left"></i> Back to registration types
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="loadingOverlay" class="loading-overlay d-none" role="status" aria-live="polite" aria-hidden="true">
    <div class="text-center bg-white rounded-3 shadow p-4">
        <div class="spinner-border text-success mb-3" role="status" aria-hidden="true"></div>
        <div>Sending verification code to your email... Please wait.</div>
    </div>
</div>

<div class="modal fade" id="otpVerifyModal" tabindex="-1" aria-labelledby="otpVerifyModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="otpVerifyModalLabel">Verify your email</h5>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" id="btn-cancel-otp">Exit / Change Email</button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Enter the 6-digit code sent to <strong><?php echo Validator::escape($otpEmail); ?></strong>.</p>
                <div id="otpMessage" class="alert d-none" role="alert"></div>
                <form id="otpVerifyForm" novalidate>
                    <?php echo CSRF::field(); ?>
                    <input type="hidden" name="email" value="<?php echo Validator::escape($otpEmail); ?>">
                    <label for="otp_code" class="form-label">Verification code</label>
                    <input type="text" class="form-control form-control-lg text-center" id="otp_code" name="otp_code" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required>
                    <button type="button" id="resend-otp-btn" class="btn btn-outline-secondary mt-2" disabled>Resend Code (<span id="cooldown-timer">60</span>s)</button>
                    <button type="submit" class="btn btn-success w-100 mt-3">Verify email</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="verificationSuccessModal" tabindex="-1" aria-labelledby="verificationSuccessModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="verificationSuccessModalLabel">Registration submitted</h5>
            </div>
            <div class="modal-body">
                <div id="verificationSuccessMessage" class="alert alert-warning mb-0"></div>
            </div>
            <div class="modal-footer">
                <a class="btn btn-primary" href="login.php">Back to Login</a>
            </div>
        </div>
    </div>
</div>

<style>
    .uppercase-input {
        text-transform: uppercase;
    }

    .loading-overlay {
        align-items: center;
        background: rgba(0, 0, 0, 0.45);
        display: flex;
        inset: 0;
        justify-content: center;
        position: fixed;
        z-index: 2000;
    }
</style>

<script>
    function validateUsername(input) {
        if (!input) return false;
        const valid = /^(?=.{5,100}$)(?!.*\s)[A-Z]?[a-z0-9\W_]+$/.test(input.value);
        input.setCustomValidity(valid ? '' : 'Use at least 5 characters with uppercase only at the beginning and no spaces.');
        return valid;
    }

    function validateMobileSuffix(input) {
        if (!input) return false;
        const value = input.value.trim();
        const valid = /^\d{9}$/.test(value);
        input.setCustomValidity(valid ? '' : 'Enter exactly 9 digits after 09.');
        return valid;
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
        const registrationForm = document.getElementById('registrationForm');
        const registrationButton = document.getElementById('registrationSubmitButton');
        const loadingOverlay = document.getElementById('loadingOverlay');
        const registrationMessage = document.getElementById('registrationMessage');
        const otpModal = document.getElementById('otpVerifyModal');
        const otpForm = document.getElementById('otpVerifyForm');
        const modal = otpModal ? new bootstrap.Modal(otpModal) : null;
        const verificationSuccessModal = document.getElementById('verificationSuccessModal');
        const verificationSuccessMessage = document.getElementById('verificationSuccessMessage');
        const successModal = verificationSuccessModal ? new bootstrap.Modal(verificationSuccessModal) : null;
        const otpEmail = otpForm?.elements.email;
        const resendOtpButton = document.getElementById('resend-otp-btn');
        let cooldownInterval;

        function startOtpCooldown(sentAt) {
            const storageKey = `junkshop_otp_sent_at_${otpEmail?.value || 'pending'}`;
            const sentTimestamp = Number(sentAt) || Math.floor(Date.now() / 1000);
            localStorage.setItem(storageKey, String(sentTimestamp));
            clearInterval(cooldownInterval);

            function updateCooldown() {
                const remaining = Math.max(0, 60 - (Math.floor(Date.now() / 1000) - sentTimestamp));
                if (remaining === 0) {
                    resendOtpButton.textContent = 'Resend Code';
                    resendOtpButton.disabled = false;
                    clearInterval(cooldownInterval);
                    return;
                }
                resendOtpButton.innerHTML = `Resend Code (<span id="cooldown-timer">${remaining}</span>s)`;
                resendOtpButton.disabled = true;
            }

            updateCooldown();
            cooldownInterval = setInterval(updateCooldown, 1000);
        }

        function showRegistrationError(message) {
            registrationMessage.className = 'alert alert-danger';
            registrationMessage.textContent = message;
        }

        function setRegistrationEnabled(enabled) {
            registrationForm?.querySelectorAll('input, select, textarea, button').forEach((element) => {
                element.disabled = !enabled;
            });
        }

        registrationForm?.addEventListener('submit', async function(event) {
            event.preventDefault();
            if (!registrationForm.checkValidity()) {
                registrationForm.classList.add('was-validated');
                return;
            }
            registrationButton.disabled = true;
            loadingOverlay.classList.remove('d-none');
            loadingOverlay.setAttribute('aria-hidden', 'false');
            registrationMessage.className = 'alert d-none';
            try {
                const response = await fetch(registrationForm.action || window.location.href, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(registrationForm)
                });
                const result = await response.json();
                if (!result.success) {
                    showRegistrationError(result.message || (result.errors || ['Registration failed.']).join(' '));
                    return;
                }
                otpEmail.value = result.email;
                startOtpCooldown(result.otp_sent_at);
                modal.show();
            } catch (error) {
                showRegistrationError('Unable to submit registration. Please try again.');
            } finally {
                loadingOverlay.classList.add('d-none');
                loadingOverlay.setAttribute('aria-hidden', 'true');
                registrationButton.disabled = false;
            }
        });

        if (otpModal && otpForm) {
            const storedOtpSentAt = localStorage.getItem(`junkshop_otp_sent_at_${otpEmail?.value || 'pending'}`);
            const initialOtpSentAt = Number(storedOtpSentAt) || <?php echo $otpSentAt; ?>;
            if (initialOtpSentAt) {
                startOtpCooldown(initialOtpSentAt);
            }
            if (<?php echo $otpRequired ? 'true' : 'false'; ?>) {
                modal.show();
            }
            otpForm.addEventListener('submit', async function(event) {
                event.preventDefault();
                const message = document.getElementById('otpMessage');
                const code = otpForm.elements.otp_code.value.trim();
                if (!/^\d{6}$/.test(code)) {
                    message.className = 'alert alert-danger';
                    message.textContent = 'Enter the 6-digit OTP code.';
                    return;
                }
                const response = await fetch('verify_otp.php', { method: 'POST', headers: { 'Accept': 'application/json' }, body: new FormData(otpForm) });
                const result = await response.json();
                message.className = result.success ? 'alert alert-success' : 'alert alert-danger';
                message.textContent = result.message;
                if (result.success) {
                    modal.hide();
                    verificationSuccessMessage.textContent = result.message;
                    successModal.show();
                }
            });

            resendOtpButton?.addEventListener('click', async function() {
                const resendData = new FormData(otpForm);
                resendData.set('action', 'resend');
                resendOtpButton.disabled = true;
                const response = await fetch('verify_otp.php', { method: 'POST', headers: { 'Accept': 'application/json' }, body: resendData });
                const result = await response.json();
                const message = document.getElementById('otpMessage');
                message.className = result.success ? 'alert alert-success' : 'alert alert-danger';
                message.textContent = result.message;
                if (result.success) {
                    startOtpCooldown(result.otp_sent_at);
                } else if (result.remaining) {
                    startOtpCooldown(Math.floor(Date.now() / 1000) - (60 - result.remaining));
                }
            });
        }
        document.getElementById('btn-cancel-otp')?.addEventListener('click', async function() {
            const cancelData = new FormData(otpForm);
            cancelData.set('action', 'cancel');
            await fetch('verify_otp.php', { method: 'POST', headers: { 'Accept': 'application/json' }, body: cancelData });
            setRegistrationEnabled(true);
            registrationButton.disabled = false;
        });
        const usernameInput = document.getElementById('username');
        if (usernameInput) {
            usernameInput.addEventListener('input', function() {
                validateUsername(this);
            });
            usernameInput.addEventListener('blur', function() {
                validateUsername(this);
            });
        }

        ['business_name', 'owner_name'].forEach((id) => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('input', function() {
                    this.value = this.value.toUpperCase();
                });
            }
        });

        const mobileInput = document.getElementById('mobile_number');
        if (mobileInput) {
            mobileInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                validateMobileSuffix(this);
            });
            mobileInput.addEventListener('blur', function() {
                validateMobileSuffix(this);
            });

            const form = mobileInput.closest('form');
            if (form) {
                form.addEventListener('submit', function(event) {
                    if (!validateMobileSuffix(mobileInput)) {
                        event.preventDefault();
                        mobileInput.reportValidity();
                    }
                });
            }
        }

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

<?php require_once __DIR__ . '/../app/views/footer.php'; ?>
