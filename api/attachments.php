<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$folder = $_GET['folder'] ?? 'INBOX';
$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$partId = $_GET['part_id'] ?? '';
$downloadAll = isset($_GET['download_all']) && $_GET['download_all'] === '1';

if (!$uid) {
    http_response_code(400);
    echo json_encode(['error' => 'UID is required']);
    exit;
}

try {
    $imap = getImapConnection($folder);

    $msgs = imap_search($imap, 'UID ' . $uid, SE_UID);
    if (!$msgs) {
        imap_close($imap);
        http_response_code(404);
        echo json_encode(['error' => 'Message not found']);
        exit;
    }

    $msgNum = $msgs[0];
    $structure = imap_fetchstructure($imap, $msgNum);

    $attachments = [];

    function extractAttachments($imap, $msgNum, $part, $prefix = '') {
        $attachments = [];

        if ($part->ifdisposition && strtolower($part->disposition ?? '') == 'attachment') {
            $filename = '';
            if (isset($part->dparameters)) {
                foreach ($part->dparameters as $param) {
                    if (strtolower($param->attribute) == 'filename') {
                        $filename = $param->value;
                        break;
                    }
                }
            }
            if (!$filename && isset($part->parameters)) {
                foreach ($part->parameters as $param) {
                    if (strtolower($param->attribute) == 'name') {
                        $filename = $param->value;
                        break;
                    }
                }
            }

            $data = imap_fetchbody($imap, $msgNum, $prefix);
            $encoding = $part->encoding ?? 0;

            switch ($encoding) {
                case 3:
                    $data = base64_decode($data);
                    break;
                case 4:
                    $data = quoted_printable_decode($data);
                    break;
            }

            $attachments[] = [
                'part_id' => $prefix,
                'filename' => $filename ?: 'attachment',
                'mime_type' => $part->ctype ?? 'application/octet-stream',
                'data' => base64_encode($data)
            ];
        }

        if (isset($part->parts)) {
            foreach ($part->parts as $idx => $subpart) {
                $subPrefix = $prefix ? $prefix . '.' . ($idx + 1) : ($idx + 1);
                $attachments = array_merge($attachments, extractAttachments($imap, $msgNum, $subpart, $subPrefix));
            }
        }

        return $attachments;
    }

    $attachments = extractAttachments($imap, $msgNum, $structure, '');

    if ($downloadAll && count($attachments) > 1) {
        $zip = new ZipArchive();
        $tempFile = tempnam(sys_get_temp_dir(), 'webmail_');

        if ($zip->open($tempFile, ZipArchive::CREATE) === TRUE) {
            foreach ($attachments as $att) {
                $zip->addFromString($att['filename'], base64_decode($att['data']));
            }
            $zip->close();

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="attachments_' . $uid . '.zip"');
            header('Content-Length: ' . filesize($tempFile));
            readfile($tempFile);
            unlink($tempFile);
            exit;
        }
    }

    if (!$partId) {
        imap_close($imap);
        echo json_encode(['attachments' => $attachments]);
        exit;
    }

    foreach ($attachments as $att) {
        if ($att['part_id'] === $partId) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer(base64_decode($att['data']));

            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . $att['filename'] . '"');
            header('Content-Length: ' . strlen(base64_decode($att['data'])));

            echo base64_decode($att['data']);
            break;
        }
    }

    imap_close($imap);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}