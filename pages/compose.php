<?php
require_once __DIR__ . '/../api/config.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$config = loadConfig();
$appName = $config['app_name'] ?? 'Zenith Mail';
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
    <title>Compose - <?= sanitize($appName) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-primary: #f0ebe0;
            --bg-secondary: #faf6ed;
            --bg-tertiary: #e5dccb;
            --border-color: #d4c4a8;
            --border-dark: #8b7355;
            --text-primary: #4a3f35;
            --text-secondary: #6a5a4a;
            --text-muted: #8a7a6a;
            --accent: #e87b35;
            --accent-hover: #d66a2a;
            --success: #4a4;
            --danger: #c44;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            height: 100vh;
            overflow: hidden;
        }

        .compose-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            padding: 16px;
            gap: 12px;
        }

        .form-row {
            display: flex;
            gap: 12px;
        }

        .form-group {
            flex: 1;
        }

        .form-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .form-input {
            width: 100%;
            padding: 10px 12px;
            font-size: 13px;
            border: 1px solid var(--border-color);
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent);
        }

        .editor-container {
            flex: 1;
            border: 1px solid var(--border-color);
            background: white;
            display: flex;
            flex-direction: column;
            min-height: 200px;
        }

        .editor-toolbar {
            background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-color);
            padding: 6px 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
        }

        .toolbar-btn {
            background: white;
            border: 1px solid var(--border-color);
            padding: 4px 8px;
            font-size: 12px;
            cursor: pointer;
        }

        .toolbar-btn:hover {
            background: var(--bg-secondary);
        }

        .editor-content {
            flex: 1;
            padding: 12px;
            font-size: 14px;
            line-height: 1.5;
            outline: none;
            overflow-y: auto;
        }

        .link-box {
            padding: 8px;
            background: var(--bg-tertiary);
            border-top: 1px solid var(--border-color);
            display: none;
            gap: 8px;
        }

        .link-box.show {
            display: flex;
        }

        .link-box input {
            flex: 1;
            padding: 6px;
            border: 1px solid var(--border-color);
        }

        .actions {
            display: flex;
            gap: 10px;
            padding-top: 8px;
        }

        .btn {
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 2px solid var(--border-dark);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn svg {
            width: 14px;
            height: 14px;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 2000;
        }

        .toast {
            background: var(--text-primary);
            color: white;
            padding: 12px 16px;
            font-size: 13px;
            margin-top: 8px;
            box-shadow: 2px 2px 0 rgba(0, 0, 0, 0.2);
        }

        .toast.success {
            background: var(--success);
        }

        .toast.error {
            background: var(--danger);
        }
    </style>
</head>

<body>
    <div class="compose-container">
        <input type="hidden" id="csrfToken" value="<?= $csrfToken ?>">
        <input type="hidden" id="replyToId" value="<?= sanitize($reply_id) ?>">
        <input type="hidden" id="replyToEmail" value="<?= sanitize($reply_to) ?>">

        <div class="form-row">
            <div class="form-group" style="flex: 1;">
                <div class="form-label">To</div>
                <input type="email" class="form-input" id="composeTo" placeholder="recipient@example.com">
            </div>
            <div class="form-group" style="width: 80px;">
                <div class="form-label">Type</div>
                <select class="form-input" id="recipientType">
                    <option value="to">To</option>
                    <option value="cc">CC</option>
                    <option value="bcc">BCC</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <div class="form-label">CC</div>
                <input type="email" class="form-input" id="composeCc">
            </div>
            <div class="form-group">
                <div class="form-label">BCC</div>
                <input type="email" class="form-input" id="composeBcc">
            </div>
        </div>

        <div class="form-group">
            <div class="form-label">Subject</div>
            <input type="text" class="form-input" id="composeSubject"
                value="<?= $is_forward ? 'Fwd: ' . sanitize($reply_subject) : ($reply_id ? 'Re: ' . sanitize($reply_subject) : '') ?>">
        </div>

        <div class="editor-container">
            <div class="editor-toolbar">
                <button class="toolbar-btn" onclick="formatDoc('bold')"><b>B</b></button>
                <button class="toolbar-btn" onclick="formatDoc('italic')"><i>I</i></button>
                <button class="toolbar-btn" onclick="formatDoc('underline')"><u>U</u></button>
                <button class="toolbar-btn" onclick="formatDoc('strikeThrough')"><s>S</s></button>
                <span style="width:1px;background:var(--border-color);margin:0 4px"></span>
                <button class="toolbar-btn" onclick="formatDoc('insertOrderedList')">1.</button>
                <button class="toolbar-btn" onclick="formatDoc('insertUnorderedList')">•</button>
                <span style="width:1px;background:var(--border-color);margin:0 4px"></span>
                <button class="toolbar-btn" onclick="showLinkInput()">Link</button>
                <button class="toolbar-btn" onclick="formatDoc('formatBlock','h1')">H1</button>
                <button class="toolbar-btn" onclick="formatDoc('formatBlock','h2')">H2</button>
                <button class="toolbar-btn" onclick="formatDoc('formatBlock','p')">P</button>
            </div>
            <div class="editor-content" id="composeEditor" contenteditable="true"></div>
            <div class="link-box" id="linkBox">
                <input type="text" id="linkUrl" placeholder="Enter URL">
                <button class="toolbar-btn" onclick="insertLink()">Add</button>
                <button class="toolbar-btn" onclick="hideLinkInput()">X</button>
            </div>
        </div>

        <div class="actions">
            <button class="btn btn-primary" onclick="sendEmail()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="22" y1="2" x2="11" y2="13" />
                    <polygon points="22 2 15 22 11 13 2 9 22 2" />
                </svg>
                Send
            </button>
            <button class="btn btn-secondary" onclick="saveDraft()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                </svg>
                Save Draft
            </button>
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
            setTimeout(() => toast.remove(), 3000);
        }

        function formatDoc(cmd, val = null) {
            document.execCommand(cmd, false, val);
            document.getElementById('composeEditor').focus();
        }

        function showLinkInput() {
            document.getElementById('linkBox').classList.add('show');
            document.getElementById('linkUrl').focus();
        }

        function hideLinkInput() {
            document.getElementById('linkBox').classList.remove('show');
            document.getElementById('linkUrl').value = '';
        }

        function insertLink() {
            const url = document.getElementById('linkUrl').value;
            if (url) formatDoc('createLink', url);
            hideLinkInput();
        }

        async function sendEmail() {
            const to = document.getElementById('composeTo').value.trim();
            const cc = document.getElementById('composeCc').value.trim();
            const bcc = document.getElementById('composeBcc').value.trim();
            const subject = document.getElementById('composeSubject').value.trim();
            const body = document.getElementById('composeEditor').innerHTML;
            const replyTo = document.getElementById('replyToEmail').value;
            const replyId = document.getElementById('replyToId').value;

            if (!to) { showToast('Please enter a recipient', 'error'); return; }
            if (!subject) { showToast('Please enter a subject', 'error'); return; }

            try {
                const res = await fetch('../api/mail.php?action=send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: JSON.stringify({ csrf_token: CSRF_TOKEN, to, cc, bcc, subject, body, reply_to: replyTo, reply_id: replyId })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Email sent!', 'success');
                    parent.navigateTo('sent');
                } else {
                    throw new Error(data.error);
                }
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        async function saveDraft() {
            const to = document.getElementById('composeTo').value.trim();
            const cc = document.getElementById('composeCc').value.trim();
            const bcc = document.getElementById('composeBcc').value.trim();
            const subject = document.getElementById('composeSubject').value.trim();
            const body = document.getElementById('composeEditor').innerHTML;

            try {
                const res = await fetch('../api/mail.php?action=draft', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    body: JSON.stringify({ csrf_token: CSRF_TOKEN, to, cc, bcc, subject, body })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Draft saved', 'success');
                }
            } catch (err) {
                showToast(err.message, 'error');
            }
        }

        document.getElementById('composeEditor').addEventListener('keydown', function (e) {
            if (e.ctrlKey && e.key === 'Enter') sendEmail();
        });
    </script>
</body>

</html>