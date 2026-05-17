<?php
require_once '../config.php';
requireAuth();

header('Content-Type: application/json');

if (!extension_loaded('imap')) {
    echo json_encode(['error' => 'PHP IMAP extension is not installed']);
    exit;
}

$uid = intval($_GET['uid'] ?? 0);
$folder = $_GET['folder'] ?? 'INBOX';

if (!$uid) {
    echo json_encode(['error' => 'Invalid message ID']);
    exit;
}

$mbox = getImapConnection();
if (!$mbox) {
    echo json_encode(['error' => 'Cannot connect to mail server']);
    exit;
}

$folderEncode = imap_utf7_encode($folder);
$folderPath = IMAP_PREFIX . $folderEncode;

$mbox = imap_open($folderPath, $_SESSION['username'], $_SESSION['password']);
if (!$mbox) {
    echo json_encode(['error' => 'Cannot open folder']);
    exit;
}

$msgNo = imap_msgno($mbox, $uid);
$header = @imap_header($mbox, $msgNo);

if (!$header) {
    imap_close($mbox);
    echo json_encode(['error' => 'Message not found']);
    exit;
}

$structure = @imap_fetchstructure($mbox, $uid, FT_UID);

$textBody = '';
$htmlBody = '';
$attachments = [];
$inlineImages = [];

function walkStructure($mbox, $uid, $structure, string $prefix, &$textBody, &$htmlBody, &$attachments, &$inlineImages) {
    if (!isset($structure->parts)) {
        if ($structure->type == 0) {
            if (strtolower($structure->subtype) === 'plain') {
                $textBody = getPartData($mbox, $uid, $structure, $prefix);
            } elseif (strtolower($structure->subtype) === 'html') {
                $htmlBody = getPartData($mbox, $uid, $structure, $prefix);
            }
        }
        return;
    }

    foreach ($structure->parts as $i => $part) {
        $partNum = $prefix ? $prefix . '.' . ($i + 1) : ($i + 1);

        if ($part->type == 0) {
            if (strtolower($part->subtype) === 'plain' && empty($textBody)) {
                $textBody = getPartData($mbox, $uid, $part, $prefix ? $prefix : '');
            } elseif (strtolower($part->subtype) === 'html' && empty($htmlBody)) {
                $htmlBody = getPartData($mbox, $uid, $part, $prefix ? $prefix : '');
            }
        }

        if ($part->type == 1 && strtolower($part->subtype) === 'related') {
            $relatedData = walkRelatedParts($mbox, $uid, $part, $partNum);
            if (!empty($relatedData['html'])) $htmlBody = $relatedData['html'];
            if (!empty($relatedData['text'])) $textBody = $relatedData['text'];
            $inlineImages = array_merge($inlineImages, $relatedData['images']);
        }

        if (isset($part->disposition) && strtolower($part->disposition) === 'attachment') {
            $filename = 'attachment';
            if (isset($part->dparameters)) {
                foreach ($part->dparameters as $param) {
                    if (strtolower($param->attribute) === 'filename') {
                        $filename = decodeHeader($param->value);
                        break;
                    }
                }
            }
            if ($filename === 'attachment' && isset($part->parameters)) {
                foreach ($part->parameters as $param) {
                    if (strtolower($param->attribute) === 'name') {
                        $filename = decodeHeader($param->value);
                        break;
                    }
                }
            }

            $content = imap_fetchbody($mbox, $uid, $partNum, FT_UID);
            if ($part->encoding == 3) {
                $content = base64_decode($content);
            } elseif ($part->encoding == 4) {
                $content = imap_qprint($content);
            }

            $attachments[] = [
                'filename' => $filename,
                'part' => $partNum,
                'size' => strlen($content),
                'mime' => $part->ctype_primary . '/' . $part->ctype_secondary
            ];
        }

        if (isset($part->parts)) {
            walkStructure($mbox, $uid, $part, $partNum, $textBody, $htmlBody, $attachments, $inlineImages);
        }
    }
}

function getPartData($mbox, $uid, $part, $prefix) {
    if (empty($prefix)) return '';
    $data = imap_fetchbody($mbox, $uid, $prefix, FT_UID);

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

function walkRelatedParts($mbox, $uid, $part, $prefix) {
    $result = ['html' => '', 'text' => '', 'images' => []];

    if (!isset($part->parts)) return $result;

    foreach ($part->parts as $i => $subPart) {
        $partNum = $prefix . '.' . ($i + 1);

        if ($subPart->type == 0) {
            if (strtolower($subPart->subtype) === 'html') {
                $result['html'] = getPartData($mbox, $uid, $subPart, $partNum);
            } elseif (strtolower($subPart->subtype) === 'plain') {
                $result['text'] = getPartData($mbox, $uid, $subPart, $partNum);
            }
        }

        if (isset($subPart->disposition) && strtolower($subPart->disposition) === 'inline') {
            $filename = '';
            $cid = '';
            if (isset($subPart->dparameters)) {
                foreach ($subPart->dparameters as $param) {
                    if (strtolower($param->attribute) === 'filename') {
                        $filename = decodeHeader($param->value);
                    } elseif (strtolower($param->attribute) === 'cid') {
                        $cid = trim($param->value, '<>');
                    }
                }
            }

            $content = imap_fetchbody($mbox, $uid, $partNum, FT_UID);
            if ($subPart->encoding == 3) {
                $content = base64_decode($content);
            } elseif ($subPart->encoding == 4) {
                $content = imap_qprint($content);
            }

            $result['images'][] = [
                'cid' => $cid,
                'filename' => $filename,
                'part' => $partNum,
                'content' => base64_encode($content),
                'mime' => $subPart->ctype_primary . '/' . $subPart->ctype_secondary
            ];
        }
    }

    return $result;
}

walkStructure($mbox, $uid, $structure, '', $textBody, $htmlBody, $attachments, $inlineImages);

$body = $htmlBody ?: $textBody;
$body = sanitizeHtml($body);

if (!empty($inlineImages)) {
    foreach ($inlineImages as $img) {
        if ($img['cid']) {
            $dataUrl = 'data:' . $img['mime'] . ';base64,' . $img['content'];
            $body = str_replace('cid:' . $img['cid'], $dataUrl, $body);
        }
    }
}

$from = getDisplayNameFromHeader($header->from);
$fromEmail = getEmailFromHeader($header->from);
$to = getDisplayNameFromHeader($header->to);
$toEmail = getEmailFromHeader($header->to);
$subject = decodeHeader($header->subject);
$date = isset($header->date) ? date('F j, Y, g:i a', strtotime($header->date)) : '';

$cc = [];
if (!empty($header->cc)) {
    foreach ($header->cc as $ccEntry) {
        $cc[] = [
            'name' => getDisplayNameFromHeader($ccEntry),
            'email' => getEmailFromHeader($ccEntry)
        ];
    }
}

$bcc = [];
if (!empty($header->bcc)) {
    foreach ($header->bcc as $bccEntry) {
        $bcc[] = [
            'name' => getDisplayNameFromHeader($bccEntry),
            'email' => getEmailFromHeader($bccEntry)
        ];
    }
}

$overview = @imap_fetch_overview($mbox, $uid, FT_UID);
$isFlagged = !empty($overview[0]->flagged);

imap_close($mbox);

echo json_encode([
    'uid' => $uid,
    'folder' => $folder,
    'from' => ['name' => $from, 'email' => $fromEmail],
    'to' => ['name' => $to, 'email' => $toEmail],
    'cc' => $cc,
    'bcc' => $bcc,
    'subject' => $subject,
    'date' => $date,
    'body' => $body,
    'attachments' => $attachments,
    'isFlagged' => $isFlagged
]);