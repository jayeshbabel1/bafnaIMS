<?php
/**
 * admin/views/translations.php
 * Multi-language content manager — Products, Categories, UI Strings, FAQ.
 * Tabs = entity type, sub-tabs = language (hi/gu/mr — English is the base,
 * not editable here).
 */
ini_set('display_errors', 1); error_reporting(E_ALL);
// ── AJAX: save one entity_type + lang batch ─────────────────────────────────
if (!empty($_POST) && ($_POST['action'] ?? '') === 'save_translations_batch') {
    header('Content-Type: application/json');
    requireAdminPermissionJson('translations.manage');
    csrfVerify(true);

    $entityType = $_POST['entity_type'] ?? '';
    $lang       = $_POST['lang'] ?? '';
    $rowsJson   = $_POST['rows'] ?? '[]';
    $rows       = json_decode($rowsJson, true);

    if (!in_array($entityType, ['product','category','ui_string','faq'], true) || !is_array($rows)) {
        echo json_encode(['success' => false, 'error' => 'Invalid payload.']); exit;
    }

    $result = saveTranslations($entityType, $lang, $rows);
    echo json_encode($result);
    exit;
}

$adminTitle = 'Translations';
requireAdminPermission('translations.manage');
include __DIR__ . '/../_layout_top.php';

$langs = array_filter(SUPPORTED_LANGS, fn($l) => $l !== 'en'); // hi, gu, mr only

// ── Data loaders per tab ─────────────────────────────────────────────────────
$db = getDB();
$products = $db->query("SELECT id, name, description, category, subcategory, color_subcategory, finish, origin FROM products ORDER BY name ASC")->fetchAll();

require_once BASE_PATH . '/includes/categories.php';
$categoryEntities = [];
foreach (getCategoryNames() as $c) $categoryEntities[] = ['id' => 'cat_' . $c, 'label' => $c, 'group' => 'Stone Type'];
foreach (COLOR_SUBCATEGORIES as $c) $categoryEntities[] = ['id' => 'color_' . $c, 'label' => $c, 'group' => 'Color'];

// UI strings — key => [English fallback, section]
$uiStrings = [
    'nav_catalog'      => ['Catalog', 'Navigation'],
    'nav_shortlist'     => ['Shortlist', 'Navigation'],
    'nav_clients'       => ['Clients', 'Navigation'],
    'nav_updates'       => ['Updates', 'Navigation'],
    'nav_support'       => ['Support', 'Navigation'],
    'nav_profile'       => ['Profile', 'Navigation'],
    'btn_browse_catalog'=> ['Browse Catalog', 'Buttons'],
    'btn_add_client'    => ['Add Client', 'Buttons'],
    'btn_save_changes'  => ['Save Changes', 'Buttons'],
    'btn_sign_in'       => ['Sign In', 'Buttons'],
    'btn_sign_out'      => ['Sign Out', 'Buttons'],
    'empty_no_products' => ['No products found', 'Empty States'],
    'empty_no_clients'  => ['No clients yet', 'Empty States'],
    'empty_no_saved'    => ['Nothing saved yet', 'Empty States'],
    'label_available_qty'=> ['Available Qty', 'Product Labels'],
    'label_thickness'  => ['Thickness', 'Product Labels'],
    'label_useable_size'=> ['Useable Size', 'Product Labels'],
    'label_italian_size'=> ['Italian Size', 'Product Labels'],
    'label_in_stock'   => ['In Stock', 'Product Labels'],
    'label_out_of_stock'=> ['Out of Stock', 'Product Labels'],
];

// FAQ — mirrors pages/support.php $faqs array, indexed 0..n
$faqs = [
    ['How do I save a product?',        'Tap the heart icon on any product card in the catalog to add it to your Shortlist for quick access later.'],
    ['How do I contact about a product?','Use the Share button on any product page to send the details via WhatsApp or email to coordinate directly.'],
    ['Can I save multiple products?',   'Yes — there is no limit. Your entire Shortlist is available under the Shortlist tab in the navigation.'],
    ['How long do notifications last?', 'Notifications are automatically removed after 20 days to keep your feed clean and relevant.'],
    ['How do I reset my password?',     'On the login page, tap "Forgot password?" and follow the instructions sent to your registered email address.'],
    ['How do I update my profile?',     'Go to your Profile page — the edit form is always visible so you can update your details at any time.'],
];

$existing = []; // [entityType][lang] => [entity_id => [field_key => value]]
foreach (['product','category','ui_string','faq'] as $et) {
    foreach ($langs as $l) $existing[$et][$l] = getTranslationsFor($et, $l);
}
?>
<style>
.tr-tabs{display:flex;gap:0;border-bottom:2px solid var(--admin-table-border,var(--border));margin-bottom:20px;overflow-x:auto;}
.tr-tab{padding:10px 20px;font-size:13px;font-weight:600;border-bottom:2px solid transparent;margin-bottom:-2px;color:var(--admin-text3,var(--text3));cursor:pointer;background:none;border-top:none;border-left:none;border-right:none;font-family:inherit;white-space:nowrap;}
.tr-tab.active{border-bottom-color:var(--admin-accent,var(--accent));color:var(--admin-accent,var(--accent));}
.tr-panel{display:none;}
.tr-panel.active{display:block;}
.tr-lang-tabs{display:flex;gap:8px;margin-bottom:16px;}
.tr-lang-tab{padding:7px 16px;border-radius:20px;font-size:12px;font-weight:600;background:var(--admin-surface,var(--surface));border:1.5px solid var(--admin-table-border,var(--border));color:var(--admin-text3,var(--text3));cursor:pointer;font-family:inherit;}
.tr-lang-tab.active{background:var(--admin-accent,var(--accent));border-color:var(--admin-accent,var(--accent));color:#fff;}
.tr-lang-panel{display:none;}
.tr-lang-panel.active{display:block;}
.tr-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:12px 0;border-bottom:1px solid var(--admin-table-border,var(--border));align-items:start;}
.tr-row-en{font-size:13px;color:var(--admin-text2,var(--text2));padding-top:9px;}
.tr-row-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--admin-text3,var(--text3));margin-bottom:4px;display:block;}
.tr-group-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--admin-text2,var(--text2));margin:18px 0 8px;padding-bottom:6px;border-bottom:1px solid var(--admin-table-border,var(--border));}
.tr-group-title:first-child{margin-top:0;}
.tr-search{margin-bottom:14px;max-width:340px;}
.tr-item-hidden{display:none !important;}
.tr-save-bar{position:sticky;bottom:0;background:var(--admin-bg,var(--bg));padding:14px 0;border-top:1px solid var(--admin-table-border,var(--border));margin-top:16px;display:flex;align-items:center;gap:12px;}
</style>

<div class="tr-tabs">
  <button class="tr-tab active" onclick="trSwitchTab('product')">Products</button>
  <button class="tr-tab" onclick="trSwitchTab('category')">Categories</button>
  <button class="tr-tab" onclick="trSwitchTab('ui_string')">UI Strings</button>
  <button class="tr-tab" onclick="trSwitchTab('faq')">FAQ</button>
</div>

<?php
// ── Renders one entity_type's full tab: lang sub-tabs + field rows ─────────
function trRenderPanel(string $entityType, array $langs, array $existing): void {
?>
<div class="tr-panel <?= $entityType==='product'?'active':'' ?>" id="tr-panel-<?= h($entityType) ?>">
  <div class="tr-lang-tabs">
    <?php foreach ($langs as $i => $l): ?>
    <button type="button" class="tr-lang-tab <?= $i===0?'active':'' ?>"
            onclick="trSwitchLang('<?= h($entityType) ?>','<?= h($l) ?>')"
            data-entity="<?= h($entityType) ?>" data-lang="<?= h($l) ?>"><?= h(LANG_LABELS[$l]) ?></button>
    <?php endforeach; ?>
  </div>

  <?php if ($entityType === 'product'): global $products; ?>
  <input type="text" class="admin-input tr-search" placeholder="Search product name…" oninput="trFilterRows(this,'tr-panel-product')"/>
  <?php foreach ($langs as $i => $l): ?>
  <div class="tr-lang-panel <?= $i===0?'active':'' ?>" id="tr-<?= h($entityType) ?>-<?= h($l) ?>">
    <form class="tr-form" data-entity="<?= h($entityType) ?>" data-lang="<?= h($l) ?>">
      <?= csrfField() ?>
      <?php foreach ($products as $p):
        $tid = (string)$p['id'];
        foreach (['name'=>'Name','description'=>'Description','category'=>'Category','subcategory'=>'Subcategory','color_subcategory'=>'Color','finish'=>'Finish','origin'=>'Origin'] as $fk => $flabel):
          $en = trim((string)($p[$fk] ?? ''));
          if ($en === '') continue;
          $val = $existing[$entityType][$l][$tid][$fk] ?? '';
      ?>
      <div class="tr-row" data-search="<?= h(mb_strtolower($p['name'])) ?>">
        <div class="tr-row-en"><span class="tr-row-label"><?= h($p['name']) ?> — <?= h($flabel) ?></span><?= h($en) ?></div>
        <div>
          <textarea class="admin-input tr-field" rows="<?= $fk==='description'?3:1 ?>"
                    data-entity-id="<?= h($tid) ?>" data-field-key="<?= h($fk) ?>"
                    placeholder="Leave blank to use English"><?= h($val) ?></textarea>
        </div>
      </div>
      <?php endforeach; endforeach; ?>
    </form>
  </div>
  <?php endforeach; ?>

  <?php elseif ($entityType === 'category'): global $categoryEntities; ?>
  <?php foreach ($langs as $i => $l): ?>
  <div class="tr-lang-panel <?= $i===0?'active':'' ?>" id="tr-<?= h($entityType) ?>-<?= h($l) ?>">
    <form class="tr-form" data-entity="<?= h($entityType) ?>" data-lang="<?= h($l) ?>">
      <?= csrfField() ?>
      <?php $lastGroup = null; foreach ($categoryEntities as $c):
        if ($c['group'] !== $lastGroup) { echo '<p class="tr-group-title">'.h($c['group']).'</p>'; $lastGroup = $c['group']; }
        $val = $existing[$entityType][$l][$c['id']]['label'] ?? '';
      ?>
      <div class="tr-row">
        <div class="tr-row-en"><?= h($c['label']) ?></div>
        <div>
          <input type="text" class="admin-input tr-field"
                 data-entity-id="<?= h($c['id']) ?>" data-field-key="label"
                 value="<?= h($val) ?>" placeholder="Leave blank to use English"/>
        </div>
      </div>
      <?php endforeach; ?>
    </form>
  </div>
  <?php endforeach; ?>

  <?php elseif ($entityType === 'ui_string'): global $uiStrings; ?>
  <?php foreach ($langs as $i => $l): ?>
  <div class="tr-lang-panel <?= $i===0?'active':'' ?>" id="tr-<?= h($entityType) ?>-<?= h($l) ?>">
    <form class="tr-form" data-entity="<?= h($entityType) ?>" data-lang="<?= h($l) ?>">
      <?= csrfField() ?>
      <?php $lastSection = null; foreach ($uiStrings as $key => [$en, $section]):
        if ($section !== $lastSection) { echo '<p class="tr-group-title">'.h($section).'</p>'; $lastSection = $section; }
        $val = $existing[$entityType][$l][$key]['value'] ?? '';
      ?>
      <div class="tr-row">
        <div class="tr-row-en"><span class="tr-row-label"><?= h($key) ?></span><?= h($en) ?></div>
        <div>
          <input type="text" class="admin-input tr-field"
                 data-entity-id="<?= h($key) ?>" data-field-key="value"
                 value="<?= h($val) ?>" placeholder="Leave blank to use English"/>
        </div>
      </div>
      <?php endforeach; ?>
    </form>
  </div>
  <?php endforeach; ?>

  <?php elseif ($entityType === 'faq'): global $faqs; ?>
  <?php foreach ($langs as $i => $l): ?>
  <div class="tr-lang-panel <?= $i===0?'active':'' ?>" id="tr-<?= h($entityType) ?>-<?= h($l) ?>">
    <form class="tr-form" data-entity="<?= h($entityType) ?>" data-lang="<?= h($l) ?>">
      <?= csrfField() ?>
      <?php foreach ($faqs as $idx => [$q, $a]):
        $qVal = $existing[$entityType][$l][(string)$idx]['question'] ?? '';
        $aVal = $existing[$entityType][$l][(string)$idx]['answer']   ?? '';
      ?>
      <p class="tr-group-title">FAQ #<?= $idx + 1 ?></p>
      <div class="tr-row">
        <div class="tr-row-en"><span class="tr-row-label">Question</span><?= h($q) ?></div>
        <div><input type="text" class="admin-input tr-field" data-entity-id="<?= $idx ?>" data-field-key="question" value="<?= h($qVal) ?>" placeholder="Leave blank to use English"/></div>
      </div>
      <div class="tr-row">
        <div class="tr-row-en"><span class="tr-row-label">Answer</span><?= h($a) ?></div>
        <div><textarea class="admin-input tr-field" rows="2" data-entity-id="<?= $idx ?>" data-field-key="answer" placeholder="Leave blank to use English"><?= h($aVal) ?></textarea></div>
      </div>
      <?php endforeach; ?>
    </form>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <div class="tr-save-bar">
    <button type="button" class="btn-admin-primary" onclick="trSaveCurrent('<?= h($entityType) ?>')">
      <?= icon('check', 15) ?> Save Changes
    </button>
    <span class="tr-status" data-entity="<?= h($entityType) ?>" style="font-size:12px;"></span>
  </div>
</div>
<?php
}
foreach (['product','category','ui_string','faq'] as $et) {
    trRenderPanel($et, $langs, $existing);
}
?>

<script>
var trActiveLang = { product: '<?= h($langs[array_key_first($langs)]) ?>', category: '<?= h($langs[array_key_first($langs)]) ?>', ui_string: '<?= h($langs[array_key_first($langs)]) ?>', faq: '<?= h($langs[array_key_first($langs)]) ?>' };

function trSwitchTab(entity) {
  document.querySelectorAll('.tr-tab').forEach(function(t){ t.classList.toggle('active', t.getAttribute('onclick').includes("'"+entity+"'")); });
  document.querySelectorAll('.tr-panel').forEach(function(p){ p.classList.toggle('active', p.id === 'tr-panel-'+entity); });
}
function trSwitchLang(entity, lang) {
  trActiveLang[entity] = lang;
  document.querySelectorAll('.tr-lang-tab[data-entity="'+entity+'"]').forEach(function(t){
    t.classList.toggle('active', t.dataset.lang === lang);
  });
  document.querySelectorAll('#tr-panel-'+entity+' .tr-lang-panel').forEach(function(p){
    p.classList.toggle('active', p.id === 'tr-'+entity+'-'+lang);
  });
}
function trFilterRows(input, panelId) {
  var q = input.value.trim().toLowerCase();
  document.querySelectorAll('#'+panelId+' .tr-row[data-search]').forEach(function(row) {
    row.classList.toggle('tr-item-hidden', q !== '' && row.dataset.search.indexOf(q) === -1);
  });
}
function trSaveCurrent(entity) {
  var lang = trActiveLang[entity];
  var form = document.querySelector('.tr-form[data-entity="'+entity+'"][data-lang="'+lang+'"]');
  var statusEl = document.querySelector('.tr-status[data-entity="'+entity+'"]');
  var csrf = form.querySelector('input[name="csrf_token"]').value;

  var rows = [];
  form.querySelectorAll('.tr-field').forEach(function(f) {
    rows.push({ entity_id: f.dataset.entityId, field_key: f.dataset.fieldKey, value: f.value });
  });

  var body = new URLSearchParams();
  body.set('action', 'save_translations_batch');
  body.set('entity_type', entity);
  body.set('lang', lang);
  body.set('rows', JSON.stringify(rows));
  body.set('csrf_token', csrf);

  statusEl.textContent = 'Saving…';
  statusEl.style.color = 'var(--admin-text3,var(--text3))';

  fetch('index.php?page=translations', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function(r){ return r.json(); })
    .then(function(d) {
      if (d.success) {
        statusEl.textContent = 'Saved ✓ (' + rows.length + ' fields)';
        statusEl.style.color = 'var(--success,#3D8B6E)';
      } else {
        statusEl.textContent = 'Error: ' + (d.error || 'save failed');
        statusEl.style.color = 'var(--danger,#E84040)';
      }
      setTimeout(function(){ statusEl.textContent = ''; }, 3500);
    })
    .catch(function(e) {
      statusEl.textContent = 'Request failed: ' + e.message;
      statusEl.style.color = 'var(--danger,#E84040)';
    });
}
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>