<?php
require_once __DIR__ . '/../config.php';
requireLogin();

header('Content-Type: application/json');

$settingsFile = __DIR__ . '/../data/settings.json';

if (!file_exists($settingsFile)) {
    $defaultSettings = [
        'account' => [
            'display_name' => '',
            'reply_to' => ''
        ],
        'reading' => [
            'mark_read_after' => 0,
            'show_images' => 'ask',
            'preferred_view' => 'html'
        ],
        'compose' => [
            'default_signature_id' => null,
            'default_priority' => 3,
            'default_format' => 'html',
            'default_font' => 'Courier New'
        ],
        'notifications' => [
            'auto_refresh' => 60
        ],
        'display' => [
            'emails_per_page' => 50,
            'date_format' => 'relative',
            'density' => 'comfortable'
        ],
        'security' => [
            'show_external_images' => 'ask'
        ],
        'search_history' => []
    ];
    file_put_contents($settingsFile, json_encode($defaultSettings));
}

$settings = json_decode(file_get_contents($settingsFile), true);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode($settings);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $key = $_POST['key'] ?? '';
    $value = $_POST['value'] ?? null;

    if ($key && $value !== null) {
        $keys = explode('.', $key);
        $current = &$settings;
        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }
        $current = $value;

        file_put_contents($settingsFile, json_encode($settings));
        echo json_encode(['success' => true]);
        exit;
    }

    if (isset($_POST['settings'])) {
        $newSettings = json_decode($_POST['settings'], true);
        if ($newSettings) {
            $settings = array_merge($settings, $newSettings);
            file_put_contents($settingsFile, json_encode($settings));
            echo json_encode(['success' => true]);
            exit;
        }
    }

    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}