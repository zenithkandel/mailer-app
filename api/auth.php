<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'login') {
    requireMethod(['POST']);
    
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data) $data = $_POST;
    
    if (!csrfValidate($data['csrf_token'] ?? '')) {
        outputJson(['error' => 'Invalid token'], 403);
    }
    
    $user = trim($data['username'] ?? '');
    $pass = $data['password'] ?? '';
    
    $config = loadConfig();
    if (!$config) {
        outputJson(['error' => 'Configuration error'], 500);
    }
    
    $adminUser = $config['admin_user'] ?? '';
    $adminPass = $config['admin_pass'] ?? '';
    
    if ($user === $adminUser && $pass === $adminPass) {
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['user'] = $user;
        
        outputJson([
            'success' => true,
            'redirect' => 'dashboard.php',
            'user' => $user
        ]);
    } else {
        outputJson(['error' => 'Invalid username or password'], 401);
    }
}

if ($action === 'logout') {
    session_destroy();
    outputJson(['success' => true, 'redirect' => 'index.php']);
}

if ($action === 'check') {
    if (isLoggedIn()) {
        outputJson(['logged_in' => true, 'user' => $_SESSION['user'] ?? 'admin']);
    } else {
        outputJson(['logged_in' => false]);
    }
}

if ($action === 'csrf') {
    outputJson(['token' => csrfGenerate()]);
}

outputJson(['error' => 'Invalid action'], 400);