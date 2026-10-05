/* ============================================================
   Tutorial detail page — star rating + AJAX submit
   Also handles: smart back button (uses browser history when
   the user navigated from within the same site).
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {

  /* ============================================================
     BACK BUTTON — use history if we came from within the site
     ============================================================ */
  (function () {
    const backBtn = document.getElementById('tut-back-btn');
    if (!backBtn) return;

    backBtn.addEventListener('click', (e) => {
      if (document.referrer && document.referrer.startsWith(location.origin)) {
        e.preventDefault();
        history.back();
      }
      /* Otherwise, let the href="tutorials.php" take over */
    });
  })();

  /* ============================================================
     RATING SYSTEM
     ============================================================ */
  const submitBtn    = document.getElementById('submit-rating');
  const feedback     = document.getElementById('rating-feedback');
  const qualityGroup = document.querySelector('.stars[data-type="quality"]');
  const clarityGroup = document.querySelector('.stars[data-type="clarity"]');

  if (!submitBtn || !qualityGroup || !clarityGroup) return;

  const tutorialId = parseInt(submitBtn.dataset.tutorialId, 10);
  const csrfToken  = submitBtn.dataset.csrf || '';
  if (!tutorialId || !csrfToken) return;

  const groups = [qualityGroup, clarityGroup];

  /* ---- Current selection (what user is about to submit) ---- */
  let selectedQuality = parseInt(qualityGroup.dataset.value, 10) || 0;
  let selectedClarity = parseInt(clarityGroup.dataset.value, 10) || 0;

  /* ---- Last submitted values — updated after each successful save ---- */
  let submittedQuality = selectedQuality;
  let submittedClarity = selectedClarity;

  /* ---- Prevent concurrent submissions ---- */
  let submitting = false;

  groups.forEach(group => {
    const starEls = group.querySelectorAll('.star');

    starEls.forEach(star => {
      star.addEventListener('mouseenter', () => {
        if (submitting) return;
        const val = parseInt(star.dataset.value, 10);
        starEls.forEach(s =>
          s.classList.toggle('hover', parseInt(s.dataset.value, 10) <= val)
        );
      });

      star.addEventListener('mouseleave', () => {
        starEls.forEach(s => s.classList.remove('hover'));
      });

      star.addEventListener('click', () => {
        if (submitting) return;

        const val = parseInt(star.dataset.value, 10);

        if (group.dataset.type === 'quality') selectedQuality = val;
        else                                  selectedClarity = val;

        group.dataset.value = val;

        starEls.forEach(s =>
          s.classList.toggle('filled', parseInt(s.dataset.value, 10) <= val)
        );

        updateSubmitBtn();
      });
    });
  });

  /* ---- Enable/disable + label management ---- */
  function updateSubmitBtn() {
    const changed  = (selectedQuality !== submittedQuality) || (selectedClarity !== submittedClarity);
    const complete = selectedQuality > 0 && selectedClarity > 0;

    submitBtn.disabled = submitting || !(changed && complete);

    /* Update label based on whether the user already has a saved rating */
    submitBtn.textContent = (submittedQuality > 0 || submittedClarity > 0)
      ? 'Update Rating'
      : 'Submit Rating';
  }

  /* ---- Submit handler ---- */
  submitBtn.addEventListener('click', async () => {
    if (submitting) return;
    submitting = true;

    setButtonLoading(submitBtn, true);
    feedback.textContent = '';
    feedback.style.color = '';

    const fd = new FormData();
    fd.append('csrf_token',  csrfToken);
    fd.append('tutorial_id', tutorialId);
    fd.append('quality',     selectedQuality);
    fd.append('clarity',     selectedClarity);

    try {
      const res  = await fetch('rate-tutorial.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (data.ok) {
        feedback.textContent = '✓ Rating saved';
        feedback.style.color = 'hsl(var(--accent))';

        document.getElementById('stat-quality').textContent =
          data.stats.total > 0 ? data.stats.avg_quality.toFixed(1) : '—';
        document.getElementById('stat-clarity').textContent =
          data.stats.total > 0 ? data.stats.avg_clarity.toFixed(1) : '—';

        document.getElementById('rating-total').textContent =
          data.stats.total + ' ' + (data.stats.total === 1 ? 'rating' : 'ratings');

        /* Remember the values we just saved */
        submittedQuality = selectedQuality;
        submittedClarity = selectedClarity;

        /* Clear the loading class before updating state */
        setButtonLoading(submitBtn, false);
        updateSubmitBtn();

      } else {
        feedback.textContent = data.error || 'Failed to save.';
        feedback.style.color = 'hsl(var(--destructive))';
        setButtonLoading(submitBtn, false);
        updateSubmitBtn();
      }
    } catch (err) {
      feedback.textContent = 'Network error. Try again.';
      feedback.style.color = 'hsl(var(--destructive))';
      setButtonLoading(submitBtn, false);
      updateSubmitBtn();
    } finally {
      submitting = false;
      /* Re-evaluate button state in case submitting flag was blocking */
      updateSubmitBtn();
    }
  });

  /* ---- Set initial button state on page load ---- */
  updateSubmitBtn();
});