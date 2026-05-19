<?php
require_once __DIR__ . '/api/config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$config = loadConfig();
$appName = $config['app_name'] ?? 'Zenith Mail';
$csrfToken = csrfGenerate();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= sanitize($appName) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <div class="login-page">
        <div class="login-container">
            <div class="login-card">
                <div class="login-logo">
                    <div class="login-logo-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                            <polyline points="22,6 12,13 2,6" />
                        </svg>
                    </div>
                </div>
                <h1 class="login-title"><?= sanitize($appName) ?></h1>
                <p class="login-subtitle">Sign in to access your mailbox</p>

                <div class="login-error" id="errorMessage"></div>

                <form class="login-form" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Enter your username" required
                            autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required
                            autocomplete="current-password">
                    </div>
                    <button type="submit" class="login-btn" id="loginBtn">Sign In</button>
                </form>

                <div class="login-footer"><?= sanitize($appName) ?> &mdash; Mail Client</div>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('loginForm');
        const errorMsg = document.getElementById('errorMessage');
        const loginBtn = document.getElementById('loginBtn');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorMsg.classList.remove('show');
            errorMsg.textContent = '';
            loginBtn.disabled = true;
            loginBtn.textContent = 'Signing in...';

            const formData = new FormData(form);

            try {
                const response = await fetch('api/auth.php?action=login', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    errorMsg.textContent = data.error || 'Login failed';
                    errorMsg.classList.add('show');
                    loginBtn.disabled = false;
                    loginBtn.textContent = 'Sign In';
                }
            } catch (err) {
                errorMsg.textContent = 'Network error. Please try again.';
                errorMsg.classList.add('show');
                loginBtn.disabled = false;
                loginBtn.textContent = 'Sign In';
            }
        });
    </script>
</body>

</html>