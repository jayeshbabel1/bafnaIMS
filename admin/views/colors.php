<?php
/**
 * PATCH 03 — admin/views/colors.php
 *
 * FULL FILE REPLACEMENT.
 * Extended Color Scheme + Theme Settings page.
 */
$adminTitle = 'Color Scheme & Theme';
include __DIR__ . '/../_layout_top.php';
$defaults = require __DIR__ . '/../../config/colors.php';
$db = getDB();
$rows = $db->query("SELECT `key`,`value` FROM settings WHERE `key` LIKE '--%'")->fetchAll();
$saved = array_column($rows, 'value', 'key');
$current = array_merge($defaults, $saved);

//  Grouped colour swatches — USER panel 
$colorGroups = [
  'Background & Surfaces' => ['--bg','--surface','--surface2','--surface3'],
  'Accent Colors'         => ['--accent','--accent2','--accent-light','--accent-mid'],
  'Text Colors'           => ['--text','--text2','--text3'],
  'Borders & Stones'      => ['--border','--stone','--stone-dark'],
  'Status Colors'         => ['--success','--success-bg','--danger','--danger-bg'],
  'Highlights'            => ['--gold','--gold-bg','--nav-bg','--topbar-bg'],
];
 
//  Grouped colour swatches — ADMIN panel 
$adminColorGroups = [
  'Admin Backgrounds'     => ['--admin-bg','--admin-surface','--admin-surface2','--admin-surface3'],
  'Admin Sidebar'         => ['--admin-sidebar-from','--admin-sidebar-to',
                              '--admin-sidebar-text','--admin-sidebar-active',
                              '--admin-sidebar-hover','--admin-sidebar-border'],
  'Admin Topbar'          => ['--admin-topbar-bg','--admin-topbar-border','--admin-topbar-text'],
  'Admin Accent'          => ['--admin-accent','--admin-accent2',
                              '--admin-accent-light','--admin-accent-mid'],
  'Admin Table'           => ['--admin-table-header-bg','--admin-table-row-hover',
                              '--admin-table-border'],
  'Admin Cards'           => ['--admin-card-bg','--admin-card-border','--admin-card-radius'],
];

//  Font options 
$googleFonts = [
  'Plus Jakarta Sans'  => "'Plus Jakarta Sans', sans-serif",
  'DM Sans'            => "'DM Sans', sans-serif",
  'Inter'              => "'Inter', sans-serif",
  'Roboto'             => "'Roboto', sans-serif",
  'Lato'               => "'Lato', sans-serif",
  'Poppins'            => "'Poppins', sans-serif",
  'Nunito'             => "'Nunito', sans-serif",
  'Raleway'            => "'Raleway', sans-serif",
  'Montserrat'         => "'Montserrat', sans-serif",
  'Source Sans 3'      => "'Source Sans 3', sans-serif",
  'Work Sans'          => "'Work Sans', sans-serif",
  'Manrope'            => "'Manrope', sans-serif",
  'Outfit'             => "'Outfit', sans-serif",
  'Figtree'            => "'Figtree', sans-serif",
  'Geist'              => "'Geist', sans-serif",
];

// Helper: strip CSS value to hex for colour pickers
function toHex(string $v): string {
    $v = trim($v);
    if (preg_match('/^#[0-9a-f]{3,8}$/i', $v)) return $v;
    return '#cccccc';
}

// Get font family label from value
function fontLabel(string $val, array $map): string {
    foreach ($map as $label => $css) {
        if (trim($css, "'\" ") === trim($val, "'\" ") || $css === $val) return $label;
    }
    // Try matching family name
    preg_match("/['\"]?([A-Za-z][A-Za-z0-9 ]+)['\"]?/", $val, $m);
    return trim($m[1] ?? 'Plus Jakarta Sans');
}
?>

<style>
.theme-tabs{display:flex;gap:0;border-bottom:2px solid var(--border);margin-bottom:24px;overflow-x:auto;}
.theme-tab{padding:10px 20px;font-size:13px;font-weight:600;border-bottom:2px solid transparent;margin-bottom:-2px;color:var(--text3);cursor:pointer;white-space:nowrap;background:none;border-top:none;border-left:none;border-right:none;font-family:inherit;}
.theme-tab.active{border-bottom-color:var(--accent);color:var(--accent);}
.theme-panel{display:none;}
.theme-panel.active{display:block;}

/* Color swatches */
.color-swatch-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;}
.color-swatch-item{display:flex;align-items:center;gap:10px;}
.color-preview{width:36px;height:36px;border-radius:8px;border:1px solid var(--border);flex-shrink:0;cursor:pointer;transition:transform .15s;}
.color-preview:hover{transform:scale(1.1);}
.color-swatch-info{flex:1;}
.color-swatch-name{font-size:11px;font-weight:600;color:var(--text2);margin-bottom:4px;}

/* New: row inputs for non-colour settings */
.theme-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--surface2);flex-wrap:wrap;gap:8px;}
.theme-row:last-child{border-bottom:none;}
.theme-row-label{font-size:13px;color:var(--text2);min-width:180px;}
.theme-row-label small{display:block;font-size:11px;color:var(--text3);margin-top:1px;}
.theme-row-control{display:flex;align-items:center;gap:8px;}

/* Font preview card */
.font-preview-card{background:var(--surface2);border-radius:10px;padding:14px 18px;margin-top:6px;font-size:18px;color:var(--text);line-height:1.4;transition:font-family .3s;}

/* Section divider inside panel */
.theme-sub-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin:20px 0 12px;padding-bottom:6px;border-bottom:1px solid var(--border);}

/* Live preview bar */
#colorPreview{display:flex;gap:8px;flex-wrap:wrap;padding:16px;background:var(--bg);border-radius:10px;border:1px solid var(--border);margin-bottom:20px;}
</style>

<!-- Top action row -->
<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">
  <button type="button" onclick="applyPreview()" class="btn-admin-secondary">
    <?= icon('eye',14) ?> Preview Live
  </button>
  <form method="POST" action="index.php" style="display:inline;">
    <input type="hidden" name="action" value="reset_colors"/>
    <button type="submit" class="btn-admin-secondary"
            data-confirm="Reset ALL theme settings to defaults?">
      <?= icon('refresh',14) ?> Reset to Defaults
    </button>
  </form>
</div>

<!-- Live Preview Strip -->
<div class="admin-form-section" style="margin-bottom:20px;">
  <p class="admin-form-section-title">Live Preview</p>
  <div id="colorPreview">
    <div style="padding:8px 16px;background:var(--btn-bg,var(--accent));color:var(--btn-color,#fff);border-radius:var(--btn-radius,8px);font-size:12px;font-weight:600;">Primary Button</div>
    <div style="padding:8px 16px;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:8px;font-size:12px;">Surface Card</div>
    <div style="padding:8px 14px;background:var(--input-bg,#fff);border:1.5px solid var(--input-border,var(--border));border-radius:var(--input-radius,10px);font-size:12px;color:var(--input-color,var(--text));">Input Field</div>
    <span class="badge badge-green">In Stock</span>
    <span class="badge badge-gold">★ Featured</span>
    <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-mid));"></div>
  </div>
</div>

<!-- Tab navigation -->
<div class="theme-tabs">
  <button class="theme-tab active" onclick="switchTab('admin-colors')">🖥️ Admin Colors</button>
  <button class="theme-tab" onclick="switchTab('admin-components')">🧩 Admin Components</button>
  <button class="theme-tab" onclick="switchTab('colors')">🎨 User Colors</button>
  <button class="theme-tab" onclick="switchTab('buttons')">🔲 Buttons</button>
  <button class="theme-tab" onclick="switchTab('inputs')">📝 Inputs</button>
  <button class="theme-tab" onclick="switchTab('labels')">🏷 Labels</button>
  <button class="theme-tab" onclick="switchTab('navbar')">≡ Navbar</button>
  <button class="theme-tab" onclick="switchTab('fonts')">🔤 Fonts</button>
  <button class="theme-tab" onclick="switchTab('radius')">▢ Radius</button>
</div>

<form method="POST" action="index.php" id="colorForm">
  <input type="hidden" name="action" value="save_colors"/>
   
  <!-- ══ TAB: Admin Colors ═════════════════════════════════════════════ -->
  <div class="theme-panel" id="panel-admin-colors">
 
    <div style="background:var(--accent-light);border:1px solid var(--border);
                border-radius:10px;padding:12px 16px;margin-bottom:20px;
                display:flex;gap:10px;align-items:flex-start;">
      <?= icon('info',14) ?>
      <p style="font-size:12px;color:var(--text2);line-height:1.6;margin:0;">
        These variables style the <strong>Admin Panel only</strong> — sidebar, topbar,
        tables, and cards. User-facing pages are not affected.
        Changes take effect on next page load.
      </p>
    </div>
 
    <!-- Live admin preview strip -->
    <div class="admin-form-section" style="margin-bottom:20px;">
      <p class="admin-form-section-title">Admin Preview</p>
      <div style="border-radius:10px;overflow:hidden;border:1px solid var(--border);">
 
        <!-- Mini sidebar -->
        <div style="display:flex;height:120px;">
          <div id="adminPreviewSidebar"
               style="width:120px;flex-shrink:0;
                      background:linear-gradient(180deg,var(--admin-sidebar-from,#1A4D65),var(--admin-sidebar-to,#0D2E3D));
                      padding:10px 8px;display:flex;flex-direction:column;gap:4px;">
            <div style="height:8px;width:60%;border-radius:4px;
                        background:var(--admin-sidebar-text,rgba(255,255,255,.8));
                        opacity:.9;margin-bottom:6px;"></div>
            <?php foreach(['Dashboard','Products','Users','Settings'] as $item): ?>
            <div style="height:6px;border-radius:3px;
                        background:var(--admin-sidebar-text,rgba(255,255,255,.7));
                        opacity:.45;width:<?= rand(50,85) ?>%;"></div>
            <?php endforeach; ?>
          </div>
          <!-- Mini topbar + content -->
          <div style="flex:1;display:flex;flex-direction:column;
                      background:var(--admin-bg,#F2F5F9);">
            <div id="adminPreviewTopbar"
                 style="height:28px;background:var(--admin-topbar-bg,#fff);
                        border-bottom:1px solid var(--admin-topbar-border,#DDE4EB);
                        display:flex;align-items:center;padding:0 10px;gap:6px;">
              <div style="height:6px;width:60px;border-radius:3px;
                          background:var(--admin-topbar-text,#1A2837);opacity:.7;"></div>
              <div style="margin-left:auto;height:6px;width:30px;border-radius:3px;
                          background:var(--admin-accent,#2C6E8A);opacity:.8;"></div>
            </div>
            <div style="padding:8px;display:flex;gap:6px;flex-wrap:wrap;">
              <div style="height:8px;width:40%;border-radius:3px;
                          background:var(--admin-card-bg,#fff);
                          border:1px solid var(--admin-card-border,#DDE4EB);"></div>
              <div style="height:8px;width:30%;border-radius:3px;
                          background:var(--admin-accent-light,#E3EFF4);"></div>
            </div>
          </div>
        </div>
 
      </div>
    </div>
 
    <?php foreach ($adminColorGroups as $groupName => $keys): ?>
    <div class="admin-form-section">
      <p class="admin-form-section-title"><?= h($groupName) ?></p>
      <div class="color-swatch-grid">
        <?php foreach ($keys as $key):
          $val   = $current[$key] ?? '#cccccc';
          $label = ltrim($key, '-');
          // For non-hex values (rgba, gradient keywords) skip the hex swatch
          $isPlainHex = preg_match('/^#[0-9a-f]{3,8}$/i', trim($val));
          $swatchColor = $isPlainHex ? $val : '#aaaaaa';
        ?>
        <div class="color-swatch-item">
          <?php if ($isPlainHex): ?>
          <div class="color-preview" style="background:<?= h($swatchColor) ?>;"
               title="<?= h($key) ?>"></div>
          <?php else: ?>
          <div style="width:36px;height:36px;border-radius:8px;
                      border:1px solid var(--border);background:var(--surface2);
                      flex-shrink:0;display:flex;align-items:center;
                      justify-content:center;font-size:9px;color:var(--text3);">css</div>
          <?php endif; ?>
          <div class="color-swatch-info">
            <div class="color-swatch-name"><?= h($label) ?></div>
            <input type="text" name="<?= h($key) ?>"
                   class="admin-input color-sync-input"
                   value="<?= h($val) ?>"
                   style="font-size:11px;padding:4px 8px;font-family:monospace;"
                   data-key="<?= h($key) ?>"
                   data-admin-preview="1"/>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
 
  </div><!-- /#panel-admin-colors -->
   
  <!-- ══ TAB: Admin Components ════════════════════════════════════════ -->
  <div class="theme-panel" id="panel-admin-components">
 
    <?php
    // ── Helper: renders a swatch + text input row inside a section ──────
    // (inline PHP so we can reuse for all component rows)
    function adminCompRow(string $key, string $label, string $type, array $current, string $hint = ''): void {
        $val  = $current[$key] ?? '';
        $isColor = ($type === 'color');
        $isHex   = $isColor && preg_match('/^#[0-9a-f]{3,8}$/i', trim($val));
        ?>
        <div class="theme-row">
          <span class="theme-row-label">
            <?= htmlspecialchars($label) ?>
            <?php if ($hint): ?><small><?= htmlspecialchars($hint) ?></small><?php endif; ?>
          </span>
          <div class="theme-row-control">
            <?php if ($isColor): ?>
              <div class="color-preview color-swatch-item-inline"
                   style="background:<?= htmlspecialchars($isHex ? $val : '#aaaaaa') ?>;"
                   title="<?= htmlspecialchars($key) ?>"></div>
            <?php endif; ?>
            <?php if ($type === 'select-weight'): ?>
              <select name="<?= htmlspecialchars($key) ?>" class="admin-input admin-select"
                      style="width:110px;"
                      onchange="document.documentElement.style.setProperty('<?= $key ?>', this.value)">
                <?php foreach (['300','400','500','600','700','800'] as $w): ?>
                <option value="<?= $w ?>" <?= ($val === $w) ? 'selected' : '' ?>><?= $w ?></option>
                <?php endforeach; ?>
              </select>
            <?php elseif ($type === 'select-transform'): ?>
              <select name="<?= htmlspecialchars($key) ?>" class="admin-input admin-select"
                      style="width:130px;"
                      onchange="document.documentElement.style.setProperty('<?= $key ?>', this.value)">
                <?php foreach (['uppercase','lowercase','capitalize','none'] as $t): ?>
                <option value="<?= $t ?>" <?= ($val === $t) ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="text" name="<?= htmlspecialchars($key) ?>"
                     class="admin-input <?= $isColor ? 'color-sync-input' : '' ?>"
                     value="<?= htmlspecialchars($val) ?>"
                     data-key="<?= htmlspecialchars($key) ?>"
                     style="font-size:12px;padding:5px 10px;font-family:monospace;width:<?= $isColor ? '130px' : '180px' ?>;"/>
            <?php endif; ?>
          </div>
        </div>
        <?php
    }
    ?>
 
    <!-- ── ADMIN TEXT ─────────────────────────────────────────────────── -->
    <div class="admin-form-section">
      <p class="admin-form-section-title">Admin Panel Text</p>
      <p style="font-size:12px;color:var(--text3);margin-bottom:14px;line-height:1.6;">
        Controls text colour throughout the admin panel — titles, table cells, secondary text, and muted captions.
      </p>
      <?php
      adminCompRow('--admin-text',  'Primary Text',   'color', $current, 'Page titles, table data');
      adminCompRow('--admin-text2', 'Secondary Text', 'color', $current, 'Sub-labels, descriptions');
      adminCompRow('--admin-text3', 'Muted Text',     'color', $current, 'Captions, placeholders');
      ?>
      <!-- Live text preview -->
      <div style="background:var(--admin-surface,#fff);border:1px solid var(--admin-table-border,#DDE4EB);
                  border-radius:8px;padding:14px 18px;margin-top:10px;display:flex;flex-direction:column;gap:6px;">
        <p style="font-size:16px;font-weight:700;color:var(--admin-text,#1A2837);">Dashboard — Primary Text</p>
        <p style="font-size:13px;color:var(--admin-text2,#4A6070);">Secondary: sub-label or table cell content</p>
        <p style="font-size:11px;color:var(--admin-text3,#8FA3B1);">Muted: captions, timestamps, badges</p>
      </div>
    </div>
 
    <!-- ── ADMIN LABELS ───────────────────────────────────────────────── -->
    <div class="admin-form-section">
      <p class="admin-form-section-title">Admin Form Labels</p>
      <p style="font-size:12px;color:var(--text3);margin-bottom:14px;line-height:1.6;">
        Controls the appearance of all <code>.admin-label</code> elements in forms.
      </p>
      <?php
      adminCompRow('--admin-label-color',          'Label Color',          'color',            $current, '.admin-label text');
      adminCompRow('--admin-label-font-size',       'Font Size',            'text',             $current, 'e.g. 11px, 12px');
      adminCompRow('--admin-label-font-weight',     'Font Weight',          'select-weight',    $current, '400–800');
      adminCompRow('--admin-label-transform',       'Text Transform',       'select-transform', $current, 'uppercase / none');
      adminCompRow('--admin-label-letter-spacing',  'Letter Spacing',       'text',             $current, 'e.g. 0.4px, 0.6px');
      ?>
      <!-- Live label preview -->
      <div style="background:var(--admin-surface,#fff);border:1px solid var(--admin-table-border,#DDE4EB);
                  border-radius:8px;padding:14px 18px;margin-top:10px;display:flex;flex-direction:column;gap:10px;">
        <label style="display:block;
          color:var(--admin-label-color,#4A6070);
          font-size:var(--admin-label-font-size,11px);
          font-weight:var(--admin-label-font-weight,700);
          text-transform:var(--admin-label-transform,uppercase);
          letter-spacing:var(--admin-label-letter-spacing,0.4px);">
          Product Name
        </label>
        <label style="display:block;
          color:var(--admin-label-color,#4A6070);
          font-size:var(--admin-label-font-size,11px);
          font-weight:var(--admin-label-font-weight,700);
          text-transform:var(--admin-label-transform,uppercase);
          letter-spacing:var(--admin-label-letter-spacing,0.4px);">
          Quarry Number
        </label>
      </div>
    </div>
 
    <!-- ── ADMIN INPUTS ───────────────────────────────────────────────── -->
    <div class="admin-form-section">
      <p class="admin-form-section-title">Admin Text Inputs</p>
      <p style="font-size:12px;color:var(--text3);margin-bottom:14px;line-height:1.6;">
        Applies to all <code>&lt;input&gt;</code> and <code>&lt;select&gt;</code> fields with class <code>.admin-input</code>.
      </p>
      <?php
      adminCompRow('--admin-input-bg',           'Background',       'color', $current);
      adminCompRow('--admin-input-color',         'Text Color',       'color', $current);
      adminCompRow('--admin-input-placeholder',   'Placeholder',      'color', $current);
      adminCompRow('--admin-input-border',        'Border',           'color', $current);
      adminCompRow('--admin-input-hover-border',  'Hover Border',     'color', $current);
      adminCompRow('--admin-input-focus-border',  'Focus Border',     'color', $current);
      adminCompRow('--admin-input-focus-shadow',  'Focus Shadow',     'text',  $current, 'rgba(…) value');
      adminCompRow('--admin-input-radius',        'Border Radius',    'text',  $current, 'e.g. 8px');
      adminCompRow('--admin-input-font-size',     'Font Size',        'text',  $current, 'e.g. 13px');
      ?>
      <!-- Live input preview -->
      <div style="background:var(--admin-surface,#fff);border:1px solid var(--admin-table-border,#DDE4EB);
                  border-radius:8px;padding:14px 18px;margin-top:10px;display:flex;flex-direction:column;gap:10px;max-width:380px;">
        <input type="text" placeholder="Text input preview…" style="
          width:100%;padding:9px 12px;
          background:var(--admin-input-bg,#fff);
          color:var(--admin-input-color,#1A2837);
          border:1.5px solid var(--admin-input-border,#DDE4EB);
          border-radius:var(--admin-input-radius,8px);
          font-size:var(--admin-input-font-size,13px);
          outline:none;font-family:inherit;" readonly/>
        <select style="width:100%;padding:9px 12px;
          background:var(--admin-input-bg,#fff);
          color:var(--admin-input-color,#1A2837);
          border:1.5px solid var(--admin-input-border,#DDE4EB);
          border-radius:var(--admin-input-radius,8px);
          font-size:var(--admin-input-font-size,13px);
          outline:none;font-family:inherit;">
          <option>Select preview…</option>
        </select>
      </div>
    </div>
 
    <!-- ── ADMIN TEXTAREA ─────────────────────────────────────────────── -->
    <div class="admin-form-section">
      <p class="admin-form-section-title">Admin Textarea</p>
      <p style="font-size:12px;color:var(--text3);margin-bottom:14px;line-height:1.6;">
        Applies to all <code>&lt;textarea&gt;</code> fields with class <code>.admin-input</code>.
      </p>
      <?php
      adminCompRow('--admin-textarea-bg',           'Background',     'color', $current);
      adminCompRow('--admin-textarea-color',         'Text Color',     'color', $current);
      adminCompRow('--admin-textarea-border',        'Border',         'color', $current);
      adminCompRow('--admin-textarea-focus-border',  'Focus Border',   'color', $current);
      adminCompRow('--admin-textarea-radius',        'Border Radius',  'text',  $current, 'e.g. 8px');
      ?>
      <!-- Live textarea preview -->
      <div style="background:var(--admin-surface,#fff);border:1px solid var(--admin-table-border,#DDE4EB);
                  border-radius:8px;padding:14px 18px;margin-top:10px;max-width:380px;">
        <textarea rows="3" placeholder="Textarea preview…" style="
          width:100%;padding:9px 12px;resize:none;
          background:var(--admin-textarea-bg,#fff);
          color:var(--admin-textarea-color,#1A2837);
          border:1.5px solid var(--admin-textarea-border,#DDE4EB);
          border-radius:var(--admin-textarea-radius,8px);
          font-size:var(--admin-input-font-size,13px);
          outline:none;font-family:inherit;" readonly></textarea>
      </div>
    </div>
 
    <!-- ── ADMIN PRIMARY BUTTON ───────────────────────────────────────── -->
    <div class="admin-form-section">
      <p class="admin-form-section-title">Admin Primary Button</p>
      <p style="font-size:12px;color:var(--text3);margin-bottom:14px;line-height:1.6;">
        Controls <code>.btn-admin-primary</code> — used for Save, Submit, Create actions.
      </p>
      <?php
      adminCompRow('--admin-btn-primary-bg',           'Background',       'color', $current);
      adminCompRow('--admin-btn-primary-color',         'Text Color',       'color', $current);
      adminCompRow('--admin-btn-primary-border',        'Border Color',     'color', $current);
      adminCompRow('--admin-btn-primary-hover-bg',      'Hover Background', 'color', $current);
      adminCompRow('--admin-btn-primary-hover-color',   'Hover Text',       'color', $current);
      adminCompRow('--admin-btn-primary-radius',        'Border Radius',    'text',  $current, 'e.g. 8px');
      ?>
      <!-- Live button preview -->
      <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap;align-items:center;">
        <button type="button" style="
          display:inline-flex;align-items:center;gap:6px;padding:9px 18px;
          background:var(--admin-btn-primary-bg,var(--accent));
          color:var(--admin-btn-primary-color,#fff);
          border:1.5px solid var(--admin-btn-primary-border,var(--accent));
          border-radius:var(--admin-btn-primary-radius,8px);
          font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;"
          onmouseover="this.style.background='var(--admin-btn-primary-hover-bg)';this.style.color='var(--admin-btn-primary-hover-color)'"
          onmouseout="this.style.background='var(--admin-btn-primary-bg)';this.style.color='var(--admin-btn-primary-color)'">
          <?= icon('check',14) ?> Save Changes
        </button>
      </div>
    </div>
 
    <!-- ── ADMIN SECONDARY BUTTON ─────────────────────────────────────── -->
    <div class="admin-form-section">
      <p class="admin-form-section-title">Admin Secondary Button</p>
      <p style="font-size:12px;color:var(--text3);margin-bottom:14px;line-height:1.6;">
        Controls <code>.btn-admin-secondary</code> — used for Cancel, Back, View actions.
      </p>
      <?php
      adminCompRow('--admin-btn-sec-bg',           'Background',       'color', $current);
      adminCompRow('--admin-btn-sec-color',         'Text Color',       'color', $current);
      adminCompRow('--admin-btn-sec-border',        'Border Color',     'color', $current);
      adminCompRow('--admin-btn-sec-hover-bg',      'Hover Background', 'color', $current);
      adminCompRow('--admin-btn-sec-hover-color',   'Hover Text',       'color', $current);
      adminCompRow('--admin-btn-sec-radius',        'Border Radius',    'text',  $current, 'e.g. 8px');
      ?>
      <!-- Live button preview -->
      <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap;align-items:center;">
        <button type="button" style="
          display:inline-flex;align-items:center;gap:6px;padding:9px 18px;
          background:var(--admin-btn-sec-bg,#fff);
          color:var(--admin-btn-sec-color,var(--text));
          border:1.5px solid var(--admin-btn-sec-border,var(--border));
          border-radius:var(--admin-btn-sec-radius,8px);
          font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;"
          onmouseover="this.style.background='var(--admin-btn-sec-hover-bg)';this.style.color='var(--admin-btn-sec-hover-color)'"
          onmouseout="this.style.background='var(--admin-btn-sec-bg)';this.style.color='var(--admin-btn-sec-color)'">
          <?= icon('back',14) ?> Cancel
        </button>
      </div>
    </div>
 
    <!-- ── ADMIN DANGER / DELETE BUTTON ───────────────────────────────── -->
    <div class="admin-form-section">
      <p class="admin-form-section-title">Admin Danger Button</p>
      <p style="font-size:12px;color:var(--text3);margin-bottom:14px;line-height:1.6;">
        Controls <code>.btn-admin-danger</code> — used for Delete, Remove actions.
      </p>
      <?php
      adminCompRow('--admin-btn-danger-bg',           'Background',       'color', $current);
      adminCompRow('--admin-btn-danger-color',         'Text Color',       'color', $current);
      adminCompRow('--admin-btn-danger-border',        'Border Color',     'color', $current);
      adminCompRow('--admin-btn-danger-hover-bg',      'Hover Background', 'color', $current);
      adminCompRow('--admin-btn-danger-hover-color',   'Hover Text',       'color', $current);
      ?>
      <!-- Live button preview -->
      <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap;align-items:center;">
        <button type="button" style="
          display:inline-flex;align-items:center;gap:6px;padding:7px 14px;
          background:var(--admin-btn-danger-bg,var(--danger-bg));
          color:var(--admin-btn-danger-color,var(--danger));
          border:1.5px solid var(--admin-btn-danger-border,var(--danger-bg));
          border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;"
          onmouseover="this.style.background='var(--admin-btn-danger-hover-bg)';this.style.color='var(--admin-btn-danger-hover-color)'"
          onmouseout="this.style.background='var(--admin-btn-danger-bg)';this.style.color='var(--admin-btn-danger-color)'">
          <?= icon('trash',13) ?> Delete
        </button>
      </div>
    </div>
 
    <!-- ── ADMIN GENERAL / TOOLBAR BUTTON ─────────────────────────────── -->
    <div class="admin-form-section">
      <p class="admin-form-section-title">Admin General / Toolbar Button</p>
      <p style="font-size:12px;color:var(--text3);margin-bottom:14px;line-height:1.6;">
        Controls <code>.admin-toolbar-btn--solid</code> — Export, Import, and other neutral toolbar actions.
      </p>
      <?php
      adminCompRow('--admin-btn-general-bg',         'Background',       'color', $current);
      adminCompRow('--admin-btn-general-color',       'Text Color',       'color', $current);
      adminCompRow('--admin-btn-general-border',      'Border Color',     'color', $current);
      adminCompRow('--admin-btn-general-hover-bg',    'Hover Background', 'color', $current);
      adminCompRow('--admin-btn-general-radius',      'Border Radius',    'text',  $current, 'e.g. 8px');
      ?>
      <!-- Live button preview -->
      <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap;align-items:center;">
        <button type="button" style="
          display:inline-flex;align-items:center;gap:6px;
          height:36px;padding:0 14px;
          background:var(--admin-btn-general-bg,#fff);
          color:var(--admin-btn-general-color,var(--text));
          border:1.5px solid var(--admin-btn-general-border,var(--border));
          border-radius:var(--admin-btn-general-radius,8px);
          font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;box-sizing:border-box;"
          onmouseover="this.style.background='var(--admin-btn-general-hover-bg)'"
          onmouseout="this.style.background='var(--admin-btn-general-bg)'">
          <?= icon('download',13) ?> Export Excel
        </button>
        <button type="button" style="
          display:inline-flex;align-items:center;gap:6px;
          height:36px;padding:0 14px;
          background:var(--admin-btn-general-bg,#fff);
          color:var(--admin-btn-general-color,var(--text));
          border:1.5px solid var(--admin-btn-general-border,var(--border));
          border-radius:var(--admin-btn-general-radius,8px);
          font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;box-sizing:border-box;"
          onmouseover="this.style.background='var(--admin-btn-general-hover-bg)'"
          onmouseout="this.style.background='var(--admin-btn-general-bg)'">
          <?= icon('upload',13) ?> Import Excel
        </button>
      </div>
    </div>
 
  </div><!-- /#panel-admin-components -->
  <!-- ══ TAB: Colors ════════════════════════════════════════════════════ -->
  <div class="theme-panel active" id="panel-colors">
    <?php foreach ($colorGroups as $groupName => $keys): ?>
    <div class="admin-form-section">
      <p class="admin-form-section-title"><?= h($groupName) ?></p>
      <div class="color-swatch-grid">
        <?php foreach ($keys as $key):
          $val = $current[$key] ?? '#cccccc';
          $label = ltrim($key, '-');
          $swatchColor = toHex($val);
        ?>
        <div class="color-swatch-item">
          <div class="color-preview" style="background:<?= h($swatchColor) ?>;" title="<?= h($key) ?>"></div>
          <div class="color-swatch-info">
            <div class="color-swatch-name"><?= h($label) ?></div>
            <input type="text" name="<?= h($key) ?>" class="admin-input color-sync-input"
                   value="<?= h($val) ?>" style="font-size:11px;padding:4px 8px;font-family:monospace;"
                   data-key="<?= h($key) ?>"/>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ══ TAB: Buttons ══════════════════════════════════════════════════ -->
  <div class="theme-panel" id="panel-buttons">
    <div class="admin-form-section">
      <p class="admin-form-section-title">Primary Button</p>
      <?php
      $btnFields = [
        '--btn-bg'           => ['Background',       'color'],
        '--btn-color'        => ['Text Color',        'color'],
        '--btn-border-color' => ['Border Color',      'color'],
        '--btn-hover-bg'     => ['Hover Background',  'color'],
        '--btn-hover-color'  => ['Hover Text',        'color'],
        '--btn-hover-border' => ['Hover Border',      'color'],
      ];
      foreach ($btnFields as $k => [$label, $type]):
        $val = $current[$k] ?? '#111111';
      ?>
      <div class="theme-row">
        <span class="theme-row-label"><?= h($label) ?></span>
        <div class="theme-row-control">
          <div class="color-preview color-swatch-item-inline" style="background:<?= h(toHex($val)) ?>;"></div>
          <input type="text" name="<?= h($k) ?>" class="admin-input color-sync-input"
                 value="<?= h($val) ?>" style="font-size:12px;padding:5px 10px;font-family:monospace;width:130px;"
                 data-key="<?= h($k) ?>"/>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="admin-form-section">
      <p class="admin-form-section-title">Secondary Button</p>
      <?php
      $btnSecFields = [
        '--btn-sec-bg'          => ['Background',       'color'],
        '--btn-sec-color'       => ['Text Color',        'color'],
        '--btn-sec-border'      => ['Border Color',      'color'],
        '--btn-sec-hover-bg'    => ['Hover Background',  'color'],
        '--btn-sec-hover-color' => ['Hover Text',        'color'],
        '--btn-sec-hover-border'=> ['Hover Border',      'color'],
      ];
      foreach ($btnSecFields as $k => [$label, $type]):
        $val = $current[$k] ?? '#ffffff';
      ?>
      <div class="theme-row">
        <span class="theme-row-label"><?= h($label) ?></span>
        <div class="theme-row-control">
          <div class="color-preview color-swatch-item-inline" style="background:<?= h(toHex($val)) ?>;"></div>
          <input type="text" name="<?= h($k) ?>" class="admin-input color-sync-input"
                 value="<?= h($val) ?>" style="font-size:12px;padding:5px 10px;font-family:monospace;width:130px;"
                 data-key="<?= h($k) ?>"/>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Live button preview -->
    <div class="admin-form-section">
      <p class="admin-form-section-title">Button Preview</p>
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <button type="button" id="btnPreviewPrimary"
                style="padding:10px 20px;border-radius:var(--btn-radius,8px);
                       background:var(--btn-bg,#111);color:var(--btn-color,#fff);
                       border:1.5px solid var(--btn-border-color,#111);
                       font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;"
                onmouseover="this.style.background='var(--btn-hover-bg)';this.style.color='var(--btn-hover-color)'"
                onmouseout="this.style.background='var(--btn-bg)';this.style.color='var(--btn-color)'">
          Primary Button
        </button>
        <button type="button" id="btnPreviewSecondary"
                style="padding:10px 20px;border-radius:var(--btn-radius,8px);
                       background:var(--btn-sec-bg,#fff);color:var(--btn-sec-color,#111);
                       border:1.5px solid var(--btn-sec-border,#ddd);
                       font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;"
                onmouseover="this.style.background='var(--btn-sec-hover-bg)';this.style.color='var(--btn-sec-hover-color)'"
                onmouseout="this.style.background='var(--btn-sec-bg)';this.style.color='var(--btn-sec-color)'">
          Secondary Button
        </button>
      </div>
    </div>
  </div>

  <!-- ══ TAB: Inputs ═══════════════════════════════════════════════════ -->
  <div class="theme-panel" id="panel-inputs">
    <div class="admin-form-section">
      <p class="admin-form-section-title">Input Field Styles</p>
      <p style="font-size:12px;color:var(--text3);margin-bottom:16px;">
        Applies globally to all text, email, password, number, tel, date, textarea, and select fields.
      </p>

      <?php
      $inputColorFields = [
        '--input-bg'           => 'Background Color',
        '--input-color'        => 'Text Color',
        '--input-placeholder'  => 'Placeholder Color',
        '--input-border'       => 'Border Color',
        '--input-focus-border' => 'Focus Border Color',
        '--input-focus-shadow' => 'Focus Shadow Color',
        '--input-hover-border' => 'Hover Border Color',
      ];
      foreach ($inputColorFields as $k => $label):
        $val = $current[$k] ?? '#cccccc';
        $isRgba = str_contains($val, 'rgba') || str_contains($val, 'rgb');
      ?>
      <div class="theme-row">
        <span class="theme-row-label"><?= h($label) ?></span>
        <div class="theme-row-control">
          <?php if (!$isRgba): ?>
          <div class="color-preview color-swatch-item-inline" style="background:<?= h(toHex($val)) ?>;"></div>
          <?php endif; ?>
          <input type="text" name="<?= h($k) ?>" class="admin-input color-sync-input"
                 value="<?= h($val) ?>" style="font-size:12px;padding:5px 10px;font-family:monospace;width:200px;"
                 data-key="<?= h($k) ?>"/>
        </div>
      </div>
      <?php endforeach; ?>

      <!-- Border Radius -->
      <div class="theme-row">
        <span class="theme-row-label">
          Border Radius
          <small>e.g. 8px, 12px, 0px</small>
        </span>
        <div class="theme-row-control">
          <input type="text" name="--input-radius" class="admin-input"
                 value="<?= h($current['--input-radius'] ?? '10px') ?>"
                 style="font-size:12px;padding:5px 10px;font-family:monospace;width:100px;"
                 oninput="document.documentElement.style.setProperty('--input-radius', this.value)"/>
        </div>
      </div>

      <!-- Font Size -->
      <div class="theme-row">
        <span class="theme-row-label">
          Font Size
          <small>e.g. 13px, 14px, 15px</small>
        </span>
        <div class="theme-row-control">
          <input type="text" name="--input-font-size" class="admin-input"
                 value="<?= h($current['--input-font-size'] ?? '14px') ?>"
                 style="font-size:12px;padding:5px 10px;font-family:monospace;width:100px;"
                 oninput="document.documentElement.style.setProperty('--input-font-size', this.value)"/>
        </div>
      </div>
    </div>

    <!-- Input live preview -->
    <div class="admin-form-section">
      <p class="admin-form-section-title">Input Preview</p>
      <div style="display:flex;flex-direction:column;gap:10px;max-width:400px;" id="inputPreviewArea">
        <input type="text" placeholder="Text input…" class="admin-input" style="
          background:var(--input-bg);color:var(--input-color);
          border-color:var(--input-border);border-radius:var(--input-radius);
          font-size:var(--input-font-size);" readonly/>
        <input type="email" placeholder="email@example.com" class="admin-input" style="
          background:var(--input-bg);color:var(--input-color);
          border-color:var(--input-border);border-radius:var(--input-radius);
          font-size:var(--input-font-size);" readonly/>
        <textarea class="admin-input" rows="2" placeholder="Textarea…" style="
          background:var(--input-bg);color:var(--input-color);
          border-color:var(--input-border);border-radius:var(--input-radius);
          font-size:var(--input-font-size);resize:none;" readonly></textarea>
        <select class="admin-input admin-select" style="
          background:var(--input-bg);color:var(--input-color);
          border-color:var(--input-border);border-radius:var(--input-radius);
          font-size:var(--input-font-size);">
          <option>Select option…</option>
          <option>Option A</option>
        </select>
      </div>
    </div>
  </div>

  <!-- ══ TAB: Labels ═══════════════════════════════════════════════════ -->
  <div class="theme-panel" id="panel-labels">
    <div class="admin-form-section">
      <p class="admin-form-section-title">Label / Form Label Styles</p>

      <div class="theme-row">
        <span class="theme-row-label">
          Text Color
          <small>Color of all form labels</small>
        </span>
        <div class="theme-row-control">
          <div class="color-preview color-swatch-item-inline"
               style="background:<?= h(toHex($current['--label-color'] ?? '#555555')) ?>;"></div>
          <input type="text" name="--label-color" class="admin-input color-sync-input"
                 value="<?= h($current['--label-color'] ?? '#555555') ?>"
                 style="font-size:12px;padding:5px 10px;font-family:monospace;width:130px;"
                 data-key="--label-color"/>
        </div>
      </div>

      <div class="theme-row">
        <span class="theme-row-label">
          Font Size
          <small>e.g. 11px, 12px, 13px</small>
        </span>
        <div class="theme-row-control">
          <input type="text" name="--label-font-size" class="admin-input"
                 value="<?= h($current['--label-font-size'] ?? '11.5px') ?>"
                 style="font-size:12px;padding:5px 10px;font-family:monospace;width:100px;"
                 oninput="document.documentElement.style.setProperty('--label-font-size', this.value)"/>
        </div>
      </div>

      <div class="theme-row">
        <span class="theme-row-label">
          Font Weight
          <small>400 normal · 500 medium · 600 semi · 700 bold</small>
        </span>
        <div class="theme-row-control">
          <select name="--label-font-weight" class="admin-input admin-select" style="width:120px;"
                  onchange="document.documentElement.style.setProperty('--label-font-weight', this.value)">
            <?php foreach (['300','400','500','600','700','800'] as $w): ?>
            <option value="<?= $w ?>" <?= ($current['--label-font-weight'] ?? '600') === $w ? 'selected' : '' ?>><?= $w ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- Label preview -->
    <div class="admin-form-section">
      <p class="admin-form-section-title">Label Preview</p>
      <label style="display:block;
        color:var(--label-color,#555);
        font-size:var(--label-font-size,11.5px);
        font-weight:var(--label-font-weight,600);
        text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">
        Form Label Example
      </label>
      <label style="display:block;
        color:var(--label-color,#555);
        font-size:var(--label-font-size,11.5px);
        font-weight:var(--label-font-weight,600);
        text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">
        Another Label
      </label>
    </div>
  </div>

  <!-- ══ TAB: Navbar ════════════════════════════════════════════════════ -->
  <div class="theme-panel" id="panel-navbar">
    <div class="admin-form-section">
      <p class="admin-form-section-title">Top Navigation Bar</p>

      <?php
      $navbarFields = [
        '--navbar-bg'          => ['Background Color',   true],
        '--navbar-color'       => ['Text Color',          true],
        '--navbar-icon-color'  => ['Icon Color',          true],
        '--navbar-hover-color' => ['Hover Color',         true],
        '--navbar-active-color'=> ['Active Menu Color',   true],
        '--navbar-border'      => ['Border Color',        true],
      ];
      foreach ($navbarFields as $k => [$label, $isColor]):
        $val = $current[$k] ?? '#cccccc';
      ?>
      <div class="theme-row">
        <span class="theme-row-label"><?= h($label) ?></span>
        <div class="theme-row-control">
          <div class="color-preview color-swatch-item-inline"
               style="background:<?= h(toHex($val)) ?>;"></div>
          <input type="text" name="<?= h($k) ?>" class="admin-input color-sync-input"
                 value="<?= h($val) ?>"
                 style="font-size:12px;padding:5px 10px;font-family:monospace;width:130px;"
                 data-key="<?= h($k) ?>"/>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Navbar preview -->
    <div class="admin-form-section">
      <p class="admin-form-section-title">Navbar Preview</p>
      <div id="navbarPreview" style="
        background:var(--navbar-bg,#fff);
        border:1px solid var(--navbar-border,#e0e0e0);
        border-radius:10px;padding:12px 20px;
        display:flex;align-items:center;gap:16px;">
        <span style="font-size:15px;font-weight:700;color:var(--navbar-color,#111);">Brand Name</span>
        <span style="font-size:13px;font-weight:500;color:var(--navbar-color,#111);opacity:.7;">Catalog</span>
        <span style="font-size:13px;font-weight:500;color:var(--navbar-active-color,#111);border-bottom:2px solid var(--navbar-active-color,#111);padding-bottom:2px;">Active Page</span>
        <span style="font-size:13px;font-weight:500;color:var(--navbar-hover-color,#555);opacity:.7;">Hovered</span>
        <div style="margin-left:auto;color:var(--navbar-icon-color,#777);"><?= icon('bell',18) ?></div>
        <div style="color:var(--navbar-icon-color,#777);"><?= icon('heart',18) ?></div>
      </div>
    </div>
  </div>

  <!-- ══ TAB: Fonts ════════════════════════════════════════════════════ -->
  <div class="theme-panel" id="panel-fonts">
    <div class="admin-form-section">
      <p class="admin-form-section-title">Global Font Settings</p>
      <p style="font-size:12px;color:var(--text3);margin-bottom:20px;line-height:1.6;">
        Select font families for each panel. Fonts are loaded from Google Fonts and applied globally.
        Changes take effect on next page load.
      </p>

      <?php
      $panelFonts = [
        '--user-font'  => ['User Panel Font',  'Applied to all user-facing pages (Catalog, Profile, Product…)'],
        '--admin-font' => ['Admin Panel Font',  'Applied throughout the Admin Panel'],
      ];
      foreach ($panelFonts as $varKey => [$title, $desc]):
        $currentVal = $current[$varKey] ?? '';
        $currentLabel = fontLabel($currentVal, $googleFonts);
      ?>
      <div style="margin-bottom:28px;">
        <p style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:4px;"><?= h($title) ?></p>
        <p style="font-size:12px;color:var(--text3);margin-bottom:12px;"><?= h($desc) ?></p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;" class="font-grid">
          <?php foreach ($googleFonts as $fname => $fcss): ?>
          <label class="font-option <?= $currentLabel === $fname ? 'selected' : '' ?>"
                 style="display:flex;align-items:center;gap:10px;padding:10px 14px;
                        border:1.5px solid var(--border);border-radius:10px;cursor:pointer;
                        transition:all .15s;font-size:13px;"
                 data-font-key="<?= h($varKey) ?>"
                 data-font-css="<?= h($fcss) ?>"
                 data-font-name="<?= h($fname) ?>">
            <input type="radio" name="<?= h($varKey) ?>" value="<?= h($fcss) ?>"
                   <?= $currentLabel === $fname ? 'checked' : '' ?>
                   style="display:none;"/>
            <span class="font-check" style="width:18px;height:18px;border-radius:50%;
              border:1.5px solid var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            </span>
            <span style="font-family:<?= h($fcss) ?>;"><?= h($fname) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
        <div class="font-preview-card" id="preview-<?= ltrim($varKey,'-') ?>"
             style="font-family:<?= h($current[$varKey] ?? "'Plus Jakarta Sans', sans-serif") ?>;">
          The quick brown fox jumps over the lazy dog — <?= h($currentLabel) ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ══ TAB: Radius ═══════════════════════════════════════════════════ -->
  <div class="theme-panel" id="panel-radius">
    <div class="admin-form-section">
      <p class="admin-form-section-title">Border Radii</p>
      <div class="admin-form-grid">
        <div>
          <label class="admin-label">Button Radius (--btn-radius)</label>
          <input type="text" name="--btn-radius" class="admin-input"
                 value="<?= h($current['--btn-radius'] ?? '8px') ?>" placeholder="8px"
                 oninput="document.documentElement.style.setProperty('--btn-radius', this.value)"/>
        </div>
        <div>
          <label class="admin-label">Card Radius (--card-radius)</label>
          <input type="text" name="--card-radius" class="admin-input"
                 value="<?= h($current['--card-radius'] ?? '16px') ?>" placeholder="16px"
                 oninput="document.documentElement.style.setProperty('--card-radius', this.value)"/>
        </div>
        <div>
          <label class="admin-label">Input Radius (--input-radius)</label>
          <input type="text" name="--input-radius" class="admin-input"
                 value="<?= h($current['--input-radius'] ?? '10px') ?>" placeholder="10px"
                 oninput="document.documentElement.style.setProperty('--input-radius', this.value)"/>
        </div>
      </div>
      <!-- Visual radius preview -->
      <div style="display:flex;gap:16px;margin-top:20px;flex-wrap:wrap;align-items:flex-end;">
        <div style="text-align:center;">
          <div style="width:80px;height:40px;background:var(--accent);border-radius:var(--btn-radius,8px);margin-bottom:6px;"></div>
          <p style="font-size:11px;color:var(--text3);">Button</p>
        </div>
        <div style="text-align:center;">
          <div style="width:80px;height:60px;background:var(--surface);border:1px solid var(--border);border-radius:var(--card-radius,16px);margin-bottom:6px;"></div>
          <p style="font-size:11px;color:var(--text3);">Card</p>
        </div>
        <div style="text-align:center;">
          <div style="width:80px;height:34px;background:var(--input-bg,#fff);border:1.5px solid var(--input-border,var(--border));border-radius:var(--input-radius,10px);margin-bottom:6px;"></div>
          <p style="font-size:11px;color:var(--text3);">Input</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Save Button (sticky bottom) -->
  <div style="position:sticky;bottom:0;background:var(--bg);padding:16px 0 4px;border-top:1px solid var(--border);margin-top:24px;display:flex;gap:12px;z-index:30;">
    <button type="submit" class="btn-admin-primary"><?= icon('check',16) ?> Save All Settings</button>
    <button type="button" onclick="applyPreview()" class="btn-admin-secondary"><?= icon('eye',14) ?> Preview</button>
  </div>
</form>

<script>
// ── Tab switching ───────────────────────────────────────────────────────────
function switchTab(name) {
  document.querySelectorAll('.theme-tab').forEach(t => {
    t.classList.toggle('active', t.getAttribute('onclick').includes("'"+name+"'"));
  });
  document.querySelectorAll('.theme-panel').forEach(p => {
    p.classList.toggle('active', p.id === 'panel-'+name);
  });
}
 
// ── Admin preview live update ────────────────────────────────────────────────
document.querySelectorAll('[data-admin-preview="1"]').forEach(inp => {
  inp.addEventListener('input', () => {
    const key = inp.dataset.key;
    if (key) document.documentElement.style.setProperty(key, inp.value);
    // Also update the mini preview boxes directly
    const sidebar  = document.getElementById('adminPreviewSidebar');
    const topbar   = document.getElementById('adminPreviewTopbar');
    if (sidebar) {
      sidebar.style.background =
        'linear-gradient(180deg,'
        + (document.querySelector('[name="--admin-sidebar-from"]')?.value || '#1A4D65')
        + ','
        + (document.querySelector('[name="--admin-sidebar-to"]')?.value   || '#0D2E3D')
        + ')';
    }
    if (topbar && key === '--admin-topbar-bg') topbar.style.background = inp.value;
  });
});

// ── Color swatch sync ───────────────────────────────────────────────────────
document.querySelectorAll('.color-sync-input').forEach(inp => {
  // Swatch inside swatch-item container
  const swatchInItem = inp.closest('.color-swatch-item')?.querySelector('.color-preview');
  // Swatch in theme-row (inline variant)
  const swatchInRow  = inp.closest('.theme-row-control')?.querySelector('.color-preview');
  const swatch = swatchInItem || swatchInRow;

  if (swatch) {
    swatch.style.background = inp.value;
    inp.addEventListener('input', () => swatch.style.background = inp.value);

    // Click swatch → native colour picker
    swatch.addEventListener('click', () => {
      const ci = document.createElement('input');
      ci.type  = 'color';
      ci.value = inp.value.startsWith('#') ? inp.value : '#776b63';
      ci.addEventListener('input', () => {
        inp.value = ci.value;
        swatch.style.background = ci.value;
        applyVar(inp.name, ci.value);
      });
      ci.click();
    });
  }

  inp.addEventListener('input', () => applyVar(inp.name, inp.value));
});

function applyVar(name, val) {
  if (name.startsWith('--')) document.documentElement.style.setProperty(name, val);
}

// ── Full live preview ───────────────────────────────────────────────────────
function applyPreview() {
  document.querySelectorAll('#colorForm input[name^="--"]').forEach(inp => {
    document.documentElement.style.setProperty(inp.name, inp.value);
  });
  document.querySelectorAll('#colorForm select[name^="--"]').forEach(sel => {
    document.documentElement.style.setProperty(sel.name, sel.value);
  });
}

// ── Font selection ──────────────────────────────────────────────────────────
document.querySelectorAll('.font-option').forEach(label => {
  label.addEventListener('click', () => {
    const varKey  = label.dataset.fontKey;
    const fontCss = label.dataset.fontCss;
    const fname   = label.dataset.fontName;

    // Deselect siblings
    document.querySelectorAll('.font-option[data-font-key="'+varKey+'"]').forEach(l => {
      l.classList.remove('selected');
      l.style.borderColor = '';
      l.style.background  = '';
      l.querySelector('.font-check').innerHTML = '';
    });

    label.classList.add('selected');
    label.style.borderColor = 'var(--accent)';
    label.style.background  = 'var(--accent-light)';
    label.querySelector('.font-check').innerHTML = '<?= icon('check', 10) ?>';
    label.querySelector('input[type=radio]').checked = true;

    // Update preview card
    const previewId = 'preview-' + varKey.replace(/^--/, '').replace(/-/g,'');
    // try exact id match
    const el = document.getElementById('preview-'+varKey.slice(2));
    if (el) {
      el.style.fontFamily = fontCss;
      el.textContent = 'The quick brown fox jumps over the lazy dog — ' + fname;
    }
  });

  // Init selected state styling
  if (label.classList.contains('selected')) {
    label.style.borderColor = 'var(--accent)';
    label.style.background  = 'var(--accent-light)';
    label.querySelector('.font-check').innerHTML = '<?= icon('check', 10) ?>';
  }
});
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>