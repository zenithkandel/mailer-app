<?php
require_once __DIR__ . '/config.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail - Webmail</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://zenithkandel.com.np/fontawesome/zenith-icons.js"></script>
    <style>
        .spa-container { display: flex; min-height: 100vh; }
        .spa-main { flex: 1; margin-left: 240px; padding: 0; min-height: 100vh; }
        .spa-content { max-width: 1000px; margin: 0 auto; padding: 32px 40px; }
        
        .send-progress {
            display: none;
            margin-bottom: 20px;
            padding: 20px;
            background: var(--bg-card);
            border: 1px solid var(--border);
        }
        .send-progress.active { display: block; }
        .progress-bar-container { display: flex; align-items: center; gap: 16px; }
        .progress-spinner {
            width: 24px; height: 24px;
            border: 2px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .progress-text { font-size: 13px; color: var(--text-secondary); }
        .progress-steps { display: flex; gap: 8px; margin-top: 12px; }
        .progress-step { flex: 1; height: 4px; background: var(--border); border-radius: 2px; transition: background 0.3s; }
        .progress-step.active { background: var(--accent); }
        .progress-step.completed { background: var(--success); }

        .email-detail-header { padding: 24px 28px; border-bottom: 1px solid var(--border); }
        .email-detail-body { padding: 28px; min-height: 300px; }
        .email-detail-actions { padding: 16px 28px; border-top: 1px solid var(--border); display: flex; gap: 12px; }

        .loading-spinner {
            display: flex; justify-content: center; align-items: center;
            padding: 60px; color: var(--text-muted);
        }
        .loading-spinner i { font-size: 24px; animation: spin 1s linear infinite; }
    </style>
</head>
<body>
    <div class="spa-container" id="app">
        <aside class="sidebar">
            <div class="sidebar-logo">
                <h1><i class="fa-sharp-duotone fa-thin fa-envelope"></i> Mail</h1>
                <p>Webmail</p>
            </div>

            <nav class="sidebar-nav">
                <a href="#" class="nav-item active" data-page="inbox">
                    <i class="fa-sharp-duotone fa-thin fa-inbox"></i> Inbox
                    <span class="badge" id="unreadBadge" style="display:none">0</span>
                </a>
                <a href="#" class="nav-item" data-page="sent">
                    <i class="fa-sharp-duotone fa-thin fa-paper-plane"></i> Sent
                </a>
                <a href="#" class="nav-item" data-page="compose">
                    <i class="fa-sharp-duotone fa-thin fa-pen-nib"></i> Compose
                </a>
                <a href="#" class="nav-item" data-page="search">
                    <i class="fa-sharp-duotone fa-thin fa-magnifying-glass"></i> Search
                </a>
                <a href="#" class="nav-item" data-page="settings">
                    <i class="fa-sharp-duotone fa-thin fa-gear"></i> Settings
                </a>
            </nav>

            <div class="sidebar-user">
                <div class="sidebar-user-info">
                    <div class="sidebar-avatar">A</div>
                    <div class="sidebar-user-email"><?php echo htmlspecialchars($_SESSION['user']); ?></div>
                </div>
                <a href="#" class="nav-item" data-action="logout" style="margin-top:12px;margin-left:-20px;margin-right:-20px;">
                    <i class="fa-sharp-duotone fa-thin fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </aside>

        <main class="spa-main">
            <div class="spa-content" id="content">
                <div class="loading-spinner"><i class="fa-sharp-duotone fa-thin fa-spinner"></i></div>
            </div>
        </main>
    </div>

    <script>
        const content = document.getElementById('content');
        let currentPage = 'inbox';
        let currentView = 'list';

        // Navigation
        document.querySelectorAll('.nav-item[data-page]').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const page = item.dataset.page;
                currentView = 'list';
                navigate(page);
            });
        });

        // Logout
        document.querySelector('.nav-item[data-action="logout"]').addEventListener('click', async (e) => {
            e.preventDefault();
            await fetch('api.php?action=logout');
            window.location.href = 'index.php';
        });

        async function navigate(page, params = {}) {
            currentPage = page;
            currentView = 'list';
            sessionStorage.setItem('mailer_page', page);
            
            // Update nav
            document.querySelectorAll('.nav-item[data-page]').forEach(item => {
                item.classList.toggle('active', item.dataset.page === page);
            });

            content.innerHTML = '<div class="loading-spinner"><i class="fa-sharp-duotone fa-thin fa-spinner"></i></div>';

            try {
                let url = 'api.php?action=' + page;
                Object.keys(params).forEach(key => url += '&' + key + '=' + encodeURIComponent(params[key]));
                
                const response = await fetch(url);
                if (!response.ok) throw new Error('HTTP ' + response.status);
                const html = await response.text();
                content.innerHTML = html;
                
                // Update unread badge
                if (page === 'inbox') {
                    const match = html.match(/unread-count">(\d+)/);
                    if (match && parseInt(match[1]) > 0) {
                        const badge = document.getElementById('unreadBadge');
                        badge.textContent = match[1];
                        badge.style.display = 'inline';
                    } else {
                        document.getElementById('unreadBadge').style.display = 'none';
                    }
                }
            } catch (err) {
                content.innerHTML = '<div class="alert alert-error">Error loading page</div>';
            }
        }

        async function openEmail(uid) {
            content.innerHTML = '<div class="loading-spinner"><i class="fa-sharp-duotone fa-thin fa-spinner"></i></div>';
            
            try {
                const response = await fetch('api.php?action=read&uid=' + uid + '&from=' + currentPage);
                const html = await response.text();
                content.innerHTML = html;
                currentView = 'read';
            } catch (err) {
                content.innerHTML = '<div class="alert alert-error">Error loading email</div>';
            }
        }

        async function deleteEmail(uid, fromPage) {
            if (!confirm('Delete this email?')) return;
            
            const formData = new FormData();
            formData.append('uid', uid);
            formData.append('from', fromPage);
            
            try {
                const response = await fetch('api.php?action=delete', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    navigate(fromPage);
                } else {
                    alert('Failed to delete email');
                }
            } catch (err) {
                alert('Error deleting email');
            }
        }

        // Initial load
        const savedPage = sessionStorage.getItem('mailer_page') || 'inbox';
        navigate(savedPage);
    </script>
</body>
</html>