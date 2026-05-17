<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/imap.php';
require_once __DIR__ . '/smtp.php';

header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? '';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Session expired']);
    exit;
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'send') {
    $to = $_POST['to'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $body = $_POST['body'] ?? '';
    
    if (!$to || !$subject || !$body) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    $result = sendEmail($to, $subject, $body);
    echo json_encode($result);
    exit;
}

if ($action === 'delete') {
    $uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
    if (!$uid) {
        echo json_encode(['success' => false, 'error' => 'Invalid email']);
        exit;
    }
    
    $success = deleteEmail($uid);
    echo json_encode(['success' => $success]);
    exit;
}

if ($action === 'saveSettings') {
    $senderName = $_POST['senderName'] ?? '';
    
    $config = getUserConfig();
    $config['senderName'] = $senderName;
    
    $success = saveUserConfig($config);
    echo json_encode(['success' => $success, 'error' => $success ? '' : 'Failed to save']);
    exit;
}

if ($action === 'addSignature') {
    $name = $_POST['name'] ?? '';
    $shortcut = $_POST['shortcut'] ?? '';
    $content = $_POST['content'] ?? '';
    
    if (!$name || !$content) {
        echo json_encode(['success' => false, 'error' => 'Name and content required']);
        exit;
    }
    
    $config = getUserConfig();
    $signatures = $config['signatures'] ?? [];
    $signatures[] = ['name' => $name, 'shortcut' => $shortcut, 'content' => $content];
    $config['signatures'] = $signatures;
    
    $success = saveUserConfig($config);
    echo json_encode(['success' => $success, 'error' => $success ? '' : 'Failed to save']);
    exit;
}

if ($action === 'deleteSignature') {
    $index = isset($_GET['index']) ? intval($_GET['index']) : -1;
    
    $config = getUserConfig();
    $signatures = $config['signatures'] ?? [];
    
    if ($index >= 0 && $index < count($signatures)) {
        array_splice($signatures, $index, 1);
        $config['signatures'] = $signatures;
        $success = saveUserConfig($config);
        echo json_encode(['success' => $success, 'error' => $success ? '' : 'Failed to save']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid index']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);