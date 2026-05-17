<?php
session_start();

$config = require __DIR__ . '/config.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$is_api = in_array($action, ['login', 'logout', 'send', 'inbox', 'sent', 'read']);

if ($is_api && !isset($_SESSION['email'])) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "error" => "Session expired"]);
    exit;
}

if ($is_api) {
    header('Content-Type: application/json');
}

if ($action === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($email === $config['email'] && $password === $config['password']) {
        $_SESSION['email'] = $email;
        $_SESSION['password'] = $password;
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => "Invalid credentials"]);
    }
    exit;
}

if ($action === 'logout') {
    session_destroy();
    echo json_encode(["success" => true]);
    exit;
}

if ($action === 'send') {
    require_once __DIR__ . '/smtp_send.php';
    
    $to = $_POST['to'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $body = $_POST['body'] ?? '';
    
    $result = smtp_send($to, $subject, $body, $_SESSION['email'], $_SESSION['password']);
    echo json_encode($result);
    exit;
}

if ($action === 'inbox') {
    require_once __DIR__ . '/imap_inbox.php';
    
    $result = imap_get_inbox($_SESSION['email'], $_SESSION['password']);
    echo json_encode($result);
    exit;
}

if ($action === 'sent') {
    require_once __DIR__ . '/imap_inbox.php';
    
    $result = imap_get_sent($_SESSION['email'], $_SESSION['password']);
    echo json_encode($result);
    exit;
}

if ($action === 'read') {
    require_once __DIR__ . '/imap_inbox.php';
    
    $msgno = $_POST['msgno'] ?? 0;
    $result = imap_read_email($_SESSION['email'], $_SESSION['password'], $msgno);
    echo json_encode($result);
    exit;
}

if (!isset($_SESSION['email'])) {
    header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mailer - Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
            </div>
            <h1>Zenith Mail</h1>
            <form id="loginForm">
                <input type="email" id="email" placeholder="Email" value="<?php echo $config['email']; ?>" required>
                <input type="password" id="password" placeholder="Password" required>
                <button type="submit">Sign In</button>
            </form>
            <div id="loginError" class="error"></div>
        </div>
    </div>
    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const errorDiv = document.getElementById('loginError');
            
            const res = await fetch('index.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=login&email=' + encodeURIComponent(email) + '&password=' + encodeURIComponent(password)
            });
            
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                errorDiv.textContent = data.error || 'Login failed';
            }
        });
    </script>
</body>
</html>
<?php
    exit;
}

if (empty($action) || !$is_api) {
    header('Content-Type: text/html; charset=UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zenith Mail</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="sidebar-header">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
                <span>Zenith Mail</span>
            </div>
            <button class="compose-btn" onclick="showCompose()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Compose
            </button>
            <nav class="nav-menu">
                <button class="nav-item active" data-view="inbox" onclick="loadInbox()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline>
                        <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path>
                    </svg>
                    <span>Inbox</span>
                    <span class="badge" id="inboxBadge" style="display:none">0</span>
                </button>
                <button class="nav-item" data-view="sent" onclick="loadSent()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                    <span>Sent</span>
                </button>
            </nav>
            <div class="sidebar-footer">
                <button class="logout-btn" onclick="logout()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    Sign out
                </button>
            </div>
        </aside>
        
        <main class="main-content">
            <div id="viewInbox" class="view">
                <div class="view-header">
                    <h2>Inbox</h2>
                </div>
                <div class="email-list" id="inboxList">
                    <div class="loading">Loading...</div>
                </div>
            </div>
            
            <div id="viewSent" class="view" style="display:none">
                <div class="view-header">
                    <h2>Sent</h2>
                </div>
                <div class="email-list" id="sentList">
                    <div class="loading">Loading...</div>
                </div>
            </div>
            
            <div id="viewCompose" class="view" style="display:none">
                <div class="view-header">
                    <h2>New Message</h2>
                </div>
                <form class="compose-form" onsubmit="sendEmail(event)">
                    <div class="form-group">
                        <input type="email" id="to" placeholder="To" required>
                    </div>
                    <div class="form-group">
                        <input type="text" id="subject" placeholder="Subject" required>
                    </div>
                    <div class="form-group">
                        <textarea id="body" placeholder="Write your message..." required></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="send-btn">Send</button>
                    </div>
                    <div id="sendStatus"></div>
                </form>
            </div>
            
            <div id="viewRead" class="view" style="display:none">
                <div class="view-header">
                    <button class="back-btn" onclick="goBack()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        Back
                    </button>
                </div>
                <div class="email-detail" id="emailDetail">
                    <div class="loading">Loading...</div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        let currentView = 'inbox';
        
        async function loadInbox() {
            currentView = 'inbox';
            hideAllViews();
            document.getElementById('viewInbox').style.display = 'block';
            document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
            document.querySelector('[data-view="inbox"]').classList.add('active');
            
            const res = await fetch('index.php?action=inbox');
            const data = await res.json();
            
            if (data.success) {
                const list = document.getElementById('inboxList');
                if (data.emails.length === 0) {
                    list.innerHTML = '<div class="empty">No messages</div>';
                } else {
                    list.innerHTML = data.emails.map(e => `
                        <div class="email-item ${e.unread ? 'unread' : ''}" onclick="readEmail(${e.id})">
                            <div class="email-from">${escapeHtml(e.from)}</div>
                            <div class="email-subject">${escapeHtml(e.subject)}</div>
                            <div class="email-date">${formatDate(e.date)}</div>
                        </div>
                    `).join('');
                }
            } else {
                document.getElementById('inboxList').innerHTML = '<div class="error">' + (data.error || 'Failed to load') + '</div>';
            }
        }
        
        async function loadSent() {
            currentView = 'sent';
            hideAllViews();
            document.getElementById('viewSent').style.display = 'block';
            document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
            document.querySelector('[data-view="sent"]').classList.add('active');
            
            const res = await fetch('index.php?action=sent');
            const data = await res.json();
            
            if (data.success) {
                const list = document.getElementById('sentList');
                if (data.emails.length === 0) {
                    list.innerHTML = '<div class="empty">No messages</div>';
                } else {
                    list.innerHTML = data.emails.map(e => `
                        <div class="email-item" onclick="readSentEmail(${e.id})">
                            <div class="email-from">To: ${escapeHtml(e.to)}</div>
                            <div class="email-subject">${escapeHtml(e.subject)}</div>
                            <div class="email-date">${formatDate(e.date)}</div>
                        </div>
                    `).join('');
                }
            } else {
                document.getElementById('sentList').innerHTML = '<div class="error">' + (data.error || 'Failed to load') + '</div>';
            }
        }
        
        function showCompose() {
            currentView = 'compose';
            hideAllViews();
            document.getElementById('viewCompose').style.display = 'block';
            document.getElementById('to').value = '';
            document.getElementById('subject').value = '';
            document.getElementById('body').value = '';
            document.getElementById('sendStatus').innerHTML = '';
        }
        
        async function sendEmail(e) {
            e.preventDefault();
            const to = document.getElementById('to').value;
            const subject = document.getElementById('subject').value;
            const body = document.getElementById('body').value;
            const status = document.getElementById('sendStatus');
            
            status.innerHTML = '<div class="sending">Sending...</div>';
            
            const formData = new URLSearchParams();
            formData.append('action', 'send');
            formData.append('to', to);
            formData.append('subject', subject);
            formData.append('body', body);
            
            const res = await fetch('index.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: formData
            });
            
            const data = await res.json();
            
            if (data.success) {
                status.innerHTML = '<div class="success">Email sent successfully!</div>';
                setTimeout(() => {
                    showCompose();
                }, 1500);
            } else {
                status.innerHTML = '<div class="error">' + (data.error || 'Failed to send') + '</div>';
            }
        }
        
        async function readEmail(msgno) {
            currentView = 'read';
            hideAllViews();
            document.getElementById('viewRead').style.display = 'block';
            
            const res = await fetch('index.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=read&msgno=' + msgno
            });
            
            const data = await res.json();
            
            if (data.success) {
                const e = data.email;
                document.getElementById('emailDetail').innerHTML = `
                    <div class="email-header">
                        <h3>${escapeHtml(e.subject)}</h3>
                        <div class="meta">
                            <span>From: ${escapeHtml(e.from)}</span>
                            <span>${formatDate(e.date)}</span>
                        </div>
                    </div>
                    <div class="email-body">${escapeHtml(e.body)}</div>
                `;
            } else {
                document.getElementById('emailDetail').innerHTML = '<div class="error">' + (data.error || 'Failed to load') + '</div>';
            }
        }
        
        async function readSentEmail(msgno) {
            await readEmail(msgno);
        }
        
        function goBack() {
            if (currentView === 'read') {
                loadInbox();
            }
        }
        
        function hideAllViews() {
            document.getElementById('viewInbox').style.display = 'none';
            document.getElementById('viewSent').style.display = 'none';
            document.getElementById('viewCompose').style.display = 'none';
            document.getElementById('viewRead').style.display = 'none';
        }
        
        async function logout() {
            await fetch('index.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=logout'
            });
            location.reload();
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            const now = new Date();
            if (d.toDateString() === now.toDateString()) {
                return d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            }
            return d.toLocaleDateString([], {month: 'short', day: 'numeric'});
        }
        
        loadInbox();
    </script>
</body>
</html>