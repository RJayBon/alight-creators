<?php
require 'config/auth.php';
require 'config/db.php';
require 'config/csrf.php';
require 'config/functions.php';
require 'config/rate_limit.php';
require_login();

$user_id = (int)$_SESSION['user_id'];
$message = "";
$messageType = "";

$uStmt = $pdo->prepare("SELECT full_name, username, email FROM users WHERE user_id = ?");
$uStmt->execute([$user_id]);
$me = $uStmt->fetch() ?: [];

$auto_name  = !empty($me['full_name']) ? $me['full_name'] : ($me['username'] ?? 'User');
$auto_email = $me['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!rate_limit_allow('contact', 3, 600)) {
        $message = "You're sending messages too fast. Please wait a few minutes.";
        $messageType = "error";
    } elseif (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $message = "Invalid session. Please try again.";
        $messageType = "error";
    } else {
        $name = trim($_POST['name'] ?? '');
        $msg  = trim($_POST['message'] ?? '');
        $email = $auto_email;

        if ($name === '' || $msg === '') {
            $message = "Please fill in all fields.";
            $messageType = "error";
        } elseif (strlen($name) > 100 || strlen($email) > 100) {
            $message = "Name is too long (max 100 characters).";
            $messageType = "error";
        } elseif (strlen($msg) > 5000) {
            $message = "Message is too long (max 5000 characters).";
            $messageType = "error";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $msg]);
                $message = "Message sent! We'll get back to you soon.";
                $messageType = "success";
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $message = "Could not send message. Please try again.";
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
  <title>Contact – Alight Creators</title>
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
  <link rel="stylesheet" href="assets/css/styles.css?v=<?= css_version(); ?>" />
</head>
<body>
  <div class="app">
    <?php include 'includes/topnav.php'; ?>

    <main>
      <div class="hero-glow-bg"></div>

      <div class="form-container">
        <div class="contact-hero contact-hero-rel">
          <h1>Get in Touch</h1>
          <p>Have a question or want to request a specific tutorial?</p>
        </div>

        <?php if ($message): ?>
          <div class="toast toast-static <?= $messageType === 'error' ? 'toast-error' : 'toast-success' ?> show">
            <?= safe($message) ?>
          </div>
        <?php endif; ?>

        <div class="form-card shadow form-rel">
          <form action="contact.php" method="POST" class="auth-form">
            <?= csrf_field() ?>

            <div class="field">
              <label>Name</label>
              <input type="text"
                     name="name"
                     value="<?= safe($auto_name) ?>"
                     placeholder="Your Name"
                     maxlength="100"
                     readonly
                     class="input-readonly"
                     title="Name is taken from your account" />
              <span class="field-help">
                Auto-filled from your profile. Replies will be sent to
                <strong><?= safe($auto_email) ?></strong>.
              </span>
            </div>

            <div class="field">
              <label>Message</label>
              <textarea name="message"
                        rows="6"
                        class="textarea-no-resize textarea-fixed"
                        placeholder="How can we help?"
                        maxlength="5000"
                        required></textarea>
            </div>

            <button type="submit" class="btn-primary btn-mt-sm">Send Message</button>
          </form>
        </div>
      </div>
    </main>

    <script src="assets/js/script.js"></script>
  </div><!-- /.app -->
</body>
</html>