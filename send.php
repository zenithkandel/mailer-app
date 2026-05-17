<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/smtp.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: compose.php');
    exit;
}

$to = trim($_POST['to'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = $_POST['message'] ?? '';

if (empty($to) || empty($subject) || empty($message)) {
    header('Location: compose.php?error=' . urlencode('All fields are required'));
    exit;
}

if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    header('Location: compose.php?error=' . urlencode('Invalid email address'));
    exit;
}

$result = smtpSendEmail($to, $subject, $message);

if ($result['success']) {
    header('Location: compose.php?success=1');
} else {
    header('Location: compose.php?error=' . urlencode($result['error']));
}
exit;