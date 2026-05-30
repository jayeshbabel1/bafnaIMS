/* ── Photo switcher ─────────────────────────────────────────────────────── */
function switchPhoto(el) {
  document.querySelectorAll('.detail-thumb').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  const src = el.dataset.src;
  const hero = document.getElementById('heroImg');
  const heroSvg = document.getElementById('heroSvg');
  if (src && hero) {
    hero.style.opacity = '0';
    hero.style.transition = 'opacity .2s';
    setTimeout(() => {
      hero.src = src;
      hero.style.opacity = '1';
    }, 160);
    if (heroSvg) heroSvg.style.display = 'none';
    hero.style.display = '';
  }
}

function switchPalette(el) {
  document.querySelectorAll('.detail-thumb').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
}

/* ── Lightbox ───────────────────────────────────────────────────────────── */
function openLightbox(src) {
  const lb  = document.getElementById('lightbox');
  const img = document.getElementById('lightboxImg');
  const dl  = document.getElementById('lightboxDl');
  img.src = src;
  dl.href = src;
  dl.download = src.split('/').pop();
  lb.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeLightbox() {
  document.getElementById('lightbox')?.classList.remove('open');
  document.body.style.overflow = '';
}

/* ── Share modal ────────────────────────────────────────────────────────── */
function openShareModal() {
  document.getElementById('shareModal')?.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeShareModal() {
  document.getElementById('shareModal')?.classList.remove('open');
  document.body.style.overflow = '';
}

/* ── Copy link ──────────────────────────────────────────────────────────── */
function copyLink(url) {
  const lbl = document.getElementById('copyLinkLabel');
  navigator.clipboard.writeText(url).then(() => {
    if (lbl) { lbl.textContent = 'Copied!'; setTimeout(() => lbl.textContent = 'Copy Link', 2000); }
  }).catch(() => {
    const ta = document.createElement('textarea');
    ta.value = url; document.body.appendChild(ta); ta.select();
    document.execCommand('copy'); document.body.removeChild(ta);
    if (lbl) { lbl.textContent = 'Copied!'; setTimeout(() => lbl.textContent = 'Copy Link', 2000); }
  });
}

/* ── Keyboard close ─────────────────────────────────────────────────────── */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeLightbox(); closeShareModal(); }
});
