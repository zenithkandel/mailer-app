<?php
require_once '../config.php';
requireAuth();

if (!extension_loaded('imap')) {
    http_response_code(500);
    echo 'PHP IMAP extension is not installed';
    exit;
}

$uid = intval($_GET['uid'] ?? 0);
$folder = $_GET['folder'] ?? 'INBOX';
$part = $_GET['part'] ?? '';
$filename = $_GET['filename'] ?? 'attachment';

if (!$uid || !$part) {
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

$mbox = getImapConnection();
if (!$mbox) {
    http_response_code(500);
    echo 'Cannot connect to mail server';
    exit;
}

$folderEncode = imap_utf7_encode($folder);
$folderPath = IMAP_PREFIX . $folderEncode;

$mbox = imap_open($folderPath, $_SESSION['username'], $_SESSION['password']);
if (!$mbox) {
    http_response_code(500);
    echo 'Cannot open folder';
    exit;
}

$structure = imap_fetchstructure($mbox, $uid, FT_UID);

function findPart($structure, $partNum) {
    $parts = explode('.', $partNum);
    $current = $structure;

    foreach ($parts as $idx) {
        $idx = intval($idx) - 1;
        if (!isset($current->parts[$idx])) {
            return null;
        }
        $current = $current->parts[$idx];
    }

    return $current;
}

$partData = findPart($structure, $part);

if (!$partData) {
    imap_close($mbox);
    http_response_code(404);
    echo 'Part not found';
    exit;
}

$content = imap_fetchbody($mbox, $uid, $part, FT_UID);

if ($partData->encoding == 3) {
    $content = base64_decode($content);
} elseif ($partData->encoding == 4) {
    $content = imap_qprint($content);
}

$mimeType = 'application/octet-stream';
if (isset($partData->ctype_primary) && isset($partData->ctype_secondary)) {
    $mimeType = $partData->ctype_primary . '/' . $partData->ctype_secondary;
}

$filename = basename($filename);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));

echo $content;

imap_close($mbox);