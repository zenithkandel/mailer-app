(function() {
    'use strict';

    const C = window.APP_CONFIG;
    const $ = (s) => document.querySelector(s);
    const $$ = (s) => document.querySelectorAll(s);

    let emails = [];
    let selectedEmail = null;
    let searchTimeout;
    let viewStack = []; // stack of view states for back navigation

    function csrfHeaders() {
        return { 'X-CSRF-TOKEN': C.csrfToken };
    }

    function escHtml(str) {
        if (str == null) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr);
        const now = new Date();
        const diff = now - d;
        if (isNaN(d.getTime())) return '';
        if (diff < 86400000 && d.toDateString() === now.toDateString()) {
            return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
        }
        if (diff < 604800000) {
            return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        }
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: d.getFullYear() !== now.getFullYear() ? 'numeric' : undefined });
    }

    function getInitials(name) {
        if (!name) return '?';
        return name.split(' ').map(p => p[0]).join('').toUpperCase().slice(0, 2);
    }

    function toast(msg, type = 'info') {
        const container = $('#toastContainer');
        if (!container) return;
        const t = document.createElement('div');
        t.className = `toast ${type}`;
        t.innerHTML = `<span>${escHtml(msg)}</span><button class="toast-close">&times;</button>`;
        container.appendChild(t);
        const closeBtn = t.querySelector('.toast-close');
        if (closeBtn) closeBtn.onclick = () => t.remove();
        setTimeout(() => t.remove(), 5000);
    }

    function getViewTitle(view) {
        const titles = { inbox: 'Inbox', sent: 'Sent', drafts: 'Drafts', starred: 'Starred', trash: 'Trash', settings: 'Settings' };
        return titles[view] || 'Mail';
    }

    async function loadEmails(reset = true) {
        if (C.loading) return;
        C.loading = true;

        const listEl = $('#emailList');
        const detailEl = $('#emailDetail');

        if (reset) {
            C.page = 1;
            C.hasMore = true;
            emails = [];
            if (listEl) listEl.innerHTML = '';
            if (detailEl) detailEl.innerHTML = '';
            selectedEmail = null;
        }

        if (!$('#emailList')) return;

        if (emails.length === 0) {
            $('#emailList').innerHTML = `<div class="loading-spinner"><div class="spinner"></div></div>`;
        }

        try {
            const url = `api/mail.php?action=${C.currentView}&page=${C.page}&search=${encodeURIComponent(C.searchQuery)}`;
            const res = await fetch(url, { headers: csrfHeaders() });
            const data = await res.json();

            if (!res.ok) throw new Error(data.error || 'Failed to load emails');

            if (reset) emails = [];
            emails = emails.concat(data.emails || []);
            C.hasMore = data.has_more;

            if ($('#inboxBadge') && C.currentView === 'inbox') {
                const badge = $('#inboxBadge');
                badge.textContent = data.unread > 0 ? data.unread : '';
                C.inboxUnread = data.unread;
            }

            renderEmailList();
            renderEmptyOrList();

        } catch (err) {
            toast(err.message, 'error');
            if ($('#emailList')) $('#emailList').innerHTML = '';
        } finally {
            C.loading = false;
        }
    }

    function renderEmptyOrList() {
        const listEl = $('#emailList');
        if (!listEl) return;

        if (emails.length === 0) {
            const emptyNames = { inbox: 'No emails in inbox', sent: 'No sent emails', drafts: 'No drafts', starred: 'No starred emails', trash: 'Trash is empty' };
            listEl.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </div>
                    <h3>${emptyNames[C.currentView] || 'No emails'}</h3>
                    <p>This folder is empty</p>
                </div>`;
        }
    }

    function renderEmailList() {
        const listEl = $('#emailList');
        if (!listEl) return;
        if (emails.length === 0) return;

        const view = C.currentView;
        const isInbox = view === 'inbox';

        listEl.innerHTML = emails.map((email, idx) => {
            const sender = isInbox ? (email.from_name || email.from || 'Unknown') : (email.to_name || email.to || 'Unknown');
            const senderEmail = isInbox ? email.from : email.to;
            const isUnread = isInbox && email.unread;

            return `
                <div class="email-item${isUnread ? ' unread' : ''}" data-id="${email.id}" data-idx="${idx}" onclick="window.app.viewEmail(${email.id})">
                    <div class="email-item-main">
                        <label class="email-star${email.flagged ? ' starred' : ''}" onclick="event.stopPropagation(); window.app.toggleStar(${email.id})">
                            <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        </label>
                        <span class="email-sender">${escHtml(sender)}</span>
                        <span class="email-subject">${escHtml(email.subject || '(No subject)')}</span>
                    </div>
                    <div>
                        <span class="email-date">${formatDate(email.date)}</span>
                    </div>
                </div>`;
        }).join('');

        const loadMoreEl = $('#loadMoreWrap');
        if (loadMoreEl) loadMoreEl.classList.toggle('hidden', !C.hasMore);
    }

    async function viewEmail(id) {
        const email = emails.find(e => e.id == id);
        if (!email) return;

        selectedEmail = email;

        $$('.email-item').forEach(el => el.classList.remove('selected'));
        const el = $(`[data-id="${id}"]`);
        if (el) el.classList.add('selected');

        const detailEl = $('#emailDetail');
        if (!detailEl) return;

        const view = C.currentView;
        const sender = view === 'inbox' ? (email.from_name || email.from || 'Unknown') : (email.to_name || email.to || 'Unknown');
        const senderEmail = view === 'inbox' ? email.from : email.to;
        const initials = getInitials(sender);
        const isInbox = view === 'inbox';

        viewStack.push({ emails: [...emails], selected: selectedEmail, searchQuery: C.searchQuery, page: C.page });

        detailEl.innerHTML = `
            <div class="email-detail">
                <div class="email-detail-header">
                    <button class="email-detail-back" onclick="window.app.closeEmail()">
                        <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        Back
                    </button>
                    <div class="email-detail-subject">${escHtml(email.subject || '(No subject)')}</div>
                </div>
                <div class="email-detail-meta">
                    <div class="sender-avatar">${initials}</div>
                    <div class="sender-info">
                        <div class="sender-name">${escHtml(sender)}</div>
                        <div class="sender-email">${escHtml(senderEmail)}</div>
                    </div>
                    <div class="email-date-full">${formatDate(email.date)}</div>
                </div>
                <div class="email-actions-bar">
                    <button class="email-action-btn" onclick="window.app.replyToEmail()">
                        <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                        Reply
                    </button>
                    <button class="email-action-btn" onclick="window.app.forwardEmail()">
                        <svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M15 5l4 4-4 4"/><line x1="21" y1="9" x2="3" y2="9"/><line x1="3" y1="19" x2="21" y2="19"/></svg>
                        Forward
                    </button>
                    <button class="email-action-btn" onclick="window.app.deleteEmail(${email.id})">
                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        Delete
                    </button>
                    <button class="email-action-btn" onclick="window.app.toggleStar(${email.id})">
                        <svg viewBox="0 0 24 24" fill="${email.flagged ? 'currentColor' : 'none'}"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        ${email.flagged ? 'Unstar' : 'Star'}
                    </button>
                </div>
                <div class="email-body" id="emailBodyWrap">
                    <div class="loading-spinner"><div class="spinner"></div></div>
                </div>
                <div class="email-reply-section">
                    <h4>Reply</h4>
                    <textarea class="reply-area" id="replyArea" placeholder="Write your reply... (Ctrl+Enter to send)"></textarea>
                    <div class="reply-actions">
                        <button class="btn btn-primary" onclick="window.app.sendReply()">
                            <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            Send
                        </button>
                    </div>
                </div>
            </div>`;

        detailEl.scrollIntoView({ behavior: 'smooth', block: 'start' });

        if (isInbox && email.unread) {
            fetch(`api/mail.php?action=mark`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': C.csrfToken },
                body: JSON.stringify({ ids: [email.id], flag: 'read', folder: view })
            }).then(() => {
                email.unread = false;
                if (el) el.classList.remove('unread');
                const badge = $('#inboxBadge');
                if (badge && C.inboxUnread > 0) {
                    C.inboxUnread--;
                    badge.textContent = C.inboxUnread > 0 ? C.inboxUnread : '';
                }
            });
        }

        loadEmailBody(email.id, view);
    }

    async function loadEmailBody(id, folder) {
        try {
            const res = await fetch(`api/mail.php?action=view&id=${id}&folder=${folder}`, { headers: csrfHeaders() });
            const data = await res.json();
            if (data.error) throw new Error(data.error);

            const bodyEl = $('#emailBodyWrap');
            if (bodyEl) {
                const sanitized = (data.body || '').replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
                bodyEl.innerHTML = `<div class="email-body">${sanitized}</div>`;
            }
        } catch (err) {
            const bodyEl = $('#emailBodyWrap');
            if (bodyEl) bodyEl.innerHTML = '<p style="color:var(--error)">Failed to load email body</p>';
        }
    }

    function renderMainContent() {
        const container = $('#viewContainer');
        if (!container) return;

        const view = C.currentView;
        const title = getViewTitle(view);

        if (view === 'settings') {
            container.innerHTML = `
                <div class="settings-page">
                    <div class="settings-title">Settings</div>
                    <div class="settings-section">
                        <div class="settings-section-header">General</div>
                        <div class="settings-section-body">
                            <div class="settings-grid">
                                <div class="form-group full">
                                    <label class="form-label">App Name</label>
                                    <input type="text" class="form-control" id="sAppName" placeholder="Zenith Mail">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="settings-section">
                        <div class="settings-section-header">Admin Account</div>
                        <div class="settings-section-body">
                            <div class="settings-grid">
                                <div class="form-group">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" id="sAdminUser">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Password (leave empty)</label>
                                    <input type="password" class="form-control" id="sAdminPass" placeholder="••••••••">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="settings-section">
                        <div class="settings-section-header">SMTP Server</div>
                        <div class="settings-section-body">
                            <div class="settings-grid">
                                <div class="form-group">
                                    <label class="form-label">Host</label>
                                    <input type="text" class="form-control" id="sSmtpHost" placeholder="smtp.example.com">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Port</label>
                                    <input type="number" class="form-control" id="sSmtpPort" value="465">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Security</label>
                                    <select class="form-control" id="sSmtpSecurity">
                                        <option value="ssl">SSL</option>
                                        <option value="tls">TLS</option>
                                        <option value="">None</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" id="sSmtpUser">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" id="sSmtpPass" placeholder="Leave empty to keep">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">From Email</label>
                                    <input type="email" class="form-control" id="sSmtpFromEmail">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">From Name</label>
                                    <input type="text" class="form-control" id="sSmtpFromName">
                                </div>
                            </div>
                            <button class="test-btn" id="testSmtpBtn" onclick="window.app.testSmtp()">Test SMTP</button>
                        </div>
                    </div>
                    <div class="settings-section">
                        <div class="settings-section-header">IMAP Server</div>
                        <div class="settings-section-body">
                            <div class="settings-grid">
                                <div class="form-group">
                                    <label class="form-label">Host</label>
                                    <input type="text" class="form-control" id="sImapHost" placeholder="imap.example.com">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Port</label>
                                    <input type="number" class="form-control" id="sImapPort" value="993">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Security</label>
                                    <select class="form-control" id="sImapSecurity">
                                        <option value="ssl">SSL</option>
                                        <option value="tls">TLS</option>
                                        <option value="">None</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" id="sImapUser">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" id="sImapPass" placeholder="Leave empty to keep">
                                </div>
                            </div>
                            <button class="test-btn" id="testImapBtn" onclick="window.app.testImap()">Test IMAP</button>
                        </div>
                    </div>
                    <div class="settings-footer">
                        <button class="btn btn-primary" onclick="window.app.saveSettings()">
                            <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                            Save Settings
                        </button>
                    </div>
                </div>`;
            loadSettingsData();
            return;
        }

        container.innerHTML = `
            <div class="view-wrap">
                <div class="page-header">
                    <div class="page-title-wrap">
                        <h1 class="page-title">${title}</h1>
                    </div>
                    <div class="page-actions">
                        <button class="icon-btn" onclick="window.app.refresh()" title="Refresh">
                            <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        </button>
                    </div>
                </div>
                <div class="email-list-wrap">
                    <div class="email-list" id="emailList"></div>
                    <div id="emailDetail"></div>
                    <div class="load-more-wrap hidden" id="loadMoreWrap">
                        <button class="load-more-btn" onclick="window.app.loadMore()">Load More</button>
                    </div>
                </div>
            </div>`;

        loadEmails();
    }

    async function loadSettingsData() {
        try {
            const res = await fetch('api/settings.php?action=get', { headers: csrfHeaders() });
            const d = await res.json();
            if (d.error) return;
            const id = (id) => document.getElementById(id);
            if (id('sAppName')) id('sAppName').value = d.app_name || '';
            if (id('sAdminUser')) id('sAdminUser').value = d.admin_user || '';
            if (id('sSmtpHost')) id('sSmtpHost').value = d.smtp?.host || '';
            if (id('sSmtpPort')) id('sSmtpPort').value = d.smtp?.port || 465;
            if (id('sSmtpSecurity')) id('sSmtpSecurity').value = d.smtp?.security || 'ssl';
            if (id('sSmtpUser')) id('sSmtpUser').value = d.smtp?.user || '';
            if (id('sSmtpFromEmail')) id('sSmtpFromEmail').value = d.smtp?.from_email || '';
            if (id('sSmtpFromName')) id('sSmtpFromName').value = d.smtp?.from_name || '';
            if (id('sImapHost')) id('sImapHost').value = d.imap?.host || '';
            if (id('sImapPort')) id('sImapPort').value = d.imap?.port || 993;
            if (id('sImapSecurity')) id('sImapSecurity').value = d.imap?.security || 'ssl';
            if (id('sImapUser')) id('sImapUser').value = d.imap?.user || '';
        } catch (e) { toast('Failed to load settings', 'error'); }
    }

    async function saveSettings() {
        const id = (id) => document.getElementById(id);
        const data = {
            csrf_token: C.csrfToken,
            app_name: id('sAppName')?.value || '',
            admin_user: id('sAdminUser')?.value || '',
            admin_pass: id('sAdminPass')?.value || '',
            smtp_host: id('sSmtpHost')?.value || '',
            smtp_port: parseInt(id('sSmtpPort')?.value) || 465,
            smtp_security: id('sSmtpSecurity')?.value || 'ssl',
            smtp_user: id('sSmtpUser')?.value || '',
            smtp_pass: id('sSmtpPass')?.value || '',
            smtp_from_email: id('sSmtpFromEmail')?.value || '',
            smtp_from_name: id('sSmtpFromName')?.value || '',
            imap_host: id('sImapHost')?.value || '',
            imap_port: parseInt(id('sImapPort')?.value) || 993,
            imap_security: id('sImapSecurity')?.value || 'ssl',
            imap_user: id('sImapUser')?.value || '',
            imap_pass: id('sImapPass')?.value || ''
        };
        try {
            const res = await fetch('api/settings.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': C.csrfToken },
                body: JSON.stringify(data)
            });
            const r = await res.json();
            if (r.success) toast('Settings saved successfully', 'success');
            else throw new Error(r.error);
        } catch (e) { toast(e.message, 'error'); }
    }

    async function testSmtp() {
        const btn = $('#testSmtpBtn');
        if (!btn) return;
        const oldText = btn.textContent;
        btn.textContent = 'Testing...';
        btn.disabled = true;
        try {
            const id = (id) => document.getElementById(id)?.value || '';
            const res = await fetch(`api/settings.php?action=test_smtp&host=${id('sSmtpHost')}&port=${id('sSmtpPort')}&security=${id('sSmtpSecurity')}&user=${id('sSmtpUser')}&pass=${id('sSmtpPass')}`, {
                headers: { 'X-CSRF-TOKEN': C.csrfToken }
            });
            const d = await res.json();
            toast(d.success ? 'SMTP connection successful!' : 'SMTP failed: ' + d.error, d.success ? 'success' : 'error');
        } catch (e) { toast('Test failed', 'error'); }
        btn.textContent = oldText;
        btn.disabled = false;
    }

    async function testImap() {
        const btn = $('#testImapBtn');
        if (!btn) return;
        const oldText = btn.textContent;
        btn.textContent = 'Testing...';
        btn.disabled = true;
        try {
            const id = (id) => document.getElementById(id)?.value || '';
            const res = await fetch(`api/settings.php?action=test_imap&host=${id('sImapHost')}&port=${id('sImapPort')}&security=${id('sImapSecurity')}&user=${id('sImapUser')}&pass=${id('sImapPass')}`, {
                headers: { 'X-CSRF-TOKEN': C.csrfToken }
            });
            const d = await res.json();
            toast(d.success ? 'IMAP connection successful!' : 'IMAP failed: ' + d.error, d.success ? 'success' : 'error');
        } catch (e) { toast('Test failed', 'error'); }
        btn.textContent = oldText;
        btn.disabled = false;
    }

    function openCompose() {
        $('#composeModal').classList.add('active');
        $('#composeTo').value = '';
        $('#composeSubject').value = '';
        $('#composeBody').value = '';
        $('#composeTo').focus();
    }

    function closeCompose() {
        $('#composeModal').classList.remove('active');
    }

    async function sendEmail() {
        const btn = $('#sendBtn');
        const to = $('#composeTo').value.trim();
        const subject = $('#composeSubject').value.trim();
        const body = $('#composeBody').value;

        if (!to) { toast('Please enter a recipient', 'error'); return; }
        if (!subject) { toast('Please enter a subject', 'error'); return; }
        if (!body) { toast('Please enter a message', 'error'); return; }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(to)) { toast('Please enter a valid email address', 'error'); return; }

        if (btn) { btn.disabled = true; btn.querySelector('span')?.remove; }
        const btnText = btn?.querySelector('svg')?.nextSibling || btn?.lastChild;
        if (btnText) btnText.textContent = 'Sending...';

        try {
            const formData = new FormData();
            formData.append('to', to);
            formData.append('subject', subject);
            formData.append('body', body);
            formData.append('csrf_token', C.csrfToken);

            const res = await fetch('api/mail.php?action=send', {
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
                emails = [];
                loadEmails();
            }
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            if (btn) { btn.disabled = false; }
            if (btnText) btnText.textContent = 'Send';
        }
    }

    async function saveDraft() {
        const to = $('#composeTo').value.trim();
        const subject = $('#composeSubject').value.trim();
        const body = $('#composeBody').value;

        if (!to && !subject && !body) { toast('Nothing to save', 'info'); return; }

        try {
            const res = await fetch('api/mail.php?action=draft', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': C.csrfToken },
                body: JSON.stringify({ csrf_token: C.csrfToken, to, subject, body })
            });
            const data = await res.json();
            if (data.success) {
                toast('Draft saved', 'success');
                closeCompose();
            } else {
                throw new Error(data.error);
            }
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    async function deleteEmail(id) {
        if (!confirm('Delete this email?')) return;
        try {
            const res = await fetch('api/mail.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': C.csrfToken },
                body: JSON.stringify({ ids: [id], folder: C.currentView })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error);

            toast('Email deleted', 'success');
            emails = emails.filter(e => e.id != id);
            if (selectedEmail?.id == id) {
                selectedEmail = null;
                window.app.closeEmail(true);
            }
            renderEmailList();
            renderEmptyOrList();
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    async function toggleStar(id) {
        const email = emails.find(e => e.id == id);
        if (!email) return;
        const newFlag = email.flagged ? 'unflagged' : 'flagged';
        try {
            const res = await fetch('api/mail.php?action=mark', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': C.csrfToken },
                body: JSON.stringify({ ids: [id], flag: newFlag, folder: C.currentView })
            });
            const data = await res.json();
            if (data.success) {
                email.flagged = !email.flagged;
                renderEmailList();
            }
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    async function sendReply() {
        if (!selectedEmail) return;
        const replyBody = $('#replyArea')?.value.trim();
        if (!replyBody) { toast('Please write a reply', 'error'); return; }

        const to = selectedEmail.from;
        const subject = selectedEmail.subject?.startsWith('Re:') ? selectedEmail.subject : 'Re: ' + (selectedEmail.subject || '');

        try {
            const res = await fetch('api/mail.php?action=send', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': C.csrfToken },
                body: new URLSearchParams({ to, subject, body: replyBody, csrf_token: C.csrfToken })
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Failed to send reply');
            toast('Reply sent', 'success');
            if ($('#replyArea')) $('#replyArea').value = '';
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    function replyToEmail() {
        if (!selectedEmail) return;
        openCompose();
        setTimeout(() => {
            $('#composeTo').value = selectedEmail.from || '';
            $('#composeSubject').value = selectedEmail.subject?.startsWith('Re:') ? selectedEmail.subject : 'Re: ' + (selectedEmail.subject || '');
        }, 100);
    }

    function forwardEmail() {
        if (!selectedEmail) return;
        openCompose();
        setTimeout(() => {
            $('#composeSubject').value = selectedEmail.subject?.startsWith('Fwd:') ? selectedEmail.subject : 'Fwd: ' + (selectedEmail.subject || '');
            $('#composeBody').value = selectedEmail.body || '';
        }, 100);
    }

    window.app = {
        navigateTo: function(view) {
            C.currentView = view;
            C.page = 1;
            C.hasMore = true;
            C.searchQuery = '';
            emails = [];
            selectedEmail = null;
            viewStack = [];
            const url = new URL(window.location.href);
            url.searchParams.set('view', view);
            window.history.pushState({}, '', url);
            $$('.nav-link').forEach(el => {
                el.classList.toggle('active', el.getAttribute('href') === '?view=' + view);
            });
            renderMainContent();
            return false;
        },

        refresh: function() {
            C.page = 1;
            C.hasMore = true;
            emails = [];
            selectedEmail = null;
            viewStack = [];
            loadEmails(true);
        },

        loadMore: function() {
            C.page++;
            loadEmails(false);
        },

        viewEmail: viewEmail,

        closeEmail: function(restoreStack = false) {
            if (restoreStack && viewStack.length > 0) {
                const prev = viewStack.pop();
                emails = prev.emails;
                selectedEmail = prev.selected;
                C.searchQuery = prev.searchQuery;
                C.page = prev.page;
                $$('.email-item').forEach(el => el.classList.remove('selected'));
                if (selectedEmail) {
                    const el = $(`[data-id="${selectedEmail.id}"]`);
                    if (el) el.classList.add('selected');
                }
                renderEmailList();
                renderEmptyOrList();
                const detailEl = $('#emailDetail');
                if (detailEl) detailEl.innerHTML = '';
                const searchInput = $('#searchInput');
                if (searchInput) searchInput.value = C.searchQuery;
            } else {
                const detailEl = $('#emailDetail');
                if (detailEl) detailEl.innerHTML = '';
                $$('.email-item').forEach(el => el.classList.remove('selected'));
                selectedEmail = null;
            }
        },

        openCompose: openCompose,
        closeCompose: closeCompose,
        sendEmail: sendEmail,
        saveDraft: saveDraft,
        deleteEmail: deleteEmail,
        toggleStar: toggleStar,
        sendReply: sendReply,
        replyToEmail: replyToEmail,
        forwardEmail: forwardEmail,

        saveSettings: saveSettings,
        testSmtp: testSmtp,
        testImap: testImap,

        logout: async function() {
            try {
                await fetch('api/auth.php?action=logout', { method: 'POST', headers: csrfHeaders() });
            } catch (_) {}
            window.location.href = 'index.php';
        },

        init: function() {
            renderMainContent();

            const searchInput = $('#searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        C.searchQuery = searchInput.value.trim();
                        C.page = 1;
                        C.hasMore = true;
                        emails = [];
                        loadEmails(true);
                    }, 400);
                });
            }

            const composeModal = $('#composeModal');
            if (composeModal) {
                composeModal.addEventListener('click', (e) => {
                    if (e.target === composeModal) closeCompose();
                });
            }

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeCompose();
                if (e.ctrlKey && e.key === 'Enter') sendReply();
            });

            window.addEventListener('popstate', () => {
                const params = new URLSearchParams(window.location.search);
                const view = params.get('view') || 'inbox';
                C.currentView = view;
                renderMainContent();
            });
        }
    };

    document.addEventListener('DOMContentLoaded', window.app.init);
})();