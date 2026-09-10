<?php
/**
 * Validation Helper
 */

class Validator
{
    private static $errors = [];

    /**
     * Validate email
     */
    public static function email($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate password strength
     */
    public static function password($password)
    {
        return strlen($password) >= 8;
    }

    public static function username($username)
    {
        return preg_match('/^(?=.{5,100}$)(?!.*\s)[A-Z]?[a-z0-9\W_]+$/D', (string) $username) === 1;
    }

    /**
     * Normalize and validate a Philippine mobile number in the exact format 09xxxxxxxxx.
     */
    public static function mobileNumber($number)
    {
        return preg_match('/^09\d{9}$/', (string) $number) === 1;
    }

    /**
     * Validate the editable registration suffix exactly as submitted by the form.
     */
    public static function mobileSuffix($number)
    {
        $digits = preg_replace('/\D+/', '', trim((string) $number));
        return preg_match('/^\d{9}$/', $digits) === 1;
    }

    /**
     * Return a valid 11-digit mobile number that always starts with 09.
     */
    public static function normalizeMobileNumber($number)
    {
        $digits = preg_replace('/\D+/', '', trim((string) $number));
        if ($digits === '') {
            return '';
        }

        if (preg_match('/^09\d{9}$/', $digits) === 1) {
            return $digits;
        }

        $digits = preg_replace('/^09/', '', $digits, 1);
        if (preg_match('/^\d{9}$/', $digits) === 1) {
            return '09' . $digits;
        }

        return '';
    }

    /**
     * Validate required field
     */
    public static function required($value)
    {
        return !empty(trim((string) $value));
    }

    /**
     * Sanitize string
     */
    public static function sanitizeString($string)
    {
        return htmlspecialchars(trim((string) $string), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize email
     */
    public static function sanitizeEmail($email)
    {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }

    /**
     * Escape for HTML display
     */
    public static function escape($data)
    {
        return htmlspecialchars((string) $data, ENT_QUOTES, 'UTF-8');
    }
}
