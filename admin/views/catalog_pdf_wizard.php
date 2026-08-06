<?php
/**
 * admin/views/catalog_pdf_wizard.php — Fire 2: Step 1 (Select) + Step 2 (Order)
 * Steps 3-5 (layout/customize/generate) added in later fires.
 */
requireAdminPermission('catalog.create');
require_once BASE_PATH . '/includes/categories.php';
require_once BASE_PATH . '/includes/catalog_pdf_engine.php';

// ── AJAX: product search for step 1 ─────────────────────────────────────────
if (!empty($_GET['ajax_pdf_products'])) {
    requireAdminPermissionJson('catalog.create');
    $q      = trim($_GET['q'] ?? '');
    $cat    = trim($_GET['cat'] ?? '');
    $db     = getDB();
    $where  = "WHERE 1=1"; $params = [];
    if ($q !== '')   { $where .= " AND (p.name LIKE ? OR p.quarry_number LIKE ?)"; $params[]="%{$q}%"; $params[]="%{$q}%"; }
    if ($cat !== '') { $where .= " AND p.category=?"; $params[] = $cat; }
 $st = $db->prepare("SELECT p.id,p.name,p.quarry_number,p.category,p.color_subcategory,p.thickness,
                        p.quantity_available,p.palette,
                        EXISTS(SELECT 1 FROM product_photos pp WHERE pp.product_id=p.id) AS has_photo,
                        (SELECT filename FROM product_photos WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS primary_photo
                        FROM products p $where
                        ORDER BY  has_photo DESC,p.created_at DESC, p.id DESC LIMIT 200");
    $st->execute($params);
    header('Content-Type: application/json');
    echo json_encode(['products' => $st->fetchAll()]);
    exit;
}


// ── AJAX: generate PDF (Step 5) 
if (!empty($_POST) && ($_POST['action'] ?? '') === 'generate_catalog_pdf_test') { // action name kept for JS compat
    header('Content-Type: application/json');
    requireAdminPermissionJson('catalog.create');
    csrfVerify(true);
    $catalogId = (int)($_POST['catalog_id'] ?? 0);
    echo json_encode(generateCatalogPdf($catalogId));
    exit;
}

// ── AJAX: load a template's config ──────────────────────────────────────────
if (!empty($_GET['ajax_load_template'])) {
    header('Content-Type: application/json');
    requireAdminPermissionJson('catalog.template.manage');
    $tid = (int)($_GET['template_id'] ?? 0);
    $st = getDB()->prepare("SELECT config_json FROM catalog_templates WHERE id=?");
    $st->execute([$tid]);
    $row = $st->fetch();
    if (!$row) { echo json_encode(['success' => false, 'error' => 'Template not found.']); exit; }
    $config = json_decode($row['config_json'], true) ?: [];
    echo json_encode(['success' => true, 'config' => $config]);
    exit;
}

// ── AJAX: save current config as a new template ─────────────────────────────
if (!empty($_POST) && ($_POST['action'] ?? '') === 'save_catalog_template') {
    header('Content-Type: application/json');
    requireAdminPermissionJson('catalog.template.manage');
    csrfVerify(true);
    $name   = trim($_POST['name'] ?? '');
    $config = json_decode($_POST['config'] ?? '{}', true);
    if ($name === '' || !is_array($config)) {
        echo json_encode(['success' => false, 'error' => 'Invalid name or config.']);
        exit;
    }
    $merged = array_replace_recursive(catalogPdfDefaultConfig(), $config);
    getDB()->prepare("INSERT INTO catalog_templates (name, config_json, created_by, created_at) VALUES (?,?,?,?)")
           ->execute([$name, json_encode($merged), $_SESSION['admin_id'] ?? null, time()]);
    echo json_encode(['success' => true]);
    exit;
}

// ── AJAX: save draft (steps 1+2 state) ──────────────────────────────────────

// ── AJAX: save draft (steps 1+2 state) ──────────────────────────────────────
if (!empty($_POST) && ($_POST['action'] ?? '') === 'save_catalog_draft') {
    header('Content-Type: application/json');
    requireAdminPermissionJson('catalog.create');
    csrfVerify(true);

    $catalogId  = (int)($_POST['catalog_id'] ?? 0);
    $name       = trim($_POST['name'] ?? 'Untitled Catalog');
    $productIds = json_decode($_POST['product_ids'] ?? '[]', true);
    if (!is_array($productIds)) $productIds = [];
    $productIds = array_values(array_unique(array_map('intval', $productIds)));

  $layout = trim($_POST['layout'] ?? '');
    $fields = json_decode($_POST['fields'] ?? '[]', true);
    if (!is_array($fields)) $fields = [];
    $customize = json_decode($_POST['customize'] ?? '[]', true);
    if (!is_array($customize)) $customize = [];

    if ($catalogId) {
    $row = getCatalog($catalogId);
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'This catalog no longer exists. Refresh and start again.']);
        exit;
    }
    $config = $row['config'];
    if ($layout !== '') $config['layout'] = $layout;
    if (!empty($fields)) $config['fields'] = $fields;
    if (!empty($customize)) $config = array_replace_recursive($config, $customize);
    $upd = getDB()->prepare("UPDATE catalogs SET name=?, product_ids_json=?, config_json=?, updated_at=? WHERE id=?");
    $upd->execute([$name, json_encode($productIds), json_encode($config), time(), $catalogId]);
    echo json_encode(['success' => $upd->rowCount() >= 0, 'id' => $catalogId]);
} else {
        $config = getCatalogPdfSettingsDefaults();
        if ($layout !== '') $config['layout'] = $layout;
        if (!empty($fields)) $config['fields'] = $fields;
        $res = createCatalogDraft([
            'name'         => $name,
            'admin_id'     => $_SESSION['admin_id'] ?? null,
            'product_ids'  => $productIds,
            'config'       => $config,
        ]);
        echo json_encode($res);
    }
    exit;
}

$adminTitle = 'Generate Catalog PDF';
include __DIR__ . '/../_layout_top.php';

$catalogId = (int)($_GET['id'] ?? 0);
$existing  = $catalogId ? getCatalog($catalogId) : null;
$categories = getCategoryNames();
?>
<style>
.cpw-steps{display:flex;gap:0;margin-bottom:22px;border-bottom:2px solid var(--admin-table-border,var(--border));overflow-x:inherit;}
.cpw-step{padding:10px 18px;font-size:13px;font-weight:600;color:var(--admin-text3,var(--text3));border-bottom:2px solid transparent;margin-bottom:-2px;white-space:nowrap;display:flex;align-items:center;gap:6px;}
.cpw-step.active{color:var(--admin-accent,var(--accent));border-bottom-color:var(--admin-accent,var(--accent));}
.cpw-step.done{color:var(--success,#3D8B6E);}
.cpw-step-num{width:20px;height:20px;border-radius:50%;background:var(--admin-surface2,var(--surface2));display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
.cpw-step.active .cpw-step-num{background:var(--admin-accent,var(--accent));color:#fff;}
.cpw-step.done .cpw-step-num{background:var(--success,#3D8B6E);color:#fff;}
.cpw-panel{display:none;}
.cpw-panel.active{display:block;}

.cpw-toolbar{display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap;}
.cpw-search-wrap{position:relative;flex:1;min-width:220px;max-width:400px;}
.cpw-search-wrap>svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--admin-text3,var(--text3));}
.cpw-search-wrap input{padding-left:34px !important;}
.cpw-counter{margin-left:auto;font-size:12px;color:var(--admin-text3,var(--text3));display:flex;gap:14px;flex-wrap:wrap;}
.cpw-counter strong{color:var(--admin-text,var(--text));font-size:15px;}

.cpw-product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;max-height:520px;overflow-y:auto;padding:4px;}
.cpw-pcard{border:1.5px solid var(--admin-table-border,var(--border));border-radius:10px;padding:10px;cursor:pointer;position:relative;background:var(--admin-card-bg,var(--surface));transition:border-color .15s;}
.cpw-pcard.selected{border-color:var(--admin-accent,var(--accent));background:var(--admin-accent-light,var(--accent-light));}
.cpw-pcard-check{position:absolute;top:8px;right:8px;width:20px;height:20px;border-radius:5px;border:1.5px solid var(--admin-table-border,var(--border));background:#fff;display:flex;align-items:center;justify-content:center;}
.cpw-pcard.selected .cpw-pcard-check{background:var(--admin-accent,var(--accent));border-color:var(--admin-accent,var(--accent));color:#fff;}
.cpw-pcard-thumb{width:100%;aspect-ratio:4/3;border-radius:6px;overflow:hidden;background:var(--admin-surface2,var(--surface2));margin-bottom:6px;}
.cpw-pcard-thumb img,.cpw-pcard-thumb svg{width:100%;height:100%;object-fit:cover;}
.cpw-pcard-name{font-size:12.5px;font-weight:700;line-height:1.3;margin-bottom:2px;}
.cpw-pcard-meta{font-size:10.5px;color:var(--admin-text3,var(--text3));}

.cpw-order-list{display:flex;flex-direction:column;gap:6px;max-height:560px;overflow-y:auto;}
.cpw-order-item{display:flex;align-items:center;gap:10px;padding:8px 10px;border:1.5px solid var(--admin-table-border,var(--border));border-radius:8px;background:var(--admin-card-bg,var(--surface));cursor:grab;}
.cpw-order-item.dragging{opacity:.4;}
.cpw-order-item.drag-over{border-color:var(--admin-accent,var(--accent));}
.cpw-order-thumb{width:40px;height:40px;border-radius:6px;overflow:hidden;background:var(--admin-surface2,var(--surface2));flex-shrink:0;}
.cpw-order-thumb img,.cpw-order-thumb svg{width:100%;height:100%;object-fit:cover;}
.cpw-order-name{font-size:13px;font-weight:600;flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.cpw-order-idx{width:24px;height:24px;border-radius:50%;background:var(--admin-surface2,var(--surface2));display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;}
.cpw-order-btns{display:flex;gap:4px;flex-shrink:0;}
.cpw-wizard-footer{display:flex;justify-content:space-between;margin-top:20px;gap:10px;}

.cpw-layout-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:24px;}
.cpw-layout-card{border:1.5px solid var(--admin-table-border,var(--border));border-radius:10px;padding:14px 12px;cursor:pointer;text-align:center;background:var(--admin-card-bg,var(--surface));}
.cpw-layout-card.selected{border-color:var(--admin-accent,var(--accent));background:var(--admin-accent-light,var(--accent-light));}
.cpw-layout-icon{width:100%;height:70px;border-radius:6px;background:var(--admin-surface2,var(--surface2));margin-bottom:8px;display:flex;align-items:center;justify-content:center;color:var(--admin-text3,var(--text3));}
.cpw-layout-name{font-size:12.5px;font-weight:700;}
.cpw-layout-desc{font-size:10.5px;color:var(--admin-text3,var(--text3));margin-top:2px;}

.cpw-fields-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;}
.cpw-field-item{display:flex;align-items:center;gap:8px;padding:9px 12px;border:1.5px solid var(--admin-table-border,var(--border));border-radius:8px;background:var(--admin-card-bg,var(--surface));cursor:pointer;}
.cpw-field-item input{width:16px;height:16px;accent-color:var(--admin-accent,var(--accent));flex-shrink:0;}
.cpw-field-item span{font-size:12.5px;font-weight:500;}

.cpw-cust-tabs{display:flex;gap:0;border-bottom:2px solid var(--admin-table-border,var(--border));margin-bottom:18px;overflow-x:auto;}
.cpw-cust-tab{padding:8px 16px;font-size:12.5px;font-weight:600;color:var(--admin-text3,var(--text3));border-bottom:2px solid transparent;margin-bottom:-2px;cursor:pointer;background:none;border-top:none;border-left:none;border-right:none;font-family:inherit;white-space:nowrap;}
.cpw-cust-tab.active{color:var(--admin-accent,var(--accent));border-bottom-color:var(--admin-accent,var(--accent));}
.cpw-cust-panel{display:none;}
.cpw-cust-panel.active{display:block;}
.cpw-cust-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--admin-surface2,var(--surface2));flex-wrap:wrap;gap:8px;}
.cpw-cust-row:last-child{border-bottom:none;}
.cpw-cust-label{font-size:13px;color:var(--admin-text2,var(--text2));min-width:160px;}
.cpw-cust-label small{display:block;font-size:11px;color:var(--admin-text3,var(--text3));}
.cpw-cust-control{display:flex;align-items:center;gap:8px;flex:1;min-width:200px;}
.cpw-cust-control input[type=text],.cpw-cust-control input[type=number],.cpw-cust-control select,.cpw-cust-control textarea{flex:1;}
.cpw-color-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;}
.cpw-color-item{display:flex;align-items:center;gap:8px;}
.cpw-color-item input[type=color]{width:38px;height:32px;padding:2px;border-radius:6px;border:1px solid var(--admin-table-border,var(--border));cursor:pointer;}

.cpw-review-card{background:var(--admin-card-bg,var(--surface));border:1px solid var(--admin-table-border,var(--border));border-radius:10px;padding:18px;margin-bottom:14px;}
.cpw-review-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 20px;}
.cpw-review-item{font-size:12.5px;}
.cpw-review-item b{color:var(--admin-text3,var(--text3));font-weight:600;display:block;font-size:10.5px;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px;}
#cpwGenProgress{display:none;text-align:center;padding:30px 0;}
#cpwGenResult{display:none;text-align:center;padding:20px 0;}

</style>

<div class="cpw-steps" id="cpwSteps">
  <div class="cpw-step active" data-step="1"><span class="cpw-step-num">1</span> Select Products</div>
  <div class="cpw-step" data-step="2"><span class="cpw-step-num">2</span> Order</div>
  <div class="cpw-step" data-step="3"><span class="cpw-step-num">3</span> Layout &amp; Fields</div>
  <div class="cpw-step" data-step="4"><span class="cpw-step-num">4</span> Customize</div>
  <div class="cpw-step" data-step="5"><span class="cpw-step-num">5</span> Generate</div>
</div>

<div class="admin-form-section" style="margin-bottom:14px;display:flex;gap:20px;flex-wrap:wrap;align-items:flex-end;">
  <div>
    <label class="admin-label">Catalog Name</label>
    <input type="text" id="cpwCatalogName" class="admin-input" style="max-width:400px;"
           value="<?= h($existing['name'] ?? 'New Catalog ' . date('d M Y')) ?>" placeholder="e.g. Summer Collection 2026"/>
  </div>
  <?php if (adminCan('catalog.template.manage')): $templates = getDB()->query("SELECT id,name FROM catalog_templates ORDER BY name ASC")->fetchAll(); ?>
  <div>
    <label class="admin-label">Load Template</label>
    <select id="cpwLoadTemplate" class="admin-input admin-select" style="max-width:220px;">
      <option value="">— Start Blank / Keep Current —</option>
      <?php foreach ($templates as $t): ?>
      <option value="<?= $t['id'] ?>"><?= h($t['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
</div>

<!-- ══ STEP 1: SELECT ══ -->
<div class="cpw-panel active" id="cpwPanel1">
  <div class="cpw-toolbar">
    <div class="cpw-search-wrap">
      <?= icon('search',14) ?>
      <input type="text" id="cpwSearch" class="admin-input" placeholder="Search name / quarry…" autocomplete="off"/>
    </div>
    <select id="cpwCatFilter" class="admin-input admin-select" style="max-width:180px;">
      <option value="">All Categories</option>
      <?php foreach ($categories as $c): ?>
      <option value="<?= h($c) ?>"><?= h($c) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="button" class="btn-admin-secondary btn-admin-sm" id="cpwSelectAll">Select All</button>
    <button type="button" class="btn-admin-secondary btn-admin-sm" id="cpwUnselectAll">Unselect All</button>
    <button type="button" class="btn-admin-secondary btn-admin-sm" id="cpwInvert">Invert</button>
    <div class="cpw-counter">
      <span>Selected: <strong id="cpwSelCount">0</strong></span>
      <span>Est. Pages: <strong id="cpwEstPages">0</strong></span>
      <span>Est. Size: <strong id="cpwEstSize">0 MB</strong></span>
    </div>
  </div>
  <div class="cpw-product-grid" id="cpwProductGrid">
    <p style="grid-column:1/-1;text-align:center;color:var(--admin-text3,var(--text3));padding:30px;">Loading products…</p>
  </div>
</div>

<!-- ══ STEP 2: ORDER ══ -->
<div class="cpw-panel" id="cpwPanel2">
  <div class="cpw-toolbar">
    <label class="admin-label" style="margin:0;">Sort by:</label>
    <select id="cpwSortBy" class="admin-input admin-select" style="max-width:180px;">
      <option value="manual">Manual Order</option>
      <option value="name">Name</option>
      <option value="category">Category</option>
      <option value="latest">Latest</option>
      <option value="oldest">Oldest</option>
    </select>
    <span style="font-size:12px;color:var(--admin-text3,var(--text3));margin-left:auto;">Drag rows to reorder, or use ↑↓</span>
  </div>
  <div class="cpw-order-list" id="cpwOrderList"></div>
</div>
<!-- ══ STEP 3: LAYOUT & FIELDS ══ -->
<div class="cpw-panel" id="cpwPanel3">
  <p class="admin-form-section-title" style="border:none;padding:0;">Choose a Page Layout</p>
  <div class="cpw-layout-grid" id="cpwLayoutGrid">
    <?php
    $layoutOptions = [
        'one_per_page' => ['One Per Page', 'Large image, full details'],
        'two_per_page' => ['Two Per Page', 'Compact side-by-side'],
        'four_per_page'=> ['Four Per Page', 'Quad grid, less detail'],
        'grid'         => ['Grid Layout', 'Many products, thumbnails'],
        'architect'    => ['Architect Style', 'Minimal, large photography'],
    ];
    foreach ($layoutOptions as $key => [$lname, $ldesc]):
    ?>
    <div class="cpw-layout-card" data-layout="<?= h($key) ?>">
      <div class="cpw-layout-icon"><?= icon('grid', 26) ?></div>
      <p class="cpw-layout-name"><?= h($lname) ?></p>
      <p class="cpw-layout-desc"><?= h($ldesc) ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <p class="admin-form-section-title" style="border:none;padding:0;">Product Fields to Include</p>
  <p style="font-size:12px;color:var(--admin-text3,var(--text3));margin-bottom:12px;">Only checked fields appear on each product's page.</p>
  <div class="cpw-fields-grid" id="cpwFieldsGrid">
    <?php
    $allFields = [
        'name'=>'Product Name','category'=>'Category','subcategory'=>'Subcategory',
        'color_subcategory'=>'Color','thickness'=>'Thickness','sizes'=>'Useable Size',
        'cutter_size'=>'Italian Size','origin'=>'Origin','finish'=>'Finish',
        'quantity_available'=>'Available Qty','quarry_number'=>'Quarry Number','description'=>'Description',
    ];
   $checkedFields = $existing['config']['fields'] ?? getCatalogPdfSettingsDefaults()['fields'];
    foreach ($allFields as $fkey => $flabel):
    ?>
    <label class="cpw-field-item">
      <input type="checkbox" name="pdf_field" value="<?= h($fkey) ?>" <?= in_array($fkey,$checkedFields,true)?'checked':'' ?>/>
      <span><?= h($flabel) ?></span>
    </label>
    <?php endforeach; ?>
  </div>
</div>

<!-- ══ STEP 4: CUSTOMIZE ══ -->
<div class="cpw-panel" id="cpwPanel4">
  <?php $cfg = array_replace_recursive(getCatalogPdfSettingsDefaults(), $existing['config'] ?? []); ?>
  <div class="cpw-cust-tabs">
    <button type="button" class="cpw-cust-tab active" data-tab="cover">Cover Page</button>
    <button type="button" class="cpw-cust-tab" data-tab="closing">Closing Page</button>
    <button type="button" class="cpw-cust-tab" data-tab="watermark">Watermark</button>
    <button type="button" class="cpw-cust-tab" data-tab="headerfooter">Header/Footer</button>
    <button type="button" class="cpw-cust-tab" data-tab="quality">Quality &amp; Format</button>
    <button type="button" class="cpw-cust-tab" data-tab="fontcolor">Font &amp; Colors</button>
  </div>

  <!-- Cover -->
  <div class="cpw-cust-panel active" id="cpwCust-cover">
    <div class="cpw-cust-row"><span class="cpw-cust-label">Show Company Logo</span><div class="cpw-cust-control"><input type="checkbox" id="cCoverLogo" <?= !empty($cfg['cover']['logo'])?'checked':'' ?>/></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Title</span><div class="cpw-cust-control"><input type="text" id="cCoverTitle" class="admin-input" value="<?= h($cfg['cover']['title']) ?>"/></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Subtitle</span><div class="cpw-cust-control"><input type="text" id="cCoverSubtitle" class="admin-input" value="<?= h($cfg['cover']['subtitle']) ?>"/></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Show Date</span><div class="cpw-cust-control"><input type="checkbox" id="cCoverDate" <?= !empty($cfg['cover']['show_date'])?'checked':'' ?>/>
      <select id="cCoverDateFormat" class="admin-input admin-select" style="max-width:160px;">
        <option value="d M Y" <?= $cfg['cover']['date_format']==='d M Y'?'selected':'' ?>>31 Dec 2026</option>
        <option value="M Y" <?= $cfg['cover']['date_format']==='M Y'?'selected':'' ?>>Dec 2026</option>
        <option value="d/m/Y" <?= $cfg['cover']['date_format']==='d/m/Y'?'selected':'' ?>>31/12/2026</option>
      </select>
    </div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Version</span><div class="cpw-cust-control"><input type="text" id="cCoverVersion" class="admin-input" value="<?= h($cfg['cover']['version']) ?>" style="max-width:120px;"/></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Marketing Message</span><div class="cpw-cust-control"><textarea id="cCoverMsg" class="admin-input" rows="2"><?= h($cfg['cover']['marketing_message']) ?></textarea></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Show Contact Details</span><div class="cpw-cust-control"><input type="checkbox" id="cCoverContact" <?= !empty($cfg['cover']['contact_details'])?'checked':'' ?>/></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Footer Text</span><div class="cpw-cust-control"><input type="text" id="cCoverFooter" class="admin-input" value="<?= h($cfg['cover']['footer_text']) ?>"/></div></div>
  </div>

  <!-- Closing -->
  <div class="cpw-cust-panel" id="cpwCust-closing">
    <div class="cpw-cust-row"><span class="cpw-cust-label">Enable Closing Page</span><div class="cpw-cust-control"><input type="checkbox" id="cClosingEnabled" <?= !empty($cfg['closing']['enabled'])?'checked':'' ?>/></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Thank You Text</span><div class="cpw-cust-control"><textarea id="cClosingText" class="admin-input" rows="2"><?= h($cfg['closing']['thank_you_text']) ?></textarea></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Contact Information</span><div class="cpw-cust-control"><input type="checkbox" id="cClosingContact" <?= !empty($cfg['closing']['contact_info'])?'checked':'' ?>/></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Website QR Code</span><div class="cpw-cust-control"><input type="checkbox" id="cClosingWebQr" <?= !empty($cfg['closing']['website_qr'])?'checked':'' ?>/></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Google Map QR Code</span><div class="cpw-cust-control"><input type="checkbox" id="cClosingMapQr" <?= !empty($cfg['closing']['gmap_qr'])?'checked':'' ?>/></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Social Media</span><div class="cpw-cust-control"><input type="checkbox" id="cClosingSocial" <?= !empty($cfg['closing']['social_media'])?'checked':'' ?>/></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Sales Team Details</span><div class="cpw-cust-control"><input type="checkbox" id="cClosingSales" <?= !empty($cfg['closing']['sales_team'])?'checked':'' ?>/></div></div>
  </div>

  <!-- Watermark -->
  <div class="cpw-cust-panel" id="cpwCust-watermark">
    <div class="cpw-cust-row"><span class="cpw-cust-label">Watermark Type</span>
      <div class="cpw-cust-control">
        <select id="cWmType" class="admin-input admin-select">
          <?php foreach (['none'=>'No Watermark','logo'=>'Company Logo','confidential'=>'CONFIDENTIAL','sample'=>'SAMPLE','custom'=>'Custom Text'] as $wk=>$wl): ?>
          <option value="<?= $wk ?>" <?= $cfg['watermark']['type']===$wk?'selected':'' ?>><?= $wl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="cpw-cust-row" id="cWmCustomRow" style="<?= $cfg['watermark']['type']!=='custom'?'display:none;':'' ?>"><span class="cpw-cust-label">Custom Text</span><div class="cpw-cust-control"><input type="text" id="cWmCustomText" class="admin-input" value="<?= h($cfg['watermark']['custom_text']) ?>"/></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Opacity (%)</span><div class="cpw-cust-control"><input type="range" id="cWmOpacity" min="0" max="100" value="<?= (int)$cfg['watermark']['opacity'] ?>"/><span id="cWmOpacityVal"><?= (int)$cfg['watermark']['opacity'] ?></span>%</div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Rotation (deg)</span><div class="cpw-cust-control"><input type="range" id="cWmRotation" min="-90" max="90" value="<?= (int)$cfg['watermark']['rotation'] ?>"/><span id="cWmRotationVal"><?= (int)$cfg['watermark']['rotation'] ?></span>°</div></div>
  </div>

  <!-- Header/Footer -->
  <div class="cpw-cust-panel" id="cpwCust-headerfooter">
    <p style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--admin-text3,var(--text3));margin:8px 0;">Header</p>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Company Logo</span><div class="cpw-cust-control"><input type="checkbox" id="cHdrLogo" <?= !empty($cfg['header']['logo'])?'checked':'' ?>/></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Catalog Name</span><div class="cpw-cust-control"><input type="checkbox" id="cHdrName" <?= !empty($cfg['header']['catalog_name'])?'checked':'' ?>/></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Page Title</span><div class="cpw-cust-control"><input type="checkbox" id="cHdrTitle" <?= !empty($cfg['header']['page_title'])?'checked':'' ?>/></div></div>
    <p style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--admin-text3,var(--text3));margin:16px 0 8px;">Footer</p>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Page Number</span><div class="cpw-cust-control"><input type="checkbox" id="cFtrPageNum" <?= !empty($cfg['footer']['page_number'])?'checked':'' ?>/>
      <select id="cFtrPageNumPos" class="admin-input admin-select" style="max-width:160px;">
        <?php foreach (['bottom_left'=>'Bottom Left','bottom_center'=>'Bottom Center','bottom_right'=>'Bottom Right','top_left'=>'Top Left','top_right'=>'Top Right'] as $pk=>$pl): ?>
        <option value="<?= $pk ?>" <?= $cfg['page_number_position']===$pk?'selected':'' ?>><?= $pl ?></option>
        <?php endforeach; ?>
      </select>
    </div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Website</span><div class="cpw-cust-control"><input type="checkbox" id="cFtrWebsite" <?= !empty($cfg['footer']['website'])?'checked':'' ?>/></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Email</span><div class="cpw-cust-control"><input type="checkbox" id="cFtrEmail" <?= !empty($cfg['footer']['email'])?'checked':'' ?>/></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Phone</span><div class="cpw-cust-control"><input type="checkbox" id="cFtrPhone" <?= !empty($cfg['footer']['phone'])?'checked':'' ?>/></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Generated Date</span><div class="cpw-cust-control"><input type="checkbox" id="cFtrGenDate" <?= !empty($cfg['footer']['generated_date'])?'checked':'' ?>/></div></div>
  </div>

  <!-- Quality & Format -->
  <div class="cpw-cust-panel" id="cpwCust-quality">
    <div class="cpw-cust-row"><span class="cpw-cust-label">PDF Quality</span><div class="cpw-cust-control">
      <select id="cQualLevel" class="admin-input admin-select">
        <?php foreach (['low'=>'Low','medium'=>'Medium','high'=>'High','print'=>'Print Quality'] as $qk=>$ql): ?>
        <option value="<?= $qk ?>" <?= $cfg['quality']['level']===$qk?'selected':'' ?>><?= $ql ?></option>
        <?php endforeach; ?>
      </select>
    </div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Compression</span><div class="cpw-cust-control">
      <select id="cQualCompress" class="admin-input admin-select">
        <option value="compress" <?= $cfg['quality']['compression']==='compress'?'selected':'' ?>>Compressed</option>
        <option value="none" <?= $cfg['quality']['compression']==='none'?'selected':'' ?>>No Compression</option>
      </select>
    </div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Optimize Image Size</span><div class="cpw-cust-control"><input type="checkbox" id="cQualOptimize" <?= !empty($cfg['quality']['optimize_size'])?'checked':'' ?>/></div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Orientation</span><div class="cpw-cust-control">
      <select id="cOrientation" class="admin-input admin-select">
        <option value="portrait" <?= $cfg['orientation']==='portrait'?'selected':'' ?>>Portrait</option>
        <option value="landscape" <?= $cfg['orientation']==='landscape'?'selected':'' ?>>Landscape</option>
      </select>
    </div></div>
    <div class="cpw-cust-row"><span class="cpw-cust-label">Page Size</span><div class="cpw-cust-control">
      <select id="cPageSize" class="admin-input admin-select">
        <?php foreach (['A4','A3','Letter','Legal','Custom'] as $ps): ?>
        <option value="<?= $ps ?>" <?= $cfg['page_size']===$ps?'selected':'' ?>><?= $ps ?></option>
        <?php endforeach; ?>
      </select>
    </div></div>
    <div class="cpw-cust-row" id="cCustomSizeRow" style="<?= $cfg['page_size']!=='Custom'?'display:none;':'' ?>">
      <span class="cpw-cust-label">Custom Size (mm)</span>
      <div class="cpw-cust-control">
        <input type="number" id="cCustomW" class="admin-input" style="max-width:90px;" value="<?= (float)$cfg['custom_w_mm'] ?>" placeholder="Width"/>
        <input type="number" id="cCustomH" class="admin-input" style="max-width:90px;" value="<?= (float)$cfg['custom_h_mm'] ?>" placeholder="Height"/>
      </div>
    </div>
  </div>

  <!-- Font & Colors -->
  <div class="cpw-cust-panel" id="cpwCust-fontcolor">
    <div class="cpw-cust-row"><span class="cpw-cust-label">Font Family</span><div class="cpw-cust-control">
      <select id="cFont" class="admin-input admin-select">
        <?php foreach (['helvetica'=>'Helvetica','arial'=>'Arial','roboto'=>'Roboto','open_sans'=>'Open Sans','noto_sans'=>'Noto Sans'] as $fk=>$fl): ?>
        <option value="<?= $fk ?>" <?= $cfg['font']===$fk?'selected':'' ?>><?= $fl ?></option>
        <?php endforeach; ?>
      </select>
    </div></div>
    <p style="font-size:12px;color:var(--admin-text3,var(--text3));margin:14px 0 10px;">Company brand colors — pulled from your theme by default.</p>
    <div class="cpw-color-grid">
      <?php foreach (['primary'=>'Primary','secondary'=>'Secondary','accent'=>'Accent','background'=>'Background','text'=>'Text','button'=>'Button','border'=>'Border'] as $ck=>$cl): ?>
      <div class="cpw-color-item">
        <input type="color" id="cColor_<?= $ck ?>" value="<?= h($cfg['colors'][$ck] ?? '#000000') ?>"/>
        <span style="font-size:12px;"><?= $cl ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ══ STEP 5: REVIEW & GENERATE ══ -->
<div class="cpw-panel" id="cpwPanel5">
  <div class="cpw-review-card">
    <p class="admin-form-section-title" style="border:none;padding:0;">Summary</p>
    <div class="cpw-review-grid" id="cpwReviewGrid"></div>
  </div>

  <?php if (adminCan('catalog.template.manage')): ?>
  <div style="display:flex;gap:10px;align-items:center;justify-content:center;margin-bottom:18px;flex-wrap:wrap;">
    <input type="text" id="cpwTemplateName" class="admin-input" placeholder="Template name (e.g. Premium Catalog)" style="max-width:260px;"/>
    <button type="button" class="btn-admin-secondary btn-admin-sm" id="cpwSaveTemplateBtn"><?= icon('copy',13) ?> Save as Template</button>
    <span id="cpwTemplateStatus" style="font-size:12px;"></span>
  </div>
  <?php endif; ?>

  <div id="cpwGenIdle" style="text-align:center;padding:10px 0;">
    <button type="button" class="btn-admin-primary" id="cpwGenerateBtn" style="padding:12px 32px;font-size:14px;">
      <?= icon('check',16) ?> Generate PDF
    </button>
  </div>

  <div id="cpwGenProgress">
    <div class="admin-loader-ring" style="margin:0 auto 14px;"></div>
    <p id="cpwGenProgressText" style="font-size:13px;color:var(--admin-text2,var(--text2));">Preparing images…</p>
  </div>

  <div id="cpwGenResult">
    <div style="width:56px;height:56px;border-radius:50%;background:var(--success-bg,#D8EFE6);color:var(--success,#3D8B6E);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
      <?= icon('check',24) ?>
    </div>
    <p style="font-size:15px;font-weight:700;margin-bottom:6px;">PDF Generated!</p>
    <p id="cpwGenMeta" style="font-size:12px;color:var(--admin-text3,var(--text3));margin-bottom:20px;"></p>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
      <a href="#" id="cpwDownloadBtn" class="btn-admin-primary"><?= icon('download',14) ?> Download</a>
      <a href="index.php?page=catalog_pdf_history" class="btn-admin-secondary">View History</a>
    </div>
  </div>

  <div id="cpwGenError" style="display:none;text-align:center;padding:20px 0;color:var(--danger,#E84040);">
    <p style="font-weight:600;margin-bottom:8px;">Generation Failed</p>
    <p id="cpwGenErrorMsg" style="font-size:12px;"></p>
    <button type="button" class="btn-admin-secondary btn-admin-sm" id="cpwRetryBtn" style="margin-top:12px;">Try Again</button>
  </div>
</div>

<div class="cpw-wizard-footer">
  <button type="button" class="btn-admin-secondary" id="cpwBackBtn" style="visibility:hidden;">← Back</button>
  <button type="button" class="btn-admin-primary" id="cpwNextBtn">Next: Order →</button>
</div>

<script>
(function () {
  var csrfToken = <?= json_encode(csrfToken()) ?>;
  var catalogId = <?= (int)$catalogId ?>;
  var existingIds = <?= json_encode($existing['product_ids'] ?? []) ?>;

  var allProducts = [];          // full loaded set (search results)
  var productMap  = {};          // id -> product obj (accumulated across searches so selection survives re-search)
  var selected    = new Set(existingIds.map(Number));
  var order       = existingIds.map(Number); // manual order array of ids
  var currentStep = 1;
  var dragEl      = null;
  var pdfGenerated = <?= json_encode((bool)($existing && ($existing['status'] ?? '') === 'done')) ?>;
  function esc(s){ var d=document.createElement('div'); d.textContent=String(s==null?'':s); return d.innerHTML; }

  var selectedLayout = <?= json_encode($existing['config']['layout'] ?? 'one_per_page') ?>;

  // ── Step nav 
  function goStep(n) {
    currentStep = n;
    document.querySelectorAll('.cpw-panel').forEach(function(p,i){ p.classList.toggle('active', (i+1)===n); });
    document.querySelectorAll('.cpw-step').forEach(function(s){
      var sn = parseInt(s.dataset.step,10);
      s.classList.toggle('active', sn===n);
      s.classList.toggle('done', sn<n);
      s.style.opacity = sn<=n ? '1' : '.4';
    });
    document.getElementById('cpwBackBtn').style.visibility = n>1 ? 'visible' : 'hidden';
    var labels = {1:'Next: Order →',2:'Next: Layout →',3:'Next: Customize →'};
    document.getElementById('cpwNextBtn').textContent = labels[n] || 'Next →';
    if (n===2) renderOrderList();
    if (n===3) initLayoutCards();
  }
document.getElementById('cpwBackBtn').addEventListener('click', function(){ if(currentStep>1) goStep(currentStep-1); });
  document.getElementById('cpwNextBtn').addEventListener('click', function(){
    if (currentStep===1 && selected.size===0) { alert('Select at least one product.'); return; }
    if (currentStep===5) return; // step 5 has its own Generate button, not Next
    if (currentStep===4) { saveDraft(function(){ renderReview(); goStep(5); }); return; }
    goStep(currentStep+1);
  });
  document.getElementById('cpwNextBtn').textContent = 'Next: Order →';

  // hide Next button entirely on step 5 (Generate button takes over)
 // hide Next button entirely on step 5 (Generate button takes over)
var _origGoStep = goStep;
goStep = function(n) {
  _origGoStep(n);
  document.getElementById('cpwNextBtn').style.display = (n===5) ? 'none' : '';
  if (n === 5) resetStep5UI();
};

function resetStep5UI() {
  document.getElementById('cpwGenIdle').style.display = 'block';
  document.getElementById('cpwGenProgress').style.display = 'none';
  document.getElementById('cpwGenResult').style.display = 'none';
  document.getElementById('cpwGenError').style.display = 'none';
  document.getElementById('cpwGenerateBtn').innerHTML = pdfGenerated
    ? '<?= icon("refresh",16) ?> Regenerate PDF'
    : '<?= icon("check",16) ?> Generate PDF';
}

  // ── Customize tab switching ──────────────────────────────────────────
  document.querySelectorAll('.cpw-cust-tab').forEach(function(tab){
    tab.addEventListener('click', function(){
      document.querySelectorAll('.cpw-cust-tab').forEach(function(t){ t.classList.remove('active'); });
      document.querySelectorAll('.cpw-cust-panel').forEach(function(p){ p.classList.remove('active'); });
      tab.classList.add('active');
      document.getElementById('cpwCust-' + tab.dataset.tab).classList.add('active');
    });
  });
  document.getElementById('cWmType').addEventListener('change', function(){
    document.getElementById('cWmCustomRow').style.display = this.value === 'custom' ? '' : 'none';
  });
  document.getElementById('cPageSize').addEventListener('change', function(){
    document.getElementById('cCustomSizeRow').style.display = this.value === 'Custom' ? '' : 'none';
  });
  document.getElementById('cWmOpacity').addEventListener('input', function(){ document.getElementById('cWmOpacityVal').textContent = this.value; });
  document.getElementById('cWmRotation').addEventListener('input', function(){ document.getElementById('cWmRotationVal').textContent = this.value; });

  // ── Collect full config object from Step 4 form fields ───────────────
  function collectCustomizeConfig() {
    return {
      cover: {
        logo: document.getElementById('cCoverLogo').checked ? 1 : 0,
        title: document.getElementById('cCoverTitle').value,
        subtitle: document.getElementById('cCoverSubtitle').value,
        show_date: document.getElementById('cCoverDate').checked ? 1 : 0,
        date_format: document.getElementById('cCoverDateFormat').value,
        version: document.getElementById('cCoverVersion').value,
        marketing_message: document.getElementById('cCoverMsg').value,
        contact_details: document.getElementById('cCoverContact').checked ? 1 : 0,
        footer_text: document.getElementById('cCoverFooter').value,
      },
      closing: {
        enabled: document.getElementById('cClosingEnabled').checked ? 1 : 0,
        thank_you_text: document.getElementById('cClosingText').value,
        contact_info: document.getElementById('cClosingContact').checked ? 1 : 0,
        website_qr: document.getElementById('cClosingWebQr').checked ? 1 : 0,
        gmap_qr: document.getElementById('cClosingMapQr').checked ? 1 : 0,
        social_media: document.getElementById('cClosingSocial').checked ? 1 : 0,
        sales_team: document.getElementById('cClosingSales').checked ? 1 : 0,
      },
      watermark: {
        type: document.getElementById('cWmType').value,
        custom_text: document.getElementById('cWmCustomText').value,
        opacity: parseInt(document.getElementById('cWmOpacity').value, 10),
        rotation: parseInt(document.getElementById('cWmRotation').value, 10),
      },
      header: {
        logo: document.getElementById('cHdrLogo').checked ? 1 : 0,
        catalog_name: document.getElementById('cHdrName').checked ? 1 : 0,
        page_title: document.getElementById('cHdrTitle').checked ? 1 : 0,
      },
      footer: {
        page_number: document.getElementById('cFtrPageNum').checked ? 1 : 0,
        website: document.getElementById('cFtrWebsite').checked ? 1 : 0,
        email: document.getElementById('cFtrEmail').checked ? 1 : 0,
        phone: document.getElementById('cFtrPhone').checked ? 1 : 0,
        generated_date: document.getElementById('cFtrGenDate').checked ? 1 : 0,
      },
      page_number_position: document.getElementById('cFtrPageNumPos').value,
      quality: {
        level: document.getElementById('cQualLevel').value,
        compression: document.getElementById('cQualCompress').value,
        optimize_size: document.getElementById('cQualOptimize').checked ? 1 : 0,
      },
      orientation: document.getElementById('cOrientation').value,
      page_size: document.getElementById('cPageSize').value,
      custom_w_mm: parseFloat(document.getElementById('cCustomW').value) || 210,
      custom_h_mm: parseFloat(document.getElementById('cCustomH').value) || 297,
      font: document.getElementById('cFont').value,
      colors: {
        primary: document.getElementById('cColor_primary').value,
        secondary: document.getElementById('cColor_secondary').value,
        accent: document.getElementById('cColor_accent').value,
        background: document.getElementById('cColor_background').value,
        text: document.getElementById('cColor_text').value,
        button: document.getElementById('cColor_button').value,
        border: document.getElementById('cColor_border').value,
      },
    };
  }

  function renderReview() {
    var cfg = collectCustomizeConfig();
    var grid = document.getElementById('cpwReviewGrid');
    var rows = [
      ['Catalog Name', document.getElementById('cpwCatalogName').value],
      ['Products Selected', selected.size],
      ['Layout', selectedLayout.replace(/_/g,' ')],
      ['Fields Shown', getCheckedFields().length + ' fields'],
      ['Orientation', cfg.orientation],
      ['Page Size', cfg.page_size],
      ['Quality', cfg.quality.level],
      ['Font', cfg.font],
      ['Watermark', cfg.watermark.type],
      ['Cover Page', cfg.cover.title || '—'],
      ['Closing Page', cfg.closing.enabled ? 'Enabled' : 'Disabled'],
      ['Est. Pages', selected.size + (cfg.closing.enabled?2:1)],
    ];
    grid.innerHTML = rows.map(function(r){
      return '<div class="cpw-review-item"><b>'+r[0]+'</b>'+r[1]+'</div>';
    }).join('');
  }

  var _origSaveDraft = saveDraft;
  saveDraft = function(cb) {
    var body = new URLSearchParams();
    body.set('action', 'save_catalog_draft');
    body.set('catalog_id', catalogId);
    body.set('name', document.getElementById('cpwCatalogName').value.trim());
    body.set('product_ids', JSON.stringify(order));
    body.set('layout', selectedLayout);
    body.set('fields', JSON.stringify(getCheckedFields()));
    body.set('customize', JSON.stringify(collectCustomizeConfig()));
    body.set('csrf_token', csrfToken);
fetch('index.php?page=catalog_pdf_wizard', { method:'POST', body: body, headers:{'X-Requested-With':'XMLHttpRequest'} })
  .then(function(r){ return r.json(); })
  .then(function(d){
    if (d.success) {
      catalogId = d.id;
      var url = new URL(window.location.href);
      url.searchParams.set('id', catalogId);
      history.replaceState({}, '', url); 
    }
    if (cb) cb(d);
  })
  .catch(function(e){ alert('Save failed: '+e.message); });
  };

  // ── Generate PDF (step 5) ─────────────────────────────────────────────
  document.getElementById('cpwGenerateBtn').addEventListener('click', function(){ runGenerate(); });
  document.getElementById('cpwRetryBtn')?.addEventListener('click', function(){ runGenerate(); });

  function runGenerate() {
     document.getElementById('cpwGenIdle').style.display = 'none';
    document.getElementById('cpwGenResult').style.display = 'none';
    document.getElementById('cpwGenError').style.display = 'none';
    document.getElementById('cpwGenProgress').style.display = 'block';

    var stages = ['Preparing images…','Generating PDF…','Compressing…','Finalizing…'];
    var si = 0;
    var stageTimer = setInterval(function(){
      si = (si+1) % stages.length;
      document.getElementById('cpwGenProgressText').textContent = stages[si];
    }, 900);

    saveDraft(function(){
      var body = new URLSearchParams();
      body.set('action', 'generate_catalog_pdf_test');
      body.set('catalog_id', catalogId);
      body.set('csrf_token', csrfToken);
      fetch('index.php?page=catalog_pdf_wizard', { method:'POST', body: body, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(function(r){ return r.json(); })
        .then(function(d){
          clearInterval(stageTimer);
          document.getElementById('cpwGenProgress').style.display = 'none';
          if (d.success) {
  pdfGenerated = true;
  document.getElementById('cpwGenResult').style.display = 'block';
  document.getElementById('cpwGenMeta').textContent = d.pages + ' pages · ' + (d.size/1048576).toFixed(1) + ' MB';
  document.getElementById('cpwDownloadBtn').href = 'index.php?catalog_download=1&id=' + catalogId;
} else {
            document.getElementById('cpwGenError').style.display = 'block';
            document.getElementById('cpwGenErrorMsg').textContent = d.error || 'Unknown error.';
          }
        })
        .catch(function(e){
          clearInterval(stageTimer);
          document.getElementById('cpwGenProgress').style.display = 'none';
          document.getElementById('cpwGenError').style.display = 'block';
          document.getElementById('cpwGenErrorMsg').textContent = e.message;
        });
    });
  }

  function initLayoutCards() {
    var cards = document.querySelectorAll('.cpw-layout-card');
    cards.forEach(function(c){
      c.classList.toggle('selected', c.dataset.layout === selectedLayout);
      c.addEventListener('click', function(){
        selectedLayout = c.dataset.layout;
        cards.forEach(function(x){ x.classList.remove('selected'); });
        c.classList.add('selected');
      }, { once:false });
    });
  }
  function getCheckedFields() {
    return Array.from(document.querySelectorAll('input[name="pdf_field"]:checked')).map(function(el){ return el.value; });
  }

  // ── Step 1: load + render products ───────────────────────────────────
  function loadProducts() {
    var q   = document.getElementById('cpwSearch').value.trim();
    var cat = document.getElementById('cpwCatFilter').value;
    var params = new URLSearchParams({ ajax_pdf_products: '1', q: q, cat: cat });
    fetch('index.php?page=catalog_pdf_wizard&' + params)
      .then(function(r){ return r.json(); })
      .then(function(d){
        allProducts = d.products || [];
        allProducts.forEach(function(p){ productMap[p.id] = p; });
        renderGrid();
      });
  }

  function renderGrid() {
    var grid = document.getElementById('cpwProductGrid');
    if (!allProducts.length) {
      grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:var(--admin-text3,var(--text3));padding:30px;">No products found.</p>';
      return;
    }
    grid.innerHTML = allProducts.map(function(p){
      var isSel = selected.has(Number(p.id));
      var thumb = p.primary_photo
        ? '<img src="../assets/uploads/_thumb/'+esc(p.primary_photo)+'" alt=""/>'
        : '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:9px;color:var(--admin-text3,var(--text3));">No img</div>';
      return '<div class="cpw-pcard'+(isSel?' selected':'')+'" data-id="'+p.id+'">'+
        '<div class="cpw-pcard-check">'+(isSel?'<?= icon("check",12) ?>':'')+'</div>'+
        '<div class="cpw-pcard-thumb">'+thumb+'</div>'+
        '<p class="cpw-pcard-name">'+esc(p.name)+'</p>'+
        '<p class="cpw-pcard-meta">Lot '+esc(p.quarry_number)+' · '+esc(p.category||'')+'</p>'+
      '</div>';
    }).join('');
    grid.querySelectorAll('.cpw-pcard').forEach(function(card){
      card.addEventListener('click', function(){ toggleSelect(parseInt(card.dataset.id,10)); });
    });
  }

  function toggleSelect(id) {
    if (selected.has(id)) { selected.delete(id); order = order.filter(function(x){ return x!==id; }); }
    else { selected.add(id); order.push(id); }
    updateCounter();
    renderGrid();
  }

  document.getElementById('cpwSelectAll').addEventListener('click', function(){
    allProducts.forEach(function(p){ var id=Number(p.id); if(!selected.has(id)){ selected.add(id); order.push(id);} });
    updateCounter(); renderGrid();
  });
  document.getElementById('cpwUnselectAll').addEventListener('click', function(){
    allProducts.forEach(function(p){ selected.delete(Number(p.id)); });
    order = order.filter(function(id){ return selected.has(id); });
    updateCounter(); renderGrid();
  });
  document.getElementById('cpwInvert').addEventListener('click', function(){
    allProducts.forEach(function(p){
      var id = Number(p.id);
      if (selected.has(id)) { selected.delete(id); order = order.filter(function(x){return x!==id;}); }
      else { selected.add(id); order.push(id); }
    });
    updateCounter(); renderGrid();
  });

  function updateCounter() {
    var n = selected.size;
    document.getElementById('cpwSelCount').textContent = n;
    document.getElementById('cpwEstPages').textContent = n; // 1 page/product estimate (layout picker in Fire 3 refines this)
    document.getElementById('cpwEstSize').textContent = (n * 0.6).toFixed(1) + ' MB'; // rough est
  }

  var searchTimer = null;
  document.getElementById('cpwSearch').addEventListener('input', function(){
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadProducts, 300);
  });
  document.getElementById('cpwCatFilter').addEventListener('change', loadProducts);

  // ── Step 2: order list (drag-drop + move up/down + sort-by) ──────────
  function renderOrderList() {
    var list = document.getElementById('cpwOrderList');
    if (!order.length) { list.innerHTML = '<p style="text-align:center;color:var(--admin-text3,var(--text3));padding:20px;">No products selected.</p>'; return; }
    list.innerHTML = order.map(function(id, i){
      var p = productMap[id] || { name: '#'+id, quarry_number: '', primary_photo: '' };
      var thumb = p.primary_photo
        ? '<img src="../assets/uploads/photos/'+esc(p.primary_photo)+'" alt=""/>'
        : '<div style="width:100%;height:100%;"></div>';
      return '<div class="cpw-order-item" draggable="true" data-id="'+id+'">'+
        '<span class="cpw-order-idx">'+(i+1)+'</span>'+
        '<div class="cpw-order-thumb">'+thumb+'</div>'+
        '<span class="cpw-order-name">'+esc(p.name)+'</span>'+
        '<div class="cpw-order-btns">'+
          '<button type="button" class="btn-admin-secondary btn-admin-sm cpw-move-up">'+'<?= icon("back",12) ?>'.replace('back','up')+'↑</button>'+
          '<button type="button" class="btn-admin-secondary btn-admin-sm cpw-move-down">↓</button>'+
        '</div>'+
      '</div>';
    }).join('');
    bindOrderRowEvents();
  }

 function bindOrderRowEvents() {
  var list = document.getElementById('cpwOrderList');

  list.querySelectorAll('.cpw-order-item').forEach(function(item){
    item.addEventListener('dragstart', function(){ dragEl = item; item.classList.add('dragging'); });
    item.addEventListener('dragend', function(){
      item.classList.remove('dragging');
      list.querySelectorAll('.drag-over').forEach(function(el){ el.classList.remove('drag-over'); });
      dragEl = null;
      syncOrderFromDOM();
    });
    item.querySelector('.cpw-move-up').addEventListener('click', function(){ moveItem(parseInt(item.dataset.id,10), -1); });
    item.querySelector('.cpw-move-down').addEventListener('click', function(){ moveItem(parseInt(item.dataset.id,10), 1); });
  });

  if (!list.dataset.dragoverBound) {
    list.dataset.dragoverBound = '1';
    list.addEventListener('dragover', function(e){
      e.preventDefault();
      if (!dragEl) return;
      var target = e.target.closest('.cpw-order-item');
      if (!target || target===dragEl) return;
      var rect = target.getBoundingClientRect();
      var before = (e.clientY - rect.top) < rect.height/2;
      list.querySelectorAll('.drag-over').forEach(function(el){ el.classList.remove('drag-over'); });
      target.classList.add('drag-over');
      if (before) list.insertBefore(dragEl, target); else list.insertBefore(dragEl, target.nextSibling);
    });
  }
}
  
  function syncOrderFromDOM() {
    var list = document.getElementById('cpwOrderList');
    order = Array.from(list.querySelectorAll('.cpw-order-item')).map(function(el){ return parseInt(el.dataset.id,10); });
    renderOrderList();
  }

  function moveItem(id, dir) {
    var idx = order.indexOf(id);
    var newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= order.length) return;
    order.splice(idx,1); order.splice(newIdx,0,id);
    renderOrderList();
  }

  document.getElementById('cpwSortBy').addEventListener('change', function(){
    var mode = this.value;
    if (mode === 'manual') return;
    order.sort(function(a,b){
      var pa = productMap[a] || {}, pb = productMap[b] || {};
      if (mode==='name') return (pa.name||'').localeCompare(pb.name||'');
      if (mode==='category') return (pa.category||'').localeCompare(pb.category||'');
      if (mode==='latest') return b - a; // higher id = newer
      if (mode==='oldest') return a - b;
      return 0;
    });
    renderOrderList();
  });

  // ── Save draft (AJAX) ─────────────────────────────────────────────────
  function saveDraft(cb) {
    var body = new URLSearchParams();
    body.set('action', 'save_catalog_draft');
    body.set('catalog_id', catalogId);
    body.set('name', document.getElementById('cpwCatalogName').value.trim());
    body.set('product_ids', JSON.stringify(order));
    body.set('layout', selectedLayout);
    body.set('fields', JSON.stringify(getCheckedFields()));
    body.set('csrf_token', csrfToken);
    fetch('index.php?page=catalog_pdf_wizard', { method:'POST', body: body, headers:{'X-Requested-With':'XMLHttpRequest'} })
      .then(function(r){ return r.json(); })
      .then(function(d){ if(d.success){ catalogId = d.id; } if(cb) cb(d); })
      .catch(function(e){ alert('Save failed: '+e.message); });
  }
  // ── Load Template ─────────────────────────────────────────────────────
  var templateLoadEl = document.getElementById('cpwLoadTemplate');
  if (templateLoadEl) {
    templateLoadEl.addEventListener('change', function () {
      var tid = this.value;
      if (!tid) return;
      fetch('index.php?page=catalog_pdf_wizard&ajax_load_template=1&template_id=' + tid)
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (!d.success) { alert('Could not load template: ' + (d.error||'')); return; }
          applyTemplateConfig(d.config);
        });
    });
  }

  function applyTemplateConfig(cfg) {
    if (!cfg) return;
    // layout
    if (cfg.layout) selectedLayout = cfg.layout;
    document.querySelectorAll('.cpw-layout-card').forEach(function(c){
      c.classList.toggle('selected', c.dataset.layout === selectedLayout);
    });
    // fields
    if (Array.isArray(cfg.fields)) {
      document.querySelectorAll('input[name="pdf_field"]').forEach(function(cb){
        cb.checked = cfg.fields.indexOf(cb.value) !== -1;
      });
    }
    // customize (cover/closing/watermark/header/footer/quality/orientation/pagesize/font/colors)
    var c = cfg.cover || {};
    if (document.getElementById('cCoverLogo')) {
      document.getElementById('cCoverLogo').checked = !!c.logo;
      document.getElementById('cCoverTitle').value = c.title || '';
      document.getElementById('cCoverSubtitle').value = c.subtitle || '';
      document.getElementById('cCoverDate').checked = !!c.show_date;
      document.getElementById('cCoverDateFormat').value = c.date_format || 'd M Y';
      document.getElementById('cCoverVersion').value = c.version || '';
      document.getElementById('cCoverMsg').value = c.marketing_message || '';
      document.getElementById('cCoverContact').checked = !!c.contact_details;
      document.getElementById('cCoverFooter').value = c.footer_text || '';

      var cl = cfg.closing || {};
      document.getElementById('cClosingEnabled').checked = !!cl.enabled;
      document.getElementById('cClosingText').value = cl.thank_you_text || '';
      document.getElementById('cClosingContact').checked = !!cl.contact_info;
      document.getElementById('cClosingWebQr').checked = !!cl.website_qr;
      document.getElementById('cClosingMapQr').checked = !!cl.gmap_qr;
      document.getElementById('cClosingSocial').checked = !!cl.social_media;
      document.getElementById('cClosingSales').checked = !!cl.sales_team;

      var wm = cfg.watermark || {};
      document.getElementById('cWmType').value = wm.type || 'none';
      document.getElementById('cWmCustomRow').style.display = wm.type==='custom' ? '' : 'none';
      document.getElementById('cWmCustomText').value = wm.custom_text || '';
      document.getElementById('cWmOpacity').value = wm.opacity != null ? wm.opacity : 15;
      document.getElementById('cWmOpacityVal').textContent = document.getElementById('cWmOpacity').value;
      document.getElementById('cWmRotation').value = wm.rotation != null ? wm.rotation : -45;
      document.getElementById('cWmRotationVal').textContent = document.getElementById('cWmRotation').value;

      var hd = cfg.header || {}, ft = cfg.footer || {};
      document.getElementById('cHdrLogo').checked = !!hd.logo;
      document.getElementById('cHdrName').checked = !!hd.catalog_name;
      document.getElementById('cHdrTitle').checked = !!hd.page_title;
      document.getElementById('cFtrPageNum').checked = !!ft.page_number;
      document.getElementById('cFtrPageNumPos').value = cfg.page_number_position || 'bottom_center';
      document.getElementById('cFtrWebsite').checked = !!ft.website;
      document.getElementById('cFtrEmail').checked = !!ft.email;
      document.getElementById('cFtrPhone').checked = !!ft.phone;
      document.getElementById('cFtrGenDate').checked = !!ft.generated_date;

      var q = cfg.quality || {};
      document.getElementById('cQualLevel').value = q.level || 'medium';
      document.getElementById('cQualCompress').value = q.compression || 'compress';
      document.getElementById('cQualOptimize').checked = !!q.optimize_size;
      document.getElementById('cOrientation').value = cfg.orientation || 'portrait';
      document.getElementById('cPageSize').value = cfg.page_size || 'A4';
      document.getElementById('cCustomSizeRow').style.display = cfg.page_size==='Custom' ? '' : 'none';
      document.getElementById('cCustomW').value = cfg.custom_w_mm || 210;
      document.getElementById('cCustomH').value = cfg.custom_h_mm || 297;
      document.getElementById('cFont').value = cfg.font || 'helvetica';

      var colors = cfg.colors || {};
      ['primary','secondary','accent','background','text','button','border'].forEach(function(k){
        var el = document.getElementById('cColor_' + k);
        if (el && colors[k]) el.value = colors[k];
      });
    }
    alert('Template loaded — review each step and adjust as needed.');
  }

  // ── Save as Template ──────────────────────────────────────────────────
  var saveTplBtn = document.getElementById('cpwSaveTemplateBtn');
  if (saveTplBtn) {
    saveTplBtn.addEventListener('click', function () {
      var name = document.getElementById('cpwTemplateName').value.trim();
      var statusEl = document.getElementById('cpwTemplateStatus');
      if (!name) { alert('Enter a template name.'); return; }
      var fullConfig = collectCustomizeConfig();
      fullConfig.layout = selectedLayout;
      fullConfig.fields = getCheckedFields();

      var body = new URLSearchParams();
      body.set('action', 'save_catalog_template');
      body.set('name', name);
      body.set('config', JSON.stringify(fullConfig));
      body.set('csrf_token', csrfToken);

      statusEl.textContent = 'Saving…'; statusEl.style.color = 'var(--admin-text3,var(--text3))';
      fetch('index.php?page=catalog_pdf_wizard', { method:'POST', body: body, headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (d.success) { statusEl.style.color='var(--success,#3D8B6E)'; statusEl.textContent='Saved ✓'; }
          else { statusEl.style.color='var(--danger,#E84040)'; statusEl.textContent='Error: '+(d.error||''); }
        })
        .catch(function(e){ statusEl.style.color='var(--danger,#E84040)'; statusEl.textContent='Failed: '+e.message; });
    });
  }

  // ── Init: preload existing selected products (if editing draft) then search ─
  if (existingIds.length) {
    var params = new URLSearchParams({ ajax_pdf_products: '1', q: '', cat: '' });
    fetch('index.php?page=catalog_pdf_wizard&' + params)
      .then(function(r){return r.json();})
      .then(function(d){ (d.products||[]).forEach(function(p){ productMap[p.id]=p; }); loadProducts(); });
  } else {
    loadProducts();
  }
  updateCounter();
})();
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>