<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');

echo "PHP Version: " . PHP_VERSION . "\n";
echo "IMAP Extension: " . (extension_loaded('imap') ? 'YES' : 'NO') . "\n";
echo "Functions exist: " . (function_exists('imap_open') ? 'YES' : 'NO') . "\n";

if (function_exists('imap_open')) {
    echo "\nTesting IMAP connection...\n";
    $mbox = @imap_open('{mail.zenithkandel.com.np:993/imap/ssl}INBOX', 'admin@zenithkandel.com.np', 'test');
    if ($mbox) {
        echo "SUCCESS: Connected!\n";
        imap_close($mbox);
    } else {
        echo "FAILED: " . imap_last_error() . "\n";
    }
} else {
    echo "\nIMAP not available - fix PHP configuration\n";
}