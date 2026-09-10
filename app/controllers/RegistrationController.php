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
        $firstName = trim((string)($data['first_name'] ?? ''));
        $lastName = trim((string)($data['last_name'] ?? ''));
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

            $stmt = $this->db->call('sp_register_seller', [
                $data['full_name'],
                trim((string) ($data['username'] ?? '')),
                $data['email'],
                $data['mobile_number'],
                password_hash($data['password'], PASSWORD_BCRYPT),
                $data['address'],
                $data['barangay'],
            ]);

            $result = $stmt->fetch();
            $this->db->closeProcedureCursor($stmt);

            if (($result['p_result'] ?? '') === 'success') {
                return ['success' => true, 'message' => 'Registration successful. Please login.'];
            }

            return ['success' => false, 'errors' => [($result['p_result'] ?? 'Registration failed')]];
        } catch (Throwable $e) {
            error_log('Registration error: ' . $e->getMessage());
            return ['success' => false, 'errors' => [$e->getMessage()]];
        }
    }

    public function registerJunkshop($data)
    {
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

            $stmt = $this->db->call('sp_register_junkshop', [
                $data['business_name'],
                $data['owner_name'],
                trim((string) ($data['username'] ?? '')),
                $data['email'],
                $data['mobile_number'],
                $data['complete_address'],
                $data['operating_schedule'],
                $data['business_permit_reference'],
                password_hash($data['password'], PASSWORD_BCRYPT),
            ]);

            $result = $stmt->fetch();
            $this->db->closeProcedureCursor($stmt);

            if (($result['p_result'] ?? '') === 'success') {
                return ['success' => true, 'message' => 'Registration successful. Please login.'];
            }

            return ['success' => false, 'errors' => [($result['p_result'] ?? 'Registration failed')]];
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
        }

        if (!Validator::required($lastName)) {
            $errors[] = 'Last name is required';
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
