<?php
/**
 * Application Bootstrap
 */

// Load configurations first so environment constants are available
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/config/Constants.php';

// Set error reporting after configuration is available
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../storage/error.log');

set_exception_handler(static function (Throwable $exception): void {
	error_log(sprintf(
		'Uncaught %s: %s in %s:%d',
		get_class($exception),
		$exception->getMessage(),
		$exception->getFile(),
		$exception->getLine()
	));

	if (!headers_sent()) {
		http_response_code(500);
	}

	echo 'EcoPick is temporarily unavailable. Please try again later.';
});

register_shutdown_function(static function (): void {
	$error = error_get_last();
	$fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
	if ($error !== null && in_array($error['type'], $fatalTypes, true)) {
		error_log(sprintf('Fatal PHP error: %s in %s:%d', $error['message'], $error['file'], $error['line']));
	}
});

// Load core classes
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Session.php';

// Load middleware
require_once __DIR__ . '/middleware/Auth.php';
require_once __DIR__ . '/middleware/CSRF.php';

// Load helpers
require_once __DIR__ . '/helpers/Validator.php';
require_once __DIR__ . '/helpers/UI.php';

// Load service layer
require_once __DIR__ . '/services/StatusLogger.php';
require_once __DIR__ . '/services/NotificationService.php';
require_once __DIR__ . '/services/MailerService.php';
require_once __DIR__ . '/controllers/FeeCalculator.php';

// Start session
Session::start();

// Set default timezone
date_default_timezone_set('UTC');
