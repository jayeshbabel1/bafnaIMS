/**
 * assets/js/product.js
 */

/*  Photo switcher  */
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

/* ── Lightbox / Gallery navigation  */
let _galleryIndex = 0;

function openLightbox(index) {
  const lb     = document.getElementById('lightbox');
  const img    = document.getElementById('lightboxImg');
  const images = window.GALLERY_IMAGES || [];
  if (!lb || !img || !images.length) return;

  _galleryIndex = typeof index === 'number' ? index : 0;
  showLightboxImage();

  lb.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function showLightboxImage() {
  const img    = document.getElementById('lightboxImg');
  const dl     = document.getElementById('lightboxDl');
  const images = window.GALLERY_IMAGES || [];
  if (!img || !images.length) return;

  if (_galleryIndex < 0) _galleryIndex = images.length - 1;
  if (_galleryIndex >= images.length) _galleryIndex = 0;

  const src = images[_galleryIndex];
  if (window.ZoomManager) window.ZoomManager.reset('lightbox');
  img.src = src;
  if (dl) {
    dl.href     = src;
    dl.download = src.split('/').pop();
  }

  const counter = document.getElementById('lightboxCounter');
  if (counter) counter.textContent = images.length > 1 ? (_galleryIndex + 1) + ' / ' + images.length : '';

  const multiple = images.length > 1;
  const prevBtn  = document.getElementById('lightboxPrev');
  const nextBtn  = document.getElementById('lightboxNext');
  if (prevBtn) prevBtn.style.display = multiple ? '' : 'none';
  if (nextBtn) nextBtn.style.display = multiple ? '' : 'none';
}

function lightboxNext() { _galleryIndex++; showLightboxImage(); }
function lightboxPrev() { _galleryIndex--; showLightboxImage(); }

function closeLightbox() {
  const lb = document.getElementById('lightbox');
  if (lb) lb.classList.remove('open');
  document.body.style.overflow = '';
  if (window.ZoomManager) window.ZoomManager.reset('lightbox');
}

function lightboxBgClick(e) {
  if (e.target === document.getElementById('lightbox')) closeLightbox();
}

/*  Swipe support (mobile) 
   Skips swipe-to-navigate while the image is zoomed in, so pinch/pan via
   ZoomManager still works as expected. */
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    const lb = document.getElementById('lightbox');
    if (!lb) return;
    let startX = 0, startY = 0, touching = false;

    lb.addEventListener('touchstart', function (e) {
      if (e.touches.length !== 1) return;
      const img = document.getElementById('lightboxImg');
      if (img) {
        const m = (img.style.transform || '').match(/scale\(([\d.]+)\)/);
        if (m && parseFloat(m[1]) > 1.02) { touching = false; return; }
      }
      startX = e.touches[0].clientX;
      startY = e.touches[0].clientY;
      touching = true;
    }, { passive: true });

    lb.addEventListener('touchend', function (e) {
      if (!touching) return;
      touching = false;
      const t = e.changedTouches && e.changedTouches[0];
      if (!t) return;
      const dx = t.clientX - startX;
      const dy = t.clientY - startY;
      if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) {
        if (dx < 0) lightboxNext(); else lightboxPrev();
      }
    }, { passive: true });
  });
})();

/* ── Share modal  */
function openShareModal() {
  const m = document.getElementById('shareModal');
  if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; }
}

function closeShareModal() {
  const m = document.getElementById('shareModal');
  if (m) { m.classList.remove('open'); document.body.style.overflow = ''; }
}

/* ── Copy link  */
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


/* ── Keyboard  */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeLightbox(); closeShareModal(); }
  const lb = document.getElementById('lightbox');
  if (lb && lb.classList.contains('open')) {
    if (e.key === 'ArrowRight') lightboxNext();
    if (e.key === 'ArrowLeft')  lightboxPrev();
  }
});