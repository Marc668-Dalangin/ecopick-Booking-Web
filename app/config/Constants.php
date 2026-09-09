<?php
/**
 * Application Constants
 */

// Roles
define('ROLE_SELLER', 1);
define('ROLE_JUNKSHOP', 2);
define('ROLE_ADMIN', 3);

// Account Status
define('ACCOUNT_ACTIVE', 'active');
define('ACCOUNT_INACTIVE', 'inactive');
define('ACCOUNT_PENDING', 'pending');
define('ACCOUNT_REJECTED', 'rejected');

// Junkshop Approval Status
define('JUNKSHOP_PENDING', 'pending');
define('JUNKSHOP_APPROVED', 'approved');
define('JUNKSHOP_REJECTED', 'rejected');

// Session keys
define('SESSION_USER_ID', 'user_id');
define('SESSION_ROLE_ID', 'role_id');
define('SESSION_ROLE_NAME', 'role_name');
define('SESSION_EMAIL', 'email');
define('SESSION_FULL_NAME', 'full_name');

// Error messages
define('ERROR_INVALID_EMAIL', 'Please enter a valid email address');
define('ERROR_INVALID_PASSWORD', 'Password must be at least 8 characters');
define('ERROR_PASSWORD_MISMATCH', 'Passwords do not match');
define('ERROR_EMAIL_TAKEN', 'Email address is already registered');
define('ERROR_INVALID_CREDENTIALS', 'Invalid email or password');
define('ERROR_ACCOUNT_INACTIVE', 'This account is inactive');
define('ERROR_JUNKSHOP_PENDING', 'Your junkshop account is awaiting EcoPick approval');
define('ERROR_JUNKSHOP_REJECTED', 'Your junkshop account has been rejected');
