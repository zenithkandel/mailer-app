(function() {
    'use strict';

    const C = window.APP_CONFIG;
    const $ = (s) => document.querySelector(s);
    const $$ = (s) => document.querySelectorAll(s);

    let emails = [];
    let sentEmails = [];

    function toast(msg, type = 'info') {
        const container = $('#toastContainer');
        const t = document.createElement('div');
        t.className = `toast ${type}`;
        t.innerHTML = `<span>${escHtml(msg)}</span><button class="toast-close">&times;</button>`;
        container.appendChild(t);
        t.querySelector('.toast-close').onclick = () => t.remove();
        setTimeout(() => t.remove(), 5000);
    }

    function escHtml(str) {
        if (str == null) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
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

    function csrfHeaders() {
        return { 'X-CSRF-TOKEN': C.csrfToken };
    }

    function showView(view) {
        C.currentView = view;
        C.page = 1;
        C.hasMore = true;
        emails = [];
        sentEmails = [];

        $$('.sidebar-item').forEach(el => el.classList.remove('active'));
        $(`#${view}Nav`).classList.add('active');

        $('#emailList').innerHTML = '';
        $('#emailDetail').classList.add('hidden');
        $('#emailDetail').innerHTML = '';
        $('#loadMoreContainer').classList.add('hidden');
        $('#emptyState').classList.add('hidden');
        $('#searchInput').value = '';
        C.searchQuery = '';

        $('#viewTitle').textContent = view === 'inbox' ? 'Inbox' : 'Sent';

        loadEmails();
    }

    async function loadEmails() {
        if (C.loading) return;
        C.loading = true;

        try {
            let url, data;
            if (C.currentView === 'inbox') {
                url = `api/inbox.php?page=${C.page}&search=${encodeURIComponent(C.searchQuery)}`;
                const res = await fetch(url, { headers: csrfHeaders() });
                data = await res.json();

                if (!res.ok) throw new Error(data.error || 'Failed to load inbox');

                if (C.page === 1) emails = [];
                emails = emails.concat(data.emails);
                C.hasMore = data.has_more;

                if ($('#inboxBadge')) {
                    $('#inboxBadge').textContent = data.unread_count > 0 ? data.unread_count : '';
                }
            } else {
                url = `api/sent.php?page=${C.page}&search=${encodeURIComponent(C.searchQuery)}`;
                const res = await fetch(url, { headers: csrfHeaders() });
                data = await res.json();

                if (!res.ok) throw new Error(data.error || 'Failed to load sent mail');

                if (C.page === 1) sentEmails = [];
                sentEmails = sentEmails.concat(data.emails);
                C.hasMore = data.has_more;
            }

            renderEmailList(C.currentView === 'inbox' ? emails : sentEmails);

            if (data.emails.length === 0 && C.page === 1) {
                $('#emptyState').classList.remove('hidden');
            }

            $('#loadMoreContainer').classList.toggle('hidden', !C.hasMore);

        } catch (err) {
            toast(err.message, 'error');
        } finally {
            C.loading = false;
        }
    }

    function renderEmailList(list) {
        const container = $('#emailList');
        const view = C.currentView;

        list.forEach((email, idx) => {
            const existing = container.querySelector(`[data-id="${email.id}"]`);
            if (existing) return;

            const item = document.createElement('div');
            item.className = 'email-item' + (email.unread ? ' unread' : '');
            item.dataset.id = email.id;
            item.dataset.idx = idx;

            if (view === 'inbox') {
                item.innerHTML = `
                    <div>
                        <div class="email-sender">${escHtml(email.from_name || email.from)}</div>
                        <div class="email-subject">${escHtml(email.subject)}</div>
                        <div class="email-preview">${escHtml(email.preview)}</div>
                    </div>
                    <div class="email-meta">
                        <span class="email-date">${formatDate(email.date)}</span>
                    </div>
                `;
            } else {
                item.innerHTML = `
                    <div>
                        <div class="email-sender">${escHtml(email.to)}</div>
                        <div class="email-subject">${escHtml(email.subject)}</div>
                        <div class="email-preview">${escHtml(email.preview)}</div>
                    </div>
                    <div class="email-meta">
                        <span class="email-date">${formatDate(email.date)}</span>
                    </div>
                `;
            }

            item.onclick = () => openEmail(email, view, idx);
            container.appendChild(item);
        });
    }

    function openEmail(email, view, idx) {
        $$('.email-item').forEach(el => el.classList.remove('selected'));
        const el = $(`[data-id="${email.id}"]`);
        if (el) el.classList.add('selected');

        const detail = $('#emailDetail');
        detail.classList.remove('hidden');

        const initials = getInitials(view === 'inbox' ? (email.from_name || email.from) : email.to);

        let bodyHtml = '';
        if (email.body) {
            const sanitized = email.body.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
            bodyHtml = `<div class="email-detail-body">${sanitized}</div>`;
        }

        detail.innerHTML = `
            <button class="email-detail-back" onclick="window.app.closeEmail()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back
            </button>
            <div class="email-detail-header">
                <div class="email-detail-subject">${escHtml(email.subject)}</div>
                <div class="email-detail-meta">
                    <div class="email-detail-sender-avatar">${initials}</div>
                    <div class="email-detail-sender-info">
                        <div class="email-detail-sender-name">${escHtml(view === 'inbox' ? (email.from_name || email.from) : email.to)}</div>
                        <div class="email-detail-sender-email">${escHtml(view === 'inbox' ? email.from : email.to)}</div>
                    </div>
                    <div class="email-detail-date">${formatDate(email.date)}</div>
                </div>
            </div>
            ${bodyHtml}
        `;

        detail.scrollIntoView({ behavior: 'smooth', block: 'start' });

        if (view === 'inbox' && email.unread && email.uid) {
            fetch('api/inbox.php?action=mark_read&uid=' + email.uid, { headers: csrfHeaders() });
            if (el) el.classList.remove('unread');
            const badge = $('#inboxBadge');
            if (badge && badge.textContent) {
                const count = parseInt(badge.textContent) - 1;
                badge.textContent = count > 0 ? count : '';
            }
        }
    }

    window.app = {
        closeEmail: function() {
            $('#emailDetail').classList.add('hidden');
            $$('.email-item').forEach(el => el.classList.remove('selected'));
        },

        init: function() {
            const debounce = (fn, delay) => {
                let t;
                return (...args) => {
                    clearTimeout(t);
                    t = setTimeout(() => fn(...args), delay);
                };
            };

            $('#inboxNav').onclick = () => showView('inbox');
            $('#sentNav').onclick = () => showView('sent');
            $('#composeBtn').onclick = () => openCompose();

            $('#loadMoreBtn').onclick = () => {
                C.page++;
                loadEmails();
            };

            const doSearch = debounce(() => {
                C.searchQuery = $('#searchInput').value.trim();
                C.page = 1;
                C.hasMore = true;
                $('#emailList').innerHTML = '';
                loadEmails();
            }, 400);

            $('#searchInput').oninput = doSearch;

            $('#composeForm').onsubmit = (e) => {
                e.preventDefault();
                sendEmail();
            };

            $('#closeComposeBtn').onclick = closeCompose;
            $('#cancelComposeBtn').onclick = closeCompose;

            $('#logoutBtn').onclick = async () => {
                try {
                    await fetch('api/logout.php', { method: 'POST', headers: csrfHeaders() });
                } catch (_) {}
                window.location.href = 'index.php';
            };

            $('#composeModal').onclick = (e) => {
                if (e.target === $('#composeModal')) closeCompose();
            };

            document.onkeydown = (e) => {
                if (e.key === 'Escape') closeCompose();
            };

            showView('inbox');
        }
    };

    function openCompose() {
        $('#composeModal').classList.add('active');
        $('#composeTo').focus();
        $('#composeTo').value = '';
        $('#composeSubject').value = '';
        $('#composeBody').value = '';
    }

    function closeCompose() {
        $('#composeModal').classList.remove('active');
    }

    async function sendEmail() {
        const btn = $('#sendBtn');
        const to = $('#composeTo').value.trim();
        const subject = $('#composeSubject').value.trim();
        const body = $('#composeBody').value;

        if (!to || !subject || !body) {
            toast('Please fill in all fields', 'error');
            return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(to)) {
            toast('Please enter a valid email address', 'error');
            return;
        }

        btn.disabled = true;
        btn.querySelector('span').textContent = 'Sending...';

        try {
            const formData = new FormData();
            formData.append('to', to);
            formData.append('subject', subject);
            formData.append('body', body);
            formData.append('csrf_token', C.csrfToken);

            const res = await fetch('api/send.php', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': C.csrfToken },
                body: formData
            });

            const data = await res.json();

            if (!res.ok) throw new Error(data.error || 'Failed to send email');

            toast('Email sent successfully', 'success');
            closeCompose();

            if (C.currentView === 'sent') {
                C.page = 1;
                C.hasMore = true;
                $('#emailList').innerHTML = '';
                loadEmails();
            }
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.querySelector('span').textContent = 'Send';
        }
    }

    document.addEventListener('DOMContentLoaded', window.app.init);
})();