<?php
require 'config/auth.php';
require 'config/db.php';
require 'config/csrf.php';
require 'config/functions.php';
require_login();
require_admin();

/* ============================================================
   POST ACTIONS
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash_message'] = "Invalid session. Please try again.";
        $_SESSION['flash_type']    = "error";
        header("Location: admin-messages.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    /* ---- Delete a single message ---- */
    if ($action === 'delete') {
        $id = (int)($_POST['message_id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM contact_messages WHERE message_id = ?")->execute([$id]);
            $_SESSION['flash_message'] = "Message deleted.";
            $_SESSION['flash_type']    = "success";
        }
        header("Location: admin-messages.php");
        exit;
    }

    /* ---- Clear all messages ---- */
    if ($action === 'clear_all') {
        if (($_POST['confirm_clear'] ?? '') === 'yes') {
            $pdo->exec("DELETE FROM contact_messages");
            $_SESSION['flash_message'] = "All contact messages cleared.";
            $_SESSION['flash_type']    = "success";
        }
        header("Location: admin-messages.php");
        exit;
    }
}

/* ============================================================
   LOAD DATA
   ============================================================ */
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type    = $_SESSION['flash_type']    ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$messages = $pdo->query("
    SELECT message_id, name, email, message, created_at
    FROM contact_messages
    ORDER BY created_at DESC
")->fetchAll();

$totalMessages = count($messages);

/* Today's count */
$todayCount = (int)$pdo->query("
    SELECT COUNT(*) FROM contact_messages
    WHERE DATE(created_at) = CURDATE()
")->fetchColumn();

/* This-week count (last 7 days) */
$thisWeekCount = (int)$pdo->query("
    SELECT COUNT(*) FROM contact_messages
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetchColumn();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Contact Messages – Alight Creators</title>
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
  <link rel="stylesheet" href="assets/css/styles.css?v=<?= css_version(); ?>" />
</head>
<body>
  <div class="app">
    <?php include 'includes/topnav.php'; ?>

    <main>
      <div class="admin-users-wrapper">

        <div class="admin-users-header">
          <div>
            <h1>Contact Messages</h1>
            <p>Messages submitted by users through the contact form.</p>
          </div>
          <a href="admin-messages.php" class="btn-outline admin-refresh-btn">↻ Refresh</a>
        </div>

        <?php if ($flash_message): ?>
          <div class="toast toast-static <?= $flash_type === 'error' ? 'toast-error' : 'toast-success' ?> show">
            <?= safe($flash_message) ?>
          </div>
        <?php endif; ?>

        <div class="admin-stats-grid">
          <div class="admin-stat-card">
            <span class="admin-stat-value"><?= $totalMessages ?></span>
            <span class="admin-stat-label">Total Messages</span>
          </div>
          <div class="admin-stat-card">
            <span class="admin-stat-value"><?= $todayCount ?></span>
            <span class="admin-stat-label">Today</span>
          </div>
          <div class="admin-stat-card">
            <span class="admin-stat-value"><?= $thisWeekCount ?></span>
            <span class="admin-stat-label">Last 7 Days</span>
          </div>
        </div>

        <?php if ($totalMessages === 0): ?>

          <div class="admin-user-list">
            <div class="admin-user-empty">
              <span class="contact-msg-empty-icon">✉️</span>
              <p>No messages yet.</p>
              <p class="contact-msg-empty-hint">When users submit the contact form, their messages will appear here.</p>
            </div>
          </div>

        <?php else: ?>

          <div class="contact-msg-toolbar">
            <span class="contact-msg-toolbar-text">
              Showing <strong><?= $totalMessages ?></strong>
              message<?= $totalMessages === 1 ? '' : 's' ?>
            </span>

            <form method="POST" action="admin-messages.php"
                  onsubmit="return confirm('Delete ALL contact messages?\n\nThis cannot be undone.');"
                  class="contact-msg-toolbar-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="clear_all" />
              <input type="hidden" name="confirm_clear" value="yes" />
              <button type="submit" class="btn-danger-outline contact-msg-clear-btn">
                🗑 Clear All
              </button>
            </form>
          </div>

          <div class="contact-msg-list">
            <?php foreach ($messages as $m): ?>
              <article class="contact-msg-card">

                <header class="contact-msg-head">
                  <div class="contact-msg-sender">
                    <strong class="contact-msg-name"><?= safe($m['name']) ?></strong>
                    <a href="mailto:<?= safe($m['email']) ?>?subject=Re:%20Your%20message%20to%20Alight%20Creators"
                       class="contact-msg-email"
                       title="Reply to <?= safe($m['name']) ?>">
                      <?= safe($m['email']) ?>
                    </a>
                  </div>
                  <time class="contact-msg-time" datetime="<?= safe($m['created_at']) ?>">
                    <?= safe(date('M j, Y · g:i A', strtotime($m['created_at']))) ?>
                  </time>
                </header>

                <div class="contact-msg-body"><?= safe($m['message']) ?></div>

                <footer class="contact-msg-footer">

                  <form method="POST" action="admin-messages.php"
                        onsubmit="return confirm('Delete this message?');"
                        class="contact-msg-delete-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete" />
                    <input type="hidden" name="message_id" value="<?= (int)$m['message_id'] ?>" />
                    <button type="submit" class="tut-action-btn tut-action-delete">
                      <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
                           stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        <line x1="10" y1="11" x2="10" y2="17"/>
                        <line x1="14" y1="11" x2="14" y2="17"/>
                      </svg>
                      Delete
                    </button>
                  </form>
                </footer>

              </article>
            <?php endforeach; ?>
          </div>

        <?php endif; ?>

      </div>
    </main>

    <script src="assets/js/script.js"></script>
  </div><!-- /.app -->
</body>
</html>