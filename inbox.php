<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/imap.php';

requireLogin();

$unreadCount = getUnreadCount();
$emails = fetchEmails('INBOX', 50);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox - Mail App</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://zenithkandel.com.np/fontawesome/zenith-icons.js"></script>
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-logo">
                <h1><i class="fa-sharp-duotone fa-thin fa-envelope"></i> Mail</h1>
                <p>Webmail</p>
            </div>

            <nav class="sidebar-nav">
                <a href="inbox.php" class="nav-item active">
                    <i class="fa-sharp-duotone fa-thin fa-inbox"></i>
                    Inbox
                    <?php if ($unreadCount > 0): ?>
                    <span class="badge"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="sent.php" class="nav-item">
                    <i class="fa-sharp-duotone fa-thin fa-paper-plane"></i>
                    Sent
                </a>
<a href="compose.php" class="nav-item">
                    <i class="fa-sharp-duotone fa-thin fa-pen-nib"></i>
                    Compose
                </a>
                <a href="search.php" class="nav-item">
                    <i class="fa-sharp-duotone fa-thin fa-magnifying-glass"></i>
                    Search
                </a>
            </nav>

            <div class="sidebar-user">
                <div class="sidebar-user-info">
                    <div class="sidebar-avatar"><?php echo getInitials($_SESSION['user']); ?></div>
                    <div class="sidebar-user-email"><?php echo htmlspecialchars($_SESSION['user']); ?></div>
                </div>
                <a href="logout.php" class="nav-item" style="margin-top: 12px; margin-left: -20px; margin-right: -20px;">
                    <i class="fa-sharp-duotone fa-thin fa-right-from-bracket"></i>
                    Logout
                </a>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-container">
                <div class="page-header">
                    <h2 class="page-title"><i class="fa-sharp-duotone fa-thin fa-inbox"></i> Inbox</h2>
                    <div class="page-actions">
                        <button class="btn-icon" onclick="location.reload()" title="Refresh"><i class="fa-sharp-duotone fa-thin fa-rotate-right"></i></button>
                    </div>
                </div>

                <?php if (empty($emails)): ?>
                <div class="empty-state">
                    <i class="fa-sharp-duotone fa-thin fa-envelope-open"></i>
                    <p>No emails in your inbox</p>
                </div>
                <?php else: ?>
                <div class="email-list">
                    <?php foreach ($emails as $email): ?>
                    <a href="read.php?uid=<?php echo $email['uid']; ?>" class="email-item <?php echo $email['read'] ? '' : 'unread'; ?>">
                        <div class="email-avatar"><?php echo getInitials($email['from_name']); ?></div>
                        <div class="email-content">
                            <span class="email-sender">
                                <?php if (!$email['read']): ?><span class="unread-dot"></span><?php endif; ?>
                                <?php echo htmlspecialchars($email['from_name']); ?>
                            </span>
                            <span class="email-subject"><?php echo htmlspecialchars($email['subject']); ?></span>
                        </div>
                        <div class="email-meta">
                            <span class="email-date" data-date="<?php echo date('c', $email['date']); ?>"><?php echo date('c', $email['date']); ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>
        document.querySelectorAll('.email-date').forEach(function(el) {
            const date = new Date(el.getAttribute('data-date'));
            const now = new Date();
            const diff = now - date;
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            if (days === 0) {
                el.textContent = date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            } else if (days === 1) {
                el.textContent = 'Yesterday';
            } else if (days < 7) {
                el.textContent = date.toLocaleDateString([], {weekday: 'short'});
            } else {
                el.textContent = date.toLocaleDateString([], {month: 'short', day: 'numeric'});
            }
        });
    </script>
</body>
</html>