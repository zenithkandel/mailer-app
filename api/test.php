<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Simulate logged in session
session_start();
$_SESSION['authenticated'] = true;
$_SESSION['username'] = 'admin@zenithkandel.com.np';
$_SESSION['password'] = 'test'; // Use test password

require_once '../config.php';

$mbox = getImapConnection();
if (!$mbox) {
    echo json_encode(['error' => 'Cannot connect: ' . imap_last_error()]);
    exit;
}

$list = imap_list($mbox, IMAP_PREFIX, '*');
$folders = [];

if ($list) {
    foreach ($list as $folder) {
        $name = str_replace(IMAP_PREFIX, '', $folder);
        $folders[] = $name;
    }
}

imap_close($mbox);

echo json_encode(['folders' => $folders]);