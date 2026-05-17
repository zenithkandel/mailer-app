<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/smtp.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: compose.php');
    exit;
}

$to = trim($_POST['to'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$body = $_POST['body'] ?? '';

$error = '';
$success = false;

if ($to === '' || $subject === '' || $body === '') {
    $error = 'All fields are required.';
} else {
    $result = sendEmail($to, $subject, $body);
    if (!empty($result['success'])) {
        $success = true;
        appendSentEmail($to, $subject, $body);
    } else {
        $error = $result['error'] ?? 'Send failed.';
    }
}

renderLayoutStart('Send Status', 'compose');
?>
<div class="card">
    <?php if ($success): ?>
        <div class="alert alert-success">Email sent successfully.</div>
        <div class="toolbar-group">
            <a class="btn btn-primary" href="compose.php">Compose another</a>
            <a class="btn btn-ghost" href="inbox.php">Back to inbox</a>
        </div>
    <?php else: ?>
        <div class="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="toolbar-group">
            <a class="btn btn-primary" href="compose.php">Try again</a>
            <a class="btn btn-ghost" href="inbox.php">Back to inbox</a>
        </div>
    <?php endif; ?>
</div>
<?php renderLayoutEnd(); ?>