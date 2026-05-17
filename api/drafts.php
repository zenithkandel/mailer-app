<?php
require_once __DIR__ . '/../config.php';
requireLogin();

header('Content-Type: application/json');

$draftsFile = __DIR__ . '/../data/drafts.json';

if (!file_exists($draftsFile)) {
    file_put_contents($draftsFile, json_encode(['drafts' => []]));
}

$drafts = json_decode(file_get_contents($draftsFile), true);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    $id = $_GET['id'] ?? null;

    if ($action === 'get' && $id) {
        foreach ($drafts['drafts'] as $draft) {
            if ($draft['id'] === $id) {
                echo json_encode(['draft' => $draft]);
                exit;
            }
        }
        http_response_code(404);
        echo json_encode(['error' => 'Draft not found']);
        exit;
    }

    echo json_encode($drafts);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'save':
            $id = $_POST['id'] ?? uniqid('draft_');
            $draftData = [
                'id' => $id,
                'to' => $_POST['to'] ?? '',
                'cc' => $_POST['cc'] ?? '',
                'bcc' => $_POST['bcc'] ?? '',
                'subject' => $_POST['subject'] ?? '',
                'body_html' => $_POST['body_html'] ?? '',
                'body_text' => $_POST['body_text'] ?? '',
                'priority' => isset($_POST['priority']) ? (int)$_POST['priority'] : 3,
                'attachments' => json_decode($_POST['attachments'] ?? '[]', true),
                'scheduled_at' => !empty($_POST['scheduled_at']) ? strtotime($_POST['scheduled_at']) : null,
                'updated_at' => time()
            ];

            foreach ($drafts['drafts'] as &$d) {
                if ($d['id'] === $id) {
                    $d = $draftData;
                    $found = true;
                    break;
                }
            }

            if (!isset($found)) {
                $draftData['created_at'] = time();
                $drafts['drafts'][] = $draftData;
            }

            file_put_contents($draftsFile, json_encode($drafts));
            echo json_encode(['success' => true, 'draft' => $draftData]);
            break;

        case 'delete':
            $id = $_POST['id'] ?? '';
            $drafts['drafts'] = array_filter($drafts['drafts'], function($d) use ($id) {
                return $d['id'] !== $id;
            });
            file_put_contents($draftsFile, json_encode($drafts));
            echo json_encode(['success' => true]);
            break;

        case 'get_recipients':
            $type = $_POST['type'] ?? 'to';
            $imap = getImapConnection('INBOX');
            $recent = imap_search($imap, 'ALL', SE_UID, 5);
            $recipients = [];

            if ($recent) {
                foreach ($recent as $uid) {
                    $header = imap_headerinfo($imap, imap_msgno($imap, $uid));
                    $field = $type === 'to' ? 'to' : 'cc';
                    if (isset($header->$field)) {
                        foreach ($header->$field as $addr) {
                            $email = (isset($addr->mailbox) ? $addr->mailbox : '') . (isset($addr->host) ? '@' . $addr->host : '');
                            if ($email && $email !== MAIL_USER) {
                                $recipients[$email] = isset($addr->personal) ? imap_utf8($addr->personal) : $email;
                            }
                        }
                    }
                }
            }
            imap_close($imap);

            echo json_encode(['recipients' => $recipients]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}