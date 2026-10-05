/* ============================================================
   Tutorial upload progress overlay.
   Intercepts the tutorial form submit, uploads via XHR, and
   shows a live progress bar. Falls back to native submit if
   XMLHttpRequest / FormData are unavailable.
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('tutorial-form');
  if (!form) return;

  const submitBtn = form.querySelector('button[type="submit"]');
  if (!submitBtn) return;

  if (!window.FormData || !window.XMLHttpRequest) return; // graceful fallback

  /* ---------- Build the overlay once ---------- */
  const overlay = document.createElement('div');
  overlay.className = 'upload-overlay';
  overlay.hidden = true;
  overlay.innerHTML = `
    <div class="upload-overlay-inner" role="dialog" aria-modal="true" aria-labelledby="upload-title">
      <h2 id="upload-title">Uploading your tutorial</h2>
      <div class="upload-progress-track">
        <div class="upload-progress-fill"></div>
      </div>
      <div class="upload-progress-pct">0%</div>
      <div class="upload-status">Starting…</div>
      <button type="button" class="upload-cancel">Cancel upload</button>
    </div>
  `;
  document.body.appendChild(overlay);

  const fill    = overlay.querySelector('.upload-progress-fill');
  const pctEl   = overlay.querySelector('.upload-progress-pct');
  const status  = overlay.querySelector('.upload-status');
  const cancel  = overlay.querySelector('.upload-cancel');

  function setProgress(pct) {
    fill.style.width = pct + '%';
    pctEl.textContent = pct + '%';
  }
  function setStatus(text) { status.textContent = text; }
  function showOverlay() {
    overlay.hidden = false;
    overlay.classList.remove('has-error');
    setProgress(0);
    setStatus('Starting…');
    document.body.style.overflow = 'hidden';
  }
  function hideOverlay() {
    overlay.hidden = true;
    document.body.style.overflow = '';
  }
  function showError(msg) {
    overlay.classList.add('has-error');
    setStatus(msg);
    cancel.textContent = 'Close';
  }

  function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    if (bytes < 1024 * 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
  }

  let currentXhr = null;

  /* ---------- Cancel handling ---------- */
  cancel.addEventListener('click', () => {
    if (currentXhr && currentXhr.readyState !== 4) {
      currentXhr.abort();
      hideOverlay();
      submitBtn.disabled = false;
      submitBtn.classList.remove('btn-loading');
    } else {
      hideOverlay();
    }
  });

  /* ---------- Intercept the submit ---------- */
  form.addEventListener('submit', (e) => {
    if (e.defaultPrevented) return;
    e.preventDefault();
    if (form.dataset.uploading === 'true') return;
    form.dataset.uploading = 'true';

    const fd = new FormData(form);
    const xhr = new XMLHttpRequest();
    currentXhr = xhr;
    const startedAt = Date.now();

    showOverlay();

    /* Live upload progress */
    xhr.upload.addEventListener('progress', (ev) => {
      if (!ev.lengthComputable) {
        setStatus('Uploading…');
        return;
      }
      const pct = Math.round((ev.loaded / ev.total) * 100);
      setProgress(pct);
      if (pct < 100) {
        setStatus('Uploading… ' + formatBytes(ev.loaded) + ' / ' + formatBytes(ev.total));
      } else {
        setStatus('Upload complete. Processing on server…');
      }
    });

    /* Upload has been fully sent, waiting on server */
    xhr.upload.addEventListener('load', () => {
      setProgress(100);
      setStatus('Processing on server…');
    });

    /* Response */
    xhr.addEventListener('load', () => {
      form.dataset.uploading = 'false';

      if (xhr.status < 200 || xhr.status >= 300) {
        showError('Server error (HTTP ' + xhr.status + '). Try again.');
        submitBtn.disabled = false;
        return;
      }

      let data;
      try {
        data = JSON.parse(xhr.responseText);
      } catch (err) {
        showError('Unexpected server response. Try again.');
        submitBtn.disabled = false;
        return;
      }

      if (data.ok && data.redirect) {
        setProgress(100);
        setStatus('Done! Redirecting…');
        const elapsed = Date.now() - startedAt;
        const wait = Math.max(0, 350 - elapsed); // avoid flicker on fast uploads
        setTimeout(() => { window.location.href = data.redirect; }, wait);
      } else {
        showError(data.error || 'Upload failed. Try again.');
        submitBtn.disabled = false;
      }
    });

    xhr.addEventListener('error', () => {
      form.dataset.uploading = 'false';
      showError('Network error. Check your connection and try again.');
      submitBtn.disabled = false;
    });

    xhr.addEventListener('abort', () => {
      form.dataset.uploading = 'false';
      hideOverlay();
      submitBtn.disabled = false;
    });

    xhr.open('POST', form.action, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.send(fd);
  });
});