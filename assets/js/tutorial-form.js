/* ============================================================
   Shared tutorial add/edit form
   Requires:
     - form#tutorial-form
     - #input_title, #input_category, #preview_title, #preview_badge
     - #thumbnail_upload, #thumb_preview_img, #thumb_placeholder, #preview_thumb_backdrop
     - #guide_file_input, #guide_url_input, #guide_divider
     - #result_file_input, #result_url_input, #result_divider
     - #steps_container, #btn_add_step
     - #resources_container, #btn_add_resource
     - <script type="application/json" id="existing-steps-data"> […] </script>       (optional)
     - <script type="application/json" id="existing-resources-data"> […] </script>   (optional)
   ============================================================ */

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("tutorial-form");
  if (!form) return;

  /* ============================================================
     CUSTOM ANIMATED SELECT
     ============================================================ */
  document.querySelectorAll('.custom-select').forEach((wrapper) => {
    const native  = wrapper.querySelector('.custom-select-native');
    const trigger = wrapper.querySelector('.custom-select-trigger');
    const label   = wrapper.querySelector('.custom-select-label');
    const menu    = wrapper.querySelector('.custom-select-menu');
    if (!native || !trigger || !label || !menu) return;

    const options = menu.querySelectorAll('li[role="option"]');

    /* --- Sync label + aria-selected from the native select --- */
    function syncFromNative() {
      const current = native.value;
      const match = Array.from(native.options).find(o => o.value === current);
      if (match) label.textContent = match.textContent.trim();
      options.forEach(li => {
        li.setAttribute('aria-selected', li.dataset.value === current ? 'true' : 'false');
      });
    }
    syncFromNative();

    /* --- Open / close --- */
    function open() {
      /* Close any other custom selects on the page first */
      document.querySelectorAll('.custom-select.open').forEach(other => {
        if (other !== wrapper) {
          other.classList.remove('open');
          const t = other.querySelector('.custom-select-trigger');
          if (t) t.setAttribute('aria-expanded', 'false');
        }
      });
      wrapper.classList.add('open');
      trigger.setAttribute('aria-expanded', 'true');
    }
    function close() {
      wrapper.classList.remove('open');
      trigger.setAttribute('aria-expanded', 'false');
    }
    function toggle() {
      wrapper.classList.contains('open') ? close() : open();
    }

    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      toggle();
    });

    /* --- Item click --- */
    options.forEach(li => {
      li.addEventListener('click', (e) => {
        e.stopPropagation();
        native.value = li.dataset.value;
        /* Fire `change` so the preview-badge handler runs */
        native.dispatchEvent(new Event('change', { bubbles: true }));
        syncFromNative();
        close();
        trigger.focus();
      });
    });

    /* --- Keyboard --- */
    wrapper.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && wrapper.classList.contains('open')) {
        e.stopPropagation();
        close();
        trigger.focus();
      }
    });

    /* --- Click outside closes --- */
    document.addEventListener('click', (e) => {
      if (!wrapper.contains(e.target)) close();
    });
  });

  /* ---------- Title → preview ---------- */
  const titleInput     = document.getElementById("input_title");
  const categorySelect = document.getElementById("input_category");
  const previewTitle   = document.getElementById("preview_title");
  const previewBadge   = document.getElementById("preview_badge");

  if (titleInput && previewTitle) {
    titleInput.addEventListener("input", (e) => {
      previewTitle.textContent = e.target.value.trim() === "" ? "Tutorial Title" : e.target.value;
    });
  }

  if (categorySelect && previewBadge) {
    categorySelect.addEventListener("change", (e) => {
      const val = e.target.value;
      previewBadge.textContent = val;
      previewBadge.className = "badge";
      const map = {
        'Beginner':     'badge-beginner',
        'Intermediate': 'badge-intermediate',
        'Advanced':     'badge-advanced',
        'Tips & Tricks':'badge-tip',
      };
      previewBadge.classList.add(map[val] || 'badge-tip');
    });
  }

  /* ---------- Thumbnail preview ---------- */
  const thumbnailUpload      = document.getElementById("thumbnail_upload");
  const thumbPreviewImg      = document.getElementById("thumb_preview_img");
  const thumbPlaceholder     = document.getElementById("thumb_placeholder");
  const previewThumbBackdrop = document.getElementById("preview_thumb_backdrop");

  if (thumbnailUpload && thumbPreviewImg) {
    thumbnailUpload.addEventListener("change", function (event) {
      const file = event.target.files[0];
      if (!file) return;

      const reader = new FileReader();
      reader.onload = function (e) {
        thumbPreviewImg.src = e.target.result;
        thumbPreviewImg.classList.add('thumb-preview-img-visible');
        if (thumbPlaceholder) thumbPlaceholder.style.display = "none";

        if (previewThumbBackdrop) {
          if (!previewThumbBackdrop.dataset.originalHtml) {
            previewThumbBackdrop.dataset.originalHtml = previewThumbBackdrop.innerHTML;
          }
          previewThumbBackdrop.style.backgroundImage = `url(${e.target.result})`;
          previewThumbBackdrop.innerHTML = '';
        }

        const uploadLabel = document.querySelector('label[for="thumbnail_upload"]');
        if (uploadLabel) uploadLabel.textContent = 'Change image';
      };
      reader.readAsDataURL(file);
    });
  }

  /* ---------- Reset (add form only) ---------- */
  const resetBtn = form.querySelector('button[type="reset"]');
  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      if (previewThumbBackdrop && previewThumbBackdrop.dataset.originalHtml) {
        previewThumbBackdrop.innerHTML = previewThumbBackdrop.dataset.originalHtml;
        previewThumbBackdrop.style.backgroundImage = '';
      }
      const uploadLabel = document.querySelector('label[for="thumbnail_upload"]');
      if (uploadLabel) uploadLabel.textContent = 'Choose image';

      if (thumbPreviewImg) {
        thumbPreviewImg.src = '';
        thumbPreviewImg.classList.remove('thumb-preview-img-visible');
      }
      if (thumbPlaceholder) thumbPlaceholder.style.display = '';
    });
  }

  /* ---------- Smart file/URL toggle ---------- */
  function setupSmartToggle(fileInput, urlInput, divider) {
    if (!fileInput || !urlInput) return;
    fileInput.addEventListener('change', function () {
      const hasFile = this.files && this.files.length > 0;
      urlInput.style.display = hasFile ? 'none' : 'block';
      if (divider) divider.style.display = hasFile ? 'none' : 'block';
    });
    urlInput.addEventListener('input', function () {
      const hasUrl = this.value.trim().length > 0;
      fileInput.style.display = hasUrl ? 'none' : 'block';
      if (divider) divider.style.display = hasUrl ? 'none' : 'block';
    });
  }

  setupSmartToggle(
    document.getElementById('guide_file_input'),
    document.getElementById('guide_url_input'),
    document.getElementById('guide_divider')
  );
  setupSmartToggle(
    document.getElementById('result_file_input'),
    document.getElementById('result_url_input'),
    document.getElementById('result_divider')
  );

  /* ---------- Client-side file-size guard ---------- */
  form.addEventListener('submit', function (e) {
    const maxBytes = 100 * 1024 * 1024;
    const files = this.querySelectorAll('input[type="file"]');
    for (const f of files) {
      if (f.files[0] && f.files[0].size > maxBytes) {
        e.preventDefault();
        alert(`"${f.files[0].name}" is too large. Max is 100 MB — or paste a URL instead.`);
        return false;
      }
    }
  });

  /* ============================================================
     STEP BUILDER
     ============================================================ */
  const stepsContainer = document.getElementById("steps_container");
  const addStepBtn     = document.getElementById("btn_add_step");

  if (stepsContainer) {
    let stepCount = 0;
    const maxSteps = 15;

    function createStep(title = '', desc = '') {
      if (stepCount >= maxSteps) {
        alert("You have reached the maximum of 15 steps.");
        return;
      }
      stepCount++;
      const stepDiv = document.createElement("div");
      stepDiv.className = "step-box";
      stepDiv.innerHTML = `
        <div class="step-header">
          <span data-step-number>Step ${stepCount}</span>
          <button type="button" class="btn-remove-step">🗑 Remove</button>
        </div>
        <div class="field" style="margin-bottom: 1rem;">
          <input type="text" name="step_title[]" placeholder="Step title" required />
        </div>
        <div class="field">
          <textarea name="step_desc[]" rows="2" class="textarea-no-resize" placeholder="Describe what to do in this step..." required></textarea>
        </div>
      `;
      if (title) stepDiv.querySelector('input[name="step_title[]"]').value = title;
      if (desc)  stepDiv.querySelector('textarea[name="step_desc[]"]').value = desc;

      stepDiv.querySelector(".btn-remove-step").addEventListener("click", function () {
        if (stepsContainer.querySelectorAll(".step-box").length <= 1) {
          alert("A tutorial must have at least 1 step.");
          return;
        }
        stepDiv.remove();
        recalculateSteps();
      });
      stepsContainer.appendChild(stepDiv);
    }

    function recalculateSteps() {
      const boxes = stepsContainer.querySelectorAll(".step-box");
      stepCount = 0;
      boxes.forEach((box) => {
        stepCount++;
        box.querySelector("[data-step-number]").textContent = "Step " + stepCount;
      });
    }

    const stepsDataEl = document.getElementById('existing-steps-data');
    let existingSteps = [];
    if (stepsDataEl) {
      try { existingSteps = JSON.parse(stepsDataEl.textContent); } catch (e) { existingSteps = []; }
    }

    if (Array.isArray(existingSteps) && existingSteps.length > 0) {
      existingSteps.forEach(s => createStep(s.title, s.desc));
    } else {
      createStep();
    }

    if (addStepBtn) addStepBtn.addEventListener("click", () => createStep());
  }

  /* ============================================================
     RESOURCE BUILDER (Presets & Assets)
     ============================================================ */
  const resourcesContainer = document.getElementById("resources_container");
  const addResourceBtn     = document.getElementById("btn_add_resource");

  if (resourcesContainer && addResourceBtn) {
    let resourceCount = 0;
    const maxResources = 10;

    function createResource(type = 'preset', name = '', url = '') {
      if (resourceCount >= maxResources) {
        alert("You have reached the maximum of 10 resources.");
        return;
      }
      resourceCount++;

      const row = document.createElement("div");
      row.className = "resource-row";
      row.innerHTML = `
        <div class="resource-row-fields">
          <select name="resource_type[]" class="resource-type-select" aria-label="Resource type">
            <option value="preset">🎁 Preset</option>
            <option value="asset">📦 Asset</option>
          </select>
          <input type="text"
                 name="resource_name[]"
                 placeholder="Name (e.g. Smooth Transition Preset)"
                 maxlength="100"
                 required />
          <input type="url"
                 name="resource_url[]"
                 placeholder="https://drive.google.com/..."
                 maxlength="500"
                 required />
        </div>
        <button type="button" class="btn-remove-resource" title="Remove resource" aria-label="Remove resource">🗑</button>
      `;

      const typeSelect = row.querySelector('select[name="resource_type[]"]');
      const nameInput  = row.querySelector('input[name="resource_name[]"]');
      const urlInput   = row.querySelector('input[name="resource_url[]"]');

      if (type === 'asset') typeSelect.value = 'asset';
      else typeSelect.value = 'preset';
      nameInput.value = name;
      urlInput.value  = url;

      row.querySelector(".btn-remove-resource").addEventListener("click", () => {
        row.remove();
        resourceCount--;
      });

      resourcesContainer.appendChild(row);
    }

    const resDataEl = document.getElementById('existing-resources-data');
    let existingResources = [];
    if (resDataEl) {
      try { existingResources = JSON.parse(resDataEl.textContent); } catch (e) { existingResources = []; }
    }

    if (Array.isArray(existingResources) && existingResources.length > 0) {
      existingResources.forEach(r => createResource(r.type, r.name, r.url));
    }

    addResourceBtn.addEventListener("click", () => createResource());
  }
});