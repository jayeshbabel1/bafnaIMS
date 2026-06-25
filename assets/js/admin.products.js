/**
 * assets/js/admin.products.js
 * AJAX pagination · sortable columns · 2-char search min · health filter support
 */
(function () {
  'use strict';

  var tbody      = document.getElementById('adminProductsTbody');
  var pagWrap    = document.getElementById('adminPaginationWrap');
  var countEl    = document.getElementById('adminProductsCount');
  var searchEl   = document.getElementById('adminProductSearch');
  var clearBtn   = document.getElementById('adminSearchClear');
  var perPageEl  = document.getElementById('adminPerPage');
  var catTabs    = document.getElementById('adminCatTabs');
  var loader     = document.getElementById('adminProductsLoader');

  if (!tbody) return;

  // Read initial filter from URL (set by dashboard health links)
  var urlParams    = new URLSearchParams(window.location.search);
  var initialFilter = urlParams.get('filter') || '';

  var state = {
    q:      '',
    cat:    '',
    per:    25,
    page:   1,
    sort:   '',        // column key
    dir:    'ASC',
    filter: initialFilter,
  };

  //  Core fetch 
  function loadProducts() {
    if (loader) loader.style.display = 'flex';
    if (tbody)  tbody.style.opacity  = '0.4';

    var params = new URLSearchParams({
      page:          'products',
      ajax_products: '1',
      per:           state.per,
      p:             state.page,
    });
    if (state.q)      params.set('q',      state.q);
    if (state.cat)    params.set('cat',    state.cat);
    if (state.sort)   params.set('sort',   state.sort);
    if (state.dir)    params.set('dir',    state.dir);
    if (state.filter) params.set('filter', state.filter);

    fetch('index.php?' + params.toString())
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (tbody) {
          tbody.innerHTML = data.rows;
          tbody.style.opacity = '1';
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
      })
      .catch(function (e) {
        console.error('Admin products AJAX:', e);
        if (tbody) tbody.style.opacity = '1';
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

  //  Sortable column headers 
  function updateSortIcons(col, dir) {
    document.querySelectorAll('.sort-icon').forEach(function (el) {
      el.classList.remove('asc', 'desc');
    });
    if (col) {
      // Map col name to element id suffix
      var idMap = {
        'name':               'name',
        'quarry_number':      'quarry_number',
        'quantity_available': 'quantity_available',
        'quantity_on_hold':   'quantity_on_hold',
        'in_stock':           'in_stock',
      };
      var elId = 'si-' + (idMap[col] || col);
      var el   = document.getElementById(elId);
      if (el) el.classList.add(dir === 'DESC' ? 'desc' : 'asc');
    }
  }

  document.querySelectorAll('.sortable-th').forEach(function (th) {
    th.addEventListener('click', function () {
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
  });

  //  Search — min 2 chars 
  var searchTimer = null;
  if (searchEl) {
    searchEl.addEventListener('input', function () {
      var val = searchEl.value.trim();
      if (clearBtn) clearBtn.style.display = val ? 'flex' : 'none';
      clearTimeout(searchTimer);
      // Only fire if empty (reset) or at least 2 chars
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
      var allowed = [25, 50, 75, 100];
      var val     = parseInt(perPageEl.value, 10);
      state.per  = allowed.includes(val) ? val : 25;
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
  loadProducts();

})();