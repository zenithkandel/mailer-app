<?php
require_once '../config.php';
requireAuth();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if (!file_exists(SIGNATURES_FILE)) {
    file_put_contents(SIGNATURES_FILE, json_encode([]));
}

$signatures = json_decode(file_get_contents(SIGNATURES_FILE), true) ?: [];

switch ($action) {
    case 'list':
        echo json_encode(['signatures' => $signatures]);
        break;

    case 'save':
        $data = json_decode(file_get_contents('php://input'), true);
        $name = trim($data['name'] ?? '');
        $body = $data['body'] ?? '';
        $isDefault = !empty($data['isDefault']);

        if (empty($name)) {
            echo json_encode(['error' => 'Signature name required']);
            break;
        }

        foreach ($signatures as &$sig) {
            $sig['isDefault'] = false;
        }

        $signatures[] = [
            'id' => bin2hex(random_bytes(8)),
            'name' => $name,
            'body' => $body,
            'isDefault' => $isDefault
        ];

        file_put_contents(SIGNATURES_FILE, json_encode($signatures, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        break;

    case 'update':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? '';
        $name = trim($data['name'] ?? '');
        $body = $data['body'] ?? '';
        $isDefault = !empty($data['isDefault']);

        if (empty($id) || empty($name)) {
            echo json_encode(['error' => 'Invalid data']);
            break;
        }

        foreach ($signatures as &$sig) {
            if ($sig['id'] === $id) {
                $sig['name'] = $name;
                $sig['body'] = $body;
            }
            $sig['isDefault'] = ($sig['id'] === $id) ? $isDefault : false;
        }

        file_put_contents(SIGNATURES_FILE, json_encode($signatures, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        break;

    case 'delete':
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            echo json_encode(['error' => 'Signature ID required']);
            break;
        }

        $signatures = array_filter($signatures, function($sig) use ($id) {
            return $sig['id'] !== $id;
        });

        file_put_contents(SIGNATURES_FILE, json_encode(array_values($signatures), JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        break;

    case 'setDefault':
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            echo json_encode(['error' => 'Signature ID required']);
            break;
        }

        foreach ($signatures as &$sig) {
            $sig['isDefault'] = ($sig['id'] === $id);
        }

        file_put_contents(SIGNATURES_FILE, json_encode($signatures, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}