<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/imap.php';
requireLogin();

$uid = $_GET['uid'] ?? '';
$folderParam = strtolower($_GET['folder'] ?? 'inbox');
$active = $folderParam === 'sent' ? 'sent' : 'inbox';
$folder = 'INBOX';
$useLocalSent = false;

if ($active === 'sent') {
    $sentFolder = getSentFolder();
    if ($sentFolder) {
        $folder = $sentFolder;
    } else {
        $useLocalSent = true;
    }
}

if ($uid === '') {
    renderLayoutStart('Read Email', $active);
    echo '<div class="card">Invalid email.</div>';
    renderLayoutEnd();
    exit;
}

$email = null;

if ($useLocalSent) {
    $item = getLocalSentById($uid);
    if ($item) {
        $rawBody = $item['body'] ?? '';
        $body = $rawBody;
        if (stripos($rawBody, '<') === false) {
            $body = nl2br(htmlspecialchars($rawBody, ENT_QUOTES, 'UTF-8'));
        }
        $email = [
            'from_name' => SMTP_USER,
            'from_email' => SMTP_FROM,
            'to' => $item['to'] ?? '',
            'subject' => $item['subject'] ?? '(No Subject)',
            'date' => $item['date'] ?? time(),
            'body' => $body,
            'attachments' => []
        ];
    }
} else {
    $email = getEmailByUid($uid, $folder);
    if ($email) {
        markAsRead($uid, $folder);
    }
}

if (!$email) {
    renderLayoutStart('Read Email', $active);
    echo '<div class="card">Email not found.</div>';
    renderLayoutEnd();
    exit;
}

$backLink = $active === 'sent' ? 'inbox.php?folder=sent' : 'inbox.php';
$dateText = date('M d, Y H:i', (int) $email['date']);
$replyLink = '';
if ($active === 'inbox' && !empty($email['from_email'])) {
    $replyLink = 'compose.php?replyto=' . urlencode($email['from_email']) . '&subject=' . urlencode('Re: ' . $email['subject']);
}

renderLayoutStart('Read Email', $active);
?>
<div class="toolbar">
    <div class="toolbar-group">
        <a class="btn btn-ghost" href="<?php echo $backLink; ?>">Back</a>
        <?php if ($replyLink): ?>
            <a class="btn btn-ghost" href="<?php echo $replyLink; ?>">Reply</a>
        <?php endif; ?>
    </div>
    <?php if ($active === 'inbox'): ?>
        <a class="btn btn-danger" href="inbox.php?delete=<?php echo urlencode((string) $uid); ?>"
            onclick="return confirm('Delete this email?');">Delete</a>
    <?php endif; ?>
</div>

<div class="card">
    <h2><?php echo htmlspecialchars($email['subject'], ENT_QUOTES, 'UTF-8'); ?></h2>
    <div class="mail-meta-grid">
        <div>
            <strong>From</strong>
            <?php echo htmlspecialchars($email['from_name'], ENT_QUOTES, 'UTF-8'); ?>
            &lt;<?php echo htmlspecialchars($email['from_email'], ENT_QUOTES, 'UTF-8'); ?>&gt;
        </div>
        <?php if (!empty($email['to'])): ?>
            <div>
                <strong>To</strong>
                <?php echo htmlspecialchars($email['to'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <div>
            <strong>Date</strong>
            <?php echo htmlspecialchars($dateText, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    </div>
    <div class="mail-body"><?php echo $email['body']; ?></div>

    <?php if (!empty($email['attachments'])): ?>
        <div class="attachment-list">
            <?php foreach ($email['attachments'] as $att): ?>
                <span class="attachment-item"><?php echo htmlspecialchars($att['name'], ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php renderLayoutEnd(); ?>