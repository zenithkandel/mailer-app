<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password are required']);
    exit;
}

$config = loadConfig();

if (!$config) {
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error']);
    exit;
}

$storedUser = $config['admin_user'] ?? '';
$storedPass = $config['admin_pass'] ?? '';

if ($username === $storedUser && $password === $storedPass) {
    session_regenerate_id(true);
    $_SESSION['logged_in'] = true;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    echo json_encode(['success' => true, 'redirect' => 'dashboard.php']);
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid username or password']);
}