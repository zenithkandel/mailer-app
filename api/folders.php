<?php
require_once __DIR__ . '/../config.php';
requireLogin();

header('Content-Type: application/json');

try {
    $imap = getImapConnection('');
    $folders = imap_list($imap, '{' . MAIL_HOST . ':' . IMAP_PORT . '/imap/ssl/novalidate-cert}', '*');

    $result = [];
    if ($folders) {
        foreach ($folders as $folder) {
            $name = imap_utf8($folder);
            $shortName = substr($name, strrpos($name, '}') + 1);

            $check = imap_mailboxmsginfo($imap);
            $unread = 0;

            $status = @imap_status($imap, $folder, SA_ALL);
            if ($status) {
                $unread = $status->unseen;
            }

            $result[] = [
                'name' => $name,
                'shortName' => $shortName,
                'unread' => $unread,
                'total' => $check->Nmsgs ?? 0
            ];
        }
    }

    imap_close($imap);

    usort($result, function($a, $b) {
        $order = ['INBOX' => 0, 'Sent' => 1, 'Drafts' => 2, 'Spam' => 3, 'Trash' => 4];
        $aOrder = isset($order[$a['shortName']]) ? $order[$a['shortName']] : 99;
        $bOrder = isset($order[$b['shortName']]) ? $order[$b['shortName']] : 99;
        return $aOrder - $bOrder;
    });

    echo json_encode(['folders' => $result]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}