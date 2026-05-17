<?php
require_once __DIR__ . '/config.php';

if (isLoggedIn()) {
    header('Location: inbox.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === ADMIN_EMAIL && $password === ADMIN_PASS) {
        $_SESSION['user'] = $email;
        header('Location: inbox.php');
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
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-logo">
                Mail
                <span>Sign in to your account</span>
            </div>

            <div class="login-error <?php echo $error ? 'show' : ''; ?>">
                <?php echo htmlspecialchars($error); ?>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn btn-primary">Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>