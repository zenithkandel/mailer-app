<?php
require_once __DIR__ . '/config.php';

set_time_limit(300);

$filtersFile = __DIR__ . '/data/filters.json';
$filters = json_decode(file_get_contents($filtersFile), true);

$folder = $argv[1] ?? 'INBOX';
$limit = $argv[2] ?? 50;

try {
    $imap = getImapConnection($folder);
    $total = imap_num_msg($imap);

    echo "Processing $total messages in $folder...\n";

    $processed = 0;
    $matched = 0;

    for ($i = 1; $i <= min($total, $limit); $i++) {
        $header = imap_headerinfo($imap, $i);
        $structure = imap_fetchstructure($imap, $i);

        $from = '';
        if (isset($header->from)) {
            foreach ($header->from as $addr) {
                $from = (isset($addr->mailbox) ? $addr->mailbox : '') . (isset($addr->host) ? '@' . $addr->host : '');
                break;
            }
        }

        $subject = isset($header->subject) ? imap_utf8($header->subject) : '';
        $body = '';

        if ($structure) {
            $bodyObj = imap_fetchbody($imap, $i, '1');
            if ($structure->encoding == 3) {
                $body = base64_decode($bodyObj);
            } elseif ($structure->encoding == 4) {
                $body = quoted_printable_decode($bodyObj);
            } else {
                $body = $bodyObj;
            }
        }

        foreach ($filters['filters'] as $filter) {
            if (!$filter['enabled']) continue;

            $conditions = $filter['conditions'];
            $match = false;

            foreach ($conditions as $condition) {
                $field = $condition['field'];
                $operator = $condition['operator'];
                $value = $condition['value'];

                $matchField = '';
                switch ($field) {
                    case 'from':
                        $matchField = $from;
                        break;
                    case 'subject':
                        $matchField = $subject;
                        break;
                    case 'body':
                        $matchField = $body;
                        break;
                }

                $matchFieldLower = strtolower($matchField);
                $valueLower = strtolower($value);

                switch ($operator) {
                    case 'contains':
                        $conditionMatch = strpos($matchFieldLower, $valueLower) !== false;
                        break;
                    case 'equals':
                        $conditionMatch = $matchFieldLower === $valueLower;
                        break;
                    case 'starts':
                        $conditionMatch = strpos($matchFieldLower, $valueLower) === 0;
                        break;
                    case 'ends':
                        $conditionMatch = substr($matchFieldLower, -strlen($valueLower)) === $valueLower;
                        break;
                    default:
                        $conditionMatch = false;
                }

                if ($filter['conditions_match'] === 'all' && !$conditionMatch) {
                    $match = false;
                    break;
                } elseif ($filter['conditions_match'] === 'any' && $conditionMatch) {
                    $match = true;
                }
            }

            if ($filter['conditions_match'] === 'all' && $match) {
                $match = true;
            }

            if ($match) {
                $matched++;
                foreach ($filter['actions'] as $action) {
                    $uid = imap_uid($imap, $i);

                    switch ($action['type']) {
                        case 'move':
                            imap_mail_move($imap, $uid, $action['folder'], FT_UID);
                            break;
                        case 'mark_read':
                            imap_setflag_full($imap, $uid, '\\Seen', FT_UID);
                            break;
                        case 'star':
                            imap_setflag_full($imap, $uid, '\\Flagged', FT_UID);
                            break;
                        case 'delete':
                            imap_delete($imap, $uid, FT_UID);
                            break;
                    }
                }
            }
        }

        $processed++;
    }

    imap_expunge($imap);
    imap_close($imap);

    echo "Processed $processed messages. Matched: $matched\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}