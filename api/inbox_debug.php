<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'C:\Users\zenith\AppData\Local\Temp\mailer_inbox_error.txt');

$fp = fopen('C:\Users\zenith\AppData\Local\Temp\mailer_inbox_debug.txt', 'w');
fwrite($fp, "Request started at " . date('Y-m-d H:i:s') . "\n");
fflush($fp);

try {
    fwrite($fp, "Loading config.php\n");
    flush();
    require_once 'api/config.php';
    fwrite($fp, "config.php loaded\n");
    flush();

    fwrite($fp, "Loading csrf.php\n");
    flush();
    require_once 'api/csrf.php';
    fwrite($fp, "csrf.php loaded\n");
    flush();

    fwrite($fp, "Loading helpers.php\n");
    flush();
    require_once 'api/helpers.php';
    fwrite($fp, "helpers.php loaded\n");
    flush();

    fwrite($fp, "IMAP function exists: " . (function_exists('imap_open') ? 'YES' : 'NO') . "\n");
    flush();

    fwrite($fp, "Session: " . print_r($_SESSION, true) . "\n");
    flush();

    $loggedIn = isLoggedIn();
    fwrite($fp, "isLoggedIn: " . ($loggedIn ? 'true' : 'false') . "\n");
    flush();

    if (!$loggedIn) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        fwrite($fp, "Sent 401 Unauthorized\n");
        fclose($fp);
        exit;
    }

    fwrite($fp, "Proceeding to IMAP check\n");
    flush();

    if (!function_exists('imap_open')) {
        http_response_code(503);
        echo json_encode(['error' => 'IMAP extension not available on this server. Please contact your hosting provider.']);
        fwrite($fp, "Sent 503 - IMAP not available\n");
        fclose($fp);
        exit;
    }

    fwrite($fp, "IMAP available - proceeding\n");
    flush();

    $config = loadConfig();
    fwrite($fp, "Config loaded, imap host: " . ($config['imap']['host'] ?? 'none') . "\n");
    flush();

    $imapConfig = $config['imap'] ?? [];
    $security = strtolower($imapConfig['security'] ?? 'ssl');
    $port = $imapConfig['port'] ?? 993;
    if ($security === 'ssl') {
        $mailbox = '{' . $imapConfig['host'] . ':' . $port . '/imap/ssl}INBOX';
    } elseif ($security === 'tls') {
        $mailbox = '{' . $imapConfig['host'] . ':' . $port . '/imap/tls}INBOX';
    } else {
        $mailbox = '{' . $imapConfig['host'] . ':' . $port . '}INBOX';
    }

    fwrite($fp, "Mailbox: $mailbox\n");
    fwrite($fp, "User: " . ($imapConfig['user'] ?? 'none') . "\n");
    flush();

    $mbox = @imap_open($mailbox, $imapConfig['user'] ?? '', $imapConfig['pass'] ?? '');
    if (!$mbox) {
        fwrite($fp, "IMAP connection failed: " . imap_last_error() . "\n");
        http_response_code(500);
        echo json_encode(['error' => 'Failed to connect to IMAP server: ' . imap_last_error()]);
        fclose($fp);
        exit;
    }

    fwrite($fp, "IMAP connected successfully!\n");
    flush();

    $emails = imap_sort($mbox, SORTDATE, 1, SE_FREE, null, 'UTF-8');
    if ($emails === false) $emails = [];

    fwrite($fp, "Total emails: " . count($emails) . "\n");
    flush();

    imap_close($mbox);

    echo json_encode([
        'emails' => [],
        'page' => 1,
        'has_more' => false,
        'total' => count($emails),
        'unread_count' => 0,
        'debug' => 'IMAP connected and working'
    ]);
    fwrite($fp, "Success!\n");

} catch (Throwable $e) {
    fwrite($fp, "EXCEPTION: " . $e->getMessage() . "\n" . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString() . "\n");
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

fclose($fp);
?>