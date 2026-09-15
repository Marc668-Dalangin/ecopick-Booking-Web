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
        if (!Session::isLoggedIn()) {
            return false;
        }

        if (Session::isExpired()) {
            self::logout();
            return false;
        }

        try {
            $account = Database::getInstance()->query(
                'SELECT a.account_status, COALESCE(a.account_role, r.name) AS account_role,
                        jp.partnership_expires_at,
                        CASE WHEN COALESCE(a.account_role, r.name) = \'junkshop\'
                                  AND jp.partnership_expires_at IS NOT NULL
                                  AND jp.partnership_expires_at <> \'0000-00-00 00:00:00\'
                                  AND jp.partnership_expires_at <= CURRENT_TIMESTAMP
                             THEN 1 ELSE 0 END AS is_expired
                 FROM accounts a
                 JOIN roles r ON r.id = a.role_id
                 LEFT JOIN junkshop_profiles jp ON jp.account_id = a.id
                 WHERE a.id = :account_id LIMIT 1',
                ['account_id' => Session::get(SESSION_USER_ID)]
            )->fetch();
        } catch (Throwable $exception) {
            error_log('Authentication status check failed: ' . $exception->getMessage());
            self::logout();
            return false;
        }

        $isExpiredJunkshop = $account
            && $account['account_role'] === 'junkshop'
            && (int) ($account['is_expired'] ?? 0) === 1;
        Session::set('is_expired', $isExpiredJunkshop);

        if (!$account || ($account['account_status'] !== 'active' && !$isExpiredJunkshop)) {
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
            self::logout();
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
                header('Location: ' . APP_URL . '/admin-private-dnstl/dashboard.php');
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
    public static function login($userId, $roleId, $roleName, $email, $fullName, $isExpired = false)
    {
        Session::start();
        Session::login($userId, $roleId, $roleName, $email, $fullName, $isExpired);
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
        if (!self::check()) {
            return null;
        }

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
