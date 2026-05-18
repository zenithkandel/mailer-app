<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$config = loadConfig();

if (!$config) {
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error']);
    exit;
}

if (!function_exists('imap_open')) {
    http_response_code(500);
    echo json_encode(['error' => 'IMAP extension is not available. Please enable php_imap in php.ini.']);
    exit;
}

$action = $_GET['action'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$perPage = 20;
$offset = ($page - 1) * $perPage;

if ($action === 'mark_read') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    $uid = intval($_GET['uid'] ?? 0);
    if ($uid <= 0) {
        echo json_encode(['success' => false]);
        exit;
    }
    try {
        $imapConfig = $config['imap'] ?? [];
        $security = strtolower($imapConfig['security'] ?? 'ssl');
        $port = $imapConfig['port'] ?? 993;
        $mailbox = ($security === 'ssl')
            ? '{' . $imapConfig['host'] . ":{$port}/imap/ssl}INBOX"
            : '{' . $imapConfig['host'] . ":{$port}}INBOX";
        $mbox = @imap_open($mailbox, $imapConfig['user'] ?? '', $imapConfig['pass'] ?? '');
        if ($mbox) {
            imap_setflag_full($mbox, $uid, '\\Seen');
            imap_close($mbox);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$imapConfig = $config['imap'] ?? [];
$security = strtolower($imapConfig['security'] ?? 'ssl');
$port = $imapConfig['port'] ?? 993;
$mailbox = ($security === 'ssl')
    ? '{' . $imapConfig['host'] . ":{$port}/imap/ssl}INBOX"
    : ($security === 'tls'
        ? '{' . $imapConfig['host'] . ":{$port}/imap/tls}INBOX"
        : '{' . $imapConfig['host'] . ":{$port}}INBOX");

$mbox = @imap_open($mailbox, $imapConfig['user'] ?? '', $imapConfig['pass'] ?? '');

if (!$mbox) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to connect to IMAP server: ' . imap_last_error()]);
    exit;
}

if (!empty($search)) {
    $searchResults = imap_search($mbox, 'ALL', SE_FREE, 'UTF-8');
    if ($searchResults === false) $searchResults = [];

    $filtered = [];
    foreach ($searchResults as $msgNum) {
        $h = @imap_headerinfo($mbox, $msgNum);
        if ($h === false) continue;
        $subj = decodeHeader($h->subject ?? '');
        $from = decodeHeader(implode(' ', array_filter([
            $h->from[0]->personal ?? '',
            $h->from[0]->mailbox ?? '',
            $h->from[0]->host ?? ''
        ])));
        if (stripos($subj, $search) !== false || stripos($from, $search) !== false) {
            $filtered[$msgNum] = $h;
        }
    }
    usort($searchResults, function($a, $b) use ($filtered) {
        $ha = $filtered[$a] ?? null;
        $hb = $filtered[$b] ?? null;
        if (!$ha || !$hb) return 0;
        return strtotime($hb->date ?? '') <=> strtotime($ha->date ?? '');
    });
    $searchResults = $filtered;
} else {
    $searchResults = imap_sort($mbox, SORTDATE, 1, SE_FREE, null, 'UTF-8');
    if ($searchResults === false) $searchResults = [];
}

$total = count($searchResults);
$slice = array_slice($searchResults, $offset, $perPage);
$hasMore = ($offset + count($slice)) < $total;

$unreadResults = @imap_search($mbox, 'UNSEEN', SE_FREE, 'UTF-8');
$unreadCount = $unreadResults === false ? 0 : count($unreadResults);

$emails = [];

foreach ($slice as $msgNum) {
    $headers = @imap_headerinfo($mbox, $msgNum);
    if ($headers === false) continue;

    $fromObj = $headers->from[0] ?? null;
    $fromName = isset($fromObj->personal) && trim($fromObj->personal) !== ''
        ? trim($fromObj->personal)
        : ($fromObj->mailbox ?? 'unknown');
    $fromEmail = (isset($fromObj->mailbox) && isset($fromObj->host))
        ? ($fromObj->mailbox . '@' . $fromObj->host) : '';

    $toObj = $headers->to[0] ?? null;
    $toEmail = (isset($toObj->mailbox) && isset($toObj->host))
        ? ($toObj->mailbox . '@' . $toObj->host) : '';

    $subject = $headers->subject ?? '(no subject)';
    $date = isset($headers->date) ? date('c', strtotime($headers->date)) : date('c');
    $msgNo = $headers->Msgno ?? $msgNum;

    $unread = isset($headers->Unseen) && $headers->Unseen === 'U';

    $overview = @imap_fetch_overview($mbox, $msgNo, 0);
    $preview = '';
    if ($overview && isset($overview[0]->subject)) {
        $preview = $overview[0]->subject ?? '';
        if (empty($preview)) {
            $struct = @imap_fetchstructure($mbox, $msgNo);
            if ($struct) {
                $preview = getBodyPreview($mbox, $msgNo, $struct, 100);
            }
        }
    }

    $emails[] = [
        'id' => (string)$msgNo,
        'uid' => (string)$msgNo,
        'from' => $fromEmail,
        'from_name' => decodeHeader($fromName),
        'to' => $toEmail,
        'subject' => decodeHeader($subject),
        'date' => $date,
        'unread' => $unread,
        'preview' => decodeHeader($preview),
    ];
}

imap_close($mbox);

echo json_encode([
    'emails' => $emails,
    'page' => $page,
    'has_more' => $hasMore,
    'total' => $total,
    'unread_count' => $unreadCount,
]);

function decodeHeader(string $str): string {
    if (empty($str)) return '';
    $decoded = imap_mime_header_decode($str);
    $result = '';
    foreach ($decoded as $part) {
        $result .= $part->text;
    }
    return $result;
}

function getBodyPreview($mbox, $msgNo, $struct, int $maxChars = 100): string {
    if (isset($struct->parts) && count($struct->parts) > 0) {
        foreach ($struct->parts as $idx => $part) {
            $subtype = strtolower($part->subtype ?? '');
            if ($subtype === 'plain' || $subtype === 'html') {
                $partId = $idx + 1;
                $body = @imap_fetchbody($mbox, $msgNo, $partId);
                if ($body === false || $body === '') continue;
                $decoded = decodePart($body, $part->encoding ?? 0);
                if ($decoded !== '') {
                    return trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags($decoded)), 0, $maxChars));
                }
            }
        }
    }
    $body = @imap_body($mbox, $msgNo);
    if ($body !== false && $body !== '') {
        $decoded = decodePart($body, $struct->encoding ?? 0);
        return trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags($decoded)), 0, $maxChars));
    }
    return '';
}

function decodePart(string $data, int $encoding): string {
    switch ($encoding) {
        case 3: $data = base64_decode($data); break;
        case 4: $data = quoted_printable_decode($data); break;
    }
    return $data;
}