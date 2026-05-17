<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/imap.php';

requireLogin();

$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;

if (!$uid) {
    header('Location: inbox.php');
    exit;
}

$email = fetchEmailByUid($uid);

if (!$email) {
    header('Location: inbox.php');
    exit;
}

function formatDateFull($timestamp) {
    return date('F d, Y \a\t H:i', $timestamp);
}

function formatSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

function getInitials($name) {
    if (!$name) return '?';
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
    <title><?php echo htmlspecialchars($email['subject']); ?> - Mail App</title>
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
                    <span class="icon">&#128229;</span>
                    Inbox
                </a>
                <a href="sent.php" class="nav-item">
                    <span class="icon">&#128228;</span>
                    Sent
                </a>
                <a href="compose.php" class="nav-item">
                    <span class="icon">&#9993;</span>
                    Compose
                </a>
                <a href="search.php" class="nav-item">
                    <span class="icon">&#128269;</span>
                    Search
                </a>
            </nav>

            <div class="sidebar-user">
                <div class="sidebar-user-info">
                    <div class="sidebar-avatar"><?php echo getInitials($_SESSION['user']); ?></div>
                    <div class="sidebar-user-email"><?php echo htmlspecialchars($_SESSION['user']); ?></div>
                </div>
                <a href="logout.php" class="nav-item" style="margin-top: 12px; margin-left: -20px; margin-right: -20px;">
                    <span class="icon">&#128682;</span>
                    Logout
                </a>
            </div>
        </aside>

        <main class="main-content">
            <a href="inbox.php" class="back-link">&#8592; Back to Inbox</a>

            <div class="email-reader">
                <div class="email-reader-header">
                    <h1 class="email-reader-subject"><?php echo htmlspecialchars($email['subject']); ?></h1>
                    <dl class="email-reader-meta">
                        <dt>From:</dt>
                        <dd>
                            <?php echo htmlspecialchars($email['from_name']); ?>
                            &lt;<?php echo htmlspecialchars($email['from_email']); ?>&gt;
                        </dd>
                        <dt>To:</dt>
                        <dd>
                            <?php echo htmlspecialchars($email['to_name']); ?>
                            &lt;<?php echo htmlspecialchars($email['to_email']); ?>&gt;
                        </dd>
                        <dt>Date:</dt>
                        <dd><?php echo formatDateFull($email['date']); ?></dd>
                    </dl>
                </div>

                <div class="email-reader-body">
                    <?php if ($email['html']): ?>
                    <iframe srcdoc="<?php echo htmlspecialchars($email['html']); ?>"></iframe>
                    <?php else: ?>
                    <div class="plain-text"><?php echo htmlspecialchars($email['body']); ?></div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($email['attachments'])): ?>
                <div class="email-attachments">
                    <?php foreach ($email['attachments'] as $attachment): ?>
                    <span class="attachment">
                        &#128206; <?php echo htmlspecialchars($attachment['filename']); ?>
                        (<?php echo formatSize($attachment['size']); ?>)
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="email-reader-actions">
                    <form method="POST" action="delete.php" style="display:inline;">
                        <input type="hidden" name="uid" value="<?php echo $email['uid']; ?>">
                        <input type="hidden" name="redirect" value="inbox.php">
                        <button type="submit" class="btn btn-secondary" onclick="return confirm('Delete this email?')">Delete</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>