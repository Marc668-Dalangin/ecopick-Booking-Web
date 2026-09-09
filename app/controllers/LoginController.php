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
    public function authenticate($email, $password)
    {
        // Validate inputs
        if (empty($email) || empty($password)) {
            return ['success' => false, 'error' => 'Email and password are required'];
        }

        if (!Validator::email($email)) {
            return ['success' => false, 'error' => 'Invalid email format'];
        }

        try {
            $stmt = $this->db->call('sp_get_login_user_by_email', [$email]);
            $user = $stmt->fetch();
            $this->db->closeProcedureCursor($stmt);

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
