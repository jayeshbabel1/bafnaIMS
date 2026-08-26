<?php
/**
 * admin/views/catalog_pdf_settings.php — Fire 8: default config editor
 * Same field set as wizard Step 3+4, saved as catalog_pdf_settings defaults.
 */
requireAdminPermission('catalog.settings');
require_once BASE_PATH . '/includes/catalog_pdf.php';

// ── AJAX save ────────────────────────────────────────────────────────────
if (!empty($_POST) && ($_POST['action'] ?? '') === 'save_catalog_pdf_defaults') {
    header('Content-Type: application/json');
    requireAdminPermissionJson('catalog.settings');
    csrfVerify(true);
    $config = json_decode($_POST['config'] ?? '{}', true);
    if (!is_array($config)) { echo json_encode(['success'=>false,'error'=>'Invalid payload.']); exit; }
    $merged = array_replace_recursive(catalogPdfDefaultConfig(), $config);
    saveCatalogPdfSettingsDefaults($merged);
    echo json_encode(['success' => true]);
    exit;
}

$adminTitle = 'Catalog PDF Settings';
include __DIR__ . '/../_layout_top.php';

$cfg = getCatalogPdfSettingsDefaults();
?>
<style>
.cps-tabs{display:flex;gap:0;border-bottom:2px solid var(--admin-table-border,var(--border));margin-bottom:18px;overflow-x:auto;}
.cps-tab{padding:9px 16px;font-size:12.5px;font-weight:600;color:var(--admin-text3,var(--text3));border-bottom:2px solid transparent;margin-bottom:-2px;cursor:pointer;background:none;border-top:none;border-left:none;border-right:none;font-family:inherit;white-space:nowrap;}
.cps-tab.active{color:var(--admin-accent,var(--accent));border-bottom-color:var(--admin-accent,var(--accent));}
.cps-panel{display:none;}
.cps-panel.active{display:block;}
.cps-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--admin-surface2,var(--surface2));flex-wrap:wrap;gap:8px;}
.cps-row:last-child{border-bottom:none;}
.cps-label{font-size:13px;color:var(--admin-text2,var(--text2));min-width:180px;}
.cps-control{display:flex;align-items:center;gap:8px;flex:1;min-width:200px;}
.cps-control input[type=text],.cps-control input[type=number],.cps-control select,.cps-control textarea{flex:1;}
.cps-color-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;}
.cps-color-item{display:flex;align-items:center;gap:8px;}
.cps-color-item input[type=color]{width:38px;height:32px;padding:2px;border-radius:6px;border:1px solid var(--admin-table-border,var(--border));cursor:pointer;}
.cps-layout-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-bottom:20px;}
.cps-layout-card{border:1.5px solid var(--admin-table-border,var(--border));border-radius:10px;padding:12px;cursor:pointer;text-align:center;background:var(--admin-card-bg,var(--surface));}
.cps-layout-card.selected{border-color:var(--admin-accent,var(--accent));background:var(--admin-accent-light,var(--accent-light));}
.cps-fields-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;margin-bottom:20px;}
.cps-field-item{display:flex;align-items:center;gap:8px;padding:8px 12px;border:1.5px solid var(--admin-table-border,var(--border));border-radius:8px;background:var(--admin-card-bg,var(--surface));cursor:pointer;font-size:12.5px;}
.cps-save-bar{position:sticky;bottom:0;background:var(--admin-bg,var(--bg));padding:14px 0;border-top:1px solid var(--admin-table-border,var(--border));margin-top:20px;display:flex;align-items:center;gap:12px;}
</style>

<div class="cps-tabs">
  <button class="cps-tab active" data-tab="layout">Layout &amp; Fields</button>
  <button class="cps-tab" data-tab="cover">Cover Page</button>
  <button class="cps-tab" data-tab="closing">Closing Page</button>
  <button class="cps-tab" data-tab="watermark">Watermark</button>
  <button class="cps-tab" data-tab="headerfooter">Header/Footer</button>
  <button class="cps-tab" data-tab="quality">Quality &amp; Format</button>
  <button class="cps-tab" data-tab="fontcolor">Font &amp; Colors</button>
  <button class="cps-tab" data-tab="email">Email Share</button>
  <button class="cps-tab" data-tab="limits">Limits</button>
</div>

<!-- Layout & Fields -->
<div class="cps-panel active" id="cps-layout">
  <p class="admin-form-section-title" style="border:none;padding:0;">Default Layout</p>
  <div class="cps-layout-grid" id="cpsLayoutGrid">
    <?php foreach (['one_per_page'=>'One Per Page','two_per_page'=>'Two Per Page','four_per_page'=>'Four Per Page','grid'=>'Grid Layout','architect'=>'Architect Style'] as $lk=>$ll): ?>
    <div class="cps-layout-card<?= $cfg['layout']===$lk?' selected':'' ?>" data-layout="<?= $lk ?>"><?= icon('grid',22) ?><p style="font-size:12px;font-weight:700;margin-top:6px;"><?= $ll ?></p></div>
    <?php endforeach; ?>
  </div>
  <p class="admin-form-section-title" style="border:none;padding:0;">Default Product Fields</p>
  <div class="cps-fields-grid">
    <?php foreach ([
        'name'=>'Product Name','category'=>'Category','subcategory'=>'Subcategory','color_subcategory'=>'Color',
        'thickness'=>'Thickness','sizes'=>'Useable Size','cutter_size'=>'Italian Size','origin'=>'Origin',
        'finish'=>'Finish','quantity_available'=>'Available Qty','quarry_number'=>'Quarry Number','description'=>'Description',
    ] as $fk=>$fl): ?>
    <label class="cps-field-item"><input type="checkbox" name="pdf_field_default" value="<?= $fk ?>" <?= in_array($fk,$cfg['fields'],true)?'checked':'' ?>/><span><?= $fl ?></span></label>
    <?php endforeach; ?>
  </div>
</div>

<!-- Cover -->
<div class="cps-panel" id="cps-cover">
  <div class="cps-row"><span class="cps-label">Show Company Logo</span><div class="cps-control"><input type="checkbox" id="sCoverLogo" <?= !empty($cfg['cover']['logo'])?'checked':'' ?>/></div></div>
  <div class="cps-row"><span class="cps-label">Title</span><div class="cps-control"><input type="text" id="sCoverTitle" class="admin-input" value="<?= h($cfg['cover']['title']) ?>"/></div></div>
  <div class="cps-row"><span class="cps-label">Subtitle</span><div class="cps-control"><input type="text" id="sCoverSubtitle" class="admin-input" value="<?= h($cfg['cover']['subtitle']) ?>"/></div></div>
  <div class="cps-row"><span class="cps-label">Show Date</span><div class="cps-control">
    <input type="checkbox" id="sCoverDate" <?= !empty($cfg['cover']['show_date'])?'checked':'' ?>/>
    <select id="sCoverDateFormat" class="admin-input admin-select" style="max-width:160px;">
      <option value="d M Y" <?= $cfg['cover']['date_format']==='d M Y'?'selected':'' ?>>31 Dec 2026</option>
      <option value="M Y" <?= $cfg['cover']['date_format']==='M Y'?'selected':'' ?>>Dec 2026</option>
      <option value="d/m/Y" <?= $cfg['cover']['date_format']==='d/m/Y'?'selected':'' ?>>31/12/2026</option>
    </select>
  </div></div>
  <div class="cps-row"><span class="cps-label">Version</span><div class="cps-control"><input type="text" id="sCoverVersion" class="admin-input" style="max-width:120px;" value="<?= h($cfg['cover']['version']) ?>"/></div></div>
  <div class="cps-row"><span class="cps-label">Marketing Message</span><div class="cps-control"><textarea id="sCoverMsg" class="admin-input" rows="2"><?= h($cfg['cover']['marketing_message']) ?></textarea></div></div>
  <div class="cps-row"><span class="cps-label">Show Contact Details</span><div class="cps-control"><input type="checkbox" id="sCoverContact" <?= !empty($cfg['cover']['contact_details'])?'checked':'' ?>/></div></div>
  <div class="cps-row"><span class="cps-label">Footer Text</span><div class="cps-control"><input type="text" id="sCoverFooter" class="admin-input" value="<?= h($cfg['cover']['footer_text']) ?>"/></div></div>
</div>

<!-- Closing -->
<div class="cps-panel" id="cps-closing">
  <div class="cps-row"><span class="cps-label">Enable Closing Page</span><div class="cps-control"><input type="checkbox" id="sClosingEnabled" <?= !empty($cfg['closing']['enabled'])?'checked':'' ?>/></div></div>
  <div class="cps-row"><span class="cps-label">Thank You Text</span><div class="cps-control"><textarea id="sClosingText" class="admin-input" rows="2"><?= h($cfg['closing']['thank_you_text']) ?></textarea></div></div>
  <div class="cps-row"><span class="cps-label">Contact Information</span><div class="cps-control"><input type="checkbox" id="sClosingContact" <?= !empty($cfg['closing']['contact_info'])?'checked':'' ?>/></div></div>
  <div class="cps-row"><span class="cps-label">Website QR Code</span><div class="cps-control"><input type="checkbox" id="sClosingWebQr" <?= !empty($cfg['closing']['website_qr'])?'checked':'' ?>/></div></div>
  <div class="cps-row"><span class="cps-label">Google Map QR Code</span><div class="cps-control"><input type="checkbox" id="sClosingMapQr" <?= !empty($cfg['closing']['gmap_qr'])?'checked':'' ?>/></div></div>
  <div class="cps-row"><span class="cps-label">Social Media</span><div class="cps-control"><input type="checkbox" id="sClosingSocial" <?= !empty($cfg['closing']['social_media'])?'checked':'' ?>/></div></div>
  <div class="cps-row"><span class="cps-label">Sales Team Details</span><div class="cps-control"><input type="checkbox" id="sClosingSales" <?= !empty($cfg['closing']['sales_team'])?'checked':'' ?>/></div></div>
</div>

<!-- Watermark -->
<div class="cps-panel" id="cps-watermark">
  <div class="cps-row"><span class="cps-label">Watermark Type</span><div class="cps-control">
    <select id="sWmType" class="admin-input admin-select">
      <?php foreach (['none'=>'No Watermark','logo'=>'Company Logo','confidential'=>'CONFIDENTIAL','sample'=>'SAMPLE','custom'=>'Custom Text'] as $wk=>$wl): ?>
      <option value="<?= $wk ?>" <?= $cfg['watermark']['type']===$wk?'selected':'' ?>><?= $wl ?></option>
      <?php endforeach; ?>
    </select>
  </div></div>
  <div class="cps-row" id="sWmCustomRow" style="<?= $cfg['watermark']['type']!=='custom'?'display:none;':'' ?>"><span class="cps-label">Custom Text</span><div class="cps-control"><input type="text" id="sWmCustomText" class="admin-input" value="<?= h($cfg['watermark']['custom_text']) ?>"/></div></div>
  <div class="cps-row"><span class="cps-label">Opacity (%)</span><div class="cps-control"><input type="range" id="sWmOpacity" min="0" max="100" value="<?= (int)$cfg['watermark']['opacity'] ?>"/><span id="sWmOpacityVal"><?= (int)$cfg['watermark']['opacity'] ?></span>%</div></div>
  <div class="cps-row"><span class="cps-label">Rotation (deg)</span><div class="cps-control"><input type="range" id="sWmRotation" min="-90" max="90" value="<?= (int)$cfg['watermark']['rotation'] ?>"/><span id="sWmRotationVal"><?= (int)$cfg['watermark']['rotation'] ?></span>°</div></div>
</div>

<!-- Header/Footer -->
<div class="cps-panel" id="cps-headerfooter">
  <p style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--admin-text3,var(--text3));margin:8px 0;">Header</p>
  <div class="cps-row"><span class="cps-label">Company Logo</span><div class="cps-control"><input type="checkbox" id="sHdrLogo" <?= !empty($cfg['header']['logo'])?'checked':'' ?>/></div></div>
  <div class="cps-row"><span class="cps-label">Catalog Name</span><div class="cps-control"><input type="checkbox" id="sHdrName" <?= !empty($cfg['header']['catalog_name'])?'checked':'' ?>/></div></div>
  <div class="cps-row"><span class="cps-label">Page Title</span><div class="cps-control"><input type="checkbox" id="sHdrTitle" <?= !empty($cfg['header']['page_title'])?'checked':'' ?>/></div></div>
  <p style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--admin-text3,var(--text3));margin:16px 0 8px;">Footer</p>
  <div class="cps-row"><span class="cps-label">Page Number</span><div class="cps-control">
    <input type="checkbox" id="sFtrPageNum" <?= !empty($cfg['footer']['page_number'])?'checked':'' ?>/>
    <select id="sFtrPageNumPos" class="admin-input admin-select" style="max-width:160px;">
      <?php foreach (['bottom_left'=>'Bottom Left','bottom_center'=>'Bottom Center','bottom_right'=>'Bottom Right','top_left'=>'Top Left','top_right'=>'Top Right'] as $pk=>$pl): ?>
      <option value="<?= $pk ?>" <?= $cfg['page_number_position']===$pk?'selected':'' ?>><?= $pl ?></option>
      <?php endforeach; ?>
    </select>
  </div></div>
  <div class="cps-row"><span class="cps-label">Website</span><div class="cps-control"><input type="checkbox" id="sFtrWebsite" <?= !empty($cfg['footer']['website'])?'checked':'' ?>/></div></div>
  <div class="cps-row"><span class="cps-label">Email</span><div class="cps-control"><input type="checkbox" id="sFtrEmail" <?= !empty($cfg['footer']['email'])?'checked':'' ?>/></div></div>
  <div class="cps-row"><span class="cps-label">Phone</span><div class="cps-control"><input type="checkbox" id="sFtrPhone" <?= !empty($cfg['footer']['phone'])?'checked':'' ?>/></div></div>
  <div class="cps-row"><span class="cps-label">Generated Date</span><div class="cps-control"><input type="checkbox" id="sFtrGenDate" <?= !empty($cfg['footer']['generated_date'])?'checked':'' ?>/></div></div>
</div>

<!-- Quality & Format -->
<div class="cps-panel" id="cps-quality">
  <div class="cps-row"><span class="cps-label">PDF Quality</span><div class="cps-control">
    <select id="sQualLevel" class="admin-input admin-select">
      <?php foreach (['low'=>'Low','medium'=>'Medium','high'=>'High','print'=>'Print Quality'] as $qk=>$ql): ?>
      <option value="<?= $qk ?>" <?= $cfg['quality']['level']===$qk?'selected':'' ?>><?= $ql ?></option>
      <?php endforeach; ?>
    </select>
  </div></div>
  <div class="cps-row"><span class="cps-label">Compression</span><div class="cps-control">
    <select id="sQualCompress" class="admin-input admin-select">
      <option value="compress" <?= $cfg['quality']['compression']==='compress'?'selected':'' ?>>Compressed</option>
      <option value="none" <?= $cfg['quality']['compression']==='none'?'selected':'' ?>>No Compression</option>
    </select>
  </div></div>
  <div class="cps-row"><span class="cps-label">Optimize Image Size</span><div class="cps-control"><input type="checkbox" id="sQualOptimize" <?= !empty($cfg['quality']['optimize_size'])?'checked':'' ?>/></div></div>
  <div class="cps-row"><span class="cps-label">Orientation</span><div class="cps-control">
    <select id="sOrientation" class="admin-input admin-select">
      <option value="portrait" <?= $cfg['orientation']==='portrait'?'selected':'' ?>>Portrait</option>
      <option value="landscape" <?= $cfg['orientation']==='landscape'?'selected':'' ?>>Landscape</option>
    </select>
  </div></div>
  <div class="cps-row"><span class="cps-label">Page Size</span><div class="cps-control">
    <select id="sPageSize" class="admin-input admin-select">
      <?php foreach (['A4','A3','Letter','Legal','Custom'] as $ps): ?>
      <option value="<?= $ps ?>" <?= $cfg['page_size']===$ps?'selected':'' ?>><?= $ps ?></option>
      <?php endforeach; ?>
    </select>
  </div></div>
  <div class="cps-row" id="sCustomSizeRow" style="<?= $cfg['page_size']!=='Custom'?'display:none;':'' ?>">
    <span class="cps-label">Custom Size (mm)</span>
    <div class="cps-control">
      <input type="number" id="sCustomW" class="admin-input" style="max-width:90px;" value="<?= (float)$cfg['custom_w_mm'] ?>" placeholder="Width"/>
      <input type="number" id="sCustomH" class="admin-input" style="max-width:90px;" value="<?= (float)$cfg['custom_h_mm'] ?>" placeholder="Height"/>
    </div>
  </div>
</div>

<!-- Font & Colors -->
<div class="cps-panel" id="cps-fontcolor">
  <div class="cps-row"><span class="cps-label">Font Family</span><div class="cps-control">
    <select id="sFont" class="admin-input admin-select">
            <?php foreach (['helvetica'=>'Helvetica','arial'=>'Arial','roboto'=>'Roboto','open_sans'=>'Open Sans','noto_sans'=>'Noto Sans','bodoni72'=>'Bodoni 72'] as $fk=>$fl): ?>
      <option value="<?= $fk ?>" <?= $cfg['font']===$fk?'selected':'' ?>><?= $fl ?></option>
      <?php endforeach; ?>
    </select>
  </div></div>
  <p style="font-size:12px;color:var(--admin-text3,var(--text3));margin:14px 0 10px;">Default brand colors — used as the starting point for every new catalog.</p>
  <div class="cps-color-grid">
    <?php foreach (['primary'=>'Primary','secondary'=>'Secondary','accent'=>'Accent','background'=>'Background','text'=>'Text','button'=>'Button','border'=>'Border'] as $ck=>$cl): ?>
    <div class="cps-color-item"><input type="color" id="sColor_<?= $ck ?>" value="<?= h($cfg['colors'][$ck] ?? '#000000') ?>"/><span style="font-size:12px;"><?= $cl ?></span></div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Email Share -->
<div class="cps-panel" id="cps-email">
  <div class="cps-row"><span class="cps-label">Default Subject</span><div class="cps-control"><input type="text" id="sEmailSubject" class="admin-input" value="<?= h($cfg['email_share']['default_subject']) ?>"/></div></div>
  <div class="cps-row"><span class="cps-label">Default Message</span><div class="cps-control"><textarea id="sEmailMessage" class="admin-input" rows="4"><?= h($cfg['email_share']['default_message']) ?></textarea></div></div>
</div>

<!-- Limits -->
<div class="cps-panel" id="cps-limits">
  <div class="cps-row"><span class="cps-label">Max Products Per Catalog<small>0 = unlimited</small></span><div class="cps-control"><input type="number" id="sMaxProducts" class="admin-input" min="0" value="<?= (int)($cfg['max_products'] ?? 0) ?>"/></div></div>
  <div class="cps-row"><span class="cps-label">Max PDF Size (MB)<small>0 = unlimited (warn only, not enforced yet)</small></span><div class="cps-control"><input type="number" id="sMaxSizeMb" class="admin-input" min="0" value="<?= (int)($cfg['max_size_mb'] ?? 0) ?>"/></div></div>
  <div class="cps-row"><span class="cps-label">Auto-Delete Temp PDFs After (days)</span><div class="cps-control"><input type="number" id="sRetentionDays" class="admin-input" min="0" value="<?= (int)($cfg['retention_days'] ?? 90) ?>"/></div></div>
</div>

<div class="cps-save-bar">
  <button type="button" class="btn-admin-primary" id="cpsSaveBtn"><?= icon('check',15) ?> Save Defaults</button>
  <span id="cpsSaveStatus" style="font-size:12px;"></span>
</div>

<script>
document.querySelectorAll('.cps-tab').forEach(function(tab){
  tab.addEventListener('click', function(){
    document.querySelectorAll('.cps-tab').forEach(function(t){t.classList.remove('active');});
    document.querySelectorAll('.cps-panel').forEach(function(p){p.classList.remove('active');});
    tab.classList.add('active');
    document.getElementById('cps-'+tab.dataset.tab).classList.add('active');
  });
});
document.querySelectorAll('.cps-layout-card').forEach(function(c){
  c.addEventListener('click', function(){
    document.querySelectorAll('.cps-layout-card').forEach(function(x){x.classList.remove('selected');});
    c.classList.add('selected');
  });
});
document.getElementById('sWmType').addEventListener('change', function(){
  document.getElementById('sWmCustomRow').style.display = this.value === 'custom' ? '' : 'none';
});
document.getElementById('sPageSize').addEventListener('change', function(){
  document.getElementById('sCustomSizeRow').style.display = this.value === 'Custom' ? '' : 'none';
});
document.getElementById('sWmOpacity').addEventListener('input', function(){ document.getElementById('sWmOpacityVal').textContent = this.value; });
document.getElementById('sWmRotation').addEventListener('input', function(){ document.getElementById('sWmRotationVal').textContent = this.value; });

document.getElementById('cpsSaveBtn').addEventListener('click', function(){
  var selectedLayout = document.querySelector('.cps-layout-card.selected')?.dataset.layout || 'one_per_page';
  var fields = Array.from(document.querySelectorAll('input[name="pdf_field_default"]:checked')).map(function(el){return el.value;});

  var config = {
    layout: selectedLayout,
    fields: fields,
    cover: {
      logo: document.getElementById('sCoverLogo').checked?1:0,
      title: document.getElementById('sCoverTitle').value,
      subtitle: document.getElementById('sCoverSubtitle').value,
      show_date: document.getElementById('sCoverDate').checked?1:0,
      date_format: document.getElementById('sCoverDateFormat').value,
      version: document.getElementById('sCoverVersion').value,
      marketing_message: document.getElementById('sCoverMsg').value,
      contact_details: document.getElementById('sCoverContact').checked?1:0,
      footer_text: document.getElementById('sCoverFooter').value,
    },
    closing: {
      enabled: document.getElementById('sClosingEnabled').checked?1:0,
      thank_you_text: document.getElementById('sClosingText').value,
      contact_info: document.getElementById('sClosingContact').checked?1:0,
      website_qr: document.getElementById('sClosingWebQr').checked?1:0,
      gmap_qr: document.getElementById('sClosingMapQr').checked?1:0,
      social_media: document.getElementById('sClosingSocial').checked?1:0,
      sales_team: document.getElementById('sClosingSales').checked?1:0,
    },
    watermark: {
      type: document.getElementById('sWmType').value,
      custom_text: document.getElementById('sWmCustomText').value,
      opacity: parseInt(document.getElementById('sWmOpacity').value,10),
      rotation: parseInt(document.getElementById('sWmRotation').value,10),
    },
    header: {
      logo: document.getElementById('sHdrLogo').checked?1:0,
      catalog_name: document.getElementById('sHdrName').checked?1:0,
      page_title: document.getElementById('sHdrTitle').checked?1:0,
    },
    footer: {
      page_number: document.getElementById('sFtrPageNum').checked?1:0,
      website: document.getElementById('sFtrWebsite').checked?1:0,
      email: document.getElementById('sFtrEmail').checked?1:0,
      phone: document.getElementById('sFtrPhone').checked?1:0,
      generated_date: document.getElementById('sFtrGenDate').checked?1:0,
    },
    page_number_position: document.getElementById('sFtrPageNumPos').value,
    quality: {
      level: document.getElementById('sQualLevel').value,
      compression: document.getElementById('sQualCompress').value,
      optimize_size: document.getElementById('sQualOptimize').checked?1:0,
    },
    orientation: document.getElementById('sOrientation').value,
    page_size: document.getElementById('sPageSize').value,
    custom_w_mm: parseFloat(document.getElementById('sCustomW').value)||210,
    custom_h_mm: parseFloat(document.getElementById('sCustomH').value)||297,
    font: document.getElementById('sFont').value,
    colors: {
      primary: document.getElementById('sColor_primary').value,
      secondary: document.getElementById('sColor_secondary').value,
      accent: document.getElementById('sColor_accent').value,
      background: document.getElementById('sColor_background').value,
      text: document.getElementById('sColor_text').value,
      button: document.getElementById('sColor_button').value,
      border: document.getElementById('sColor_border').value,
    },
    email_share: {
      default_subject: document.getElementById('sEmailSubject').value,
      default_message: document.getElementById('sEmailMessage').value,
    },
    max_products: parseInt(document.getElementById('sMaxProducts').value,10)||0,
    max_size_mb: parseInt(document.getElementById('sMaxSizeMb').value,10)||0,
    retention_days: parseInt(document.getElementById('sRetentionDays').value,10)||90,
  };

  var statusEl = document.getElementById('cpsSaveStatus');
  statusEl.textContent = 'Saving…'; statusEl.style.color = 'var(--admin-text3,var(--text3))';
  var body = new URLSearchParams();
  body.set('action','save_catalog_pdf_defaults');
  body.set('config', JSON.stringify(config));
  body.set('csrf_token', <?= json_encode(csrfToken()) ?>);
  fetch('index.php?page=catalog_pdf_settings', {method:'POST', body:body, headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(function(r){return r.json();})
    .then(function(d){
      if (d.success) { statusEl.style.color='var(--success,#3D8B6E)'; statusEl.textContent='Saved ✓'; }
      else { statusEl.style.color='var(--danger,#E84040)'; statusEl.textContent='Error: '+(d.error||''); }
      setTimeout(function(){statusEl.textContent='';}, 3000);
    })
    .catch(function(e){ statusEl.style.color='var(--danger,#E84040)'; statusEl.textContent='Failed: '+e.message; });
});
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>