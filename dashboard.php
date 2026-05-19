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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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

        .app-container {
            display: flex;
            height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 220px;
            background: var(--bg-secondary);
            border-right: 2px solid var(--border-color);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }

        .sidebar-header {
            padding: 16px;
            border-bottom: 2px solid var(--border-color);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .brand-icon {
            width: 32px;
            height: 32px;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand-icon svg {
            width: 18px;
            height: 18px;
            fill: white;
        }

        .brand-name {
            font-size: 16px;
            font-weight: 700;
        }

        .nav-menu {
            list-style: none;
            padding: 12px;
            flex: 1;
            overflow-y: auto;
        }

        .nav-item {
            margin-bottom: 4px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            border: 2px solid transparent;
            cursor: pointer;
            border-radius: 4px;
        }

        .nav-link:hover,
        .nav-link.active {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        .nav-link.active {
            border-left: 3px solid var(--accent);
            background: var(--accent-light, rgba(232, 123, 53, 0.1));
        }

        .nav-link svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .nav-label {
            flex: 1;
        }

        .nav-badge {
            background: var(--accent);
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
        }

        .sidebar-footer {
            padding: 12px;
            border-top: 2px solid var(--border-color);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
            border-radius: 50%;
        }

        .user-details {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-email {
            font-size: 10px;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .logout-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            color: var(--text-muted);
        }

        .logout-btn:hover {
            color: var(--danger);
        }

        .logout-btn svg {
            width: 16px;
            height: 16px;
        }

        /* Main area */
        .main-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .content-frame {
            flex: 1;
            border: none;
            width: 100%;
            height: 100%;
            background: var(--bg-primary);
        }

        /* Mobile - Bottom Nav */
        .mobile-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-secondary);
            border-top: 2px solid var(--border-dark);
            padding: 8px 12px;
            justify-content: space-around;
            z-index: 100;
        }

        .mobile-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 10px;
            cursor: pointer;
        }

        .mobile-nav-item.active {
            color: var(--accent);
        }

        .mobile-nav-item svg {
            width: 20px;
            height: 20px;
        }

        .mobile-menu {
            display: none;
            position: fixed;
            bottom: 60px;
            right: 12px;
            background: var(--bg-secondary);
            border: 2px solid var(--border-dark);
            box-shadow: 4px 4px 0 rgba(0, 0, 0, 0.2);
            z-index: 101;
        }

        .mobile-menu-item {
            display: block;
            padding: 12px 20px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 13px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
        }

        .mobile-menu-item:last-child {
            border-bottom: none;
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }

            .mobile-nav {
                display: flex;
            }

            .mobile-menu.show {
                display: block;
            }

            .app-container {
                padding-bottom: 60px;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="brand">
                    <div class="brand-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                            <polyline points="22,6 12,13 2,6" />
                        </svg>
                    </div>
                    <span class="brand-name"><?= sanitize($appName) ?></span>
                </div>
            </div>

            <ul class="nav-menu">
                <li class="nav-item"><a href="?view=inbox" class="nav-link <?= $view == 'inbox' ? 'active' : '' ?>"
                        data-view="inbox"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 16 12 14 15 10 15 8 12 2 12" />
                            <path
                                d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
                        </svg><span class="nav-label">Inbox</span><span class="nav-badge" id="inboxBadge"
                            style="display:none"></span></a></li>
                <li class="nav-item"><a href="?view=sent" class="nav-link <?= $view == 'sent' ? 'active' : '' ?>"
                        data-view="sent"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13" />
                            <polygon points="22 2 15 22 11 13 2 9 22 2" />
                        </svg><span class="nav-label">Sent</span></a></li>
                <li class="nav-item"><a href="?view=drafts" class="nav-link <?= $view == 'drafts' ? 'active' : '' ?>"
                        data-view="drafts"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                        </svg><span class="nav-label">Drafts</span></a></li>
                <li class="nav-item"><a href="?view=starred" class="nav-link <?= $view == 'starred' ? 'active' : '' ?>"
                        data-view="starred"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon
                                points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                        </svg><span class="nav-label">Starred</span></a></li>
                <li class="nav-item"><a href="?view=trash" class="nav-link <?= $view == 'trash' ? 'active' : '' ?>"
                        data-view="trash"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6" />
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                        </svg><span class="nav-label">Trash</span></a></li>
                <li class="nav-item"><a href="?view=compose" class="nav-link <?= $view == 'compose' ? 'active' : '' ?>"
                        data-view="compose"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg><span class="nav-label">Compose</span></a></li>
                <li class="nav-item"><a href="?view=settings" class="nav-link <?= $view == 'settings' ? 'active' : '' ?>"
                        data-view="settings"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <circle cx="12" cy="12" r="3" />
                            <path
                                d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />
                        </svg><span class="nav-label">Settings</span></a></li>
            </ul>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?= strtoupper(substr($_SESSION['user'] ?? 'A', 0, 1)) ?></div>
                    <div class="user-details">
                        <div class="user-name"><?= sanitize($_SESSION['user'] ?? 'Admin') ?></div>
                        <div class="user-email"><?= sanitize($accountEmail) ?></div>
                    </div>
                    <button class="logout-btn" onclick="logout()" title="Logout">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <polyline points="16 17 21 12 16 7" />
                            <line x1="21" y1="12" x2="9" y2="12" />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <main class="main-area">
            <iframe id="contentFrame" class="content-frame" src="pages/<?= $view ?>.php"></iframe>
        </main>
    </div>

    <div class="mobile-nav">
        <a href="?view=inbox" class="mobile-nav-item <?= $view == 'inbox' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="22 12 16 12 14 15 10 15 8 12 2 12" />
                <path
                    d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
            </svg>
            Inbox
        </a>
        <a href="?view=compose" class="mobile-nav-item <?= $view == 'compose' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Compose
        </a>
        <a href="?view=sent" class="mobile-nav-item <?= $view == 'sent' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="22" y1="2" x2="11" y2="13" />
                <polygon points="22 2 15 22 11 13 2 9 22 2" />
            </svg>
            Sent
        </a>
        <a href="?view=drafts" class="mobile-nav-item <?= $view == 'drafts' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            </svg>
            Drafts
        </a>
        <div class="mobile-nav-item" onclick="document.getElementById('mobileMenu').classList.toggle('show')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12" />
                <line x1="3" y1="6" x2="21" y2="6" />
                <line x1="3" y1="18" x2="21" y2="18" />
            </svg>
            Menu
        </div>
    </div>

    <div class="mobile-menu" id="mobileMenu">
        <a href="?view=starred" class="mobile-menu-item">Starred</a>
        <a href="?view=trash" class="mobile-menu-item">Trash</a>
        <a href="?view=settings" class="mobile-menu-item">Settings</a>
    </div>

    <script>
        function logout() {
            fetch('api/auth.php?action=logout', { method: 'POST' }).then(() => window.location.href = 'index.php');
        }

        document.querySelectorAll('.mobile-menu-item').forEach(item => {
            item.addEventListener('click', () => document.getElementById('mobileMenu').classList.remove('show'));
        });

        function updateUnreadBadge() {
            fetch('api/mail.php?action=unread_count', { headers: { 'X-CSRF-TOKEN': '<?= $csrfToken ?>' } })
                .then(r => r.json())
                .then(d => {
                    const badge = document.getElementById('inboxBadge');
                    if (d.unread > 0) { badge.textContent = d.unread; badge.style.display = 'block'; }
                    else badge.style.display = 'none';
                });
        }
        updateUnreadBadge();
        setInterval(updateUnreadBadge, 60000);
    </script>
</body>

</html>