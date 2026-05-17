<?php
error_reporting(0);
ini_set('display_errors', 0);

define('IMAP_HOST', 'mail.zenithkandel.com.np');
define('IMAP_PORT', 993);
define('IMAP_FLAGS', '/imap/ssl');
define('IMAP_PREFIX', '{' . IMAP_HOST . ':' . IMAP_PORT . IMAP_FLAGS . '}');

define('SMTP_HOST', 'ssl://' . IMAP_HOST);
define('SMTP_PORT', 465);

define('MESSAGES_PER_PAGE', 25);
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('SIGNATURES_FILE', __DIR__ . '/data/signatures.json');

session_start();

function requireAuth(): void {
    if (empty($_SESSION['authenticated'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

function getImapConnection() {
    if (empty($_SESSION['username']) || empty($_SESSION['password'])) {
        return null;
    }
    $mbox = @imap_open(IMAP_PREFIX, $_SESSION['username'], $_SESSION['password']);
    return $mbox ?: null;
}

function decodeHeader(string $str): string {
    if (empty($str)) return '';
    return mb_decode_mimeheader(imap_utf8($str));
}

function getEmailFromHeader($header): string {
    if (!is_object($header)) return '';
    if (!empty($header->mailbox)) {
        return $header->mailbox . '@' . $header->host;
    }
    return '';
}

function getDisplayNameFromHeader($header): string {
    if (!is_object($header)) return '';
    return !empty($header->personal) ? decodeHeader($header->personal) : getEmailFromHeader($header);
}

function formatDate(string $date): string {
    $timestamp = strtotime($date);
    if (!$timestamp) return $date;
    $now = time();
    $diff = $now - $timestamp;

    if ($diff < 86400) {
        return date('H:i', $timestamp);
    } elseif ($diff < 604800) {
        return date('D', $timestamp);
    } else {
        return date('M j', $timestamp);
    }
}

function sanitizeHtml(string $html): string {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//*[@onclick]|//*[@onload]|//*[@onerror]|//*[@onmouseover]|//*[@onfocus]|//*[@onblur]');
    foreach ($nodes as $node) {
        foreach ($node->attributes as $attr) {
            if (strpos($attr->name, 'on') === 0) {
                $node->removeAttribute($attr->name);
            }
        }
    }

    $scripts = $xpath->query('//script');
    foreach ($scripts as $script) {
        $script->parentNode->removeChild($script);
    }

    $iframes = $xpath->query('//iframe');
    foreach ($iframes as $iframe) {
        $iframe->parentNode->removeChild($iframe);
    }

    $links = $xpath->query('//a[@href]');
    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        if (stripos($href, 'javascript:') === 0) {
            $link->setAttribute('href', '#');
        }
    }

    return $dom->saveHTML();
}

function escapeHtml(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function formatBytes(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

function generateMessageId(): string {
    return '<' . bin2hex(random_bytes(16)) . '@zenithmail.local>';
}