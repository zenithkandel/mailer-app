<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'get') {
    requireLogin();
    
    $config = loadConfig();
    if (!$config) {
        echo json_encode(['error' => 'Configuration error']);
        exit;
    }
    
    $safeConfig = [
        'app_name' => $config['app_name'] ?? '',
        'admin_user' => $config['admin_user'] ?? '',
        'smtp' => [
            'host' => $config['smtp']['host'] ?? '',
            'port' => $config['smtp']['port'] ?? 465,
            'security' => $config['smtp']['security'] ?? 'ssl',
            'user' => $config['smtp']['user'] ?? '',
            'from_email' => $config['smtp']['from_email'] ?? '',
            'from_name' => $config['smtp']['from_name'] ?? ''
        ],
        'imap' => [
            'host' => $config['imap']['host'] ?? '',
            'port' => $config['imap']['port'] ?? 993,
            'security' => $config['imap']['security'] ?? 'ssl',
            'user' => $config['imap']['user'] ?? '',
            'folders' => $config['imap']['folders'] ?? []
        ],
        'settings' => $config['settings'] ?? []
    ];
    
    echo json_encode($safeConfig);
    exit;
}

if ($action === 'save') {
    requireLogin();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
    
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data) $data = $_POST;
    
    if (!csrfValidate($data['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    
    $configFile = __DIR__ . '/../data/config.json';
    
    $existing = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
    
    $newConfig = [
        'app_name' => $data['app_name'] ?? $existing['app_name'] ?? 'Zenith Mail',
        'admin_user' => $data['admin_user'] ?? $existing['admin_user'] ?? '',
        'admin_pass' => $data['admin_pass'] ?? $existing['admin_pass'] ?? '',
        'smtp' => [
            'host' => $data['smtp_host'] ?? $existing['smtp']['host'] ?? '',
            'port' => (int)($data['smtp_port'] ?? $existing['smtp']['port'] ?? 465),
            'security' => $data['smtp_security'] ?? $existing['smtp']['security'] ?? 'ssl',
            'user' => $data['smtp_user'] ?? $existing['smtp']['user'] ?? '',
            'pass' => $data['smtp_pass'] ?? $existing['smtp']['pass'] ?? '',
            'from_email' => $data['smtp_from_email'] ?? $existing['smtp']['from_email'] ?? '',
            'from_name' => $data['smtp_from_name'] ?? $existing['smtp']['from_name'] ?? ''
        ],
        'imap' => [
            'host' => $data['imap_host'] ?? $existing['imap']['host'] ?? '',
            'port' => (int)($data['imap_port'] ?? $existing['imap']['port'] ?? 993),
            'security' => $data['imap_security'] ?? $existing['imap']['security'] ?? 'ssl',
            'user' => $data['imap_user'] ?? $existing['imap']['user'] ?? '',
            'pass' => $data['imap_pass'] ?? $existing['imap']['pass'] ?? '',
            'folders' => $existing['imap']['folders'] ?? ['inbox' => 'INBOX', 'sent' => 'Sent', 'drafts' => 'Drafts', 'trash' => 'Trash']
        ],
        'settings' => $existing['settings'] ?? ['per_page' => 25, 'preview_length' => 100, 'theme' => 'light']
    ];
    
    $json = json_encode($newConfig, JSON_PRETTY_PRINT);
    
    if (file_put_contents($configFile, $json) === false) {
        echo json_encode(['error' => 'Failed to save configuration']);
        exit;
    }
    
    echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
    exit;
}

if ($action === 'test_imap') {
    requireLogin();
    
    $host = $_GET['host'] ?? '';
    $port = (int)($_GET['port'] ?? 993);
    $security = $_GET['security'] ?? 'ssl';
    $user = $_GET['user'] ?? '';
    $pass = $_GET['pass'] ?? '';
    
    if (empty($host) || empty($user) || empty($pass)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    $prefix = ($security === 'ssl') ? '{' . $host . ':' . $port . '/imap/ssl}' : ($security === 'tls' ? '{' . $host . ':' . $port . '/imap/tls}' : '{' . $host . ':' . $port . '}');
    
    $mbox = @imap_open($prefix . 'INBOX', $user, $pass);
    
    if ($mbox) {
        imap_close($mbox);
        echo json_encode(['success' => true, 'message' => 'Connection successful']);
    } else {
        echo json_encode(['success' => false, 'error' => imap_last_error() ?: 'Connection failed']);
    }
    exit;
}

if ($action === 'test_smtp') {
    requireLogin();
    
    $host = $_GET['host'] ?? '';
    $port = (int)($_GET['port'] ?? 465);
    $security = $_GET['security'] ?? 'ssl';
    $user = $_GET['user'] ?? '';
    $pass = $_GET['pass'] ?? '';
    
    if (empty($host) || empty($user) || empty($pass)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    $protocol = ($security === 'ssl') ? 'ssl' : 'tls';
    $address = $protocol . '://' . $host . ':' . $port;
    
    $context = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
    ]);
    
    $socket = @stream_socket_client($address, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
    
    if ($socket) {
        $response = fgets($socket, 512);
        fclose($socket);
        echo json_encode(['success' => true, 'message' => 'Connection successful']);
    } else {
        echo json_encode(['success' => false, 'error' => $errstr ?: 'Connection failed']);
    }
    exit;
}

echo json_encode(['error' => 'Invalid action']);