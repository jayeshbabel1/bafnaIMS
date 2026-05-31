/**
 * assets/js/catalog.js
 * Tasks 4 (responsive), 5 (sort + view), 7 (search ≥ 3 chars)
 */
(function () {
  'use strict';

  const content      = document.getElementById('catalogContent');
  const loader       = document.getElementById('ajaxLoader');
  const sortSelect   = document.getElementById('sortSelect');
  const searchInput  = document.getElementById('searchInput');
  const searchHint   = document.getElementById('searchHint');
  const totalCountEl = document.getElementById('totalCount');
  const btnGrid      = document.getElementById('viewGrid');
  const btnList      = document.getElementById('viewList');

  if (!content) return;

  // ── State ─────────────────────────────────────────────────────────────────
  const state = {
    cat:   content.dataset.cat   || '',
    color: content.dataset.color || '',
    q:     content.dataset.q     || '',
    sort:  content.dataset.sort  || 'latest',
    view:  localStorage.getItem('catalogView') || 'grid',
    page:  1,
  };

  // ── Init view from localStorage ───────────────────────────────────────────
  applyViewButtons(state.view);
  content.dataset.view = state.view;

  // ── Build AJAX URL ────────────────────────────────────────────────────────
  function buildUrl(page) {
    const p = new URLSearchParams({ page: 'catalog', ajax: '1' });
    if (state.cat)   p.set('cat',   state.cat);
    if (state.color) p.set('color', state.color);
    if (state.q)     p.set('q',     state.q);
    if (state.sort && state.sort !== 'latest') p.set('sort', state.sort);
    if (state.view)  p.set('view',  state.view);
    if (page > 1)    p.set('p',     page);
    return 'index.php?' + p.toString();
  }

  // ── Push browser history ──────────────────────────────────────────────────
  function pushHistory(page) {
    const p = new URLSearchParams({ page: 'catalog' });
    if (state.cat)   p.set('cat',   state.cat);
    if (state.color) p.set('color', state.color);
    if (state.q)     p.set('q',     state.q);
    if (state.sort && state.sort !== 'latest') p.set('sort', state.sort);
    if (page > 1)    p.set('p',     page);
    window.history.pushState({ catalogPage: page }, '', 'index.php?' + p.toString());
  }

  // ── Fetch and render ──────────────────────────────────────────────────────
  async function loadPage(page, push = true) {
    state.page = page;
    if (loader) loader.style.display = 'flex';
    const grid = document.getElementById('productsGrid');
    if (grid) grid.style.opacity = '0.4';

    try {
      const resp = await fetch(buildUrl(page));
      if (!resp.ok) throw new Error('HTTP ' + resp.status);
      const data = await resp.json();

      content.innerHTML = data.html;
      if (totalCountEl) totalCountEl.textContent = data.total;

      // Re-bind after DOM update
      bindShortlist();

      if (push) pushHistory(page);

      // Smooth scroll to top of catalog
      const top = content.getBoundingClientRect().top + window.scrollY - 70;
      window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });

    } catch (e) {
      console.error('Catalog AJAX error:', e);
    } finally {
      if (loader) loader.style.display = 'none';
      const g = document.getElementById('productsGrid');
      if (g) g.style.opacity = '1';
    }
  }

  // ── Pagination: event delegation on content ───────────────────────────────
  content.addEventListener('click', function (e) {
    const btn = e.target.closest('.pag-btn');
    if (!btn || btn.classList.contains('disabled') || btn.classList.contains('active')) return;
    const pg = parseInt(btn.dataset.page, 10);
    if (!isNaN(pg) && pg > 0) loadPage(pg);
  });

  // ── Sort select ───────────────────────────────────────────────────────────
  if (sortSelect) {
    sortSelect.addEventListener('change', function () {
      state.sort = this.value;
      loadPage(1);
    });
  }

  // ── View toggle ───────────────────────────────────────────────────────────
  function applyViewButtons(view) {
    if (btnGrid) btnGrid.classList.toggle('active', view === 'grid');
    if (btnList) btnList.classList.toggle('active', view === 'list');
  }

  if (btnGrid) {
    btnGrid.addEventListener('click', function () {
      state.view = 'grid';
      localStorage.setItem('catalogView', 'grid');
      applyViewButtons('grid');
      loadPage(1);
    });
  }
  if (btnList) {
    btnList.addEventListener('click', function () {
      state.view = 'list';
      localStorage.setItem('catalogView', 'list');
      applyViewButtons('list');
      loadPage(1);
    });
  }

  // ── Search: min 3 chars + debounce ────────────────────────────────────────
  let searchTimer = null;

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      const val = this.value.trim();

      // Show/hide hint
      if (searchHint) {
        searchHint.style.display = (val.length > 0 && val.length < 3) ? 'flex' : 'none';
      }

      clearTimeout(searchTimer);

      // Dont trigger if less than 3 chars (but allow empty = clear search)
      if (val.length > 0 && val.length < 3) return;

      searchTimer = setTimeout(function () {
        state.q = val;
        loadPage(1, true);
      }, 300);
    });

    // Enter key
    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const val = this.value.trim();
        if (val.length === 0 || val.length >= 3) {
          clearTimeout(searchTimer);
          state.q = val;
          loadPage(1, true);
        }
      }
    });
  }

  // ── Browser back/forward ──────────────────────────────────────────────────
  window.addEventListener('popstate', function (e) {
    loadPage(e.state?.catalogPage || 1, false);
  });

  // ── Initial shortlist bind ────────────────────────────────────────────────
  bindShortlist();

})();

/* ── Shortlist AJAX (progressive enhancement) ──────────────────────────────── */
function bindShortlist() {
  document.querySelectorAll('.shortlist-form').forEach(function (form) {
    if (form._bound) return;
    form._bound = true;
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      const btn = form.querySelector('.shortlist-btn, .shortlist-btn-list');
      if (btn) {
        btn.style.transform = 'scale(.8)';
        setTimeout(function () { btn.style.transform = ''; }, 200);
      }
      try {
        await fetch('index.php', { method: 'POST', body: new FormData(form) });
        if (btn) {
          const isSaved = btn.classList.toggle('saved');
          btn.title = isSaved ? 'Remove from shortlist' : 'Add to shortlist';
          btn.innerHTML = isSaved
            ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" fill="#C0392B" stroke="#C0392B"/></svg>'
            : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
        }
      } catch (err) {
        form.submit();
      }
    });
  });
}