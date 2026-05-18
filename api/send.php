<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$to = trim($_POST['to'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$body = $_POST['body'] ?? '';

if (empty($to) || empty($subject) || empty($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'To, subject, and body are required']);
    exit;
}

if (!validateEmail($to)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid recipient email address']);
    exit;
}

$config = loadConfig();

if (!$config) {
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error']);
    exit;
}

if (!isset($config['smtp'])) {
    http_response_code(500);
    echo json_encode(['error' => 'SMTP configuration not found']);
    exit;
}

$mailer = new SMTPMailer($config['smtp']);
$result = $mailer->send($to, $subject, $body);

if ($result['success']) {
    addSentEntry([
        'id' => uniqid('sent_'),
        'to' => $to,
        'subject' => $subject,
        'body' => $body,
        'date' => date('c'),
        'status' => 'sent',
    ]);

    echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send email: ' . ($result['error'] ?? 'Unknown error')]);
}