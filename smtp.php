<?php

function smtpConnect() {
    $host = SMTP_HOST;
    $port = 587; // Use submission port with STARTTLS

    $socket = @fsockopen($host, $port, $errno, $errstr, 10);

    if (!$socket) {
        return ['success' => false, 'error' => "Connection failed: $errstr ($errno)"];
    }

    stream_set_timeout($socket, 10);

    // Read greeting properly - handle multi-line
    do {
        $line = fgets($socket, 512);
        $response = $line;
    } while (isset($line[3]) && $line[3] !== ' ');

    if (substr($response, 0, 3) !== '220') {
        fclose($socket);
        return ['success' => false, 'error' => "SMTP greeting failed: $response"];
    }

    // Read any additional lines after greeting
    while (!feof($socket)) {
        $line = fgets($socket, 512);
        if (!$line || $line === "\n") break;
    }

    return ['success' => true, 'socket' => $socket];
}

function smtpRead($socket, $blocking = false) {
    if ($blocking) {
        stream_set_timeout($socket, 10);
    }
    return fgets($socket, 512);
}

function smtpSendEmail($to, $subject, $body, $headers = []) {
    require_once __DIR__ . '/config.php';

    $connect = smtpConnect();
    if (!$connect['success']) {
        return $connect;
    }

    $socket = $connect['socket'];

    // EHLO with proper domain
    fwrite($socket, "EHLO " . SMTP_HOST . "\r\n");
    
    // Read all EHLO response lines
    $ehloData = '';
    do {
        $response = fgets($socket, 512);
        $ehloData .= $response;
    } while (isset($response[3]) && $response[3] !== ' ');

    if (substr($ehloData, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => 'EHLO failed: ' . $ehloData];
    }

    // Check if STARTTLS is available
    if (stripos($ehloData, 'STARTTLS') !== false) {
        fwrite($socket, "STARTTLS\r\n");
        $response = smtpRead($socket, true);
        
        if (substr($response, 0, 3) === '220') {
            // Upgrade to TLS
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return ['success' => false, 'error' => 'TLS upgrade failed'];
            }
            
            // Send EHLO again after TLS
            fwrite($socket, "EHLO " . SMTP_HOST . "\r\n");
            $response = smtpRead($socket);
            while (isset($response[3]) && $response[3] !== ' ') {
                $response = fgets($socket, 512);
            }
        }
    }

    // AUTH LOGIN
    fwrite($socket, "AUTH LOGIN\r\n");
    $response = smtpRead($socket, true);
    if (substr($response, 0, 3) !== '334') {
        fclose($socket);
        return ['success' => false, 'error' => 'AUTH LOGIN failed: ' . $response];
    }

    // Username
    fwrite($socket, base64_encode(SMTP_USER) . "\r\n");
    $response = smtpRead($socket, true);
    if (substr($response, 0, 3) !== '334') {
        fclose($socket);
        return ['success' => false, 'error' => 'Username failed: ' . $response];
    }

    // Password
    fwrite($socket, base64_encode(SMTP_PASS) . "\r\n");
    $response = smtpRead($socket, true);
    if (substr($response, 0, 3) !== '235') {
        fclose($socket);
        return ['success' => false, 'error' => 'Authentication failed: ' . $response];
    }

    // MAIL FROM
    fwrite($socket, "MAIL FROM: <" . SMTP_FROM . ">\r\n");
    $response = smtpRead($socket, true);
    if (substr($response, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => 'MAIL FROM failed: ' . $response];
    }

    // RCPT TO
    fwrite($socket, "RCPT TO: <" . $to . ">\r\n");
    $response = smtpRead($socket, true);
    if (substr($response, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => 'RCPT TO failed: ' . $response];
    }

    // DATA
    fwrite($socket, "DATA\r\n");
    $response = smtpRead($socket, true);
    if (substr($response, 0, 3) !== '354') {
        fclose($socket);
        return ['success' => false, 'error' => 'DATA failed: ' . $response];
    }

    // Email content
    $userConfig = getUserConfig();
    $senderName = $userConfig['senderName'] ?? '';
    $fromHeader = $senderName ? '"' . $senderName . '" <' . SMTP_FROM . '>' : SMTP_FROM;
    
    $emailHeaders = [
        'From: ' . $fromHeader,
        'To: ' . $to,
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Date: ' . date('r')
    ];

    foreach ($headers as $key => $value) {
        $emailHeaders[] = $key . ': ' . $value;
    }

    $fullBody = implode("\r\n", $emailHeaders) . "\r\n\r\n" . $body . "\r\n.\r\n";

    fwrite($socket, $fullBody);
    $response = smtpRead($socket, true);

    fwrite($socket, "QUIT\r\n");
    @fgets($socket, 512);
    fclose($socket);

    if (substr($response, 0, 3) === '250') {
        logSentEmail($to, $subject);
        return ['success' => true, 'message' => 'Email sent successfully'];
    }

    return ['success' => false, 'error' => 'Send failed: ' . $response];
}

function logSentEmail($to, $subject) {
    $logFile = SENT_LOG_FILE;
    $entry = [
        'to' => $to,
        'subject' => $subject,
        'date' => date('Y-m-d H:i:s')
    ];

    $logs = [];
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        $logs = json_decode($content, true) ?: [];
    }

    array_unshift($logs, $entry);

    if (count($logs) > 100) {
        $logs = array_slice($logs, 0, 100);
    }

    file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
}

function getSentLog() {
    $logFile = SENT_LOG_FILE;
    if (!file_exists($logFile)) {
        return [];
    }
    $content = file_get_contents($logFile);
    return json_decode($content, true) ?: [];
}