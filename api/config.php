<?php
error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('UTC');
session_start();

$CONFIG_FILE = __DIR__ . '/../data/config.json';

function loadConfig() {
    global $CONFIG_FILE;
    if (!file_exists($CONFIG_FILE)) return null;
    $json = file_get_contents($CONFIG_FILE);
    $config = json_decode($json, true);
    return $config ?: null;
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

function requireMethod($methods) {
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods)) {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sanitize($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function csrfGenerate() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfValidate($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}