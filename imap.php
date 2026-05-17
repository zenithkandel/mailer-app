<?php

require_once __DIR__ . '/config.php';

function imapConnect($folder = 'INBOX') {
    $host = '{' . IMAP_HOST . ':' . IMAP_PORT . '/ssl}' . $folder;
    $connection = @imap_open($host, IMAP_USER, IMAP_PASS, OP_READONLY);

    if (!$connection) {
        return null;
    }

    return $connection;
}

function getUnreadCount($folder = 'INBOX') {
    $connection = imapConnect($folder);
    if (!$connection) {
        return 0;
    }

    $search = imap_search($connection, 'UNSEEN');
    $count = $search ? count($search) : 0;
    imap_close($connection);

    return $count;
}

function fetchEmails($folder = 'INBOX', $limit = 50, $start = 0) {
    $connection = imapConnect($folder);
    if (!$connection) {
        return [];
    }

    $emails = [];
    $total = imap_num_msg($connection);

    if ($total === 0) {
        imap_close($connection);
        return [];
    }

    $startNum = max(1, $total - $start);
    $endNum = max(1, $total - $start - $limit + 1);

    for ($i = $startNum; $i >= $endNum; $i--) {
        $header = imap_headerinfo($connection, $i);
        if (!$header) continue;

        $overview = imap_fetch_overview($connection, $i, 0);

        $from = isset($header->from[0]) ? $header->from[0] : null;
        $senderName = $from ? (isset($from->personal) ? $from->personal : $from->mailbox) : 'Unknown';
        $senderEmail = $from ? ($from->mailbox . '@' . $from->host) : '';

        $subject = isset($header->subject) ? imap_mime_header_decode($header->subject) : '';
        $subject = is_array($subject) ? implode('', array_map(function($s) { return $s->text; }, $subject)) : $subject;

        $date = isset($header->udate) ? $header->udate : time();
        $read = isset($overview[0]->seen) && $overview[0]->seen == 1;

        $preview = '';
        $body = imap_fetchbody($connection, $i, '1');
        if ($body) {
            $body = imap_qprint($body);
            $preview = substr(strip_tags($body), 0, 100);
        }

        $header = imap_headerinfo($connection, $i);
        $uid = isset($header->uid) ? $header->uid : $i;

        $emails[] = [
            'uid' => $uid,
            'msgnum' => $i,
            'from_name' => $senderName,
            'from_email' => $senderEmail,
            'subject' => $subject ?: '(No Subject)',
            'date' => $date,
            'read' => $read,
            'preview' => $preview
        ];
    }

    imap_close($connection);
    return $emails;
}

function fetchEmailByUid($uid) {
    $connection = imapConnect();
    if (!$connection) {
        return null;
    }

    $msgnum = imap_msgno($connection, $uid);
    if (!$msgnum) {
        imap_close($connection);
        return null;
    }

    $header = imap_headerinfo($connection, $msgnum);
    $structure = imap_fetchstructure($connection, $msgnum);

    $from = isset($header->from[0]) ? $header->from[0] : null;
    $to = isset($header->to[0]) ? $header->to[0] : null;

    $fromName = $from ? (isset($from->personal) ? $from->personal : '') : '';
    $fromEmail = $from ? ($from->mailbox . '@' . $from->host) : '';

    $toName = $to ? (isset($to->personal) ? $to->personal : '') : '';
    $toEmail = $to ? ($to->mailbox . '@' . $to->host) : '';

    $subject = isset($header->subject) ? imap_mime_header_decode($header->subject) : '';
    $subject = is_array($subject) ? implode('', array_map(function($s) { return $s->text; }, $subject)) : $subject;

    $date = isset($header->udate) ? $header->udate : time();

    $body = '';
    $html = '';
    $attachments = [];

    if (isset($structure->parts)) {
        foreach ($structure->parts as $partNum => $part) {
            if ($part->type == 0 && $part->subtype == 'PLAIN') {
                $body = imap_fetchbody($connection, $msgnum, $partNum + 1);
                $body = imap_qprint($body);
            } elseif ($part->type == 0 && $part->subtype == 'HTML') {
                $html = imap_fetchbody($connection, $msgnum, $partNum + 1);
                $html = imap_qprint($html);
            } elseif ($part->type >= 2) {
                $attachment = getAttachment($connection, $msgnum, $partNum + 1, $part);
                if ($attachment) {
                    $attachments[] = $attachment;
                }
            }
        }
    }

    if (!$body && !$html) {
        $body = imap_fetchbody($connection, $msgnum, '1');
        $body = imap_qprint($body);
    }

    imap_close($connection);

    return [
        'uid' => $uid,
        'from_name' => $fromName,
        'from_email' => $fromEmail,
        'to_name' => $toName,
        'to_email' => $toEmail,
        'subject' => $subject ?: '(No Subject)',
        'date' => $date,
        'body' => $body,
        'html' => $html,
        'attachments' => $attachments
    ];
}

function getAttachment($connection, $msgnum, $partNum, $part) {
    $filename = '';
    if (isset($part->dparameters)) {
        foreach ($part->dparameters as $param) {
            if ($param->attribute == 'filename') {
                $filename = $param->value;
                break;
            }
        }
    }

    if (!$filename && isset($part->parameters)) {
        foreach ($part->parameters as $param) {
            if ($param->attribute == 'name') {
                $filename = $param->value;
                break;
            }
        }
    }

    if (!$filename) {
        return null;
    }

    $data = imap_fetchbody($connection, $msgnum, $partNum);
    $data = base64_decode($data);

    return [
        'filename' => $filename,
        'size' => strlen($data),
        'data' => $data
    ];
}

function deleteEmail($uid) {
    $connection = imapConnect();
    if (!$connection) {
        return false;
    }

    $msgnum = imap_msgno($connection, $uid);
    if (!$msgnum) {
        imap_close($connection);
        return false;
    }

    imap_delete($connection, $msgnum);
    imap_expunge($connection);
    imap_close($connection);

    return true;
}

function searchEmails($query, $folder = 'INBOX') {
    $connection = imapConnect($folder);
    if (!$connection) {
        return [];
    }

    $search = imap_search($connection, 'ALL');
    if (!$search) {
        imap_close($connection);
        return [];
    }

    $emails = [];
    foreach ($search as $msgnum) {
        $header = imap_headerinfo($connection, $msgnum);
        if (!$header) continue;

        $from = isset($header->from[0]) ? $header->from[0] : null;
        $senderEmail = $from ? ($from->mailbox . '@' . $from->host) : '';
        $senderName = $from ? (isset($from->personal) ? $from->personal : $from->mailbox) : 'Unknown';

        $subject = isset($header->subject) ? imap_mime_header_decode($header->subject) : '';
        $subject = is_array($subject) ? implode('', array_map(function($s) { return $s->text; }, $subject)) : $subject;

        if (stripos($subject, $query) !== false || stripos($senderEmail, $query) !== false || stripos($senderName, $query) !== false) {
            $overview = imap_fetch_overview($connection, $msgnum, 0);
            $date = isset($header->udate) ? $header->udate : time();
            $read = isset($overview[0]->seen) && $overview[0]->seen == 1;

            $body = imap_fetchbody($connection, $msgnum, '1');
            $preview = '';
            if ($body) {
                $body = imap_qprint($body);
                $preview = substr(strip_tags($body), 0, 100);
            }

            $emails[] = [
                'uid' => isset($header->uid) ? $header->uid : $msgnum,
                'msgnum' => $msgnum,
                'from_name' => $senderName,
                'from_email' => $senderEmail,
                'subject' => $subject ?: '(No Subject)',
                'date' => $date,
                'read' => $read,
                'preview' => $preview
            ];
        }
    }

    imap_close($connection);
    return $emails;
}

function getSentFolderConnection() {
    $folders = ['INBOX.Sent', 'Sent', 'INBOX.Sent Messages', 'Sent Messages'];

    foreach ($folders as $folder) {
        $host = '{' . IMAP_HOST . ':' . IMAP_PORT . '/ssl}' . $folder;
        $connection = @imap_open($host, IMAP_USER, IMAP_PASS, OP_READONLY);
        if ($connection) {
            return ['connection' => $connection, 'folder' => $folder];
        }
    }

    return null;
}