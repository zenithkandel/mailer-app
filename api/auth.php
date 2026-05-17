<?php
require_once '../config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['error' => 'Email and password required']);
        exit;
    }

    $mbox = @imap_open(IMAP_PREFIX, $username, $password);
    if ($mbox) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['password'] = $password;
        imap_close($mbox);
        echo json_encode(['success' => true, 'username' => $username]);
    } else {
        echo json_encode(['error' => 'Invalid credentials']);
    }
    exit;
}

echo json_encode(['error' => 'Invalid request']);