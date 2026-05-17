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
        .spa-main { flex: 1; margin-left: 240px; padding: 32px; max-width: 900px; }
        
        .loader-full {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-main);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 200;
        }
        .loader-full.hidden { display: none; }
        
        .loader-full-content {
            text-align: center;
            width: 300px;
        }
        
        .loader-icon {
            font-size: 32px;
            color: var(--accent);
            margin-bottom: 16px;
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.5; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.1); }
        }
        
        .loader-title {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .loader-bar-container {
            background: var(--border);
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin: 16px 0;
        }
        
        .loader-bar {
            height: 100%;
            background: var(--accent);
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 4px;
        }
        
        .loader-bar.steps { 
            background: linear-gradient(90deg, var(--accent) 0%, var(--accent) 25%, var(--border) 25%, var(--border) 50%, var(--border) 50%, var(--border) 75%, var(--border) 75%);
        }
        
        .loader-status {
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .loader-sub {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <div class="loader-full" id="loader">
        <div class="loader-full-content">
            <div class="loader-icon"><i class="fa-sharp-duotone fa-thin fa-circle-notch"></i></div>
            <div class="loader-title" id="loaderTitle">Loading...</div>
            <div class="loader-bar-container">
                <div class="loader-bar" id="loaderBar"></div>
            </div>
            <div class="loader-status" id="loaderStatus">Preparing</div>
            <div class="loader-sub" id="loaderSub"></div>
        </div>
    </div>

    <div class="spa-container" id="app">
        <aside class="sidebar">
            <div class="sidebar-logo">
                <h1><i class="fa-sharp-duotone fa-thin fa-envelope"></i> Mail</h1>
                <p>Webmail</p>
            </div>

            <nav class="sidebar-nav">
                <a href="#" class="nav-item active" data-page="inbox" onclick="loadPage('inbox')">
                    <i class="fa-sharp-duotone fa-thin fa-inbox"></i> Inbox
                    <span class="badge" id="unreadBadge" style="display: none;">0</span>
                </a>
                <a href="#" class="nav-item" data-page="sent" onclick="loadPage('sent')">
                    <i class="fa-sharp-duotone fa-thin fa-paper-plane"></i> Sent
                </a>
                <a href="#" class="nav-item" data-page="compose" onclick="loadPage('compose')">
                    <i class="fa-sharp-duotone fa-thin fa-pen-nib"></i> Compose
                </a>
                <a href="#" class="nav-item" data-page="search" onclick="loadPage('search')">
                    <i class="fa-sharp-duotone fa-thin fa-magnifying-glass"></i> Search
                </a>
                <a href="#" class="nav-item" data-page="settings" onclick="loadPage('settings')">
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

        <main class="spa-main" id="content">
        </main>
    </div>

    <script>
        var currentPage = 'inbox';
        var loader = document.getElementById('loader');
        var loaderTitle = document.getElementById('loaderTitle');
        var loaderBar = document.getElementById('loaderBar');
        var loaderStatus = document.getElementById('loaderStatus');
        var loaderSub = document.getElementById('loaderSub');
        var content = document.getElementById('content');
        
        var pageSteps = {
            'inbox': ['Connecting to mail server', 'Fetching inbox messages', 'Decoding message headers', 'Rendering email list'],
            'sent': ['Connecting to mail server', 'Loading sent folder', 'Processing messages', 'Building sent list'],
            'compose': ['Initializing composer', 'Loading templates', 'Ready to compose'],
            'search': ['Preparing search', 'Building search index', 'Ready to search'],
            'settings': ['Loading user preferences', 'Fetching saved settings', 'Applying configuration']
        };
        
        function showLoader(title, steps) {
            loader.classList.remove('hidden');
            loaderTitle.textContent = title;
            loaderBar.classList.add('steps');
            loaderBar.style.width = '0%';
            
            var stepIndex = 0;
            loaderStatus.textContent = steps[0] || 'Loading...';
            
            var interval = setInterval(function() {
                stepIndex++;
                var progress = (stepIndex / steps.length) * 100;
                loaderBar.style.width = progress + '%';
                
                if (stepIndex < steps.length) {
                    loaderStatus.textContent = steps[stepIndex];
                }
                
                if (stepIndex >= steps.length) {
                    clearInterval(interval);
                }
            }, 400);
            
            return interval;
        }
        
        function hideLoader(interval) {
            if (interval) clearInterval(interval);
            loaderBar.style.width = '100%';
            loaderStatus.textContent = 'Complete';
            setTimeout(function() {
                loader.classList.add('hidden');
                loaderBar.style.width = '0%';
            }, 200);
        }
        
        function loadPage(page, params) {
            currentPage = page;
            sessionStorage.setItem('mailer_page', page);
            
            document.querySelectorAll('.nav-item[data-page]').forEach(function(item) {
                item.classList.toggle('active', item.dataset.page === page);
            });
            
            var steps = pageSteps[page] || ['Loading'];
            var interval = showLoader(page.charAt(0).toUpperCase() + page.slice(1), steps);
            
            var url = 'api.php?action=' + page;
            if (params) {
                Object.keys(params).forEach(function(key) {
                    url += '&' + key + '=' + encodeURIComponent(params[key]);
                });
            }
            
            content.innerHTML = '<div class="loading-placeholder"></div>';
            
            fetch(url).then(function(response) {
                return response.text();
            }).then(function(html) {
                hideLoader(interval);
                content.innerHTML = html;
                
                if (page === 'inbox') {
                    var match = html.match(/unread-count">(\d+)/);
                    if (match) {
                        var count = parseInt(match[1]);
                        var badge = document.getElementById('unreadBadge');
                        if (count > 0) {
                            badge.textContent = count;
                            badge.style.display = 'inline';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                }
            }).catch(function(err) {
                hideLoader(interval);
                content.innerHTML = '<div class="alert alert-error">Error loading page: ' + err.message + '</div>';
            });
        }
        
        function logout() {
            fetch('api.php?action=logout').then(function() {
                window.location.href = 'index.php';
            });
        }
        
        window.addEventListener('load', function() {
            var savedPage = sessionStorage.getItem('mailer_page') || 'inbox';
            loadPage(savedPage);
        });
    </script>
</body>
</html>