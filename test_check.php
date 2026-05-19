<?php
echo 'IMAP: ' . (function_exists('imap_open') ? 'YES' : 'NO') . "\n";
echo 'Session: ' . (function_exists('session_start') ? 'YES' : 'NO') . "\n";
echo 'File: ' . __FILE__ . "\n";
echo 'Dir: ' . __DIR__ . "\n";
echo 'Config exists: ' . (file_exists(__DIR__ . '/../data/config.json') ? 'YES' : 'NO') . "\n";
chdir(__DIR__);
echo 'After chdir config exists: ' . (file_exists('data/config.json') ? 'YES' : 'NO') . "\n";
$config = json_decode(@file_get_contents('data/config.json'), true);
echo 'Config loaded: ' . ($config ? 'YES' : 'NO') . "\n";
if ($config) {
    echo 'IMAP host: ' . ($config['imap']['host'] ?? 'none') . "\n";
    echo 'IMAP user: ' . ($config['imap']['user'] ?? 'none') . "\n";
}
?>