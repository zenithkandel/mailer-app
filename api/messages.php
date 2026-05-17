<?php
require_once __DIR__ . '/../config.php';
requireLogin();

header('Content-Type: application/json');
header('Transfer-Encoding: chunked');

$folder = $_GET['folder'] ?? 'INBOX';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
$sort = $_GET['sort'] ?? 'date';
$sortDir = $_GET['sort_dir'] ?? 'DESC';

if ($page < 1) $page = 1;
if ($perPage < 1) $perPage = 50;
if ($perPage > 100) $perPage = 100;

try {
    $imap = getImapConnection($folder);
    $total = imap_num_msg($imap);

    $sortCriteria = match($sort) {
        'from' => SORTFROM,
        'subject' => SORTSUBJECT,
        'size' => SORTSIZE,
        default => SORTDATE
    };

    $sortFlag = $sortDir === 'ASC' ? 0 : 1;
    imap_sort($imap, $sortCriteria, $sortFlag, SE_NOPREFETCH);

    $start = ($page - 1) * $perPage + 1;
    $end = min($page * $perPage, $total);
    $totalPages = ceil($total / $perPage);

    ob_start();
    echo json_encode([
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
        'messages' => []
    ]);
    $initialJson = ob_get_clean();

    echo $initialJson;
    flush();

    $messages = [];

    if ($total > 0 && $start <= $total) {
        for ($i = $end; $i >= $start; $i--) {
            $header = imap_headerinfo($imap, $i);
            if (!$header) continue;

            $uid = imap_uid($imap, $i);
            $overview = imap_fetch_overview($imap, $i, 0);

            $from = '';
            $fromName = '';
            if (isset($header->from)) {
                foreach ($header->from as $addr) {
                    $fromName = isset($addr->personal) ? imap_utf8($addr->personal) : '';
                    $from = isset($addr->mailbox) ? $addr->mailbox : '';
                    if (isset($addr->host)) {
                        $from .= '@' . $addr->host;
                    }
                    break;
                }
            }

            $subject = isset($header->subject) ? imap_utf8($header->subject) : '(No Subject)';
            $subject = trim($subject);

            $date = isset($header->udate) ? $header->udate : 0;
            $size = isset($header->Size) ? $header->Size : 0;

            $flags = $overview[0]->seen ? 'seen' : 'unseen';
            if ($overview[0]->flagged) $flags .= ',flagged';
            if ($overview[0]->answered) $flags .= ',answered';
            if ($overview[0]->draft) $flags .= ',draft';

            $structure = imap_fetchstructure($imap, $i);
            $hasAttachment = false;
            if ($structure && isset($structure->parts)) {
                foreach ($structure->parts as $part) {
                    if ($part->ifdisposition) {
                        $hasAttachment = true;
                        break;
                    }
                }
            }

            $preview = '';
            if (imap_fetchbody($imap, $i, '1')) {
                $body = imap_fetchbody($imap, $i, '1');
                if ($structure && $structure->encoding == 3) {
                    $body = base64_decode($body);
                } elseif ($structure && $structure->encoding == 4) {
                    $body = quoted_printable_decode($body);
                }
                $preview = strip_tags(substr($body, 0, 200));
            }

            $messages[] = [
                'number' => $i,
                'uid' => $uid,
                'subject' => $subject,
                'from' => $from,
                'from_name' => $fromName,
                'date' => $date,
                'size' => $size,
                'flags' => $flags,
                'has_attachment' => $hasAttachment,
                'is_read' => $overview[0]->seen ? true : false,
                'is_starred' => $overview[0]->flagged ? true : false,
                'preview' => $preview
            ];

            if (count($messages) % 10 === 0) {
                ob_start();
                echo json_encode(['partial' => $messages, 'received' => count($messages)]);
                $chunk = ob_get_clean();
                echo "\n" . strlen($chunk) . "\n" . $chunk;
                flush();
            }
        }
    }

    imap_close($imap);

    echo "\n" . json_encode(['complete' => true, 'messages' => $messages]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}