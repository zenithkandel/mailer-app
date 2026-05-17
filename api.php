<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/imap.php';
require_once __DIR__ . '/smtp.php';

$action = $_GET['action'] ?? '';

if (!isLoggedIn()) {
    echo '<div class="alert alert-error">Session expired. <a href="index.php">Login again</a></div>';
    exit;
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');

if ($action === 'inbox') {
    $unreadCount = getUnreadCount();
    $emails = fetchEmails('INBOX', 50);
    
    echo '<span class="unread-count" style="display:none">' . $unreadCount . '</span>';
    
    echo '<div class="page-header">
        <h2 class="page-title"><i class="fa-sharp-duotone fa-thin fa-inbox"></i> Inbox</h2>
        <div class="page-actions">
            <button class="btn-icon" onclick="loadPage(\'inbox\')" title="Refresh">
                <i class="fa-sharp-duotone fa-thin fa-rotate-right"></i>
            </button>
        </div>
    </div>';
    
    if (empty($emails)) {
        echo '<div class="empty-state">
            <i class="fa-sharp-duotone fa-thin fa-envelope-open"></i>
            <p>No emails in your inbox</p>
        </div>';
    } else {
        echo '<div class="email-list">';
        foreach ($emails as $email) {
            $initials = getInitials($email['from_name']);
            $unreadClass = $email['read'] ? '' : 'unread';
            $unreadDot = $email['read'] ? '' : '<span class="unread-dot"></span>';
            $date = date('Y-m-d H:i:s', $email['date']);
            
            echo '<a href="#" class="email-item ' . $unreadClass . '" onclick="event.preventDefault(); openEmail(' . $email['uid'] . ')">
                <div class="email-avatar">' . $initials . '</div>
                <div class="email-content">
                    <span class="email-sender">' . $unreadDot . htmlspecialchars($email['from_name']) . '</span>
                    <span class="email-subject">' . htmlspecialchars($email['subject']) . '</span>
                </div>
                <div class="email-meta">
                    <span class="email-date" data-date="' . $date . '"></span>
                </div>
            </a>';
        }
        echo '</div>';
    }
    
    echo '<script>
        document.querySelectorAll(".email-date").forEach(function(el) {
            var date = new Date(el.dataset.date);
            var now = new Date();
            var diff = now - date;
            var days = Math.floor(diff / (1000 * 60 * 60 * 24));
            if (days === 0) el.textContent = date.toLocaleTimeString([], {hour: "2-digit", minute: "2-digit"});
            else if (days === 1) el.textContent = "Yesterday";
            else if (days < 7) el.textContent = date.toLocaleDateString([], {weekday: "short"});
            else el.textContent = date.toLocaleDateString([], {month: "short", day: "numeric"});
        });
        
        function openEmail(uid) {
            loadPage("read", { uid: uid, from: "inbox" });
        }
    </script>';
    exit;
}

if ($action === 'read') {
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
    
    $initials = getInitials($email['from_name']);
    $date = date('Y-m-d H:i:s', $email['date']);
    
    echo '<div class="page-header">
        <h2 class="page-title"><i class="fa-sharp-duotone fa-thin fa-envelope-open"></i> Read Email</h2>
        <div class="page-actions">
            <button class="btn-icon" onclick="loadPage(\'' . htmlspecialchars($from) . '\')" title="Back">
                <i class="fa-sharp-duotone fa-thin fa-arrow-left"></i>
            </button>
            <button class="btn-icon" onclick="replyToEmail()" title="Reply">
                <i class="fa-sharp-duotone fa-thin fa-reply"></i>
            </button>
            <button class="btn-icon" onclick="deleteEmail(' . $uid . ', \'' . htmlspecialchars($from) . '\')" title="Delete">
                <i class="fa-sharp-duotone fa-thin fa-trash"></i>
            </button>
        </div>
    </div>';
    
    echo '<div class="email-view">
        <div class="email-header">
            <div class="email-avatar">' . $initials . '</div>
            <div class="email-header-content">
                <div class="email-header-from">
                    <strong>' . htmlspecialchars($email['from_name']) . '</strong>
                    <span class="email-header-email">&lt;' . htmlspecialchars($email['from_email']) . '&gt;</span>
                </div>
                <div class="email-header-subject">' . htmlspecialchars($email['subject']) . '</div>
                <div class="email-header-date" data-date="' . $date . '"></div>
            </div>
        </div>
        <div class="email-body">' . $email['body'] . '</div>';
    
    if (!empty($email['attachments'])) {
        echo '<div class="email-attachments">
            <h4><i class="fa-sharp-duotone fa-thin fa-paperclip"></i> Attachments</h4>
            <div class="attachment-list">';
        foreach ($email['attachments'] as $att) {
            echo '<span class="attachment-item">
                <i class="fa-sharp-duotone fa-thin fa-file"></i>
                ' . htmlspecialchars($att['name']) . '
            </span>';
        }
        echo '</div></div>';
    }
    
    echo '</div>';
    
    echo '<script>
        var el = document.querySelector(".email-header-date");
        if (el) {
            var date = new Date(el.dataset.date);
            el.textContent = date.toLocaleString();
        }
        
        function replyToEmail() {
            loadPage("compose", { replyto: "' . urlencode($email['from_email']) . '", subject: "Re: ' . urlencode($email['subject']) . '" });
        }
        
        function deleteEmail(uid, from) {
            if (!confirm("Delete this email?")) return;
            fetch("api.php?action=delete&uid=" + uid).then(function() {
                loadPage(from);
            });
        }
    </script>';
    exit;
}

if ($action === 'compose') {
    $replyto = isset($_GET['replyto']) ? $_GET['replyto'] : '';
    $subject = isset($_GET['subject']) ? $_GET['subject'] : '';
    
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
                <input type="email" id="to" name="to" required value="<?php echo htmlspecialchars($replyto); ?>" placeholder="recipient@example.com">
            </div>
            <div class="form-group">
                <label for="subject"><i class="fa-sharp-duotone fa-thin fa-heading"></i> Subject</label>
                <input type="text" id="subject" name="subject" required value="<?php echo htmlspecialchars($subject); ?>" placeholder="Email subject">
            </div>
    <?php if (!empty($signatures)): ?>
            <div class="form-group">
                <label for="signature"><i class="fa-sharp-duotone fa-thin fa-signature"></i> Signature</label>
                <select id="signature" onchange="applySignature()">
                    <option value="">-- No signature --</option>
            <?php foreach ($signatures as $sig): ?>
                    <option value="<?php echo htmlspecialchars($sig['content']); ?>"><?php echo htmlspecialchars($sig['name']); ?></option>
            <?php endforeach; ?>
                </select>
            </div>
    <?php endif; ?>
            <div class="form-group">
                <label for="body"><i class="fa-sharp-duotone fa-thin fa-align-left"></i> Message</label>
                <textarea id="body" name="body" required placeholder="Write your message..." style="min-height: 300px;"></textarea>
            </div>
            <div class="compose-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-sharp-duotone fa-thin fa-paper-plane"></i> Send
                </button>
            </div>
        </div>
    </form>
    
    <div id="progress" class="progress-overlay" style="display: none;">
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
    <?php
    exit;
}

if ($action === 'sent') {
    $emails = fetchEmails('INBOX.Sent', 50);
    
    echo '<div class="page-header">
        <h2 class="page-title"><i class="fa-sharp-duotone fa-thin fa-paper-plane"></i> Sent</h2>
        <div class="page-actions">
            <button class="btn-icon" onclick="loadPage(\'sent\')" title="Refresh">
                <i class="fa-sharp-duotone fa-thin fa-rotate-right"></i>
            </button>
        </div>
    </div>';
    
    if (empty($emails)) {
        echo '<div class="empty-state">
            <i class="fa-sharp-duotone fa-thin fa-paper-plane"></i>
            <p>No sent emails</p>
        </div>';
    } else {
        echo '<div class="email-list">';
        foreach ($emails as $email) {
            $initials = getInitials($email['from_name']);
            $date = date('Y-m-d H:i:s', $email['date']);
            
            echo '<a href="#" class="email-item" onclick="event.preventDefault(); openEmail(' . $email['uid'] . ')">
                <div class="email-avatar">' . $initials . '</div>
                <div class="email-content">
                    <span class="email-sender">' . htmlspecialchars($email['from_name']) . '</span>
                    <span class="email-subject">' . htmlspecialchars($email['subject']) . '</span>
                </div>
                <div class="email-meta">
                    <span class="email-date" data-date="' . $date . '"></span>
                </div>
            </a>';
        }
        echo '</div>';
    }
    
    echo '<script>
        document.querySelectorAll(".email-date").forEach(function(el) {
            var date = new Date(el.dataset.date);
            var now = new Date();
            var diff = now - date;
            var days = Math.floor(diff / (1000 * 60 * 60 * 24));
            if (days === 0) el.textContent = date.toLocaleTimeString([], {hour: "2-digit", minute: "2-digit"});
            else if (days === 1) el.textContent = "Yesterday";
            else if (days < 7) el.textContent = date.toLocaleDateString([], {weekday: "short"});
            else el.textContent = date.toLocaleDateString([], {month: "short", day: "numeric"});
        });
        
        function openEmail(uid) {
            loadPage("read", { uid: uid, from: "sent" });
        }
        
        function getInitials(name) {
            var parts = name.trim().split(" ");
            var initials = "";
            for (var i = 0; i < parts.length && initials.length < 2; i++) {
                initials += parts[i].charAt(0).toUpperCase();
            }
            return initials || "?";
        }
    </script>';
    exit;
}

if ($action === 'search') {
    $query = isset($_GET['q']) ? $_GET['q'] : '';
    $results = $query ? searchEmails($query) : [];
    
    echo '<div class="page-header">
        <h2 class="page-title"><i class="fa-sharp-duotone fa-thin fa-magnifying-glass"></i> Search</h2>
    </div>';
    
    echo '<div class="search-form" style="margin-bottom: 20px;">
        <form onsubmit="event.preventDefault(); performSearch();">
            <input type="text" id="searchQuery" placeholder="Search emails..." value="' . htmlspecialchars($query) . '" style="width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: 4px;">
        </form>
    </div>';
    
    if (!$query) {
        echo '<div class="empty-state">
            <i class="fa-sharp-duotone fa-thin fa-magnifying-glass"></i>
            <p>Enter a search term above</p>
        </div>';
    } elseif (empty($results)) {
        echo '<div class="empty-state">
            <i class="fa-sharp-duotone fa-thin fa-folder-open"></i>
            <p>No results found</p>
        </div>';
    } else {
        echo '<div class="email-list">';
        foreach ($results as $email) {
            $initials = getInitials($email['from_name']);
            $unreadClass = $email['read'] ? '' : 'unread';
            $unreadDot = $email['read'] ? '' : '<span class="unread-dot"></span>';
            $date = date('Y-m-d H:i:s', $email['date']);
            
            echo '<a href="#" class="email-item ' . $unreadClass . '" onclick="event.preventDefault(); openEmail(' . $email['uid'] . ')">
                <div class="email-avatar">' . $initials . '</div>
                <div class="email-content">
                    <span class="email-sender">' . $unreadDot . htmlspecialchars($email['from_name']) . '</span>
                    <span class="email-subject">' . htmlspecialchars($email['subject']) . '</span>
                </div>
                <div class="email-meta">
                    <span class="email-date" data-date="' . $date . '"></span>
                </div>
            </a>';
        }
        echo '</div>';
    }
    
    echo '<script>
        document.querySelectorAll(".email-date").forEach(function(el) {
            var date = new Date(el.dataset.date);
            var now = new Date();
            var diff = now - date;
            var days = Math.floor(diff / (1000 * 60 * 60 * 24));
            if (days === 0) el.textContent = date.toLocaleTimeString([], {hour: "2-digit", minute: "2-digit"});
            else if (days === 1) el.textContent = "Yesterday";
            else if (days < 7) el.textContent = date.toLocaleDateString([], {weekday: "short"});
            else el.textContent = date.toLocaleDateString([], {month: "short", day: "numeric"});
        });
        
        function performSearch() {
            var q = document.getElementById("searchQuery").value;
            loadPage("search", { q: q });
        }
        
        function openEmail(uid) {
            loadPage("read", { uid: uid, from: "search&q=' . urlencode($query) . '" });
        }
        
        function getInitials(name) {
            var parts = name.trim().split(" ");
            var initials = "";
            for (var i = 0; i < parts.length && initials.length < 2; i++) {
                initials += parts[i].charAt(0).toUpperCase();
            }
            return initials || "?";
        }
    </script>';
    exit;
}

if ($action === 'settings') {
    $config = getUserConfig();
    $signatures = $config['signatures'] ?? [];
    
    echo '<div class="page-header">
        <h2 class="page-title"><i class="fa-sharp-duotone fa-thin fa-gear"></i> Settings</h2>
    </div>';
    
    echo '<div id="settingsAlert"></div>';
    
    echo '<div class="compose-form">
        <div class="form-group">
            <label for="senderName"><i class="fa-sharp-duotone fa-thin fa-user"></i> Display Name</label>
            <input type="text" id="senderName" placeholder="Your name as recipients see it" value="' . htmlspecialchars($config['senderName'] ?? '') . '">
            <small style="color: var(--text-muted); display: block; margin-top: 4px;">This name will appear in the "From" field for recipients</small>
        </div>
        
        <div class="form-group">
            <label><i class="fa-sharp-duotone fa-thin fa-signature"></i> Signatures</label>
            <div id="signatureList">';
    
    if (empty($signatures)) {
        echo '<p style="color: var(--text-muted); font-size: 13px;">No signatures yet. Add one below.</p>';
    } else {
        $i = 0;
        foreach ($signatures as $sig) {
            echo '<div class="signature-item" style="margin-bottom: 16px; padding: 12px; background: var(--bg-main); border: 1px solid var(--border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <strong>' . htmlspecialchars($sig['name']) . '</strong>
                    <button type="button" class="btn-icon" onclick="deleteSignature(' . $i . ')" title="Delete">
                        <i class="fa-sharp-duotone fa-thin fa-trash"></i>
                    </button>
                </div>
                <div style="font-size: 13px; color: var(--text-secondary); white-space: pre-wrap;">' . htmlspecialchars($sig['content']) . '</div>
            </div>';
            $i++;
        }
    }
    
    echo '</div></div>
        
        <div class="form-group" style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border);">
            <label>Add New Signature</label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" id="sigName" placeholder="Signature name (e.g. Work, Personal)">
                <input type="text" id="sigShortcut" placeholder="Shortcut (e.g. /w, /p)">
            </div>
            <textarea id="sigContent" placeholder="Signature content..." style="margin-top: 12px; min-height: 100px;"></textarea>
            <button type="button" class="btn btn-secondary" onclick="addSignature()" style="margin-top: 12px;">
                <i class="fa-sharp-duotone fa-thin fa-plus"></i> Add Signature
            </button>
        </div>
        
        <div class="compose-actions">
            <button type="button" class="btn btn-primary" onclick="saveSettings()">
                <i class="fa-sharp-duotone fa-thin fa-save"></i> Save Settings
            </button>
        </div>
    </div>';
    
    echo '<script>
    async function saveSettings() {
        var senderName = document.getElementById("senderName").value;
        var alertDiv = document.getElementById("settingsAlert");
        
        try {
            var formData = new FormData();
            formData.append("senderName", senderName);
            
            var response = await fetch("api.php?action=saveSettings", {
                method: "POST",
                body: formData
            });
            var result = await response.json();
            
            if (result.success) {
                alertDiv.innerHTML = \'<div class="alert alert-success"><i class="fa-sharp-duotone fa-thin fa-circle-check"></i> Settings saved!</div>\';
                setTimeout(function() { alertDiv.innerHTML = ""; }, 3000);
            } else {
                alertDiv.innerHTML = \'<div class="alert alert-error"><i class="fa-sharp-duotone fa-thin fa-circle-exclamation"></i> Failed to save</div>\';
            }
        } catch (err) {
            alertDiv.innerHTML = \'<div class="alert alert-error">Error saving settings</div>\';
        }
    }
    
    async function addSignature() {
        var name = document.getElementById("sigName").value;
        var shortcut = document.getElementById("sigShortcut").value;
        var content = document.getElementById("sigContent").value;
        
        if (!name || !content) {
            alert("Please provide name and content");
            return;
        }
        
        try {
            var formData = new FormData();
            formData.append("name", name);
            formData.append("shortcut", shortcut);
            formData.append("content", content);
            
            var response = await fetch("api.php?action=addSignature", {
                method: "POST",
                body: formData
            });
            var result = await response.json();
            
            if (result.success) {
                loadPage("settings");
            } else {
                alert(result.error || "Error adding signature");
            }
        } catch (err) {
            alert("Error: " + err.message);
        }
    }
    
    async function deleteSignature(index) {
        if (!confirm("Delete this signature?")) return;
        
        try {
            var response = await fetch("api.php?action=deleteSignature&index=" + index);
            var result = await response.json();
            
            if (result.success) {
                loadPage("settings");
            } else {
                alert(result.error || "Error deleting signature");
            }
        } catch (err) {
            alert("Error: " + err.message);
        }
    }
    </script>';
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

if ($action === 'send') {
    header('Content-Type: application/json');
    $to = $_POST['to'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $body = $_POST['body'] ?? '';
    
    if (!$to || !$subject || !$body) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    $result = sendEmail($to, $subject, $body);
    echo json_encode($result);
    exit;
}

if ($action === 'delete') {
    header('Content-Type: application/json');
    $uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
    if (!$uid) {
        echo json_encode(['success' => false, 'error' => 'Invalid email']);
        exit;
    }
    
    $success = deleteEmail($uid);
    echo json_encode(['success' => $success]);
    exit;
}

if ($action === 'saveSettings') {
    header('Content-Type: application/json');
    $senderName = $_POST['senderName'] ?? '';
    
    $config = getUserConfig();
    $config['senderName'] = $senderName;
    
    $success = saveUserConfig($config);
    echo json_encode(['success' => $success]);
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
    $signatures = $config['signatures'] ?? [];
    $signatures[] = ['name' => $name, 'shortcut' => $shortcut, 'content' => $content];
    $config['signatures'] = $signatures;
    
    $success = saveUserConfig($config);
    echo json_encode(['success' => $success]);
    exit;
}

if ($action === 'deleteSignature') {
    header('Content-Type: application/json');
    $index = isset($_GET['index']) ? intval($_GET['index']) : -1;
    
    $config = getUserConfig();
    $signatures = $config['signatures'] ?? [];
    
    if ($index >= 0 && $index < count($signatures)) {
        array_splice($signatures, $index, 1);
        $config['signatures'] = $signatures;
        $success = saveUserConfig($config);
        echo json_encode(['success' => $success]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid index']);
    }
    exit;
}

echo '<div class="alert alert-error">Unknown action</div>';