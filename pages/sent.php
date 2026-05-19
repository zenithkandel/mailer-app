<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sent</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="page-container">
        <div class="page-toolbar">
            <button class="btn btn-secondary btn-sm" onclick="loadEmails(true)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                Refresh
            </button>
        </div>
        <div class="page-header"><h2>Sent</h2></div>
        <div class="email-list-container">
            <ul class="email-list-simple" id="emailList">
                <li class="empty-state-sm">Loading...</li>
            </ul>
        </div>
    </div>
    <script>
        const CSRF_TOKEN = '<?= $csrfToken ?>';
        let state = { emails: [], page: 1, hasMore: false, loading: false };

        function escHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
        function formatDate(d) { return new Date(d).toLocaleDateString([], { month: 'short', day: 'numeric' }); }

        async function loadEmails(reset = true) {
            if (state.loading) return;
            state.loading = true;
            try {
                const res = await fetch(`../api/sent.php?page=${state.page}`, { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } });
                const data = await res.json();
                state.emails = data.emails || [];
                render();
            } catch (e) { document.getElementById('emailList').innerHTML = '<li class="empty-state-sm">Error loading</li>'; }
            state.loading = false;
        }

        function render() {
            const list = document.getElementById('emailList');
            if (!state.emails.length) { list.innerHTML = '<li class="empty-state-sm">No sent emails</li>'; return; }
            list.innerHTML = state.emails.map(e => `
                <li class="email-item">
                    <span class="email-to">To: ${escHtml(e.to)}</span>
                    <span class="email-subj">${escHtml(e.subject)}</span>
                    <span class="email-date">${formatDate(e.date)}</span>
                </li>
            `).join('');
        }

        loadEmails(true);
    </script>
</body>
</html>