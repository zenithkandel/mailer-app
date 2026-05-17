<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../imap.php';

requireLogin();

$query = isset($_GET['q']) ? $_GET['q'] : '';

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

function formatDateJS($timestamp) {
    return date('Y-m-d H:i:s', $timestamp);
}

$results = [];
if ($query) {
    $results = searchEmails($query);
}
?>

<div class="page-header">
    <h2 class="page-title"><i class="fa-sharp-duotone fa-thin fa-magnifying-glass"></i> Search</h2>
</div>

<div class="search-form" style="margin-bottom:20px;">
    <form onsubmit="event.preventDefault(); performSearch();">
        <input type="text" id="searchQuery" placeholder="Search emails..." value="<?php echo htmlspecialchars($query); ?>" style="width:100%;">
    </form>
</div>

<?php if (!$query): ?>
    <div class="empty-state">
        <i class="fa-sharp-duotone fa-thin fa-magnifying-glass"></i>
        <p>Enter a search term above</p>
    </div>
<?php elseif (empty($results)): ?>
    <div class="empty-state">
        <i class="fa-sharp-duotone fa-thin fa-folder-open"></i>
        <p>No results found</p>
    </div>
<?php else: ?>
    <div class="email-list">
        <?php foreach ($results as $email): ?>
            <a href="#" class="email-item <?php echo $email['read'] ? '' : 'unread'; ?>" onclick="event.preventDefault(); openEmail(<?php echo $email['uid']; ?>, 'search&q=<?php echo urlencode($query); ?>')">
                <div class="email-avatar"><?php echo getInitials($email['from_name']); ?></div>
                <div class="email-content">
                    <span class="email-sender">
                        <?php echo $email['read'] ? '' : '<span class="unread-dot"></span>'; ?>
                        <?php echo htmlspecialchars($email['from_name']); ?>
                    </span>
                    <span class="email-subject"><?php echo htmlspecialchars($email['subject']); ?></span>
                </div>
                <div class="email-meta">
                    <span class="email-date" data-date="<?php echo formatDateJS($email['date']); ?>"></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function performSearch() {
    const q = document.getElementById('searchQuery').value;
    document.getElementById('content-frame').src = 'pages/search-content.php?q=' + encodeURIComponent(q);
}

function openEmail(uid, from) {
    document.getElementById('content-frame').src = 'pages/read-content.php?uid=' + uid + '&from=' + encodeURIComponent(from);
}

function formatDates() {
    document.querySelectorAll('.email-date').forEach(el => {
        const date = new Date(el.dataset.date);
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
}

document.addEventListener('DOMContentLoaded', formatDates);
</script>