/**
 * assets/js/zoom.js — Task 3: Image Zoom & Pan
 * Handles hero image zoom and lightbox zoom independently.
 * Vanilla JS only, no external dependencies.
 */
(function () {
  'use strict';

  var ZOOM_MIN   = 1;
  var ZOOM_MAX   = 4;
  var ZOOM_STEP  = 0.5;
  var TRANSITION = 'transform 0.2s ease';

  // Registry: zoomId → state
  var registry = {};

  function getState(id) {
    if (!registry[id]) {
      registry[id] = { scale: 1, x: 0, y: 0, dragging: false, startX: 0, startY: 0, lastX: 0, lastY: 0 };
    }
    return registry[id];
  }

  function applyTransform(img, state, animated) {
    img.style.transition = animated ? TRANSITION : 'none';
    img.style.transform  = 'translate(' + state.x + 'px, ' + state.y + 'px) scale(' + state.scale + ')';
    img.style.cursor     = state.scale > 1 ? 'grab' : 'default';
  }

  function clampPosition(state, container, img) {
    if (state.scale <= 1) { state.x = 0; state.y = 0; return; }
    var cw  = container.clientWidth;
    var ch  = container.clientHeight;
    var iw  = img.naturalWidth  || img.clientWidth;
    var ih  = img.naturalHeight || img.clientHeight;
    var maxX = Math.max(0, (iw  * state.scale - cw)  / 2);
    var maxY = Math.max(0, (ih  * state.scale - ch)  / 2);
    state.x = Math.min(maxX, Math.max(-maxX, state.x));
    state.y = Math.min(maxY, Math.max(-maxY, state.y));
  }

  function zoomIn(id) {
    var img   = document.querySelector('[data-zoom-id="' + id + '"]');
    var cont  = img && img.closest('.zoom-container');
    if (!img || !cont) return;
    var st    = getState(id);
    st.scale  = Math.min(ZOOM_MAX, parseFloat((st.scale + ZOOM_STEP).toFixed(2)));
    clampPosition(st, cont, img);
    applyTransform(img, st, true);
    updateResetBtn(id, st);
  }

  function zoomOut(id) {
    var img  = document.querySelector('[data-zoom-id="' + id + '"]');
    var cont = img && img.closest('.zoom-container');
    if (!img || !cont) return;
    var st   = getState(id);
    st.scale = Math.max(ZOOM_MIN, parseFloat((st.scale - ZOOM_STEP).toFixed(2)));
    clampPosition(st, cont, img);
    applyTransform(img, st, true);
    updateResetBtn(id, st);
  }

  function zoomReset(id) {
    var img = document.querySelector('[data-zoom-id="' + id + '"]');
    if (!img) return;
    var st  = getState(id);
    st.scale = 1; st.x = 0; st.y = 0;
    applyTransform(img, st, true);
    updateResetBtn(id, st);
  }

  function updateResetBtn(id, st) {
    var btns = document.querySelectorAll('[data-target="' + id + '"] .zoom-btn--reset');
    btns.forEach(function (b) {
      b.classList.toggle('zoom-btn--active', st.scale !== 1);
    });
  }

  // ── Control button binding ─────────────────────────────────────────────────
  function bindControls(wrap) {
    var id = wrap.dataset.target;
    wrap.querySelectorAll('.zoom-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var action = btn.dataset.action;
        if (action === 'in')    zoomIn(id);
        if (action === 'out')   zoomOut(id);
        if (action === 'reset') zoomReset(id);
      });
    });
  }

  // ── Mouse wheel zoom ───────────────────────────────────────────────────────
  function bindWheel(container, id) {
    container.addEventListener('wheel', function (e) {
      e.preventDefault();
      if (e.deltaY < 0) zoomIn(id); else zoomOut(id);
    }, { passive: false });
  }

  // ── Mouse drag / pan ───────────────────────────────────────────────────────
  function bindDrag(img, id) {
    var st = getState(id);

    img.addEventListener('mousedown', function (e) {
      if (st.scale <= 1) return;
      e.preventDefault();
      st.dragging = true;
      st.startX   = e.clientX - st.lastX;
      st.startY   = e.clientY - st.lastY;
      img.style.cursor = 'grabbing';
    });

    document.addEventListener('mousemove', function (e) {
      if (!st.dragging) return;
      st.x = e.clientX - st.startX;
      st.y = e.clientY - st.startY;
      var cont = img.closest('.zoom-container');
      if (cont) clampPosition(st, cont, img);
      st.lastX = st.x;
      st.lastY = st.y;
      applyTransform(img, st, false);
    });

    document.addEventListener('mouseup', function () {
      if (!st.dragging) return;
      st.dragging = false;
      img.style.cursor = st.scale > 1 ? 'grab' : 'default';
    });
  }

  // ── Touch drag / pinch-to-zoom ─────────────────────────────────────────────
  function bindTouch(img, id) {
    var st = getState(id);
    var lastDist = 0;

    img.addEventListener('touchstart', function (e) {
      if (e.touches.length === 2) {
        lastDist = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY
        );
      } else if (e.touches.length === 1 && st.scale > 1) {
        st.dragging = true;
        st.startX   = e.touches[0].clientX - st.lastX;
        st.startY   = e.touches[0].clientY - st.lastY;
      }
    }, { passive: true });

    img.addEventListener('touchmove', function (e) {
      if (e.touches.length === 2) {
        e.preventDefault();
        var dist = Math.hypot(
          e.touches[0].clientX - e.touches[1].clientX,
          e.touches[0].clientY - e.touches[1].clientY
        );
        var delta = dist - lastDist;
        lastDist  = dist;
        var cont  = img.closest('.zoom-container');
        if (delta > 2)       { st.scale = Math.min(ZOOM_MAX, parseFloat((st.scale + 0.1).toFixed(2))); }
        else if (delta < -2) { st.scale = Math.max(ZOOM_MIN, parseFloat((st.scale - 0.1).toFixed(2))); }
        if (cont) clampPosition(st, cont, img);
        applyTransform(img, st, false);
        updateResetBtn(id, st);
      } else if (e.touches.length === 1 && st.dragging) {
        e.preventDefault();
        st.x = e.touches[0].clientX - st.startX;
        st.y = e.touches[0].clientY - st.startY;
        var cont = img.closest('.zoom-container');
        if (cont) clampPosition(st, cont, img);
        st.lastX = st.x;
        st.lastY = st.y;
        applyTransform(img, st, false);
      }
    }, { passive: false });

    img.addEventListener('touchend', function () {
      st.dragging = false;
    });
  }

  // ── Init a zoom target ─────────────────────────────────────────────────────
  function initZoomTarget(img) {
    var id   = img.dataset.zoomId;
    var cont = img.closest('.zoom-container');
    if (!id || !cont) return;
    cont.style.overflow = 'hidden';
    bindDrag(img, id);
    bindTouch(img, id);
    bindWheel(cont, id);
  }

  // ── Init all control panels ────────────────────────────────────────────────
  function initAll() {
    document.querySelectorAll('.zoom-target').forEach(initZoomTarget);
    document.querySelectorAll('[data-target]').forEach(function (wrap) {
      if (wrap.classList.contains('zoom-controls')) bindControls(wrap);
    });
  }

  // ── Public API (used by product.js lightbox opener) ──────────────────────
  window.ZoomManager = {
    reset: zoomReset,
    init:  initAll,
    initTarget: initZoomTarget,
  };

  // ── Boot ──────────────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', initAll);

})();