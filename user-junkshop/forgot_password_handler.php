<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../config/mail.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function recoveryRedirect(string $message, string $type = 'info')
{
    Session::set('password_recovery_message', $message);
    Session::set('password_recovery_message_type', $type);
    header('Location: ' . APP_URL . '/user-junkshop/forgot-password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verify($_POST['_csrf_token'] ?? '')) {
    recoveryRedirect('Invalid security token. Please try again.', 'danger');
}

$email = strtolower(trim((string)($_POST['email'] ?? '')));
if (!Validator::email($email)) {
    recoveryRedirect('Please enter a valid registered email address.', 'danger');
}

try {
    $db = Database::getInstance();
    $account = $db->query(
        'SELECT email, full_name FROM accounts WHERE email = :email AND account_role IN (\'seller\', \'junkshop\') LIMIT 1',
        ['email' => $email]
    )->fetch();

    if (!$account) {
        recoveryRedirect('If an account exists for that email, a recovery link will be sent shortly.');
    }

    $token = bin2hex(random_bytes(32));
    $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+1 hour')->format('Y-m-d H:i:s');

    $db->query('DELETE FROM password_resets WHERE email = :email', ['email' => $email]);
    $db->query(
        'INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires_at)',
        ['email' => $email, 'token' => $token, 'expires_at' => $expiresAt]
    );

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Composer autoloader not found.');
    }
    require_once $autoload;

    $mailer = new PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = SMTP_HOST;
    $mailer->SMTPAuth = true;
    $mailer->Username = SMTP_USER;
    $mailer->Password = SMTP_APP_PASSWORD;
    $mailer->SMTPSecure = strtolower(SMTP_ENCRYPTION) === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Port = SMTP_PORT;
    $mailer->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];
    $mailer->setFrom(SMTP_USER, 'EcoPick');
    $mailer->addAddress($account['email'], $account['full_name']);
    $mailer->isHTML(true);
    $mailer->Subject = 'EcoPick Password Reset';
    $resetLink = APP_URL . '/user-junkshop/reset-password.php?token=' . rawurlencode($token) . '&email=' . rawurlencode($email);
    $mailer->Body = '<p>Hello ' . Validator::escape($account['full_name']) . ',</p>'
        . '<p>Click the link below to reset your EcoPick password. This link expires in one hour.</p>'
        . '<p><a href="' . Validator::escape($resetLink) . '">Reset your password</a></p>'
        . '<p>If you did not request this, you can ignore this email.</p>';
    $mailer->AltBody = "Reset your EcoPick password: {$resetLink}\n\nThis link expires in one hour.";
    $mailer->send();

    recoveryRedirect('If an account exists for that email, a recovery link will be sent shortly.');
} catch (Exception $exception) {
    error_log('Password recovery email error: ' . $exception->getMessage());
    recoveryRedirect('We could not send the recovery email. Please try again later.', 'danger');
} catch (Throwable $exception) {
    error_log('Password recovery error: ' . $exception->getMessage());
    recoveryRedirect('We could not process your request. Please try again later.', 'danger');
}
