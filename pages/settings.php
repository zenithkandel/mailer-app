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
    <title>Settings</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <div class="settings-container">
        <div class="settings-card">
            <div class="settings-header">
                <h2>Settings</h2>
            </div>

            <div class="settings-section">
                <div class="settings-section-title">General</div>
                <div class="settings-row single">
                    <div class="settings-field">
                        <label>App Name</label>
                        <input type="text" id="appName" placeholder="My Mail App">
                    </div>
                </div>
            </div>

            <div class="settings-section">
                <div class="settings-section-title">Admin Account</div>
                <div class="settings-row">
                    <div class="settings-field">
                        <label>Username</label>
                        <input type="text" id="adminUser">
                    </div>
                    <div class="settings-field">
                        <label>Password (leave empty to keep)</label>
                        <input type="password" id="adminPass">
                    </div>
                </div>
            </div>

            <div class="settings-section">
                <div class="settings-section-title">SMTP Server</div>
                <div class="settings-row">
                    <div class="settings-field">
                        <label>Host</label>
                        <input type="text" id="smtpHost">
                    </div>
                    <div class="settings-field">
                        <label>Port</label>
                        <input type="number" id="smtpPort">
                    </div>
                </div>
                <div class="settings-row">
                    <div class="settings-field">
                        <label>Security</label>
                        <select id="smtpSecurity">
                            <option value="ssl">SSL</option>
                            <option value="tls">TLS</option>
                            <option value="">None</option>
                        </select>
                    </div>
                    <div class="settings-field">
                        <label>Username</label>
                        <input type="text" id="smtpUser">
                    </div>
                </div>
                <div class="settings-row">
                    <div class="settings-field">
                        <label>Password (leave empty)</label>
                        <input type="password" id="smtpPass">
                    </div>
                    <div class="settings-field">
                        <label>From Email</label>
                        <input type="email" id="smtpFromEmail">
                    </div>
                </div>
                <div class="settings-row single">
                    <div class="settings-row single">
                        <div class="settings-field">
                            <label>From Name</label>
                            <input type="text" id="smtpFromName">
                        </div>
                    </div>
                    <button class="btn btn-secondary btn-sm" onclick="testSmtp()">Test SMTP</button>
                </div>

                <div class="settings-section">
                    <div class="settings-section-title">IMAP Server</div>
                    <div class="settings-row">
                        <div class="settings-field">
                            <label>Host</label>
                            <input type="text" id="imapHost">
                        </div>
                        <div class="settings-field">
                            <label>Port</label>
                            <input type="number" id="imapPort">
                        </div>
                    </div>
                    <div class="settings-row">
                        <div class="settings-field">
                            <label>Security</label>
                            <select id="imapSecurity">
                                <option value="ssl">SSL</option>
                                <option value="tls">TLS</option>
                                <option value="">None</option>
                            </select>
                        </div>
                        <div class="settings-field">
                            <label>Username</label>
                            <input type="text" id="imapUser">
                        </div>
                    </div>
                    <div class="settings-row single">
                        <div class="settings-field">
                            <label>Password (leave empty)</label>
                            <input type="password" id="imapPass">
                        </div>
                    </div>
                    <button class="btn btn-secondary btn-sm" onclick="testImap()">Test IMAP</button>
                </div>

                <div class="settings-actions">
                    <button class="btn btn-primary" onclick="saveSettings()">Save Settings</button>
                </div>
            </div>
        </div>

        <div class="toast-container" id="toastContainer"></div>

        <script>
            const CSRF_TOKEN = '<?= $csrfToken ?>';

            function toast(msg, type = 'info') {
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
                } catch (e) { toast('Failed to load settings', 'error'); }
            }

            async function saveSettings() {
                const data = {
                    csrf_token: CSRF_TOKEN,
                    app_name: document.getElementById('appName').value,
                    admin_user: document.getElementById('adminUser').value,
                    admin_pass: document.getElementById('adminPass').value,
                    smtp_host: document.getElementById('smtpHost').value,
                    smtp_host: document.getElementById('smtpHost').value,
                    smtp_port: document.getElementById('smtpPort').value,
                    smtp_security: document.getElementById('smtp_security: document.getElementById('smtpSecurity').value,
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
                try {
                    const res = await fetch('../api/settings.php?action=save', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                        body: JSON.stringify(data)
                    });
                    const r = await res.json();
                    if (r.success) toast('Settings saved!', 'success');
                    else throw new Error(r.error);
                } catch (e) { toast(e.message, 'error'); }
            }

            async function testSmtp() {
                const h = document.getElementById('smtpHost').value;
                const p = document.getElementById('smtpPort').value;
                const s = document.getElementById('smtpSecurity').value;
                const u = document.getElementById('smtpUser').value;
                const w = document.getElementById('smtpPass').value;
                try {
                    const res = await fetch(`../api/settings.php?action=test_smtp&host=${hst = ${ h } & port=${ p } & security=${ s } & user=${ u } & pass=${ w }`, { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } });
                const d = await res.json();
                toast(d.success ? 'SMTP OK!' : 'SMTP failed: ' + d.error, d.success ? 'success' : 'error');
            } catch (e) { toast('Test failed', 'error'); }
        }

        }

        async function testImap() {
            const h = document.getElementById('imapHost').value;
            const p = document.getElementById('imapPort').value;
            const s = document.getElementById('imapSecurity').value;
            const u = document.getElementById('imapUser').value;
            const w = document.getElementById('imapPass').value;
            try {
                const res = await fetch(`../ api / settings.php ? action = test_imap & host=${ h } & port=${ p } & security=${ s } & user=${ u } & pass=${ w }`, { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } });
                const d = await res.json();
                toast(d.success ? 'IMAP OK!' : 'IMAP failed: ' + d.error, d.success ? 'success' : 'error');
            } catch (e) { toast('Test failed', 'error'); }
        }

        loadSettings();
        </script>
</body>

</html>