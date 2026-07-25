/**
 * assets/js/admin.products.js
 * AJAX pagination · sortable columns (table view only) · 2-char search min ·
 * health filter support · Fire 3: Grid/List/Table view switcher w/ localStorage
 */
(function () {
  'use strict';

  var content    = document.getElementById('adminProductsContent');
  var pagWrap    = document.getElementById('adminPaginationWrap');
  var countEl    = document.getElementById('adminProductsCount');
  var searchEl   = document.getElementById('adminProductSearch');
  var clearBtn   = document.getElementById('adminSearchClear');
  var perPageEl  = document.getElementById('adminPerPage');
  var catTabs    = document.getElementById('adminCatTabs');
  var loader     = document.getElementById('adminProductsLoader');
  var viewSwitch = document.getElementById('apvViewSwitch');

  if (!content) return;

  var LS_KEY = 'admin_product_view';
  var urlParams     = new URLSearchParams(window.location.search);
  var initialFilter = urlParams.get('filter') || '';

  function loadSavedView() {
    try {
      var v = localStorage.getItem(LS_KEY);
      if (v === 'grid' || v === 'list' || v === 'table') return v;
    } catch (e) {}
    return (window.ADMIN_PRODUCT_DEFAULT_VIEW === 'grid' || window.ADMIN_PRODUCT_DEFAULT_VIEW === 'list' || window.ADMIN_PRODUCT_DEFAULT_VIEW === 'table')
      ? window.ADMIN_PRODUCT_DEFAULT_VIEW : 'table';
  }
  function saveView(v) {
    try { localStorage.setItem(LS_KEY, v); } catch (e) {}
  }

  var state = {
    q:      '',
    cat:    '',
    per:    24,
    page:   1,
    sort:   '',        // column key (table view only)
    dir:    'ASC',
    filter: initialFilter,
    view:   loadSavedView(),
  };

  function applyViewButtons() {
    if (!viewSwitch) return;
    viewSwitch.querySelectorAll('.apv-view-btn').forEach(function (btn) {
      btn.classList.toggle('active', btn.dataset.view === state.view);
    });
  }

  //  Core fetch 
  function loadProducts() {
    if (loader) loader.style.display = 'flex';
    if (content) content.style.opacity = '0.4';

    var params = new URLSearchParams({
      page:          'products',
      ajax_products: '1',
      per:           state.per,
      p:             state.page,
      view:          state.view,
    });
    if (state.q)      params.set('q',      state.q);
    if (state.cat)    params.set('cat',    state.cat);
    if (state.sort)   params.set('sort',   state.sort);
    if (state.dir)    params.set('dir',    state.dir);
    if (state.filter) params.set('filter', state.filter);

    fetch('index.php?' + params.toString())
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (content) {
          content.innerHTML = data.html;
          content.style.opacity = '1';
          bindConfirm();
        }
        if (pagWrap) { pagWrap.innerHTML = data.pagination || ''; bindPagination(); }
        if (countEl) {
          var start = data.total === 0 ? 0 : (state.page - 1) * state.per + 1;
          var end   = Math.min(state.page * state.per, data.total);
          countEl.textContent = data.total + ' products'
            + (data.total > 0 ? ' · showing ' + start + '–' + end : '');
        }
        updateSortIcons(data.sort || '', data.dir || 'ASC');
        applyViewButtons();
      })
      .catch(function (e) {
        console.error('Admin products AJAX:', e);
        if (content) content.style.opacity = '1';
      })
      .finally(function () {
        if (loader) loader.style.display = 'none';
      });
  }

  //  Pagination 
  function bindPagination() {
    if (!pagWrap) return;
    pagWrap.querySelectorAll('.apag-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (btn.classList.contains('disabled') || btn.classList.contains('active')) return;
        var pg = parseInt(btn.dataset.page, 10);
        if (!isNaN(pg) && pg > 0) {
          state.page = pg;
          loadProducts();
          var wrap = document.getElementById('adminProductsTableWrap');
          if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  }

  //  Sortable column headers (table view only — no-op if headers absent) 
  function updateSortIcons(col, dir) {
    document.querySelectorAll('.sort-icon').forEach(function (el) {
      el.classList.remove('asc', 'desc');
    });
    if (col) {
      var el = document.getElementById('si-' + col);
      if (el) el.classList.add(dir === 'DESC' ? 'desc' : 'asc');
    }
  }

  // Delegated click on content — works after every AJAX re-render, and only
  // matters when table view's sortable <th> elements are present.
  content.addEventListener('click', function (e) {
    var th = e.target.closest('.sortable-th');
    if (!th) return;
    var col = th.dataset.col;
    if (!col) return;
    if (state.sort === col) {
      state.dir = state.dir === 'ASC' ? 'DESC' : 'ASC';
    } else {
      state.sort = col;
      state.dir  = 'ASC';
    }
    state.page = 1;
    loadProducts();
  });

  //  View switcher 
  if (viewSwitch) {
    viewSwitch.querySelectorAll('.apv-view-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var v = btn.dataset.view;
        if (v === state.view) return;
        state.view = v;
        state.page = 1;
        saveView(v);
        applyViewButtons();
        loadProducts();
      });
    });
  }

  //  Search — min 2 chars 
  var searchTimer = null;
  if (searchEl) {
    searchEl.addEventListener('input', function () {
      var val = searchEl.value.trim();
      if (clearBtn) clearBtn.style.display = val ? 'flex' : 'none';
      clearTimeout(searchTimer);
      if (val.length > 0 && val.length < 2) return;
      searchTimer = setTimeout(function () {
        state.q    = val;
        state.page = 1;
        loadProducts();
      }, 350);
    });
    searchEl.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        var val = searchEl.value.trim();
        if (val.length === 0 || val.length >= 2) {
          clearTimeout(searchTimer);
          state.q    = val;
          state.page = 1;
          loadProducts();
        }
      }
    });
  }
  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      searchEl.value         = '';
      clearBtn.style.display = 'none';
      state.q    = '';
      state.page = 1;
      loadProducts();
      searchEl.focus();
    });
  }

  //  Per-page 
  if (perPageEl) {
    perPageEl.addEventListener('change', function () {
      var allowed = [24, 48, 72, 100];
      var val     = parseInt(perPageEl.value, 10);
      state.per  = allowed.includes(val) ? val : 24;
      state.page = 1;
      loadProducts();
    });
  }

  //  Category tabs 
  if (catTabs) {
    catTabs.querySelectorAll('[data-cat]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        catTabs.querySelectorAll('[data-cat]').forEach(function (b) {
          b.classList.remove('active');
        });
        btn.classList.add('active');
        state.cat    = btn.dataset.cat;
        state.page   = 1;
        state.filter = '';   // clear health filter when switching category
        loadProducts();
      });
    });
  }

  //  Confirm-delete rebind 
  function bindConfirm() {
    document.querySelectorAll('[data-confirm]').forEach(function (btn) {
      if (btn._confirmBound) return;
      btn._confirmBound = true;
      btn.addEventListener('click', function (e) {
        if (!confirm(btn.dataset.confirm)) e.preventDefault();
      });
    });
  }

  //  Initial load 
  applyViewButtons();
  loadProducts();

})();