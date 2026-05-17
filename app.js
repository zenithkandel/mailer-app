class MailApp {
    constructor() {
        this.csrfToken = '';
        this.currentFolder = 'INBOX';
        this.currentPage = 1;
        this.totalPages = 1;
        this.selectedMessages = new Set();
        this.currentMessageUid = null;
        this.autoRefreshInterval = null;
        this.draftAutoSaveInterval = null;
        this.composeMode = 'new';
        this.composeDraftId = null;
        this.attachments = [];

        this.folderManager = new FolderManager(this);
        this.messageListManager = new MessageListManager(this);
        this.messageViewerManager = new MessageViewerManager(this);
        this.composeManager = new ComposeManager(this);
        this.contactsManager = new ContactsManager(this);
        this.searchManager = new SearchManager(this);
        this.settingsManager = new SettingsManager(this);
        this.filterManager = new FilterManager(this);
        this.progressManager = new ProgressManager(this);
        this.toastManager = new ToastManager(this);
        this.keyboardManager = new KeyboardManager(this);
        this.contextMenuManager = new ContextMenuManager(this);

        this.init();
    }

    async init() {
        const check = await this.api('api/login.php', 'GET');
        if (!check.authenticated) {
            document.getElementById('login-screen').classList.remove('hidden');
            this.setupLogin();
        } else {
            this.csrfToken = check.csrf_token;
            document.getElementById('app-container').classList.remove('hidden');
            this.setupApp();
        }
    }

    setupLogin() {
        const form = document.getElementById('login-form');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const password = document.getElementById('login-password').value;
            try {
                const result = await this.api('api/login.php', 'POST', { password });
                this.csrfToken = result.csrf_token;
                document.getElementById('login-screen').classList.add('hidden');
                document.getElementById('app-container').classList.remove('hidden');
                this.setupApp();
            } catch (err) {
                document.getElementById('login-error').textContent = err.message || 'Login failed';
            }
        });
    }

    async setupApp() {
        await this.folderManager.loadFolders();
        await this.messageListManager.loadMessages();
        this.composeManager.init();
        this.contactsManager.loadContacts();
        this.settingsManager.loadSettings();
        this.filterManager.loadFilters();
        this.setupEventListeners();
        this.startAutoRefresh();
    }

    setupEventListeners() {
        document.getElementById('global-search').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.searchManager.search(e.target.value);
            }
        });

        document.getElementById('search-btn').addEventListener('click', () => {
            const query = document.getElementById('global-search').value;
            this.searchManager.search(query);
        });

        document.getElementById('compose-btn').addEventListener('click', () => {
            this.composeManager.open();
        });

        document.getElementById('refresh-btn').addEventListener('click', () => {
            this.messageListManager.loadMessages();
        });

        document.getElementById('prev-page').addEventListener('click', () => {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.messageListManager.loadMessages();
            }
        });

        document.getElementById('next-page').addEventListener('click', () => {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.messageListManager.loadMessages();
            }
        });

        document.getElementById('sort-select').addEventListener('change', () => {
            this.messageListManager.loadMessages();
        });

        document.getElementById('sort-dir-select').addEventListener('change', () => {
            this.messageListManager.loadMessages();
        });

        document.getElementById('select-all').addEventListener('change', (e) => {
            const checkboxes = document.querySelectorAll('.msg-checkbox');
            checkboxes.forEach(cb => cb.checked = e.target.checked);
            this.updateSelectedMessages();
        });

        document.getElementById('logout-btn').addEventListener('click', () => {
            window.location.reload();
        });

        document.getElementById('empty-trash-btn').addEventListener('click', async () => {
            if (confirm('Delete all messages in Trash?')) {
                await this.api('api/action.php', 'POST', {
                    action: 'expunge',
                    folder: 'Trash',
                    uids: [],
                    csrf_token: this.csrfToken
                });
                this.toastManager.success('Trash emptied');
                this.messageListManager.loadMessages();
            }
        });

        document.getElementById('empty-spam-btn').addEventListener('click', async () => {
            if (confirm('Delete all messages in Spam?')) {
                await this.api('api/action.php', 'POST', {
                    action: 'delete',
                    folder: 'Spam',
                    uids: [],
                    csrf_token: this.csrfToken
                });
                this.toastManager.success('Spam emptied');
                this.messageListManager.loadMessages();
            }
        });

        document.getElementById('contacts-btn').addEventListener('click', () => {
            this.contactsManager.showModal();
        });

        document.getElementById('settings-btn').addEventListener('click', () => {
            this.settingsManager.showModal();
        });

        document.getElementById('filters-btn').addEventListener('click', () => {
            this.filterManager.showModal();
        });

        this.keyboardManager.init(this);
    }

    startAutoRefresh() {
        const settings = this.settingsManager.settings;
        const interval = settings?.notifications?.auto_refresh || 60;
        if (interval > 0) {
            this.autoRefreshInterval = setInterval(() => {
                this.messageListManager.loadMessages();
                this.folderManager.loadFolders();
            }, interval * 1000);
        }
    }

    async api(endpoint, method = 'GET', data = {}) {
        const options = { method, headers: {} };

        if (method === 'POST') {
            options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            if (this.csrfToken && endpoint !== 'api/login.php') {
                data.csrf_token = this.csrfToken;
            }
            const params = new URLSearchParams(data);
            options.body = params.toString();
        }

        const response = await fetch(endpoint, options);
        const result = await response.json();

        if (result.error) {
            throw new Error(result.error);
        }

        return result;
    }

    async apiWithProgress(endpoint, method = 'GET', data = {}, progressId = 'default') {
        this.progressManager.start(progressId, 'Loading...');

        try {
            const result = await this.api(endpoint, method, data);
            this.progressManager.complete(progressId, 'Complete');
            return result;
        } catch (err) {
            this.progressManager.error(progressId, err.message);
            throw err;
        }
    }

    updateSelectedMessages() {
        const checkboxes = document.querySelectorAll('.msg-checkbox:checked');
        this.selectedMessages.clear();
        checkboxes.forEach(cb => this.selectedMessages.add(cb.dataset.uid));
        this.updateBulkActions();
        this.updateStatusBar();
    }

    updateBulkActions() {
        const toolbar = document.getElementById('bulk-actions-toolbar');
        if (this.selectedMessages.size > 0) {
            toolbar.classList.add('show');
        } else {
            toolbar.classList.remove('show');
        }
    }

    updateStatusBar() {
        document.getElementById('selected-info').textContent =
            this.selectedMessages.size > 0 ? `${this.selectedMessages.size} selected` : '';
    }

    async bulkAction(action, targetFolder = '') {
        if (this.selectedMessages.size === 0) return;

        this.progressManager.start('bulk', 'Processing...');

        try {
            await this.api('api/action.php', 'POST', {
                action,
                folder: this.currentFolder,
                uids: Array.from(this.selectedMessages).join(','),
                target_folder: targetFolder,
                csrf_token: this.csrfToken
            });

            this.selectedMessages.clear();
            this.messageListManager.loadMessages();
            this.toastManager.success('Action completed');
        } catch (err) {
            this.toastManager.error(err.message);
        }

        this.progressManager.complete('bulk', 'Done');
    }
}

class FolderManager {
    constructor(app) {
        this.app = app;
        this.folders = [];
    }

    async loadFolders() {
        try {
            const result = await this.app.api('api/folders.php');
            this.folders = result.folders;
            this.render();
        } catch (err) {
            this.app.toastManager.error('Failed to load folders: ' + err.message);
        }
    }

    render() {
        const container = document.getElementById('folder-list');
        container.innerHTML = '';

        this.folders.forEach(folder => {
            const div = document.createElement('div');
            div.className = `folder-item${folder.shortName === this.app.currentFolder ? ' active' : ''}`;
            div.dataset.folder = folder.shortName;
            div.innerHTML = `
                <span class="folder-name">${folder.shortName}</span>
                ${folder.unread > 0 ? `<span class="folder-count">${folder.unread}</span>` : ''}
            `;
            div.addEventListener('click', () => this.selectFolder(folder.shortName));
            container.appendChild(div);
        });

        document.getElementById('inbox-count').textContent =
            this.folders.find(f => f.shortName === 'INBOX')?.unread || 0;
    }

    selectFolder(folder) {
        this.app.currentFolder = folder;
        this.app.currentPage = 1;
        this.app.selectedMessages.clear();
        this.app.messageListManager.loadMessages();

        document.querySelectorAll('.folder-item').forEach(el => {
            el.classList.toggle('active', el.dataset.folder === folder);
        });
    }
}

class MessageListManager {
    constructor(app) {
        this.app = app;
        this.messages = [];
    }

    async loadMessages() {
        const folder = this.app.currentFolder;
        const page = this.app.currentPage;
        const sort = document.getElementById('sort-select').value;
        const sortDir = document.getElementById('sort-dir-select').value;

        this.app.progressManager.start('messages', 'Loading messages...');

        try {
            const result = await this.app.api('api/messages.php', 'GET', {
                folder,
                page,
                per_page: 50,
                sort,
                sort_dir: sortDir
            });

            this.messages = result.messages;
            this.app.totalPages = result.total_pages || 1;
            this.app.currentPage = result.page || 1;

            this.render();
            this.app.progressManager.complete('messages', 'Loaded');
        } catch (err) {
            this.app.toastManager.error('Failed to load messages: ' + err.message);
            this.app.progressManager.error('messages', err.message);
        }
    }

    render() {
        const tbody = document.getElementById('message-list');
        tbody.innerHTML = '';

        if (this.messages.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;">No messages</td></tr>';
            return;
        }

        this.messages.forEach(msg => {
            const tr = document.createElement('tr');
            tr.className = `${msg.is_read ? '' : 'unread'}${this.app.selectedMessages.has(String(msg.uid)) ? ' selected' : ''}`;
            tr.dataset.uid = msg.uid;

            tr.innerHTML = `
                <td><input type="checkbox" class="msg-checkbox" data-uid="${msg.uid}"></td>
                <td>${this.escapeHtml(msg.from_name || msg.from)}</td>
                <td class="col-subject">
                    ${msg.has_attachment ? '📎 ' : ''}
                    ${this.escapeHtml(msg.subject)}
                </td>
                <td>${this.formatDate(msg.date)}</td>
                <td>${this.formatSize(msg.size)}</td>
            `;

            tr.addEventListener('click', (e) => {
                if (e.target.type !== 'checkbox') {
                    this.selectMessage(msg.uid);
                }
            });

            tr.querySelector('.msg-checkbox').addEventListener('change', () => {
                this.app.updateSelectedMessages();
            });

            tbody.appendChild(tr);
        });

        document.getElementById('page-info').textContent = `Page ${this.app.currentPage} of ${this.app.totalPages}`;
        document.getElementById('folder-info').textContent = `Folder: ${this.app.currentFolder}`;
    }

    selectMessage(uid) {
        this.app.currentMessageUid = uid;
        this.app.messageViewerManager.loadMessage(uid);

        document.querySelectorAll('#message-list tr').forEach(tr => {
            tr.classList.toggle('selected', tr.dataset.uid == uid);
        });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    formatDate(timestamp) {
        if (!timestamp) return '';
        const date = new Date(timestamp * 1000);
        const now = new Date();
        const diff = now - date;

        if (diff < 86400000) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } else if (diff < 604800000) {
            return date.toLocaleDateString([], { weekday: 'short' });
        } else {
            return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
        }
    }

    formatSize(bytes) {
        if (!bytes) return '';
        if (bytes < 1024) return bytes + 'B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + 'K';
        return (bytes / 1048576).toFixed(1) + 'M';
    }
}

class MessageViewerManager {
    constructor(app) {
        this.app = app;
        this.currentMessage = null;
    }

    async loadMessage(uid) {
        this.app.progressManager.start('viewer', 'Loading...');

        try {
            const result = await this.app.api('api/message.php', 'GET', {
                folder: this.app.currentFolder,
                uid: uid
            });

            this.currentMessage = result;
            this.render();
            this.app.progressManager.complete('viewer', 'Loaded');
        } catch (err) {
            this.app.toastManager.error('Failed to load message: ' + err.message);
            this.app.progressManager.error('viewer', err.message);
        }
    }

    render() {
        document.getElementById('viewer-empty').classList.add('hidden');
        document.getElementById('message-viewer').classList.remove('hidden');

        const msg = this.currentMessage;

        document.getElementById('msg-from').textContent = msg.headers.from.name
            ? `${msg.headers.from.name} <${msg.headers.from.email}>`
            : msg.headers.from.email;
        document.getElementById('msg-to').textContent = msg.headers.to.map(t => t.email).join(', ');

        const ccRow = document.getElementById('msg-cc-row');
        if (msg.headers.cc && msg.headers.cc.length > 0) {
            ccRow.classList.remove('hidden');
            document.getElementById('msg-cc').textContent = msg.headers.cc.map(c => c.email).join(', ');
        } else {
            ccRow.classList.add('hidden');
        }

        document.getElementById('msg-date').textContent = msg.headers.date;
        document.getElementById('msg-subject').textContent = msg.headers.subject;
        document.getElementById('message-body').innerHTML = msg.body_html || `<pre>${msg.body_text}</pre>`;

        const attachmentsSection = document.getElementById('attachments-section');
        if (msg.attachments && msg.attachments.length > 0) {
            attachmentsSection.classList.remove('hidden');
            const list = document.getElementById('attachments-list');
            list.innerHTML = msg.attachments.map(att => `
                <div class="attachment-item" data-part-id="${att.part_id}">
                    <span>${att.filename}</span>
                    <span>${this.formatSize(att.size)}</span>
                </div>
            `).join('');

            list.querySelectorAll('.attachment-item').forEach(item => {
                item.addEventListener('click', () => {
                    this.downloadAttachment(item.dataset.partId);
                });
            });
        } else {
            attachmentsSection.classList.add('hidden');
        }

        document.getElementById('reply-btn').onclick = () => this.reply();
        document.getElementById('reply-all-btn').onclick = () => this.replyAll();
        document.getElementById('forward-btn').onclick = () => this.forward();
        document.getElementById('delete-msg-btn').onclick = () => this.deleteMessage();
        document.getElementById('raw-btn').onclick = () => this.showRaw();
        document.getElementById('print-btn').onclick = () => window.print();

        document.getElementById('download-all-btn').onclick = () => this.downloadAll();
    }

    downloadAttachment(partId) {
        const url = `api/attachments.php?folder=${encodeURIComponent(this.app.currentFolder)}&uid=${this.currentMessage.uid}&part_id=${partId}`;
        window.open(url, '_blank');
    }

    downloadAll() {
        const url = `api/attachments.php?folder=${encodeURIComponent(this.app.currentFolder)}&uid=${this.currentMessage.uid}&download_all=1`;
        window.open(url, '_blank');
    }

    async deleteMessage() {
        if (!confirm('Delete this message?')) return;

        try {
            await this.app.api('api/action.php', 'POST', {
                action: 'delete',
                folder: this.app.currentFolder,
                uids: this.currentMessage.uid,
                csrf_token: this.app.csrfToken
            });

            document.getElementById('viewer-empty').classList.remove('hidden');
            document.getElementById('message-viewer').classList.add('hidden');
            this.app.messageListManager.loadMessages();
            this.app.toastManager.success('Message deleted');
        } catch (err) {
            this.app.toastManager.error(err.message);
        }
    }

    reply() {
        const msg = this.currentMessage;
        this.app.composeManager.open('reply', {
            to: msg.headers.from.email,
            subject: msg.headers.subject.startsWith('Re:') ? msg.headers.subject : 'Re: ' + msg.headers.subject,
            body: `<br><br>On ${msg.headers.date}, ${msg.headers.from.name || msg.headers.from.email} wrote:<br><blockquote>${msg.body_html || msg.body_text}</blockquote>`
        });
    }

    replyAll() {
        const msg = this.currentMessage;
        const to = [msg.headers.from.email];
        const cc = [...(msg.headers.to || []), ...(msg.headers.cc || [])]
            .filter(t => t.email !== this.app.settingsManager.settings?.account?.email)
            .map(t => t.email);

        this.app.composeManager.open('reply', {
            to: to.join(', '),
            cc: cc.join(', '),
            subject: msg.headers.subject.startsWith('Re:') ? msg.headers.subject : 'Re: ' + msg.headers.subject,
            body: `<br><br>On ${msg.headers.date}, ${msg.headers.from.name || msg.headers.from.email} wrote:<br><blockquote>${msg.body_html || msg.body_text}</blockquote>`
        });
    }

    forward() {
        const msg = this.currentMessage;
        this.app.composeManager.open('forward', {
            subject: msg.headers.subject.startsWith('FW:') ? msg.headers.subject : 'FW: ' + msg.headers.subject,
            body: `<br><br>---------- Forwarded message ----------<br>From: ${msg.headers.from.name || msg.headers.from.email}<br>Date: ${msg.headers.date}<br>Subject: ${msg.headers.subject}<br><br>${msg.body_html || msg.body_text}`
        });
    }

    async showRaw() {
        try {
            const result = await this.app.api('api/message.php', 'GET', {
                folder: this.app.currentFolder,
                uid: this.currentMessage.uid,
                raw: 1
            });

            document.getElementById('raw-content').textContent = JSON.stringify(result, null, 2);
            document.getElementById('raw-modal').classList.remove('hidden');
        } catch (err) {
            this.app.toastManager.error(err.message);
        }
    }

    formatSize(bytes) {
        if (!bytes) return '';
        if (bytes < 1024) return bytes + 'B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + 'K';
        return (bytes / 1048576).toFixed(1) + 'M';
    }
}

class ComposeManager {
    constructor(app) {
        this.app = app;
        this.quill = null;
        this.files = [];
    }

    init() {
        this.quill = new Quill('#compose-editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'header': [1, 2, 3, false] }],
                    [{ 'font': [] }],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    ['indent', 'outdent'],
                    ['blockquote', 'code-block'],
                    ['link', 'image'],
                    ['clean']
                ]
            }
        });

        this.setupDropZone();
        this.loadSignatures();
    }

    async loadSignatures() {
        try {
            const result = await this.app.api('api/signatures.php');
            const select = document.getElementById('compose-signature');
            select.innerHTML = '<option value="">No Signature</option>';

            result.signatures.forEach(sig => {
                const option = document.createElement('option');
                option.value = sig.id;
                option.textContent = sig.name;
                if (sig.id === result.default_id) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        } catch (err) {
            console.error('Failed to load signatures:', err);
        }
    }

    setupDropZone() {
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');

        dropZone.addEventListener('click', () => fileInput.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            this.handleFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', (e) => {
            this.handleFiles(e.target.files);
        });
    }

    handleFiles(files) {
        for (const file of files) {
            this.files.push(file);
            this.renderAttachment(file);
        }
    }

    renderAttachment(file) {
        const list = document.getElementById('attachment-list');
        const item = document.createElement('div');
        item.className = 'attachment-file';
        item.innerHTML = `
            <span>${file.name}</span>
            <span class="remove" data-name="${file.name}">&times;</span>
        `;
        list.appendChild(item);

        item.querySelector('.remove').addEventListener('click', () => {
            this.files = this.files.filter(f => f.name !== file.name);
            item.remove();
        });
    }

    open(mode = 'new', data = {}) {
        this.app.composeMode = mode;
        this.app.composeDraftId = null;
        this.files = [];
        document.getElementById('attachment-list').innerHTML = '';

        document.getElementById('compose-title').textContent = mode === 'reply' ? 'Reply' : mode === 'forward' ? 'Forward' : 'New Message';
        document.getElementById('compose-to').value = data.to || '';
        document.getElementById('compose-cc').value = data.cc || '';
        document.getElementById('compose-bcc').value = '';
        document.getElementById('compose-subject').value = data.subject || '';

        if (data.body) {
            this.quill.root.innerHTML = data.body;
        } else {
            this.quill.root.innerHTML = '';
        }

        document.getElementById('compose-modal').classList.remove('hidden');

        this.startAutoSave();
    }

    startAutoSave() {
        if (this.app.draftAutoSaveInterval) {
            clearInterval(this.app.draftAutoSaveInterval);
        }

        this.app.draftAutoSaveInterval = setInterval(() => {
            this.saveDraft();
        }, 120000);
    }

    async saveDraft() {
        const to = document.getElementById('compose-to').value;
        const subject = document.getElementById('compose-subject').value;
        const bodyHtml = this.quill.root.innerHTML;
        const bodyText = this.quill.getText();

        if (!to && !subject && !bodyHtml) return;

        try {
            const result = await this.app.api('api/drafts.php', 'POST', {
                action: 'save',
                id: this.app.composeDraftId || '',
                to,
                subject,
                body_html: bodyHtml,
                body_text: bodyText,
                attachments: JSON.stringify([]),
                csrf_token: this.app.csrfToken
            });

            if (!this.app.composeDraftId) {
                this.app.composeDraftId = result.draft.id;
            }

            document.getElementById('compose-status').textContent = `Draft saved at ${new Date().toLocaleTimeString()}`;
        } catch (err) {
            console.error('Failed to save draft:', err);
        }
    }

    async send() {
        const to = document.getElementById('compose-to').value;
        const cc = document.getElementById('compose-cc').value;
        const bcc = document.getElementById('compose-bcc').value;
        const subject = document.getElementById('compose-subject').value;
        const bodyHtml = this.quill.root.innerHTML;
        const bodyText = this.quill.getText();
        const priority = document.getElementById('compose-priority').value;
        const readReceipt = document.getElementById('compose-read-receipt').checked ? '1' : '';

        if (!to) {
            this.app.toastManager.error('Recipient is required');
            return;
        }

        const status = document.getElementById('compose-status');
        status.textContent = 'Sending...';

        const formData = new FormData();
        formData.append('to', to);
        formData.append('cc', cc);
        formData.append('bcc', bcc);
        formData.append('subject', subject);
        formData.append('body_html', bodyHtml);
        formData.append('body_text', bodyText);
        formData.append('priority', priority);
        formData.append('read_receipt', readReceipt);
        formData.append('csrf_token', this.app.csrfToken);

        this.app.files.forEach(file => {
            formData.append('attachments[]', file);
        });

        this.app.progressManager.start('send', 'Sending email...');

        try {
            const response = await fetch('api/send.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.error) {
                throw new Error(result.error);
            }

            if (this.app.composeDraftId) {
                await this.app.api('api/drafts.php', 'POST', {
                    action: 'delete',
                    id: this.app.composeDraftId,
                    csrf_token: this.app.csrfToken
                });
            }

            this.close();
            this.app.toastManager.success('Email sent successfully');
            this.app.messageListManager.loadMessages();
        } catch (err) {
            status.textContent = 'Failed: ' + err.message;
            this.app.toastManager.error(err.message);
        }

        this.app.progressManager.complete('send', 'Sent');
    }

    close() {
        document.getElementById('compose-modal').classList.add('hidden');
        if (this.app.draftAutoSaveInterval) {
            clearInterval(this.app.draftAutoSaveInterval);
        }
    }
}

class ContactsManager {
    constructor(app) {
        this.app = app;
        this.contacts = [];
    }

    async loadContacts() {
        try {
            const result = await this.app.api('api/contacts.php');
            this.contacts = result.contacts || [];
        } catch (err) {
            console.error('Failed to load contacts:', err);
        }
    }

    showModal() {
        document.getElementById('contacts-modal').classList.remove('hidden');
        this.render();
    }

    hideModal() {
        document.getElementById('contacts-modal').classList.add('hidden');
    }

    render() {
        const list = document.getElementById('contacts-list');
        const search = document.getElementById('contacts-search').value.toLowerCase();

        const filtered = this.contacts.filter(c =>
            !search || c.name.toLowerCase().includes(search) || c.email.toLowerCase().includes(search)
        );

        list.innerHTML = filtered.map(c => `
            <div class="contact-card">
                <div class="contact-info">
                    <h4>${this.escapeHtml(c.name)}</h4>
                    <p>${this.escapeHtml(c.email)}</p>
                    ${c.company ? `<p>${this.escapeHtml(c.company)}</p>` : ''}
                </div>
                <div class="contact-actions">
                    <button onclick="app.contactsManager.edit('${c.id}')">Edit</button>
                    <button onclick="app.contactsManager.delete('${c.id}')">Delete</button>
                </div>
            </div>
        `).join('');
    }

    async create() {
        const name = prompt('Name:');
        const email = prompt('Email:');
        if (!name || !email) return;

        try {
            await this.app.api('api/contacts.php', 'POST', {
                action: 'create',
                name,
                email,
                csrf_token: this.app.csrfToken
            });
            await this.loadContacts();
            this.render();
            this.app.toastManager.success('Contact created');
        } catch (err) {
            this.app.toastManager.error(err.message);
        }
    }

    async edit(id) {
        const contact = this.contacts.find(c => c.id === id);
        if (!contact) return;

        const name = prompt('Name:', contact.name);
        const email = prompt('Email:', contact.email);
        const company = prompt('Company:', contact.company || '');

        try {
            await this.app.api('api/contacts.php', 'POST', {
                action: 'update',
                id,
                name,
                email,
                company,
                csrf_token: this.app.csrfToken
            });
            await this.loadContacts();
            this.render();
            this.app.toastManager.success('Contact updated');
        } catch (err) {
            this.app.toastManager.error(err.message);
        }
    }

    async delete(id) {
        if (!confirm('Delete this contact?')) return;

        try {
            await this.app.api('api/contacts.php', 'POST', {
                action: 'delete',
                id,
                csrf_token: this.app.csrfToken
            });
            await this.loadContacts();
            this.render();
            this.app.toastManager.success('Contact deleted');
        } catch (err) {
            this.app.toastManager.error(err.message);
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

class SearchManager {
    constructor(app) {
        this.app = app;
    }

    async search(query) {
        if (!query) return;

        this.app.progressManager.start('search', 'Searching...');

        try {
            const result = await this.app.api('api/search.php', 'GET', { query });

            this.app.messageListManager.messages = result.results;
            this.app.messageListManager.render();

            this.app.toastManager.info(`Found ${result.total} results`);
            this.app.progressManager.complete('search', 'Done');
        } catch (err) {
            this.app.toastManager.error(err.message);
            this.app.progressManager.error('search', err.message);
        }
    }

    showAdvancedSearch() {
        document.getElementById('advanced-search-modal').classList.remove('hidden');
    }
}

class SettingsManager {
    constructor(app) {
        this.app = app;
        this.settings = {};
    }

    async loadSettings() {
        try {
            const result = await this.app.api('api/settings.php');
            this.settings = result;
        } catch (err) {
            console.error('Failed to load settings:', err);
        }
    }

    showModal() {
        document.getElementById('settings-modal').classList.remove('hidden');
        this.render();
    }

    hideModal() {
        document.getElementById('settings-modal').classList.add('hidden');
    }

    render() {
        const content = document.getElementById('settings-content');
        content.innerHTML = `
            <div class="settings-group">
                <h3>Account</h3>
                <div class="settings-option">
                    <label>Display Name:</label>
                    <input type="text" id="setting-display-name" value="${this.settings.account?.display_name || ''}">
                </div>
                <div class="settings-option">
                    <label>Reply-To:</label>
                    <input type="text" id="setting-reply-to" value="${this.settings.account?.reply_to || ''}">
                </div>
            </div>
            <div class="settings-group">
                <h3>Reading</h3>
                <div class="settings-option">
                    <label>Mark as read after (seconds):</label>
                    <input type="number" id="setting-mark-read" value="${this.settings.reading?.mark_read_after || 0}">
                </div>
                <div class="settings-option">
                    <label>Show images:</label>
                    <select id="setting-show-images">
                        <option value="ask" ${this.settings.reading?.show_images === 'ask' ? 'selected' : ''}>Ask</option>
                        <option value="yes" ${this.settings.reading?.show_images === 'yes' ? 'selected' : ''}>Always</option>
                        <option value="no" ${this.settings.reading?.show_images === 'no' ? 'selected' : ''}>Never</option>
                    </select>
                </div>
            </div>
            <div class="settings-group">
                <h3>Notifications</h3>
                <div class="settings-option">
                    <label>Auto-refresh (seconds, 0=off):</label>
                    <input type="number" id="setting-auto-refresh" value="${this.settings.notifications?.auto_refresh || 60}">
                </div>
            </div>
            <div class="settings-group">
                <h3>Display</h3>
                <div class="settings-option">
                    <label>Emails per page:</label>
                    <select id="setting-per-page">
                        <option value="25" ${this.settings.display?.emails_per_page === 25 ? 'selected' : ''}>25</option>
                        <option value="50" ${this.settings.display?.emails_per_page === 50 ? 'selected' : ''}>50</option>
                        <option value="100" ${this.settings.display?.emails_per_page === 100 ? 'selected' : ''}>100</option>
                    </select>
                </div>
            </div>
        `;

        document.querySelectorAll('#settings-content input, #settings-content select').forEach(input => {
            input.addEventListener('change', () => this.saveSetting(input));
        });
    }

    async saveSetting(input) {
        const key = input.id.replace('setting-', '').replace('-', '.');
        const value = input.type === 'number' ? parseInt(input.value) : input.value;

        try {
            const keys = key.split('.');
            await this.app.api('api/settings.php', 'POST', {
                key,
                value: typeof value === 'object' ? JSON.stringify(value) : value,
                csrf_token: this.app.csrfToken
            });

            this.settings = { ...this.settings, [keys[0]]: { ...this.settings[keys[0]], [keys[1]]: value } };
            this.app.toastManager.success('Setting saved');
        } catch (err) {
            this.app.toastManager.error(err.message);
        }
    }
}

class FilterManager {
    constructor(app) {
        this.app = app;
        this.filters = [];
    }

    async loadFilters() {
        try {
            const result = await this.app.api('api/filters.php');
            this.filters = result.filters || [];
        } catch (err) {
            console.error('Failed to load filters:', err);
        }
    }

    showModal() {
        document.getElementById('filters-modal').classList.remove('hidden');
        this.render();
    }

    hideModal() {
        document.getElementById('filters-modal').classList.add('hidden');
    }

    render() {
        const list = document.getElementById('filters-list');

        if (this.filters.length === 0) {
            list.innerHTML = '<p style="text-align:center;padding:20px;color:var(--text-secondary)">No filters configured</p>';
            return;
        }

        list.innerHTML = this.filters.map(f => `
            <div class="filter-item">
                <div class="filter-header">
                    <span class="filter-name">${this.escapeHtml(f.name)}</span>
                    <span class="filter-toggle ${f.enabled ? '' : 'disabled'}">
                        ${f.enabled ? 'Enabled' : 'Disabled'}
                    </span>
                </div>
                <div class="filter-conditions">Conditions: ${f.conditions.length}</div>
                <div class="filter-actions">Actions: ${f.actions.length}</div>
                <button onclick="app.filterManager.toggle('${f.id}')">Toggle</button>
                <button onclick="app.filterManager.delete('${f.id}')">Delete</button>
            </div>
        `).join('');
    }

    async toggle(id) {
        try {
            await this.app.api('api/filters.php', 'POST', {
                action: 'toggle',
                id,
                csrf_token: this.app.csrfToken
            });
            await this.loadFilters();
            this.render();
        } catch (err) {
            this.app.toastManager.error(err.message);
        }
    }

    async delete(id) {
        if (!confirm('Delete this filter?')) return;

        try {
            await this.app.api('api/filters.php', 'POST', {
                action: 'delete',
                id,
                csrf_token: this.app.csrfToken
            });
            await this.loadFilters();
            this.render();
            this.app.toastManager.success('Filter deleted');
        } catch (err) {
            this.app.toastManager.error(err.message);
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

class ProgressManager {
    constructor(app) {
        this.app = app;
    }

    start(id, label) {
        const container = document.getElementById('global-progress-container');
        container.classList.remove('hidden');

        const fill = document.getElementById('global-progress-fill');
        fill.className = '';
        fill.style.width = '0%';

        document.getElementById('global-progress-label').textContent = label;
        document.getElementById('global-progress-percent').textContent = '0%';
    }

    update(id, percent, label) {
        document.getElementById('global-progress-fill').style.width = percent + '%';
        document.getElementById('global-progress-percent').textContent = Math.round(percent) + '%';

        if (label) {
            document.getElementById('global-progress-label').textContent = label;
        }
    }

    complete(id, label) {
        const fill = document.getElementById('global-progress-fill');
        fill.style.width = '100%';
        fill.classList.add('progress-success');
        document.getElementById('global-progress-percent').textContent = '100%';

        if (label) {
            document.getElementById('global-progress-label').textContent = label;
        }

        setTimeout(() => {
            document.getElementById('global-progress-container').classList.add('hidden');
            fill.classList.remove('progress-success');
        }, 1500);
    }

    error(id, message) {
        const fill = document.getElementById('global-progress-fill');
        fill.classList.add('progress-error');
        document.getElementById('global-progress-label').textContent = message || 'Error';

        setTimeout(() => {
            document.getElementById('global-progress-container').classList.add('hidden');
            fill.classList.remove('progress-error');
        }, 3000);
    }
}

class ToastManager {
    constructor(app) {
        this.app = app;
        this.container = document.getElementById('toast-container');
    }

    show(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <span>${message}</span>
            <span class="toast-close">&times;</span>
        `;

        toast.querySelector('.toast-close').addEventListener('click', () => {
            toast.remove();
        });

        this.container.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 4000);
    }

    success(message) { this.show(message, 'success'); }
    error(message) { this.show(message, 'error'); }
    warning(message) { this.show(message, 'warning'); }
    info(message) { this.show(message, 'info'); }
}

class KeyboardManager {
    constructor(app) {
        this.app = app;
    }

    init(app) {
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                if (e.ctrlKey && e.key === 'Enter') {
                    if (document.getElementById('compose-modal').classList.contains('hidden') === false) {
                        e.preventDefault();
                        app.composeManager.send();
                    }
                }
                return;
            }

            switch (e.key) {
                case 'c':
                    if (!e.ctrlKey && !e.metaKey) {
                        app.composeManager.open();
                    }
                    break;
                case 'r':
                    if (app.currentMessageUid) {
                        app.messageViewerManager.reply();
                    }
                    break;
                case 'f':
                    if (app.currentMessageUid) {
                        app.messageViewerManager.forward();
                    }
                    break;
                case 'd':
                    app.bulkAction('delete');
                    break;
                case 'u':
                    app.bulkAction('unread');
                    break;
                case 's':
                    if (app.currentMessageUid) {
                        app.api('api/action.php', 'POST', {
                            action: 'flag',
                            folder: app.currentFolder,
                            uids: app.currentMessageUid,
                            csrf_token: app.csrfToken
                        });
                    }
                    break;
                case 'j':
                    const current = document.querySelector('#message-list tr.selected');
                    const next = current ? current.nextElementSibling : document.querySelector('#message-list tr:first-child');
                    if (next) {
                        next.click();
                    }
                    break;
                case 'k':
                    const prev = document.querySelector('#message-list tr.selected')?.previousElementSibling;
                    if (prev) {
                        prev.click();
                    }
                    break;
                case 'g':
                    break;
                case '?':
                    document.getElementById('keyboard-help-modal').classList.remove('hidden');
                    break;
                case 'Escape':
                    document.querySelectorAll('.modal:not(.hidden)').forEach(m => m.classList.add('hidden'));
                    break;
            }

            if (e.key === 'g' && !e.repeat) {
                const nextKey = (e2) => {
                    document.removeEventListener('keydown', nextKey);
                    switch (e2.key) {
                        case 'i':
                            app.folderManager.selectFolder('INBOX');
                            break;
                        case 's':
                            app.folderManager.selectFolder('Sent');
                            break;
                        case 'd':
                            app.folderManager.selectFolder('Drafts');
                            break;
                    }
                };
                setTimeout(() => document.removeEventListener('keydown', nextKey), 1000);
                document.addEventListener('keydown', nextKey);
            }
        });

        document.getElementById('close-help').onclick = () => {
            document.getElementById('keyboard-help-modal').classList.add('hidden');
        };
    }
}

class ContextMenuManager {
    constructor(app) {
        this.app = app;
        this.menu = null;
    }

    init() {
        document.addEventListener('contextmenu', (e) => {
            const row = e.target.closest('#message-table tbody tr');
            if (row) {
                e.preventDefault();
                this.show(row, e.pageX, e.pageY);
            }
        });

        document.addEventListener('click', () => this.hide());
    }

    show(row, x, y) {
        this.hide();

        this.menu = document.createElement('div');
        this.menu.className = 'context-menu';
        this.menu.style.cssText = `
            position: fixed;
            left: ${x}px;
            top: ${y}px;
            background: var(--panel-bg);
            border: 2px solid var(--accent-primary);
            z-index: 1000;
        `;

        const actions = [
            { label: 'Reply', action: () => this.app.messageViewerManager.reply() },
            { label: 'Forward', action: () => this.app.messageViewerManager.forward() },
            { label: 'Mark as Read', action: () => this.app.bulkAction('read') },
            { label: 'Mark as Unread', action: () => this.app.bulkAction('unread') },
            { label: 'Star', action: () => this.app.bulkAction('flag') },
            { label: 'Delete', action: () => this.app.bulkAction('delete') }
        ];

        this.menu.innerHTML = actions.map(a => `
            <div class="context-menu-item" style="padding:8px 15px;cursor:pointer;hover:background:var(--selected-bg)">
                ${a.label}
            </div>
        `).join('');

        this.menu.querySelectorAll('.context-menu-item').forEach((item, i) => {
            item.addEventListener('click', () => {
                actions[i].action();
                this.hide();
            });
        });

        document.body.appendChild(this.menu);
    }

    hide() {
        if (this.menu) {
            this.menu.remove();
            this.menu = null;
        }
    }
}

const app = new MailApp();