<?php

function smtp_send($to, $subject, $body, $from, $password) {
    $config = require __DIR__ . '/config.php';
    
    $smtp_host = $config['smtp_host'];
    $smtp_port = $config['smtp_port'];
    $debug_log = [];
    
    function read_response($socket) {
        $response = "";
        do {
            $line = fgets($socket, 512);
            $response .= $line;
        } while (isset($line[3]) && substr($line, 3, 1) == '-');
        return $response;
    }
    
    if ($smtp_port == 465) {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        $debug_log[] = "Connecting to ssl://$smtp_host:$smtp_port";
        $socket = @stream_socket_client("ssl://$smtp_host:$smtp_port", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        
        if (!$socket) {
            return ["success" => false, "error" => "Connection failed: $errstr ($errno)", "debug" => $debug_log];
        }
        
        $debug_log[] = "Connected successfully";
        
        $response = read_response($socket);
        $debug_log[] = "Greeting: " . substr($response, 0, 100);
        
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            return ["success" => false, "error" => "Bad greeting: " . substr($response, 0, 50), "debug" => $debug_log];
        }
        
        fwrite($socket, "EHLO localhost\r\n");
        $debug_log[] = "Sent: EHLO localhost";
        
        $response = read_response($socket);
        $debug_log[] = "EHLO response: " . substr($response, 0, 100);
        
        if (strpos($response, 'AUTH') === false) {
            fclose($socket);
            return ["success" => false, "error" => "No AUTH available on port 465", "debug" => $debug_log];
        }
        
        fwrite($socket, "AUTH LOGIN\r\n");
        $debug_log[] = "Sent: AUTH LOGIN";
        
        $response = fgets($socket, 512);
        $debug_log[] = "Response: $response";
        
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            return ["success" => false, "error" => "AUTH LOGIN failed: $response", "debug" => $debug_log];
        }
        
        fwrite($socket, base64_encode($from) . "\r\n");
        $debug_log[] = "Sent username";
        
        $response = fgets($socket, 512);
        $debug_log[] = "Response: $response";
        
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            return ["success" => false, "error" => "Username rejected: $response", "debug" => $debug_log];
        }
        
        fwrite($socket, base64_encode($password) . "\r\n");
        $debug_log[] = "Sent password";
        
        $response = fgets($socket, 512);
        $debug_log[] = "Response: $response";
        
        if (substr($response, 0, 3) != '235') {
            fclose($socket);
            return ["success" => false, "error" => "Authentication failed: $response", "debug" => $debug_log];
        }
        
        $debug_log[] = "Authentication successful";
        
        fwrite($socket, "MAIL FROM:<$from>\r\n");
        $response = fgets($socket, 512);
        
        fwrite($socket, "RCPT TO:<$to>\r\n");
        $response = fgets($socket, 512);
        
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return ["success" => false, "error" => "RCPT TO failed: $response", "debug" => $debug_log];
        }
        
        fwrite($socket, "DATA\r\n");
        $response = fgets($socket, 512);
        
        if (substr($response, 0, 3) != '354') {
            fclose($socket);
            return ["success" => false, "error" => "DATA failed: $response", "debug" => $debug_log];
        }
        
        $headers = "From: $from\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "\r\n";
        
        fwrite($socket, $headers);
        fwrite($socket, $body . "\r\n");
        fwrite($socket, ".\r\n");
        
        $response = fgets($socket, 512);
        
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return ["success" => false, "error" => "Message send failed: $response", "debug" => $debug_log];
        }
        
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        
        return ["success" => true, "debug" => $debug_log];
        
    } elseif ($smtp_port == 587) {
        $socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 30);
        
        if (!$socket) {
            return ["success" => false, "error" => "Connection failed: $errstr ($errno)"];
        }
        
        $debug_log[] = "Connected to port 587";
        
        $response = read_response($socket);
        
        fwrite($socket, "EHLO localhost\r\n");
        $response = read_response($socket);
        $debug_log[] = "EHLO: " . substr($response, 0, 100);
        
        if (strpos($response, 'STARTTLS') === false) {
            fclose($socket);
            return ["success" => false, "error" => "STARTTLS not available", "debug" => $debug_log];
        }
        
        fwrite($socket, "STARTTLS\r\n");
        $response = fgets($socket, 512);
        
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            return ["success" => false, "error" => "STARTTLS failed: $response", "debug" => $debug_log];
        }
        
        $debug_log[] = "STARTTLS accepted, upgrading crypto";
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        
        fwrite($socket, "EHLO localhost\r\n");
        $response = read_response($socket);
        $debug_log[] = "EHLO after TLS: " . substr($response, 0, 100);
        
        if (strpos($response, 'AUTH') === false) {
            fclose($socket);
            return ["success" => false, "error" => "No AUTH after TLS", "debug" => $debug_log];
        }
        
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 512);
        
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            return ["success" => false, "error" => "AUTH LOGIN failed: $response", "debug" => $debug_log];
        }
        
        fwrite($socket, base64_encode($from) . "\r\n");
        $response = fgets($socket, 512);
        
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            return ["success" => false, "error" => "Username rejected: $response", "debug" => $debug_log];
        }
        
        fwrite($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket, 512);
        
        if (substr($response, 0, 3) != '235') {
            fclose($socket);
            return ["success" => false, "error" => "Authentication failed: $response", "debug" => $debug_log];
        }
        
        $debug_log[] = "Authentication successful";
        
        fwrite($socket, "MAIL FROM:<$from>\r\n");
        fgets($socket, 512);
        
        fwrite($socket, "RCPT TO:<$to>\r\n");
        $response = fgets($socket, 512);
        
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return ["success" => false, "error" => "RCPT TO failed: $response", "debug" => $debug_log];
        }
        
        fwrite($socket, "DATA\r\n");
        fgets($socket, 512);
        
        $headers = "From: $from\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "\r\n";
        
        fwrite($socket, $headers);
        fwrite($socket, $body . "\r\n");
        fwrite($socket, ".\r\n");
        
        $response = fgets($socket, 512);
        
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        
        if (substr($response, 0, 3) == '250') {
            return ["success" => true, "debug" => $debug_log];
        } else {
            return ["success" => false, "error" => "Message send failed: $response", "debug" => $debug_log];
        }
    }
    
    return ["success" => false, "error" => "Invalid port configuration"];
}