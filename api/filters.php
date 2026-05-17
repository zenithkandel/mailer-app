<?php
require_once __DIR__ . '/../config.php';
requireLogin();

header('Content-Type: application/json');

$filtersFile = __DIR__ . '/../data/filters.json';

if (!file_exists($filtersFile)) {
    file_put_contents($filtersFile, json_encode(['filters' => []]));
}

$filters = json_decode(file_get_contents($filtersFile), true);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode($filters);
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
            $newFilter = [
                'id' => uniqid('filter_'),
                'name' => $_POST['name'] ?? 'New Filter',
                'enabled' => true,
                'conditions' => json_decode($_POST['conditions'] ?? '[]', true),
                'conditions_match' => $_POST['conditions_match'] ?? 'all',
                'actions' => json_decode($_POST['actions'] ?? '[]', true),
                'order' => count($filters['filters']),
                'created_at' => time()
            ];
            $filters['filters'][] = $newFilter;
            file_put_contents($filtersFile, json_encode($filters));
            echo json_encode(['success' => true, 'filter' => $newFilter]);
            break;

        case 'update':
            $id = $_POST['id'] ?? '';
            foreach ($filters['filters'] as &$f) {
                if ($f['id'] === $id) {
                    $f['name'] = $_POST['name'] ?? $f['name'];
                    $f['enabled'] = isset($_POST['enabled']) ? ($_POST['enabled'] === 'true') : $f['enabled'];
                    if (isset($_POST['conditions'])) {
                        $f['conditions'] = json_decode($_POST['conditions'], true);
                    }
                    $f['conditions_match'] = $_POST['conditions_match'] ?? $f['conditions_match'];
                    if (isset($_POST['actions'])) {
                        $f['actions'] = json_decode($_POST['actions'], true);
                    }
                    break;
                }
            }
            file_put_contents($filtersFile, json_encode($filters));
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = $_POST['id'] ?? '';
            $filters['filters'] = array_filter($filters['filters'], function($f) use ($id) {
                return $f['id'] !== $id;
            });
            file_put_contents($filtersFile, json_encode($filters));
            echo json_encode(['success' => true]);
            break;

        case 'reorder':
            $order = json_decode($_POST['order'] ?? '[]', true);
            $ordered = [];
            foreach ($order as $id) {
                foreach ($filters['filters'] as $f) {
                    if ($f['id'] === $id) {
                        $ordered[] = $f;
                        break;
                    }
                }
            }
            foreach ($ordered as $i => $f) {
                $ordered[$i]['order'] = $i;
            }
            $filters['filters'] = $ordered;
            file_put_contents($filtersFile, json_encode($filters));
            echo json_encode(['success' => true]);
            break;

        case 'toggle':
            $id = $_POST['id'] ?? '';
            foreach ($filters['filters'] as &$f) {
                if ($f['id'] === $id) {
                    $f['enabled'] = !$f['enabled'];
                    break;
                }
            }
            file_put_contents($filtersFile, json_encode($filters));
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}