<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Cache-Control: no-cache');

session_start();

$action = $_GET['action'] ?? '';

if ($action === 'login') {
    $username = $_GET['username'] ?? '';
    $password = $_GET['password'] ?? '';
    
    $imap = @imap_open('{mail.zenithkandel.com.np:993/imap/ssl}INBOX', $username, $password);
    if ($imap) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['password'] = $password;
        imap_close($imap);
        echo json_encode(['success' => true, 'username' => $username]);
    } else {
        echo json_encode(['error' => 'Invalid credentials: ' . imap_last_error()]);
    }
    exit;
}

if ($action === 'status') {
    echo json_encode([
        'session_authenticated' => isset($_SESSION['authenticated']),
        'session_username' => $_SESSION['username'] ?? 'none',
        'has_password' => !empty($_SESSION['password'])
    ]);
    exit;
}

// Try to connect using session credentials
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated']) {
    $username = $_SESSION['username'] ?? '';
    $password = $_SESSION['password'] ?? '';
    
    $imap = @imap_open('{mail.zenithkandel.com.np:993/imap/ssl}INBOX', $username, $password);
    if ($imap) {
        $folders = imap_list($imap, '{mail.zenithkandel.com.np:993/imap/ssl}', '*');
        echo json_encode(['success' => true, 'folders' => $folders]);
        imap_close($imap);
    } else {
        echo json_encode(['error' => 'Session credentials failed: ' . imap_last_error()]);
    }
} else {
    echo json_encode(['error' => 'Not logged in']);
}