<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if (!function_exists('imap_open')) {
    echo json_encode(['error' => 'IMAP extension not available']);
    exit;
}

require_once '../config.php';

if (empty($_SESSION['authenticated']) || empty($_SESSION['username']) || empty($_SESSION['password'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$username = $_SESSION['username'];
$password = $_SESSION['password'];

$imapPrefix = '{mail.zenithkandel.com.np:993/imap/ssl}';

$mbox = @imap_open($imapPrefix . 'INBOX', $username, $password);
if (!$mbox) {
    echo json_encode(['error' => 'Cannot connect: ' . imap_last_error()]);
    exit;
}

$list = @imap_list($mbox, $imapPrefix, '*');

$folders = [];
if ($list) {
    foreach ($list as $folder) {
        $name = str_replace($imapPrefix, '', $folder);
        $folderEncode = imap_utf7_encode($name);
        $folderPath = $imapPrefix . $folderEncode;

        $status = @imap_status($mbox, $folderPath, SA_ALL);
        $unread = $status ? $status->unseen : 0;

        $displayName = preg_replace('/^INBOX\.?/i', '', $name);
        if (empty($displayName)) $displayName = 'Inbox';

        $folders[] = [
            'name' => $name,
            'displayName' => $displayName,
            'unread' => $unread
        ];
    }
}

imap_close($mbox);

usort($folders, function($a, $b) {
    $order = ['Inbox', 'Sent', 'Drafts', 'Trash', 'Spam'];
    $aOrder = array_search($a['name'], $order);
    $bOrder = array_search($b['name'], $order);
    $aOrder = $aOrder === false ? 100 : $aOrder;
    $bOrder = $bOrder === false ? 100 : $bOrder;
    if ($aOrder !== $bOrder) return $aOrder - $bOrder;
    return strcmp($a['displayName'], $b['displayName']);
});

echo json_encode(['folders' => $folders]);