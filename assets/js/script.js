/* ============================================================
   Alight Creators — Global UI utilities + loading states
   ============================================================ */

/* ---------- User dropdown ---------- */
function setupUserDropdown() {
  const menu = document.getElementById("user-menu");
  if (!menu) return;

  const trigger = document.getElementById("user-trigger");
  if (trigger) {
    trigger.addEventListener("click", (e) => {
      e.stopPropagation();
      menu.classList.toggle("open");
    });
  }

  document.addEventListener("click", (e) => {
    if (!menu.contains(e.target)) menu.classList.remove("open");
  });
}

/* ============================================================
   1. FULL-PAGE LOADER
   Shows during initial paint, hides after window.load.
   ============================================================ */
function initPageLoader() {
  const loader = document.getElementById('page-loader');
  if (!loader) return;

  /* ---- Sync loader top-edge with the real navbar height ---- */
  const navbar = document.querySelector('.navbar');
  function syncNavbarHeight() {
    if (!navbar) return;
    const h = navbar.offsetHeight;
    if (h > 0) {
      document.documentElement.style.setProperty('--navbar-height', h + 'px');
    }
  }
  syncNavbarHeight();

  // Re-measure on resize (navbar wraps to two rows on narrow screens)
  let resizeTimer = null;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(syncNavbarHeight, 120);
  });

  // Re-measure once web fonts load (their metrics can shift layout slightly)
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(syncNavbarHeight).catch(() => {});
  }

  /* ---- Hide the loader after the page finishes loading ---- */
  let hidden = false;
  const hideLoader = () => {
    if (hidden) return;
    hidden = true;
    syncNavbarHeight();            // one final sync before reveal
    requestAnimationFrame(() => {
      requestAnimationFrame(() => loader.classList.add('hidden'));
    });
  };

  if (document.readyState === 'complete') {
    hideLoader();
  } else {
    window.addEventListener('load', hideLoader);
    // Safety net: never lock the UI longer than 6 seconds
    setTimeout(hideLoader, 6000);
  }
}

/* ============================================================
   2. TOP PROGRESS BAR — internal navigation feedback
   ============================================================ */
function initTopProgress() {
  const progress = document.getElementById('top-progress');
  if (!progress) return;

  let timer = null;

  function start() {
    progress.classList.remove('done');
    progress.classList.add('active');
    progress.style.width = '0%';
    let pct = 0;
    clearInterval(timer);
    timer = setInterval(() => {
      pct += Math.random() * 12;
      if (pct > 90) pct = 90;
      progress.style.width = pct + '%';
    }, 180);
  }

  function finish() {
    clearInterval(timer);
    progress.style.width = '100%';
    progress.classList.add('done');
    setTimeout(() => {
      progress.classList.remove('active', 'done');
      progress.style.width = '0%';
    }, 350);
  }

  document.addEventListener('click', (e) => {
    const link = e.target.closest('a[href]');
    if (!link) return;

    const href = link.getAttribute('href');
    if (!href) return;
    if (href.startsWith('#') ||
        href.startsWith('javascript:') ||
        href.startsWith('mailto:') ||
        href.startsWith('tel:')) return;
    if (link.target === '_blank') return;
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;

    try {
      const url = new URL(href, location.href);
      if (url.origin === location.origin &&
          url.pathname === location.pathname &&
          url.search === location.search) {
        return; // same-page link — skip
      }
    } catch (_) { /* malformed URL — fall through */ }

    start();
  });

  window.addEventListener('beforeunload', () => {
    progress.classList.add('active');
    progress.style.width = '100%';
  });

  /* Restore after bfcache back-navigation */
  window.addEventListener('pageshow', (e) => {
    if (e.persisted) finish();
  });
}

/* ============================================================
   3. AUTO-ATTACH BUTTON LOADING TO FORM SUBMISSIONS
   ============================================================ */
function initFormLoading() {
  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.dataset.noLoader === 'true') return;

    // ⚠️ CRITICAL: if an earlier listener (e.g. delete confirm dialog)
    // already prevented the submit, do NOT show loading — the form
    // isn't actually going anywhere.
    if (e.defaultPrevented) return;

    // Prefer the button that triggered the submit; fall back to first submit button
    let btn = e.submitter ||
              form.querySelector('button[type="submit"]:not([form])');

    if (!btn) return;

    // Guard against double-submit
    if (btn.classList.contains('btn-loading')) {
      e.preventDefault();
      return;
    }

    // Defer so the browser captures button name/value first
    setTimeout(() => {
      // Double-check: another listener may have prevented during this tick
      if (e.defaultPrevented) return;
      btn.classList.add('btn-loading');
      btn.disabled = true;
    }, 0);
  });

  // Restore buttons when coming back via bfcache
  window.addEventListener('pageshow', (e) => {
    if (!e.persisted) return;
    document.querySelectorAll('.btn-loading').forEach((btn) => {
      btn.classList.remove('btn-loading');
      btn.disabled = false;
    });
  });
}

/* ============================================================
   4. GLOBAL HELPER FOR AJAX BUTTONS
   Usage: setButtonLoading(btn, true) / setButtonLoading(btn, false)
   ============================================================ */
window.setButtonLoading = function (btn, on) {
  if (!btn) return;
  if (on) {
    btn.classList.add('btn-loading');
    btn.disabled = true;
  } else {
    btn.classList.remove('btn-loading');
    btn.disabled = false;
  }
};

/* ============================================================
   INIT
   ============================================================ */
document.addEventListener("DOMContentLoaded", () => {
  setupUserDropdown();
  initPageLoader();
  initTopProgress();
  initFormLoading();
});