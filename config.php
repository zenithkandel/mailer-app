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
define('USER_CONFIG_FILE', __DIR__ . '/user_config.json');

function getUserConfig() {
    if (file_exists(USER_CONFIG_FILE)) {
        return json_decode(file_get_contents(USER_CONFIG_FILE), true) ?: [];
    }
    return ['senderName' => '', 'signatures' => []];
}

function saveUserConfig($config) {
    file_put_contents(USER_CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
}

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