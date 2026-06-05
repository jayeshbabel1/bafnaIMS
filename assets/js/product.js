/**
 * assets/js/product.js
 */

/* ── Photo switcher ─────────────────────────────────────────────────── */
function switchPhoto(el) {
  document.querySelectorAll('.detail-thumb').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  const src     = el.dataset.src;
  const hero    = document.getElementById('heroImg');
  const heroSvg = document.getElementById('heroSvg');
  if (src && hero) {
    hero.style.opacity    = '0';
    hero.style.transition = 'opacity .2s';
    setTimeout(() => {
      hero.src           = src;
      hero.style.opacity = '1';
    }, 160);
    if (heroSvg) heroSvg.style.display = 'none';
    hero.style.display = '';
  }
  if (window.ZoomManager) window.ZoomManager.reset('hero');
}

function switchPalette(el) {
  document.querySelectorAll('.detail-thumb').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
}

/* ── Lightbox ───────────────────────────────────────────────────────── */
function openLightbox(src) {
  const lb  = document.getElementById('lightbox');
  const img = document.getElementById('lightboxImg');
  const dl  = document.getElementById('lightboxDl');
  if (!lb || !img) return;
  if (window.ZoomManager) window.ZoomManager.reset('lightbox');
  img.src       = src;
  dl.href       = src;
  dl.download   = src.split('/').pop();
  lb.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeLightbox() {
  const lb = document.getElementById('lightbox');
  if (lb) lb.classList.remove('open');
  document.body.style.overflow = '';
  if (window.ZoomManager) window.ZoomManager.reset('lightbox');
}

function lightboxBgClick(e) {
  if (e.target === document.getElementById('lightbox')) closeLightbox();
}

/* ── Share modal ─────────────────────────────────────────────────────── */
function openShareModal() {
  const m = document.getElementById('shareModal');
  if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; }
}

function closeShareModal() {
  const m = document.getElementById('shareModal');
  if (m) { m.classList.remove('open'); document.body.style.overflow = ''; }
}

/* ── Copy link ───────────────────────────────────────────────────────── */
function copyLink(url) {
  const lbl  = document.getElementById('copyLinkLabel');
  const done = () => {
    if (lbl) { lbl.textContent = 'Copied!'; setTimeout(() => { lbl.textContent = 'Copy Link'; }, 2000); }
  };
  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(done).catch(() => { fallbackCopy(url); done(); });
  } else { fallbackCopy(url); done(); }
}

function fallbackCopy(url) {
  const ta = document.createElement('textarea');
  ta.value = url; document.body.appendChild(ta); ta.select();
  document.execCommand('copy'); document.body.removeChild(ta);
}


/* ── Keyboard ────────────────────────────────────────────────────────── */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeLightbox(); closeShareModal(); }
});