/**
 * assets/js/catalog.js
 * Fixed: range filter inputs now correctly update state and trigger AJAX.
 * All existing functionality (search, sort, view, pagination, shortlist) preserved.
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

  // ── State ──────────────────────────────────────────────────────────────────
  const state = {
    cat:      content.dataset.cat      || '',
    color:    content.dataset.color    || '',
    q:        content.dataset.q        || '',
    sort:     content.dataset.sort     || 'latest',
    view:     localStorage.getItem('catalogView') || 'grid',
    page:     1,
    sqft_min: content.dataset.sqftMin  || '',
    sqft_max: content.dataset.sqftMax  || '',
    size_min: content.dataset.sizeMin  || '',
    size_max: content.dataset.sizeMax  || '',
  };

  // ── Init view from localStorage ────────────────────────────────────────────
  applyViewButtons(state.view);
  content.dataset.view = state.view;

  // ── Helper: a param is "set" when it's a non-empty string ─────────────────
  // NOTE: Do NOT use truthiness check — "0" is falsy but is a valid filter value.
  function paramSet(val) {
    return val !== null && val !== undefined && String(val).trim() !== '';
  }

  // ── Build AJAX URL ─────────────────────────────────────────────────────────
  function buildUrl(page) {
    const p = new URLSearchParams({ page: 'catalog', ajax: '1' });
    if (paramSet(state.cat))      p.set('cat',      state.cat);
    if (paramSet(state.color))    p.set('color',    state.color);
    if (paramSet(state.q))        p.set('q',        state.q);
    if (state.sort && state.sort !== 'latest') p.set('sort', state.sort);
    if (paramSet(state.view))     p.set('view',     state.view);
    if (paramSet(state.sqft_min)) p.set('sqft_min', state.sqft_min);
    if (paramSet(state.sqft_max)) p.set('sqft_max', state.sqft_max);
    if (paramSet(state.size_min)) p.set('size_min', state.size_min);
    if (paramSet(state.size_max)) p.set('size_max', state.size_max);
    if (page > 1)                 p.set('p',        page);
    return 'index.php?' + p.toString();
  }

  // ── Push browser history ───────────────────────────────────────────────────
  function pushHistory(page) {
    const p = new URLSearchParams({ page: 'catalog' });
    if (paramSet(state.cat))      p.set('cat',      state.cat);
    if (paramSet(state.color))    p.set('color',    state.color);
    if (paramSet(state.q))        p.set('q',        state.q);
    if (state.sort && state.sort !== 'latest') p.set('sort', state.sort);
    if (paramSet(state.sqft_min)) p.set('sqft_min', state.sqft_min);
    if (paramSet(state.sqft_max)) p.set('sqft_max', state.sqft_max);
    if (paramSet(state.size_min)) p.set('size_min', state.size_min);
    if (paramSet(state.size_max)) p.set('size_max', state.size_max);
    if (page > 1)                 p.set('p',        page);
    window.history.pushState({ catalogPage: page }, '', 'index.php?' + p.toString());
  }

  // ── Fetch and render ───────────────────────────────────────────────────────
  async function loadPage(page, push) {
    if (push === undefined) push = true;
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

      bindShortlist();
      if (push) pushHistory(page);

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

  // ── Pagination: event delegation on content ────────────────────────────────
  content.addEventListener('click', function (e) {
    const btn = e.target.closest('.pag-btn');
    if (!btn || btn.classList.contains('disabled') || btn.classList.contains('active')) return;
    const pg = parseInt(btn.dataset.page, 10);
    if (!isNaN(pg) && pg > 0) loadPage(pg);
  });

  // ── Sort select ────────────────────────────────────────────────────────────
  if (sortSelect) {
    sortSelect.addEventListener('change', function () {
      state.sort = this.value;
      loadPage(1);
    });
  }

  // ── View toggle ────────────────────────────────────────────────────────────
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
      if (searchHint) {
        searchHint.style.display = (val.length > 0 && val.length < 3) ? 'flex' : 'none';
      }
      clearTimeout(searchTimer);
      if (val.length > 0 && val.length < 3) return;
      searchTimer = setTimeout(function () {
        state.q = val;
        loadPage(1, true);
      }, 300);
    });

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

  // ── Browser back/forward ───────────────────────────────────────────────────
  window.addEventListener('popstate', function (e) {
    // Sync input values from URL when navigating history
    const urlParams = new URLSearchParams(window.location.search);
    state.sqft_min = urlParams.get('sqft_min') || '';
    state.sqft_max = urlParams.get('sqft_max') || '';
    state.size_min = urlParams.get('size_min') || '';
    state.size_max = urlParams.get('size_max') || '';
    syncRangeInputsFromState();
    loadPage(e.state && e.state.catalogPage ? e.state.catalogPage : 1, false);
  });

  // ── Sync range input DOM values FROM state (used on popstate) ─────────────
  function syncRangeInputsFromState() {
    const sqftMinEl = document.getElementById('sqftMin');
    const sqftMaxEl = document.getElementById('sqftMax');
    const sizeMinEl = document.getElementById('sizeMin');
    const sizeMaxEl = document.getElementById('sizeMax');
    if (sqftMinEl) sqftMinEl.value = state.sqft_min;
    if (sqftMaxEl) sqftMaxEl.value = state.sqft_max;
    if (sizeMinEl) sizeMinEl.value = state.size_min;
    if (sizeMaxEl) sizeMaxEl.value = state.size_max;
    updateRangeCardUI('rfCardSqft', 'sqftClear',
      paramSet(state.sqft_min) || paramSet(state.sqft_max));
    updateRangeCardUI('rfCardSize', 'sizeClear',
      paramSet(state.size_min) || paramSet(state.size_max));
  }

  // ── Update card active class + badge + clear-button visibility ─────────────
  function updateRangeCardUI(cardId, clearBtnId, isActive) {
    const card     = document.getElementById(cardId);
    const clearBtn = document.getElementById(clearBtnId);
    if (card) {
      card.classList.toggle('range-filter-card--active', isActive);
      const badge = card.querySelector('.range-filter-badge');
      if (badge) badge.style.display = isActive ? '' : 'none';
    }
    if (clearBtn) clearBtn.style.display = isActive ? 'flex' : 'none';
  }

  // ── Range filter debounce timer ────────────────────────────────────────────
  let rangeTimer = null;

  function scheduleRangeReload() {
    clearTimeout(rangeTimer);
    rangeTimer = setTimeout(function () {
      state.page = 1;
      loadPage(1, true);
    }, 600);   // 600 ms after last keystroke
  }

  // ── Wire up a single range input ──────────────────────────────────────────
  function bindRangeInput(inputId, stateKey, cardId, clearBtnId, pairStateKey) {
    const el = document.getElementById(inputId);
    if (!el) return;

    function onChange() {
      // Use the raw value string — trim whitespace but keep "0"
      state[stateKey] = el.value.trim();
      const isActive  = paramSet(state[stateKey]) || paramSet(state[pairStateKey]);
      updateRangeCardUI(cardId, clearBtnId, isActive);
      scheduleRangeReload();
    }

    // Both 'input' (live) and 'change' (on blur / spin arrows) for full coverage
    el.addEventListener('input',  onChange);
    el.addEventListener('change', onChange);
  }

  // Sqft range
  bindRangeInput('sqftMin', 'sqft_min', 'rfCardSqft', 'sqftClear', 'sqft_max');
  bindRangeInput('sqftMax', 'sqft_max', 'rfCardSqft', 'sqftClear', 'sqft_min');

  // Size range
  bindRangeInput('sizeMin', 'size_min', 'rfCardSize', 'sizeClear', 'size_max');
  bindRangeInput('sizeMax', 'size_max', 'rfCardSize', 'sizeClear', 'size_min');

  // ── Clear buttons ──────────────────────────────────────────────────────────
  function bindClearBtn(clearBtnId, minId, maxId, minKey, maxKey, cardId) {
    const btn = document.getElementById(clearBtnId);
    if (!btn) return;
    btn.addEventListener('click', function () {
      const minEl = document.getElementById(minId);
      const maxEl = document.getElementById(maxId);
      if (minEl) minEl.value = '';
      if (maxEl) maxEl.value = '';
      state[minKey] = '';
      state[maxKey] = '';
      updateRangeCardUI(cardId, clearBtnId, false);
      clearTimeout(rangeTimer);
      state.page = 1;
      loadPage(1, true);
    });
  }

  bindClearBtn('sqftClear', 'sqftMin', 'sqftMax', 'sqft_min', 'sqft_max', 'rfCardSqft');
  bindClearBtn('sizeClear', 'sizeMin', 'sizeMax', 'size_min', 'size_max', 'rfCardSize');

  // ── Initial shortlist bind ─────────────────────────────────────────────────
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