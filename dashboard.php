<?php
require_once __DIR__ . '/config.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail - Webmail</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://zenithkandel.com.np/fontawesome/zenith-icons.js"></script>
    <style>
        .spa-container {
            display: flex;
            min-height: 100vh;
        }

        .spa-sidebar {
            width: 240px;
            flex-shrink: 0;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 100;
        }

        .spa-main {
            flex: 1;
            margin-left: 240px;
            padding: 32px;
            max-width: 900px;
        }

        .loader-full {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-main);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 200;
        }

        .loader-full.hidden {
            display: none;
        }

        .loader-full-content {
            text-align: center;
            width: 300px;
        }

        .loader-icon {
            font-size: 32px;
            color: var(--accent);
            margin-bottom: 16px;
            animation: pulse 1.5s ease-in-out infinite;
        }

        <?php
        require_once __DIR__ . '/config.php';
        requireLogin();

        header('Location: inbox.php');
        exit;