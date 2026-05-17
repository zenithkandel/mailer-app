<?php
require_once '../config.php';
requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Upload error: ' . $file['error']]);
    exit;
}

$allowedExtensions = [
    'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp',
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt',
    'zip', 'rar', '7z', 'tar', 'gz',
    'mp3', 'mp4', 'avi', 'mov', 'wav', 'flac',
    'html', 'htm', 'css', 'js', 'json', 'xml'
];

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExtensions)) {
    echo json_encode(['error' => 'File type not allowed']);
    exit;
}

if ($file['size'] > 10485760) {
    echo json_encode(['error' => 'File size exceeds 10MB limit']);
    exit;
}

$sessionId = session_id();
$random = bin2hex(random_bytes(8));
$filename = $sessionId . '_' . $random . '_' . basename($file['name']);
$path = UPLOAD_DIR . $filename;

if (!move_uploaded_file($file['tmp_name'], $path)) {
    echo json_encode(['error' => 'Failed to save file']);
    exit;
}

$now = time();
$files = glob(UPLOAD_DIR . $sessionId . '_*');
foreach ($files as $f) {
    if (filemtime($f) < $now - 3600) {
        @unlink($f);
    }
}

$mimeType = $file['type'];
if (empty($mimeType) || $mimeType === 'application/octet-stream') {
    $mimeTypes = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'pdf' => 'application/pdf',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt' => 'text/plain', 'zip' => 'application/zip'
    ];
    $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';
}

echo json_encode([
    'success' => true,
    'filename' => $file['name'],
    'path' => $path,
    'size' => $file['size'],
    'mime' => $mimeType
]);