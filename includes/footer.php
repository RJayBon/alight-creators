<?php
/**
 * Global footer partial.
 *
 * IMPORTANT — this file also closes the outer `.app` wrapper that
 * every page opens with `<div class="app">`. Pages that include this
 * partial must NOT close `.app` themselves, or the layout will
 * collapse with an unmatched closing tag.
 *
 * Pages that DON'T include this partial (home.php, login.php,
 * register.php, admin-*.php, tutorial-detail.php, profile pages,
 * etc.) are responsible for closing `.app` on their own — and most
 * of them already do via a literal `</div><!-- /.app -->` at the
 * bottom of the file.
 *
 * Why is the close here instead of each page? Because index.php,
 * forgot-password.php, and reset-password.php all reuse this file
 * to keep their footer markup DRY.
 */
?>
<footer class="footer">
  <p>&copy; 2026 Alight Creators | Learn • Create • Improve</p>
</footer>
</div><!-- /.app — closed here by includes/footer.php -->