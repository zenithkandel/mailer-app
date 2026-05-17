<?php

require_once __DIR__ . '/config.php';

function sendEmail($to, $subject, $body) {
    $config = getUserConfig();
    $senderName = $config['senderName'] ?: SMTP_USER;
    $from = SMTP_USER;
    
    $socket = @fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 30);
    if (!$socket) {
        return ['success' => false, 'error' => "Connection failed: $errstr ($errno)"];
    }
    
    $response = fgets($socket, 512);
    if (substr($response, 0, 3) !== '220') {
        fclose($socket);
        return ['success' => false, 'error' => 'SMTP connection failed'];
    }
    
    fwrite($socket, "EHLO " . SMTP_HOST . "\r\n");
    $response = fgets($socket, 512);
    
    fwrite($socket, "STARTTLS\r\n");
    $response = fgets($socket, 512);
    if (substr($response, 0, 3) !== '220') {
        fclose($socket);
        return ['success' => false, 'error' => 'STARTTLS failed'];
    }
    
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        fclose($socket);
        return ['success' => false, 'error' => 'TLS encryption failed'];
    }
    
    fwrite($socket, "EHLO " . SMTP_HOST . "\r\n");
    while (substr($response, 3, 1) !== ' ') {
        $response = fgets($socket, 512);
    }
    
    fwrite($socket, "AUTH LOGIN\r\n");
    $response = fgets($socket, 512);
    if (substr($response, 0, 3) !== '334') {
        fclose($socket);
        return ['success' => false, 'error' => 'AUTH LOGIN failed'];
    }
    
    fwrite($socket, base64_encode(SMTP_USER) . "\r\n");
    $response = fgets($socket, 512);
    if (substr($response, 0, 3) !== '334') {
        fclose($socket);
        return ['success' => false, 'error' => 'Username failed'];
    }
    
    fwrite($socket, base64_encode(SMTP_PASS) . "\r\n");
    $response = fgets($socket, 512);
    if (substr($response, 0, 3) !== '235') {
        fclose($socket);
        return ['success' => false, 'error' => 'Authentication failed'];
    }
    
    $messageId = '<' . time() . '.' . rand(1000, 9999) . '@' . SMTP_HOST . '>';
    $date = date('D, d M Y H:i:s O');
    
    $headers = "From: $senderName <$from>\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "Date: $date\r\n";
    $headers .= "Message-ID: $messageId\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 7bit\r\n";
    
    fwrite($socket, "MAIL FROM:<$from>\r\n");
    $response = fgets($socket, 512);
    
    fwrite($socket, "RCPT TO:<$to>\r\n");
    $response = fgets($socket, 512);
    if (substr($response, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => 'Recipient rejected'];
    }
    
    fwrite($socket, "DATA\r\n");
    $response = fgets($socket, 512);
    
    fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
    $response = fgets($socket, 512);
    if (substr($response, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => 'Message sending failed'];
    }
    
    fwrite($socket, "QUIT\r\n");
    fclose($socket);
    
    return ['success' => true];
}