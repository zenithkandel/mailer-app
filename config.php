<?php
session_start();

define('IMAP_HOST', 'mail.yourdomain.com');
define('IMAP_PORT', 993);
define('IMAP_USER', 'your-email@yourdomain.com');
define('IMAP_PASS', 'your-password');

define('SMTP_HOST', 'mail.yourdomain.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@yourdomain.com');
define('SMTP_PASS', 'your-password');

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