<?php
require_once __DIR__ . '/../app/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$failure = ['success' => false, 'message' => 'Invalid or expired OTP code. Please try again.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode($failure, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!CSRF::verify($_POST['_csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please try again.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_POST['action'] ?? '') === 'cancel') {
    Session::unset('pending_registration');
    Session::unset('last_otp_sent_at');
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_POST['action'] ?? '') === 'resend') {
    try {
        $pending = Session::get('pending_registration');
        if (!is_array($pending)
            || !isset($pending['type'], $pending['email'], $pending['full_name'])
            || !in_array($pending['type'], ['seller', 'junkshop'], true)) {
            echo json_encode(['success' => false, 'message' => 'Your registration session has expired. Please register again.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $now = time();
        $lastSent = (int) ($pending['last_otp_sent_at'] ?? Session::get('last_otp_sent_at', 0));
        if (($now - $lastSent) < 60) {
            $remaining = 60 - ($now - $lastSent);
            echo json_encode(['success' => false, 'message' => "Please wait {$remaining} seconds before requesting a new code.", 'remaining' => $remaining], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpExpiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        if (!MailerService::sendRegistrationOtp($pending['email'], $pending['full_name'], $otp)) {
            echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please check your email address.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $sentAt = time();
        $pending['otp_code'] = $otp;
        $pending['otp_expires_at'] = $otpExpiresAt;
        $pending['otp_expires_timestamp'] = strtotime($otpExpiresAt);
        $pending['last_otp_sent_at'] = $sentAt;
        Session::set('pending_registration', $pending);
        Session::set('last_otp_sent_at', $sentAt);

        echo json_encode(['success' => true, 'message' => 'A new verification code has been sent to your email.', 'otp_sent_at' => $sentAt], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $exception) {
        error_log('OTP resend error: ' . $exception->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to resend the OTP right now. Please try again.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$otpCode = trim((string) ($_POST['otp_code'] ?? ''));
if (!Validator::email($email) || !preg_match('/^\d{6}$/', $otpCode)) {
    echo json_encode($failure, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pending = Session::get('pending_registration');
    if (!is_array($pending)
        || !isset($pending['type'], $pending['email'], $pending['username'], $pending['password_hash'], $pending['full_name'], $pending['mobile_number'], $pending['otp_code'], $pending['otp_expires_timestamp'])
        || $pending['email'] !== $email
        || !in_array($pending['type'], ['seller', 'junkshop'], true)
        || $otpCode !== trim((string) $pending['otp_code'])
        || time() > (int) $pending['otp_expires_timestamp']) {
        echo json_encode($failure, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db = Database::getInstance();
    $existing = $db->query(
        'SELECT id FROM accounts WHERE email = :email OR username = :username LIMIT 1',
        ['email' => $pending['email'], 'username' => $pending['username']]
    )->fetch();
    if ($existing) {
        echo json_encode($failure, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db->beginTransaction();
    $accountStatus = $pending['type'] === 'seller' ? ACCOUNT_ACTIVE : ACCOUNT_PENDING;
    $db->query(
        "INSERT INTO accounts
            (role_id, account_role, email, username, password_hash, full_name, mobile_number, account_status, is_email_verified)
         VALUES
            ((SELECT id FROM roles WHERE name = :role), :account_role, :email, :username, :password_hash, :full_name, :mobile_number, :account_status, 1)",
        [
            'role' => $pending['type'],
            'account_role' => $pending['type'],
            'email' => $pending['email'],
            'username' => $pending['username'],
            'password_hash' => $pending['password_hash'],
            'full_name' => $pending['full_name'],
            'mobile_number' => $pending['mobile_number'],
            'account_status' => $accountStatus,
        ]
    );
    $accountId = (int) $db->getPDO()->lastInsertId();

    if ($pending['type'] === 'seller') {
        $db->query(
            'INSERT INTO sellers (account_id, address, barangay, last_profile_edit) VALUES (:account_id, :address, :barangay, NULL)',
            [
                'account_id' => $accountId,
                'address' => $pending['address'],
                'barangay' => $pending['barangay'],
            ]
        );
    } else {
        $db->query(
            "INSERT INTO junkshop_profiles
                (account_id, business_name, owner_name, complete_address, operating_schedule, business_permit_reference, approval_status, last_profile_edit)
             VALUES (:account_id, :business_name, :owner_name, :complete_address, :operating_schedule, :permit_reference, 'pending', NULL)",
            [
                'account_id' => $accountId,
                'business_name' => $pending['business_name'],
                'owner_name' => $pending['owner_name'],
                'complete_address' => $pending['complete_address'],
                'operating_schedule' => $pending['operating_schedule'],
                'permit_reference' => $pending['business_permit_reference'],
            ]
        );
        $db->query('DELETE FROM rejected_emails WHERE email = :email', ['email' => $pending['email']]);
    }

    $db->commit();
    Session::unset('pending_registration');
    Session::unset('last_otp_sent_at');

    $message = $pending['type'] === 'seller'
        ? 'Email verified successfully! Your account has been created. You can now proceed to log in.'
        : 'Email verified successfully! Your junkshop registration has been submitted and is currently pending approval by the Admin. Please wait for Admin activation before logging in.';

    echo json_encode([
        'success' => true,
        'account_type' => $pending['type'],
        'message' => $message,
        'redirect' => 'login.php'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    if (isset($db) && $db->getPDO()->inTransaction()) {
        $db->rollBack();
    }
    error_log('OTP verification error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to verify the OTP right now. Please try again.'], JSON_UNESCAPED_UNICODE);
}