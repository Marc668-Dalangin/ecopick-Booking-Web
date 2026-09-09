<?php
/**
 * UI Helper Functions
 */

class UI
{
    /**
     * Alert types
     */
    const ALERT_SUCCESS = 'success';
    const ALERT_ERROR = 'danger';
    const ALERT_WARNING = 'warning';
    const ALERT_INFO = 'info';

    /**
     * Display Bootstrap alert
     */
    public static function alert($message, $type = self::ALERT_INFO)
    {
        $validTypes = ['success', 'danger', 'warning', 'info'];
        $type = in_array($type, $validTypes) ? $type : self::ALERT_INFO;

        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }

    /**
     * Display success message
     */
    public static function success($message)
    {
        self::alert($message, self::ALERT_SUCCESS);
    }

    /**
     * Display error message
     */
    public static function error($message)
    {
        self::alert($message, self::ALERT_ERROR);
    }

    /**
     * Display form errors as alert list
     */
    public static function formErrors($errors)
    {
        if (empty($errors)) {
            return;
        }

        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
        echo '<strong>Please fix the following errors:</strong>';
        echo '<ul class="mb-0 mt-2">';

        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        echo '</ul>';
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }

    /**
     * Get Bootstrap class for form group with error
     */
    public static function formGroupClass($hasError = false)
    {
        return $hasError ? 'mb-3' : 'mb-3';
    }

    /**
     * Get Bootstrap class for form input with error
     */
    public static function inputClass($hasError = false)
    {
        return $hasError ? 'form-control is-invalid' : 'form-control';
    }

    /**
     * Display invalid feedback
     */
    public static function invalidFeedback($message)
    {
        echo '<div class="invalid-feedback">';
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo '</div>';
    }
}
