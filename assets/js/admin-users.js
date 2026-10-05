/* ============================================================
   Admin — Manage Users
   Live-search (debounced) + paginated AJAX.
   Progressive enhancement: form submits normally if JS is off.
   ============================================================ */

(function () {
  'use strict';

  const state = window.ADMIN_USERS_STATE || { search: '', page: 1, totalPages: 1 };

  const els = {
    form:       document.getElementById('admin-search-form'),
    input:      document.getElementById('admin-search-input'),
    rows:       document.getElementById('admin-user-rows'),
    pagination: document.getElementById('admin-pagination'),
    prevBtn:    document.getElementById('admin-page-prev'),
    nextBtn:    document.getElementById('admin-page-next'),
    pageInfo:   document.getElementById('admin-page-info'),
  };

  if (!els.form || !els.input || !els.rows) return;

  let currentPage = state.page;
  let totalPages  = state.totalPages;
  let inFlight    = null;
  let debounceTimer = null;

  function esc(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function buildRolePill(role) {
    if (role === 'admin') {
      return '<span class="admin-role-pill admin-role-admin">Admin</span>';
    }
    return '<span class="admin-role-pill admin-role-user">User</span>';
  }

  function renderRow(u) {
    const youTag   = u.is_me ? ' <em class="admin-you-tag">you</em>' : '';
    const rolePill = buildRolePill(u.role);

    return `
      <div class="admin-user-row">
        <div class="admin-user-cell-user">
          <img src="${esc(u.avatar)}" alt="" class="admin-user-avatar" />
          <div class="admin-user-meta">
            <span class="admin-user-name">${esc(u.name)}${youTag}</span>
            <span class="admin-user-handle">@${esc(u.username)}</span>
          </div>
        </div>

        <div class="admin-user-cell-email" title="${esc(u.email)}">
          ${esc(u.email)}
        </div>

        <div class="admin-user-cell-role">
          ${rolePill}
        </div>

        <div class="admin-user-cell-count">
          ${u.tutorial_count}
        </div>

        <div class="admin-user-cell-actions">
          <a href="user.php?u=${encodeURIComponent(u.username)}" class="admin-row-btn" title="View public profile">👁</a>
          <a href="profile.php?id=${u.user_id}" class="admin-row-btn admin-row-btn-edit" title="Edit user">✎</a>
        </div>
      </div>
    `;
  }

  function renderList(payload) {
    if (!payload.users || payload.users.length === 0) {
      els.rows.innerHTML = `<div class="admin-user-empty">${
        payload.search ? 'No users match your search.' : 'No users yet.'
      }</div>`;
    } else {
      els.rows.innerHTML = payload.users.map(renderRow).join('');
    }

    totalPages  = payload.total_pages;
    currentPage = payload.page;

    if (els.pagination) {
      els.pagination.hidden = payload.total_pages <= 1;
      if (els.prevBtn) els.prevBtn.disabled = payload.page <= 1;
      if (els.nextBtn) els.nextBtn.disabled = payload.page >= payload.total_pages;
      if (els.pageInfo) els.pageInfo.textContent =
        `Page ${payload.page} of ${payload.total_pages} · ${payload.total} users`;
    }

    const url = new URL(window.location);
    if (payload.search) url.searchParams.set('q', payload.search);
    else                url.searchParams.delete('q');
    if (payload.page > 1) url.searchParams.set('page', payload.page);
    else                  url.searchParams.delete('page');
    history.replaceState({}, '', url);
  }

  async function fetchUsers() {
    if (inFlight) inFlight.abort();
    const ctrl = new AbortController();
    inFlight = ctrl;

    const params = new URLSearchParams({
      ajax: '1',
      q:    els.input.value.trim(),
      page: currentPage,
    });

    try {
      const res = await fetch(`admin-users.php?${params.toString()}`, {
        signal: ctrl.signal,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      if (data && data.ok) renderList(data);
    } catch (err) {
      if (err.name !== 'AbortError') console.error('[admin-users] fetch failed:', err);
    } finally {
      if (inFlight === ctrl) inFlight = null;
    }
  }

  function debouncedFetch(delay = 250) {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      currentPage = 1;
      fetchUsers();
    }, delay);
  }

  els.input.addEventListener('input', () => {
    debouncedFetch();
  });

  els.form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (debounceTimer) clearTimeout(debounceTimer);
    currentPage = 1;
    fetchUsers();
  });

  els.input.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      e.preventDefault();
      els.input.value = '';
      currentPage = 1;
      fetchUsers();
    }
  });

  if (els.prevBtn) {
    els.prevBtn.addEventListener('click', () => {
      if (currentPage <= 1) return;
      currentPage--;
      fetchUsers();
    });
  }
  if (els.nextBtn) {
    els.nextBtn.addEventListener('click', () => {
      if (currentPage >= totalPages) return;
      currentPage++;
      fetchUsers();
    });
  }
})();