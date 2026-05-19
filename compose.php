<?php
require_once __DIR__ . '/api/config.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$config = loadConfig();
$appName = $config['app_name'] ?? 'Zenith Mail';
$accountEmail = $config['imap']['user'] ?? '';
$csrfToken = csrfGenerate();

$reply_to = $_GET['reply_to'] ?? '';
$reply_subject = $_GET['subject'] ?? '';
$reply_id = $_GET['id'] ?? '';
$is_forward = $_GET['forward'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose — <?= sanitize($appName) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --bg-primary: #e8dcc8;
            --bg-secondary: #f5f0e6;
            --bg-tertiary: #dccfb8;
            --border-color: #c9b896;
            --border-dark: #8b7355;
            --text-primary: #4a3f35;
            --text-secondary: #7a6b5a;
            --text-muted: #9a8a7a;
            --accent: #e87b35;
            --accent-hover: #d66a2a;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .header {
            background: var(--bg-secondary);
            border-bottom: 3px solid var(--border-dark);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .header a:hover { color: var(--accent); }

        .header svg { width: 18px; height: 18px; }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .brand-icon {
            width: 32px;
            height: 32px;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-icon svg { width: 18px; height: 18px; fill: white; }
        .brand-name { font-size: 16px; font-weight: 700; }

        .container {
            max-width: 900px;
            margin: 24px auto;
            padding: 0 24px;
        }

        .compose-card {
            background: var(--bg-secondary);
            border: 3px solid var(--border-dark);
            box-shadow: 6px 6px 0 rgba(0,0,0,0.15);
        }

        .compose-header {
            background: var(--accent);
            color: white;
            padding: 16px 24px;
            font-size: 18px;
            font-weight: 700;
        }

        .compose-form { padding: 24px; }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            font-size: 14px;
            border: 2px solid var(--border-color);
            background: white;
            color: var(--text-primary);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent);
        }

        .form-row {
            display: flex;
            gap: 16px;
        }

        .form-row .form-group { flex: 1; }

        .editor-container {
            border: 2px solid var(--border-color);
            background: white;
            min-height: 300px;
        }

        .editor-toolbar {
            background: var(--bg-tertiary);
            border-bottom: 2px solid var(--border-color);
            padding: 8px 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        .toolbar-btn {
            background: white;
            border: 1px solid var(--border-color);
            padding: 6px 10px;
            font-size: 14px;
            cursor: pointer;
            font-weight: 600;
        }

        .toolbar-btn:hover { background: var(--bg-secondary); }
        
        .toolbar-btn.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .toolbar-separator {
            width: 1px;
            background: var(--border-color);
            margin: 0 8px;
        }

        .editor-content {
            padding: 16px;
            min-height: 250px;
            font-size: 14px;
            line-height: 1.6;
            outline: none;
        }

        .editor-content:empty:before {
            content: 'Write your message here...';
            color: var(--text-muted);
        }

        .editor-content a { color: var(--accent); }
        .editor-content img { max-width: 100%; }

        .compose-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .btn {
            padding: 14px 28px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            border: 2px solid var(--border-dark);
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
        }

        .btn svg { width: 16px; height: 16px; }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover { background: var(--accent-hover); }
        
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .btn-secondary:hover { background: var(--border-color); }

        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 2000;
        }

        .toast {
            background: var(--text-primary);
            color: white;
            padding: 14px 20px;
            box-shadow: 4px 4px 0 rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
            margin-top: 8px;
        }

        .toast.success { background: #4a4; }
        .toast.error { background: #c44; }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .link-input-container {
            display: none;
            margin-top: 8px;
            padding: 8px;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
        }

        .link-input-container.show { display: block; }

        .link-input-container input {
            padding: 8px;
            border: 1px solid var(--border-color);
            width: 200px;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="dashboard.php">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
            </svg>
            Back to Inbox
        </a>
        
        <div class="brand">
            <div class="brand-icon">
                <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </div>
            <span class="brand-name"><?= sanitize($appName) ?></span>
        </div>
    </div>

    <div class="container">
        <div class="compose-card">
            <div class="compose-header">
                <?= $is_forward ? 'Forward Message' : ($reply_id ? 'Reply' : 'New Message') ?>
            </div>
            <div class="compose-form">
                <input type="hidden" id="csrfToken" value="<?= $csrfToken ?>">
                <input type="hidden" id="replyToId" value="<?= sanitize($reply_id) ?>">
                <input type="hidden" id="replyToEmail" value="<?= sanitize($reply_to) ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">To</label>
                        <input type="email" class="form-input" id="to" placeholder="recipient@example.com" required>
                    </div>
                    <div class="form-group" style="width: 100px;">
                        <label class="form-label">Type</label>
                        <select class="form-input" id="recipientType">
                            <option value="to">To</option>
                            <option value="cc">CC</option>
                            <option value="bcc">BCC</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">CC</label>
                        <input type="email" class="form-input" id="cc" placeholder="">
                    </div>
                    <div class="form-group">
                        <label class="form-label">BCC</label>
                        <input type="email" class="form-input" id="bcc" placeholder="">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Subject</label>
                    <input type="text" class="form-input" id="subject" placeholder="Subject" required 
                           value="<?= $is_forward ? 'Fwd: ' . sanitize($reply_subject) : ($reply_id ? 'Re: ' . sanitize($reply_subject) : '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Message</label>
                    <div class="editor-container">
                        <div class="editor-toolbar">
                            <button type="button" class="toolbar-btn" onclick="formatDoc('bold')" title="Bold"><b>B</b></button>
                            <button type="button" class="toolbar-btn" onclick="formatDoc('italic')" title="Italic"><i>I</i></button>
                            <button type="button" class="toolbar-btn" onclick="formatDoc('underline')" title="Underline"><u>U</u></button>
                            <button type="button" class="toolbar-btn" onclick="formatDoc('strikeThrough')" title="Strikethrough"><s>S</s></button>
                            <div class="toolbar-separator"></div>
                            <button type="button" class="toolbar-btn" onclick="formatDoc('justifyLeft')" title="Align Left">&#8676;</button>
                            <button type="button" class="toolbar-btn" onclick="formatDoc('justifyCenter')" title="Align Center">&#8596;</button>
                            <button type="button" class="toolbar-btn" onclick="formatDoc('justifyRight')" title="Align Right">&#8677;</button>
                            <div class="toolbar-separator"></div>
                            <button type="button" class="toolbar-btn" onclick="formatDoc('insertOrderedList')" title="Numbered List">1.</button>
                            <button type="button" class="toolbar-btn" onclick="formatDoc('insertUnorderedList')" title="Bullet List">&bull;</button>
                            <div class="toolbar-separator"></div>
                            <button type="button" class="toolbar-btn" onclick="showLinkInput()" title="Insert Link">&#128279;</button>
                            <button type="button" class="toolbar-btn" onclick="formatDoc('insertImage')" title="Insert Image">&#128247;</button>
                            <button type="button" class="toolbar-btn" onclick="formatDoc('createLink')" title=" hyperlink ">Link</button>
                            <div class="toolbar-separator"></div>
                            <button type="button" class="toolbar-btn" onclick="formatDoc('undo')" title="Undo">&#8630;</button>
                            <button type="button" class="toolbar-btn" onclick="formatDoc('redo')" title="Redo">&#8631;</button>
                            <div class="toolbar-separator"></div>
                            <button type="button" class="toolbar-btn" onclick="formatDoc('formatBlock', 'h1')" title="Heading 1">H1</button>
                            <button type="button" class="toolbar-btn" onclick="formatDoc('formatBlock', 'h2')" title="Heading 2">H2</button>
                            <button type="button" class="toolbar-btn" onclick="formatDoc('formatBlock', 'p')" title="Paragraph">P</button>
                        </div>
                        <div class="editor-content" id="editor" contenteditable="true"></div>
                        <div class="link-input-container" id="linkContainer">
                            <input type="text" id="linkUrl" placeholder="Enter URL">
                            <button type="button" class="toolbar-btn" onclick="insertLink()">Insert</button>
                            <button type="button" class="toolbar-btn" onclick="hideLinkInput()">Cancel</button>
                        </div>
                    </div>
                </div>

                <div class="compose-actions">
                    <button class="btn btn-primary" id="sendBtn" onclick="sendEmail()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                        Send
                    </button>
                    <button class="btn btn-secondary" onclick="saveDraft()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                        </svg>
                        Save Draft
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        const CSRF_TOKEN = document.getElementById('csrfToken').value;

        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast ' + type;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        }

        function formatDoc(cmd, value = null) {
            if (value) {
                document.execCommand(cmd, false, value);
            } else {
                document.execCommand(cmd, false, null);
            }
            document.getElementById('editor').focus();
        }

        function showLinkInput() {
            document.getElementById('linkContainer').classList.add('show');
            document.getElementById('linkUrl').focus();
        }

        function hideLinkInput() {
            document.getElementById('linkContainer').classList.remove('show');
            document.getElementById('linkUrl').value = '';
        }

        function insertLink() {
            const url = document.getElementById('linkUrl').value;
            if (url) {
                formatDoc('createLink', url);
            }
            hideLinkInput();
        }

        async function sendEmail() {
            const to = document.getElementById('to').value.trim();
            const cc = document.getElementById('cc').value.trim();
            const bcc = document.getElementById('bcc').value.trim();
            const subject = document.getElementById('subject').value.trim();
            const body = document.getElementById('editor').innerHTML;
            const replyTo = document.getElementById('replyToEmail').value;
            const replyId = document.getElementById('replyToId').value;

            if (!to) { showToast('Please enter a recipient', 'error'); return; }
            if (!subject) { showToast('Please enter a subject', 'error'); return; }

            const btn = document.getElementById('sendBtn');
            btn.disabled = true;
            btn.textContent = 'Sending...';

            try {
                const res = await fetch('api/mail.php?action=send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: JSON.stringify({
                        csrf_token: CSRF_TOKEN,
                        to, cc, bcc, subject, body,
                        reply_to: replyTo,
                        reply_id: replyId
                    })
                });
                const data = await res.json();

                if (data.success) {
                    showToast('Email sent successfully!', 'success');
                    setTimeout(() => window.location.href = 'dashboard.php?view=sent', 1500);
                } else {
                    throw new Error(data.error);
                }
            } catch (err) {
                showToast(err.message, 'error');
                btn.disabled = false;
                btn.textContent = 'Send';
            }
        }

        async function saveDraft() {
            const to = document.getElementById('to').value.trim();
            const cc = document.getElementById('cc').value.trim();
            const bcc = document.getElementById('bcc').value.trim();
            const subject = document.getElementById('subject').value.trim();
            const body = document.getElementById('editor').innerHTML;

            if (!to && !subject && !body) {
                showToast('Nothing to save', 'info');
                return;
            }

            try {
                const res = await fetch('api/mail.php?action=draft', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: JSON.stringify({ csrf_token: CSRF_TOKEN, to, cc, bcc, subject, body })
                });
                const data = await res.json();

                if (data.success) {
                    showToast('Draft saved', 'success');
                } else {
                    throw new Error(data.error);
                }
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        // Add keyboard shortcuts
        document.getElementById('editor').addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                sendEmail();
            }
        });
    </script>
</body>
</html>