<?php
require_once '../config.php';
requireAuth();

header('Content-Type: application/json');

if (!extension_loaded('imap')) {
    echo json_encode(['error' => 'PHP IMAP extension is not installed']);
    exit;
}

$folder = $_GET['folder'] ?? 'INBOX';
$page = max(1, intval($_GET['page'] ?? 1));

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

$search = $_GET['search'] ?? '';
$searchField = $_GET['searchField'] ?? 'subject';
$searchScope = $_GET['searchScope'] ?? 'current';

if ($search && $searchScope === 'all') {
    $uids = [];
    $list = imap_list($mbox, IMAP_PREFIX, '*');
    if ($list) {
        foreach ($list as $f) {
            $fname = str_replace(IMAP_PREFIX, '', $f);
            $fenc = imap_utf7_encode($fname);
            $fpath = IMAP_PREFIX . $fenc;
            $tmpBox = @imap_open($fpath, $_SESSION['username'], $_SESSION['password']);
            if ($tmpBox) {
                $searchQuery = strtoupper($searchField) === 'BODY'
                    ? 'TEXT "' . imap_utf7_encode($search) . '"'
                    : strtoupper($searchField) . ' "' . imap_utf7_encode($search) . '"';
                $found = @imap_search($tmpBox, $searchQuery, SE_UID);
                if ($found) {
                    $uids = array_merge($uids, $found);
                }
                imap_close($tmpBox);
            }
        }
    }
    rsort($uids);
} elseif ($search) {
    $searchQuery = strtoupper($searchField) === 'BODY'
        ? 'TEXT "' . imap_utf7_encode($search) . '"'
        : strtoupper($searchField) . ' "' . imap_utf7_encode($search) . '"';
    $uids = @imap_search($mbox, $searchQuery, SE_UID);
    if ($uids) {
        rsort($uids);
    } else {
        $uids = [];
    }
} else {
    $uids = @imap_search($mbox, 'ALL', SE_UID);
    if ($uids) {
        rsort($uids);
    } else {
        $uids = [];
    }
}

$total = count($uids);
$totalPages = ceil($total / MESSAGES_PER_PAGE);

$pageUids = array_slice($uids, ($page - 1) * MESSAGES_PER_PAGE, MESSAGES_PER_PAGE);

$messages = [];
foreach ($pageUids as $uid) {
    $msgNo = imap_msgno($mbox, $uid);
    $header = @imap_header($mbox, $msgNo);
    if (!$header) continue;

    $from = getDisplayNameFromHeader($header->from);
    $fromEmail = getEmailFromHeader($header->from);
    $subject = decodeHeader($header->subject);
    $date = isset($header->date) ? formatDate($header->date) : '';
    $hasAttachment = isset($header->attachments) && $header->attachments;

    $flags = '';
    $overview = @imap_fetch_overview($mbox, $uid, FT_UID);
    if ($overview) {
        $flags = $overview[0]->seen ? 'seen' : 'unseen';
        if (!empty($overview[0]->flagged)) {
            $flags .= ' flagged';
        }
    }

    if (isset($header->Recent) && $header->Recent === 'N') {
        $flags .= ' recent';
    }

    $messages[] = [
        'uid' => $uid,
        'from' => $from,
        'fromEmail' => $fromEmail,
        'subject' => $subject ?: '(No subject)',
        'date' => $date,
        'flags' => $flags,
        'hasAttachment' => $hasAttachment
    ];
}

imap_close($mbox);

echo json_encode([
    'messages' => $messages,
    'total' => $total,
    'page' => $page,
    'totalPages' => $totalPages
]);