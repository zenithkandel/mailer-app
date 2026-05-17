<?php
require_once 'config.php';

if (empty($_SESSION['authenticated'])) {
    header('Location: index.php');
    exit;
}

$uid = intval($_GET['uid'] ?? 0);
$folder = $_GET['folder'] ?? 'INBOX';

if (!$uid) {
    die('Invalid message');
}

$mbox = getImapConnection();
if (!$mbox) {
    die('Cannot connect to mail server');
}

$folderEncoded = imap_utf7_encode($folder);
$folderPath = IMAP_PREFIX . $folderEncoded;

$mboxFolder = imap_open($folderPath, $_SESSION['username'], $_SESSION['password']);
if (!$mboxFolder) {
    die('Cannot open folder');
}

$header = imap_header($mboxFolder, imap_msgno($mboxFolder, $uid));
if (!$header) {
    imap_close($mboxFolder);
    die('Message not found');
}

$structure = imap_fetchstructure($mboxFolder, $uid, FT_UID);

$body = '';
$textBody = '';
$htmlBody = '';

function getPart($mbox, $uid, $part, $prefix = '') {
    $data = imap_fetchbody($mbox, $uid, $prefix . $part, FT_UID);

    if ($part->encoding == 3) {
        $data = base64_decode($data);
    } elseif ($part->encoding == 4) {
        $data = imap_qprint($data);
    }

    if (isset($part->parameters) && is_array($part->parameters)) {
        foreach ($part->parameters as $param) {
            if (strtolower($param->attribute) === 'charset') {
                $data = mb_convert_encoding($data, 'UTF-8', $param->value);
            }
        }
    }

    return $data;
}

function walkStructure($mbox, $uid, $structure, $prefix = '') {
    global $textBody, $htmlBody;

    if (!isset($structure->parts)) {
        if (strtolower($structure->subtype) === 'plain') {
            $textBody = getPart($mbox, $uid, $structure, $prefix);
        } elseif (strtolower($structure->subtype) === 'html') {
            $htmlBody = getPart($mbox, $uid, $structure, $prefix);
        }
        return;
    }

    foreach ($structure->parts as $i => $part) {
        $partNum = $prefix . ($i + 1);

        if ($part->type == 0) {
            if (strtolower($part->subtype) === 'plain' && empty($textBody)) {
                $textBody = getPart($mbox, $uid, $part, $prefix);
            } elseif (strtolower($part->subtype) === 'html' && empty($htmlBody)) {
                $htmlBody = getPart($mbox, $uid, $part, $prefix);
            }
        }

        if (isset($part->parts)) {
            walkStructure($mbox, $uid, $part, $partNum . '.');
        }
    }
}

walkStructure($mboxFolder, $uid, $structure);

$displayBody = $htmlBody ?: $textBody;
$displayBody = sanitizeHtml($displayBody);

$from = getDisplayNameFromHeader($header->from);
$fromEmail = getEmailFromHeader($header->from);
$to = getDisplayNameFromHeader($header->to);
$toEmail = getEmailFromHeader($header->to);
$subject = decodeHeader($header->subject);
$date = isset($header->date) ? date('F j, Y, g:i a', strtotime($header->date)) : '';

$cc = '';
if (!empty($header->cc)) {
    $ccParts = [];
    foreach ($header->cc as $ccEntry) {
        $ccParts[] = getDisplayNameFromHeader($ccEntry);
    }
    $cc = implode(', ', $ccParts);
}

imap_close($mboxFolder);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escapeHtml($subject); ?> - ZenithMail</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #fff;
            color: #1C1810;
            line-height: 1.6;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }
        .message-header {
            border-bottom: 1px solid #D0C8B8;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .message-header h1 {
            font-size: 24px;
            margin-bottom: 15px;
            word-wrap: break-word;
        }
        .message-meta {
            display: grid;
            grid-template-columns: 80px auto;
            gap: 10px;
            font-size: 14px;
        }
        .message-meta label {
            font-weight: 600;
            color: #6A5E50;
        }
        .message-meta span {
            word-wrap: break-word;
        }
        .message-body {
            font-size: 15px;
            line-height: 1.7;
        }
        .message-body img {
            max-width: 100%;
            height: auto;
        }
        .message-body a {
            color: #D4600A;
        }
        .print-only {
            display: none;
        }
        @media print {
            body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="message-header">
        <h1><?php echo escapeHtml($subject); ?></h1>
        <div class="message-meta">
            <label>From:</label>
            <span><?php echo escapeHtml($from); ?> &lt;<?php echo escapeHtml($fromEmail); ?>&gt;</span>

            <label>To:</label>
            <span><?php echo escapeHtml($to); ?> &lt;<?php echo escapeHtml($toEmail); ?>&gt;</span>

            <?php if ($cc): ?>
            <label>CC:</label>
            <span><?php echo escapeHtml($cc); ?></span>
            <?php endif; ?>

            <label>Date:</label>
            <span><?php echo escapeHtml($date); ?></span>
        </div>
    </div>

    <div class="message-body">
        <?php echo $displayBody; ?>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>