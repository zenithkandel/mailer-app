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
    
    $search = @imap_search($connection, 'UNSEEN');
    $count = $search ? count($search) : 0;
    @imap_close($connection);
    
    return $count;
}

function safeQprint($data) {
    if ($data === false || $data === null) {
        return '';
    }
    $prev = set_error_handler(function ($errno, $errstr) {
        return true;
    });
    $decoded = @imap_qprint($data);
    if ($prev !== null) {
        set_error_handler($prev);
    } else {
        restore_error_handler();
    }
    return $decoded === false ? quoted_printable_decode($data) : $decoded;
}

function fetchEmails($folder = 'INBOX', $limit = 50) {
    $connection = imapConnect($folder);
    if (!$connection) {
        return [];
    }
    
    $emails = [];
    $total = @imap_num_msg($connection);
    
    if ($total === 0) {
        @imap_close($connection);
        return [];
    }
    
    $startNum = max(1, $total - $limit + 1);
    $endNum = $total;
    
    for ($i = $endNum; $i >= $startNum; $i--) {
        $header = @imap_headerinfo($connection, $i);
        if (!$header) {
            continue;
        }
        
        $overview = @imap_fetch_overview($connection, $i, 0);
        
        $from = isset($header->from[0]) ? $header->from[0] : null;
        $senderName = $from ? (isset($from->personal) ? $from->personal : $from->mailbox) : 'Unknown';
        $senderEmail = $from ? ($from->mailbox . '@' . $from->host) : '';
        
        $subject = isset($header->subject) ? @imap_mime_header_decode($header->subject) : '';
        if (is_array($subject)) {
            $subject = implode('', array_map(function ($s) {
                return $s->text;
            }, $subject));
        }
        
        $date = isset($header->udate) ? $header->udate : time();
        $read = isset($overview[0]->seen) && $overview[0]->seen == 1;
        
        $uid = function_exists('imap_msg_uid') ? @imap_msg_uid($connection, $i) : @imap_uid($connection, $i);
        
        $emails[] = [
            'uid' => $uid ?: $i,
            'msgnum' => $i,
            'from_name' => $senderName,
            'from_email' => $senderEmail,
            'subject' => $subject ?: '(No Subject)',
            'date' => $date,
            'read' => $read
        ];
    }
    
    @imap_close($connection);
    return $emails;
}

function getEmailByUid($uid) {
    $connection = imapConnect();
    if (!$connection) {
        return null;
    }
    
    $msgnum = @imap_msgno($connection, $uid);
    if (!$msgnum) {
        @imap_close($connection);
        return null;
    }
    
    $header = @imap_headerinfo($connection, $msgnum);
    $overview = @imap_fetch_overview($connection, $msgnum, 0);
    $structure = @imap_fetchstructure($connection, $msgnum);
    
    if (!$header) {
        @imap_close($connection);
        return null;
    }
    
    $from = isset($header->from[0]) ? $header->from[0] : null;
    $senderName = $from ? (isset($from->personal) ? $from->personal : $from->mailbox) : 'Unknown';
    $senderEmail = $from ? ($from->mailbox . '@' . $from->host) : '';
    
    $subject = isset($header->subject) ? @imap_mime_header_decode($header->subject) : '';
    if (is_array($subject)) {
        $subject = implode('', array_map(function ($s) {
            return $s->text;
        }, $subject));
    }
    
    $date = isset($header->udate) ? $header->udate : time();
    
    $body = '';
    $html = '';
    $attachments = [];
    
    if (isset($structure->parts)) {
        foreach ($structure->parts as $partNum => $part) {
            if ($part->type == 0 && $part->subtype == 'PLAIN') {
                $body = @imap_fetchbody($connection, $msgnum, $partNum + 1);
                $body = safeQprint($body);
            } elseif ($part->type == 0 && $part->subtype == 'HTML') {
                $html = @imap_fetchbody($connection, $msgnum, $partNum + 1);
                $html = safeQprint($html);
            } elseif ($part->type >= 2) {
                $attachment = getAttachment($connection, $msgnum, $partNum + 1, $part);
                if ($attachment) {
                    $attachments[] = $attachment;
                }
            }
        }
    }
    
    if (!$body && !$html) {
        $body = @imap_fetchbody($connection, $msgnum, '1');
        $body = safeQprint($body);
    }
    
    @imap_close($connection);
    
    return [
        'uid' => $uid,
        'from_name' => $senderName,
        'from_email' => $senderEmail,
        'subject' => $subject ?: '(No Subject)',
        'date' => $date,
        'body' => $html ?: nl2br(htmlspecialchars($body)),
        'attachments' => $attachments
    ];
}

function getAttachment($connection, $msgnum, $partNum, $part) {
    $filename = isset($part->dparameters[0]->attribute) ? $part->dparameters[0]->value : '';
    if (!$filename && isset($part->parameters[0]->attribute)) {
        $filename = $part->parameters[0]->value;
    }
    
    if (!$filename) {
        return null;
    }
    
    return [
        'name' => $filename,
        'part' => $partNum,
        'type' => $part->type,
        'subtype' => $part->subtype
    ];
}

function markAsRead($uid) {
    $connection = imapConnect();
    if (!$connection) {
        return false;
    }
    
    $msgnum = @imap_msgno($connection, $uid);
    if (!$msgnum) {
        @imap_close($connection);
        return false;
    }
    
    @imap_setflag_full($connection, $msgnum, '\\Seen');
    @imap_close($connection);
    return true;
}

function deleteEmail($uid) {
    $connection = imapConnect();
    if (!$connection) {
        return false;
    }
    
    $msgnum = @imap_msgno($connection, $uid);
    if (!$msgnum) {
        @imap_close($connection);
        return false;
    }
    
    @imap_delete($connection, $msgnum);
    @imap_expunge($connection);
    @imap_close($connection);
    return true;
}

function searchEmails($query, $folder = 'INBOX', $limit = 50) {
    $connection = imapConnect($folder);
    if (!$connection) {
        return [];
    }
    
    $emails = [];
    $search = @imap_search($connection, 'ALL');
    
    if (!$search) {
        @imap_close($connection);
        return [];
    }
    
    $search = array_slice($search, 0, $limit * 3);
    
    foreach ($search as $msgnum) {
        $header = @imap_headerinfo($connection, $msgnum);
        if (!$header) continue;
        
        $overview = @imap_fetch_overview($connection, $msgnum, 0);
        
        $from = isset($header->from[0]) ? $header->from[0] : null;
        $senderName = $from ? (isset($from->personal) ? $from->personal : $from->mailbox) : 'Unknown';
        $senderEmail = $from ? ($from->mailbox . '@' . $from->host) : '';
        
        $subject = isset($header->subject) ? @imap_mime_header_decode($header->subject) : '';
        if (is_array($subject)) {
            $subject = implode('', array_map(function ($s) {
                return $s->text;
            }, $subject));
        }
        
        $q = strtolower($query);
        if (strpos(strtolower($senderName), $q) === false &&
            strpos(strtolower($senderEmail), $q) === false &&
            strpos(strtolower($subject), $q) === false) {
            continue;
        }
        
        $date = isset($header->udate) ? $header->udate : time();
        $read = isset($overview[0]->seen) && $overview[0]->seen == 1;
        
        $uid = function_exists('imap_msg_uid') ? @imap_msg_uid($connection, $msgnum) : @imap_uid($connection, $msgnum);
        
        if (count($emails) >= $limit) break;
        
        $emails[] = [
            'uid' => $uid ?: $msgnum,
            'msgnum' => $msgnum,
            'from_name' => $senderName,
            'from_email' => $senderEmail,
            'subject' => $subject ?: '(No Subject)',
            'date' => $date,
            'read' => $read
        ];
    }
    
    @imap_close($connection);
    return $emails;
}