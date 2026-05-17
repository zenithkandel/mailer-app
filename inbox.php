<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/imap.php';
requireLogin();

$folderParam = strtolower($_GET['folder'] ?? 'inbox');
$active = 'inbox';
$title = 'Inbox';
$folder = 'INBOX';
$useLocalSent = false;

if ($folderParam === 'sent') {
    $active = 'sent';
    $title = 'Sent';
    $sentFolder = getSentFolder();
    if ($sentFolder) {
        $folder = $sentFolder;
    } else {
        $useLocalSent = true;
    }
}

$query = trim($_GET['q'] ?? '');
$delete = $_GET['delete'] ?? '';

if ($delete && $active === 'inbox') {
    $uid = intval($delete);
    if ($uid) {
        deleteEmail($uid, $folder);
    }

    $redirect = 'inbox.php';
    $params = [];
    if ($folderParam === 'sent') {
        $params[] = 'folder=sent';
    }
    if ($query !== '') {
        $params[] = 'q=' . urlencode($query);
    }
    if ($params) {
        $redirect .= '?' . implode('&', $params);
    }
    header('Location: ' . $redirect);
    exit;
}

if ($active === 'sent') {
    if ($useLocalSent) {
        $emails = getLocalSent(50, $query);
    } else {
        $emails = $query ? searchEmails($query, $folder, 50) : fetchEmails($folder, 50, true);
    }
} else {
    $emails = $query ? searchEmails($query, $folder, 50) : fetchEmails($folder, 50, true);
}

$unreadCount = $active === 'inbox' ? getUnreadCount('INBOX') : 0;

renderLayoutStart($title, $active);
?>
<div class="toolbar">
    <div class="toolbar-group">
        <?php if ($active === 'inbox'): ?>
            <div class="card">Unread: <?php echo (int) $unreadCount; ?></div>
        <?php endif; ?>
    </div>
    <form class="search" method="get" action="inbox.php">
        <?php if ($active === 'sent'): ?>
            <input type="hidden" name="folder" value="sent">
        <?php endif; ?>
        <input type="text" name="q" placeholder="Search sender or subject"
            value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'UTF-8'); ?>">
        <button class="btn btn-ghost" type="submit">Search</button>
        <a class="btn btn-ghost" href="inbox.php<?php echo $active === 'sent' ? '?folder=sent' : ''; ?>">Refresh</a>
    </form>
</div>

<?php if (empty($emails)): ?>
    <div class="card empty-state">No emails found.</div>
<?php else: ?>
    <div class="mail-list">
        <?php foreach ($emails as $email): ?>
            <?php
            $isUnread = !$email['read'] && $active === 'inbox';
            $rowClass = $isUnread ? 'mail-row unread' : 'mail-row';
            $date = isset($email['date']) ? date('M d, H:i', (int) $email['date']) : '';
            $uid = urlencode((string) $email['uid']);
            $folderQuery = $active === 'sent' ? '&folder=sent' : '';
            $readLink = 'read.php?uid=' . $uid . $folderQuery;
            $deleteLink = 'inbox.php?delete=' . $uid;
            if ($query !== '') {
                $deleteLink .= '&q=' . urlencode($query);
            }
            ?>
            <a class="<?php echo $rowClass; ?>" href="<?php echo $readLink; ?>">
                <div class="mail-from">
                    <?php if ($isUnread): ?>
                        <span class="dot"></span>
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars($email['from_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="mail-subject"><?php echo htmlspecialchars($email['subject'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="mail-snippet"><?php echo htmlspecialchars($email['snippet'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="mail-meta">
                    <span><?php echo $date; ?></span>
                    <?php if ($active === 'inbox'): ?>
                        <a class="btn btn-ghost btn-xs" href="<?php echo $deleteLink; ?>"
                            onclick="return confirm('Delete this email?');">Delete</a>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php renderLayoutEnd(); ?>