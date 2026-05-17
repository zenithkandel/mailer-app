<?php
require_once '../config.php';
requireAuth();

header('Content-Type: application/json');

$mbox = getImapConnection();
if (!$mbox) {
    echo json_encode(['error' => 'Cannot connect to mail server']);
    exit;
}

$list = imap_list($mbox, IMAP_PREFIX, '*');

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