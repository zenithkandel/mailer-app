<?php
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === ADMIN_EMAIL && $password === ADMIN_PASS) {
        $_SESSION['user'] = $email;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid email or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mail App</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://zenithkandel.com.np/fontawesome/zenith-icons.js"></script>
    <style>
        .login-page { display: block; }
        .app-layout { display: none; }
    </style>
</head>
<body>
    <?php if (!isLoggedIn()): ?>
    <div class="login-page">
        <div class="login-card">
            <div class="login-logo">
                <i class="fa-sharp-duotone fa-thin fa-envelope"></i> Mail
                <span>Sign in to your account</span>
            </div>

            <div class="login-error <?php echo $error ? 'show' : ''; ?>">
                <i class="fa-sharp-duotone fa-thin fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email"><i class="fa-sharp-duotone fa-thin fa-user"></i> Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                </div>

                <div class="form-group">
                    <label for="password"><i class="fa-sharp-duotone fa-thin fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn btn-primary"><i class="fa-sharp-duotone fa-thin fa-right-to-bracket"></i> Sign In</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>