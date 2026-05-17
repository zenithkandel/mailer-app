<?php
session_start();

define('IMAP_HOST', 'mail.zenithkandel.com.np');
define('IMAP_PORT', 993);
define('IMAP_USER', 'admin@zenithkandel.com.np');
define('IMAP_PASS', '8038@Zenith');

define('SMTP_HOST', 'mail.zenithkandel.com.np');
define('SMTP_PORT', 465);
define('SMTP_USER', 'admin@zenithkandel.com.np');
define('SMTP_PASS', '8038@Zenith');
define('SMTP_FROM', 'admin@zenithkandel.com.np');

define('ADMIN_EMAIL', 'admin@zenithkandel.com.np');
define('ADMIN_PASS', '8038@Zenith');

define('CONFIG_FILE', __DIR__ . '/user_config.json');
define('SENT_FILE', __DIR__ . '/sent.json');

function isLoggedIn()
{
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function getUserConfig()
{
    if (!file_exists(CONFIG_FILE)) {
        return ['senderName' => '', 'signatures' => []];
    }
    $content = file_get_contents(CONFIG_FILE);
    $config = json_decode($content, true);
    return $config ?: ['senderName' => '', 'signatures' => []];
}

function saveUserConfig($config)
{
    return file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT)) !== false;
}

function buildSnippet($text, $limit = 140)
{
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    if (strlen($plain) > $limit) {
        $plain = substr($plain, 0, $limit) . '...';
    }
    return $plain;
}

function appendSentEmail($to, $subject, $body)
{
    $sent = [];
    if (file_exists(SENT_FILE)) {
        $existing = json_decode(file_get_contents(SENT_FILE), true);
        if (is_array($existing)) {
            $sent = $existing;
        }
    }

    $sent[] = [
        'id' => time() . '-' . rand(1000, 9999),
        'to' => $to,
        'from' => SMTP_FROM,
        'subject' => $subject,
        'body' => $body,
        'date' => time()
    ];

    return file_put_contents(SENT_FILE, json_encode($sent, JSON_PRETTY_PRINT)) !== false;
}

function getLocalSent($limit = 50, $query = '')
{
    if (!file_exists(SENT_FILE)) {
        return [];
    }
    $items = json_decode(file_get_contents(SENT_FILE), true);
    if (!is_array($items)) {
        return [];
    }

    $query = trim(strtolower($query));
    $filtered = [];
    foreach (array_reverse($items) as $item) {
        $haystack = strtolower(($item['to'] ?? '') . ' ' . ($item['subject'] ?? '') . ' ' . ($item['body'] ?? ''));
        if ($query && strpos($haystack, $query) === false) {
            continue;
        }
        $filtered[] = [
            'uid' => $item['id'],
            'from_name' => SMTP_USER,
            'from_email' => SMTP_FROM,
            'subject' => $item['subject'] ?? '(No Subject)',
            'date' => $item['date'] ?? time(),
            'read' => true,
            'snippet' => buildSnippet($item['body'] ?? '')
        ];
        if (count($filtered) >= $limit) {
            break;
        }
    }

    return $filtered;
}

function getLocalSentById($id)
{
    if (!file_exists(SENT_FILE)) {
        return null;
    }
    $items = json_decode(file_get_contents(SENT_FILE), true);
    if (!is_array($items)) {
        return null;
    }

    foreach ($items as $item) {
        if (($item['id'] ?? '') === $id) {
            return $item;
        }
    }

    return null;
}

function renderLayoutStart($title, $active)
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $user = htmlspecialchars($_SESSION['user'] ?? '', ENT_QUOTES, 'UTF-8');
    $inboxClass = $active === 'inbox' ? 'nav-item active' : 'nav-item';
    $sentClass = $active === 'sent' ? 'nav-item active' : 'nav-item';
    $composeClass = $active === 'compose' ? 'nav-item active' : 'nav-item';

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $safeTitle . ' - Mail</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="page">
        <aside class="sidebar glass">
            <div class="brand">
                <div class="brand-mark">Mail</div>
                <div class="brand-sub">Webmail</div>
            </div>
            <a class="compose-cta" href="compose.php">New message</a>
            <nav class="nav">
                <a class="' . $inboxClass . '" href="inbox.php">Inbox</a>
                <a class="' . $sentClass . '" href="inbox.php?folder=sent">Sent</a>
                <a class="' . $composeClass . '" href="compose.php">Compose</a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-pill">' . $user . '</div>
                <a class="nav-item danger" href="logout.php">Logout</a>
            </div>
        </aside>
        <main class="content">
            <div class="content-head">
                <h1>' . $safeTitle . '</h1>
            </div>';
}

function renderLayoutEnd()
{
    echo '    </main>
    </div>
</body>
</html>';
}