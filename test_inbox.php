<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$fp = fopen('C:\Users\zenith\AppData\Local\Temp\inbox_debug.txt', 'w');

function logMsg($msg) {
    global $fp;
    fwrite($fp, date('Y-m-d H:i:s') . " $msg\n");
}

try {
    logMsg("Starting");
    require_once 'api/config.php';
    logMsg("config loaded");

    require_once 'api/csrf.php';
    logMsg("csrf loaded");

    require_once 'api/helpers.php';
    logMsg("helpers loaded");

    logMsg("Checking imap_open: " . (function_exists('imap_open') ? 'YES' : 'NO'));

    if (!isLoggedIn()) {
        logMsg("Not logged in");
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    logMsg("Logged in");

    $config = loadConfig();
    logMsg("Config loaded: " . ($config ? 'YES' : 'NO'));

    if (!function_exists('imap_open')) {
        logMsg("IMAP not available");
        http_response_code(500);
        echo json_encode(['error' => 'IMAP extension is not available. Please enable php_imap in php.ini.']);
        exit;
    }

    logMsg("IMAP available");

} catch (Throwable $e) {
    logMsg("EXCEPTION: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

fclose($fp);
?>