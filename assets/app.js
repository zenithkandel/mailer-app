(function() {
    const AppState = {
        folder: 'INBOX',
        page: 1,
        openUid: null,
        search: '',
        selectedUids: [],
        messages: [],
        total: 0,
        totalPages: 1,
        signatures: [],
        editingSignatureId: null
    };

    let eventSource = null;

    function $(selector) {
        return document.querySelector(selector);
    }

    function $$(selector) {
        return document.querySelectorAll(selector);
    }

    function html(str, ...values) {
        return str.replace(/\{(\d+)\}/g, (_, i) => escapeHtml(values[i]));
    }

    function escapeHtml(str) {
        if (typeof str !== 'string') return str;
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function showToast(message, type = '') {
        const container = $('#toast-container');
        const toast = document.createElement('div');
        toast.className = 'toast' + (type ? ' ' + type : '');
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    }

    function showProgress(pct, msg) {
        const bar = $('#progress-bar');
        const fill = $('#progress-fill');
        bar.classList.add('active');
        fill.style.width = pct + '%';
        fill.classList.remove('success', 'error');
        if (pct === 100) {
            fill.classList.add('success');
            setTimeout(() => bar.classList.remove('active'), 1500);
        } else if (pct === 0) {
            fill.classList.add('error');
            setTimeout(() => bar.classList.remove('active'), 1500);
        }
    }

    async function apiCall(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                ...options.headers
            },
            credentials: 'same-origin'
        });
        if (response.status === 401) {
            window.location.href = 'index.php';
            return null;
        }
        return response.json();
    }

    async function loadFolders() {
        const data = await apiCall('api/folders.php');
        if (!data) return;

        if (data.error) {
            showToast('Error loading folders: ' + data.error, 'error');
            console.error('Folders API error:', data.error);
            return;
        }

        const list = $('#folder-list');
        list.innerHTML = '';

        data.folders.forEach(folder => {
            const item = document.createElement('div');
            item.className = 'folder-item' + (folder.name === AppState.folder ? ' active' : '');
            item.dataset.folder = folder.name;
            const htmlContent = folder.unread
                ? '<span class="folder-name">' + escapeHtml(folder.displayName) + '</span><span class="folder-count">' + folder.unread + '</span>'
                : '<span class="folder-name">' + escapeHtml(folder.displayName) + '</span>';
            item.innerHTML = htmlContent;
            item.addEventListener('click', () => selectFolder(folder.name));
            list.appendChild(item);
        });

        const trashBtn = $('#empty-trash-btn');
        const isTrash = AppState.folder.toLowerCase() === 'trash';
        trashBtn.style.display = isTrash ? 'block' : 'none';

        const moveSelect = $('#move-to-folder');
        moveSelect.innerHTML = '<option value="">Move to...</option>';
        data.folders.forEach(folder => {
            if (folder.name !== AppState.folder) {
                const opt = document.createElement('option');
                opt.value = folder.name;
                opt.textContent = folder.displayName;
                moveSelect.appendChild(opt);
            }
        });
    }

    async function selectFolder(folder) {
        AppState.folder = folder;
        AppState.page = 1;
        AppState.search = '';
        AppState.openUid = null;
        AppState.selectedUids = [];
        $('#search-input').value = '';
        await loadMessages();
        await loadFolders();
        showMessagePane(null);
    }

    async function loadMessages() {
        showProgress(10, 'Connecting...');
        const params = new URLSearchParams({
            folder: AppState.folder,
            page: AppState.page
        });

        if (AppState.search) {
            params.set('search', AppState.search);
            params.set('searchField', $('#search-field').value);
            params.set('searchScope', $('#search-scope').value);
        }

        const data = await apiCall('api/messages.php?' + params);
        if (!data) return;

        if (data.error) {
            showToast('Error loading messages: ' + data.error, 'error');
            console.error('Messages API error:', data.error);
            return;
        }

        showProgress(50, 'Fetching messages...');

        AppState.messages = data.messages || [];
        AppState.total = data.total || 0;
        AppState.totalPages = data.totalPages || 1;

        renderMessageList();

        const folderName = AppState.folder.replace(/^INBOX\.?/i, '') || 'Inbox';
        $('#folder-name').textContent = folderName;
        $('#message-count').textContent = data.total + ' messages';

        showProgress(100, 'Done');
    }

    function renderMessageList() {
        const list = $('#message-list');
        if (AppState.messages.length === 0) {
            list.innerHTML = '<div class="loading">No messages</div>';
            return;
        }

        list.innerHTML = AppState.messages.map(msg => {
            const isUnread = !msg.flags.includes('seen');
            const isSelected = AppState.selectedUids.includes(msg.uid);
            const hasFlag = msg.flags.includes('flagged');
            const rowClass = (isUnread ? 'unread' : '') + (isSelected ? ' selected' : '');
            const fromHtml = hasFlag ? '<span class="flag-icon">★</span>' + escapeHtml(msg.from) : escapeHtml(msg.from);
            const subjectHtml = msg.hasAttachment ? escapeHtml(msg.subject) + '<span class="attachment-icon">📎</span>' : escapeHtml(msg.subject);
            
            return '<div class="message-row ' + rowClass + '" data-uid="' + msg.uid + '">' +
                '<input type="checkbox" class="checkbox" ' + (isSelected ? 'checked' : '') + '>' +
                '<div class="message-info">' +
                '<div class="message-from">' + fromHtml + '</div>' +
                '<div class="message-subject">' + subjectHtml + '</div>' +
                '</div>' +
                '<div class="message-date">' + msg.date + '</div>' +
                '</div>';
        }).join('');

        list.querySelectorAll('.message-row').forEach(row => {
            row.addEventListener('click', (e) => {
                if (e.target.classList.contains('checkbox')) return;
                const uid = parseInt(row.dataset.uid);
                openMessage(uid);
            });
        });

        list.querySelectorAll('.checkbox').forEach(cb => {
            cb.addEventListener('change', (e) => {
                const uid = parseInt(e.target.closest('.message-row').dataset.uid);
                toggleSelection(uid, e.target.checked);
            });
        });

        renderPagination();
        updateBulkActions();
    }

    function toggleSelection(uid, selected) {
        if (selected) {
            if (!AppState.selectedUids.includes(uid)) {
                AppState.selectedUids.push(uid);
            }
        } else {
            AppState.selectedUids = AppState.selectedUids.filter(id => id !== uid);
        }

        const row = document.querySelector(`.message-row[data-uid="${uid}"]`);
        if (row) {
            row.classList.toggle('selected', AppState.selectedUids.includes(uid));
        }

        updateBulkActions();
    }

    function updateBulkActions() {
        const bulk = $('#bulk-actions');
        const count = AppState.selectedUids.length;

        if (count > 0) {
            bulk.style.display = 'flex';
            $('#selected-count').textContent = count + ' selected';
        } else {
            bulk.style.display = 'none';
        }
    }

    function renderPagination() {
        const p = $('#pagination');
        if (AppState.totalPages <= 1) {
            p.innerHTML = '';
            return;
        }

        let html = `<button ${AppState.page === 1 ? 'disabled' : ''} onclick="changePage(${AppState.page - 1})">Prev</button>`;
        html += `<span>Page ${AppState.page} of ${AppState.totalPages}</span>`;
        html += `<button ${AppState.page >= AppState.totalPages ? 'disabled' : ''} onclick="changePage(${AppState.page + 1})">Next</button>`;
        p.innerHTML = html;
    }

    window.changePage = async function(page) {
        if (page < 1 || page > AppState.totalPages) return;
        AppState.page = page;
        await loadMessages();
    };

    async function openMessage(uid) {
        AppState.openUid = uid;
        const msg = AppState.messages.find(m => m.uid === uid);
        if (!msg) return;

        const pane = $('#message-pane');
        pane.innerHTML = '<div class="loading">Loading...</div>';

        const data = await apiCall('api/message.php?uid=' + uid + '&folder=' + encodeURIComponent(AppState.folder));
        if (!data) return;

        renderMessageView(data);

        setTimeout(() => {
            apiCall('api/action.php?action=mark-read&uids=' + uid + '&folder=' + encodeURIComponent(AppState.folder), { method: 'POST' });
            msg.flags = msg.flags + ' seen';
            renderMessageList();
        }, 2000);
    }

    function renderMessageView(msg) {
        const pane = $('#message-pane');
        const hasAttachments = msg.attachments && msg.attachments.length > 0;

        let ccHtml = '';
        if (msg.cc && msg.cc.length > 0) {
            const ccList = msg.cc.map(c => escapeHtml(c.name || c.email)).join(', ');
            ccHtml = '<div class="message-meta-row"><label>CC:</label><span>' + ccList + '</span></div>';
        }

        let attachmentsHtml = '';
        if (hasAttachments) {
            const attachmentItems = msg.attachments.map(att => {
                return '<div class="attachment-item">' +
                    '<span>' + escapeHtml(att.filename) + '</span>' +
                    '<span class="size">(' + formatBytes(att.size) + ')</span>' +
                    '<button class="btn btn-small" onclick="downloadAttachment(' + msg.uid + ', \'' + encodeURIComponent(att.filename) + '\', \'' + att.part + '\')">Download</button>' +
                    '</div>';
            }).join('');
            attachmentsHtml = '<div class="message-attachments"><h4>Attachments (' + msg.attachments.length + ')</h4><div class="attachment-list">' + attachmentItems + '</div></div>';
        }

        pane.innerHTML = '<div class="message-view">' +
            '<div class="message-view-header">' +
            '<h2>' + escapeHtml(msg.subject) + '</h2>' +
            '<div class="message-meta-row"><label>From:</label><span>' + escapeHtml(msg.from.name) + ' &lt;' + escapeHtml(msg.from.email) + '&gt;</span></div>' +
            '<div class="message-meta-row"><label>To:</label><span>' + escapeHtml(msg.to.name) + ' &lt;' + escapeHtml(msg.to.email) + '&gt;</span></div>' +
            ccHtml +
            '<div class="message-meta-row"><label>Date:</label><span>' + msg.date + '</span></div>' +
            '</div>' +
            '<div class="message-view-body">' + msg.body + '</div>' +
            attachmentsHtml +
            '<div class="message-actions">' +
            '<button class="btn btn-primary btn-small" onclick="replyMessage(' + msg.uid + ')">Reply</button>' +
            '<button class="btn btn-secondary btn-small" onclick="replyAllMessage(' + msg.uid + ')">Reply All</button>' +
            '<button class="btn btn-secondary btn-small" onclick="forwardMessage(' + msg.uid + ')">Forward</button>' +
            '<button class="btn btn-secondary btn-small" onclick="toggleFlag(' + msg.uid + ')">' + (msg.isFlagged ? 'Unflag' : 'Flag') + '</button>' +
            '<button class="btn btn-danger btn-small" onclick="deleteMessage(' + msg.uid + ')">Delete</button>' +
            '<button class="btn btn-secondary btn-small" onclick="printMessage(' + msg.uid + ')">Print</button>' +
            '</div>' +
            '</div>';
    }

    function showMessagePane(content) {
        const pane = $('#message-pane');
        if (content) {
            pane.innerHTML = content;
        } else {
            pane.innerHTML = '<div class="pane-empty"><p>Select a message to read</p></div>';
        }
    }

    window.formatBytes = function(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    };

    window.downloadAttachment = function(uid, filename, part) {
        const url = 'api/download.php?uid=' + uid + '&folder=' + encodeURIComponent(AppState.folder) + '&part=' + part + '&filename=' + filename;
        const a = document.createElement('a');
        a.href = url;
        a.download = decodeURIComponent(filename);
        a.click();
    };

    window.deleteMessage = async function(uid) {
        const confirmed = confirm('Delete this message?');
        if (!confirmed) return;

        const data = await apiCall('api/action.php', {
            method: 'POST',
            body: new URLSearchParams({
                action: 'delete',
                uids: uid,
                folder: AppState.folder
            })
        });

        if (data && data.success) {
            showToast(data.message);
            AppState.openUid = null;
            showMessagePane(null);
            await loadMessages();
            await loadFolders();
        } else {
            showToast(data?.error || 'Failed to delete', 'error');
        }
    };

    window.toggleFlag = async function(uid) {
        const msg = AppState.messages.find(m => m.uid === uid);
        if (!msg) return;

        const action = msg.flags.includes('flagged') ? 'unflag' : 'flag';
        const data = await apiCall('api/action.php', {
            method: 'POST',
            body: new URLSearchParams({
                action: action,
                uids: uid,
                folder: AppState.folder
            })
        });

        if (data && data.success) {
            msg.flags = action === 'flag' ? msg.flags + ' flagged' : msg.flags.replace(' flagged', '');
            renderMessageList();
            if (AppState.openUid === uid) {
                openMessage(uid);
            }
        }
    };

    window.printMessage = function(uid) {
        window.open('print.php?uid=' + uid + '&folder=' + encodeURIComponent(AppState.folder), '_blank');
    };

    window.replyMessage = function(uid) {
        openCompose('reply', uid);
    };

    window.replyAllMessage = function(uid) {
        openCompose('replyAll', uid);
    };

    window.forwardMessage = function(uid) {
        openCompose('forward', uid);
    };

    function openCompose(type = 'new', uid = null) {
        const modal = $('#compose-modal');
        const form = $('#compose-form');
        form.reset();

        document.querySelectorAll('.chip').forEach(c => c.remove());
        $('#attachments-preview').innerHTML = '';

        const title = $('#compose-title');
        window.composeData = { type, uid, attachments: [] };

        if (type !== 'new' && uid) {
            loadMessageForCompose(type, uid);
        } else {
            title.textContent = 'New Message';
            loadSignatures();
        }

        modal.classList.add('active');
    }

    async function loadMessageForCompose(type, uid) {
        const data = await apiCall('api/message.php?uid=' + uid + '&folder=' + encodeURIComponent(AppState.folder));
        if (!data) return;

        const title = $('#compose-title');

        if (type === 'reply' || type === 'replyAll') {
            title.textContent = 'Reply';
            addChip('to', data.from.email);

            if (type === 'replyAll' && data.cc) {
                data.cc.forEach(c => addChip('cc', c.email));
            }

            $('#subject-field').value = data.subject.startsWith('Re:') ? data.subject : 'Re: ' + data.subject;

            const quotedBody = '\n\n--- Original Message ---\nFrom: ' + data.from.name + ' <' + data.from.email + '>\nDate: ' + data.date + '\nSubject: ' + data.subject + '\n\n' + stripHtml(data.body);
            $('#body-field').value = quotedBody;

            window.composeData.replyTo = data.from.email;
        } else if (type === 'forward') {
            title.textContent = 'Forward';
            $('#subject-field').value = data.subject.startsWith('Fwd:') ? data.subject : 'Fwd: ' + data.subject;

            const quotedBody = '\n\n--- Forwarded Message ---\nFrom: ' + data.from.name + ' <' + data.from.email + '>\nDate: ' + data.date + '\nSubject: ' + data.subject + '\n\n' + stripHtml(data.body);
            $('#body-field').value = quotedBody;
        }

        loadSignatures();
    }

    function stripHtml(html) {
        const div = document.createElement('div');
        div.innerHTML = html;
        return div.textContent || div.innerText || '';
    }

    function addChip(field, email) {
        const container = $('#' + field + '-input');
        const chip = document.createElement('span');
        chip.className = 'chip';
        chip.innerHTML = html`{0} <span class="remove" onclick="this.parentElement.remove()">×</span>`, email;
        container.insertBefore(chip, $('#' + field + '-field'));
    }

    window.addChipFromInput = function(field) {
        const input = $('#' + field + '-field');
        const email = input.value.trim().replace(/,$/, '');
        if (email && email.includes('@')) {
            addChip(field, email);
            input.value = '';
        }
    };

    $('#to-field').addEventListener('keypress', (e) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            window.addChipFromInput('to');
        }
    });

    $('#cc-field').addEventListener('keypress', (e) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            window.addChipFromInput('cc');
        }
    });

    $('#bcc-field').addEventListener('keypress', (e) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            window.addChipFromInput('bcc');
        }
    });

    $('#add-cc').addEventListener('click', (e) => {
        e.preventDefault();
        $('#cc-row').style.display = 'flex';
        $('#add-cc').style.display = 'none';
    });

    $('#add-bcc').addEventListener('click', (e) => {
        e.preventDefault();
        $('#bcc-row').style.display = 'flex';
        $('#add-bcc').style.display = 'none';
    });

    async function loadSignatures() {
        const data = await apiCall('api/signatures.php?action=list');
        if (!data) return;

        AppState.signatures = data.signatures || [];

        const select = $('#signature-select');
        select.innerHTML = '<option value="">No signature</option>';

        AppState.signatures.forEach(sig => {
            const opt = document.createElement('option');
            opt.value = sig.id;
            opt.textContent = sig.name;
            if (sig.isDefault) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }

    $('#signature-select').addEventListener('change', function() {
        const sigId = this.value;
        if (!sigId) return;

        const sig = AppState.signatures.find(s => s.id === sigId);
        if (!sig) return;

        const body = $('#body-field');
        body.value += (body.value ? '\n\n' : '') + sig.body;
    });

    $('#attach-btn').addEventListener('click', () => {
        $('#file-input').click();
    });

    $('#file-input').addEventListener('change', async function() {
        for (const file of this.files) {
            const formData = new FormData();
            formData.append('file', file);

            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const pct = Math.round((e.loaded / e.total) * 100);
                    showProgress(pct, 'Uploading ' + file.name);
                }
            });

            xhr.onload = async function() {
                if (xhr.status === 200) {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        window.composeData.attachments.push({
                            filename: data.filename,
                            path: data.path,
                            size: data.size,
                            mime: data.mime
                        });

                        const preview = $('#attachments-preview');
                        const item = document.createElement('div');
                        item.className = 'attachment-preview';
                        item.innerHTML = '<span>' + escapeHtml(data.filename) + '</span><span class="size">(' + formatBytes(data.size) + ')</span><span class="remove" onclick="removeAttachment(this, \'' + data.path + '\')">×</span>';
                        preview.appendChild(item);
                    } else {
                        showToast(data.error || 'Upload failed', 'error');
                    }
                }
            };

            xhr.open('POST', 'api/upload.php');
            xhr.send(formData);
        }
        this.value = '';
    });

    window.removeAttachment = function(el, path) {
        el.parentElement.remove();
        window.composeData.attachments = window.composeData.attachments.filter(a => a.path !== path);
    };

    $('#compose-form').addEventListener('submit', async (e) => {
        e.preventDefault();

        const toChips = document.querySelectorAll('#to-input .chip');
        const to = Array.from(toChips).map(c => c.textContent.replace('×', '').trim());

        const ccChips = document.querySelectorAll('#cc-input .chip');
        const cc = Array.from(ccChips).map(c => c.textContent.replace('×', '').trim());

        const bccChips = document.querySelectorAll('#bcc-input .chip');
        const bcc = Array.from(bccChips).map(c => c.textContent.replace('×', '').trim());

        if (to.length === 0) {
            showToast('Please enter at least one recipient', 'error');
            return;
        }

        const subject = $('#subject-field').value;
        if (!subject) {
            showToast('Please enter a subject', 'error');
            return;
        }

        const body = $('#body-field').value;

        $('#compose-modal').classList.remove('active');

        if (eventSource) {
            eventSource.close();
        }

        eventSource = new EventSource('api/send.php');

        eventSource.onmessage = function(e) {
            const data = JSON.parse(e.data);
            showProgress(data.pct, data.msg);

            if (data.pct === 100 || data.pct === 0) {
                setTimeout(() => {
                    eventSource.close();
                    eventSource = null;
                }, 1500);
            }
        };

        eventSource.onerror = function() {
            eventSource.close();
            eventSource = null;
            showToast('Connection error', 'error');
        };

        const sendData = {
            to: to,
            cc: cc,
            bcc: bcc,
            subject: subject,
            body: body,
            attachments: window.composeData.attachments,
            replyTo: window.composeData.replyTo || ''
        };

        await fetch('api/send.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(sendData)
        });
    });

    $('#close-compose').addEventListener('click', () => {
        $('#compose-modal').classList.remove('active');
    });

    $('#compose-btn').addEventListener('click', () => openCompose('new'));

    $('#discard-btn').addEventListener('click', () => {
        const body = $('#body-field').value;
        if (body && !confirm('Discard this message?')) return;
        $('#compose-modal').classList.remove('active');
    });

    $('#logout-btn').addEventListener('click', async () => {
        await apiCall('api/auth.php?action=logout');
        window.location.href = 'index.php';
    });

    $('#search-btn').addEventListener('click', async () => {
        const query = $('#search-input').value.trim();
        if (!query) {
            AppState.search = '';
            await loadMessages();
            return;
        }

        AppState.search = query;
        AppState.page = 1;
        await loadMessages();
    });

    $('#search-input').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            $('#search-btn').click();
        }
    });

    $$('.bulk-actions button[data-action]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const action = btn.dataset.action;
            if (AppState.selectedUids.length === 0) return;

            const data = await apiCall('api/action.php', {
                method: 'POST',
                body: new URLSearchParams({
                    action: action,
                    uids: AppState.selectedUids.join(','),
                    folder: AppState.folder
                })
            });

            if (data && data.success) {
                showToast(data.message);
                AppState.selectedUids = [];
                AppState.openUid = null;
                showMessagePane(null);
                await loadMessages();
                await loadFolders();
            } else {
                showToast(data?.error || 'Action failed', 'error');
            }
        });
    });

    $('#move-to-folder').addEventListener('change', async function() {
        const toFolder = this.value;
        if (!toFolder || AppState.selectedUids.length === 0) return;

        const data = await apiCall('api/action.php', {
            method: 'POST',
            body: new URLSearchParams({
                action: 'move',
                uids: AppState.selectedUids.join(','),
                folder: AppState.folder,
                toFolder: toFolder
            })
        });

        if (data && data.success) {
            showToast(data.message);
            AppState.selectedUids = [];
            AppState.openUid = null;
            showMessagePane(null);
            await loadMessages();
            await loadFolders();
        } else {
            showToast(data?.error || 'Move failed', 'error');
        }

        this.value = '';
    });

    $('#empty-trash-btn').addEventListener('click', async () => {
        if (!confirm('Empty Trash? This will permanently delete all messages in Trash.')) return;

        const data = await apiCall('api/action.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'empty-trash' })
        });

        if (data && data.success) {
            showToast(data.message);
            await loadMessages();
            await loadFolders();
        } else {
            showToast(data?.error || 'Failed to empty trash', 'error');
        }
    });

    const selectAllCheckbox = document.createElement('input');
    selectAllCheckbox.type = 'checkbox';
    selectAllCheckbox.className = 'checkbox select-all';
    selectAllCheckbox.style.marginRight = '10px';
    selectAllCheckbox.addEventListener('change', (e) => {
        AppState.messages.forEach(msg => {
            toggleSelection(msg.uid, e.target.checked);
        });
        document.querySelectorAll('#message-list .checkbox').forEach(cb => {
            cb.checked = e.target.checked;
        });
    });

    loadFolders().then(loadMessages);
})();