<?php
require_once __DIR__ . '/config.php';
requireLogin();

$replyTo = trim($_GET['replyto'] ?? '');
$subject = trim($_GET['subject'] ?? '');

$config = getUserConfig();
$signatures = $config['signatures'] ?? [];

renderLayoutStart('Compose', 'compose');
?>
<div class="card">
    <form method="post" action="send.php">
        <label class="field">
            <span>To</span>
            <input type="email" name="to" required
                value="<?php echo htmlspecialchars($replyTo, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="recipient@example.com">
        </label>

        <label class="field">
            <span>Subject</span>
            <input type="text" name="subject" required
                value="<?php echo htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Email subject">
        </label>

        <?php if (!empty($signatures)): ?>
            <label class="field">
                <span>Signature</span>
                <select id="signature">
                    <option value="">No signature</option>
                    <?php foreach ($signatures as $sig): ?>
                        <option value="<?php echo htmlspecialchars($sig['content'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($sig['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>

        <label class="field">
            <span>Message (HTML supported)</span>
            <textarea name="body" id="body" required placeholder="Write your message..."></textarea>
        </label>

        <button class="btn btn-primary" type="submit">Send</button>
    </form>
</div>

<?php if (!empty($signatures)): ?>
    <script>
        (function () {
            var select = document.getElementById('signature');
            var body = document.getElementById('body');
            if (!select || !body) return;
            select.addEventListener('change', function () {
                if (!select.value) return;
                body.value += (body.value ? "\n\n" : "") + select.value;
                select.value = '';
            });
        })();
    </script>
<?php endif; ?>

<?php renderLayoutEnd(); ?>