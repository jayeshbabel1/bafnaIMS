/**
 * assets/js/catalog.js — AJAX catalog with sidebar + drawer filter support
 */
(function () {
  'use strict';

  const content      = document.getElementById('catalogContent');
  const loader       = document.getElementById('ajaxLoader');
  const sortSelect   = document.getElementById('sortSelect');
  const searchInput  = document.getElementById('searchInput');
  const searchClear  = document.getElementById('searchClear');
  const totalCountEl = document.getElementById('totalCount');
  const btnGrid      = document.getElementById('viewGrid');
  const btnList      = document.getElementById('viewList');

  if (!content) return;

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

  applyViewButtons(state.view);

  function paramSet(v) { return v !== null && v !== undefined && String(v).trim() !== ''; }

  function buildUrl(page) {
    const p = new URLSearchParams({ page: 'catalog', ajax: '1' });
    if (paramSet(state.cat))      p.set('cat',      state.cat);
    if (paramSet(state.color))    p.set('color',    state.color);
    if (paramSet(state.q))        p.set('q',        state.q);
    if (state.sort !== 'latest')  p.set('sort',     state.sort);
    if (paramSet(state.view))     p.set('view',     state.view);
    if (paramSet(state.sqft_min)) p.set('sqft_min', state.sqft_min);
    if (paramSet(state.sqft_max)) p.set('sqft_max', state.sqft_max);
    if (paramSet(state.size_min)) p.set('size_min', state.size_min);
    if (paramSet(state.size_max)) p.set('size_max', state.size_max);
    if (page > 1)                 p.set('p',        page);
    return 'index.php?' + p.toString();
  }

  function pushHistory(page) {
    const p = new URLSearchParams({ page: 'catalog' });
    if (paramSet(state.cat))      p.set('cat',      state.cat);
    if (paramSet(state.color))    p.set('color',    state.color);
    if (paramSet(state.q))        p.set('q',        state.q);
    if (state.sort !== 'latest')  p.set('sort',     state.sort);
    if (paramSet(state.sqft_min)) p.set('sqft_min', state.sqft_min);
    if (paramSet(state.sqft_max)) p.set('sqft_max', state.sqft_max);
    if (paramSet(state.size_min)) p.set('size_min', state.size_min);
    if (paramSet(state.size_max)) p.set('size_max', state.size_max);
    if (page > 1)                 p.set('p',        page);
    window.history.pushState({ catalogPage: page }, '', 'index.php?' + p.toString());
  }

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
      const top = content.getBoundingClientRect().top + window.scrollY - 80;
      window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    } catch (e) {
      console.error('Catalog AJAX:', e);
    } finally {
      if (loader) loader.style.display = 'none';
      const g = document.getElementById('productsGrid');
      if (g) g.style.opacity = '1';
    }
  }

  // Pagination
  content.addEventListener('click', function(e) {
    const btn = e.target.closest('.pag-btn');
    if (!btn || btn.classList.contains('disabled') || btn.classList.contains('active')) return;
    const pg = parseInt(btn.dataset.page, 10);
    if (!isNaN(pg) && pg > 0) loadPage(pg);
  });

  // Sort
  if (sortSelect) {
    sortSelect.addEventListener('change', function() { state.sort = this.value; loadPage(1); });
  }

  // View toggle
  function applyViewButtons(view) {
    if (btnGrid) btnGrid.classList.toggle('active', view === 'grid');
    if (btnList) btnList.classList.toggle('active', view === 'list');
  }
  if (btnGrid) { btnGrid.addEventListener('click', function() { state.view = 'grid'; localStorage.setItem('catalogView','grid'); applyViewButtons('grid'); loadPage(1); }); }
  if (btnList) { btnList.addEventListener('click', function() { state.view = 'list'; localStorage.setItem('catalogView','list'); applyViewButtons('list'); loadPage(1); }); }

  // Search
  let searchTimer = null;
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const val = this.value.trim();
      clearTimeout(searchTimer);
      if (val.length > 0 && val.length < 3) return;
      searchTimer = setTimeout(function() { state.q = val; loadPage(1); }, 350);
    });
    searchInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const val = this.value.trim();
        if (val.length === 0 || val.length >= 3) { clearTimeout(searchTimer); state.q = val; loadPage(1); }
      }
    });
  }
  if (searchClear) {
    searchClear.addEventListener('click', function() {
      if (searchInput) searchInput.value = '';
      state.q = ''; loadPage(1);
    });
  }

  // History
  window.addEventListener('popstate', function(e) {
    const urlParams = new URLSearchParams(window.location.search);
    state.sqft_min = urlParams.get('sqft_min') || '';
    state.sqft_max = urlParams.get('sqft_max') || '';
    state.size_min = urlParams.get('size_min') || '';
    state.size_max = urlParams.get('size_max') || '';
    loadPage(e.state && e.state.catalogPage ? e.state.catalogPage : 1, false);
  });

  // Public API for sidebar/drawer range updates
  let rangeTimer = null;
  function scheduleRangeReload() {
    clearTimeout(rangeTimer);
    rangeTimer = setTimeout(function() { state.page = 1; loadPage(1, true); }, 600);
  }

  window.catalogUpdateRange = function(key, val) {
    state[key] = val;
    scheduleRangeReload();
  };

  window.catalogSetRange = function(sqftMin, sqftMax, sizeMin, sizeMax) {
    state.sqft_min = sqftMin;
    state.sqft_max = sqftMax;
    state.size_min = sizeMin;
    state.size_max = sizeMax;
    loadPage(1, true);
  };

  // Drawer inputs sync to state directly
  ['drawerSqftMin','drawerSqftMax','drawerSizeMin','drawerSizeMax'].forEach(function(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const key = id.replace('drawer','').replace('Sqft','sqft_').replace('Size','size_').toLowerCase();
    // map: drawerSqftMin -> sqft_min, drawerSqftMax -> sqft_max, etc.
    const keyMap = {
      'drawerSqftMin': 'sqft_min',
      'drawerSqftMax': 'sqft_max',
      'drawerSizeMin': 'size_min',
      'drawerSizeMax': 'size_max',
    };
    el.addEventListener('change', function() { state[keyMap[id]] = this.value.trim(); });
  });

  // Initial shortlist bind
  bindShortlist();
})();

/* Shortlist AJAX ────────────────────────────────────────────────────────── */
function bindShortlist() {
  document.querySelectorAll('.shortlist-form').forEach(function(form) {
    if (form._bound) return;
    form._bound = true;
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      const btn = form.querySelector('.shortlist-btn, .shortlist-btn-list');
      if (btn) { btn.style.transform = 'scale(.8)'; setTimeout(function(){ btn.style.transform=''; }, 200); }
      try {
        await fetch('index.php', { method: 'POST', body: new FormData(form) });
        if (btn) {
          const saved = btn.classList.toggle('saved');
          btn.title = saved ? 'Remove from shortlist' : 'Add to shortlist';
          const heartFill = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" fill="#e11d48" stroke="#e11d48"/></svg>';
          const heartEmpty = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
          btn.innerHTML = saved ? heartFill : heartEmpty;
        }
      } catch(err) { form.submit(); }
    });
  });
}
