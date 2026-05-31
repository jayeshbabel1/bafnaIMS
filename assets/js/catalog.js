/* ── Catalog AJAX engine ────────────────────────────────────────────────── */
(function () {
  const content = document.getElementById('catalogContent');
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
    const loader = document.getElementById('ajaxLoader');
    if (grid) grid.classList.add('refreshing');
if(loader){loader.style.display = 'flex';}
    try {
      const resp = await fetch(buildUrl(page));
      if (!resp.ok) throw new Error('HTTP ' + resp.status);
      const data = await resp.json();

      content.style.transition = 'opacity .18s';
      content.style.opacity    = '0';
      await new Promise(r => setTimeout(r, 180));

      content.innerHTML     = data.html;
      content.style.opacity = '1';

      // Re-bind shortlist AJAX on new cards (direct binding still needed here)
      bindShortlist();

content.scrollIntoView({ behavior: 'smooth', block: 'start' });

      if (push) {
        const params = new URLSearchParams(window.location.search);
        if (page > 1) params.set('p', page); else params.delete('p');
        window.history.pushState({ catalogPage: page }, '', 'index.php?' + params.toString());
      }

    } catch (e) {
      content.style.opacity = '1';
      console.error('Catalog load error:', e);
    }
finally {

  if(loader){
      loader.style.display = 'none';
  }}
  }

  /* ── Event delegation for pagination (works on both initial and AJAX-loaded buttons) */
 content.addEventListener('click', function (e) {
    const btn = e.target.closest('.pag-btn');
    if (!btn || btn.classList.contains('disabled')) return;
    const pg = parseInt(btn.dataset.page, 10);
    if (!isNaN(pg) && pg > 0) loadPage(pg);
});

  /* ── Handle browser back/forward ─────────────────────────────────────── */
  window.addEventListener('popstate', (e) => {
    const pg = e.state?.catalogPage || 1;
    loadPage(pg, false);
  });

  /* ── Initial shortlist bind ─────────────────────────────────────────── */
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