<?php
require 'config/auth.php';
require 'config/functions.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Alight Creators – Beginner-Friendly Alight Motion Tutorials</title>
  <meta name="description" content="Master Alight Motion with beginner-friendly tutorials, step-by-step editing guides, and creative tips." />
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
  <link rel="stylesheet" href="assets/css/styles.css?v=<?= css_version(); ?>" />
</head>
<body>
  <div class="app">
    <?php include 'includes/topnav.php'; ?>

    <main>
      <section class="landing-hero">
        <div class="inner">
          <h1>Elevate Your <span class="text-primary">Motion Design</span></h1>
          <p>Master professional video editing and animation</p>
          <a href="home.php" class="cta-pill">Start Learning Now</a>
        </div>
      </section>

      <section class="features">
        <h2>Why Alight Creators?</h2>
        <div class="feature-grid">
          <article class="feature-card">
            <h3>🚀 Zero to Hero</h3>
            <p>No confusing jargon. Just straight-up easy guides designed specifically for complete beginners.</p>
          </article>
          <article class="feature-card">
            <h3>🧩 Step-by-Step</h3>
            <p>Master keyframes, null objects, and 3D effects without losing your mind in the timeline.</p>
          </article>
          <article class="feature-card">
            <h3>🔥 Trend-Ready</h3>
            <p>Learn the exact techniques the pros use to drop edits that actually slap on TikTok and IG.</p>
          </article>
        </div>
      </section>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="assets/js/script.js"></script>
  </body>
</html>