<?php
/**
 * CSRF Protection
 */

class CSRF
{
    private static $tokenLength = 32;
    private static $tokenName = '_csrf_token';

    /**
     * Generate CSRF token
     */
    public static function generateToken()
    {
        if (!isset($_SESSION[self::$tokenName])) {
            $_SESSION[self::$tokenName] = bin2hex(random_bytes(self::$tokenLength));
        }
        return $_SESSION[self::$tokenName];
    }

    /**
     * Get CSRF token for forms
     */
    public static function token()
    {
        return self::generateToken();
    }

    /**
     * Get CSRF token field HTML
     */
    public static function field()
    {
        return '<input type="hidden" name="' . self::$tokenName . '" value="' . self::token() . '">';
    }

    /**
     * Verify CSRF token
     */
    public static function verify($token = null)
    {
        if ($token === null) {
            $token = $_POST[self::$tokenName] ?? '';
        }

        if (!isset($_SESSION[self::$tokenName])) {
            return false;
        }

        return hash_equals($_SESSION[self::$tokenName], $token);
    }

    /**
     * Verify CSRF token and return error message if invalid
     */
    public static function verifyOrDie($token = null)
    {
        if (!self::verify($token)) {
            http_response_code(403);
            die(json_encode(['error' => 'Invalid security token']));
        }
    }
}
