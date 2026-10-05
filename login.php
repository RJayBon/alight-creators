<?php
require 'config/auth.php';
require 'config/db.php';
require 'config/csrf.php';
require 'config/functions.php';
require 'config/rate_limit.php';
require_guest();

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rate_limit_allow('login', 5, 300)) {
        $error_message = "Too many attempts. Try again in a few minutes.";
    } elseif (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error_message = "Invalid session. Please try again.";
    } else {
        /* ------------------------------------------------------------------
           Accept BOTH email and username in the same field.

           Rules:
             • User may type "you@example.com"    → treat as email
             • User may type "admin"              → treat as username
             • User may type "@admin"             → strip @, treat as username
             • Usernames are stored lowercase and never contain @,
               emails always contain @ → the two namespaces never collide,
               so a single "email = ? OR username = ?" lookup is unambiguous.
           ------------------------------------------------------------------ */
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';

        /* Strip a single leading "@" (common when users type their handle). */
        if ($identifier !== '' && $identifier[0] === '@') {
            $identifier = substr($identifier, 1);
        }

        /* No "@" anywhere → it's a username. Normalize it the same way
           we normalize handles at registration, so "Admin", "ADMIN", and
           "admin" all map to the stored value. */
        if ($identifier !== '' && strpos($identifier, '@') === false) {
            $identifier = normalize_handle($identifier);
        }

        if ($identifier === '' || $password === '') {
            $error_message = "Please enter your email or username and password.";
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM users
                WHERE email = ? OR username = ?
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                rate_limit_reset('login');
                session_regenerate_id(true);
                $_SESSION['user_id']  = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                header("Location: home.php");
                exit;
            } else {
                /* Never reveal whether the account exists. */
                $error_message = "Invalid credentials. Please try again.";
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
  <title>Login – Alight Creators</title>
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
          <h1>Welcome Back</h1>
          <p>Log in with your email or @username.</p>
        </div>

        <?php if ($error_message): ?>
          <div class="toast toast-static toast-error show">
            <?= safe($error_message) ?>
          </div>
        <?php endif; ?>

        <div class="auth-card">
          <form method="POST" action="login.php" id="login-form">
            <?= csrf_field() ?>

            <div class="field field-mb-lg">
              <label for="identifier">Email or Username</label>
              <input id="identifier"
                     name="identifier"
                     type="text"
                     required
                     placeholder="you@example.com or @username"
                     maxlength="100"
                     autocomplete="username"
                     autocapitalize="none"
                     spellcheck="false" />
            </div>

            <div class="field field-mb-xl">
              <label for="password">Password</label>
              <input id="password"
                     name="password"
                     type="password"
                     required
                     placeholder="• • • • • • • •"
                     autocomplete="current-password" />
              <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
            </div>

            <button type="submit" class="btn-primary btn-full">Log In</button>
          </form>

          <div class="auth-footer">
            Don't have an account? <a href="register.php">Sign up here</a>
          </div>
        </div>
      </div>
    </main>

    <script src="assets/js/script.js"></script>
  </div><!-- /.app -->
</body>
</html>