<?php
require 'config/auth.php';
require 'config/db.php';
require 'config/csrf.php';
require 'config/functions.php';
require 'config/rate_limit.php';
require_guest();

$message      = "";
$messageType  = "";
$reset_link   = "";    // shown on screen for demo purposes

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rate_limit_allow('forgot_password', 3, 600)) {
        $message = "Too many reset attempts. Please try again in a few minutes.";
        $messageType = "error";
    } elseif (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $message = "Invalid session. Please refresh and try again.";
        $messageType = "error";
    } else {
        $email = trim($_POST['email'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $messageType = "error";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    $token = create_password_reset($pdo, (int)$user['user_id'], 15);

                    /* Build the reset link the user will use. In production, send
                       this via email. Here we surface it on-screen so the flow is
                       testable without an SMTP server. */
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
                    $base   = $scheme . '://' . $host . $dir;

                    $reset_link = $base . '/reset-password.php?token=' . urlencode($token);

                    $message = "Reset link generated. It expires in 15 minutes.";
                    $messageType = "success";
                } else {
                    /* Do not reveal whether the email exists */
                    $message = "If that email exists, a reset link has been generated.";
                    $messageType = "success";
                }
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $message = "Something went wrong. Please try again.";
                $messageType = "error";
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Forgot Password – Alight Creators</title>
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
  <link rel="stylesheet" href="assets/css/styles.css?v=<?= css_version(); ?>" />
</head>
<body>
  <div class="app">
    <?php include 'includes/topnav.php'; ?>

    <main>
      <div class="hero-glow-bg"></div>

      <div class="auth-container">
        <div class="auth-hero">
          <h1>Forgot Password?</h1>
          <p>Enter your email and we'll generate a reset link.</p>
        </div>

        <?php if ($message): ?>
          <div class="toast toast-static <?= $messageType === 'error' ? 'toast-error' : 'toast-success' ?> show">
            <?= safe($message) ?>
          </div>
        <?php endif; ?>

        <?php if ($reset_link !== ''): ?>
          <div class="reset-link-panel">
            <h3>Your reset link is ready</h3>
            <p>
              In a live site this would be emailed. For now, copy the link below
              and open it to reset your password.
            </p>
            <div class="reset-link-box">
              <a href="<?= safe($reset_link) ?>" class="reset-link-anchor"><?= safe($reset_link) ?></a>
            </div>
            <a href="<?= safe($reset_link) ?>" class="btn-primary btn-full reset-link-btn">Open Reset Link →</a>
          </div>
        <?php else: ?>
          <div class="auth-card">
            <form action="forgot-password.php" method="POST" id="forgot-form">
              <?= csrf_field() ?>

              <div class="field field-mb-xl">
                <label for="email">Email Address</label>
                <input id="email"
                       name="email"
                       type="email"
                       placeholder="you@example.com"
                       maxlength="100"
                       autocomplete="email"
                       required />
              </div>

              <button type="submit" class="btn-primary btn-full">Send Reset Link</button>
            </form>

            <div class="auth-footer">
              Remembered it? <a href="login.php">Back to login</a>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/script.js"></script>
  </body>
</html>