<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

$CONFIG_FILE = __DIR__ . '/data/config.json';

function loadConfig() {
    global $CONFIG_FILE;
    if (!file_exists($CONFIG_FILE)) {
        echo json_encode(['error' => 'Config file not found']);
        return null;
    }
    $json = file_get_contents($CONFIG_FILE);
    $config = json_decode($json, true);
    return $config ?: null;
}

$config = loadConfig();
if ($config) {
    echo json_encode(['success' => true, 'config' => 'loaded']);
} else {
    echo json_encode(['success' => false, 'error' => 'Config is null']);
}