<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/imap.php';
require_once __DIR__ . '/smtp.php';

header('Content-Type: text/html; charset=UTF-8');

$action = $_GET['action'] ?? 'inbox';

if (!isLoggedIn()) {
    echo '<div class="alert alert-error">Session expired</div>';
    exit;
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

function formatDateJS($timestamp) {
    return date('c', $timestamp);
}

if ($action === 'logout') {
    session_destroy();
    exit;
}

if ($action === 'inbox') {
    $unreadCount = getUnreadCount();
    $emails = fetchEmails('INBOX', 50);
    
    echo '<div class="page-header">
        <h2 class="page-title"><i class="fa-sharp-duotone fa-thin fa-inbox"></i> Inbox</h2>
        <div class="page-actions">
            <button class="btn-icon" onclick="navigate(\'inbox\')" title="Refresh"><i class="fa-sharp-duotone fa-thin fa-rotate-right"></i></button>
        </div>
    </div>
    <span class="unread-count" style="display:none">'.$unreadCount.'</span>';

    if (empty($emails)) {
        echo '<div class="empty-state">
            <i class="fa-sharp-duotone fa-thin fa-envelope-open"></i>
            <p>No emails in your inbox</p>
        </div>';
    } else {
        echo '<div class="email-list">';
        foreach ($emails as $email) {
            echo '<a href="#" class="email-item '.($email['read'] ? '' : 'unread').'" onclick="event.preventDefault(); openEmail('.$email['uid'].')">
                <div class="email-avatar">'.getInitials($email['from_name']).'</div>
                <div class="email-content">
                    <span class="email-sender">
                        '.($email['read'] ? '' : '<span class="unread-dot"></span>').'
                        '.htmlspecialchars($email['from_name']).'
                    </span>
                    <span class="email-subject">'.htmlspecialchars($email['subject']).'</span>
                </div>
                <div class="email-meta">
                    <span class="email-date" data-date="'.formatDateJS($email['date']).'"></span>
                </div>
            </a>';
        }
        echo '</div>';
    }
    
    echo '<script>document.querySelectorAll(".email-date").forEach(el => {
        const d = new Date(el.dataset.date);
        const now = new Date();
        const days = Math.floor((now - d) / (1000*60*60*24));
        el.textContent = days === 0 ? d.toLocaleTimeString([],{hour:"2-digit",minute:"2-digit"}) : days === 1 ? "Yesterday" : days < 7 ? d.toLocaleDateString([],{weekday:"short"}) : d.toLocaleDateString([],{month:"short",day:"numeric"});
    });</script>';
}

if ($action === 'sent') {
    $sentEmails = [];
    $useJsonLog = false;
    $sentFolder = getSentFolderConnection();
    if ($sentFolder) {
        $sentEmails = fetchEmails($sentFolder['folder'], 50);
    } else {
        $useJsonLog = true;
        $sentEmails = getSentLog();
    }
    
    echo '<div class="page-header">
        <h2 class="page-title"><i class="fa-sharp-duotone fa-thin fa-paper-plane"></i> Sent</h2>
        '.($useJsonLog ? '<span style="font-size:12px;color:var(--text-muted);">(Local Log)</span>' : '').'
    </div>';

    if (empty($sentEmails)) {
        echo '<div class="empty-state">
            <i class="fa-sharp-duotone fa-thin fa-paper-plane-empty"></i>
            <p>No sent emails</p>
        </div>';
    } else {
        echo '<div class="email-list">';
        foreach ($sentEmails as $email) {
            $sender = $useJsonLog ? $email['to'] : ($email['from_name'] ?? '');
            $date = $useJsonLog ? strtotime($email['date']) : $email['date'];
            echo '<div class="email-item">
                <div class="email-avatar">'.getInitials($sender).'</div>
                <div class="email-content">
                    <span class="email-sender">'.htmlspecialchars($sender).'</span>
                    <span class="email-subject">'.htmlspecialchars($email['subject']).'</span>
                </div>
                <div class="email-meta">
                    <span class="email-date" data-date="'.formatDateJS($date).'"></span>
                </div>
            </div>';
        }
        echo '</div>';
    }
    
    echo '<script>document.querySelectorAll(".email-date").forEach(el => {
        const d = new Date(el.dataset.date);
        const now = new Date();
        const days = Math.floor((now - d) / (1000*60*60*24));
        el.textContent = days === 0 ? d.toLocaleTimeString([],{hour:"2-digit",minute:"2-digit"}) : days === 1 ? "Yesterday" : days < 7 ? d.toLocaleDateString([],{weekday:"short"}) : d.toLocaleDateString([],{month:"short",day:"numeric"});
    });</script>';
}

if ($action === 'compose') {
    $config = getUserConfig();
    $signatures = $config['signatures'] ?? [];
    
    echo '<div class="page-header">
        <h2 class="page-title"><i class="fa-sharp-duotone fa-thin fa-pen-nib"></i> Compose</h2>
    </div>

    <div id="composeAlert"></div>

    <div class="send-progress" id="sendProgress">
        <div class="progress-bar-container">
            <div class="progress-spinner"></div>
            <span class="progress-text" id="progressText">Connecting...</span>
        </div>
        <div class="progress-steps">
            <div class="progress-step" id="step1"></div>
            <div class="progress-step" id="step2"></div>
            <div class="progress-step" id="step3"></div>
            <div class="progress-step" id="step4"></div>
            <div class="progress-step" id="step5"></div>
        </div>
    </div>

    <form class="compose-form" id="composeForm">
        <div class="form-group">
            <label for="to"><i class="fa-sharp-duotone fa-thin fa-user"></i> To</label>
            <input type="email" id="to" name="to" placeholder="recipient@example.com" required>
        </div>

        <div class="form-group">
            <label for="subject"><i class="fa-sharp-duotone fa-thin fa-heading"></i> Subject</label>
            <input type="text" id="subject" name="subject" placeholder="Enter subject" required>
        </div>
        
        <div class="form-group">
            <label for="signature"><i class="fa-sharp-duotone fa-thin fa-signature"></i> Signature (optional)</label>
            <select id="signature" onchange="insertSignature(this.value)" style="width:100%;height:44px;padding:0 14px;border:1px solid var(--border);background:var(--bg-input);font-family:inherit;font-size:14px;color:var(--text-primary);">
                <option value="">No signature</option>';
                foreach ($signatures as $sig) {
                    $shortcut = $sig['shortcut'] ? ' (' . $sig['shortcut'] . ')' : '';
                    echo '<option value="'.htmlspecialchars($sig['content']).'">'.htmlspecialchars($sig['name']).$shortcut.'</option>';
                }
            echo '</select>
            <small style="color:var(--text-muted);display:block;margin-top:4px;">Select a signature or type ';

if (!empty($signatures)) {
    $shortcuts = array_filter(array_column($signatures, 'shortcut'));
    if (!empty($shortcuts)) {
        echo 'shortcuts: ' . implode(', ', $shortcuts);
    } else {
        echo 'a / for signature menu';
    }
} else {
    echo 'none configured - add in Settings';
}
echo '</small>
        </div>';

        <div class="form-group">
            <label for="message"><i class="fa-sharp-duotone fa-thin fa-align-left"></i> Message</label>
            <textarea id="message" name="message" placeholder="Write your message here..." required></textarea>
        </div>

        <div class="compose-actions">
            <button type="submit" class="btn btn-primary" id="sendBtn"><i class="fa-sharp-duotone fa-thin fa-paper-plane"></i> Send Email</button>
        </div>
    </form>

    <script>
    document.getElementById("composeForm").addEventListener("submit", async function(e) {
        e.preventDefault();
        const form = this;
        const progress = document.getElementById("sendProgress");
        const progressText = document.getElementById("progressText");
        const sendBtn = document.getElementById("sendBtn");
        const alertDiv = document.getElementById("composeAlert");
        
        const formData = new FormData(form);
        const steps = [
            document.getElementById("step1"), document.getElementById("step2"),
            document.getElementById("step3"), document.getElementById("step4"), document.getElementById("step5")
        ];
        
        progress.classList.add("active");
        sendBtn.disabled = true;
        sendBtn.innerHTML = "<i class=\"fa-sharp-duotone fa-thin fa-spinner fa-spin\"></i> Sending...";
        alertDiv.innerHTML = "";
        
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
                setTimeout(() => { progress.classList.remove("active"); }, 1500);
            } else {
                progressText.textContent = "Failed";
                steps[4].style.background = "var(--danger)";
                alertDiv.innerHTML = "<div class=\"alert alert-error\"><i class=\"fa-sharp-duotone fa-thin fa-circle-exclamation\"></i> " + result.error + "</div>";
            }
        } catch (err) {
            clearInterval(progressInterval);
            alertDiv.innerHTML = "<div class=\"alert alert-error\">Error sending email</div>";
        }
        
        sendBtn.disabled = false;
        sendBtn.innerHTML = "<i class=\"fa-sharp-duotone fa-thin fa-paper-plane\"></i> Send Email";
    });
    </script>';
}

if ($action === 'search') {
    $query = $_GET['q'] ?? '';
    $results = $query ? searchEmails($query) : [];
    
    echo '<div class="page-header">
        <h2 class="page-title"><i class="fa-sharp-duotone fa-thin fa-magnifying-glass"></i> Search</h2>
    </div>
    
    <form class="search-form" onsubmit="event.preventDefault(); navigate(\'search\', {q: this.query.value})">
        <input type="text" name="query" placeholder="Search by sender or subject..." value="'.htmlspecialchars($query).'">
        <button type="submit" class="btn btn-primary"><i class="fa-sharp-duotone fa-thin fa-magnifying-glass"></i> Search</button>
    </form>';
    
    if ($query) {
        if (empty($results)) {
            echo '<div class="empty-state">
                <i class="fa-sharp-duotone fa-thin fa-magnifying-glass-minus"></i>
                <p>No results found for "'.htmlspecialchars($query).'"</p>
            </div>';
        } else {
            echo '<div class="search-results-info"><i class="fa-sharp-duotone fa-thin fa-circle-check"></i> '.count($results).' result(s) found</div>
            <div class="email-list">';
            foreach ($results as $email) {
                echo '<a href="#" class="email-item '.($email['read'] ? '' : 'unread').'" onclick="event.preventDefault(); openEmail('.$email['uid'].')">
                    <div class="email-avatar">'.getInitials($email['from_name']).'</div>
                    <div class="email-content">
                        <span class="email-sender">'.($email['read'] ? '' : '<span class="unread-dot"></span>').' '.htmlspecialchars($email['from_name']).'</span>
                        <span class="email-subject">'.htmlspecialchars($email['subject']).'</span>
                    </div>
                    <div class="email-meta"><span class="email-date" data-date="'.formatDateJS($email['date']).'"></span></div>
                </a>';
            }
            echo '</div>';
        }
    }
    
    echo '<script>document.querySelectorAll(".email-date").forEach(el => {
        const d = new Date(el.dataset.date);
        const now = new Date();
        const days = Math.floor((now - d) / (1000*60*60*24));
        el.textContent = days === 0 ? d.toLocaleTimeString([],{hour:"2-digit",minute:"2-digit"}) : days === 1 ? "Yesterday" : days < 7 ? d.toLocaleDateString([],{weekday:"short"}) : d.toLocaleDateString([],{month:"short",day:"numeric"});
    });</script>';
}

if ($action === 'read') {
    $uid = (int)$_GET['uid'] ?? 0;
    if (!$uid) {
        echo '<div class="alert alert-error">Invalid email</div>';
        exit;
    }
    
    markAsRead($uid);
    $email = fetchEmailByUid($uid);
    
    if (!$email) {
        echo '<div class="alert alert-error">Email not found</div>';
        exit;
    }
    
    function formatDateFull($ts) { return date('F d, Y \a\t H:i', $ts); }
    function formatSize($b) {
        if ($b < 1024) return $b . ' B';
        if ($b < 1048576) return round($b/1024,1) . ' KB';
        return round($b/1048576,1) . ' MB';
    }
    
    echo '<a href="#" class="back-link" onclick="event.preventDefault(); navigate(\''.($_GET['from'] ?? 'inbox').'\');">
        <i class="fa-sharp-duotone fa-thin fa-arrow-left"></i> Back
    </a>
    
    <div class="email-reader">
        <div class="email-reader-header">
            <h1 class="email-reader-subject">'.htmlspecialchars($email['subject']).'</h1>
            <dl class="email-reader-meta">
                <dt>From:</dt>
                <dd>'.htmlspecialchars($email['from_name']).' &lt;'.htmlspecialchars($email['from_email']).'&gt;</dd>
                <dt>To:</dt>
                <dd>'.htmlspecialchars($email['to_name']).' &lt;'.htmlspecialchars($email['to_email']).'&gt;</dd>
                <dt>Date:</dt>
                <dd>'.formatDateFull($email['date']).'</dd>
            </dl>
        </div>
        
        <div class="email-reader-body">
            '.($email['html'] ? '<iframe srcdoc="'.htmlspecialchars($email['html']).'"></iframe>' : '<div class="plain-text">'.htmlspecialchars($email['body']).'</div>').'
        </div>';
        
    if (!empty($email['attachments'])) {
        echo '<div class="email-attachments">';
        foreach ($email['attachments'] as $att) {
            echo '<span class="attachment"><i class="fa-sharp-duotone fa-thin fa-paperclip"></i> '.htmlspecialchars($att['filename']).' ('.formatSize($att['size']).')</span>';
        }
        echo '</div>';
    }
    
    echo '<div class="email-reader-actions">
        <button class="btn btn-danger" onclick="deleteEmail('.$uid.', \''.$_GET['from'].'\')">
            <i class="fa-sharp-duotone fa-thin fa-trash"></i> Delete
        </button>
    </div></div>';
}

if ($action === 'send') {
    header('Content-Type: application/json');
    
    $to = trim($_POST['to'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = $_POST['message'] ?? '';
    
    if (empty($to) || empty($subject) || empty($message)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        exit;
    }
    
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email address']);
        exit;
    }
    
    $result = smtpSendEmail($to, $subject, $message);
    echo json_encode($result);
    exit;
}

if ($action === 'delete') {
    header('Content-Type: application/json');
    $uid = (int)$_POST['uid'] ?? 0;
    $from = $_POST['from'] ?? 'inbox';
    
    $success = deleteEmail($uid);
    echo json_encode(['success' => $success, 'redirect' => $from]);
    exit;
}

if ($action === 'settings') {
    $config = getUserConfig();
    
    echo '<div class="page-header">
        <h2 class="page-title"><i class="fa-sharp-duotone fa-thin fa-gear"></i> Settings</h2>
    </div>
    
    <div id="settingsAlert"></div>
    
    <div class="compose-form">
        <div class="form-group">
            <label for="senderName"><i class="fa-sharp-duotone fa-thin fa-user"></i> Display Name</label>
            <input type="text" id="senderName" placeholder="Your name as recipients see it" value="'.htmlspecialchars($config['senderName'] ?? '').'">
            <small style="color:var(--text-muted);display:block;margin-top:4px;">This name will appear in the "From" field for recipients</small>
        </div>
        
        <div class="form-group">
            <label><i class="fa-sharp-duotone fa-thin fa-signature"></i> Signatures</label>
            <div id="signatureList">';
    
    $signatures = $config['signatures'] ?? [];
    if (empty($signatures)) {
        echo '<p style="color:var(--text-muted);font-size:13px;">No signatures yet. Add one below.</p>';
    } else {
        $i = 0;
        foreach ($signatures as $sig) {
            echo '<div class="signature-item" style="margin-bottom:16px;padding:12px;background:var(--bg-main);border:1px solid var(--border);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <strong>'.htmlspecialchars($sig['name']).'</strong>
                    <button type="button" class="btn-icon" onclick="deleteSignature('.$i.')" title="Delete"><i class="fa-sharp-duotone fa-thin fa-trash"></i></button>
                </div>
                <div style="font-size:13px;color:var(--text-secondary);white-space:pre-wrap;">'.htmlspecialchars($sig['content']).'</div>
            </div>';
            $i++;
        }
    }
    
    echo '</div>
        </div>
        
        <div class="form-group" style="margin-top:24px;padding-top:24px;border-top:1px solid var(--border);">
            <label>Add New Signature</label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <input type="text" id="sigName" placeholder="Signature name (e.g. Work, Personal)">
                <input type="text" id="sigShortcut" placeholder="Shortcut (e.g. /w, /p)">
            </div>
            <textarea id="sigContent" placeholder="Signature content..." style="margin-top:12px;min-height:100px;"></textarea>
            <button type="button" class="btn btn-secondary" onclick="addSignature()" style="margin-top:12px;">
                <i class="fa-sharp-duotone fa-thin fa-plus"></i> Add Signature
            </button>
        </div>
        
        <div class="compose-actions">
            <button type="button" class="btn btn-primary" onclick="saveSettings()">
                <i class="fa-sharp-duotone fa-thin fa-save"></i> Save Settings
            </button>
        </div>
    </div>
    
    <script>
    async function saveSettings() {
        const senderName = document.getElementById("senderName").value;
        const alertDiv = document.getElementById("settingsAlert");
        
        try {
            const formData = new FormData();
            formData.append("senderName", senderName);
            
            const response = await fetch("api.php?action=saveSettings", {
                method: "POST",
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                alertDiv.innerHTML = "<div class=\"alert alert-success\"><i class=\"fa-sharp-duotone fa-thin fa-circle-check\"></i> Settings saved!</div>";
                setTimeout(() => { alertDiv.innerHTML = ""; }, 3000);
            } else {
                alertDiv.innerHTML = "<div class=\"alert alert-error\"><i class=\"fa-sharp-duotone fa-thin fa-circle-exclamation\"></i> " + result.error + "</div>";
            }
        } catch (err) {
            alertDiv.innerHTML = "<div class=\"alert alert-error\">Error saving settings</div>";
        }
    }
    
    async function addSignature() {
        const name = document.getElementById("sigName").value;
        const shortcut = document.getElementById("sigShortcut").value;
        const content = document.getElementById("sigContent").value;
        
        if (!name || !content) {
            alert("Please enter signature name and content");
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append("action", "addSignature");
            formData.append("name", name);
            formData.append("shortcut", shortcut);
            formData.append("content", content);
            
            const response = await fetch("api.php?action=addSignature", {
                method: "POST",
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                navigate("settings");
            }
        } catch (err) {
            alert("Error adding signature");
        }
    }
    
    async function deleteSignature(index) {
        if (!confirm("Delete this signature?")) return;
        
        try {
            const formData = new FormData();
            formData.append("index", index);
            
            const response = await fetch("api.php?action=deleteSignature", {
                method: "POST",
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                navigate("settings");
            }
        } catch (err) {
            alert("Error deleting signature");
        }
    }
    </script>';
}

if ($action === 'saveSettings') {
    header('Content-Type: application/json');
    
    $senderName = $_POST['senderName'] ?? '';
    $config = getUserConfig();
    $config['senderName'] = $senderName;
    
    saveUserConfig($config);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'addSignature') {
    header('Content-Type: application/json');
    
    $name = $_POST['name'] ?? '';
    $shortcut = $_POST['shortcut'] ?? '';
    $content = $_POST['content'] ?? '';
    
    if (!$name || !$content) {
        echo json_encode(['success' => false, 'error' => 'Name and content required']);
        exit;
    }
    
    $config = getUserConfig();
    $config['signatures'] = $config['signatures'] ?? [];
    $config['signatures'][] = [
        'name' => $name,
        'shortcut' => $shortcut,
        'content' => $content
    ];
    
    saveUserConfig($config);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'deleteSignature') {
    header('Content-Type: application/json');
    
    $index = (int)$_POST['index'] ?? -1;
    $config = getUserConfig();
    
    if (isset($config['signatures'][$index])) {
        array_splice($config['signatures'], $index, 1);
        saveUserConfig($config);
    }
    
    echo json_encode(['success' => true]);
    exit;
}