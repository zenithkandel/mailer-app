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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — <?= sanitize($appName) ?></title>
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
            max-width: 800px;
            margin: 24px auto;
            padding: 0 24px;
        }

        .settings-card {
            background: var(--bg-secondary);
            border: 3px solid var(--border-dark);
            box-shadow: 6px 6px 0 rgba(0,0,0,0.15);
        }

        .settings-header {
            background: var(--accent);
            color: white;
            padding: 16px 24px;
            font-size: 18px;
            font-weight: 700;
        }

        .settings-section {
            padding: 24px;
            border-bottom: 2px solid var(--border-color);
        }

        .settings-section:last-child { border-bottom: none; }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-row {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group {
            flex: 1;
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 12px 16px;
            font-size: 14px;
            border: 2px solid var(--border-color);
            background: white;
            color: var(--text-primary);
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--accent);
        }

        .test-btn {
            background: var(--bg-tertiary);
            border: 2px solid var(--border-color);
            padding: 10px 16px;
            font-size: 13px;
            cursor: pointer;
            color: var(--text-secondary);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
        }

        .test-btn:hover { background: var(--border-color); color: var(--text-primary); }
        .test-btn:disabled { opacity: 0.6; cursor: not-allowed; }

        .test-btn svg { width: 16px; height: 16px; }

        .settings-actions {
            padding: 24px;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            border-top: 2px solid var(--border-color);
        }

        .btn {
            padding: 14px 28px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            border: 2px solid var(--border-dark);
            text-transform: uppercase;
        }

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

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .loading-overlay.show { display: flex; }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--bg-tertiary);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }
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
        <div class="settings-card">
            <div class="settings-header">Settings</div>
            
            <div class="settings-section">
                <div class="section-title">General</div>
                <div class="form-group">
                    <label class="form-label">App Name</label>
                    <input type="text" class="form-input" id="appName" placeholder="Zenith Mail">
                </div>
            </div>

            <div class="settings-section">
                <div class="section-title">Admin Account</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-input" id="adminUser" placeholder="admin">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password (leave empty to keep current)</label>
                        <input type="password" class="form-input" id="adminPass" placeholder="Password">
                    </div>
                </div>
            </div>

            <div class="settings-section">
                <div class="section-title">SMTP Server</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Host</label>
                        <input type="text" class="form-input" id="smtpHost" placeholder="mail.example.com">
                    </div>
                    <div class="form-group" style="width: 100px;">
                        <label class="form-label">Port</label>
                        <input type="number" class="form-input" id="smtpPort" placeholder="465">
                    </div>
                    <div class="form-group" style="width: 120px;">
                        <label class="form-label">Security</label>
                        <select class="form-select" id="smtpSecurity">
                            <option value="ssl">SSL</option>
                            <option value="tls">TLS</option>
                            <option value="">None</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-input" id="smtpUser" placeholder="user@example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password (leave empty to keep current)</label>
                        <input type="password" class="form-input" id="smtpPass" placeholder="Password">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Email</label>
                        <input type="email" class="form-input" id="smtpFromEmail" placeholder="user@example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">From Name</label>
                        <input type="text" class="form-input" id="smtpFromName" placeholder="My Name">
                    </div>
                </div>
                <button class="test-btn" id="testSmtpBtn" onclick="testSmtp()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                    </svg>
                    Test SMTP
                </button>
            </div>

            <div class="settings-section">
                <div class="section-title">IMAP Server</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Host</label>
                        <input type="text" class="form-input" id="imapHost" placeholder="mail.example.com">
                    </div>
                    <div class="form-group" style="width: 100px;">
                        <label class="form-label">Port</label>
                        <input type="number" class="form-input" id="imapPort" placeholder="993">
                    </div>
                    <div class="form-group" style="width: 120px;">
                        <label class="form-label">Security</label>
                        <select class="form-select" id="imapSecurity">
                            <option value="ssl">SSL</option>
                            <option value="tls">TLS</option>
                            <option value="">None</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-input" id="imapUser" placeholder="user@example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password (leave empty to keep current)</label>
                        <input type="password" class="form-input" id="imapPass" placeholder="Password">
                    </div>
                </div>
                <button class="test-btn" id="testImapBtn" onclick="testImap()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                    </svg>
                    Test IMAP
                </button>
            </div>

            <div class="settings-actions">
                <button class="btn btn-primary" id="saveBtn" onclick="saveSettings()">Save Settings</button>
            </div>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        const CSRF_TOKEN = '<?= $csrfToken ?>';

        function csrfHeaders() {
            return { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN };
        }

        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast ' + type;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        }

        function showLoading() { document.getElementById('loadingOverlay').classList.add('show'); }
        function hideLoading() { document.getElementById('loadingOverlay').classList.remove('show'); }

        async function loadSettings() {
            try {
                const res = await fetch('api/settings.php?action=get', { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } });
                const data = await res.json();

                if (data.error) {
                    showToast(data.error, 'error');
                    return;
                }

                document.getElementById('appName').value = data.app_name || '';
                document.getElementById('adminUser').value = data.admin_user || '';

                document.getElementById('smtpHost').value = data.smtp?.host || '';
                document.getElementById('smtpPort').value = data.smtp?.port || 465;
                document.getElementById('smtpSecurity').value = data.smtp?.security || 'ssl';
                document.getElementById('smtpUser').value = data.smtp?.user || '';
                document.getElementById('smtpFromEmail').value = data.smtp?.from_email || '';
                document.getElementById('smtpFromName').value = data.smtp?.from_name || '';

                document.getElementById('imapHost').value = data.imap?.host || '';
                document.getElementById('imapPort').value = data.imap?.port || 993;
                document.getElementById('imapSecurity').value = data.imap?.security || 'ssl';
                document.getElementById('imapUser').value = data.imap?.user || '';
            } catch (err) {
                showToast('Failed to load settings', 'error');
            }
        }

        async function saveSettings() {
            const data = {
                csrf_token: CSRF_TOKEN,
                app_name: document.getElementById('appName').value,
                admin_user: document.getElementById('adminUser').value,
                admin_pass: document.getElementById('adminPass').value,
                smtp_host: document.getElementById('smtpHost').value,
                smtp_port: document.getElementById('smtpPort').value,
                smtp_security: document.getElementById('smtpSecurity').value,
                smtp_user: document.getElementById('smtpUser').value,
                smtp_pass: document.getElementById('smtpPass').value,
                smtp_from_email: document.getElementById('smtpFromEmail').value,
                smtp_from_name: document.getElementById('smtpFromName').value,
                imap_host: document.getElementById('imapHost').value,
                imap_port: document.getElementById('imapPort').value,
                imap_security: document.getElementById('imapSecurity').value,
                imap_user: document.getElementById('imapUser').value,
                imap_pass: document.getElementById('imapPass').value
            };

            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.textContent = 'Saving...';

            try {
                const res = await fetch('api/settings.php?action=save', {
                    method: 'POST',
                    headers: csrfHeaders(),
                    body: JSON.stringify(data)
                });
                const result = await res.json();

                if (result.success) {
                    showToast('Settings saved successfully!', 'success');
                } else {
                    throw new Error(result.error);
                }
            } catch (err) {
                showToast(err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Save Settings';
            }
        }

        async function testSmtp() {
            const btn = document.getElementById('testSmtpBtn');
            btn.disabled = true;
            btn.textContent = 'Testing...';

            try {
                const host = document.getElementById('smtpHost').value;
                const port = document.getElementById('smtpPort').value;
                const security = document.getElementById('smtpSecurity').value;
                const user = document.getElementById('smtpUser').value;
                const pass = document.getElementById('smtpPass').value;

                const res = await fetch(`api/settings.php?action=test_smtp&host=${encodeURIComponent(host)}&port=${port}&security=${encodeURIComponent(security)}&user=${encodeURIComponent(user)}&pass=${encodeURIComponent(pass)}`, { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } });
                const data = await res.json();

                if (data.success) {
                    showToast('SMTP connection successful!', 'success');
                } else {
                    showToast('SMTP failed: ' + data.error, 'error');
                }
            } catch (err) {
                showToast('SMTP test failed', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Test SMTP';
            }
        }

        async function testImap() {
            const btn = document.getElementById('testImapBtn');
            btn.disabled = true;
            btn.textContent = 'Testing...';

            try {
                const host = document.getElementById('imapHost').value;
                const port = document.getElementById('imapPort').value;
                const security = document.getElementById('imapSecurity').value;
                const user = document.getElementById('imapUser').value;
                const pass = document.getElementById('imapPass').value;

                const res = await fetch(`api/settings.php?action=test_imap&host=${encodeURIComponent(host)}&port=${port}&security=${encodeURIComponent(security)}&user=${encodeURIComponent(user)}&pass=${encodeURIComponent(pass)}`, { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } });
                const data = await res.json();

                if (data.success) {
                    showToast('IMAP connection successful!', 'success');
                } else {
                    showToast('IMAP failed: ' + data.error, 'error');
                }
            } catch (err) {
                showToast('IMAP test failed', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Test IMAP';
            }
        }

        loadSettings();
    </script>
</body>
</html>