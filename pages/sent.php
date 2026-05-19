<?php
require_once __DIR__ . '/../api/config.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$config = loadConfig();
$appName = $config['app_name'] ?? 'Zenith Mail';
$csrfToken = csrfGenerate();
$folder = 'sent';
$viewTitle = 'Sent';
$folderMap = ['sent' => 'Sent'];
$imapFolder = $folderMap[$folder] ?? 'Sent';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sent - <?= sanitize($appName) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --bg-primary: #f0ebe0; --bg-secondary: #faf6ed; --bg-tertiary: #e5dccb; --border-color: #d4c4a8; --text-primary: #4a3f35; --text-secondary: #6a5a4a; --text-muted: #8a7a6a; --accent: #e87b35; }
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg-primary); color: var(--text-primary); height: 100vh; overflow: hidden; }
        .page-container { display: flex; flex-direction: column; height: 100vh; }
        .toolbar { padding: 10px 16px; background: var(--bg-secondary); border-bottom: 2px solid var(--border-color); display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
        .toolbar-btn { background: white; border: 1px solid var(--border-color); padding: 6px 12px; font-size: 12px; cursor: pointer; color: var(--text-secondary); }
        .toolbar-btn:hover { background: var(--bg-tertiary); }
        .content-header { padding: 12px 16px; background: var(--bg-secondary); border-bottom: 2px solid var(--border-color); flex-shrink: 0; }
        .content-title { font-size: 16px; font-weight: 600; }
        .email-list-container { flex: 1; overflow-y: auto; }
        .email-list { list-style: none; }
        .email-item { display: flex; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--border-color); cursor: pointer; }
        .email-item:hover { background: var(--bg-secondary); }
        .email-sender { width: 160px; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex-shrink: 0; }
        .email-subject { flex: 1; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-secondary); }
        .email-date { font-size: 11px; color: var(--text-muted); margin-left: 12px; flex-shrink: 0; }
        .empty-state { padding: 60px 20px; text-align: center; color: var(--text-muted); }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="toolbar">
            <button class="toolbar-btn" onclick="parent.loadEmails(true)">Refresh</button>
        </div>
        <div class="content-header"><h2 class="content-title">Sent</h2></div>
        <div class="email-list-container"><ul class="email-list" id="emailList"><li class="empty-state">Loading...</li></ul></div>
    </div>
    <script>
        const CSRF_TOKEN = '<?= $csrfToken ?>';
        const FOLDER = 'sent';
        const state = { emails: [], page: 1, hasMore: false, loading: false, search: '' };
        function csrfHeaders() { return { 'X-CSRF-TOKEN': CSRF_TOKEN }; }
        function escHtml(str) { const div = document.createElement('div'); div.textContent = str; return div.innerHTML; }
        function formatDate(dateStr) { const d = new Date(dateStr); return d.toLocaleDateString([], { month: 'short', day: 'numeric' }); }
        async function loadEmails(reset = true) {
            if (state.loading) return;
            state.loading = true;
            try {
                const res = await fetch(`../api/mail.php?action=${FOLDER}&page=${state.page}`, { headers: csrfHeaders() });
                const data = await res.json();
                state.emails = data.emails || [];
                renderEmailList();
            } catch (e) { document.getElementById('emailList').innerHTML = '<li class="empty-state">Error loading</li>'; }
            state.loading = false;
        }
        function renderEmailList() {
            const list = document.getElementById('emailList');
            if (!state.emails.length) { list.innerHTML = '<li class="empty-state">No sent emails</li>'; return; }
            list.innerHTML = state.emails.map(e => `<li class="email-item" onclick="parent.viewEmail(${e.id},'${FOLDER}')"><span class="email-sender">To: ${escHtml(e.to_name || e.to)}</span><span class="email-subject">${escHtml(e.subject)}</span><span class="email-date">${formatDate(e.date)}</span></li>`).join('');
        }
        loadEmails(true);
    </script>
</body>
</html>