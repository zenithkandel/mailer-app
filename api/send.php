<?php
require_once __DIR__ . '/../config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$to = $_POST['to'] ?? '';
$cc = $_POST['cc'] ?? '';
$bcc = $_POST['bcc'] ?? '';
$subject = $_POST['subject'] ?? '';
$bodyHtml = $_POST['body_html'] ?? '';
$bodyText = $_POST['body_text'] ?? '';
$priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 3;
$readReceipt = isset($_POST['read_receipt']) && $_POST['read_receipt'] === '1';
$replyToUid = $_POST['reply_to_uid'] ?? '';
$forwardUid = $_POST['forward_uid'] ?? '';

if (!$to && !$bcc) {
    http_response_code(400);
    echo json_encode(['error' => 'At least one recipient required']);
    exit;
}

if (!$subject && !$bodyHtml && !$bodyText) {
    http_response_code(400);
    echo json_encode(['error' => 'Subject or body required']);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->Port = SMTP_PORT;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USER;
    $mail->Password = MAIL_PASS;
    $mail->setFrom(MAIL_USER, SMTP_FROM_NAME);

    $recipients = explode(',', $to);
    foreach ($recipients as $email) {
        $email = trim($email);
        if ($email) {
            $mail->addAddress($email);
        }
    }

    if ($cc) {
        $ccRecipients = explode(',', $cc);
        foreach ($ccRecipients as $email) {
            $email = trim($email);
            if ($email) {
                $mail->addCC($email);
            }
        }
    }

    if ($bcc) {
        $bccRecipients = explode(',', $bcc);
        foreach ($bccRecipients as $email) {
            $email = trim($email);
            if ($email) {
                $mail->addBCC($email);
            }
        }
    }

    if ($readReceipt) {
        $mail->ConfirmReadingTo = MAIL_USER;
    }

    $mail->Subject = $subject ?: '(No Subject)';
    $mail->Priority = $priority;

    if ($bodyHtml) {
        $mail->isHTML(true);
        $mail->Body = $bodyHtml;
        if ($bodyText) {
            $mail->AltBody = $bodyText;
        } else {
            $mail->AltBody = strip_tags($bodyHtml);
        }
    } else {
        $mail->isHTML(false);
        $mail->Body = $bodyText ?: '';
    }

    $uploadDir = __DIR__ . '/../data/attachments/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
        $count = count($_FILES['attachments']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                $filename = $_FILES['attachments']['name'][$i];
                $tmpFile = $_FILES['attachments']['tmp_name'][$i];
                $mail->addAttachment($tmpFile, $filename);
            }
        }
    }

    $mail->send();

    try {
        $imap = getWritableImapConnection('INBOX');
        $sentFolder = '{' . MAIL_HOST . ':' . IMAP_PORT . '/imap/ssl/novalidate-cert}Sent';

        $sentMsg = "From: " . MAIL_USER . "\r\n";
        $sentMsg .= "To: " . $to . "\r\n";
        if ($cc) $sentMsg .= "Cc: " . $cc . "\r\n";
        $sentMsg .= "Subject: " . ($subject ?: '(No Subject)') . "\r\n";
        $sentMsg .= "Date: " . date('r') . "\r\n";
        $sentMsg .= "MIME-Version: 1.0\r\n";
        $sentMsg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $sentMsg .= "\r\n";
        $sentMsg .= $bodyHtml ?: nl2br(htmlspecialchars($bodyText));

        imap_append($imap, $sentFolder, $sentMsg);
        imap_close($imap, CL_EXPUNGE);
    } catch (Exception $e) {
        error_log('Could not save to Sent folder: ' . $e->getMessage());
    }

    echo json_encode(['success' => true, 'message' => 'Email sent successfully']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}