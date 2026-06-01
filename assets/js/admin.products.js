/**
 * assets/js/admin.products.js — Task 2: Admin Products AJAX Pagination
 * Handles per-page, search debounce, category filter, pagination — all AJAX.
 */
(function () {
  'use strict';

  // ── DOM refs ────────────────────────────────────────────────────────────────
  var tbody      = document.getElementById('adminProductsTbody');
  var pagWrap    = document.getElementById('adminPaginationWrap');
  var countEl    = document.getElementById('adminProductsCount');
  var searchEl   = document.getElementById('adminProductSearch');
  var clearBtn   = document.getElementById('adminSearchClear');
  var perPageEl  = document.getElementById('adminPerPage');
  var catTabs    = document.getElementById('adminCatTabs');
  var loader     = document.getElementById('adminProductsLoader');

  if (!tbody) return; // not on products page

  // ── State ───────────────────────────────────────────────────────────────────
  var state = {
    q:    '',
    cat:  '',
    per:  25,
    page: 1,
  };

  // ── Core fetch ──────────────────────────────────────────────────────────────
  function loadProducts() {
    if (loader) loader.style.display = 'flex';
    if (tbody)  tbody.style.opacity  = '0.4';

    var params = new URLSearchParams({
      page:          'products',
      ajax_products: '1',
      per:           state.per,
      p:             state.page,
    });
    if (state.q)   params.set('q',   state.q);
    if (state.cat) params.set('cat', state.cat);

    fetch('index.php?' + params.toString())
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (tbody) {
          tbody.innerHTML  = data.rows;
          tbody.style.opacity = '1';
          // Re-bind confirm-delete buttons
          bindConfirm();
        }
        if (pagWrap) pagWrap.innerHTML = data.pagination || '';
        if (countEl) {
          var start = data.total === 0 ? 0 : (state.page - 1) * state.per + 1;
          var end   = Math.min(state.page * state.per, data.total);
          countEl.textContent = data.total + ' products' +
            (data.total > 0 ? ' · showing ' + start + '–' + end : '');
        }
        // Re-bind pagination buttons
        bindPagination();
      })
      .catch(function (e) {
        console.error('Admin products AJAX error:', e);
        if (tbody) tbody.style.opacity = '1';
      })
      .finally(function () {
        if (loader) loader.style.display = 'none';
      });
  }

  // ── Pagination button binding ───────────────────────────────────────────────
  function bindPagination() {
    if (!pagWrap) return;
    pagWrap.querySelectorAll('.apag-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (btn.classList.contains('disabled') || btn.classList.contains('active')) return;
        var pg = parseInt(btn.dataset.page, 10);
        if (!isNaN(pg) && pg > 0) {
          state.page = pg;
          loadProducts();
          // Scroll table into view
          var wrap = document.getElementById('adminProductsTableWrap');
          if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  }

  // ── Search ──────────────────────────────────────────────────────────────────
  var searchTimer = null;
  if (searchEl) {
    searchEl.addEventListener('input', function () {
      var val = searchEl.value.trim();
      if (clearBtn) clearBtn.style.display = val ? 'flex' : 'none';
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () {
        state.q    = val;
        state.page = 1;
        loadProducts();
      }, 300);
    });
    searchEl.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(searchTimer);
        state.q    = searchEl.value.trim();
        state.page = 1;
        loadProducts();
      }
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      searchEl.value     = '';
      clearBtn.style.display = 'none';
      state.q    = '';
      state.page = 1;
      loadProducts();
      searchEl.focus();
    });
  }

  // ── Per-page dropdown ────────────────────────────────────────────────────────
  if (perPageEl) {
    perPageEl.addEventListener('change', function () {
      var allowed = [25, 50, 75, 100];
      var val     = parseInt(perPageEl.value, 10);
      state.per  = allowed.includes(val) ? val : 25;
      state.page = 1;
      loadProducts();
    });
  }

  // ── Category tabs ────────────────────────────────────────────────────────────
  if (catTabs) {
    catTabs.querySelectorAll('[data-cat]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        catTabs.querySelectorAll('[data-cat]').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        state.cat  = btn.dataset.cat;
        state.page = 1;
        loadProducts();
      });
    });
  }

  // ── Confirm-delete re-bind (after AJAX re-render) ───────────────────────────
  function bindConfirm() {
    document.querySelectorAll('[data-confirm]').forEach(function (btn) {
      if (btn._confirmBound) return;
      btn._confirmBound = true;
      btn.addEventListener('click', function (e) {
        if (!confirm(btn.dataset.confirm)) e.preventDefault();
      });
    });
  }

  // ── Initial load ─────────────────────────────────────────────────────────────
  loadProducts();

})();