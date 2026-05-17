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
        .spa-sidebar { width: 240px; flex-shrink: 0; position: fixed; top: 0; left: 0; height: 100vh; z-index: 100; }
        .spa-main { flex: 1; margin-left: 240px; height: 100vh; }
        .spa-iframe { width: 100%; height: 100%; border: none; }
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
                <a href="#" class="nav-item active" data-page="inbox" onclick="navigate('inbox')">
                    <i class="fa-sharp-duotone fa-thin fa-inbox"></i> Inbox
                    <span class="badge" id="unreadBadge" style="display: none;">0</span>
                </a>
                <a href="#" class="nav-item" data-page="sent" onclick="navigate('sent')">
                    <i class="fa-sharp-duotone fa-thin fa-paper-plane"></i> Sent
                </a>
                <a href="#" class="nav-item" data-page="compose" onclick="navigate('compose')">
                    <i class="fa-sharp-duotone fa-thin fa-pen-nib"></i> Compose
                </a>
                <a href="#" class="nav-item" data-page="search" onclick="navigate('search')">
                    <i class="fa-sharp-duotone fa-thin fa-magnifying-glass"></i> Search
                </a>
                <a href="#" class="nav-item" data-page="settings" onclick="navigate('settings')">
                    <i class="fa-sharp-duotone fa-thin fa-gear"></i> Settings
                </a>
            </nav>

            <div class="sidebar-user">
                <div class="sidebar-user-info">
                    <div class="sidebar-avatar">A</div>
                    <div class="sidebar-user-email"><?php echo htmlspecialchars($_SESSION['user']); ?></div>
                </div>
                <a href="#" class="nav-item" onclick="logout()" style="margin-top: 12px; margin-left: -20px; margin-right: -20px;">
                    <i class="fa-sharp-duotone fa-thin fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </aside>

        <main class="spa-main">
            <iframe id="content-frame" class="spa-iframe" src="pages/inbox-content.php"></iframe>
        </main>
    </div>

    <script>
        function navigate(page) {
            sessionStorage.setItem('mailer_page', page);
            
            document.querySelectorAll('.nav-item[data-page]').forEach(function(item) {
                item.classList.toggle('active', item.dataset.page === page);
            });
            
            document.getElementById('content-frame').src = 'pages/' + page + '-content.php';
        }
        
        function logout() {
            fetch('api.php?action=logout').then(function() {
                window.location.href = 'index.php';
            });
        }
        
        function updateUnreadBadge(count) {
            var badge = document.getElementById('unreadBadge');
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'inline';
            } else {
                badge.style.display = 'none';
            }
        }
        
        window.addEventListener('message', function(e) {
            if (e.data.type === 'unreadCount') {
                updateUnreadBadge(e.data.count);
            }
        });
        
        window.addEventListener('load', function() {
            var savedPage = sessionStorage.getItem('mailer_page') || 'inbox';
            navigate(savedPage);
        });
    </script>
</body>
</html>