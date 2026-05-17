<?php
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    header('Location: inbox.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($user === ADMIN_EMAIL && $pass === ADMIN_PASS) {
        $_SESSION['user'] = $user;
        header('Location: inbox.php');
        exit;
    }

    $error = 'Invalid credentials';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mail</title>
    <link rel="stylesheet" href="style.css">
</head>

<body class="auth-body">
    <div class="auth-wrap">
        <div class="auth-card glass">
            <div class="auth-brand">Mail</div>
            <div class="auth-sub">Sign in to your mailbox</div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <label class="field">
                    <span>Email</span>
                    <input type="email" name="username" required placeholder="admin@zenithkandel.com.np">
                </label>

                <label class="field">
                    <span>Password</span>
                    <input type="password" name="password" required placeholder="Your password">
                </label>

                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>
        </div>
    </div>
</body>

</html>