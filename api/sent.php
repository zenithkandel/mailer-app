<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$page = max(1, intval($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$perPage = 20;

$entries = readSentLog();

if (!empty($search)) {
    $q = strtolower($search);
    $entries = array_filter($entries, function($e) use ($q) {
        return strpos(strtolower($e['to'] ?? ''), $q) !== false
            || strpos(strtolower($e['subject'] ?? ''), $q) !== false;
    });
    $entries = array_values($entries);
}

$total = count($entries);
$offset = ($page - 1) * $perPage;
$slice = array_slice($entries, $offset, $perPage);
$hasMore = ($offset + count($slice)) < $total;

$emails = array_map(function($e) {
    $body = $e['body'] ?? '';
    $preview = mb_strlen($body) > 120 ? mb_substr($body, 0, 120) . '...' : $body;
    $preview = preg_replace('/<[^>]+>/', '', $preview);
    $preview = trim(preg_replace('/\s+/', ' ', $preview));

    return [
        'id' => $e['id'] ?? uniqid('sent_'),
        'to' => $e['to'] ?? '',
        'subject' => $e['subject'] ?? '(no subject)',
        'preview' => $preview,
        'body' => $body,
        'date' => $e['date'] ?? date('c'),
    ];
}, $slice);

echo json_encode([
    'emails' => $emails,
    'page' => $page,
    'has_more' => $hasMore,
    'total' => $total,
]);