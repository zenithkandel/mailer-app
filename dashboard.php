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
$username = $_SESSION['user'] ?? 'Admin';
$initial = strtoupper(substr($username, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($appName) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/quill/2.0.2/quill.snow.min.css" rel="stylesheet">
    <script>
    window.APP_CONFIG = {
        csrfToken: '<?= $csrfToken ?>',
        currentView: '<?= $view ?>',
        page: 1,
        hasMore: true,
        searchQuery: '',
        loading: false,
        inboxUnread: 0
    };
    </script>
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="brand">
                <div class="brand-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                </div>
                <span class="brand-name"><?= sanitize($appName) ?></span>
            </div>
        </div>

        <div class="sidebar-compose">
            <button class="compose-btn" onclick="return window.app.navigateTo('compose')">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Compose
            </button>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Mail</div>
                <a href="?view=inbox" class="nav-link<?= $view == 'inbox' ? ' active' : '' ?>" onclick="return window.app.navigateTo('inbox')">
                    <svg viewBox="0 0 24 24">
                        <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/>
                        <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>
                    </svg>
                    <span class="nav-label">Inbox</span>
                    <span class="nav-badge" id="inboxBadge"></span>
                </a>
                <a href="?view=sent" class="nav-link<?= $view == 'sent' ? ' active' : '' ?>" onclick="return window.app.navigateTo('sent')">
                    <svg viewBox="0 0 24 24">
                        <line x1="22" y1="2" x2="11" y2="13"/>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                    <span class="nav-label">Sent</span>
                </a>
                <a href="?view=drafts" class="nav-link<?= $view == 'drafts' ? ' active' : '' ?>" onclick="return window.app.navigateTo('drafts')">
                    <svg viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    <span class="nav-label">Drafts</span>
                </a>
                <a href="?view=starred" class="nav-link<?= $view == 'starred' ? ' active' : '' ?>" onclick="return window.app.navigateTo('starred')">
                    <svg viewBox="0 0 24 24">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                    <span class="nav-label">Starred</span>
                </a>
                <a href="?view=trash" class="nav-link<?= $view == 'trash' ? ' active' : '' ?>" onclick="return window.app.navigateTo('trash')">
                    <svg viewBox="0 0 24 24">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                    <span class="nav-label">Trash</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= $initial ?></div>
                <div class="user-details">
                    <div class="user-name"><?= sanitize($username) ?></div>
                    <div class="user-email"><?= sanitize($accountEmail) ?></div>
                </div>
                <button class="logout-btn" onclick="window.app.logout()" title="Logout">
                    <svg viewBox="0 0 24 24">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </button>
            </div>
        </div>
    </aside>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-search">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="searchInput" placeholder="Search emails...">
            </div>
            <div class="topbar-actions">
                <button class="topbar-icon-btn" onclick="window.app.refresh()" title="Refresh">
                    <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                </button>
            </div>
        </div>

        <div id="viewContainer"></div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/2.0.2/quill.min.js" integrity="sha512-1nmY9t9/Iq3JU1fGf0OpNCn6uXMmwC1XYX9a6547vnfcjCY1KvU9TE5e8jHQvXBoEH7hcKLIbbOjneZ8HCeNLA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="assets/js/app.js"></script>
</body>
</html>