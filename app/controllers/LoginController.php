<?php
/**
 * Login Controller
 */

require_once __DIR__ . '/../../app/bootstrap.php';

class LoginController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Authenticate user
     */
    public function authenticate($input, $password)
    {
        // Validate inputs
        if (empty($input) || empty($password)) {
            return ['success' => false, 'error' => 'Email or username and password are required'];
        }

        try {
            $user = $this->db->query(
                "SELECT a.id, a.role_id, COALESCE(a.account_role, r.name) AS account_role,
                        a.email, a.username, a.password_hash, a.full_name, a.account_status,
                        r.name AS role_name,
                        jp.partnership_expires_at,
                            CASE WHEN r.name = 'junkshop'
                                    AND jp.partnership_expires_at IS NOT NULL
                                    AND jp.partnership_expires_at <> '0000-00-00 00:00:00'
                                    AND jp.partnership_expires_at <= CURRENT_TIMESTAMP
                                THEN 1 ELSE 0 END AS is_expired,
                        CASE WHEN r.name = 'junkshop' THEN (
                            SELECT approval_status FROM junkshop_profiles WHERE account_id = a.id
                        ) ELSE NULL END AS approval_status
                 FROM accounts a
                 JOIN roles r ON a.role_id = r.id
                 LEFT JOIN junkshop_profiles jp ON jp.account_id = a.id
                 WHERE a.email = :input_email OR a.username = :input_username
                 LIMIT 1",
                ['input_email' => $input, 'input_username' => $input]
            )->fetch();

            if (!$user) {
                $rejectedEmail = $this->db->query(
                    'SELECT email FROM rejected_emails WHERE email = :email LIMIT 1',
                    ['email' => $input]
                )->fetch();

                if ($rejectedEmail) {
                    return ['success' => false, 'error' => 'Your account application was rejected and deleted. Please make a new account again.'];
                }

                return ['success' => false, 'error' => 'Invalid email or password.'];
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'error' => 'Invalid email or password.'];
            }

            // A NULL or legacy zero date means no explicit expiration was configured.
            $expiryDate = trim((string) ($user['partnership_expires_at'] ?? ''));
            $hasExpiredPartnership = $user['role_name'] === 'junkshop'
                && $expiryDate !== ''
                && $expiryDate !== '0000-00-00'
                && $expiryDate !== '0000-00-00 00:00:00'
                && (int) ($user['is_expired'] ?? 0) === 1;

            // Expired junkshops may log in; only rejected accounts remain blocked.
            $accountStatus = strtolower(trim((string) ($user['account_status'] ?? 'active')));
            if ($accountStatus === 'rejected') {
                return ['success' => false, 'error' => 'This account has been rejected'];
            }
            if (in_array($accountStatus, ['inactive', 'suspended'], true) && !$hasExpiredPartnership) {
                return ['success' => false, 'error' => 'This account is inactive'];
            }

            // Check junkshop approval status
            if ($user['role_name'] === 'junkshop') {
                if ($user['approval_status'] === 'pending') {
                    return ['success' => false, 'error' => 'Your junkshop account is awaiting EcoPick approval', 'pending' => true];
                }

                if ($user['approval_status'] === 'rejected') {
                    return ['success' => false, 'error' => 'Your junkshop account has been rejected'];
                }
            }

            // Login successful
            Auth::login($user['id'], $user['role_id'], $user['role_name'], $user['email'], $user['full_name'], $hasExpiredPartnership);

            return [
                'success' => true,
                'message' => 'Login successful',
                'role' => $user['role_name']
            ];
        } catch (PDOException $e) {
            error_log(sprintf('Login database error [%s]: %s', $e->getCode(), $e->getMessage()));
            return ['success' => false, 'error' => 'Unable to complete login right now. Please try again later.'];
        } catch (Throwable $e) {
            error_log(sprintf('Unexpected login error [%s]: %s', get_class($e), $e->getMessage()));
            return ['success' => false, 'error' => 'Unable to complete login right now. Please try again later.'];
        }
    }
}
