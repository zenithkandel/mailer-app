<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../imap.php';

requireLogin();

$uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
$from = isset($_GET['from']) ? $_GET['from'] : 'inbox';

if (!$uid) {
    echo '<div class="alert alert-error">Invalid email</div>';
    exit;
}

$email = getEmailByUid($uid);

if (!$email) {
    echo '<div class="alert alert-error">Email not found</div>';
    exit;
}

markAsRead($uid);

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

function formatDateFull($timestamp) {
    return date('Y-m-d H:i:s', $timestamp);
}
?>

<div class="page-header">
    <h2 class="page-title"><i class="fa-sharp-duotone fa-thin fa-envelope-open"></i> Read Email</h2>
    <div class="page-actions">
        <button class="btn-icon" onclick="goBack('<?php echo htmlspecialchars($from); ?>')" title="Back">
            <i class="fa-sharp-duotone fa-thin fa-arrow-left"></i>
        </button>
        <button class="btn-icon" onclick="deleteEmail(<?php echo $uid; ?>, '<?php echo htmlspecialchars($from); ?>')" title="Delete">
            <i class="fa-sharp-duotone fa-thin fa-trash"></i>
        </button>
    </div>
</div>

<div class="email-view">
    <div class="email-header">
        <div class="email-avatar"><?php echo getInitials($email['from_name']); ?></div>
        <div class="email-header-content">
            <div class="email-header-from">
                <strong><?php echo htmlspecialchars($email['from_name']); ?></strong>
                <span class="email-header-email">&lt;<?php echo htmlspecialchars($email['from_email']); ?>&gt;</span>
            </div>
            <div class="email-header-subject"><?php echo htmlspecialchars($email['subject']); ?></div>
            <div class="email-header-date" data-date="<?php echo formatDateFull($email['date']); ?>"></div>
        </div>
    </div>
    
    <div class="email-body">
        <?php echo $email['body']; ?>
    </div>
    
    <?php if (!empty($email['attachments'])): ?>
    <div class="email-attachments">
        <h4><i class="fa-sharp-duotone fa-thin fa-paperclip"></i> Attachments</h4>
        <div class="attachment-list">
            <?php foreach ($email['attachments'] as $att): ?>
                <span class="attachment-item">
                    <i class="fa-sharp-duotone fa-thin fa-file"></i>
                    <?php echo htmlspecialchars($att['name']); ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function goBack(from) {
    document.getElementById('content-frame').src = 'pages/' + from + '-content.php';
}

function deleteEmail(uid, from) {
    if (!confirm('Delete this email?')) return;
    fetch('../api.php?action=delete&uid=' + uid).then(function() {
        goBack(from);
    });
}

function formatFullDate() {
    var el = document.querySelector('.email-header-date');
    if (el) {
        var date = new Date(el.dataset.date);
        el.textContent = date.toLocaleString();
    }
}

document.addEventListener('DOMContentLoaded', formatFullDate);
</script>