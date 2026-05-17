<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/imap.php';
require_once __DIR__ . '/smtp.php';

requireLogin();

$unreadCount = getUnreadCount();
$inboxEmails = fetchEmails('INBOX', 10);
$sentEmails = getSentLog();

function getInitials($name) {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach ($parts as $part) {
        if (strlen($initials) < 2) $initials .= strtoupper(substr($part, 0, 1));
    }
    return $initials ?: '?';
}

function formatDate($timestamp) {
    $now = time();
    $diff = $now - $timestamp;
    if ($diff < 86400) return date('H:i', $timestamp);
    elseif ($diff < 604800) return date('D', $timestamp);
    else return date('M d', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Mail App</title>
    <link rel="stylesheet" href="style.css">
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
                    <span class="icon">&#128229;</span> Inbox
                </a>
                <a href="sent.php" class="nav-item">
                    <span class="icon">&#128228;</span> Sent
                </a>
                <a href="compose.php" class="nav-item">
                    <span class="icon">&#9993;</span> Compose
                </a>
                <a href="search.php" class="nav-item">
                    <span class="icon">&#128269;</span> Search
                </a>
            </nav>
            <div class="sidebar-user">
                <div class="sidebar-user-info">
                    <div class="sidebar-avatar">A</div>
                    <div class="sidebar-user-email"><?php echo htmlspecialchars($_SESSION['user']); ?></div>
                </div>
                <a href="logout.php" class="nav-item" style="margin-top:12px;margin-left:-20px;margin-right:-20px;">
                    <span class="icon">&#128682;</span> Logout
                </a>
            </div>
        </aside>
        <main class="main-content">
            <div class="page-header">
                <h2 class="page-title">Dashboard</h2>
                <a href="compose.php" class="btn btn-peach">Compose</a>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3>Unread</h3>
                    <div class="value green"><?php echo $unreadCount; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Sent</h3>
                    <div class="value accent"><?php echo count($sentEmails); ?></div>
                </div>
            </div>

            <h3 style="margin-bottom:16px;font-size:15px;font-weight:500;">Recent Inbox</h3>
            <?php if (empty($inboxEmails)): ?>
            <div class="empty-state">
                <div class="icon">&#128231;</div>
                <p>No emails in inbox</p>
            </div>
            <?php else: ?>
            <div class="email-list">
                <?php foreach ($inboxEmails as $email): ?>
                <a href="read.php?uid=<?php echo $email['uid']; ?>" class="email-item <?php echo $email['read'] ? '' : 'unread'; ?>">
                    <div class="email-avatar"><?php echo getInitials($email['from_name']); ?></div>
                    <div class="email-content">
                        <div class="email-sender">
                            <?php if (!$email['read']): ?><span class="unread-dot"></span><?php endif; ?>
                            <?php echo htmlspecialchars($email['from_name']); ?>
                        </div>
                        <div class="email-subject"><?php echo htmlspecialchars($email['subject']); ?></div>
                        <div class="email-preview"><?php echo htmlspecialchars($email['preview']); ?></div>
                    </div>
                    <div class="email-meta">
                        <div class="email-date"><?php echo formatDate($email['date']); ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>