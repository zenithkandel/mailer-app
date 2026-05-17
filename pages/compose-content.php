<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../imap.php';
require_once __DIR__ . '/../smtp.php';

requireLogin();

$replyto = isset($_GET['replyto']) ? $_GET['replyto'] : '';
$subject = isset($_GET['subject']) ? $_GET['subject'] : '';
$to = isset($_GET['to']) ? $_GET['to'] : '';

$config = getUserConfig();
$signatures = $config['signatures'] ?? [];
?>

<div class="page-header">
    <h2 class="page-title"><i class="fa-sharp-duotone fa-thin fa-pen-nib"></i> Compose</h2>
</div>

<div id="composeAlert"></div>

<form id="composeForm" onsubmit="sendEmail(event)">
    <div class="compose-form">
        <div class="form-group">
            <label for="to"><i class="fa-sharp-duotone fa-thin fa-user"></i> To</label>
            <input type="email" id="to" required value="<?php echo htmlspecialchars($to); ?>" placeholder="recipient@example.com">
        </div>
        
        <div class="form-group">
            <label for="subject"><i class="fa-sharp-duotone fa-thin fa-heading"></i> Subject</label>
            <input type="text" id="subject" required value="<?php echo htmlspecialchars($subject); ?>" placeholder="Email subject">
        </div>
        
        <?php if (!empty($signatures)): ?>
        <div class="form-group">
            <label for="signature"><i class="fa-sharp-duotone fa-thin fa-signature"></i> Signature</label>
            <select id="signature" onchange="applySignature()">
                <option value="">-- No signature --</option>
                <?php foreach ($signatures as $sig): ?>
                    <option value="<?php echo htmlspecialchars($sig['content']); ?>" data-shortcut="<?php echo htmlspecialchars($sig['shortcut'] ?? ''); ?>">
                        <?php echo htmlspecialchars($sig['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <div class="form-group">
            <label for="body"><i class="fa-sharp-duotone fa-thin fa-align-left"></i> Message</label>
            <textarea id="body" required placeholder="Write your message..." style="min-height:300px;"></textarea>
        </div>
        
        <div class="compose-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fa-sharp-duotone fa-thin fa-paper-plane"></i> Send
            </button>
        </div>
    </div>
</form>

<div id="progress" class="progress-overlay" style="display:none;">
    <div class="progress-content">
        <div class="progress-steps">
            <div class="progress-step"></div>
            <div class="progress-step"></div>
            <div class="progress-step"></div>
            <div class="progress-step"></div>
            <div class="progress-step"></div>
        </div>
        <div class="progress-text">Connecting to server...</div>
    </div>
</div>

<script>
async function sendEmail(e) {
    e.preventDefault();
    
    const form = document.getElementById('composeForm');
    const formData = new FormData(form);
    const alertDiv = document.getElementById('composeAlert');
    const progress = document.getElementById('progress');
    const progressText = progress.querySelector('.progress-text');
    const steps = progress.querySelectorAll('.progress-step');
    
    progress.style.display = 'flex';
    let step = 1;
    const progressInterval = setInterval(() => {
        if (step <= 5) {
            steps[step-1].className = "progress-step active";
            const texts = ["Connecting to server...", "Authenticating...", "Preparing email...", "Sending...", "Finalizing..."];
            progressText.textContent = texts[step-1];
            step++;
        }
    }, 600);
    
    try {
        const response = await fetch("api.php?action=send", {
            method: "POST",
            body: formData
        });
        const result = await response.json();
        
        clearInterval(progressInterval);
        steps.forEach(s => s.className = "progress-step completed");
        
        if (result.success) {
            progressText.textContent = "Sent!";
            alertDiv.innerHTML = "<div class=\"alert alert-success\"><i class=\"fa-sharp-duotone fa-thin fa-circle-check\"></i> Email sent successfully!</div>";
            form.reset();
            setTimeout(() => { progress.classList.remove("active"); progress.style.display = 'none'; }, 1500);
        } else {
            progressText.textContent = "Failed";
            steps[4].style.background = "var(--danger)";
            alertDiv.innerHTML = "<div class=\"alert alert-error\"><i class=\"fa-sharp-duotone fa-thin fa-circle-exclamation\"></i> " + result.error + "</div>";
            setTimeout(() => { progress.style.display = 'none'; }, 2000);
        }
    } catch (err) {
        clearInterval(progressInterval);
        progress.style.display = 'none';
        alertDiv.innerHTML = "<div class=\"alert alert-error\">Error: " + err.message + "</div>";
    }
}

function applySignature() {
    const select = document.getElementById('signature');
    const body = document.getElementById('body');
    if (select.value) {
        body.value += (body.value ? '\n\n' : '') + select.value;
    }
}
</script>