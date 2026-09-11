<?php
require_once __DIR__ . '/../app/bootstrap.php';

function resetRedirect(string $message)
{
    Session::set('password_reset_error', $message);
    $token = rawurlencode(trim((string)($_POST['token'] ?? '')));
    $email = rawurlencode(strtolower(trim((string)($_POST['email'] ?? ''))));
    header('Location: ' . APP_URL . '/user-junkshop/reset-password.php?token=' . $token . '&email=' . $email);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verify($_POST['_csrf_token'] ?? '')) {
    resetRedirect('Invalid security token. Please try again.');
}

$token = trim((string)($_POST['token'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$password = (string)($_POST['password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');

if (!preg_match('/^[a-f0-9]{64}$/', $token) || !Validator::email($email)) {
    resetRedirect('This password reset link is invalid or has expired. Please request a new one.');
}

if (!Validator::password($password)) {
    resetRedirect('Password must be at least 8 characters.');
}

if ($password !== $confirmPassword) {
    resetRedirect('Passwords do not match.');
}

try {
    $db = null;
    $db = Database::getInstance();
    $db->beginTransaction();

    $reset = $db->query(
        'SELECT email FROM password_resets WHERE email = :email AND token = :token AND expires_at > UTC_TIMESTAMP() LIMIT 1 FOR UPDATE',
        ['email' => $email, 'token' => $token]
    )->fetch();

    if (!$reset) {
        $db->rollBack();
        resetRedirect('This password reset link is invalid or has expired. Please request a new one.');
    }

    $updated = $db->query(
        'UPDATE accounts SET password_hash = :password_hash WHERE email = :email AND account_role IN (\'seller\', \'junkshop\')',
        [
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'email' => $email,
        ]
    )->rowCount();

    if ($updated !== 1) {
        $db->rollBack();
        resetRedirect('The password could not be reset. Please request a new link.');
    }

    $db->query('DELETE FROM password_resets WHERE email = :email AND token = :token', ['email' => $email, 'token' => $token]);
    $db->commit();

    Session::set('password_reset_success', 'Your password has been reset successfully. Please log in with your new password.');
    header('Location: ' . APP_URL . '/user-junkshop/reset-password.php?reset=success');
    exit;
} catch (Throwable $exception) {
    if ($db instanceof Database) {
        $db->rollBack();
    }
    error_log('Password reset error: ' . $exception->getMessage());
    resetRedirect('We could not reset your password. Please try again later.');
}
