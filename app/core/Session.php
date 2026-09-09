<?php
/**
 * Session Manager
 */

class Session
{
    /**
     * Start session securely
     */
    public static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session options before the session is started
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_only_cookies', 1);
            session_start();
        }
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn()
    {
        return isset($_SESSION[SESSION_USER_ID]);
    }

    /**
     * Get session value
     */
    public static function get($key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Set session value
     */
    public static function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Unset session value
     */
    public static function unset($key)
    {
        unset($_SESSION[$key]);
    }

    /**
     * Login user - set session and regenerate ID
     */
    public static function login($userId, $roleId, $roleName, $email, $fullName)
    {
        // Regenerate session ID for security
        session_regenerate_id(true);

        $_SESSION[SESSION_USER_ID] = $userId;
        $_SESSION[SESSION_ROLE_ID] = $roleId;
        $_SESSION[SESSION_ROLE_NAME] = $roleName;
        $_SESSION[SESSION_EMAIL] = $email;
        $_SESSION[SESSION_FULL_NAME] = $fullName;
        $_SESSION['login_time'] = time();
    }

    /**
     * Logout user
     */
    public static function logout()
    {
        // Clear all session data
        $_SESSION = [];

        // Destroy the session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
    }

    /**
     * Check if session has expired
     */
    public static function isExpired()
    {
        if (!isset($_SESSION['login_time'])) {
            return true;
        }

        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            return true;
        }

        return false;
    }

    /**
     * Get user role
     */
    public static function getRole()
    {
        return self::get(SESSION_ROLE_NAME);
    }

    /**
     * Check if user has a specific role
     */
    public static function hasRole($roleName)
    {
        return self::getRole() === $roleName;
    }
}
