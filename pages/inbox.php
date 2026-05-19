<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="page-container">
        <div class="page-toolbar">
            <button class="btn btn-secondary btn-sm" onclick="loadEmails(true)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                Refresh
            </button>
            <div style="flex:1"></div>
            <div class="search-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="searchInput" placeholder="Search emails...">
            </div>
        </div>
        <div class="page-header">
            <h2>Inbox</h2>
        </div>
        <div class="email-list-container">
            <div class="email-list" id="emailList">
                <div class="empty-state"><p>Loading...</p></div>
            </div>
            <div class="load-more-container hidden" id="loadMoreContainer">
                <button class="btn btn-secondary" id="loadMoreBtn">Load More</button>
            </div>
            <div class="empty-state hidden" id="emptyState">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                <p>No emails found</p>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        const CSRF_TOKEN = '<?= $csrfToken ?>';
        let state = {
            emails: [], page: 1, hasMore: false, loading: false, search: ''
        };

        function toast(msg, type = 'info') {
            const c = document.getElementById('toastContainer');
            const t = document.createElement('div');
            t.className = 'toast ' + type;
            t.innerHTML = `<span>${escHtml(msg)}</span><button class="toast-close">&times;</button>`;
            c.appendChild(t);
            t.querySelector('.toast-close').onclick = () => t.remove();
            setTimeout(() => t.remove(), 5000);
        }

        function escHtml(str) {
            if (str == null) return '';
            const d = document.createElement('div');
            d.textContent = String(str);
            return d.innerHTML;
        }

        function formatDate(dateStr) {
            const d = new Date(dateStr);
            const now = new Date();
            const diff = now - d;
            if (diff < 86400000 && d.getDate() === now.getDate()) {
                return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
            }
            if (diff < 604800000) {
                return d.toLocaleDateString('en-US', { weekday: 'short', hour: '2-digit', minute: '2-digit', hour12: false });
            }
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: d.getFullYear() !== now.getFullYear() ? 'numeric' : undefined });
        }

        function getInitials(name) {
            if (!name) return '?';
            return name.split(' ').map(p => p[0]).join('').toUpperCase().slice(0, 2);
        }

        async function loadEmails(reset = true) {
            if (state.loading) return;
            state.loading = true;
            if (reset) { state.page = 1; state.emails = []; document.getElementById('emailList').innerHTML = ''; }

            try {
                const res = await fetch(`../api/inbox.php?page=${state.page}&search=${encodeURIComponent(state.search)}`, {
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN }
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Failed to load');

                state.emails = reset ? data.emails : state.emails.concat(data.emails);
                state.hasMore = data.has_more;
                renderEmails();

                const badge = document.getElementById('inboxBadge');
                if (badge) badge.textContent = data.unread_count > 0 ? data.unread_count : '';

                document.getElementById('loadMoreContainer').classList.toggle('hidden', !data.has_more);

                if (data.emails.length === 0 && state.page === 1) {
                    document.getElementById('emptyState').classList.remove('hidden');
                } else {
                    document.getElementById('emptyState').classList.add('hidden');
                }
            } catch (err) {
                toast(err.message, 'error');
            } finally {
                state.loading = false;
            }
        }

        function renderEmails() {
            const container = document.getElementById('emailList');
            state.emails.forEach(email => {
                if (container.querySelector(`[data-id="${email.id}"]`)) return;
                const item = document.createElement('div');
                item.className = 'email-item' + (email.unread ? ' unread' : '');
                item.dataset.id = email.id;
                item.innerHTML = `
                    <div class="email-avatar">${getInitials(email.from_name || email.from)}</div>
                    <div class="email-content">
                        <div class="email-sender">${escHtml(email.from_name || email.from)}</div>
                        <div class="email-subject">${escHtml(email.subject)}</div>
                        <div class="email-preview">${escHtml(email.preview || '')}</div>
                    </div>
                    <div class="email-meta">
                        <span class="email-date">${formatDate(email.date)}</span>
                    </div>
                `;
                item.onclick = () => openEmail(email);
                container.appendChild(item);
            });
        }

        let selectedId = null;

        function openEmail(email) {
            document.querySelectorAll('.email-item').forEach(el => el.classList.remove('selected'));
            const el = document.querySelector(`[data-id="${email.id}"]`);
            if (el) el.classList.add('selected');

            if (email.unread && email.uid) {
                fetch('../api/inbox.php?action=mark_read&uid=' + email.uid, { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } });
                if (el) el.classList.remove('unread');
            }

            const container = document.getElementById('emailList');
            const initials = getInitials(email.from_name || email.from);
            let bodyHtml = '';
            if (email.body) {
                const sanitized = email.body.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
                bodyHtml = `<div class="email-detail-body">${sanitized}</div>`;
            }

            container.innerHTML = `
                <button class="email-detail-back" onclick="loadEmails(true)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Back to Inbox
                </button>
                <div class="email-detail">
                    <div class="email-detail-header">
                        <div class="email-detail-subject">${escHtml(email.subject)}</div>
                        <div class="email-detail-meta">
                            <div class="email-detail-sender-avatar">${initials}</div>
                            <div class="email-detail-sender-info">
                                <div class="email-detail-sender-name">${escHtml(email.from_name || email.from)}</div>
                                <div class="email-detail-sender-email">${escHtml(email.from)}</div>
                            </div>
                            <div class="email-detail-date">${formatDate(email.date)}</div>
                        </div>
                    </div>
                    ${bodyHtml}
                </div>
            `;
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadEmails(true);

            const searchInput = document.getElementById('searchInput');
            let debounceTimer;
            searchInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    state.search = searchInput.value.trim();
                    loadEmails(true);
                }, 400);
            });

            document.getElementById('loadMoreBtn').addEventListener('click', () => {
                state.page++;
                loadEmails(false);
            });
        });
    </script>
</body>
</html>
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