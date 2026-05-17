<?php
require_once __DIR__ . '/../config.php';
requireLogin();

header('Content-Type: application/json');

$contactsFile = __DIR__ . '/../data/contacts.json';

if (!file_exists($contactsFile)) {
    file_put_contents($contactsFile, json_encode(['contacts' => [], 'groups' => []]));
}

$contacts = json_decode(file_get_contents($contactsFile), true);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    $search = $_GET['search'] ?? '';
    $groupId = $_GET['group'] ?? null;

    if ($action === 'search' && $search) {
        $results = array_filter($contacts['contacts'], function($c) use ($search) {
            $searchLower = strtolower($search);
            return stripos(strtolower($c['name']), $searchLower) !== false ||
                   stripos(strtolower($c['email']), $searchLower) !== false;
        });
        echo json_encode(['contacts' => array_values($results)]);
        exit;
    }

    if ($groupId) {
        $filtered = array_filter($contacts['contacts'], function($c) use ($groupId) {
            return in_array($groupId, $c['groups'] ?? []);
        });
        echo json_encode(['contacts' => array_values($filtered)]);
        exit;
    }

    echo json_encode($contacts);
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
        case 'create':
            $newContact = [
                'id' => uniqid('contact_'),
                'name' => $_POST['name'] ?? '',
                'email' => $_POST['email'] ?? '',
                'emails' => json_decode($_POST['emails'] ?? '[]', true),
                'phone' => $_POST['phone'] ?? '',
                'company' => $_POST['company'] ?? '',
                'notes' => $_POST['notes'] ?? '',
                'tags' => json_decode($_POST['tags'] ?? '[]', true),
                'groups' => json_decode($_POST['groups'] ?? '[]', true),
                'created_at' => time()
            ];
            $contacts['contacts'][] = $newContact;
            file_put_contents($contactsFile, json_encode($contacts));
            echo json_encode(['success' => true, 'contact' => $newContact]);
            break;

        case 'update':
            $id = $_POST['id'] ?? '';
            foreach ($contacts['contacts'] as &$c) {
                if ($c['id'] === $id) {
                    $c['name'] = $_POST['name'] ?? $c['name'];
                    $c['email'] = $_POST['email'] ?? $c['email'];
                    $c['emails'] = json_decode($_POST['emails'] ?? '[]', true) ?: $c['emails'];
                    $c['phone'] = $_POST['phone'] ?? $c['phone'];
                    $c['company'] = $_POST['company'] ?? $c['company'];
                    $c['notes'] = $_POST['notes'] ?? $c['notes'];
                    $c['tags'] = json_decode($_POST['tags'] ?? '[]', true) ?: $c['tags'];
                    $c['groups'] = json_decode($_POST['groups'] ?? '[]', true) ?: $c['groups'];
                    break;
                }
            }
            file_put_contents($contactsFile, json_encode($contacts));
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = $_POST['id'] ?? '';
            $contacts['contacts'] = array_filter($contacts['contacts'], function($c) use ($id) {
                return $c['id'] !== $id;
            });
            file_put_contents($contactsFile, json_encode($contacts));
            echo json_encode(['success' => true]);
            break;

        case 'create_group':
            $newGroup = [
                'id' => uniqid('group_'),
                'name' => $_POST['name'] ?? 'New Group'
            ];
            $contacts['groups'][] = $newGroup;
            file_put_contents($contactsFile, json_encode($contacts));
            echo json_encode(['success' => true, 'group' => $newGroup]);
            break;

        case 'import_vcf':
            if (isset($_FILES['vcf_file'])) {
                $content = file_get_contents($_FILES['vcf_file']['tmp_name']);
                preg_match_all('/BEGIN:VCARD.*?END:VCARD/s', $content, $cards);

                $imported = 0;
                foreach ($cards[0] as $card) {
                    $name = '';
                    $email = '';
                    $phone = '';
                    $company = '';

                    if (preg_match('/FN:(.+)/', $card, $m)) $name = $m[1];
                    if (preg_match('/EMAIL[^:]*:(.+)/', $card, $m)) $email = $m[1];
                    if (preg_match('/TEL[^:]*:(.+)/', $card, $m)) $phone = $m[1];
                    if (preg_match('/ORG:(.+)/', $card, $m)) $company = $m[1];

                    if ($email) {
                        $contacts['contacts'][] = [
                            'id' => uniqid('contact_'),
                            'name' => $name,
                            'email' => $email,
                            'emails' => [['email' => $email, 'type' => 'home']],
                            'phone' => $phone,
                            'company' => $company,
                            'notes' => '',
                            'tags' => [],
                            'groups' => [],
                            'created_at' => time()
                        ];
                        $imported++;
                    }
                }
                file_put_contents($contactsFile, json_encode($contacts));
                echo json_encode(['success' => true, 'imported' => $imported]);
            }
            break;

        case 'import_csv':
            if (isset($_FILES['csv_file'])) {
                $content = file_get_contents($_FILES['csv_file']['tmp_name']);
                $lines = explode("\n", $content);
                $imported = 0;

                foreach ($lines as $line) {
                    $fields = str_getcsv($line);
                    if (isset($fields[0]) && isset($fields[1])) {
                        $contacts['contacts'][] = [
                            'id' => uniqid('contact_'),
                            'name' => $fields[0],
                            'email' => $fields[1],
                            'emails' => [['email' => $fields[1], 'type' => 'home']],
                            'phone' => $fields[2] ?? '',
                            'company' => $fields[3] ?? '',
                            'notes' => '',
                            'tags' => [],
                            'groups' => [],
                            'created_at' => time()
                        ];
                        $imported++;
                    }
                }
                file_put_contents($contactsFile, json_encode($contacts));
                echo json_encode(['success' => true, 'imported' => $imported]);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}