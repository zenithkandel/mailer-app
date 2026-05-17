<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../imap.php';

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

function formatDateJson($timestamp) {
    return date('Y-m-d H:i:s', $timestamp);
}
?>

<div class="page-header">
    <h2 class="page-title"><i class="fa-sharp-duotone fa-thin fa-inbox"></i> Inbox</h2>
    <div class="page-actions">
        <button class="btn-icon" onclick="refreshInbox()" title="Refresh">
            <i class="fa-sharp-duotone fa-thin fa-rotate-right"></i>
        </button>
    </div>
</div>

<script>
window.addEventListener('load', function() {
    window.parent.postMessage({ type: 'unreadCount', count: <?php echo $unreadCount; ?> }, '*');
});
</script>

<?php if (empty($emails)): ?>
    <div class="empty-state">
        <i class="fa-sharp-duotone fa-thin fa-envelope-open"></i>
        <p>No emails in your inbox</p>
    </div>
<?php else: ?>
    <div class="email-list">
        <?php foreach ($emails as $email): ?>
            <a href="#" class="email-item <?php echo $email['read'] ? '' : 'unread'; ?>" onclick="event.preventDefault(); openEmail(<?php echo $email['uid']; ?>, 'inbox')">
                <div class="email-avatar"><?php echo getInitials($email['from_name']); ?></div>
                <div class="email-content">
                    <span class="email-sender">
                        <?php if (!$email['read']): ?><span class="unread-dot"></span><?php endif; ?>
                        <?php echo htmlspecialchars($email['from_name']); ?>
                    </span>
                    <span class="email-subject"><?php echo htmlspecialchars($email['subject']); ?></span>
                </div>
                <div class="email-meta">
                    <span class="email-date" data-date="<?php echo formatDateJson($email['date']); ?>"></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function refreshInbox() {
    document.getElementById('content-frame').src = 'pages/inbox-content.php';
}

function openEmail(uid, from) {
    document.getElementById('content-frame').src = 'pages/read-content.php?uid=' + uid + '&from=' + from;
}

function formatDates() {
    document.querySelectorAll('.email-date').forEach(function(el) {
        var date = new Date(el.dataset.date);
        var now = new Date();
        var diff = now - date;
        var days = Math.floor(diff / (1000 * 60 * 60 * 24));
        
        if (days === 0) {
            el.textContent = date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
        } else if (days === 1) {
            el.textContent = 'Yesterday';
        } else if (days < 7) {
            el.textContent = date.toLocaleDateString([], {weekday: 'short'});
        } else {
            el.textContent = date.toLocaleDateString([], {month: 'short', day: 'numeric'});
        }
    });
}

document.addEventListener('DOMContentLoaded', formatDates);
</script>