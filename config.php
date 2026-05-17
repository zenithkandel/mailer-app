<?php
session_start();

define('SMTP_HOST', 'mail.zenithkandel.com.np');
define('SMTP_PORT', 465);
define('SMTP_USER', 'admin@zenithkandel.com.np');
define('SMTP_PASS', '8038@Zenith');
define('SMTP_FROM', 'admin@zenithkandel.com.np');

define('IMAP_HOST', 'mail.zenithkandel.com.np');
define('IMAP_PORT', 993);
define('IMAP_USER', 'admin@zenithkandel.com.np');
define('IMAP_PASS', '8038@Zenith');

define('ADMIN_EMAIL', 'admin@zenithkandel.com.np');
define('ADMIN_PASS', '8038@Zenith');

define('SENT_LOG_FILE', __DIR__ . '/sent_log.json');

function isLoggedIn()
{
    return isset($_SESSION['user']) && $_SESSION['user'] === ADMIN_EMAIL;
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}