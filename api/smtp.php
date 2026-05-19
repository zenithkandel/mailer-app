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

    private function send($cmd) {
        fwrite($this->socket, $cmd . "\r\n");
        return $this->read();
    }

    private function isSuccess($response) {
        $code = (int)substr(trim($response), 0, 3);
        return $code >= 200 && $code < 400;
    }

public function sendEmail($to, $subject, $body, $toName = '', $cc = '', $bcc = '', $replyTo = '', $attachments = []) {
        if (!$this->connect()) {
            return ['success' => false, 'error' => 'Connection failed'];
        }

        $this->send("EHLO localhost");
        if ($this->security === 'tls') {
            $this->send("STARTTLS");
            stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->send("EHLO localhost");
        }

        $this->send("AUTH LOGIN");
        $this->send(base64_encode($this->user));
        $this->send(base64_encode($this->pass));

        $boundary = 'Zenith_' . bin2hex(random_bytes(12));
        $plainBody = strip_tags(preg_replace('/<[^>]*>/', "\n", $body));
        $plainBody = html_entity_decode($plainBody);
        $plainBody = preg_replace('/\n+/', "\n", $plainBody);
        $plainBody = trim($plainBody);

        $this->send("MAIL FROM:<{$this->fromEmail}>");
        $this->send("RCPT TO:<{$to}>");
        if ($cc) $this->send("RCPT TO:<{$cc}>");
        if ($bcc) $this->send("RCPT TO:<{$bcc}>");

        $this->send("DATA");

        $headers = [
            "From: {$this->fromName} <{$this->fromEmail}>",
            "To: " . ($toName ? "{$toName} <{$to}>" : $to),
            "Subject: {$subject}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "Date: " . date('r')
        ];

        if ($replyTo) $headers[] = "Reply-To: {$replyTo}";
        if ($cc) $headers[] = "Cc: {$cc}";

        $headers[] = "";
        $headers[] = "--{$boundary}";
        $headers[] = "Content-Type: text/plain; charset=UTF-8; format=flowed";
        $headers[] = "Content-Transfer-Encoding: 7bit";
        $headers[] = "";
        $headers[] = $plainBody;
        $headers[] = "";
        $headers[] = "--{$boundary}";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "Content-Transfer-Encoding: quoted-printable";
        $headers[] = "";

        $encodedBody = str_replace("\n", "\r\n", quoted_printable_encode($body));
        $encodedBody = preg_replace('/=(?:\r?\n|$)/', '', $encodedBody);
        $headers[] = $encodedBody;
        $headers[] = "";
        $headers[] = "--{$boundary}--";
        $headers[] = "";

        $message = implode("\r\n", $headers);
        fwrite($this->socket, $message . "\r\n");
        $response = $this->send(".");
        fclose($this->socket);

        if ($this->isSuccess($response)) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => substr($response, 4)];
    }
}