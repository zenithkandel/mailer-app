<?php
require_once __DIR__ . '/api/config.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$config = loadConfig();
$appName = $config['app_name'] ?? 'Zenith Mail';
$accountEmail = $config['imap']['user'] ?? '';
$csrfToken = csrfGenerate();

$view = $_GET['view'] ?? 'inbox';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?= sanitize($appName) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <!-- Top Nav -->
    <nav class="top-nav">
        <div class="nav-brand">
            <div class="nav-brand-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                    <polyline points="22,6 12,13 2,6" />
                </svg>
            </div>
            <span><?= sanitize($appName) ?></span>
        </div>
        <div class="nav-spacer"></div>
        <div class="nav-account">
            <span class="nav-account-email"><?= sanitize($accountEmail) ?></span>
            <div class="nav-user-avatar"><?= strtoupper(substr($_SESSION['user'] ?? 'A', 0, 1)) ?></div>
            <button class="nav-logout-btn" onclick="logout()" title="Logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
            </button>
        </div>
    </nav>

    <div class="app-body">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-nav">
                <div class="sidebar-section">
                    <div class="sidebar-label">Mail</div>
                    <a href="?view=inbox" class="sidebar-item <?= $view == 'inbox' ? 'active' : '' ?>" data-view="inbox">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 16 12 14 15 10 15 8 12 2 12" />
                            <path
                                d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
                        </svg>
                        <span class="nav-label">Inbox</span>
                        <span class="badge" id="inboxBadge"></span>
                    </a>
                    <a href="?view=sent" class="sidebar-item <?= $view == 'sent' ? 'active' : '' ?>" data-view="sent">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13" />
                            <polygon points="22 2 15 22 11 13 2 9 22 2" />
                        </svg>
                        <span class="nav-label">Sent</span>
                    </a>
                    <a href="?view=drafts" class="sidebar-item <?= $view == 'drafts' ? 'active' : '' ?>" data-view="drafts">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                        </svg>
                        <span class="nav-label">Drafts</span>
                    </a>
                </div>
                <div class="sidebar-section">
                    <div class="sidebar-label">More</div>
                    <a href="?view=starred" class="sidebar-item <?= $view == 'starred' ? 'active' : '' ?>"
                        data-view="starred">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon
                                points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                        </svg>
                        <span class="nav-label">Starred</span>
                    </a>
                    <a href="?view=trash" class="sidebar-item <?= $view == 'trash' ? 'active' : '' ?>" data-view="trash">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6" />
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                        </svg>
                        <span class="nav-label">Trash</span>
                    </a>
                </div>
                <div class="sidebar-section">
                    <div class="sidebar-label">Actions</div>
                    <a href="?view=compose" class="sidebar-item <?= $view == 'compose' ? 'active' : '' ?>"
                        data-view="compose">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        <span class="nav-label">Compose</span>
                    </a>
                    <a href="?view=settings" class="sidebar-item <?= $view == 'settings' ? 'active' : '' ?>"
                        data-view="settings">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3" />
                            <path
                                d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />
                        </svg>
                        <span class="nav-label">Settings</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <iframe id="contentFrame" class="content-frame" src="pages/<?= $view ?>.php"
                style="width:100%;height:100%;border:none;background:transparent;min-height:calc(100vh - var(--nav-h) - 56px);"></iframe>
        </main>
    </div>

    <script>
        function logout() {
            fetch('api/auth.php?action=logout', { method: 'POST' }).then(() => window.location.href = 'index.php');
        }

        function updateUnreadBadge() {
            fetch('api/mail.php?action=unread_count', { headers: { 'X-CSRF-TOKEN': '<?= $csrfToken ?>' } })
                .then(r => r.json())
                .then(d => {
                    const badge = document.getElementById('inboxBadge');
                    if (d.unread > 0) { badge.textContent = d.unread; } else { badge.textContent = ''; }
                });
        }
        updateUnreadBadge();
        setInterval(updateUnreadBadge, 60000);
    </script>
</body>

</html>