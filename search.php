<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/imap.php';

requireLogin();

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if ($query) {
    $results = searchEmails($query);
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
    <title>Search - Mail App</title>
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
                    <i class="fa-sharp-duotone fa-thin fa-inbox"></i> Inbox
                </a>
                <a href="sent.php" class="nav-item">
                    <i class="fa-sharp-duotone fa-thin fa-paper-plane"></i> Sent
                </a>
                <a href="compose.php" class="nav-item">
                    <i class="fa-sharp-duotone fa-thin fa-pencil"></i> Compose
                </a>
                <a href="search.php" class="nav-item active">
                    <i class="fa-sharp-duotone fa-thin fa-magnifying-glass"></i> Search
                </a>
            </nav>
            <div class="sidebar-user">
                <div class="sidebar-user-info">
                    <div class="sidebar-avatar">A</div>
                    <div class="sidebar-user-email"><?php echo htmlspecialchars($_SESSION['user']); ?></div>
                </div>
                <a href="logout.php" class="nav-item" style="margin-top:12px;margin-left:-20px;margin-right:-20px;">
                    <i class="fa-sharp-duotone fa-thin fa-sign-out"></i> Logout
                </a>
            </div>
        </aside>
        <main class="main-content">
            <div class="page-container">
                <div class="page-header">
                    <h2 class="page-title">Search</h2>
                </div>
                <form class="search-form" method="GET">
                    <input type="text" name="q" placeholder="Search by sender or subject..." value="<?php echo htmlspecialchars($query); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>
                <?php if ($query): ?>
                    <?php if (empty($results)): ?>
                    <div class="empty-state">
                        <i class="fa-sharp-duotone fa-thin fa-magnifying-glass"></i>
                        <p>No results found for "<?php echo htmlspecialchars($query); ?>"</p>
                    </div>
                    <?php else: ?>
                    <div class="search-results-info">
                        <i class="fa-sharp-duotone fa-thin fa-circle-check"></i> <?php echo count($results); ?> result(s) found
                    </div>
                    <div class="email-list">
                        <?php foreach ($results as $email): ?>
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
                                <span class="email-date"><?php echo formatDate($email['date']); ?></span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>