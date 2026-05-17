<?php
require_once __DIR__ . '/config.php';

requireLogin();

$success = isset($_GET['success']) && $_GET['success'] === '1';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose - Mail App</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://zenithkandel.com.np/fontawesome/zenith-icons.js"></script>
    <style>
        .send-progress {
            display: none;
            margin-bottom: 20px;
            padding: 20px;
            background: var(--bg-card);
            border: 1px solid var(--border);
        }
        .send-progress.active {
            display: block;
        }
        .progress-bar-container {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .progress-spinner {
            width: 24px;
            height: 24px;
            border: 2px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .progress-text {
            font-size: 13px;
            color: var(--text-secondary);
        }
        .progress-steps {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        .progress-step {
            flex: 1;
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            transition: background 0.3s;
        }
        .progress-step.active {
            background: var(--accent);
        }
        .progress-step.completed {
            background: var(--success);
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-logo">
                <h1><i class="fa-sharp-duotone fa-thin fa-envelope"></i> Mail</h1>
                <p>Webmail</p>
            </div>

            <nav class="sidebar-nav">
                <a href="inbox.php" class="nav-item">
                    <i class="fa-sharp-duotone fa-thin fa-inbox"></i>
                    Inbox
                </a>
                <a href="sent.php" class="nav-item">
                    <i class="fa-sharp-duotone fa-thin fa-paper-plane"></i>
                    Sent
                </a>
                <a href="compose.php" class="nav-item active">
                    <i class="fa-sharp-duotone fa-thin fa-pen-nib"></i>
                    Compose
                </a>
                <a href="search.php" class="nav-item">
                    <i class="fa-sharp-duotone fa-thin fa-magnifying-glass"></i>
                    Search
                </a>
            </nav>

            <div class="sidebar-user">
                <div class="sidebar-user-info">
                    <div class="sidebar-avatar">A</div>
                    <div class="sidebar-user-email"><?php echo htmlspecialchars($_SESSION['user']); ?></div>
                </div>
                <a href="logout.php" class="nav-item" style="margin-top: 12px; margin-left: -20px; margin-right: -20px;">
                    <i class="fa-sharp-duotone fa-thin fa-right-from-bracket"></i>
                    Logout
                </a>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-container">
                <div class="page-header">
                    <h2 class="page-title"><i class="fa-sharp-duotone fa-thin fa-pen-nib"></i> Compose</h2>
                </div>

                <?php if ($success): ?>
                <div class="alert alert-success"><i class="fa-sharp-duotone fa-thin fa-circle-check"></i> Email sent successfully!</div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-error"><i class="fa-sharp-duotone fa-thin fa-circle-exclamation"></i> <?php echo $error; ?></div>
                <?php endif; ?>

                <div class="send-progress" id="sendProgress">
                    <div class="progress-bar-container">
                        <div class="progress-spinner"></div>
                        <span class="progress-text" id="progressText">Connecting to server...</span>
                    </div>
                    <div class="progress-steps">
                        <div class="progress-step" id="step1"></div>
                        <div class="progress-step" id="step2"></div>
                        <div class="progress-step" id="step3"></div>
                        <div class="progress-step" id="step4"></div>
                        <div class="progress-step" id="step5"></div>
                    </div>
                </div>

                <form class="compose-form" id="composeForm" method="POST" action="send.php">
                    <div class="form-group">
                        <label for="to"><i class="fa-sharp-duotone fa-thin fa-user"></i> To</label>
                        <input type="email" id="to" name="to" placeholder="recipient@example.com" required>
                    </div>

                    <div class="form-group">
                        <label for="subject"><i class="fa-sharp-duotone fa-thin fa-heading"></i> Subject</label>
                        <input type="text" id="subject" name="subject" placeholder="Enter subject" required>
                    </div>

                    <div class="form-group">
                        <label for="message"><i class="fa-sharp-duotone fa-thin fa-align-left"></i> Message</label>
                        <textarea id="message" name="message" placeholder="Write your message here..." required></textarea>
                    </div>

                    <div class="compose-actions">
                        <button type="submit" class="btn btn-primary" id="sendBtn"><i class="fa-sharp-duotone fa-thin fa-paper-plane"></i> Send Email</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>
        const form = document.getElementById('composeForm');
        const progress = document.getElementById('sendProgress');
        const progressText = document.getElementById('progressText');
        const sendBtn = document.getElementById('sendBtn');
        const steps = [
            document.getElementById('step1'),
            document.getElementById('step2'),
            document.getElementById('step3'),
            document.getElementById('step4'),
            document.getElementById('step5')
        ];

        function updateProgress(step, text) {
            for (let i = 0; i < step - 1; i++) {
                steps[i].className = 'progress-step completed';
            }
            steps[step - 1].className = 'progress-step active';
            progressText.textContent = text;
        }

        form.addEventListener('submit', function(e) {
            progress.classList.add('active');
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fa-sharp-duotone fa-thin fa-spinner fa-spin"></i> Sending...';

            updateProgress(1, 'Connecting to server...');

            let step = 2;
            const interval = setInterval(() => {
                if (step <= 5) {
                    const texts = [
                        'Authenticating...',
                        'Preparing email...',
                        'Sending...',
                        'Finalizing...'
                    ];
                    updateProgress(step, texts[step - 2] || 'Sending...');
                    step++;
                }
            }, 800);

            // Progress will continue until page redirects
        });
    </script>
</body>
</html>