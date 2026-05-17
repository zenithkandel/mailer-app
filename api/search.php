<?php
require_once __DIR__ . '/../config.php';
requireLogin();

header('Content-Type: application/json');
header('Transfer-Encoding: chunked');

$query = $_GET['query'] ?? '';
$folder = $_GET['folder'] ?? 'INBOX';
$scope = $_GET['scope'] ?? 'all';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$subject = $_GET['subject'] ?? '';
$body = $_GET['body'] ?? '';
$hasAttachment = isset($_GET['has_attachment']) ? $_GET['has_attachment'] : null;
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$starredOnly = isset($_GET['starred']) && $_GET['starred'] === '1';
$unreadOnly = isset($_GET['unread']) && $_GET['unread'] === '1';

if (!$query && !$from && !$to && !$subject && !$body && $hasAttachment === null && !$dateFrom && !$dateTo) {
    http_response_code(400);
    echo json_encode(['error' => 'At least one search parameter required']);
    exit;
}

try {
    $foldersToSearch = [];
    if ($scope === 'all') {
        $imap = getImapConnection('');
        $folders = imap_list($imap, '{' . MAIL_HOST . ':' . IMAP_PORT . '/imap/ssl/novalidate-cert}', '*');
        imap_close($imap);
        if ($folders) {
            foreach ($folders as $f) {
                $foldersToSearch[] = substr($f, strrpos($f, '}') + 1);
            }
        }
    } else {
        $foldersToSearch = [$folder];
    }

    $results = [];

    foreach ($foldersToSearch as $searchFolder) {
        try {
            $imap = getImapConnection($searchFolder);
            if (!$imap) continue;

            $searchCriteria = 'ALL';

            $searchParts = [];
            if ($query) {
                $searchParts[] = 'TEXT "' . imap_escape($query) . '"';
            }
            if ($from) {
                $searchParts[] = 'FROM "' . imap_escape($from) . '"';
            }
            if ($to) {
                $searchParts[] = 'TO "' . imap_escape($to) . '"';
            }
            if ($subject) {
                $searchParts[] = 'SUBJECT "' . imap_escape($subject) . '"';
            }
            if ($unreadOnly) {
                $searchParts[] = 'UNSEEN';
            }
            if ($starredOnly) {
                $searchParts[] = 'FLAGGED';
            }

            if (!empty($searchParts)) {
                $searchCriteria = implode(' ', $searchParts);
            }

            $matches = imap_search($imap, $searchCriteria, SE_UID);

            if ($matches) {
                $folderResults = [];
                foreach ($matches as $uid) {
                    $header = imap_headerinfo($imap, imap_msgno($imap, $uid));
                    $overview = imap_fetch_overview($imap, $uid, FT_UID);

                    $fromName = '';
                    $fromEmail = '';
                    if (isset($header->from)) {
                        foreach ($header->from as $addr) {
                            $fromName = isset($addr->personal) ? imap_utf8($addr->personal) : '';
                            $fromEmail = (isset($addr->mailbox) ? $addr->mailbox : '') . (isset($addr->host) ? '@' . $addr->host : '');
                            break;
                        }
                    }

                    $hasAtt = false;
                    $struct = imap_fetchstructure($imap, imap_msgno($imap, $uid));
                    if ($struct && isset($struct->parts)) {
                        foreach ($struct->parts as $part) {
                            if ($part->ifdisposition) {
                                $hasAtt = true;
                                break;
                            }
                        }
                    }

                    if ($hasAttachment !== null) {
                        if (($hasAttachment === '1' && !$hasAtt) || ($hasAttachment === '0' && $hasAtt)) {
                            continue;
                        }
                    }

                    $folderResults[] = [
                        'uid' => $uid,
                        'folder' => $searchFolder,
                        'subject' => isset($header->subject) ? imap_utf8($header->subject) : '(No Subject)',
                        'from' => $fromEmail,
                        'from_name' => $fromName,
                        'date' => isset($header->udate) ? $header->udate : 0,
                        'size' => isset($header->Size) ? $header->Size : 0,
                        'is_read' => $overview[0]->seen ?? false,
                        'is_starred' => $overview[0]->flagged ?? false,
                        'has_attachment' => $hasAtt
                    ];
                }

                $results = array_merge($results, $folderResults);
            }

            imap_close($imap);
        } catch (Exception $e) {
            continue;
        }
    }

    usort($results, function($a, $b) {
        return $b['date'] - $a['date'];
    });

    echo json_encode([
        'query' => $query,
        'results' => $results,
        'total' => count($results)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function imap_escape($str) {
    return str_replace(['"', '\\'], ['\\"', '\\\\'], $str);
}