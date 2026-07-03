/**
 * assets/js/room_visualizer.js
 */
(function () {
  'use strict';

  var tabs        = document.querySelectorAll('.rv-room-tab');
  var sceneGrid   = document.getElementById('rvSceneGrid');
  var sceneItems  = document.querySelectorAll('.rv-scene-item');
  var genBtn      = document.getElementById('rvGenerateBtn');
  var resultWrap  = document.getElementById('rvResultWrap');
  var resultImg   = document.getElementById('rvResultImg');
  var loader      = document.getElementById('rvLoader');
  var regenBtn    = document.getElementById('rvRegenerateBtn');
  var dlBtn       = document.getElementById('rvDownloadBtn');

  if (!genBtn) return;

  var selectedTemplateId = null;

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      tabs.forEach(function (t) { t.classList.remove('active'); });
      tab.classList.add('active');
      var type = tab.dataset.type;
      sceneItems.forEach(function (item) {
        item.style.display = item.dataset.type === type ? '' : 'none';
        item.classList.remove('selected');
      });
      selectedTemplateId = null;
      genBtn.disabled = true;
      genBtn.textContent = 'Select a scene to generate';
    });
  });

  sceneItems.forEach(function (item) {
    item.addEventListener('click', function () {
      sceneItems.forEach(function (i) { i.classList.remove('selected'); });
      item.classList.add('selected');
      selectedTemplateId = item.dataset.id;
      genBtn.disabled = false;
      genBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>&nbsp; Generate Preview';
    });
  });

  function generate() {
    if (!selectedTemplateId) return;
    resultWrap.style.display = 'block';
    loader.style.display = 'flex';
    resultImg.style.opacity = '0.3';
    genBtn.disabled = true;
    regenBtn.disabled = true;

    var body = new URLSearchParams({
      action: 'generate_room_preview',
      product_id: window.RV_PRODUCT_ID,
      template_id: selectedTemplateId,
	  csrf_token: window.CSRF_TOKEN, 
    });

    fetch('index.php', { method: 'POST', body: body })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.success) throw new Error(d.error || 'Generation failed.');
        resultImg.src = d.url + '?t=' + Date.now();
        dlBtn.href = d.url;
        dlBtn.download = 'room-preview-' + Date.now() + '.jpg';
        resultWrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
      })
      .catch(function (e) {
        alert(e.message || 'Something went wrong generating the preview.');
      })
      .finally(function () {
        loader.style.display = 'none';
        resultImg.style.opacity = '1';
        genBtn.disabled = false;
        regenBtn.disabled = false;
      });
  }

  genBtn.addEventListener('click', generate);
  regenBtn.addEventListener('click', generate);

  // History delete
  document.querySelectorAll('.rv-history-delete').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!confirm('Delete this render?')) return;
      var body = new URLSearchParams({ action: 'delete_room_visualization', id: btn.dataset.id, csrf_token: window.CSRF_TOKEN, });
      fetch('index.php', { method: 'POST', body: body }).then(function () {
        btn.closest('.rv-history-item').remove();
      });
    });
  });
})();