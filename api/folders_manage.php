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
$folderName = $_POST['folder_name'] ?? '';
$newName = $_POST['new_name'] ?? '';
$parentFolder = $_POST['parent'] ?? '';

if (!$action || !in_array($action, ['create', 'rename', 'delete', 'subscribe', 'unsubscribe'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

try {
    $imap = getWritableImapConnection('');

    switch ($action) {
        case 'create':
            if (!$folderName) {
                throw new Exception('Folder name required');
            }
            $fullPath = $parentFolder ? $parentFolder . '/' . $folderName : $folderName;
            imap_createmailbox($imap, '{' . MAIL_HOST . ':' . IMAP_PORT . '/imap/ssl/novalidate-cert}' . $fullPath);
            $result = ['message' => 'Folder created successfully'];
            break;

        case 'rename':
            if (!$folderName || !$newName) {
                throw new Exception('Folder name and new name required');
            }
            imap_renamemailbox($imap, '{' . MAIL_HOST . ':' . IMAP_PORT . '/imap/ssl/novalidate-cert}' . $folderName, '{' . MAIL_HOST . ':' . IMAP_PORT . '/imap/ssl/novalidate-cert}' . $newName);
            $result = ['message' => 'Folder renamed successfully'];
            break;

        case 'delete':
            if (!$folderName) {
                throw new Exception('Folder name required');
            }
            $check = imap_status($imap, '{' . MAIL_HOST . ':' . IMAP_PORT . '/imap/ssl/novalidate-cert}' . $folderName, SA_ALL);
            if ($check && $check->messages > 0) {
                throw new Exception('Folder is not empty. Move or delete all messages first.');
            }
            imap_deletemailbox($imap, '{' . MAIL_HOST . ':' . IMAP_PORT . '/imap/ssl/novalidate-cert}' . $folderName);
            $result = ['message' => 'Folder deleted successfully'];
            break;

        case 'subscribe':
            if (!$folderName) {
                throw new Exception('Folder name required');
            }
            imap_subscribe($imap, '{' . MAIL_HOST . ':' . IMAP_PORT . '/imap/ssl/novalidate-cert}' . $folderName);
            $result = ['message' => 'Folder subscribed successfully'];
            break;

        case 'unsubscribe':
            if (!$folderName) {
                throw new Exception('Folder name required');
            }
            imap_unsubscribe($imap, '{' . MAIL_HOST . ':' . IMAP_PORT . '/imap/ssl/novalidate-cert}' . $folderName);
            $result = ['message' => 'Folder unsubscribed successfully'];
            break;
    }

    imap_close($imap, CL_EXPUNGE);

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}