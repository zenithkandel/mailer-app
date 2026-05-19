<?php
require_once __DIR__ . '/../api/config.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit;
}

$config = loadConfig();
$appName = $config['app_name'] ?? 'Zenith Mail';
$csrfToken = csrfGenerate();
$folder = $_GET['folder'] ?? 'inbox';
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$perPage = 20;
$offset = ($page - 1) * $perPage;

$viewTitle = ucfirst($folder);
$folderMap = ['inbox' => 'INBOX', 'sent' => 'Sent', 'drafts' => 'Drafts', 'trash' => 'Trash'];
$imapFolder = $folderMap[$folder] ?? 'INBOX';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $viewTitle ?> - <?= sanitize($appName) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
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
            --accent-light: rgba(232, 123, 53, 0.1);
            --danger: #c44;
            --success: #4a4;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            height: 100vh;
            overflow: hidden;
        }

        .page-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        .toolbar {
            padding: 10px 16px;
            background: var(--bg-secondary);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .toolbar-btn {
            background: white;
            border: 1px solid var(--border-color);
            padding: 6px 12px;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            color: var(--text-secondary);
        }

        .toolbar-btn:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .toolbar-btn svg { width: 14px; height: 14px; }

        .search-box {
            flex: 1;
            position: relative;
            max-width: 300px;
        }

        .search-input {
            width: 100%;
            padding: 8px 12px 8px 32px;
            border: 1px solid var(--border-color);
            background: white;
            font-size: 13px;
        }

        .search-input:focus { outline: none; border-color: var(--accent); }

        .search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            color: var(--text-muted);
        }

        .content-header {
            padding: 12px 16px;
            background: var(--bg-secondary);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .content-title {
            font-size: 16px;
            font-weight: 600;
        }

        .email-list-container {
            flex: 1;
            overflow-y: auto;
        }

        .email-list {
            list-style: none;
        }

        .email-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.15s;
        }

        .email-item:hover { background: var(--bg-secondary); }
        .email-item.unread { background: #f5f0e6; font-weight: 600; }

        .email-checkbox {
            width: 16px;
            height: 16px;
            margin-right: 10px;
            accent-color: var(--accent);
        }

        .email-star {
            width: 16px;
            height: 16px;
            margin-right: 10px;
            color: var(--text-muted);
            cursor: pointer;
        }

        .email-star.starred { color: #e8a030; }

        .email-sender {
            width: 160px;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex-shrink: 0;
        }

        .email-subject {
            flex: 1;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-secondary);
        }

        .email-item.unread .email-subject { color: var(--text-primary); }

        .email-preview {
            flex: 1;
            font-size: 12px;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .email-date {
            font-size: 11px;
            color: var(--text-muted);
            margin-left: 12px;
            flex-shrink: 0;
        }

        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: var(--text-muted);
        }

        .empty-state svg {
            width: 48px;
            height: 48px;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .empty-state h3 { font-size: 16px; margin-bottom: 6px; color: var(--text-secondary); }

        .loading {
            padding: 40px;
            text-align: center;
            color: var(--text-muted);
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
            box-shadow: 2px 2px 0 rgba(0,0,0,0.2);
        }

        .toast.success { background: var(--success); }
        .toast.error { background: var(--danger); }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="toolbar">
            <button class="toolbar-btn" onclick="parent.loadEmails(true)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
                Refresh
            </button>
            <div class="search-box">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" class="search-input" id="searchInput" placeholder="Search..." value="<?= sanitize($search) ?>">
            </div>
        </div>

        <div class="content-header">
            <h2 class="content-title"><?= $viewTitle ?></h2>
        </div>

        <div class="email-list-container">
            <ul class="email-list" id="emailList">
                <li class="loading">Loading...</li>
            </ul>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        const CSRF_TOKEN = '<?= $csrfToken ?>';
        const FOLDER = '<?= $folder ?>';

        const state = {
            emails: [],
            selectedIds: new Set(),
            page: 1,
            hasMore: false,
            search: '<?= $search ?>',
            loading: false
        };

        function csrfHeaders() {
            return { 'X-CSRF-TOKEN': CSRF_TOKEN };
        }

        function escHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast ' + type;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diff = now - date;
            if (diff < 86400000 && date.getDate() === now.getDate()) {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } else if (diff < 604800000) {
                return date.toLocaleDateString([], { weekday: 'short' });
            } else {
                return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
            }
        }

        async function loadEmails(reset = true) {
            if (state.loading) return;
            state.loading = true;

            if (reset) {
                state.page = 1;
                state.emails = [];
            }

            try {
                const url = `../api/mail.php?action=${FOLDER}&page=${state.page}&search=${encodeURIComponent(state.search)}`;
                const res = await fetch(url, { headers: csrfHeaders() });
                const data = await res.json();

                if (reset) {
                    state.emails = data.emails || [];
                } else {
                    state.emails = [...state.emails, ...(data.emails || [])];
                }
                state.hasMore = data.has_more;
                state.page++;

                renderEmailList();
            } catch (err) {
                showToast('Failed to load emails', 'error');
            } finally {
                state.loading = false;
            }
        }

        function renderEmailList() {
            const list = document.getElementById('emailList');
            
            if (state.emails.length === 0) {
                const viewName = '<?= $viewTitle ?>';
                list.innerHTML = `
                    <li class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <h3>No emails</h3>
                        <p>Your ${viewName.toLowerCase()} is empty</p>
                    </li>
                `;
                return;
            }

            list.innerHTML = state.emails.map(email => `
                <li class="email-item ${email.unread ? 'unread' : ''}" onclick="viewEmail(${email.id})">
                    <input type="checkbox" class="email-checkbox" onclick="event.stopPropagation()">
                    <span class="email-star">
                        <svg viewBox="0 0 24 24" fill="${email.flagged ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                    </span>
                    <span class="email-sender">${escHtml(email.from_name || email.from || 'Unknown')}</span>
                    <span class="email-subject">${escHtml(email.subject || '(No subject)')}</span>
                    <span class="email-preview">${escHtml(email.preview || '')}</span>
                    <span class="email-date">${formatDate(email.date)}</span>
                </li>
            `).join('');
        }

        function viewEmail(id) {
            parent.viewEmail(id, FOLDER);
        }

        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                state.search = e.target.value;
                const url = new URL(window.location.href);
                url.searchParams.set('search', state.search);
                url.searchParams.set('page', '1');
                window.history.replaceState({}, '', url);
                loadEmails(true);
            }, 500);
        });

        loadEmails(true);
    </script>
</body>
</html>