<?php
require_once __DIR__ . '/../api/config.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$config = loadConfig();
$appName = $config['app_name'] ?? 'Zenith Mail';
$csrfToken = csrfGenerate();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings -
        <?= sanitize($appName) ?>
    </title>
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
            font-family: -apple-system, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            height: 100vh;
            overflow: hidden;
        }

        .settings-container {
            height: 100vh;
            overflow-y: auto;
            padding: 16px;
        }

        .settings-card {
            background: var(--bg-secondary);
            border: 2px solid var(--border-dark);
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.1);
        }

        .settings-header {
            background: var(--accent);
            color: white;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 700;
        }

        .settings-section {
            padding: 14px 16px;
            border-bottom: 2px solid var(--border-color);
        }

        .section-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .form-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .form-group {
            flex: 1;
        }

        .form-label {
            display: block;
            font-size: 10px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 8px 10px;
            font-size: 12px;
            border: 1px solid var(--border-color);
            background: white;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--accent);
        }

        .test-btn {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            padding: 6px 12px;
            font-size: 11px;
            cursor: pointer;
            margin-top: 6px;
        }

        .settings-actions {
            padding: 12px 16px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn {
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: 2px solid var(--border-dark);
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
        }

        .toast-container {
            position: fixed;
            bottom: 16px;
            right: 16px;
            z-index: 2000;
        }

        .toast {
            background: var(--text-primary);
            color: white;
            padding: 10px 14px;
            font-size: 12px;
            margin-top: 6px;
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
    <div class="settings-container">
        <div class="settings-card">
            <div class="settings-header">Settings</div>

            <div class="settings-section">
                <div class="section-title">General</div>
                <div class="form-group">
                    <div class="form-label">App Name</div><input type="text" class="form-input" id="appName"
                        placeholder="Zenith Mail">
                </div>
            </div>

            <div class="settings-section">
                <div class="section-title">Admin Account</div>
                <div class="form-row">
                    <div class="form-group">
                        <div class="form-label">Username</div><input type="text" class="form-input" id="adminUser">
                    </div>
                    <div class="form-group">
                        <div class="form-label">Password (leave empty)</div><input type="password" class="form-input"
                            id="adminPass">
                    </div>
                </div>
            </div>

            <div class="settings-section">
                <div class="section-title">SMTP Server</div>
                <div class="form-row">
                    <div class="form-group">
                        <div class="form-label">Host</div><input type="text" class="form-input" id="smtpHost">
                    </div>
                    <div class="form-group" style="width:70px">
                        <div class="form-label">Port</div><input type="number" class="form-input" id="smtpPort">
                    </div>
                    <div class="form-group" style="width:80px">
                        <div class="form-label">Security</div><select class="form-select" id="smtpSecurity">
                            <option value="ssl">SSL</option>
                            <option value="tls">TLS</option>
                            <option value="">None</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <div class="form-label">Username</div><input type="text" class="form-input" id="smtpUser">
                    </div>
                    <div class="form-group">
                        <div class="form-label">Password (leave empty)</div><input type="password" class="form-input"
                            id="smtpPass">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <div class="form-label">From Email</div><input type="email" class="form-input"
                            id="smtpFromEmail">
                    </div>
                    <div class="form-group">
                        <div class="form-label">From Name</div><input type="text" class="form-input" id="smtpFromName">
                    </div>
                </div>
                <button class="test-btn" onclick="testSmtp()">Test SMTP</button>
            </div>

            <div class="settings-section">
                <div class="section-title">IMAP Server</div>
                <div class="form-row">
                    <div class="form-group">
                        <div class="form-label">Host</div><input type="text" class="form-input" id="imapHost">
                    </div>
                    <div class="form-group" style="width:70px">
                        <div class="form-label">Port</div><input type="number" class="form-input" id="imapPort">
                    </div>
                    <div class="form-group" style="width:80px">
                        <div class="form-label">Security</div><select class="form-select" id="imapSecurity">
                            <option value="ssl">SSL</option>
                            <option value="tls">TLS</option>
                            <option value="">None</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <div class="form-label">Username</div><input type="text" class="form-input" id="imapUser">
                    </div>
                    <div class="form-group">
                        <div class="form-label">Password (leave empty)</div><input type="password" class="form-input"
                            id="imapPass">
                    </div>
                </div>
                <button class="test-btn" onclick="testImap()">Test IMAP</button>
            </div>

            <div class="settings-actions">
                <button class="btn btn-primary" onclick="saveSettings()">Save Settings</button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        const CSRF_TOKEN = '<?= $csrfToken ?>';
        
        function showToast(msg, type='info') {
            const c = document.getElementById('toastContainer');
            const t = document.createElement('div');
            t.className = 'toast ' + type;
            t.textContent = msg;
            c.appendChild(t);
            setTimeout(() => t.remove(), 3000);
        }

        async function loadSettings() {
            try {
                const res = await fetch('../api/settings.php?action=get', { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } });
                const d = await res.json();
                if (d.error) return;
                document.getElementById('appName').value = d.app_name || '';
                document.getElementById('adminUser').value = d.admin_user || '';
                document.getElementById('smtpHost').value = d.smtp?.host || '';
                document.getElementById('smtpPort').value = d.smtp?.port || 465;
                document.getElementById('smtpSecurity').value = d.smtp?.security || 'ssl';
                document.getElementById('smtpUser').value = d.smtp?.user || '';
                document.getElementById('smtpFromEmail').value = d.smtp?.from_email || '';
                document.getElementById('smtpFromName').value = d.smtp?.from_name || '';
                document.getElementById('imapHost').value = d.imap?.host || '';
                document.getElementById('imapPort').value = d.imap?.port || 993;
                document.getElementById('imapSecurity').value = d.imap?.security || 'ssl';
                document.getElementById('imapUser').value = d.imap?.user || '';
            } catch (e) { showToast('Failed to load settings', 'error'); }
        }

        async function saveSettings() {
            const data = { csrf_token: CSRF_TOKEN,
                app_name: document.getElementById('appName').value,
                admin_user: document.getElementById('adminUser').value,
                admin_pass: document.getElementById('adminPass').value,
                smtp_host: document.getElementById('smtpHost').value,
                smtp_port: document.getElementById('smtpPort').value,
                smtp_security: document.getElementById('smtpSecurity').value,
                smtp_user: document.getElementBy                    Id('smtpUser').value,
                smtp_pass: document.getElementById('smtpPass').value,
                smtp_from_email: document.getElementById('smtpFromEmail').value,
                smtp_from_name: document.getElementById('smtpFromName').value,
                imap_host: document.getElementById('imapHost').value,
                imap_port: document.getElementById('imapPort').value,
                imap_security: document.getElementById('imapSecurity').value,
                imap_user: document.getElementById('imapUser').value,
                imap_pass: document.getElementById('imapPass').value
            };
            try {
                const res = await fetch('../api/settings.php?action=save', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN }, body: JSON.stringify(data) });
                const r = await res.json();
                if (r.success) showToast('Settings saved!', 'success');
                else throw new Error(r.error);
            } catch (e) { showToast(e.message, 'error'); }
        }

        async function testSmtp() {
            const btn = document.querySelectorAll('.test-btn')[0];
            btn.textContent = 'Testing...';
            try {
                const h = document.getElementById('smtpHost').value;
                const p = document.getElementById('smtpPort').value;
                const s = document.getElementById('smtpSecurity').value;
                const u = document.getElementById('smtpUser').value;
                const w = document.getElementById('smtpPass').value;
                const res = await fetch(`../api/settings.php?action=test_smtp&host=${h}&port=${p}&security=${s}&user=${u}&pass=${w}`, { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } });
                const d = await res.json();
                showToast(d.success ? 'SMTP OK!' : 'SMTP failed: ' + d.error, d.success ? 'success' : 'error');
            } catch (e) { showToast('Test failed', 'error'); }
            btn.textContent = 'Test SMTP';
        }

        async function testImap() {
            const btn = document.querySelectorAll('.test-btn')[1];
            btn.textContent = 'Testing...';
            try {
                const h = document.getElementById('imapHost').value;
                const p = document.getElementById('imapPort').value;
                const s = document.getElementById('imapSecurity').value;
                const u = document.getElementById('imapUser').value;
                const w = document.getElementById('imapPass').value;
                const res = await fetch(`../api/settings.php?action=test_imap&host=${h}&port=${p}&security=${s}&user=${u}&pass=${w}`, { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } });
                const d = await res.json();
                showToast(d.success ? 'IMAP OK!' : 'IMAP failed: ' + d.error, d.success ? 'success' : 'error');
            } catch (e) { showToast('Test failed', 'error'); }
            btn.textContent = 'Test IMAP';
        }

        loadSettings();
    </script>
</body>
</html>