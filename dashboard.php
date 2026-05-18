<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/csrf.php';
require_once __DIR__ . '/api/helpers.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$config = loadConfig();
$csrfToken = csrfToken();
$appName = $config['app_name'] ?? 'Zenith Mail';
$accountEmail = $config['smtp']['user'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self';">
    <title>Dashboard — <?= sanitizeOutput($appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='6' fill='%23E87B35'/><path d='M6 10h20v14H6z' fill='none' stroke='white' stroke-width='2'/><path d='M6 10l10 8 10-8' fill='none' stroke='white' stroke-width='2'/></svg>">
</head>
<body>
    <nav class="top-nav">
        <div class="nav-brand">
            <svg width="28" height="28" viewBox="0 0 32 32" fill="none">
                <rect width="32" height="32" rx="6" fill="#E87B35"/>
                <path d="M6 10h20v14H6z" stroke="white" stroke-width="2" fill="none"/>
                <path d="M6 10l10 8 10-8" stroke="white" stroke-width="2" fill="none"/>
            </svg>
            <span><?= sanitizeOutput($appName) ?></span>
        </div>
        <div class="nav-account">
            <div class="nav-account-info">
                <span class="nav-account-email"><?= sanitizeOutput($accountEmail) ?></span>
            </div>
            <button class="btn-icon" id="logoutBtn" title="Sign out">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </button>
        </div>
    </nav>

    <div class="app-body">
        <aside class="sidebar">
            <button class="sidebar-compose btn-compose" id="composeBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Compose
            </button>
            <nav class="sidebar-nav">
                <button class="sidebar-item active" data-view="inbox" id="inboxNav">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/>
                        <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>
                    </svg>
                    <span>Inbox</span>
                    <span class="badge" id="inboxBadge"></span>
                </button>
                <button class="sidebar-item" data-view="sent" id="sentNav">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"/>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                    <span>Sent</span>
                </button>
            </nav>
        </aside>

        <main class="main-content">
            <div id="viewContainer">
                <div class="view-header">
                    <h2 class="view-title" id="viewTitle">Inbox</h2>
                    <div class="view-actions">
                        <div class="search-box" id="searchBox">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                            <input type="text" id="searchInput" placeholder="Search...">
                        </div>
                    </div>
                </div>
                <div id="emailList" class="email-list"></div>
                <div id="emailDetail" class="email-detail hidden"></div>
                <div id="loadMoreContainer" class="load-more-container hidden">
                    <button class="btn-load-more" id="loadMoreBtn">Load More</button>
                </div>
                <div id="emptyState" class="empty-state hidden">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    <p>No emails found</p>
                </div>
            </div>
        </main>
    </div>

    <div class="modal-overlay" id="composeModal">
        <div class="modal">
            <div class="modal-header">
                <h3>New Message</h3>
                <button class="btn-icon" id="closeComposeBtn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <form id="composeForm">
                <input type="hidden" name="csrf_token" id="composeCsrf" value="<?= $csrfToken ?>">
                <div class="modal-body">
                    <div class="form-row">
                        <label for="composeTo">To</label>
                        <input type="email" id="composeTo" name="to" placeholder="recipient@example.com" required>
                    </div>
                    <div class="form-row">
                        <label for="composeSubject">Subject</label>
                        <input type="text" id="composeSubject" name="subject" placeholder="Subject" required>
                    </div>
                    <div class="form-row">
                        <label for="composeBody">Message</label>
                        <textarea id="composeBody" name="body" rows="10" placeholder="Write your message..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="cancelComposeBtn">Cancel</button>
                    <button type="submit" class="btn-primary" id="sendBtn">
                        <span>Send</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"/>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
    window.APP_CONFIG = {
        csrfToken: '<?= $csrfToken ?>',
        currentView: 'inbox',
        page: 1,
        loading: false,
        hasMore: true,
        searchQuery: ''
    };
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>