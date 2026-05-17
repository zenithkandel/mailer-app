<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? '';

    if ($password === MAIL_PASS) {
        $_SESSION['authenticated'] = true;
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        echo json_encode([
            'success' => true,
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid password']);
    }
    exit;
}

if (isLoggedIn()) {
    echo json_encode([
        'authenticated' => true,
        'csrf_token' => $_SESSION['csrf_token']
    ]);
} else {
    echo json_encode(['authenticated' => false]);
}