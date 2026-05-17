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
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-logo">
                <h1>Mail</h1>
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
                    <i class="fa-sharp-duotone fa-thin fa-pencil"></i>
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
                    <i class="fa-sharp-duotone fa-thin fa-sign-out"></i>
                    Logout
                </a>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-container">
                <div class="page-header">
                    <h2 class="page-title">Compose</h2>
                </div>

                <?php if ($success): ?>
                <div class="alert alert-success">Email sent successfully!</div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <form class="compose-form" method="POST" action="send.php">
                    <div class="form-group">
                        <label for="to">To</label>
                        <input type="email" id="to" name="to" placeholder="recipient@example.com" required>
                    </div>

                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" placeholder="Enter subject" required>
                    </div>

                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" placeholder="Write your message here..." required></textarea>
                    </div>

                    <div class="compose-actions">
                        <button type="submit" class="btn btn-primary">Send Email</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>