/* ── Catalog AJAX engine ────────────────────────────────────────────────── */
(function () {
  const content   = document.getElementById('catalogContent');
  if (!content) return;

  /* ── Build query string from current state ─────────────────────────── */
  function buildUrl(page) {
    const params = new URLSearchParams();
    params.set('page', 'catalog');
    params.set('ajax', '1');
    const cat   = content.dataset.cat;
    const color = content.dataset.color;
    const q     = content.dataset.q;
    if (cat)   params.set('cat',   cat);
    if (color) params.set('color', color);
    if (q)     params.set('q',     q);
    if (page > 1) params.set('p', page);
    return 'index.php?' + params.toString();
  }

  /* ── Fetch and render a page ────────────────────────────────────────── */
  async function loadPage(page, push = true) {
    const grid = document.getElementById('productsGrid');
    if (grid) { grid.classList.add('refreshing'); }

    try {
      const resp = await fetch(buildUrl(page));
      const data = await resp.json();

      // Fade out → swap → fade in
      content.style.opacity = '0';
      content.style.transition = 'opacity .18s';
      await new Promise(r => setTimeout(r, 180));
      content.innerHTML = data.html;
      content.style.opacity = '1';

      // Re-bind shortlist AJAX on new cards
      bindShortlist();
      // Re-bind pagination buttons
      bindPagination();

      // Scroll to top of grid area smoothly
      content.scrollIntoView({ behavior: 'smooth', block: 'start' });

      // Update browser URL without reload
      if (push) {
        const params = new URLSearchParams(window.location.search);
        if (page > 1) params.set('p', page); else params.delete('p');
        window.history.pushState({ page }, '', 'index.php?' + params.toString());
      }

    } catch (e) {
      content.style.opacity = '1';
      console.error('Catalog load error:', e);
    }
  }

  /* ── Bind pagination buttons ────────────────────────────────────────── */
  function bindPagination() {
    document.querySelectorAll('#paginationWrap .pag-btn:not(.disabled)').forEach(btn => {
      btn.addEventListener('click', () => {
        const pg = parseInt(btn.dataset.page);
        if (!isNaN(pg) && pg > 0) loadPage(pg);
      });
    });
  }

  /* ── Handle browser back/forward ─────────────────────────────────────── */
  window.addEventListener('popstate', (e) => {
    const pg = e.state?.page || 1;
    loadPage(pg, false);
  });

  /* ── Initial bind ───────────────────────────────────────────────────── */
  bindPagination();
  bindShortlist();

  /* ── Search debounce ────────────────────────────────────────────────── */
  const searchInput = document.getElementById('searchInput');
  const searchForm  = document.getElementById('searchForm');
  if (searchInput && searchForm) {
    let timer;
    searchInput.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => searchForm.submit(), 550);
    });
  }
})();

/* ── Shortlist AJAX (progressive enhancement) ───────────────────────────── */
function bindShortlist() {
  document.querySelectorAll('.shortlist-form').forEach(form => {
    if (form._bound) return;
    form._bound = true;
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const btn = form.querySelector('.shortlist-btn');
      if (btn) { btn.style.transform = 'scale(.8)'; setTimeout(() => btn.style.transform = '', 200); }
      try {
        await fetch('index.php', { method: 'POST', body: new FormData(form) });
        const isSaved = btn.classList.toggle('saved');
        btn.title = isSaved ? 'Remove from shortlist' : 'Add to shortlist';
        btn.innerHTML = isSaved
          ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" fill="#C0392B" stroke="#C0392B"/></svg>'
          : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
      } catch {
        form.submit();
      }
    });
  });
}
