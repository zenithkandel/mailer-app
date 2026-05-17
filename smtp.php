<?php

function smtpConnect() {
    $host = SMTP_HOST;
    $port = SMTP_PORT;

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $socket = @fsockopen('ssl://' . $host, $port, $errno, $errstr, 30);

    if (!$socket) {
        return ['success' => false, 'error' => "Connection failed: $errstr ($errno)"];
    }

    stream_set_timeout($socket, 30);

    // Read all server responses until we get complete greeting
    $response = '';
    while (($line = fgets($socket, 512)) !== false) {
        $response .= $line;
        if (substr($line, 3, 1) === ' ') break;
    }

    if (substr($response, 0, 3) !== '220') {
        fclose($socket);
        return ['success' => false, 'error' => "SMTP greeting failed: $response"];
    }

    return ['success' => true, 'socket' => $socket, 'greeting' => $response];
}

function smtpCommand($socket, $command, $expectedCode = null) {
    fwrite($socket, $command . "\r\n");
    $response = fgets($socket, 512);

    if ($expectedCode && substr($response, 0, 3) !== $expectedCode) {
        return ['success' => false, 'response' => $response];
    }

    return ['success' => true, 'response' => $response];
}

function smtpSendEmail($to, $subject, $body, $headers = []) {
    require_once __DIR__ . '/config.php';

    $connect = smtpConnect();
    if (!$connect['success']) {
        return $connect;
    }

    $socket = $connect['socket'];

    // Wait a bit for server to be ready
    usleep(100000);

    // Try EHLO with domain
    $ehlo = smtpCommand($socket, 'EHLO localhost', '250');
    if (!$ehlo['success']) {
        // Try HELO if EHLO fails
        $helo = smtpCommand($socket, 'HELO localhost', '250');
        if (!$helo['success']) {
            fclose($socket);
            return ['success' => false, 'error' => 'HELO failed: ' . $helo['response']];
        }
    }

    $auth = smtpCommand($socket, 'AUTH LOGIN', '334');
    if (!$auth['success']) {
        fclose($socket);
        return ['success' => false, 'error' => 'AUTH LOGIN failed: ' . $auth['response']];
    }

    $username = smtpCommand($socket, base64_encode(SMTP_USER), '334');
    if (!$username['success']) {
        fclose($socket);
        return ['success' => false, 'error' => 'Username failed: ' . $username['response']];
    }

    $password = smtpCommand($socket, base64_encode(SMTP_PASS), '235');
    if (!$password['success']) {
        fclose($socket);
        return ['success' => false, 'error' => 'Authentication failed: ' . $password['response']];
    }

    $from = smtpCommand($socket, 'MAIL FROM:<' . SMTP_FROM . '>', '250');
    if (!$from['success']) {
        fclose($socket);
        return ['success' => false, 'error' => 'MAIL FROM failed: ' . $from['response']];
    }

    $rcpt = smtpCommand($socket, 'RCPT TO:<' . $to . '>', '250');
    if (!$rcpt['success']) {
        fclose($socket);
        return ['success' => false, 'error' => 'RCPT TO failed: ' . $rcpt['response']];
    }

    $data = smtpCommand($socket, 'DATA', '354');
    if (!$data['success']) {
        fclose($socket);
        return ['success' => false, 'error' => 'DATA failed: ' . $data['response']];
    }

    $emailHeaders = [
        'From: ' . SMTP_FROM,
        'To: ' . $to,
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Date: ' . date('r')
    ];

    foreach ($headers as $key => $value) {
        $emailHeaders[] = $key . ': ' . $value;
    }

    $fullBody = implode("\r\n", $emailHeaders) . "\r\n\r\n" . $body . "\r\n.";

    fwrite($socket, $fullBody . "\r\n");
    $response = fgets($socket, 512);

    fwrite($socket, "QUIT\r\n");
    fgets($socket, 512);
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