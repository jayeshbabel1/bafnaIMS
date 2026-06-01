/**
 * assets/js/product.js — updated for Task 3 zoom integration
 */

/* ── Photo switcher ─────────────────────────────────────────────────────── */
function switchPhoto(el) {
  document.querySelectorAll('.detail-thumb').forEach(function (t) { t.classList.remove('active'); });
  el.classList.add('active');
  var src     = el.dataset.src;
  var hero    = document.getElementById('heroImg');
  var heroSvg = document.getElementById('heroSvg');
  if (src && hero) {
    hero.style.opacity    = '0';
    hero.style.transition = 'opacity .2s';
    setTimeout(function () {
      hero.src           = src;
      hero.style.opacity = '1';
    }, 160);
    if (heroSvg) heroSvg.style.display = 'none';
    hero.style.display = '';
  }
  // Reset zoom when switching photo
  if (window.ZoomManager) window.ZoomManager.reset('hero');
}

function switchPalette(el) {
  document.querySelectorAll('.detail-thumb').forEach(function (t) { t.classList.remove('active'); });
  el.classList.add('active');
}

/* ── Lightbox ───────────────────────────────────────────────────────────── */
function openLightbox(src) {
  var lb  = document.getElementById('lightbox');
  var img = document.getElementById('lightboxImg');
  var dl  = document.getElementById('lightboxDl');
  if (!lb || !img) return;

  // Reset zoom state before showing new image
  if (window.ZoomManager) window.ZoomManager.reset('lightbox');

  img.src    = src;
  dl.href    = src;
  dl.download = src.split('/').pop();
  lb.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeLightbox() {
  var lb = document.getElementById('lightbox');
  if (lb) lb.classList.remove('open');
  document.body.style.overflow = '';
  if (window.ZoomManager) window.ZoomManager.reset('lightbox');
}

function lightboxBgClick(e) {
  // Close only when clicking the dark backdrop, not the image or controls
  if (e.target === document.getElementById('lightbox')) closeLightbox();
}

/* ── Share modal ────────────────────────────────────────────────────────── */
function openShareModal() {
  var m = document.getElementById('shareModal');
  if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeShareModal() {
  var m = document.getElementById('shareModal');
  if (m) { m.classList.remove('open'); document.body.style.overflow = ''; }
}

/* ── Copy link ──────────────────────────────────────────────────────────── */
function copyLink(url) {
  var lbl = document.getElementById('copyLinkLabel');
  var done = function () {
    if (lbl) { lbl.textContent = 'Copied!'; setTimeout(function () { lbl.textContent = 'Copy Link'; }, 2000); }
  };
  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(done).catch(function () { fallbackCopy(url); done(); });
  } else {
    fallbackCopy(url); done();
  }
}
function fallbackCopy(url) {
  var ta = document.createElement('textarea');
  ta.value = url; document.body.appendChild(ta); ta.select();
  document.execCommand('copy'); document.body.removeChild(ta);
}

/* ── Keyboard close ─────────────────────────────────────────────────────── */
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') { closeLightbox(); closeShareModal(); }
});