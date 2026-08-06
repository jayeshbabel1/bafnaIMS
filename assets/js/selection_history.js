/**
 * assets/js/selection_history.js
 * Shared Client Selection History modal — used by BOTH the User panel
 * (pages/client_selections.php) and Admin panel
 * (admin/views/admin_client_selections.php).
 *
 * History is NEVER prefetched. It is only requested from the server when
 * the user opens the modal (window.openSelectionHistory), and the
 * "thinking" loader is held visible for a MINIMUM of 4 seconds regardless
 * of how fast the server actually responds.
 */
(function (window) {
  'use strict';

  var MIN_LOADER_MS = 4000;
  var _modalEl = null;
  var _state = { clientId: 0, endpoint: '', page: 1 };

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML;
  }

  function buildModal() {
    if (_modalEl) return _modalEl;
    var wrap = document.createElement('div');
    wrap.id = 'selHistModal';
    wrap.className = 'selhist-overlay';
    wrap.innerHTML =
      '<div class="selhist-card">' +
        '<div class="selhist-head">' +
          '<p class="selhist-title">Selection History</p>' +
          '<button type="button" class="selhist-close" aria-label="Close">&times;</button>' +
        '</div>' +
        '<div class="selhist-body">' +

          '<div class="selhist-loader" id="selHistLoader">' +
            '<div class="selhist-scene">' +
          '<svg viewBox="0 0 200 200" class="selhist-svg" aria-hidden="true">' +
  '<ellipse cx="100" cy="178" rx="60" ry="8" class="selhist-shadow"/>' +
  '<rect x="55" y="120" width="70" height="10" rx="3" class="selhist-chairseat"/>' +
  '<rect x="58" y="128" width="8" height="42" class="selhist-chairleg"/>' +
  '<rect x="114" y="128" width="8" height="42" class="selhist-chairleg"/>' +
  '<rect x="108" y="70" width="10" height="55" rx="4" class="selhist-chairback"/>' +
  '<g class="selhist-body-g">' +
    '<rect x="72" y="95" width="34" height="38" rx="12" class="selhist-torso"/>' +
    '<rect x="70" y="128" width="16" height="34" rx="6" class="selhist-leg"/>' +
    '<rect x="98" y="128" width="16" height="34" rx="6" class="selhist-leg"/>' +
    '<g class="selhist-arm-still">' +
      '<rect x="66" y="102" width="12" height="30" rx="6" class="selhist-arm"/>' +
    '</g>' +
    '<g class="selhist-arm-tap">' +
      '<rect x="98" y="98" width="12" height="26" rx="6" class="selhist-arm"/>' +
      '<circle cx="104" cy="90" r="7" class="selhist-finger"/>' +
    '</g>' +
    '<circle cx="89" cy="80" r="20" class="selhist-head"/>' +
    '<circle cx="82" cy="78" r="2.4" class="selhist-eye"/>' +
    '<circle cx="94" cy="78" r="2.4" class="selhist-eye"/>' +
    '<path d="M87 82 q2 5 0 8" class="selhist-nose"/>' +
  '</g>' +
'</svg>' +
              '<div class="selhist-thought"><span></span><span></span><span></span></div>' +
            '</div>' +
            '<p class="selhist-loader-text">Fetching history…</p>' +
          '</div>' +

          '<div class="selhist-list" id="selHistList" style="display:none;"></div>' +
          '<div class="selhist-empty" id="selHistEmpty" style="display:none;"><p>No history yet for this client.</p></div>' +
          '<div class="selhist-error" id="selHistError" style="display:none;"></div>' +

        '</div>' +
        '<div class="selhist-foot" id="selHistFoot" style="display:none;"></div>' +
      '</div>';
    document.body.appendChild(wrap);
    _modalEl = wrap;

    wrap.querySelector('.selhist-close').addEventListener('click', closeSelectionHistory);
    wrap.addEventListener('click', function (e) { if (e.target === wrap) closeSelectionHistory(); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && wrap.classList.contains('open')) closeSelectionHistory();
    });

    return wrap;
  }

  function actionMeta(action) {
    switch (action) {
      case 'added':   return { cls: 'selhist-badge--added',   label: 'Added' };
      case 'edited':  return { cls: 'selhist-badge--edited',  label: 'Edited' };
      case 'deleted': return { cls: 'selhist-badge--deleted', label: 'Deleted' };
      default:        return { cls: 'selhist-badge--default', label: action };
    }
  }

  function renderRows(rows) {
    return rows.map(function (h) {
      var m = actionMeta(h.action);
      return '<div class="selhist-item">' +
        '<span class="selhist-badge ' + m.cls + '">' + esc(m.label) + '</span>' +
        '<div class="selhist-item-body"><p class="selhist-item-msg">' + esc(h.message) + '</p></div>' +
      '</div>';
    }).join('');
  }

  function renderPagination(current, pages) {
    if (pages <= 1) return '';
    var range = 2, s = Math.max(1, current - range), e = Math.min(pages, current + range);
    var html = '<div class="selhist-pag">';
    html += '<button type="button" class="selhist-pag-btn" data-page="' + (current - 1) + '"' + (current <= 1 ? ' disabled' : '') + '>&lsaquo;</button>';
    if (s > 1) { html += '<button type="button" class="selhist-pag-btn" data-page="1">1</button>'; if (s > 2) html += '<span class="selhist-pag-ellipsis">…</span>'; }
    for (var i = s; i <= e; i++) {
      html += '<button type="button" class="selhist-pag-btn' + (i === current ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
    }
    if (e < pages) { if (e < pages - 1) html += '<span class="selhist-pag-ellipsis">…</span>'; html += '<button type="button" class="selhist-pag-btn" data-page="' + pages + '">' + pages + '</button>'; }
    html += '<button type="button" class="selhist-pag-btn" data-page="' + (current + 1) + '"' + (current >= pages ? ' disabled' : '') + '>&rsaquo;</button>';
    html += '</div>';
    return html;
  }

  function bindPagination() {
    var foot = document.getElementById('selHistFoot');
    if (!foot) return;
    foot.querySelectorAll('.selhist-pag-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (btn.disabled || btn.classList.contains('active')) return;
        var pg = parseInt(btn.dataset.page, 10);
        if (!isNaN(pg) && pg > 0) loadPage(pg);
      });
    });
  }

  function showLoader() {
    document.getElementById('selHistLoader').style.display = 'flex';
    document.getElementById('selHistList').style.display = 'none';
    document.getElementById('selHistEmpty').style.display = 'none';
    document.getElementById('selHistError').style.display = 'none';
    document.getElementById('selHistFoot').style.display = 'none';
    document.getElementById('selHistFoot').innerHTML = '';
  }

  function loadPage(page) {
    _state.page = page;
    showLoader();

    var params = new URLSearchParams({ ajax_history: '1', client_id: _state.clientId, p: page });
    var url = _state.endpoint + '&' + params.toString();

    var fetchPromise = fetch(url).then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
    // Loader is held for a minimum of 4 seconds no matter how fast the
    // server responds — Promise.all waits for the SLOWER of the two.
    var timerPromise = new Promise(function (resolve) { setTimeout(resolve, MIN_LOADER_MS); });

    Promise.all([fetchPromise, timerPromise])
      .then(function (results) {
        var data = results[0];
        document.getElementById('selHistLoader').style.display = 'none';
        if (!data || data.success === false) {
          document.getElementById('selHistError').style.display = 'block';
          document.getElementById('selHistError').textContent = (data && data.error) || 'Could not load history.';
          return;
        }
        if (!data.rows || !data.rows.length) {
          document.getElementById('selHistEmpty').style.display = 'block';
          return;
        }
        var list = document.getElementById('selHistList');
        list.innerHTML = renderRows(data.rows);
        list.style.display = 'block';

        var foot = document.getElementById('selHistFoot');
        var pagHtml = renderPagination(data.current, data.pages);
        foot.innerHTML = (pagHtml || '<span></span>') +
          '<p class="selhist-count">' + data.total + ' record' + (data.total !== 1 ? 's' : '') + '</p>';
        foot.style.display = 'flex';
        bindPagination();
      })
      .catch(function (err) {
        document.getElementById('selHistLoader').style.display = 'none';
        document.getElementById('selHistError').style.display = 'block';
        document.getElementById('selHistError').textContent = 'Request failed: ' + err.message;
      });
  }

  function openSelectionHistory(clientId, endpoint) {
    var modal = buildModal();
    _state.clientId = clientId;
    _state.endpoint = endpoint;
    _state.page = 1;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
    loadPage(1);
  }

  function closeSelectionHistory() {
    if (!_modalEl) return;
    _modalEl.classList.remove('open');
    document.body.style.overflow = '';
  }

  window.openSelectionHistory  = openSelectionHistory;
  window.closeSelectionHistory = closeSelectionHistory;
})(window);