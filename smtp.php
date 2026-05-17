<?php

require_once __DIR__ . '/config.php';

function readResponse($socket)
{
    $response = '';
    while (true) {
        $line = fgets($socket, 512);
        if ($line === false) {
            break;
        }
        $response .= $line;
        if (strlen($line) < 4 || substr($line, 3, 1) !== '-') {
            break;
        }
    }
    return $response;
}

function sendCommand($socket, $command)
{
    if ($command !== null) {
        fwrite($socket, $command . "\r\n");
    }
    $response = readResponse($socket);
    $code = (strlen($response) >= 3) ? substr($response, 0, 3) : '';
    return [$code, $response];
}

function parseEhloCapabilities($response)
{
    $capabilities = [];
    $lines = preg_split("/\r\n|\n|\r/", trim($response));
    foreach ($lines as $line) {
        if (substr($line, 0, 3) !== '250') {
            continue;
        }
        $cap = trim(substr($line, 4));
        if ($cap !== '') {
            $capabilities[] = $cap;
        }
    }
    return $capabilities;
}

function getAuthMethods($capabilities)
{
    $methods = [];
    foreach ($capabilities as $cap) {
        if (stripos($cap, 'AUTH=') === 0) {
            $cap = 'AUTH ' . substr($cap, 5);
        }
        if (stripos($cap, 'AUTH ') === 0) {
            $parts = preg_split('/\s+/', $cap);
            array_shift($parts);
            foreach ($parts as $method) {
                $method = strtoupper(trim($method));
                if ($method !== '') {
                    $methods[] = $method;
                }
            }
        }
    }
    return array_values(array_unique($methods));
}

function authPlain($socket, $username, $password)
{
    $authString = base64_encode("\0" . $username . "\0" . $password);
    list($code, $response) = sendCommand($socket, "AUTH PLAIN " . $authString);
    if ($code === '235') {
        return ['success' => true, 'response' => $response];
    }
    if ($code === '334') {
        list($code, $response) = sendCommand($socket, $authString);
        if ($code === '235') {
            return ['success' => true, 'response' => $response];
        }
    }
    return ['success' => false, 'response' => $response];
}

function authLogin($socket, $username, $password)
{
    list($code, $response) = sendCommand($socket, "AUTH LOGIN");
    if ($code !== '334') {
        return ['success' => false, 'response' => $response];
    }
    list($code, $response) = sendCommand($socket, base64_encode($username));
    if ($code !== '334') {
        return ['success' => false, 'response' => $response];
    }
    list($code, $response) = sendCommand($socket, base64_encode($password));
    if ($code !== '235') {
        return ['success' => false, 'response' => $response];
    }
    return ['success' => true, 'response' => $response];
}

function authenticate($socket, $username, $password, $methods)
{
    $result = ['success' => false, 'response' => 'Authentication failed'];
    $attempted = false;

    if (in_array('PLAIN', $methods, true)) {
        $attempted = true;
        $result = authPlain($socket, $username, $password);
        if ($result['success']) {
            return $result;
        }
    }

    if (in_array('LOGIN', $methods, true)) {
        $attempted = true;
        $result = authLogin($socket, $username, $password);
        if ($result['success']) {
            return $result;
        }
    }

    if (!$attempted) {
        $result = authPlain($socket, $username, $password);
        if ($result['success']) {
            return $result;
        }
        $result = authLogin($socket, $username, $password);
    }

    return $result;
}

function sendEmail($to, $subject, $body)
{
    $config = getUserConfig();
    $senderName = $config['senderName'] ?: SMTP_USER;
    $from = SMTP_FROM ?: SMTP_USER;

    $host = SMTP_HOST;
    $port = SMTP_PORT;

    $protocol = ($port === 465) ? 'ssl' : 'tcp';

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);

    $url = $protocol . '://' . $host . ':' . $port;
    $socket = @stream_socket_client($url, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);

    if (!$socket) {
        return ['success' => false, 'error' => "Connection failed: $errstr ($errno)"];
    }

    stream_set_timeout($socket, 30);

    $response = fgets($socket, 512);
    if (substr($response, 0, 3) !== '220') {
        fclose($socket);
        return ['success' => false, 'error' => 'SMTP connection failed: ' . $response];
    }

    $clientHost = 'localhost';
    if (!empty($_SERVER['SERVER_NAME'])) {
        $clientHost = $_SERVER['SERVER_NAME'];
    } elseif (!empty($_SERVER['SERVER_ADDR'])) {
        $clientHost = $_SERVER['SERVER_ADDR'];
    } elseif (function_exists('gethostname')) {
        $host = gethostname();
        if (!empty($host)) {
            $clientHost = $host;
        }
    }

    list($code, $response) = sendCommand($socket, "EHLO " . $clientHost);
    if ($code !== '250') {
        list($code, $response) = sendCommand($socket, "HELO " . $clientHost);
        if ($code !== '250') {
            fclose($socket);
            return ['success' => false, 'error' => 'EHLO/HELO failed: ' . $response];
        }
        $capabilities = [];
    } else {
        $capabilities = parseEhloCapabilities($response);
    }

    if ($port === 587) {
        list($code, $response) = sendCommand($socket, "STARTTLS");
        if ($code !== '220') {
            fclose($socket);
            return ['success' => false, 'error' => 'STARTTLS failed: ' . $response];
        }

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return ['success' => false, 'error' => 'TLS encryption failed'];
        }

        list($code, $response) = sendCommand($socket, "EHLO " . $clientHost);
        if ($code !== '250') {
            fclose($socket);
            return ['success' => false, 'error' => 'EHLO after STARTTLS failed: ' . $response];
        }
        $capabilities = parseEhloCapabilities($response);
    }

    $methods = getAuthMethods($capabilities);
    $authResult = authenticate($socket, SMTP_USER, SMTP_PASS, $methods);
    if (!$authResult['success']) {
        fclose($socket);
        return ['success' => false, 'error' => 'Authentication failed: ' . $authResult['response']];
    }

    $messageId = '<' . time() . '.' . rand(1000, 9999) . '@' . SMTP_HOST . '>';
    $date = date('D, d M Y H:i:s O');

    $headers = "From: $senderName <$from>\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "Date: $date\r\n";
    $headers .= "Message-ID: $messageId\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 7bit\r\n";

    fwrite($socket, "MAIL FROM:<$from>\r\n");
    $response = fgets($socket, 512);

    fwrite($socket, "RCPT TO:<$to>\r\n");
    $response = fgets($socket, 512);
    if (substr($response, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => 'Recipient rejected: ' . $response];
    }

    fwrite($socket, "DATA\r\n");
    $response = fgets($socket, 512);

    fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
    $response = fgets($socket, 512);
    if (substr($response, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => 'Message sending failed: ' . $response];
    }

    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return ['success' => true];
}