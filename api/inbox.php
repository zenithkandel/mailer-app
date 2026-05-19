<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

function outputJson($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

set_exception_handler(function($e) {
    outputJson([
        'emails' => [],
        'page' => 1,
        'has_more' => false,
        'total' => 0,
        'unread_count' => 0,
        'error' => 'Server error: ' . $e->getMessage()
    ], 500);
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
        outputJson([
            'emails' => [],
            'page' => 1,
            'has_more' => false,
            'total' => 0,
            'unread_count' => 0,
            'error' => 'Fatal error: ' . $error['message']
        ], 500);
    }
});

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/helpers.php';

if (!isLoggedIn()) {
    outputJson(['error' => 'Unauthorized', 'emails' => [], 'page' => 1, 'has_more' => false, 'total' => 0, 'unread_count' => 0], 401);
}

if (!function_exists('imap_open')) {
    outputJson([
        'emails' => [],
        'page' => 1,
        'has_more' => false,
        'total' => 0,
        'unread_count' => 0,
        'error' => 'IMAP extension is not available on this server. Please enable php_imap.dll in php.ini.'
    ], 503);
}

$action = $_GET['action'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$perPage = 20;
$offset = ($page - 1) * $perPage;

if ($action === 'mark_read') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        outputJson(['error' => 'Method not allowed'], 405);
    }
    $uid = intval($_GET['uid'] ?? 0);
    if ($uid <= 0) {
        outputJson(['success' => false]);
    }
    try {
        $config = loadConfig();
        $imapConfig = $config['imap'] ?? [];
        $security = strtolower($imapConfig['security'] ?? 'ssl');
        $port = $imapConfig['port'] ?? 993;
        if ($security === 'ssl') {
            $mailbox = '{' . $imapConfig['host'] . ':' . $port . '/imap/ssl}INBOX';
        } else {
            $mailbox = '{' . $imapConfig['host'] . ':' . $port . '}INBOX';
        }
        $mbox = @imap_open($mailbox, $imapConfig['user'] ?? '', $imapConfig['pass'] ?? '');
        if ($mbox) {
            imap_setflag_full($mbox, $uid, '\\Seen');
            imap_close($mbox);
        }
        outputJson(['success' => true]);
    } catch (Exception $e) {
        outputJson(['success' => false]);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    outputJson(['error' => 'Method not allowed'], 405);
}

$config = loadConfig();
if (!$config) {
    outputJson(['error' => 'Server configuration error', 'emails' => [], 'page' => 1, 'has_more' => false, 'total' => 0, 'unread_count' => 0], 500);
}

$imapConfig = $config['imap'] ?? [];
$security = strtolower($imapConfig['security'] ?? 'ssl');
$port = $imapConfig['port'] ?? 993;
if ($security === 'ssl') {
    $mailbox = '{' . $imapConfig['host'] . ':' . $port . '/imap/ssl}INBOX';
} elseif ($security === 'tls') {
    $mailbox = '{' . $imapConfig['host'] . ':' . $port . '/imap/tls}INBOX';
} else {
    $mailbox = '{' . $imapConfig['host'] . ':' . $port . '}INBOX';
}

$user = $imapConfig['user'] ?? '';
$pass = $imapConfig['pass'] ?? '';

$mbox = @imap_open($mailbox, $user, $pass);

if (!$mbox) {
    $err = imap_last_error() ?: 'Unknown connection error';
    $isConnError = (strpos($err, 'connect') !== false || strpos($err, 'timed out') !== false || strpos($err, 'Couldn\'t open') !== false || $err === 'Unknown connection error');
    outputJson([
        'emails' => [],
        'page' => $page,
        'has_more' => false,
        'total' => 0,
        'unread_count' => 0,
        'error' => 'Failed to connect to mail server: ' . $err
    ], $isConnError ? 503 : 500);
}

if (!empty($search)) {
    $allResults = @imap_search($mbox, 'ALL', SE_FREE, 'UTF-8');
    if ($allResults === false) $allResults = [];

    $filtered = [];
    foreach ($allResults as $msgNum) {
        $h = @imap_headerinfo($mbox, $msgNum);
        if ($h === false) continue;
        $subj = decodeHeader($h->subject ?? '');
        $fromObj = $h->from[0] ?? null;
        $fromName = $fromObj ? trim(($fromObj->personal ?? '') . ' ' . ($fromObj->mailbox ?? '') . ' ' . ($fromObj->host ?? '')) : '';
        if (stripos($subj, $search) !== false || stripos($fromName, $search) !== false) {
            $filtered[] = ['msgNum' => $msgNum, 'header' => $h];
        }
    }
    usort($filtered, function($a, $b) {
        return strtotime($b['header']->date ?? '') <=> strtotime($a['header']->date ?? '');
    });
    $searchResults = array_column($filtered, 'msgNum');
} else {
    $searchResults = imap_sort($mbox, SORTDATE, 1, 0, null, 'UTF-8');
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

outputJson([
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