<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

set_time_limit(300);

$draftsFile = __DIR__ . '/data/drafts.json';
$drafts = json_decode(file_get_contents($draftsFile), true);

$now = time();
$sent = [];
$failed = [];

foreach ($drafts['drafts'] as $i => $draft) {
    if (!empty($draft['scheduled_at']) && $draft['scheduled_at'] <= $now) {
        if (sendScheduledEmail($draft)) {
            $sent[] = $draft['id'];
        } else {
            $failed[] = $draft['id'];
        }
    }
}

foreach ($sent as $id) {
    $drafts['drafts'] = array_filter($drafts['drafts'], function($d) use ($id) {
        return $d['id'] !== $id;
    });
}

if (!empty($failed)) {
    foreach ($drafts['drafts'] as &$d) {
        if (in_array($d['id'], $failed)) {
            $d['failed'] = true;
        }
    }
}

file_put_contents($draftsFile, json_encode($drafts));

echo "Processed scheduled emails. Sent: " . count($sent) . ", Failed: " . count($failed) . "\n";

function sendScheduledEmail($draft) {
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

        $recipients = explode(',', $draft['to']);
        foreach ($recipients as $email) {
            $email = trim($email);
            if ($email) {
                $mail->addAddress($email);
            }
        }

        if (!empty($draft['cc'])) {
            $ccRecipients = explode(',', $draft['cc']);
            foreach ($ccRecipients as $email) {
                $email = trim($email);
                if ($email) {
                    $mail->addCC($email);
                }
            }
        }

        if (!empty($draft['bcc'])) {
            $bccRecipients = explode(',', $draft['bcc']);
            foreach ($bccRecipients as $email) {
                $email = trim($email);
                if ($email) {
                    $mail->addBCC($email);
                }
            }
        }

        $mail->Subject = $draft['subject'] ?: '(No Subject)';
        $mail->Priority = $draft['priority'] ?? 3;

        if (!empty($draft['body_html'])) {
            $mail->isHTML(true);
            $mail->Body = $draft['body_html'];
            $mail->AltBody = strip_tags($draft['body_html']);
        } else {
            $mail->isHTML(false);
            $mail->Body = $draft['body_text'] ?: '';
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Scheduled email failed: ' . $e->getMessage());
        return false;
    }
}