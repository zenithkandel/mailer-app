<?php
require_once '../config.php';
require_once '../lib/Smtp.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

function sendSse($data) {
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendSse(['pct' => 0, 'msg' => 'Invalid request']);
    exit;
}

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data) {
    sendSse(['pct' => 0, 'msg' => 'Invalid data']);
    exit;
}

$to = $data['to'] ?? [];
$cc = $data['cc'] ?? [];
$bcc = $data['bcc'] ?? [];
$subject = $data['subject'] ?? '';
$body = $data['body'] ?? '';
$attachments = $data['attachments'] ?? [];
$replyTo = $data['replyTo'] ?? '';

if (empty($to) || empty($subject)) {
    sendSse(['pct' => 0, 'msg' => 'Missing required fields']);
    exit;
}

$username = $_SESSION['username'] ?? '';
$password = $_SESSION['password'] ?? '';

if (empty($username) || empty($password)) {
    sendSse(['pct' => 0, 'msg' => 'Session expired']);
    exit;
}

$from = $username;

$processedAttachments = [];
foreach ($attachments as $att) {
    $path = $att['path'] ?? '';
    if ($path && file_exists($path)) {
        $content = file_get_contents($path);
        $processedAttachments[] = [
            'filename' => $att['filename'],
            'content' => $content,
            'mime' => $att['mime'] ?? 'application/octet-stream'
        ];
    }
}

try {
    $smtp = new Smtp(SMTP_HOST, SMTP_PORT, $username, $password);

    $smtp->onProgress(function($pct, $msg) use (&$lastPct) {
        if ($pct > $lastPct) {
            $lastPct = $pct;
            sendSse(['pct' => $pct, 'msg' => $msg]);
        }
    });

    $lastPct = 0;

    $smtp->send([
        'from' => $from,
        'to' => $to,
        'cc' => $cc,
        'bcc' => $bcc,
        'subject' => $subject,
        'body' => $body,
        'replyTo' => $replyTo,
        'attachments' => $processedAttachments
    ]);

    foreach ($attachments as $att) {
        $path = $att['path'] ?? '';
        if ($path && file_exists($path)) {
            @unlink($path);
        }
    }

    sendSse(['pct' => 100, 'msg' => 'Message sent successfully!']);

} catch (Exception $e) {
    sendSse(['pct' => 0, 'msg' => 'Error: ' . $e->getMessage()]);
}