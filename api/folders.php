<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Cache-Control: no-cache');

function output($data) {
    echo json_encode($data);
    exit;
}

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Server error: ' . $error['message']]);
        }
        exit;
    }
});

if (!function_exists('imap_open')) {
    output(['error' => 'IMAP extension not available']);
}

require_once '../config.php';
requireAuth();

$mbox = getImapConnection();
if (!$mbox) {
    echo json_encode(['error' => 'Cannot connect to mail server']);
    exit;
}

$list = @imap_list($mbox, IMAP_PREFIX, '*');

$folders = [];
if ($list) {
    foreach ($list as $folder) {
        $name = str_replace(IMAP_PREFIX, '', $folder);
        $folderEncode = imap_utf7_encode($name);
        $folderPath = IMAP_PREFIX . $folderEncode;

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