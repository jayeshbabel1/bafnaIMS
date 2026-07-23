<?php
/**
 * admin/views/product_view_settings.php
 * Settings → Product Views — configure Admin Panel & User Panel product
 * listing views (Grid / List / Table): default view + field visibility/order.
 */
requireAdminPermission('settings.product_views');
require_once BASE_PATH . '/includes/product_views.php';
ensureProductViewTables();

// ── AJAX: save one panel's full config (default view + all 3 view field sets) ─
if (!empty($_POST) && ($_POST['action'] ?? '') === 'save_product_view_panel') {
    header('Content-Type: application/json');
    csrfVerify(true);
    $panel = $_POST['panel'] ?? '';
    if (!in_array($panel, PV_PANELS, true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid panel.']); exit;
    }
    $defaultView = $_POST['default_view'] ?? 'grid';
    setDefaultView($panel, $defaultView);

    if ($panel === 'user' && !empty($_POST['catalog_theme'])) {
        $themeRes = setCatalogTheme($_POST['catalog_theme']);
        if (!$themeRes['success']) { echo json_encode($themeRes); exit; }
    }

    $errors = [];
    foreach (PV_VIEWS as $view) {
        $raw = $_POST['fields_' . $view] ?? '[]';
        $fields = json_decode($raw, true);
        if (!is_array($fields)) { $errors[] = "Bad payload for $view"; continue; }
        $res = saveViewFieldConfig($panel, $view, $fields);
        if (!$res['success']) $errors[] = $view . ': ' . ($res['error'] ?? 'failed');
    }
    echo json_encode(['success' => empty($errors), 'error' => implode('; ', $errors)]);
    exit;
}

$adminTitle = 'Product View Settings';
include __DIR__ . '/../_layout_top.php';

$adminBundle  = getPanelViewBundle('admin');
$userBundle   = getPanelViewBundle('user');
$fieldLabels  = pvAllFields();
$themes       = pvCatalogThemes();
$currentTheme = getCatalogTheme();
?>
<style>
.pvs-tabs{display:flex;gap:0;border-bottom:2px solid var(--admin-table-border,var(--border));margin-bottom:20px;}
.pvs-tab{padding:10px 20px;font-size:13px;font-weight:600;border-bottom:2px solid transparent;margin-bottom:-2px;color:var(--admin-text3,var(--text3));cursor:pointer;background:none;border-top:none;border-left:none;border-right:none;font-family:inherit;}
.pvs-tab.active{border-bottom-color:var(--admin-accent,var(--accent));color:var(--admin-accent,var(--accent));}
.pvs-panel{display:none;}
.pvs-panel.active{display:block;}

.pvs-subtabs{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;}
.pvs-subtab{padding:7px 16px;border-radius:20px;font-size:12px;font-weight:600;background:var(--admin-surface,var(--surface));border:1.5px solid var(--admin-table-border,var(--border));color:var(--admin-text3,var(--text3));cursor:pointer;font-family:inherit;}
.pvs-subtab.active{background:var(--admin-accent,var(--accent));border-color:var(--admin-accent,var(--accent));color:#fff;}
.pvs-subpanel{display:none;}
.pvs-subpanel.active{display:block;}

.pvs-default-row{display:flex;align-items:center;gap:14px;margin-bottom:18px;flex-wrap:wrap;background:var(--admin-accent-light,var(--accent-light));border-radius:10px;padding:12px 16px;}
.pvs-default-row label{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;cursor:pointer;}

.pvs-field-list{display:flex;flex-direction:column;gap:6px;max-width:520px;}
.pvs-field-item{display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--admin-card-bg,var(--surface));border:1.5px solid var(--admin-table-border,var(--border));border-radius:8px;cursor:grab;}
.pvs-field-item.dragging{opacity:.4;}
.pvs-field-item.drag-over{border-color:var(--admin-accent,var(--accent));}
.pvs-drag-handle{color:var(--admin-text3,var(--text3));flex-shrink:0;}
.pvs-field-item input[type=checkbox]{width:16px;height:16px;accent-color:var(--admin-accent,var(--accent));flex-shrink:0;}
.pvs-field-label{font-size:13px;font-weight:500;color:var(--admin-text,var(--text));flex:1;}
.pvs-field-key{font-size:10px;color:var(--admin-text3,var(--text3));font-family:monospace;}

.pvs-theme-row{margin-bottom:20px;padding-bottom:18px;border-bottom:1px solid var(--admin-table-border,var(--border));}
.pvs-theme-grid{display:grid;grid-template-columns:1fr;gap:10px;max-width:640px;}
@media (min-width:560px){.pvs-theme-grid{grid-template-columns:repeat(2,1fr);}}
@media (min-width:1024px){.pvs-theme-grid{grid-template-columns:repeat(4,1fr);}}
.pvs-theme-card{display:flex;flex-direction:column;gap:6px;padding:12px;border:1.5px solid var(--admin-table-border,var(--border));border-radius:10px;cursor:pointer;background:var(--admin-card-bg,var(--surface));position:relative;}
.pvs-theme-card.selected{border-color:var(--admin-accent,var(--accent));background:var(--admin-accent-light,var(--accent-light));}
.pvs-theme-card input[type=radio]{position:absolute;top:10px;right:10px;width:15px;height:15px;accent-color:var(--admin-accent,var(--accent));}
.pvs-theme-swatch{display:block;height:40px;border-radius:6px;}
.pvs-theme-swatch--classic{background:linear-gradient(135deg,#fff,#eee);border:1px solid var(--border);}
.pvs-theme-swatch--minimal{background:#fff;}
.pvs-theme-swatch--bold_gold{background:linear-gradient(135deg,#c9a84c,#8a6d1f);}
.pvs-theme-swatch--compact{background:repeating-linear-gradient(0deg,#e8e8e8,#e8e8e8 4px,#fff 4px,#fff 8px);}
.pvs-theme-name{font-size:13px;font-weight:700;color:var(--admin-text,var(--text));}
.pvs-theme-desc{font-size:11px;color:var(--admin-text3,var(--text3));line-height:1.4;}
</style>

<?php
// Render one panel's full editor (both top-level tabs share this)
function pvsRenderPanel(string $panel, array $bundle, array $fieldLabels, array $themes = [], string $currentTheme = ''): void {
    $panelLabel = $panel === 'admin' ? 'Admin' : 'User';
?>
<form class="pvs-form" data-panel="<?= h($panel) ?>">
  <?= csrfField() ?>

  <?php if ($panel === 'user'): ?>
  <div class="pvs-theme-row">
    <p style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--admin-text2,var(--text2));margin-bottom:10px;">Catalog Theme</p>
    <div class="pvs-theme-grid">
      <?php foreach ($themes as $key => $t): ?>
      <label class="pvs-theme-card <?= $currentTheme === $key ? 'selected' : '' ?>">
        <input type="radio" name="catalog_theme" value="<?= h($key) ?>" <?= $currentTheme === $key ? 'checked' : '' ?>/>
        <span class="pvs-theme-swatch pvs-theme-swatch--<?= h($key) ?>"></span>
        <span class="pvs-theme-name"><?= h($t['label']) ?></span>
        <span class="pvs-theme-desc"><?= h($t['desc']) ?></span>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="pvs-default-row">
    <span style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--admin-text2,var(--text2));">Default View:</span>
    <?php foreach (PV_VIEWS as $v): ?>
    <label>
      <input type="radio" name="default_view_<?= h($panel) ?>" value="<?= h($v) ?>"
             <?= $bundle['default_view'] === $v ? 'checked' : '' ?>/>
      <?= ucfirst($v) ?>
    </label>
    <?php endforeach; ?>
  </div>

  <div class="pvs-subtabs">
    <?php foreach (PV_VIEWS as $i => $v): ?>
    <button type="button" class="pvs-subtab <?= $i===0?'active':'' ?>"
            onclick="pvsSwitchSub('<?= h($panel) ?>','<?= h($v) ?>')"
            data-panel="<?= h($panel) ?>" data-view="<?= h($v) ?>"><?= ucfirst($v) ?> View</button>
    <?php endforeach; ?>
  </div>

  <?php foreach (PV_VIEWS as $i => $v): ?>
  <div class="pvs-subpanel <?= $i===0?'active':'' ?>" id="pvs-<?= h($panel) ?>-<?= h($v) ?>">
    <p style="font-size:12px;color:var(--admin-text3,var(--text3));margin-bottom:12px;">
      Drag to reorder. Checkbox toggles visibility in the <?= h($panelLabel) ?> Panel's <?= h($v) ?> view.
    </p>
    <div class="pvs-field-list" data-panel="<?= h($panel) ?>" data-view="<?= h($v) ?>">
      <?php foreach ($bundle['views'][$v] as $f): ?>
      <div class="pvs-field-item" draggable="true" data-key="<?= h($f['key']) ?>">
        <span class="pvs-drag-handle"><?= icon('grid', 14) ?></span>
        <input type="checkbox" <?= $f['visible'] ? 'checked' : '' ?>/>
        <span class="pvs-field-label"><?= h($f['label']) ?></span>
        <span class="pvs-field-key"><?= h($f['key']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <input type="hidden" class="pvs-fields-json" data-view="<?= h($v) ?>"/>
  </div>
  <?php endforeach; ?>

  <div style="margin-top:18px;display:flex;align-items:center;gap:12px;">
    <button type="button" class="btn-admin-primary" onclick="pvsSave('<?= h($panel) ?>')">
      <?= icon('check', 15) ?> Save <?= h($panelLabel) ?> View Settings
    </button>
    <span class="pvs-save-status" data-panel="<?= h($panel) ?>" style="font-size:12px;"></span>
  </div>
</form>
<?php
}
?>

<div class="pvs-tabs">
  <button class="pvs-tab active" onclick="pvsSwitchTab('admin')">Admin Product View</button>
  <button class="pvs-tab" onclick="pvsSwitchTab('user')">User Product View</button>
</div>

<div class="pvs-panel active" id="pvs-panel-admin">
  <?php pvsRenderPanel('admin', $adminBundle, $fieldLabels); ?>
</div>
<div class="pvs-panel" id="pvs-panel-user">
  <?php pvsRenderPanel('user', $userBundle, $fieldLabels, $themes, $currentTheme); ?>
</div>

<script>
function pvsSwitchTab(panel) {
  document.querySelectorAll('.pvs-tab').forEach(function(t){ t.classList.toggle('active', t.getAttribute('onclick').includes("'"+panel+"'")); });
  document.querySelectorAll('.pvs-panel').forEach(function(p){ p.classList.toggle('active', p.id === 'pvs-panel-'+panel); });
}
function pvsSwitchSub(panel, view) {
  document.querySelectorAll('.pvs-subtab[data-panel="'+panel+'"]').forEach(function(t){
    t.classList.toggle('active', t.dataset.view === view);
  });
  document.querySelectorAll('#pvs-panel-'+panel+' .pvs-subpanel').forEach(function(p){
    p.classList.toggle('active', p.id === 'pvs-'+panel+'-'+view);
  });
}

// ── Drag & drop reordering (vanilla HTML5 DnD, no library) ─────────────────
(function () {
  document.querySelectorAll('.pvs-field-list').forEach(function (list) {
    var dragEl = null;
    list.addEventListener('dragstart', function (e) {
      var item = e.target.closest('.pvs-field-item');
      if (!item) return;
      dragEl = item;
      item.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
    });
    list.addEventListener('dragend', function (e) {
      var item = e.target.closest('.pvs-field-item');
      if (item) item.classList.remove('dragging');
      list.querySelectorAll('.drag-over').forEach(function (el) { el.classList.remove('drag-over'); });
    });
    list.addEventListener('dragover', function (e) {
      e.preventDefault();
      var target = e.target.closest('.pvs-field-item');
      if (!target || target === dragEl) return;
      var rect = target.getBoundingClientRect();
      var before = (e.clientY - rect.top) < rect.height / 2;
      list.querySelectorAll('.drag-over').forEach(function (el) { el.classList.remove('drag-over'); });
      target.classList.add('drag-over');
      if (before) list.insertBefore(dragEl, target);
      else list.insertBefore(dragEl, target.nextSibling);
    });
  });
})();

// ── Collect current DOM order + checkbox state into JSON, then POST ────────
function pvsCollectFields(panel, view) {
  var list = document.querySelector('.pvs-field-list[data-panel="'+panel+'"][data-view="'+view+'"]');
  var out = [];
  list.querySelectorAll('.pvs-field-item').forEach(function (item) {
    out.push({ key: item.dataset.key, visible: item.querySelector('input[type=checkbox]').checked ? 1 : 0 });
  });
  return out;
}

// Theme card click → select its radio + toggle 'selected' class
document.querySelectorAll('.pvs-theme-card').forEach(function (card) {
  card.addEventListener('click', function () {
    var radio = card.querySelector('input[type=radio]');
    radio.checked = true;
    card.parentElement.querySelectorAll('.pvs-theme-card').forEach(function (c) { c.classList.remove('selected'); });
    card.classList.add('selected');
  });
});

function pvsSave(panel) {
  var form = document.querySelector('.pvs-form[data-panel="'+panel+'"]');
  var statusEl = document.querySelector('.pvs-save-status[data-panel="'+panel+'"]');
  var defaultView = form.querySelector('input[name="default_view_'+panel+'"]:checked').value;
  var csrf = form.querySelector('input[name="csrf_token"]').value;

  var body = new URLSearchParams();
  body.set('action', 'save_product_view_panel');
  body.set('panel', panel);
  body.set('default_view', defaultView);
  body.set('csrf_token', csrf);
  if (panel === 'user') {
    var themeInput = form.querySelector('input[name="catalog_theme"]:checked');
    if (themeInput) body.set('catalog_theme', themeInput.value);
  }
  ['grid','list','table'].forEach(function (v) {
    body.set('fields_' + v, JSON.stringify(pvsCollectFields(panel, v)));
  });

  statusEl.textContent = 'Saving…';
  statusEl.style.color = 'var(--admin-text3,var(--text3))';

  fetch('index.php?page=product_view_settings', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (d.success) {
        statusEl.textContent = 'Saved ✓';
        statusEl.style.color = 'var(--success,#3D8B6E)';
      } else {
        statusEl.textContent = 'Error: ' + (d.error || 'save failed');
        statusEl.style.color = 'var(--danger,#E84040)';
      }
      setTimeout(function () { statusEl.textContent = ''; }, 3000);
    })
    .catch(function (e) {
      statusEl.textContent = 'Request failed: ' + e.message;
      statusEl.style.color = 'var(--danger,#E84040)';
    });
}
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>