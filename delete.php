<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/imap.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = isset($_POST['uid']) ? (int)$_POST['uid'] : 0;
    $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'inbox.php';

    if ($uid) {
        deleteEmail($uid);
    }
}

header('Location: ' . ($redirect ?? 'inbox.php'));
exit;