<?php
// config/mail.php - Global SMTP Mail Configuration

if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.gmail.com');
}
if (!defined('SMTP_USER')) {
    define('SMTP_USER', 'ecopicklipacity@gmail.com');
}
if (!defined('SMTP_APP_PASSWORD')) {
    define('SMTP_APP_PASSWORD', 'zemkqmunllofeicq');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', 587);
}
if (!defined('SMTP_ENCRYPTION')) {
    define('SMTP_ENCRYPTION', 'tls');
}
