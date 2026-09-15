<?php
/**
 * Registration Controller
 */

require_once __DIR__ . '/../../app/bootstrap.php';

class RegistrationController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function registerSeller($data)
    {
        $data['email'] = strtolower(trim((string) ($data['email'] ?? '')));
        $firstName = mb_strtoupper(trim((string)($data['first_name'] ?? '')), 'UTF-8');
        $lastName = mb_strtoupper(trim((string)($data['last_name'] ?? '')), 'UTF-8');
        $data['first_name'] = $firstName;
        $data['last_name'] = $lastName;
        $suffix = preg_replace('/\D+/', '', trim((string)($data['mobile_number'] ?? '')));
        $data['mobile_number_input'] = $suffix;
        $data['full_name'] = trim($firstName . ' ' . $lastName);
        $data['mobile_number'] = Validator::normalizeMobileNumber($suffix);

        $errors = $this->validateSellerForm($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            if ($this->usernameExists($data['username'])) {
                return ['success' => false, 'errors' => ['Username already registered']];
            }

            $existing = $this->db->query(
                'SELECT id FROM accounts WHERE email = :email OR username = :username LIMIT 1',
                ['email' => $data['email'], 'username' => trim((string) ($data['username'] ?? ''))]
            )->fetch();
            if ($existing) {
                return ['success' => false, 'errors' => ['Email or username already registered']];
            }

            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $otpExpiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            if (!MailerService::sendRegistrationOtp($data['email'], $data['full_name'], $otp)) {
                return ['success' => false, 'otp_send_failed' => true, 'errors' => ['Failed to send OTP. Please check your email address.']];
            }

            Session::set('pending_registration', [
                'type' => 'seller',
                'email' => $data['email'],
                'username' => trim((string) $data['username']),
                'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
                'full_name' => $data['full_name'],
                'mobile_number' => $data['mobile_number'],
                'address' => trim((string) $data['address']),
                'barangay' => trim((string) $data['barangay']),
                'otp_code' => $otp,
                'otp_expires_at' => $otpExpiresAt,
                'otp_expires_timestamp' => strtotime($otpExpiresAt),
            ]);

            return ['success' => true, 'otp_required' => true, 'email' => $data['email']];
        } catch (Throwable $e) {
            error_log('Registration error: ' . $e->getMessage());
            return ['success' => false, 'errors' => [$e->getMessage()]];
        }
    }

    public function registerJunkshop($data)
    {
        $data['email'] = strtolower(trim((string) ($data['email'] ?? '')));
        $data['business_name'] = mb_strtoupper(trim((string)($data['business_name'] ?? '')), 'UTF-8');
        $data['owner_name'] = mb_strtoupper(trim((string)($data['owner_name'] ?? '')), 'UTF-8');
        $suffix = preg_replace('/\D+/', '', trim((string)($data['mobile_number'] ?? '')));
        $data['mobile_number_input'] = $suffix;
        $data['mobile_number'] = Validator::normalizeMobileNumber($suffix);

        $errors = $this->validateJunkshopForm($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            if ($this->usernameExists($data['username'])) {
                return ['success' => false, 'errors' => ['Username already registered']];
            }

            $existing = $this->db->query(
                'SELECT id FROM accounts WHERE email = :email OR username = :username LIMIT 1',
                ['email' => $data['email'], 'username' => trim((string) ($data['username'] ?? ''))]
            )->fetch();
            if ($existing) {
                return ['success' => false, 'errors' => ['Email or username already registered']];
            }

            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $otpExpiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            if (!MailerService::sendRegistrationOtp($data['email'], $data['owner_name'], $otp)) {
                return ['success' => false, 'otp_send_failed' => true, 'errors' => ['Failed to send OTP. Please check your email address.']];
            }

            Session::set('pending_registration', [
                'type' => 'junkshop',
                'email' => $data['email'],
                'username' => trim((string) $data['username']),
                'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
                'full_name' => $data['owner_name'],
                'mobile_number' => $data['mobile_number'],
                'business_name' => $data['business_name'],
                'owner_name' => $data['owner_name'],
                'complete_address' => trim((string) $data['complete_address']),
                'operating_schedule' => $data['operating_schedule'],
                'business_permit_reference' => trim((string) $data['business_permit_reference']),
                'otp_code' => $otp,
                'otp_expires_at' => $otpExpiresAt,
                'otp_expires_timestamp' => strtotime($otpExpiresAt),
            ]);

            return ['success' => true, 'otp_required' => true, 'email' => $data['email']];
        } catch (Throwable $e) {
            error_log('Registration error: ' . $e->getMessage());
            return ['success' => false, 'errors' => [$e->getMessage()]];
        }
    }

    private function usernameExists($username)
    {
        return (int) $this->db->query(
            'SELECT COUNT(*) FROM accounts WHERE username = :username',
            ['username' => trim((string) $username)]
        )->fetchColumn() > 0;
    }

    private function validateSellerForm($data)
    {
        $errors = [];

        $firstName = trim((string)($data['first_name'] ?? ''));
        $lastName = trim((string)($data['last_name'] ?? ''));

        if (!Validator::required($firstName)) {
            $errors[] = 'First name is required';
        } elseif (preg_match('/^[a-zA-Z\s]+$/', $firstName) !== 1) {
            $errors[] = 'First name may contain letters and spaces only';
        }

        if (!Validator::required($lastName)) {
            $errors[] = 'Last name is required';
        } elseif (preg_match('/^[a-zA-Z\s]+$/', $lastName) !== 1) {
            $errors[] = 'Last name may contain letters and spaces only';
        }

        if (!Validator::required($data['full_name'] ?? '')) {
            $errors[] = 'Full name is required';
        }

        if (!Validator::required($data['username'] ?? '')) {
            $errors[] = 'Username is required';
        } elseif (!Validator::username($data['username'])) {
            $errors[] = 'Username must be at least 5 characters, contain no spaces, and use an uppercase letter only as its first character';
        }

        if (!Validator::required($data['email'] ?? '')) {
            $errors[] = 'Email is required';
        } elseif (!Validator::email($data['email'])) {
            $errors[] = 'Email is invalid';
        }

        if (!Validator::required($data['mobile_number_input'] ?? '')) {
            $errors[] = 'Mobile number is required';
        } elseif (!Validator::mobileSuffix($data['mobile_number_input'])) {
            $errors[] = 'Mobile number must contain exactly 9 digits after 09';
        } elseif (!Validator::mobileNumber($data['mobile_number'])) {
            $errors[] = 'Mobile number must start with 09 and contain 11 digits';
        }

        if (!Validator::required($data['address'] ?? '')) {
            $errors[] = 'Address is required';
        }

        if (!Validator::required($data['barangay'] ?? '')) {
            $errors[] = 'Barangay is required';
        }

        if (!Validator::required($data['password'] ?? '')) {
            $errors[] = 'Password is required';
        } elseif (!Validator::password($data['password'])) {
            $errors[] = 'Password must be at least 8 characters';
        }

        if (!Validator::required($data['confirm_password'] ?? '')) {
            $errors[] = 'Confirm password is required';
        } elseif (($data['password'] ?? '') !== ($data['confirm_password'] ?? '')) {
            $errors[] = 'Passwords do not match';
        }

        if (!isset($data['terms']) || $data['terms'] !== 'on') {
            $errors[] = 'You must agree to the terms and conditions';
        }

        return $errors;
    }

    private function validateJunkshopForm($data)
    {
        $errors = [];

        if (!Validator::required($data['business_name'] ?? '')) {
            $errors[] = 'Business name is required';
        }

        if (!Validator::required($data['owner_name'] ?? '')) {
            $errors[] = 'Owner name is required';
        }

        if (!Validator::required($data['username'] ?? '')) {
            $errors[] = 'Username is required';
        } elseif (!Validator::username($data['username'])) {
            $errors[] = 'Username must be at least 5 characters, contain no spaces, and use an uppercase letter only as its first character';
        }

        if (!Validator::required($data['email'] ?? '')) {
            $errors[] = 'Email is required';
        } elseif (!Validator::email($data['email'])) {
            $errors[] = 'Email is invalid';
        }

        if (!Validator::required($data['mobile_number_input'] ?? '')) {
            $errors[] = 'Mobile number is required';
        } elseif (!ctype_digit((string) $data['mobile_number_input'])) {
            $errors[] = 'Mobile number must contain digits only';
        } elseif (!Validator::mobileSuffix($data['mobile_number_input'])) {
            $errors[] = 'Mobile number must contain exactly 9 digits after 09';
        } elseif (!Validator::mobileNumber($data['mobile_number'])) {
            $errors[] = 'Mobile number must start with 09 and contain 11 digits';
        }

        if (!Validator::required($data['complete_address'] ?? '')) {
            $errors[] = 'Complete address is required';
        }

        if (!Validator::required($data['operating_schedule'] ?? '')) {
            $errors[] = 'Operating schedule is required';
        } else {
            $schedule = trim((string)($data['operating_schedule'] ?? ''));
            $hasPipe = strpos($schedule, '|') !== false;
            $hasTimeRange = preg_match('/\d{1,2}:\d{2}\s*(?:AM|PM)\s*(?:-|–)\s*\d{1,2}:\d{2}\s*(?:AM|PM)/i', $schedule) === 1;
            $hasDayText = preg_match('/(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday|[A-Za-z]+(?:\s*(?:-|–)\s*[A-Za-z]+)?(?:\s*,\s*[A-Za-z]+)*)/i', $schedule) === 1;

            if (!$hasPipe || !$hasTimeRange || !$hasDayText) {
                $errors[] = 'Operating schedule format is invalid. Please choose valid days and times.';
            }
        }

        if (!Validator::required($data['business_permit_reference'] ?? '')) {
            $errors[] = 'Business permit reference is required';
        }

        if (!Validator::required($data['password'] ?? '')) {
            $errors[] = 'Password is required';
        } elseif (!Validator::password($data['password'])) {
            $errors[] = 'Password must be at least 8 characters';
        }

        if (!Validator::required($data['confirm_password'] ?? '')) {
            $errors[] = 'Confirm password is required';
        } elseif (($data['password'] ?? '') !== ($data['confirm_password'] ?? '')) {
            $errors[] = 'Passwords do not match';
        }

        if (!isset($data['terms']) || $data['terms'] !== 'on') {
            $errors[] = 'You must agree to the terms and conditions';
        }

        return $errors;
    }
}
