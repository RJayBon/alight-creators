<?php
require 'config/auth.php';
require 'config/functions.php';
require_login();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>About – Alight Creators</title>
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
  <link rel="stylesheet" href="assets/css/styles.css?v=<?= css_version(); ?>" />
</head>
<body>
  <div class="app">
    <?php include 'includes/topnav.php'; ?>

    <main class="about-page-wrapper">
      <div class="hero-glow-bg"></div>

      <section class="about-hero-section">
        <h1>Behind <span class="text-primary">Alight Creators</span></h1>
        <p>Making motion design accessible, structured, and beginner-friendly.</p>
      </section>

      <section class="about-cards-container">
        <div class="about-feature-card">
          <h2 class="text-secondary">The Mission</h2>
          <p>Learning Alight Motion shouldn't feel like deciphering an alien language. Alight Creators was built to fix that—a clean, accessible hub where editors can actually understand the "why" behind the tools, not just the "how."</p>
        </div>

        <div class="about-feature-card">
          <h2 class="text-secondary">Designed for Humans</h2>
          <p>This platform was built from the ground up with Human-Computer Interaction principles in mind. From a high-contrast dark UI that reduces eye strain during late-night editing sessions, to logical categorization, every element is designed to give the user maximum control and minimal frustration.</p>
        </div>

        <div class="about-feature-card creator-profile-card">
          <div class="creator-avatar-circle">
            <img src="<?= DEFAULT_AVATAR ?>" alt="RJ AY" />
          </div>
          <h3>RJ AY</h3>
          <span class="creator-role text-primary">Lead Developer &amp; UI Designer</span>
          <p>A 1st-year BSIT college student at SEAIT with a serious passion for repairing electronics, riding motorcycles, and crafting video and audio edits. The personal philosophy: Work hard, avoid the unnecessary, and make sure the final product slaps.</p>
        </div>
      </section>

      <div class="about-footer-icon">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
        </svg>
      </div>
    </main>

    <script src="assets/js/script.js"></script>
  </div><!-- /.app -->
</body>
</html>