/**
 * assets/js/pagination.js
 * Shared AJAX pagination renderer + click binder.
 * Renders Previous / numbered / Next controls from {current, totalPages}
 * and delegates the actual page-change to the caller's onPage callback.
 *
 * Usage:
 *   const pager = initPagination({
 *     wrapEl: document.getElementById('paginationWrap'),
 *     btnClass: 'pag-btn',       // 'pag-btn' (user panel) or 'apag-btn' (admin panel)
 *     onPage: function (page) { loadPage(page); }
 *   });
 *   pager.render(currentPage, totalPages);
 */
(function (window) {
  'use strict';

  function initPagination(cfg) {
    var wrapEl       = cfg.wrapEl;
    var btnClass     = cfg.btnClass || 'pag-btn';
    var ellipsisCls  = cfg.ellipsisClass || (btnClass === 'apag-btn' ? 'apag-ellipsis' : 'pag-ellipsis');
    var onPage       = cfg.onPage || function () {};
    var range        = cfg.range || 2;
    var prevText     = cfg.prevText || '‹ Previous';
    var nextText     = cfg.nextText || 'Next ›';

    if (!wrapEl) return { render: function () {} };

    function build(current, total) {
      if (total <= 1) return '';
      var s = Math.max(1, current - range);
      var e = Math.min(total, current + range);
      var html = '';

      html += '<button type="button" class="' + btnClass + ' ' + btnClass + '--prev' +
              (current <= 1 ? ' disabled' : '') + '" data-page="' + (current - 1) + '">' + prevText + '</button>';

      if (s > 1) {
        html += '<button type="button" class="' + btnClass + '" data-page="1">1</button>';
        if (s > 2) html += '<span class="' + ellipsisCls + '">…</span>';
      }
      for (var i = s; i <= e; i++) {
        html += '<button type="button" class="' + btnClass + (i === current ? ' active' : '') +
                '" data-page="' + i + '">' + i + '</button>';
      }
      if (e < total) {
        if (e < total - 1) html += '<span class="' + ellipsisCls + '">…</span>';
        html += '<button type="button" class="' + btnClass + '" data-page="' + total + '">' + total + '</button>';
      }

      html += '<button type="button" class="' + btnClass + ' ' + btnClass + '--next' +
              (current >= total ? ' disabled' : '') + '" data-page="' + (current + 1) + '">' + nextText + '</button>';

      return html;
    }

    function render(current, total) {
      if (!wrapEl) return;
      wrapEl.innerHTML = build(current, total);
      wrapEl.querySelectorAll('.' + btnClass).forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (btn.classList.contains('disabled') || btn.classList.contains('active')) return;
          var pg = parseInt(btn.dataset.page, 10);
          if (!isNaN(pg) && pg > 0) onPage(pg);
        });
      });
    }
     function setWrapEl(el) {
     wrapEl = el;   }
   return { render: render, setWrapEl: setWrapEl };
  }

  window.initPagination = initPagination;
})(window);