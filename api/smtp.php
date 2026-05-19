<?php
class SMTPMailer {
    private $host, $port, $security, $user, $pass, $fromEmail, $fromName;
    private $socket, $timeout = 30;

    public function __construct(array $config) {
        $this->host = $config['host'] ?? '';
        $this->port = (int)($config['port'] ?? 465);
        $this->security = strtolower($config['security'] ?? 'ssl');
        $this->user = $config['user'] ?? '';
        $this->pass = $config['pass'] ?? '';
        $this->fromEmail = $config['from_email'] ?? $this->user;
        $this->fromName = $config['from_name'] ?? 'Mailer';
    }

    private function connect() {
        $protocol = ($this->security === 'ssl') ? 'ssl' : 'tls';
        $address = "{$protocol}://{$this->host}:{$this->port}";

        $context = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
        ]);

        $this->socket = @stream_socket_client($address, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$this->socket) return false;

        stream_set_timeout($this->socket, $this->timeout);
        $this->read();
        return true;
    }

    private function read() {
        $response = '';
        while ($line = fgets($this->socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $response;
    }

    private function sendCmd($cmd) {
        fwrite($this->socket, $cmd . "\r\n");
        return $this->read();
    }

    private function isSuccess($response) {
        $code = (int)substr(trim($response), 0, 3);
        return $code >= 200 && $code < 400;
    }

    private function encodeHeader($str) {
        if (empty($str)) return '';
        if (preg_match('/^[a-zA-Z0-9\s!#$%&\'*+\-\/.=?^_`{|}~]+$/', $str)) return $str;
        return '=?UTF-8?B?' . base64_encode($str) . '?=';
    }

    private function encodeBody($html) {
        $lines = explode("\n", $html);
        $encoded = [];
        foreach ($lines as $line) {
            $line = str_replace("\r", '', $line);
            $line = rtrim($line);
            if (strlen($line) > 76) {
                $encoded[] = chunk_split(base64_encode($line), 76, "\r\n");
            } else {
                $encoded[] = $line;
            }
        }
        return implode("\r\n", $encoded);
    }

    public function sendEmail($to, $subject, $body, $toName = '', $cc = '', $bcc = '', $replyTo = '', $attachments = []) {
        if (!$this->connect()) {
            return ['success' => false, 'error' => 'Connection failed'];
        }

        $this->sendCmd("EHLO localhost");
        if ($this->security === 'tls') {
            $this->sendCmd("STARTTLS");
            stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->sendCmd("EHLO localhost");
        }

        $this->sendCmd("AUTH LOGIN");
        $this->sendCmd(base64_encode($this->user));
        $this->sendCmd(base64_encode($this->pass));

        $this->sendCmd("MAIL FROM:<{$this->fromEmail}>");
        $this->sendCmd("RCPT TO:<{$to}>");
        if ($cc) $this->sendCmd("RCPT TO:<{$cc}>");
        if ($bcc) $this->sendCmd("RCPT TO:<{$bcc}>");

        $this->sendCmd("DATA");

        $fromNameEnc = $this->encodeHeader($this->fromName);
        $subjectEnc = $this->encodeHeader($subject);

        $plainBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $body));
        $plainBody = preg_replace('/<[^>]+>/', '', $plainBody);
        $plainBody = html_entity_decode($plainBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plainBody = preg_replace('/\r\n+/', "\n", $plainBody);
        $plainBody = preg_replace('/\n+/', "\n", $plainBody);
        $plainBody = trim($plainBody);

        $boundary = 'Zenith_' . bin2hex(random_bytes(12));

        $mimeHeaders = [
            "From: {$fromNameEnc} <{$this->fromEmail}>",
            "To: " . ($toName ? "{$toName} <{$to}>" : $to),
            "Subject: {$subjectEnc}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "Date: " . date('r'),
            "X-Mailer: ZenithMailer"
        ];

        if ($replyTo) $mimeHeaders[] = "Reply-To: {$replyTo}";
        if ($cc) $mimeHeaders[] = "Cc: {$cc}";

        $htmlContent = $body;

        $plainPart = "--{$boundary}\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: 7bit\r\n\r\n" .
            $plainBody . "\r\n";

        $htmlPart = "--{$boundary}\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: base64\r\n\r\n" .
            chunk_split(base64_encode($htmlContent)) . "\r\n";

        $endBoundary = "--{$boundary}--\r\n";

        $this->sendCmd(implode("\r\n", $mimeHeaders) . "\r\n\r\n" . $plainPart . $htmlPart . $endBoundary . "\r\n.");
        fclose($this->socket);

        return ['success' => true];
    }
}