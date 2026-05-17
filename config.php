<?php
define('MAIL_HOST', 'mail.zenithkandel.com.np');
define('MAIL_USER', 'admin@zenithkandel.com.np');
define('MAIL_PASS', 'YOUR_PASSWORD_HERE');
define('IMAP_PORT', 993);
define('SMTP_PORT', 465);
define('POP3_PORT', 995);
define('SMTP_FROM', 'admin@zenithkandel.com.np');
define('SMTP_FROM_NAME', 'WebMail User');

session_start();

function isLoggedIn() {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        return false;
    }
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 86400)) {
        session_destroy();
        return false;
    }
    return true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getImapConnection($folder = '') {
    $mailbox = sprintf(
        '{%s:%d/imap/ssl/novalidate-cert}%s',
        MAIL_HOST,
        IMAP_PORT,
        $folder
    );
    $imap = imap_open($mailbox, MAIL_USER, MAIL_PASS, OP_READONLY);
    if (!$imap) {
        throw new Exception('Failed to connect to IMAP server');
    }
    return $imap;
}

function getWritableImapConnection($folder = '') {
    $mailbox = sprintf(
        '{%s:%d/imap/ssl/novalidate-cert}%s',
        MAIL_HOST,
        IMAP_PORT,
        $folder
    );
    $imap = imap_open($mailbox, MAIL_USER, MAIL_PASS, CL_READWRITE);
    if (!$imap) {
        throw new Exception('Failed to connect to IMAP server');
    }
    return $imap;
}