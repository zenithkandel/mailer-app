<?php

class Smtp {
    private $socket;
    private $host;
    private $port;
    private $username;
    private $password;
    private $from;
    private $to = [];
    private $cc = [];
    private $bcc = [];
    private $subject;
    private $body;
    private $replyTo;
    private $attachments = [];
    private $lastResponse;

    private $callbacks = [];

    public function __construct(string $host, int $port, string $username, string $password) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }

    public function onProgress(callable $callback): self {
        $this->callbacks[] = $callback;
        return $this;
    }

    private function emit(int $pct, string $msg): void {
        foreach ($this->callbacks as $cb) {
            $cb($pct, $msg);
        }
    }

    public function send(array $opts): bool {
        $this->from = $opts['from'] ?? '';
        $this->to = $opts['to'] ?? [];
        $this->cc = $opts['cc'] ?? [];
        $this->bcc = $opts['bcc'] ?? [];
        $this->subject = $opts['subject'] ?? '';
        $this->body = $opts['body'] ?? '';
        $this->replyTo = $opts['replyTo'] ?? '';
        $this->attachments = $opts['attachments'] ?? [];

        try {
            $this->emit(5, 'Connecting to mail server...');
            $this->connect();

            $this->emit(10, 'Sending EHLO...');
            $this->ehlo();

            $this->emit(20, 'Authenticating...');
            $this->authLogin();

            $this->emit(40, 'Preparing message...');
            $message = $this->buildMessage();

            $this->emit(50, 'Sending FROM...');
            $this->mailFrom();

            $this->emit(60, 'Sending recipients...');
            $allRecipients = array_merge($this->to, $this->cc, $this->bcc);
            foreach ($allRecipients as $rcpt) {
                $this->rcptTo($rcpt);
            }

            $this->emit(70, 'Sending message data...');
            $this->data($message);

            $this->emit(90, 'Closing connection...');
            $this->quit();

            $this->emit(100, 'Message sent successfully!');
            return true;
        } catch (Exception $e) {
            $this->emit(0, 'Error: ' . $e->getMessage());
            if ($this->socket) {
                fclose($this->socket);
            }
            throw $e;
        }
    }

    private function connect(): void {
        $errno = 0;
        $errstr = '';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 30, $context);

        if (!$this->socket) {
            throw new Exception("Connection failed: $errstr ($errno)");
        }

        stream_set_timeout($this->socket, 60);
        $this->readResponse();
    }

    private function readResponse(): string {
        $data = '';
        while ($line = fgets($this->socket, 512)) {
            $data .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        $this->lastResponse = $data;
        return $data;
    }

    private function sendCommand(string $cmd): string {
        fwrite($this->socket, $cmd . "\r\n");
        return $this->readResponse();
    }

    private function ehlo(): void {
        $response = $this->sendCommand('EHLO ' . gethostname());
        if (substr($response, 0, 3) !== '250') {
            throw new Exception("EHLO failed: $response");
        }
    }

    private function authLogin(): void {
        $response = $this->sendCommand('AUTH LOGIN');
        if (substr($response, 0, 3) !== '334') {
            throw new Exception("AUTH LOGIN failed: $response");
        }

        $response = $this->sendCommand(base64_encode($this->username));
        if (substr($response, 0, 3) !== '334') {
            throw new Exception("Username failed: $response");
        }

        $response = $this->sendCommand(base64_encode($this->password));
        if (substr($response, 0, 3) !== '235') {
            throw new Exception("Authentication failed: $response");
        }
    }

    private function mailFrom(): void {
        $response = $this->sendCommand('MAIL FROM:<' . $this->from . '>');
        if (substr($response, 0, 3) !== '250') {
            throw new Exception("MAIL FROM failed: $response");
        }
    }

    private function rcptTo(string $to): void {
        $response = $this->sendCommand('RCPT TO:<' . $to . '>');
        if (substr($response, 0, 3) !== '250' && substr($response, 0, 3) !== '251') {
            throw new Exception("RCPT TO failed for $to: $response");
        }
    }

    private function data(string $message): void {
        $response = $this->sendCommand('DATA');
        if (substr($response, 0, 3) !== '354') {
            throw new Exception("DATA command failed: $response");
        }

        fwrite($this->socket, $message . "\r\n.");
        $response = $this->readResponse();
        if (substr($response, 0, 3) !== '250') {
            throw new Exception("Message send failed: $response");
        }
    }

    private function quit(): void {
        $this->sendCommand('QUIT');
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function buildMessage(): string {
        $boundary = '----=_Part_' . bin2hex(random_bytes(16));
        $mimeBoundary = '----=_MIME_' . bin2hex(random_bytes(16));

        $headers = [];
        $headers[] = 'From: ' . $this->from;
        $headers[] = 'To: ' . implode(', ', $this->to);
        if ($this->cc) {
            $headers[] = 'Cc: ' . implode(', ', $this->cc);
        }
        if ($this->replyTo) {
            $headers[] = 'Reply-To: ' . $this->replyTo;
        }
        $headers[] = 'Subject: ' . $this->subject;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Message-ID: ' . generateMessageId();

        if (empty($this->attachments)) {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: quoted-printable';
            $body = $this->encodeQuotedPrintable($this->body);
            return implode("\r\n", $headers) . "\r\n\r\n" . $body;
        }

        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        $headers[] = 'X-Mailer: ZenithMail/1.0';

        $message = implode("\r\n", $headers) . "\r\n";

        $message .= "--" . $boundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $message .= $this->encodeQuotedPrintable($this->body) . "\r\n";

        foreach ($this->attachments as $attachment) {
            $filename = $attachment['filename'];
            $content = $attachment['content'];
            $mimeType = $attachment['mime'] ?? 'application/octet-stream';

            $message .= "--" . $boundary . "\r\n";
            $message .= "Content-Type: $mimeType; name=\"$filename\"\r\n";
            $message .= "Content-Disposition: attachment; filename=\"$filename\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split(base64_encode($content), 76, "\r\n") . "\r\n";
        }

        $message .= "--" . $boundary . "--\r\n";

        return $message;
    }

    private function encodeQuotedPrintable(string $text): string {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        $encoded = '';
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            $encoded .= $this->encodeQuotedPrintableLine($line) . "\r\n";
        }

        return rtrim($encoded, "\r\n");
    }

    private function encodeQuotedPrintableLine(string $line): string {
        $result = '';
        $length = 0;

        for ($i = 0; $i < strlen($line); $i++) {
            $char = $line[$i];
            $ord = ord($char);

            if ($ord > 126 || $ord < 33 && $ord !== 9 || $ord === 61) {
                $encoded = sprintf('=%02X', $ord);
                $result .= $encoded;
                $length += 3;
            } else {
                $result .= $char;
                $length++;
            }

            if ($length > 75) {
                $result .= "=\r\n";
                $length = 0;
            }
        }

        return rtrim($result);
    }
}