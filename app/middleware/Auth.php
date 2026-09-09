<?php
/**
 * Authentication Middleware
 */

class Auth
{
    /**
     * Check if user is authenticated
     */
    public static function check()
    {
        Session::start();
        if (!Session::isLoggedIn() || Session::isExpired()) {
            return false;
        }

        try {
            $account = Database::getInstance()->query(
                'SELECT account_status FROM accounts WHERE id = :account_id LIMIT 1',
                ['account_id' => Session::get(SESSION_USER_ID)]
            )->fetch();
        } catch (Throwable $exception) {
            error_log('Authentication status check failed: ' . $exception->getMessage());
            self::logout();
            return false;
        }

        if (!$account || $account['account_status'] !== 'active') {
            self::logout();
            return false;
        }

        return true;
    }

    /**
     * Redirect to login if not authenticated
     */
    public static function requireLogin()
    {
        if (!self::check()) {
            session_destroy();
            header('Location: ' . APP_URL . '/user-junkshop/login.php');
            exit;
        }
    }

    /**
     * Require specific role
     */
    public static function requireRole($role)
    {
        self::requireLogin();

        if (!Session::hasRole($role)) {
            http_response_code(403);
            die('Access denied');
        }
    }

    /**
     * Require roles (multiple)
     */
    public static function requireRoles($roles)
    {
        self::requireLogin();

        $userRole = Session::getRole();
        if (!in_array($userRole, $roles)) {
            http_response_code(403);
            die('Access denied');
        }
    }

    /**
     * Redirect to dashboard if already logged in
     */
    public static function redirectIfAuthenticated()
    {
        if (self::check()) {
            $role = Session::getRole();

            if ($role === 'admin') {
                header('Location: ' . APP_URL . '/admin/dashboard.php');
            } else if ($role === 'junkshop') {
                header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
            } else if ($role === 'seller') {
                header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
            }

            exit;
        }
    }

    /**
     * Login user
     */
    public static function login($userId, $roleId, $roleName, $email, $fullName)
    {
        Session::start();
        Session::login($userId, $roleId, $roleName, $email, $fullName);
    }

    /**
     * Logout user
     */
    public static function logout()
    {
        Session::logout();
    }

    /**
     * Get current user ID
     */
    public static function userId()
    {
        return Session::get(SESSION_USER_ID);
    }

    /**
     * Get current user role
     */
    public static function userRole()
    {
        return Session::get(SESSION_ROLE_NAME);
    }

    /**
     * Get current user email
     */
    public static function userEmail()
    {
        return Session::get(SESSION_EMAIL);
    }

    /**
     * Get current user full name
     */
    public static function userName()
    {
        return Session::get(SESSION_FULL_NAME);
    }
}
