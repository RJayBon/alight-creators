<?php
require 'config/auth.php';
require 'config/functions.php';
/* No require_login() — 404 should display for everyone */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Not Found – Alight Creators</title>
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
  <link rel="stylesheet" href="assets/css/styles.css?v=<?= css_version(); ?>" />
</head>
<body>
  <div class="app">
    <?php include 'includes/topnav.php'; ?>

    <main>
      <div class="notfound">
        <h1>404</h1>
        <p>Oops! That page doesn't exist.</p>
        <a href="index.php" class="btn-primary" style="padding:.75rem 1.5rem;">Return Home</a>
      </div>
    </main>

    <script src="assets/js/script.js"></script>
  </div><!-- /.app -->
</body>
</html>