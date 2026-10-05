<?php
require 'config/auth.php';
require 'config/db.php';
require 'config/csrf.php';
require 'config/functions.php';
require 'config/rate_limit.php';
require_guest();

$token   = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$reset   = find_valid_reset($pdo, $token);

$message     = "";
$messageType = "";

/* ---------- Invalid / expired / missing token ---------- */
if (!$reset) {
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>Reset Link Invalid – Alight Creators</title>
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
              <h1>Link Expired</h1>
              <p>This reset link is invalid or has expired.</p>
            </div>
            <div class="auth-card">
              <p class="reset-invalid-text">
                Reset links last 15 minutes and can only be used once.
                Request a fresh one to continue.
              </p>
              <a href="forgot-password.php" class="btn-primary btn-full">Request a new link</a>
              <div class="auth-footer">
                <a href="login.php">Back to login</a>
              </div>
            </div>
          </div>
        </main>
        <?php include 'includes/footer.php'; ?>
        <script src="assets/js/script.js"></script>
      </body>
    </html>
    <?php
    exit;
}

/* ---------- Handle new-password submission ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rate_limit_allow('reset_password', 5, 600)) {
        $message = "Too many attempts. Please try again in a few minutes.";
        $messageType = "error";
    } elseif (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $message = "Invalid session. Please refresh and try again.";
        $messageType = "error";
    } else {
        $password         = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 6) {
            $message = "Password must be at least 6 characters.";
            $messageType = "error";
        } elseif ($password !== $confirm_password) {
            $message = "Passwords do not match.";
            $messageType = "error";
        } else {
            try {
                consume_password_reset($pdo, $reset['reset_id'], $reset['user_id'], $password);

                $_SESSION['flash_message'] = "Password updated successfully. You can now log in.";
                $_SESSION['flash_type']    = "success";
                header("Location: login.php");
                exit;
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $message = "Could not update your password. Please try again.";
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
  <title>Reset Password – Alight Creators</title>
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
          <h1>Set a New Password</h1>
          <p>Choose a strong password you haven't used before.</p>
        </div>

        <?php if ($message): ?>
          <div class="toast toast-static <?= $messageType === 'error' ? 'toast-error' : 'toast-success' ?> show">
            <?= safe($message) ?>
          </div>
        <?php endif; ?>

        <div class="auth-card">
          <form action="reset-password.php" method="POST" id="reset-form">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= safe($token) ?>" />

            <div class="field field-mb-lg">
              <label for="password">New Password</label>
              <input id="password"
                     name="password"
                     type="password"
                     placeholder="Create a secure password"
                     minlength="6"
                     autocomplete="new-password"
                     required />
            </div>

            <div class="field field-mb-xl">
              <label for="confirm_password">Confirm New Password</label>
              <input id="confirm_password"
                     name="confirm_password"
                     type="password"
                     placeholder="Re-enter your new password"
                     minlength="6"
                     autocomplete="new-password"
                     required />
              <span class="field-help" id="confirm_password_help">Must match your new password above.</span>
            </div>

            <button type="submit" class="btn-primary btn-full">Update Password</button>
          </form>

          <div class="auth-footer">
            <a href="login.php">Cancel and return to login</a>
          </div>
        </div>
      </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/script.js"></script>
    <script>
      /* Live match feedback — same pattern as register.php */
      (function () {
        const pw  = document.getElementById('password');
        const cpw = document.getElementById('confirm_password');
        const help = document.getElementById('confirm_password_help');
        if (!pw || !cpw || !help) return;

        function sync() {
          if (cpw.value === '') {
            cpw.classList.remove('input-invalid');
            help.textContent = 'Must match your new password above.';
            help.style.color = '';
            return;
          }
          const ok = pw.value === cpw.value;
          cpw.classList.toggle('input-invalid', !ok);
          help.textContent = ok ? '✓ Passwords match.' : '✕ Passwords do not match.';
          help.style.color = ok
            ? 'hsl(var(--accent))'
            : 'hsl(var(--destructive))';
        }

        pw.addEventListener('input', sync);
        cpw.addEventListener('input', sync);

        document.getElementById('reset-form').addEventListener('submit', (e) => {
          if (pw.value !== cpw.value) {
            e.preventDefault();
            sync();
            cpw.focus();
          }
        });
      })();
    </script>
  </body>
</html>