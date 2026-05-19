<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/smtp.php';

header('Content-Type: application/json');

function outputJson($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

set_exception_handler(function($e) {
    outputJson(['error' => $e->getMessage()], 500);
});

requireLogin();

$config = loadConfig();
if (!$config) outputJson(['error' => 'Configuration error'], 500);

$imapConfig = $config['imap'] ?? [];
$folders = $imapConfig['folders'] ?? ['inbox' => 'INBOX', 'sent' => 'Sent', 'drafts' => 'Drafts', 'trash' => 'Trash'];
$settings = $config['settings'] ?? [];

function getMailbox($config) {
    $imap = $config['imap'];
    $security = strtolower($imap['security'] ?? 'ssl');
    $port = $imap['port'] ?? 993;
    $prefix = ($security === 'ssl') ? '{' . $imap['host'] . ':' . $port . '/imap/ssl}' : ($security === 'tls' ? '{' . $imap['host'] . ':' . $port . '/imap/tls}' : '{' . $imap['host'] . ':' . $port . '}');
    return $prefix;
}

function decodeHeaderStr($str) {
    if (empty($str)) return '';
    $decoded = imap_mime_header_decode($str);
    $result = '';
    foreach ($decoded as $part) {
        $result .= $part->text;
    }
    return $result;
}

function getEmailAddress($obj) {
    if (!$obj) return '';
    return ($obj->mailbox ?? '') . '@' . ($obj->host ?? '');
}

function getDisplayName($obj) {
    if (!$obj) return '';
    $name = trim($obj->personal ?? '');
    return $name ?: ($obj->mailbox ?? '');
}

function formatDate($dateStr) {
    if (empty($dateStr)) return date('c');
    $ts = strtotime($dateStr);
    return $ts ? date('c', $ts) : date('c');
}

$action = $_GET['action'] ?? 'inbox';

if ($action === 'folders') {
    $mailbox = getMailbox($config);
    $mbox = @imap_open($mailbox, $imapConfig['user'] ?? '', $imapConfig['pass'] ?? '');
    if (!$mbox) outputJson(['error' => imap_last_error()]);
    
    $list = imap_list($mbox, $mailbox, '*');
    imap_close($mbox);
    
    outputJson(['folders' => $list ?: []]);
}

if ($action === 'unread_count') {
    $mailbox = getMailbox($config) . $folders['inbox'];
    $mbox = @imap_open($mailbox, $imapConfig['user'], $imapConfig['pass']);
    if (!$mbox) outputJson(['unread' => 0]);
    
    $search = @imap_search($mbox, 'UNSEEN');
    $count = $search ? count($search) : 0;
    imap_close($mbox);
    
    outputJson(['unread' => $count]);
}

if (in_array($action, ['inbox', 'sent', 'drafts', 'trash', 'starred'])) {
    $folder = $folders[$action] ?? 'INBOX';

    $page = max(1, (int)($_GET['page'] ?? 1));
    $search = trim($_GET['search'] ?? '');
    $perPage = $settings['per_page'] ?? 25;
    $offset = ($page - 1) * $perPage;

    $mailbox = getMailbox($config) . $folder;
    $mbox = @imap_open($mailbox, $imapConfig['user'], $imapConfig['pass']);

    if (!$mbox) {
        outputJson(['emails' => [], 'page' => $page, 'has_more' => false, 'total' => 0, 'unread' => 0, 'error' => imap_last_error()]);
    }

    $results = [];

    if ($action === 'starred') {
        $flagged = @imap_search($mbox, 'FLAGGED');
        $results = $flagged ?: [];
        usort($results, function($a, $b) use ($mbox) {
            $ha = @imap_headerinfo($mbox, $a);
            $hb = @imap_headerinfo($mbox, $b);
            return strtotime($hb->date ?? 0) <=> strtotime($ha->date ?? 0);
        });
    } elseif (!empty($search)) {
        $all = @imap_search($mbox, 'ALL');
        if ($all === false) $all = [];

        foreach ($all as $msg) {
            $h = @imap_headerinfo($mbox, $msg);
            if ($h === false) continue;

            $subj = decodeHeaderStr($h->subject ?? '');
            $from = getDisplayName($h->from[0] ?? null);
            $to = getDisplayName($h->to[0] ?? null);

            if (stripos($subj, $search) !== false || stripos($from, $search) !== false || stripos($to, $search) !== false) {
                $results[] = $msg;
            }
        }

        usort($results, function($a, $b) use ($mbox) {
            $ha = imap_headerinfo($mbox, $a);
            $hb = imap_headerinfo($mbox, $b);
            return strtotime($hb->date ?? '') <=> strtotime($ha->date ?? '');
        });
    } else {
        $results = imap_sort($mbox, SORTDATE, 1);
        if ($results === false) $results = [];
    }
    
    $total = count($results);
    $slice = array_slice($results, $offset, $perPage);
    $hasMore = ($offset + count($slice)) < $total;
    
    $emails = [];
    foreach ($slice as $msgNum) {
        $h = @imap_headerinfo($mbox, $msgNum);
        if ($h === false) continue;
        
        $fromObj = $h->from[0] ?? null;
        $toObj = $h->to[0] ?? null;
        
        $email = [
            'id' => $msgNum,
            'uid' => $msgNum,
            'from' => getEmailAddress($fromObj),
            'from_name' => decodeHeaderStr(getDisplayName($fromObj)),
            'to' => getEmailAddress($toObj),
            'to_name' => decodeHeaderStr(getDisplayName($toObj)),
            'subject' => decodeHeaderStr($h->subject ?? ''),
            'date' => formatDate($h->date ?? ''),
            'unread' => isset($h->Unseen) && $h->Unseen === 'U',
            'flagged' => isset($h->Flagged) && $h->Flagged === 'F'
        ];
        
        $overview = @imap_fetch_overview($mbox, $msgNum, 0);
        if ($overview && isset($overview[0]->subject)) {
            $email['preview'] = decodeHeaderStr($overview[0]->subject ?? '');
        }
        
        $emails[] = $email;
    }
    
    $unreadCount = 0;
    if ($action === 'inbox') {
        $unreadSearch = @imap_search($mbox, 'UNSEEN');
        $unreadCount = $unreadSearch ? count($unreadSearch) : 0;
    }
    
    imap_close($mbox);
    
    outputJson(['emails' => $emails, 'page' => $page, 'has_more' => $hasMore, 'total' => $total, 'unread' => $unreadCount]);
}

if ($action === 'view') {
    requireMethod(['GET']);
    $id = (int)($_GET['id'] ?? 0);
    $folder = $_GET['folder'] ?? 'inbox';
    $folderName = $folders[$folder] ?? 'INBOX';
    
    $mailbox = getMailbox($config) . $folderName;
    $mbox = @imap_open($mailbox, $imapConfig['user'], $imapConfig['pass']);
    
    if (!$mbox || !$id) {
        outputJson(['error' => 'Email not found'], 404);
    }
    
    $header = @imap_headerinfo($mbox, $id);
    if (!$header) {
        imap_close($mbox);
        outputJson(['error' => 'Email not found'], 404);
    }
    
    $struct = @imap_fetchstructure($mbox, $id);
    $body = '';
    $attachments = [];
    
    if ($struct) {
        if (!empty($struct->parts)) {
            foreach ($struct->parts as $partNum => $part) {
                $encoding = $part->encoding ?? 0;
                $subtype = strtolower($part->subtype ?? '');
                
                if ($subtype === 'html') {
                    $body = @imap_fetchbody($mbox, $id, $partNum + 1);
                    $body = decodeBody($body, $encoding);
                } elseif ($subtype === 'plain' && empty($body)) {
                    $body = @imap_fetchbody($mbox, $id, $partNum + 1);
                    $body = decodeBody($body, $encoding);
                }
                
                if (!empty($part->parts)) {
                    foreach ($part->parts as $subPartNum => $subPart) {
                        if (!empty($subPart->dparameters)) {
                            foreach ($subPart->dparameters as $param) {
                                if (strpos($param->attribute, 'filename') !== false) {
                                    $attachments[] = [
                                        'name' => $param->value,
                                        'part' => $partNum . '.' . ($subPartNum + 1),
                                        'type' => $subPart->subtype ?? 'bin'
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        } else {
            $body = @imap_body($mbox, $id);
            $body = decodeBody($body, $struct->encoding ?? 0);
        }
    }
    
    if (empty($body)) {
        $body = @imap_body($mbox, $id);
        $body = decodeBody($body, 0);
    }
    
    $fromObj = $header->from[0] ?? null;
    $toObj = $header->to[0] ?? null;
    $replyToObj = $header->reply_to[0] ?? null;
    
    $email = [
        'id' => $id,
        'from' => getEmailAddress($fromObj),
        'from_name' => decodeHeaderStr(getDisplayName($fromObj)),
        'to' => getEmailAddress($toObj),
        'to_name' => decodeHeaderStr(getDisplayName($toObj)),
        'reply_to' => getEmailAddress($replyToObj),
        'reply_to_name' => decodeHeaderStr(getDisplayName($replyToObj)),
        'subject' => decodeHeaderStr($header->subject ?? ''),
        'date' => formatDate($header->date ?? ''),
        'body' => $body,
        'attachments' => $attachments,
        'headers' => [
            'message_id' => $header->message_id ?? '',
            'in_reply_to' => $header->in_reply_to ?? ''
        ]
    ];
    
    imap_close($mbox);
    outputJson($email);
}

if ($action === 'send') {
    requireMethod(['POST']);
    
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    if (!csrfValidate($data['csrf_token'] ?? '')) {
        outputJson(['error' => 'Invalid token'], 403);
    }
    
    $to = trim($data['to'] ?? '');
    $subject = trim($data['subject'] ?? '');
    $body = $data['body'] ?? '';
    $toName = trim($data['to_name'] ?? '');
    $cc = trim($data['cc'] ?? '');
    $bcc = trim($data['bcc'] ?? '');
    $replyTo = trim($data['reply_to'] ?? '');
    $replyId = $data['reply_id'] ?? '';
    $folder = $data['folder'] ?? 'inbox';
    
    if (empty($to) || empty($subject) || empty($body)) {
        outputJson(['error' => 'Missing required fields'], 400);
    }
    
    $smtpConfig = $config['smtp'] ?? [];
    $mailer = new SMTPMailer($smtpConfig);
    $result = $mailer->sendEmail($to, $subject, $body, $toName, $cc, $bcc, $replyTo);
    
    if ($result['success']) {
        if (!empty($replyId)) {
            $folderName = $folders[$folder] ?? 'INBOX';
            $mailbox = getMailbox($config) . $folderName;
            $mbox = @imap_open($mailbox, $imapConfig['user'], $imapConfig['pass']);
            if ($mbox) {
                imap_setflag_full($mbox, $replyId, '\\Answered');
                imap_close($mbox);
            }
        }
        outputJson(['success' => true, 'message' => 'Email sent successfully']);
    } else {
        outputJson(['error' => $result['error'] ?? 'Failed to send email'], 500);
    }
}

if ($action === 'delete') {
    requireMethod(['POST']);
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    
    $ids = $data['ids'] ?? [];
    $folder = $data['folder'] ?? 'inbox';
    $folderName = $folders[$folder] ?? 'INBOX';
    
    if (empty($ids)) outputJson(['error' => 'No emails selected'], 400);
    
    $mailbox = getMailbox($config) . $folderName;
    $mbox = @imap_open($mailbox, $imapConfig['user'], $imapConfig['pass']);
    
    if (!$mbox) outputJson(['error' => 'Connection failed']);
    
    foreach ($ids as $id) {
        if ($folder === 'trash') {
            imap_delete($mbox, $id);
        } else {
            imap_setflag_full($mbox, $id, '\\Deleted');
        }
    }
    
    imap_expunge($mbox);
    imap_close($mbox);
    
    outputJson(['success' => true]);
}

if ($action === 'mark') {
    requireMethod(['POST']);
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    
    $ids = $data['ids'] ?? [];
    $flag = $data['flag'] ?? 'read';
    $folder = $data['folder'] ?? 'inbox';
    $folderName = $folders[$folder] ?? 'INBOX';
    
    if (empty($ids)) outputJson(['error' => 'No emails selected'], 400);
    
    $mailbox = getMailbox($config) . $folderName;
    $mbox = @imap_open($mailbox, $imapConfig['user'], $imapConfig['pass']);
    
    if (!$mbox) outputJson(['error' => 'Connection failed']);
    
    $flagMap = [
        'read' => '\\Seen',
        'unread' => '\\Seen',
        'flagged' => '\\Flagged',
        'unflagged' => '\\Flagged'
    ];
    
    $imapFlag = $flagMap[$flag] ?? '\\Seen';
    $actionFlag = (in_array($flag, ['unread', 'unflagged'])) ? '-'.$imapFlag : '+'.$imapFlag;
    
    foreach ($ids as $id) {
        imap_setflag_full($mbox, $id, $imapFlag);
    }
    
    imap_close($mbox);
    outputJson(['success' => true]);
}

if ($action === 'draft') {
    requireMethod(['POST']);
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    
    if (!$data) $data = $_POST;
    
    $draftsFolder = $folders['drafts'] ?? 'Drafts';
    $mailbox = getMailbox($config) . $draftsFolder;
    $mbox = @imap_open($mailbox, $imapConfig['user'], $imapConfig['pass'], 0, CL_EXPUNGE);
    
    if (!$mbox) {
        $mbox = @imap_open(getMailbox($config) . 'INBOX', $imapConfig['user'], $imapConfig['pass']);
    }
    
    if (!$mbox) outputJson(['error' => 'Failed to save draft']);
    
    $envelope = [
        'subject' => $data['subject'] ?? '',
        'to' => $data['to'] ?? '',
        'cc' => $data['cc'] ?? '',
        'bcc' => $data['bcc'] ?? ''
    ];
    
    $body = $data['body'] ?? '';
    
    imap_append($mbox, $mailbox, $envelope, $body);
    imap_close($mbox);
    
    outputJson(['success' => true]);
}

if ($action === 'attachment') {
    requireMethod(['GET']);
    $id = (int)($_GET['id'] ?? 0);
    $part = $_GET['part'] ?? '';
    $name = $_GET['name'] ?? 'attachment';
    $folder = $_GET['folder'] ?? 'inbox';
    $folderName = $folders[$folder] ?? 'INBOX';
    
    if (!$id || !$part) outputJson(['error' => 'Invalid request'], 400);
    
    $mailbox = getMailbox($config) . $folderName;
    $mbox = @imap_open($mailbox, $imapConfig['user'], $imapConfig['pass']);
    
    if (!$mbox) outputJson(['error' => 'Connection failed'], 500);
    
    $data = @imap_fetchbody($mbox, $id, $part);
    imap_close($mbox);
    
    if ($data) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        echo $data;
    } else {
        outputJson(['error' => 'Attachment not found'], 404);
    }
}

function decodeBody($data, $encoding) {
    if ($encoding == 3) {
        $data = base64_decode($data);
    } elseif ($encoding == 4) {
        $data = quoted_printable_decode($data);
    }
    return $data;
}

outputJson(['error' => 'Invalid action'], 400);