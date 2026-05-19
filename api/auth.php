<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data) {
        $data = $_POST;
    }
    
    if (!csrfValidate($data['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid token', 'debug' => 'CSRF validation failed']);
        exit;
    }
    
    $user = trim($data['username'] ?? '');
    $pass = $data['password'] ?? '';
    
    $config = loadConfig();
    if (!$config) {
        http_response_code(500);
        echo json_encode(['error' => 'Configuration error']);
        exit;
    }
    
    $adminUser = $config['admin_user'] ?? '';
    $adminPass = $config['admin_pass'] ?? '';
    
    if (($user === $adminUser || $user === $adminUser . '@zenithkandel.com.np') && $pass === $adminPass) {
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['user'] = $adminUser;
        
        echo json_encode([
            'success' => true,
            'redirect' => 'dashboard.php',
            'user' => $adminUser
        ]);
        exit;
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid username or password']);
        exit;
    }
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true, 'redirect' => 'index.php']);
    exit;
}

if ($action === 'check') {
    if (isLoggedIn()) {
        echo json_encode(['logged_in' => true, 'user' => $_SESSION['user'] ?? 'admin']);
    } else {
        echo json_encode(['logged_in' => false]);
    }
    exit;
}

if ($action === 'csrf') {
    echo json_encode(['token' => csrfGenerate()]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
exit;