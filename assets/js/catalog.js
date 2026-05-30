// ── Shortlist AJAX (progressive enhancement) ────────────────────────────────
document.querySelectorAll('.shortlist-form').forEach(form => {
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = form.querySelector('.shortlist-btn');
    btn.style.transform = 'scale(.85)';
    setTimeout(() => btn.style.transform = '', 200);
    try {
      const resp = await fetch('index.php', { method:'POST', body: new FormData(form) });
      // Toggle heart visually
      const isSaved = btn.classList.toggle('saved');
      btn.title = isSaved ? 'Remove from shortlist' : 'Add to shortlist';
      btn.innerHTML = isSaved
        ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" fill="#E84040" stroke="#E84040"/></svg>'
        : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
      // Update input for correct next submit
      const actionInput = form.querySelector('[name=action]');
      actionInput.value = 'toggle_shortlist';
    } catch(err) {
      form.submit(); // fallback
    }
  });
});

// ── Search debounce ──────────────────────────────────────────────────────────
const searchInput = document.getElementById('searchInput');
const searchForm  = document.getElementById('searchForm');
if (searchInput && searchForm) {
  let timer;
  searchInput.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => searchForm.submit(), 600);
  });
}
