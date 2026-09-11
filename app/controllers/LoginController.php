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
                        CASE WHEN r.name = 'junkshop' THEN (
                            SELECT approval_status FROM junkshop_profiles WHERE account_id = a.id
                        ) ELSE NULL END AS approval_status
                 FROM accounts a
                 JOIN roles r ON a.role_id = r.id
                 WHERE a.email = :input_email OR a.username = :input_username
                 LIMIT 1",
                ['input_email' => $input, 'input_username' => $input]
            )->fetch();

            if (!$user) {
                return ['success' => false, 'error' => 'Invalid email or password'];
            }

            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'error' => 'Invalid email or password'];
            }

            // Check account status
            if ($user['account_status'] === 'inactive') {
                return ['success' => false, 'error' => 'This account is inactive'];
            }

            if ($user['account_status'] === 'rejected') {
                return ['success' => false, 'error' => 'This account has been rejected'];
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
            Auth::login($user['id'], $user['role_id'], $user['role_name'], $user['email'], $user['full_name']);

            return [
                'success' => true,
                'message' => 'Login successful',
                'role' => $user['role_name']
            ];
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'An error occurred during login'];
        }
    }
}
