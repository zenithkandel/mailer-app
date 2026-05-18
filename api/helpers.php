<?php

function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sanitizeOutput(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function encodeMimeHeader(string $str): string {
    return mb_encode_mimeheader($str, 'UTF-8', 'Q', "\r\n", 68);
}

function base64Body(string $body, string $charset = 'UTF-8'): string {
    return base64_encode($body);
}

function buildEmailHeaders(array $headers): string {
    $lines = [];
    foreach ($headers as $key => $val) {
        $lines[] = "$key: $val";
    }
    return implode("\r\n", $lines);
}

class SMTPMailer {
    private $host;
    private $port;
    private $security;
    private $user;
    private $pass;
    private $fromEmail;
    private $fromName;
    private $timeout = 30;
    private $socket;
    private $lastResponse;

    public function __construct(array $config) {
        $this->host = $config['host'] ?? '';
        $this->port = (int)($config['port'] ?? 465);
        $this->security = strtolower($config['security'] ?? 'ssl');
        $this->user = $config['user'] ?? '';
        $this->pass = $config['pass'] ?? '';
        $this->fromEmail = $config['from_email'] ?? $this->user;
        $this->fromName = $config['from_name'] ?? 'Mailer';
    }

    private function connect(): bool {
        $protocol = ($this->security === 'ssl') ? 'ssl' : 'tls';
        $address = "{$protocol}://{$this->host}:{$this->port}";

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ]
        ]);

        $this->socket = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            $this->lastResponse = "Connection failed: $errstr ($errno)";
            return false;
        }

        stream_set_timeout($this->socket, $this->timeout);
        $greeting = $this->readResponse();

        if (!$this->isSuccessCode($greeting)) {
            $this->lastResponse = "SMTP greeting failed: " . substr($greeting, 4);
            return false;
        }

        return true;
    }

    private function readResponse(): string {
        $line = fgets($this->socket, 512);
        $this->lastResponse = $line;
        return $line;
    }

    private function isSuccessCode(string $response): bool {
        $code = (int)substr(trim($response), 0, 3);
        return $code >= 200 && $code < 400;
    }

    private function sendCommand(string $cmd): bool {
        fwrite($this->socket, $cmd . "\r\n");
        $response = $this->readResponse();
        return $this->isSuccessCode($response);
    }

    public function send(string $to, string $subject, string $body, string $toName = ''): array {
        if (!$this->connect()) {
            return ['success' => false, 'error' => $this->lastResponse];
        }

        if (!$this->sendCommand("EHLO localhost")) {
            if (!$this->sendCommand("HELO localhost")) {
                fclose($this->socket);
                return ['success' => false, 'error' => 'EHLO/HELO failed'];
            }
        }

        if ($this->security === 'tls') {
            $this->sendCommand("STARTTLS");
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($this->socket);
                return ['success' => false, 'error' => 'TLS negotiation failed'];
            }
            $this->sendCommand("EHLO localhost");
        }

        if (!$this->sendCommand("AUTH LOGIN")) {
            fclose($this->socket);
            return ['success' => false, 'error' => 'AUTH LOGIN rejected'];
        }

        if (!$this->sendCommand(base64_encode($this->user))) {
            fclose($this->socket);
            return ['success' => false, 'error' => 'Username rejected'];
        }

        if (!$this->sendCommand(base64_encode($this->pass))) {
            fclose($this->socket);
            return ['success' => false, 'error' => 'Password rejected'];
        }

        if (!$this->sendCommand("MAIL FROM:<{$this->fromEmail}>")) {
            fclose($this->socket);
            return ['success' => false, 'error' => 'MAIL FROM rejected'];
        }

        if (!$this->sendCommand("RCPT TO:<{$to}>")) {
            fclose($this->socket);
            return ['success' => false, 'error' => 'RCPT TO rejected'];
        }

        if (!$this->sendCommand("DATA")) {
            fclose($this->socket);
            return ['success' => false, 'error' => 'DATA command rejected'];
        }

        $fromNameEnc = encodeMimeHeader($this->fromName);
        $subjectEnc = encodeMimeHeader($subject);
        $toNameEnc = encodeMimeHeader($toName);

        $headers = [
            "From: {$fromNameEnc} <{$this->fromEmail}>",
            "To: {$toNameEnc} <{$to}>",
            "Subject: {$subjectEnc}",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: base64",
            "Date: " . date('r'),
        ];
        $headerStr = buildEmailHeaders($headers);

        $bodyB64 = base64_encode($body);

        $emailData = $headerStr . "\r\n\r\n" . $bodyB64 . "\r\n.";

        fwrite($this->socket, $emailData . "\r\n");
        $response = $this->readResponse();

        fclose($this->socket);

        if (!$this->isSuccessCode($response)) {
            return ['success' => false, 'error' => 'Message rejected: ' . substr($response, 4)];
        }

        return ['success' => true];
    }
}