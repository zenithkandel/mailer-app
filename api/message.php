<?php
require_once __DIR__ . '/../config.php';
requireLogin();

header('Content-Type: application/json');

$folder = $_GET['folder'] ?? 'INBOX';
$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$markRead = $_GET['mark_read'] ?? 'true';

if (!$uid) {
    http_response_code(400);
    echo json_encode(['error' => 'UID is required']);
    exit;
}

try {
    $imap = getImapConnection($folder);

    $msgs = imap_search($imap, 'UID ' . $uid, SE_UID);
    if (!$msgs) {
        imap_close($imap);
        http_response_code(404);
        echo json_encode(['error' => 'Message not found']);
        exit;
    }

    $msgNum = $msgs[0];

    if ($markRead === 'true' || $markRead === '1') {
        imap_setflag_full($imap, $uid, '\\Seen', ST_UID);
    }

    $header = imap_headerinfo($imap, $msgNum);
    $structure = imap_fetchstructure($imap, $msgNum);

    $from = [];
    if (isset($header->from)) {
        foreach ($header->from as $addr) {
            $from = [
                'name' => isset($addr->personal) ? imap_utf8($addr->personal) : '',
                'email' => (isset($addr->mailbox) ? $addr->mailbox : '') . (isset($addr->host) ? '@' . $addr->host : '')
            ];
            break;
        }
    }

    $to = [];
    if (isset($header->to)) {
        foreach ($header->to as $addr) {
            $to[] = [
                'name' => isset($addr->personal) ? imap_utf8($addr->personal) : '',
                'email' => (isset($addr->mailbox) ? $addr->mailbox : '') . (isset($addr->host) ? '@' . $addr->host : '')
            ];
        }
    }

    $cc = [];
    if (isset($header->cc)) {
        foreach ($header->cc as $addr) {
            $cc[] = [
                'name' => isset($addr->personal) ? imap_utf8($addr->personal) : '',
                'email' => (isset($addr->mailbox) ? $addr->mailbox : '') . (isset($addr->host) ? '@' . $addr->host : '')
            ];
        }
    }

    $bcc = [];
    if (isset($header->bcc)) {
        foreach ($header->bcc as $addr) {
            $bcc[] = [
                'name' => isset($addr->personal) ? imap_utf8($addr->personal) : '',
                'email' => (isset($addr->mailbox) ? $addr->mailbox : '') . (isset($addr->host) ? '@' . $addr->host : '')
            ];
        }
    }

    $allHeaders = [
        'from' => $from,
        'to' => $to,
        'cc' => $cc,
        'bcc' => $bcc,
        'subject' => isset($header->subject) ? imap_utf8($header->subject) : '',
        'date' => isset($header->date) ? $header->date : '',
        'message_id' => isset($header->message_id) ? $header->message_id : '',
        'reply_to' => isset($header->reply_to) ? imap_utf8($header->reply_to) : '',
        'in_reply_to' => isset($header->in_reply_to) ? $header->in_reply_to : '',
        'references' => isset($header->references) ? $header->references : '',
        'x_priority' => isset($header->x_priority) ? $header->x_priority : 3,
        'content_type' => $structure->ctype ?? 'text/plain'
    ];

    $bodyHtml = '';
    $bodyText = '';
    $attachments = [];

    function getBodyRecursive($imap, $msgNum, $part, $prefix = '') {
        global $bodyHtml, $bodyText, $attachments;

        $data = imap_fetchbody($imap, $msgNum, $prefix);
        if (!$data) return;

        $encoding = $part->encoding;

        switch ($encoding) {
            case 3:
                $data = base64_decode($data);
                break;
            case 4:
                $data = quoted_printable_decode($data);
                break;
            case 0:
                $data = imap_utf8($data);
                break;
        }

        $subtype = strtolower($part->subtype ?? '');
        $type = strtolower($part->type ?? 0);

        if ($type == 1 && $subtype == 'alternative') {
            if (isset($part->parts)) {
                $hasHtml = false;
                $hasText = false;
                foreach ($part->parts as $idx => $subpart) {
                    $subSub = getBodyRecursive($imap, $msgNum, $subpart, $prefix . ($idx + 1));
                }
            }
        } elseif ($type == 1 && $subtype == 'mixed') {
            if (isset($part->parts)) {
                foreach ($part->parts as $idx => $subpart) {
                    getBodyRecursive($imap, $msgNum, $subpart, $prefix . ($idx + 1));
                }
            }
        } elseif ($subtype == 'html') {
            $bodyHtml = $data;
        } elseif ($subtype == 'plain') {
            if (!$bodyText) $bodyText = $data;
        }

        if ($part->ifdisposition && strtolower($part->disposition) == 'attachment') {
            $filename = $part->dparameters[0]->value ?? $part->parameters[0]->value ?? 'attachment';
            $attachments[] = [
                'part_id' => $prefix,
                'filename' => $filename,
                'mime_type' => ($part->ctype ?? 'application/octet-stream'),
                'size' => strlen($data),
                'encoding' => $encoding
            ];
        }

        if (isset($part->parts)) {
            foreach ($part->parts as $idx => $subpart) {
                $subPrefix = $prefix ? $prefix . '.' . ($idx + 1) : ($idx + 1);
                getBodyRecursive($imap, $msgNum, $subpart, $subPrefix);
            }
        }
    }

    getBodyRecursive($imap, $msgNum, $structure, '');

    if (!$bodyHtml && $bodyText) {
        $bodyHtml = nl2br(htmlspecialchars($bodyText));
    }

    imap_close($imap);

    echo json_encode([
        'uid' => $uid,
        'folder' => $folder,
        'headers' => $allHeaders,
        'body_html' => $bodyHtml,
        'body_text' => $bodyText,
        'attachments' => $attachments
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}