<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/imap.php';
require_once __DIR__ . '/smtp.php';

requireLogin();

$sentEmails = [];
$useJsonLog = false;

$sentFolder = getSentFolderConnection();
if ($sentFolder) {
    $sentEmails = fetchEmails($sentFolder['folder'], 50);
} else {
    $useJsonLog = true;
    $sentEmails = getSentLog();
}

function getInitials($name) {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach ($parts as $part) {
        if (strlen($initials) < 2) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
    }
    return $initials ?: '?';
}

function formatDate($timestamp) {
    if (is_numeric($timestamp)) {
        $ts = $timestamp;
    } else {
        $ts = strtotime($timestamp);
    }
    $now = time();
    $diff = $now - $ts;

    if ($diff < 86400) {
        return date('H:i', $ts);
    } elseif ($diff < 604800) {
        return date('D', $ts);
    } else {
        return date('M d', $ts);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sent - Mail App</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://zenithkandel.com.np/fontawesome/zenith-icons.js"></script>
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-logo">
                <h1>Mail</h1>
                <p>Webmail</p>
            </div>

            <nav class="sidebar-nav">
                <a href="inbox.php" class="nav-item">
                    <i class="icon fa fa-inbox"></i>
                    Inbox
                </a>
                <a href="sent.php" class="nav-item active">
                    <i class="icon fa fa-paper-plane"></i>
                    Sent
                </a>
                <a href="compose.php" class="nav-item">
                    <i class="icon fa fa-pencil"></i>
                    Compose
                </a>
                <a href="search.php" class="nav-item">
                    <i class="icon fa fa-search"></i>
                    Search
                </a>
            </nav>

            <div class="sidebar-user">
                <div class="sidebar-user-info">
                    <div class="sidebar-avatar">A</div>
                    <div class="sidebar-user-email"><?php echo htmlspecialchars($_SESSION['user']); ?></div>
                </div>
                <a href="logout.php" class="nav-item" style="margin-top: 12px; margin-left: -20px; margin-right: -20px;">
                    <i class="icon fa fa-sign-out"></i>
                    Logout
                </a>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-container">
                <div class="page-header">
                    <h2 class="page-title">Sent</h2>
                    <?php if ($useJsonLog): ?>
                    <span style="font-size:12px;color:var(--text-muted);">(Local Log)</span>
                    <?php endif; ?>
                </div>

                <?php if (empty($sentEmails)): ?>
                <div class="empty-state">
                    <i class="icon fa fa-paper-plane-o"></i>
                    <p>No sent emails</p>
                </div>
                <?php else: ?>
                <div class="email-list">
                    <?php if ($useJsonLog): ?>
                        <?php foreach ($sentEmails as $email): ?>
                        <div class="email-item">
                            <div class="email-avatar"><?php echo getInitials($email['to']); ?></div>
                            <div class="email-content">
                                <span class="email-sender"><?php echo htmlspecialchars($email['to']); ?></span>
                                <span class="email-subject"><?php echo htmlspecialchars($email['subject']); ?></span>
                            </div>
                            <div class="email-meta">
                                <span class="email-date"><?php echo formatDate($email['date']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach ($sentEmails as $email): ?>
                        <div class="email-item">
                            <div class="email-avatar"><?php echo getInitials($email['from_name']); ?></div>
                            <div class="email-content">
                                <span class="email-sender"><?php echo htmlspecialchars($email['from_name']); ?></span>
                                <span class="email-subject"><?php echo htmlspecialchars($email['subject']); ?></span>
                            </div>
                            <div class="email-meta">
                                <span class="email-date"><?php echo formatDate($email['date']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>