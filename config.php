<?php
session_start();

define('IMAP_HOST', 'mail.zenithkandel.com.np');
define('IMAP_PORT', 993);
define('IMAP_USER', 'admin@zenithkandel.com.np');
define('IMAP_PASS', '8038@Zenith');

define('SMTP_HOST', 'mail.zenithkandel.com.np');
define('SMTP_PORT', 465);
define('SMTP_USER', 'admin@zenithkandel.com.np');
define('SMTP_PASS', '8038@Zenith');
define('SMTP_FROM', 'admin@zenithkandel.com.np');

define('ADMIN_EMAIL', 'admin@zenithkandel.com.np');
define('ADMIN_PASS', '8038@Zenith');

define('CONFIG_FILE', __DIR__ . '/user_config.json');

function isLoggedIn() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function getUserConfig() {
    if (!file_exists(CONFIG_FILE)) {
        return ['senderName' => '', 'signatures' => []];
    }
    $content = file_get_contents(CONFIG_FILE);
    $config = json_decode($content, true);
    return $config ?: ['senderName' => '', 'signatures' => []];
}

function saveUserConfig($config) {
    return file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT)) !== false;
}