<?php
require_once '../config.php';
requireAuth();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$uids = $_POST['uids'] ?? $_GET['uids'] ?? '';
$folder = $_POST['folder'] ?? $_GET['folder'] ?? 'INBOX';
$toFolder = $_POST['toFolder'] ?? '';

if (!$uids) {
    echo json_encode(['error' => 'No messages selected']);
    exit;
}

$uidArray = is_array($uids) ? $uids : explode(',', $uids);
$uidArray = array_map('intval', $uidArray);
$uidArray = array_filter($uidArray);

if (empty($uidArray)) {
    echo json_encode(['error' => 'Invalid message IDs']);
    exit;
}

$mbox = getImapConnection();
if (!$mbox) {
    echo json_encode(['error' => 'Cannot connect to mail server']);
    exit;
}

$folderEncode = imap_utf7_encode($folder);
$folderPath = IMAP_PREFIX . $folderEncode;

$mbox = imap_open($folderPath, $_SESSION['username'], $_SESSION['password']);
if (!$mbox) {
    echo json_encode(['error' => 'Cannot open folder']);
    exit;
}

$success = true;
$message = 'Action completed';

switch ($action) {
    case 'delete':
        if (strtolower($folder) === 'trash') {
            foreach ($uidArray as $uid) {
                imap_delete($mbox, $uid, FT_UID);
            }
            imap_expunge($mbox);
            $message = 'Messages permanently deleted';
        } else {
            foreach ($uidArray as $uid) {
                imap_mail_move($mbox, $uid, 'Trash', CP_UID);
            }
            imap_expunge($mbox);
            $message = 'Messages moved to Trash';
        }
        break;

    case 'mark-read':
        $flag = '\\Seen';
        foreach ($uidArray as $uid) {
            imap_setflag_full($mbox, $uid, $flag, ST_UID);
        }
        $message = 'Messages marked as read';
        break;

    case 'mark-unread':
        $flag = '\\Seen';
        foreach ($uidArray as $uid) {
            imap_clearflag_full($mbox, $uid, $flag, ST_UID);
        }
        $message = 'Messages marked as unread';
        break;

    case 'flag':
        $flag = '\\Flagged';
        foreach ($uidArray as $uid) {
            imap_setflag_full($mbox, $uid, $flag, ST_UID);
        }
        $message = 'Messages flagged';
        break;

    case 'unflag':
        $flag = '\\Flagged';
        foreach ($uidArray as $uid) {
            imap_clearflag_full($mbox, $uid, $flag, ST_UID);
        }
        $message = 'Messages unflagged';
        break;

    case 'move':
        if (empty($toFolder)) {
            $success = false;
            $message = 'Destination folder not specified';
        } else {
            foreach ($uidArray as $uid) {
                imap_mail_move($mbox, $uid, $toFolder, CP_UID);
            }
            imap_expunge($mbox);
            $message = 'Messages moved to ' . $toFolder;
        }
        break;

    case 'empty-trash':
        $trashPath = IMAP_PREFIX . 'Trash';
        $trashBox = @imap_open($trashPath, $_SESSION['username'], $_SESSION['password']);
        if ($trashBox) {
            $msgs = imap_search($trashBox, 'ALL', SE_UID);
            if ($msgs) {
                foreach ($msgs as $uid) {
                    imap_delete($trashBox, $uid, FT_UID);
                }
                imap_expunge($trashBox);
            }
            imap_close($trashBox);
            $message = 'Trash emptied';
        } else {
            $success = false;
            $message = 'Cannot open Trash folder';
        }
        break;

    default:
        $success = false;
        $message = 'Unknown action';
}

imap_close($mbox);

echo json_encode([
    'success' => $success,
    'message' => $message
]);