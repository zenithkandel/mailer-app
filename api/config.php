<?php
error_reporting(0);
ini_set('display_errors', 0);

date_default_timezone_set('UTC');

session_start();

$CONFIG_FILE = __DIR__ . '/../data/config.json';
$LOGS_DIR = __DIR__ . '/../logs';
$SENT_LOG = $LOGS_DIR . '/sent.json';

function loadConfig() {
    global $CONFIG_FILE;
    if (!file_exists($CONFIG_FILE)) {
        return null;
    }
    $json = file_get_contents($CONFIG_FILE);
    $config = json_decode($json, true);
    return $config;
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function requireAuth() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function createLogsDir() {
    global $LOGS_DIR;
    if (!is_dir($LOGS_DIR)) {
        mkdir($LOGS_DIR, 0755, true);
    }
}

function readSentLog() {
    global $SENT_LOG;
    createLogsDir();
    if (!file_exists($SENT_LOG)) {
        return [];
    }
    $json = file_get_contents($SENT_LOG);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function writeSentLog($entries) {
    global $SENT_LOG;
    createLogsDir();
    file_put_contents($SENT_LOG, json_encode($entries, JSON_PRETTY_PRINT));
}

function addSentEntry($entry) {
    $entries = readSentLog();
    array_unshift($entries, $entry);
    if (count($entries) > 500) {
        $entries = array_slice($entries, 0, 500);
    }
    writeSentLog($entries);
}