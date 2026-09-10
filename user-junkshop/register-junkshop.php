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

        if ($result['success']) {
            $success = true;
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

                        <form method="POST" action="" novalidate>
                            <!-- CSRF Token -->
                            <?php echo CSRF::field(); ?>

                            <!-- Business Information -->
                            <h5 class="mb-3 fw-bold">Business Information</h5>

                            <!-- Business Name -->
                            <div class="mb-3">
                                <label for="business_name" class="form-label">Business Name <span class="text-danger">*</span></label>
                                <input 
                                    type="text" 
                                    class="form-control" 
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
                                    class="form-control" 
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
                                <label for="mobile_number" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">09</span>
                                    <input
                                        type="tel"
                                        class="form-control"
                                        id="mobile_number"
                                        name="mobile_number"
                                        value="<?php echo isset($_POST['mobile_number']) ? Validator::escape($_POST['mobile_number']) : ''; ?>"
                                        required
                                        inputmode="numeric"
                                        maxlength="9"
                                        pattern="[0-9]*"
                                        placeholder="123456789"
                                        aria-describedby="mobile_number_help"
                                    >
                                </div>
                                <div id="mobile_number_help" class="form-text">Enter the remaining 9 digits only. The 09 prefix is fixed.</div>
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
                            <button type="submit" class="btn btn-success w-100 mb-3">
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
        const usernameInput = document.getElementById('username');
        if (usernameInput) {
            usernameInput.addEventListener('input', function() {
                validateUsername(this);
            });
            usernameInput.addEventListener('blur', function() {
                validateUsername(this);
            });
        }

        const mobileInput = document.getElementById('mobile_number');
        if (mobileInput) {
            mobileInput.addEventListener('input', function() {
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
