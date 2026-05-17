<?php

function imap_connect($email, $password) {
    $config = require __DIR__ . '/config.php';
    
    $imap_host = $config['imap_host'];
    $imap_port = $config['imap_port'];
    
    $mailbox = "{" . $imap_host . ":" . $imap_port . "/imap/ssl}INBOX";
    
    $imap = @imap_open($mailbox, $email, $password);
    
    if (!$imap) {
        $error = imap_last_error();
        return ["success" => false, "error" => $error];
    }
    
    return ["success" => true, "imap" => $imap];
}

function imap_get_inbox($email, $password, $limit = 50) {
    $conn = imap_connect($email, $password);
    
    if (!$conn['success']) {
        return $conn;
    }
    
    $imap = $conn['imap'];
    
    $emails = imap_search($imap, "ALL");
    
    if (!$emails) {
        imap_close($imap);
        return ["success" => true, "emails" => []];
    }
    
    $emails = array_slice($emails, 0, $limit);
    $result = [];
    
    foreach ($emails as $num) {
        $header = imap_headerinfo($imap, $num);
        
        $from = isset($header->from[0]) ? $header->from[0] : null;
        $result[] = [
            "id" => $num,
            "from" => $from ? (isset($from->personal) && $from->personal ? $from->personal . " <" . $from->mailbox . "@" . $from->host . ">" : $from->mailbox . "@" . $from->host) : "Unknown",
            "from_address" => $from ? ($from->mailbox . "@" . $from->host) : "",
            "subject" => isset($header->subject) ? imap_mime_header_decode($header->subject)[0]->text : "(No Subject)",
            "date" => isset($header->udate) ? date("Y-m-d H:i:s", $header->udate) : "",
            "unread" => !isset($header->seen) || $header->seen == 0
        ];
    }
    
    imap_close($imap);
    
    return ["success" => true, "emails" => $result];
}

function imap_get_sent($email, $password, $limit = 50) {
    $conn = imap_connect($email, $password);
    
    if (!$conn['success']) {
        return $conn;
    }
    
    $imap = $conn['imap'];
    
    $mailboxes = imap_list($imap, "{mail.zenithkandel.com.np:993/imap/ssl}", "*");
    
    $sentBox = null;
    foreach ($mailboxes as $box) {
        if (stripos($box, 'Sent') !== false || stripos($box, 'sent') !== false) {
            $sentBox = $box;
            break;
        }
    }
    
    if (!$sentBox) {
        imap_close($imap);
        return ["success" => true, "emails" => []];
    }
    
    $sent = imap_open($sentBox, $email, $password);
    
    if (!$sent) {
        imap_close($imap);
        return ["success" => true, "emails" => []];
    }
    
    $emails = imap_search($sent, "ALL");
    
    if (!$emails) {
        imap_close($sent);
        imap_close($imap);
        return ["success" => true, "emails" => []];
    }
    
    $emails = array_slice($emails, 0, $limit);
    $result = [];
    
    foreach ($emails as $num) {
        $header = imap_headerinfo($sent, $num);
        
        $to = isset($header->to[0]) ? $header->to[0] : null;
        $result[] = [
            "id" => $num,
            "to" => $to ? (isset($to->personal) && $to->personal ? $to->personal . " <" . $to->mailbox . "@" . $to->host . ">" : $to->mailbox . "@" . $to->host) : "Unknown",
            "to_address" => $to ? ($to->mailbox . "@" . $to->host) : "",
            "subject" => isset($header->subject) ? imap_mime_header_decode($header->subject)[0]->text : "(No Subject)",
            "date" => isset($header->udate) ? date("Y-m-d H:i:s", $header->udate) : ""
        ];
    }
    
    imap_close($sent);
    imap_close($imap);
    
    return ["success" => true, "emails" => $result];
}

function imap_read_email($email, $password, $msgno) {
    $conn = imap_connect($email, $password);
    
    if (!$conn['success']) {
        return $conn;
    }
    
    $imap = $conn['imap'];
    
    $header = imap_headerinfo($imap, $msgno);
    
    $structure = imap_fetchstructure($imap, $msgno);
    
    $body = imap_body($imap, $msgno, FT_PEEK);
    
    if ($structure->encoding == 4) {
        $body = imap_qprint($body);
    } elseif ($structure->encoding == 3) {
        $body = imap_base64($body);
    }
    
    $from = isset($header->from[0]) ? $header->from[0] : null;
    
    imap_setflag_full($imap, $msgno, "\\Seen");
    
    imap_close($imap);
    
    return [
        "success" => true,
        "email" => [
            "from" => $from ? ($from->personal ? $from->personal . " <" . $from->mailbox . "@" . $from->host . ">" : $from->mailbox . "@" . $from->host) : "Unknown",
            "from_address" => $from ? ($from->mailbox . "@" . $from->host) : "",
            "subject" => isset($header->subject) ? imap_mime_header_decode($header->subject)[0]->text : "(No Subject)",
            "date" => isset($header->udate) ? date("Y-m-d H:i:s", $header->udate) : "",
            "body" => $body
        ]
    ];
}