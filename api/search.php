<?php
require_once '../config.php';
requireAuth();

header('Content-Type: application/json');

$query = $_GET['query'] ?? '';
$field = $_GET['field'] ?? 'subject';
$scope = $_GET['scope'] ?? 'current';
$folder = $_GET['folder'] ?? 'INBOX';

if (empty($query)) {
    echo json_encode(['error' => 'Search query required']);
    exit;
}

$query = imap_utf7_encode($query);

$searchQuery = strtoupper($field) === 'BODY'
    ? 'TEXT "' . $query . '"'
    : strtoupper($field) . ' "' . $query . '"';

if ($scope === 'current') {
    $folderEncode = imap_utf7_encode($folder);
    $folderPath = IMAP_PREFIX . $folderEncode;

    $mbox = @imap_open($folderPath, $_SESSION['username'], $_SESSION['password']);
    if (!$mbox) {
        echo json_encode(['error' => 'Cannot open folder']);
        exit;
    }

    $uids = @imap_search($mbox, $searchQuery, SE_UID);
    if (!$uids) {
        $uids = [];
    }
    rsort($uids);
    imap_close($mbox);
} else {
    $uids = [];
    $list = imap_list($mbox = getImapConnection(), IMAP_PREFIX, '*');
    if (!$list) {
        echo json_encode(['error' => 'No folders found']);
        exit;
    }

    foreach ($list as $f) {
        $fname = str_replace(IMAP_PREFIX, '', $f);
        $fenc = imap_utf7_encode($fname);
        $fpath = IMAP_PREFIX . $fenc;
        $tmpBox = @imap_open($fpath, $_SESSION['username'], $_SESSION['password']);
        if ($tmpBox) {
            $found = @imap_search($tmpBox, $searchQuery, SE_UID);
            if ($found) {
                foreach ($found as $uid) {
                    $uids[] = ['uid' => $uid, 'folder' => $fname];
                }
            }
            imap_close($tmpBox);
        }
    }

    usort($uids, function($a, $b) {
        return $b['uid'] - $a['uid'];
    });
}

$results = array_slice($uids, 0, 100);

echo json_encode([
    'query' => $query,
    'results' => $results,
    'total' => count($results)
]);