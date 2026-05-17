<?php
require_once __DIR__ . '/../config.php';
requireLogin();

header('Content-Type: application/json');

$signaturesFile = __DIR__ . '/../data/signatures.json';

if (!file_exists($signaturesFile)) {
    file_put_contents($signaturesFile, json_encode([
        'signatures' => [],
        'default_id' => null
    ]));
}

$signatures = json_decode(file_get_contents($signaturesFile), true);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode($signatures);
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
            $newSignature = [
                'id' => uniqid('sig_'),
                'name' => $_POST['name'] ?? 'New Signature',
                'html' => $_POST['html'] ?? '',
                'text' => $_POST['text'] ?? ''
            ];
            $signatures['signatures'][] = $newSignature;
            if (!isset($signatures['default_id'])) {
                $signatures['default_id'] = $newSignature['id'];
            }
            file_put_contents($signaturesFile, json_encode($signatures));
            echo json_encode(['success' => true, 'signature' => $newSignature]);
            break;

        case 'update':
            $id = $_POST['id'] ?? '';
            foreach ($signatures['signatures'] as &$s) {
                if ($s['id'] === $id) {
                    $s['name'] = $_POST['name'] ?? $s['name'];
                    $s['html'] = $_POST['html'] ?? $s['html'];
                    $s['text'] = $_POST['text'] ?? $s['text'];
                    break;
                }
            }
            file_put_contents($signaturesFile, json_encode($signatures));
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = $_POST['id'] ?? '';
            $signatures['signatures'] = array_filter($signatures['signatures'], function($s) use ($id) {
                return $s['id'] !== $id;
            });
            if ($signatures['default_id'] === $id) {
                $signatures['default_id'] = count($signatures['signatures']) > 0 ? $signatures['signatures'][0]['id'] : null;
            }
            file_put_contents($signaturesFile, json_encode($signatures));
            echo json_encode(['success' => true]);
            break;

        case 'set_default':
            $id = $_POST['id'] ?? '';
            $signatures['default_id'] = $id;
            file_put_contents($signaturesFile, json_encode($signatures));
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}