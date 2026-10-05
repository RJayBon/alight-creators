<?php
require 'config/auth.php';
require 'config/db.php';
require 'config/csrf.php';
require 'config/functions.php';
require_login();
require_admin();

/* ============================================================
   AJAX MODE
   ============================================================ */
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

$search   = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

$where  = '';
$params = [];
if ($search !== '') {
    $where  = " WHERE (full_name LIKE ? OR username LIKE ? OR email LIKE ?)";
    $like   = '%' . $search . '%';
    $params = [$like, $like, $like];
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$countStmt->execute($params);
$total       = (int)$countStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

$listStmt = $pdo->prepare("
    SELECT u.user_id, u.full_name, u.username, u.email, u.avatar_path, u.role,
           (SELECT COUNT(*) FROM tutorials t WHERE t.user_id = u.user_id) AS tutorial_count
    FROM users u
    $where
    ORDER BY (u.role = 'admin') DESC, u.user_id ASC
    LIMIT $per_page OFFSET $offset
");
$listStmt->execute($params);
$users = $listStmt->fetchAll();

if ($is_ajax) {
    header('Content-Type: application/json');

    echo json_encode([
        'ok'          => true,
        'search'      => $search,
        'page'        => $page,
        'per_page'    => $per_page,
        'total'       => $total,
        'total_pages' => $total_pages,
        'users'       => array_map(function ($u) {
            return [
                'user_id'        => (int)$u['user_id'],
                'name'           => !empty($u['full_name']) ? $u['full_name'] : $u['username'],
                'username'       => $u['username'],
                'email'          => $u['email'],
                'avatar'         => resolve_avatar($u['avatar_path'] ?? null),
                'role'           => (string)($u['role'] ?? 'user'),
                'tutorial_count' => (int)$u['tutorial_count'],
                'is_me'          => ((int)$u['user_id'] === (int)$_SESSION['user_id']),
            ];
        }, $users),
    ]);
    exit;
}

/* ============================================================
   HTML MODE
   ============================================================ */

$totalUsers  = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$totalTuts   = (int)$pdo->query("SELECT COUNT(*) FROM tutorials")->fetchColumn();

$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type    = $_SESSION['flash_type']    ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Users – Alight Creators</title>
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
            <h1>Manage Users</h1>
            <p>View, edit, and moderate user accounts across the platform.</p>
          </div>
          <a href="admin-users.php" class="btn-outline admin-refresh-btn">↻ Refresh</a>
        </div>

        <?php if ($flash_message): ?>
          <div class="toast toast-static <?= $flash_type === 'error' ? 'toast-error' : 'toast-success' ?> show">
            <?= safe($flash_message) ?>
          </div>
        <?php endif; ?>

        <div class="admin-stats-grid">
          <div class="admin-stat-card">
            <span class="admin-stat-value"><?= $totalUsers ?></span>
            <span class="admin-stat-label">Total Users</span>
          </div>
          <div class="admin-stat-card">
            <span class="admin-stat-value"><?= $totalAdmins ?></span>
            <span class="admin-stat-label">Admins</span>
          </div>
          <div class="admin-stat-card">
            <span class="admin-stat-value"><?= $totalTuts ?></span>
            <span class="admin-stat-label">Tutorials</span>
          </div>
        </div>

        <form method="GET" action="admin-users.php" class="admin-search-form" id="admin-search-form" data-no-loader="true">
          <input type="text"
                 name="q"
                 id="admin-search-input"
                 value="<?= safe($search) ?>"
                 placeholder="Search by name, @handle, or email…"
                 autocomplete="off"
                 spellcheck="false" />
          <button type="submit" id="admin-search-btn" class="btn-outline admin-search-btn" aria-label="Search">
            Search
          </button>
        </form>

        <div class="admin-user-list">
          <div class="admin-user-list-head">
            <span>User</span>
            <span>Email</span>
            <span>Role</span>
            <span>Tutorials</span>
            <span>Actions</span>
          </div>

          <div id="admin-user-rows">
            <?php if (count($users) > 0): ?>
              <?php foreach ($users as $u):
                $avatar = resolve_avatar($u['avatar_path'] ?? null);
                $name   = !empty($u['full_name']) ? $u['full_name'] : $u['username'];
                $is_me  = ((int)$u['user_id'] === (int)$_SESSION['user_id']);
                $is_admin_row = (($u['role'] ?? '') === 'admin');
              ?>
                <div class="admin-user-row">
                  <div class="admin-user-cell-user">
                    <img src="<?= safe($avatar) ?>" alt="" class="admin-user-avatar" />
                    <div class="admin-user-meta">
                      <span class="admin-user-name"><?= safe($name) ?><?php if ($is_me): ?> <em class="admin-you-tag">you</em><?php endif; ?></span>
                      <span class="admin-user-handle">@<?= safe($u['username']) ?></span>
                    </div>
                  </div>

                  <div class="admin-user-cell-email" title="<?= safe($u['email']) ?>">
                    <?= safe($u['email']) ?>
                  </div>

                  <div class="admin-user-cell-role">
                    <?php if ($is_admin_row): ?>
                      <span class="admin-role-pill admin-role-admin">Admin</span>
                    <?php else: ?>
                      <span class="admin-role-pill admin-role-user">User</span>
                    <?php endif; ?>
                  </div>

                  <div class="admin-user-cell-count">
                    <?= (int)$u['tutorial_count'] ?>
                  </div>

                  <div class="admin-user-cell-actions">
                    <a href="user.php?u=<?= urlencode($u['username']) ?>" class="admin-row-btn" title="View public profile">👁</a>
                    <a href="profile.php?id=<?= (int)$u['user_id'] ?>" class="admin-row-btn admin-row-btn-edit" title="Edit user">✎</a>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="admin-user-empty">
                <?= $search !== '' ? 'No users match your search.' : 'No users yet.' ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <nav class="admin-pagination" id="admin-pagination" <?= $total_pages <= 1 ? 'hidden' : '' ?>>
          <button type="button" class="admin-page-btn" id="admin-page-prev" <?= $page <= 1 ? 'disabled' : '' ?>>← Prev</button>
          <span class="admin-page-info" id="admin-page-info">
            Page <?= $page ?> of <?= $total_pages ?> · <?= $total ?> users
          </span>
          <button type="button" class="admin-page-btn" id="admin-page-next" <?= $page >= $total_pages ? 'disabled' : '' ?>>Next →</button>
        </nav>

      </div>
    </main>

    <script>
      window.ADMIN_USERS_STATE = {
        search: <?= json_encode($search) ?>,
        page:   <?= (int)$page ?>,
        totalPages: <?= (int)$total_pages ?>
      };
    </script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/admin-users.js"></script>
  </div><!-- /.app -->
</body>
</html>