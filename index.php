<?php
require_once 'config.php';

if (!empty($_SESSION['authenticated'])) {
    header('Location: app.php');
    exit;
}

$error = '';

if (!extension_loaded('imap')) {
    $error = 'PHP IMAP extension is not installed. Please enable php_imap extension in php.ini and restart the server.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $mbox = @imap_open(IMAP_PREFIX, $username, $password);
        if ($mbox) {
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['password'] = $password;
            imap_close($mbox);
            header('Location: app.php');
            exit;
        } else {
            $error = 'Invalid email or password. Please check your credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZenithMail - Login</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>ZenithMail</h1>
                <p>Sign in to your email</p>
            </div>
            <form method="POST" action="">
                <?php if ($error): ?>
                    <div class="login-error"><?php echo escapeHtml($error); ?></div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="username">Email</label>
                    <input type="email" id="username" name="username" required autocomplete="username" placeholder="admin@zenithkandel.com.np">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
                </div>
                <button type="submit" class="btn btn-primary btn-full">Sign In</button>
            </form>
            <div class="login-footer">
                <p>Mail Server: mail.zenithkandel.com.np</p>
            </div>
        </div>
    </div>
</body>
</html>