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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #e8dcc8 0%, #d4c4a8 50%, #c9b896 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: #f5f0e6;
            border: 2px solid #8b7355;
            border-radius: 0;
            box-shadow: 8px 8px 0 rgba(0,0,0,0.15);
            width: 100%;
            max-width: 420px;
            padding: 40px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-logo {
            width: 64px;
            height: 64px;
            background: #e87b35;
            border-radius: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            box-shadow: 4px 4px 0 rgba(0,0,0,0.1);
        }

        .login-logo svg {
            width: 36px;
            height: 36px;
            fill: white;
        }

        .login-title {
            font-size: 24px;
            font-weight: 700;
            color: #4a3f35;
            margin-bottom: 8px;
        }

        .login-subtitle {
            font-size: 14px;
            color: #7a6b5a;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #5a4f45;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            font-size: 15px;
            border: 2px solid #c9b896;
            border-radius: 0;
            background: #fff;
            color: #4a3f35;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: #e87b35;
            box-shadow: 0 0 0 3px rgba(232, 123, 53, 0.15);
        }

        .form-input::placeholder {
            color: #a09080;
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            font-size: 15px;
            font-weight: 700;
            color: white;
            background: #e87b35;
            border: 2px solid #c96a2d;
            border-radius: 0;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .login-btn:hover {
            background: #d66a2a;
            transform: translateY(-2px);
            box-shadow: 4px 4px 0 rgba(0,0,0,0.15);
        }

        .login-btn:active {
            transform: translateY(0);
            box-shadow: 2px 2px 0 rgba(0,0,0,0.15);
        }

        .login-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .error-message {
            background: #f8e8e8;
            border: 2px solid #d66;
            color: #a33;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .login-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 12px;
            color: #8a7a6a;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
            </div>
            <h1 class="login-title"><?= sanitize($appName) ?></h1>
            <p class="login-subtitle">Sign in to access your mailbox</p>
        </div>

        <div class="error-message" id="errorMessage"></div>

        <form id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input type="text" class="form-input" id="username" name="username" placeholder="Enter your username" required autocomplete="username">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" class="form-input" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
            </div>

            <button type="submit" class="login-btn" id="loginBtn">Sign In</button>
        </form>

        <div class="login-footer">
            &copy; <?= date('Y') ?> <?= sanitize($appName) ?>
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