<?php
require_once __DIR__ . '/../config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? '';
$folder = $_POST['folder'] ?? 'INBOX';
$uids = isset($_POST['uids']) ? explode(',', $_POST['uids']) : [];
$targetFolder = $_POST['target_folder'] ?? '';

if (empty($uids)) {
    http_response_code(400);
    echo json_encode(['error' => 'No messages selected']);
    exit;
}

if (!$action || !in_array($action, ['delete', 'move', 'copy', 'flag', 'unflag', 'read', 'unread', 'spam', 'unspam', 'expunge'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

try {
    $imap = getWritableImapConnection($folder);

    $result = [];

    switch ($action) {
        case 'delete':
            foreach ($uids as $uid) {
                imap_delete($imap, $uid, FT_UID);
            }
            imap_expunge($imap);
            $result = ['message' => 'Messages deleted'];
            break;

        case 'move':
            if (!$targetFolder) {
                throw new Exception('Target folder required');
            }
            foreach ($uids as $uid) {
                imap_mail_move($imap, $uid, $targetFolder, FT_UID);
            }
            imap_expunge($imap);
            $result = ['message' => 'Messages moved'];
            break;

        case 'copy':
            if (!$targetFolder) {
                throw new Exception('Target folder required');
            }
            foreach ($uids as $uid) {
                imap_mail_copy($imap, $uid, $targetFolder, FT_UID);
            }
            $result = ['message' => 'Messages copied'];
            break;

        case 'flag':
            foreach ($uids as $uid) {
                imap_setflag_full($imap, $uid, '\\Flagged', FT_UID);
            }
            $result = ['message' => 'Messages flagged'];
            break;

        case 'unflag':
            foreach ($uids as $uid) {
                imap_clearflag_full($imap, $uid, '\\Flagged', FT_UID);
            }
            $result = ['message' => 'Flags removed'];
            break;

        case 'read':
            foreach ($uids as $uid) {
                imap_setflag_full($imap, $uid, '\\Seen', FT_UID);
            }
            $result = ['message' => 'Messages marked as read'];
            break;

        case 'unread':
            foreach ($uids as $uid) {
                imap_clearflag_full($imap, $uid, '\\Seen', FT_UID);
            }
            $result = ['message' => 'Messages marked as unread'];
            break;

        case 'spam':
            if ($targetFolder) {
                foreach ($uids as $uid) {
                    imap_mail_move($imap, $uid, $targetFolder, FT_UID);
                }
                imap_expunge($imap);
            } else {
                foreach ($uids as $uid) {
                    imap_setflag_full($imap, $uid, '\\Answered \\Flagged', FT_UID);
                }
            }
            $result = ['message' => 'Reported as spam'];
            break;

        case 'unspam':
            if ($targetFolder) {
                foreach ($uids as $uid) {
                    imap_mail_move($imap, $uid, $targetFolder, FT_UID);
                }
                imap_expunge($imap);
            }
            $result = ['message' => 'Removed from spam'];
            break;

        case 'expunge':
            imap_expunge($imap);
            $result = ['message' => 'Folder expunged'];
            break;
    }

    imap_close($imap, CL_EXPUNGE);

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}