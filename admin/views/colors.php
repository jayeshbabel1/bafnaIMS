<?php
$adminTitle = 'Color Scheme';
include __DIR__ . '/../_layout_top.php';
$defaults = require __DIR__ . '/../../config/colors.php';
$db = getDB();
$rows = $db->query("SELECT `key`,`value` FROM settings WHERE `key` LIKE '--%'")->fetchAll();
$saved = array_column($rows, 'value', 'key');
$current = array_merge($defaults, $saved);

$groups = [
  'Background & Surfaces' => ['--bg','--surface','--surface2','--surface3'],
  'Accent Colors'         => ['--accent','--accent2','--accent-light','--accent-mid'],
  'Text Colors'           => ['--text','--text2','--text3'],
  'Borders & Stones'      => ['--border','--stone','--stone-dark'],
  'Status Colors'         => ['--success','--success-bg','--danger','--danger-bg'],
  'Highlights'            => ['--gold','--gold-bg','--nav-bg','--topbar-bg'],
];
?>

<div style="display:flex;gap:12px;margin-bottom:24px;">
  <form method="POST" action="index.php">
    <input type="hidden" name="action" value="reset_colors"/>
    <button type="submit" class="btn-admin-secondary"
            data-confirm="Reset all colors to defaults?"><?= icon('refresh',14) ?> Reset to Defaults</button>
  </form>
</div>

<form method="POST" action="index.php" id="colorForm">
  <input type="hidden" name="action" value="save_colors"/>

  <!-- Live Preview -->
  <div class="admin-form-section" style="margin-bottom:24px;">
    <p class="admin-form-section-title">Live Preview</p>
    <div id="colorPreview" style="display:flex;gap:8px;flex-wrap:wrap;padding:16px;background:var(--bg);border-radius:10px;border:1px solid var(--border);">
      <div style="padding:8px 16px;background:var(--accent);color:#fff;border-radius:8px;font-size:12px;font-weight:600;">Primary Button</div>
      <div style="padding:8px 16px;background:var(--surface);color:var(--text);border:1px solid var(--border);border-radius:8px;font-size:12px;">Surface Card</div>
      <span class="badge badge-green">In Stock</span>
      <span class="badge badge-gold">✦ Featured</span>
      <span class="badge badge-blue">Marble</span>
      <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-mid));"></div>
      <div style="width:40px;height:40px;border-radius:8px;background:var(--success-bg);border:1px solid var(--success);"></div>
      <div style="width:40px;height:40px;border-radius:8px;background:var(--danger-bg);border:1px solid var(--danger);"></div>
    </div>
  </div>

  <?php foreach ($groups as $groupName => $keys): ?>
  <div class="admin-form-section">
    <p class="admin-form-section-title"><?= h($groupName) ?></p>
    <div class="color-swatch-grid">
      <?php foreach ($keys as $key):
        $val = $current[$key] ?? '#cccccc';
        $label = ltrim($key, '-');
        $isColor = str_starts_with(trim($val), '#') || str_starts_with(trim($val), 'rgb');
        $swatchColor = $isColor ? $val : '#cccccc';
      ?>
      <div class="color-swatch-item">
        <div class="color-preview" style="background:<?= h($swatchColor) ?>;" title="Click to pick color for <?= h($key) ?>"></div>
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

  <!-- Border radius -->
  <div class="admin-form-section">
    <p class="admin-form-section-title">Border Radii</p>
    <div class="admin-form-grid">
      <div>
        <label class="admin-label">Button Radius (--btn-radius)</label>
        <input type="text" name="--btn-radius" class="admin-input" value="<?= h($current['--btn-radius'] ?? '12px') ?>" placeholder="12px"/>
      </div>
      <div>
        <label class="admin-label">Card Radius (--card-radius)</label>
        <input type="text" name="--card-radius" class="admin-input" value="<?= h($current['--card-radius'] ?? '12px') ?>" placeholder="12px"/>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:12px;">
    <button type="submit" class="btn-admin-primary"><?= icon('check',16) ?> Save Color Scheme</button>
    <button type="button" onclick="applyPreview()" class="btn-admin-secondary"><?= icon('eye',14) ?> Preview Live</button>
  </div>
</form>

<script>
function applyPreview() {
  const form = document.getElementById('colorForm');
  const inputs = form.querySelectorAll('input[name^="--"]');
  const root = document.documentElement;
  inputs.forEach(inp => { root.style.setProperty(inp.name, inp.value); });
}
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>
