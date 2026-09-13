/**
 * assets/js/catalog.js — AJAX catalog with sidebar + drawer filter support
 * Fire 4: adds Table view alongside Grid/List (3-way toggle), all driven by
 * Settings → Product Views (user panel) field config on the server.
 *
 * Filters (category, color, available qty, useable length, useable height)
 * are buffered locally and only applied to the catalog when the user clicks
 * "Apply Filters" (desktop sidebar or mobile drawer). Search and Sort remain
 * live/instant. No full page reloads — everything goes through AJAX +
 * history.pushState.
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
  const btnTable     = document.getElementById('viewTable');
  const suggestBox   = document.getElementById('searchSuggestBox');

  if (!content) return;

  function loadSavedView() {
    var v = null;
    try { v = localStorage.getItem('catalogView'); } catch (e) {}
    if (v === 'grid' || v === 'list' || v === 'table') return v;
    return content.dataset.view || window.CATALOG_DEFAULT_VIEW || 'grid';
  }
  function saveView(v) {
    try { localStorage.setItem('catalogView', v); } catch (e) {}
  }

  //  State 
  const state = {
    cat:      content.dataset.cat      || '',
    color:    content.dataset.color    || '',
    q:        content.dataset.q        || '',
    sort:     content.dataset.sort     || 'latest',
    view:     loadSavedView(),
    page:     1,
    sqft_min: content.dataset.sqftMin  || '',
    sqft_max: content.dataset.sqftMax  || '',
    sl_min:   content.dataset.slMin    || '',
    sl_max:   content.dataset.slMax    || '',
    sh_min:   content.dataset.shMin    || '',
    sh_max:   content.dataset.shMax    || '',
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
    if (paramSet(state.sl_min))   p.set('sl_min',   state.sl_min);
    if (paramSet(state.sl_max))   p.set('sl_max',   state.sl_max);
    if (paramSet(state.sh_min))   p.set('sh_min',   state.sh_min);
    if (paramSet(state.sh_max))   p.set('sh_max',   state.sh_max);
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
    if (paramSet(state.sl_min))   p.set('sl_min',   state.sl_min);
    if (paramSet(state.sl_max))   p.set('sl_max',   state.sl_max);
    if (paramSet(state.sh_min))   p.set('sh_min',   state.sh_min);
    if (paramSet(state.sh_max))   p.set('sh_max',   state.sh_max);
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

  // Pagination (delegated — works after AJAX re-render)
  content.addEventListener('click', function (e) {
    const btn = e.target.closest('.pag-btn');
    if (!btn || btn.classList.contains('disabled') || btn.classList.contains('active')) return;
    const pg = parseInt(btn.dataset.page, 10);
    if (!isNaN(pg) && pg > 0) loadPage(pg);
  });

  // ── Sort — instant, independent of Apply Filters 
  if (sortSelect) {
    sortSelect.addEventListener('change', function () { state.sort = this.value; loadPage(1); });
  }

  // ── View toggle (3-way: grid / list / table) 
  function applyViewButtons(view) {
    if (btnGrid)  btnGrid.classList.toggle('active', view === 'grid');
    if (btnList)  btnList.classList.toggle('active', view === 'list');
    if (btnTable) btnTable.classList.toggle('active', view === 'table');
  }
  function switchView(view) {
    if (view === state.view) return;
    state.view = view;
    saveView(view);
    applyViewButtons(view);
    loadPage(1);
  }
  if (btnGrid)  btnGrid.addEventListener('click', function () { switchView('grid'); });
  if (btnList)  btnList.addEventListener('click', function () { switchView('list'); });
  if (btnTable) btnTable.addEventListener('click', function () { switchView('table'); });

  //  Search — instant (debounced), independent of Apply Filters 
  //  Search — instant (debounced), independent of Apply Filters 
  let searchTimer = null;
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      const val = this.value.trim();
      clearTimeout(searchTimer);
      fetchSuggestions(val);
      if (val.length > 0 && val.length < 3) return;
      searchTimer = setTimeout(function () { state.q = val; loadPage(1); }, 350);
    });
    searchInput.addEventListener('focus', function () {
      fetchSuggestions(this.value.trim());
    });
    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const val = this.value.trim();
        if (val.length === 0 || val.length >= 3) {
          clearTimeout(searchTimer);
          state.q = val;
          loadPage(1);
          saveSearchTerm(val);
        }
        closeSuggestBox();
      }
      if (e.key === 'Escape') closeSuggestBox();
    });
  }
  if (searchClear) {
    searchClear.addEventListener('click', function () {
      if (searchInput) searchInput.value = '';
      state.q = ''; loadPage(1);
      closeSuggestBox();
    });
  }

  // ── Suggest / history dropdown ────────────────────────────────────────
  let suggestTimer = null;
  function esc(s) {
    const d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
  }

  function fetchSuggestions(q) {
    if (!suggestBox) return;
    clearTimeout(suggestTimer);
    suggestTimer = setTimeout(function () {
      fetch('index.php?page=catalog&ajax_suggest=1&q=' + encodeURIComponent(q))
        .then(function (r) { return r.json(); })
        .then(function (d) { renderSuggestBox(d.recent || [], d.products || [], q); })
        .catch(function () {});
    }, 150);
  }

  function renderSuggestBox(recent, products, q) {
    if (!suggestBox) return;
    let html = '';

    if (recent.length) {
      html += '<div class="ss-section-label">Recent Searches</div>';
      recent.forEach(function (r) {
        html += '<div class="ss-item" data-type="recent" data-query="' + esc(r.query) + '">' +
          '<span class="ss-item-icon">' + iconClock() + '</span>' +
          '<span class="ss-item-text">' + esc(r.query) + '</span>' +
          '<span class="ss-item-remove" data-remove-id="' + r.id + '">' + iconClose() + '</span>' +
        '</div>';
      });
    }

    if (products.length) {
      html += '<div class="ss-section-label">Products</div>';
      products.forEach(function (p) {
        const thumb = p.primary_photo
          ? '<div class="ss-item-thumb"><img src="assets/uploads/photos/' + esc(p.primary_photo) + '" alt=""/></div>'
          : '<div class="ss-item-thumb"></div>';
        html += '<div class="ss-item" data-type="product" data-id="' + p.id + '" data-name="' + esc(p.name) + '">' +
          thumb +
          '<div style="flex:1;min-width:0;">' +
            '<div class="ss-item-text">' + esc(p.name) + '</div>' +
            '<div class="ss-item-sub">Lot ' + esc(p.quarry_number) + '</div>' +
          '</div>' +
        '</div>';
      });
    }

    if (!recent.length && !products.length) {
      html += '<div class="ss-empty">' + (q ? 'No matches found.' : 'No recent searches.') + '</div>';
    }

    if (recent.length) {
      html += '<button type="button" class="ss-clear-history">Clear History</button>';
    }

    suggestBox.innerHTML = html;
    suggestBox.classList.add('open');
    bindSuggestBoxEvents();
  }

  function bindSuggestBoxEvents() {
    suggestBox.querySelectorAll('.ss-item').forEach(function (item) {
      item.addEventListener('click', function (e) {
        if (e.target.closest('.ss-item-remove')) return;
        if (item.dataset.type === 'recent') {
          const q = item.dataset.query;
          searchInput.value = q;
          state.q = q;
          loadPage(1);
          saveSearchTerm(q);
        } else if (item.dataset.type === 'product') {
          window.location.href = 'index.php?page=product&id=' + item.dataset.id;
        }
        closeSuggestBox();
      });
    });
    suggestBox.querySelectorAll('.ss-item-remove').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        const id = btn.dataset.removeId;
        fetch('index.php?page=catalog&ajax_search_delete=1', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(window.CSRF_TOKEN || '')
        }).then(function () { fetchSuggestions(searchInput.value.trim()); });
      });
    });
    const clearBtn = suggestBox.querySelector('.ss-clear-history');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        fetch('index.php?page=catalog&ajax_search_clear=1', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'csrf_token=' + encodeURIComponent(window.CSRF_TOKEN || '')
        }).then(function () { fetchSuggestions(searchInput.value.trim()); });
      });
    }
  }

  function saveSearchTerm(q) {
    if (!q || q.length < 2) return;
    fetch('index.php?page=catalog&ajax_search_save=1', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'q=' + encodeURIComponent(q) + '&csrf_token=' + encodeURIComponent(window.CSRF_TOKEN || '')
    });
  }

  function closeSuggestBox() {
    if (suggestBox) suggestBox.classList.remove('open');
  }

  function iconClock() {
    return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
  }
  function iconClose() {
    return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
  }

  document.addEventListener('click', function (e) {
    if (suggestBox && !e.target.closest('.catalog-search-wrap')) closeSuggestBox();
  });

  //  Browser back/forward 
  window.addEventListener('popstate', function (e) {
    const urlParams = new URLSearchParams(window.location.search);
    state.cat      = urlParams.get('cat')      || '';
    state.color    = urlParams.get('color')    || '';
    state.q        = urlParams.get('q')        || '';
    state.sort     = urlParams.get('sort')     || 'latest';
    state.sqft_min = urlParams.get('sqft_min') || '';
    state.sqft_max = urlParams.get('sqft_max') || '';
    state.sl_min   = urlParams.get('sl_min')   || '';
    state.sl_max   = urlParams.get('sl_max')   || '';
    state.sh_min   = urlParams.get('sh_min')   || '';
    state.sh_max   = urlParams.get('sh_max')   || '';
    loadPage(e.state && e.state.catalogPage ? e.state.catalogPage : 1, false);
  });

  // ══════════════════════════════════════════════════════════════════════════
  // PUBLIC API — Apply Filters flow (desktop sidebar + mobile drawer)
  // ══════════════════════════════════════════════════════════════════════════
  window.catalogApplyAllFilters = function (newState) {
    if (!newState) return;
    if (newState.cat      !== undefined) state.cat      = newState.cat;
    if (newState.color    !== undefined) state.color    = newState.color;
    if (newState.sqft_min !== undefined) state.sqft_min = newState.sqft_min;
    if (newState.sqft_max !== undefined) state.sqft_max = newState.sqft_max;
    if (newState.sl_min   !== undefined) state.sl_min   = newState.sl_min;
    if (newState.sl_max   !== undefined) state.sl_max   = newState.sl_max;
    if (newState.sh_min   !== undefined) state.sh_min   = newState.sh_min;
    if (newState.sh_max   !== undefined) state.sh_max   = newState.sh_max;
    loadPage(1);
  };

  window.catalogUpdateRange = function (key, val) {
    state[key] = val;
  };

  window.catalogSetRange = function (sqftMin, sqftMax, slMin, slMax, shMin, shMax) {
    state.sqft_min = sqftMin;
    state.sqft_max = sqftMax;
    state.sl_min   = slMin;
    state.sl_max   = slMax;
    state.sh_min   = shMin;
    state.sh_max   = shMax;
    loadPage(1);
  };

  window.catalogGetState = function () {
    return Object.assign({}, state);
  };

  //  Initial shortlist bind 
  bindShortlist();

  // If the saved/localStorage view differs from what the server rendered
  // (SSR always uses the panel's configured default view), silently swap in
  // without a jarring flash — the initial paint was already fast/SSR'd.
  if (state.view !== (content.dataset.view || window.CATALOG_DEFAULT_VIEW || 'grid')) {
    loadPage(1, false);
  }
})();

/* Shortlist AJAX  */
function bindShortlist() {
  document.querySelectorAll('.shortlist-form').forEach(function (form) {
    if (form._bound) return;
    form._bound = true;
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      const btn = form.querySelector('.shortlist-btn, .shortlist-btn-list');
      if (btn) { btn.style.transform = 'scale(.8)'; setTimeout(function () { btn.style.transform = ''; }, 200); }
      try {
        await fetch('index.php', { method: 'POST', body: new FormData(form) });
        if (btn) {
          const saved = btn.classList.toggle('saved');
          btn.title = saved ? 'Remove from shortlist' : 'Add to shortlist';
          const heartFill  = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" fill="#e11d48" stroke="#e11d48"/></svg>';
          const heartEmpty = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
          btn.innerHTML = saved ? heartFill : heartEmpty;
        }
      } catch (err) { form.submit(); }
    });
  });
}