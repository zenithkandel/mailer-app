<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

session_start();

$result = [
    'session_id' => session_id(),
    'session_status' => session_status(),
    'authenticated' => isset($_SESSION['authenticated']),
    'username_set' => isset($_SESSION['username']),
    'password_set' => !empty($_SESSION['password']),
];

if (!empty($_SESSION['username']) && !empty($_SESSION['password'])) {
    $imap = @imap_open('{mail.zenithkandel.com.np:993/imap/ssl}INBOX', $_SESSION['username'], $_SESSION['password']);
    if ($imap) {
        $result['imap_test'] = 'SUCCESS';
        $folders = imap_list($imap, '{mail.zenithkandel.com.np:993/imap/ssl}', '*');
        $result['folders'] = $folders;
        imap_close($imap);
    } else {
        $result['imap_test'] = 'FAILED';
        $result['error'] = imap_last_error();
    }
}

echo json_encode($result, JSON_PRETTY_PRINT);