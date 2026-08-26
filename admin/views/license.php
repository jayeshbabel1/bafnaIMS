<?php
/**
 * admin/views/license.php — License activation (view / activate / delete).
 * Generation is intentionally NOT here — see Scripts/license_generator.php.
 */
requireAdminPermission('license.manage');
$adminTitle = 'License & Activation';
include __DIR__ . '/../_layout_top.php';
require_once BASE_PATH . '/includes/license.php';
require_once BASE_PATH . '/includes/license_caps.php';

$status   = checkLicenseStatus();
$license  = $status['license'];
$planKey  = $license['plan'] ?? null;
$planInfo = $planKey && isset(LICENSE_PLAN_CAPS[$planKey]) ? LICENSE_PLAN_CAPS[$planKey] : null;

function planBadgeClass(string $plan): string {
    return ['demo'=>'badge-gray','lite'=>'badge-blue','pro'=>'badge-gold','pro_plus'=>'badge-green'][$plan] ?? 'badge-gray';
}
$statusCls = [
    'active'=>'badge-green','lifetime'=>'badge-green','expired'=>'badge-gray','revoked'=>'badge-red',
    'not_activated'=>'badge-gray','invalid'=>'badge-red','domain_mismatch'=>'badge-red',
][$status['state']] ?? 'badge-gray';

$_licUsage = $status['valid'] ? getAllLicenseCapUsageCached() : [];
$_licPlanKey  = getCurrentLicensePlan();
$_licPlanInfo = LICENSE_PLAN_CAPS[$_licPlanKey];
?>
<style>
.lic-card{background:var(--admin-card-bg,var(--surface));border:1px solid var(--admin-table-border,var(--border));border-radius:var(--admin-card-radius,var(--card-radius));padding:20px 22px;margin-bottom:20px;}
.lic-card-title{font-size:15px;font-weight:700;color:var(--admin-text,var(--text));margin-bottom:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.lic-detail-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:18px;}
.lic-detail-item b{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--admin-text3,var(--text3));margin-bottom:3px;}
.lic-detail-item span{font-size:13px;color:var(--admin-text,var(--text));}
.lic-key-value{font-family:monospace;font-size:14px;font-weight:700;letter-spacing:.5px;background:var(--admin-surface2,var(--surface2));border:1px solid var(--admin-table-border,var(--border));border-radius:8px;padding:8px 12px;display:inline-block;}
.lic-usage-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-top:6px;}
.lic-usage-bar{height:7px;border-radius:4px;background:var(--admin-surface2,var(--surface2));overflow:hidden;margin-top:6px;}
.lic-usage-bar-fill{height:100%;border-radius:4px;}
</style>

<!-- ══ Current License ══════════════════════════════════════════════════ -->
<div class="lic-card">
  <p class="lic-card-title">
    Current License
    <?php if ($planInfo): ?><span class="badge <?= planBadgeClass($planKey) ?>"><?= h($planInfo['label']) ?></span><?php endif; ?>
    <span class="badge <?= $statusCls ?>"><?= h(ucwords(str_replace('_',' ', $status['state']))) ?></span>
  </p>

  <?php if ($license): ?>
  <div class="lic-detail-grid">
    <div class="lic-detail-item"><b>Customer</b><span><?= h($license['customer_name']) ?></span></div>
    <div class="lic-detail-item"><b>Project</b><span><?= h($license['project_name']) ?></span></div>
    <div class="lic-detail-item"><b>Key</b><span class="lic-key-value"><?= h(maskLicenseKey($license['key_display'])) ?></span></div>
    <div class="lic-detail-item"><b>Activated</b><span><?= $license['activation_date'] ? date('d M Y', $license['activation_date']) : '—' ?></span></div>
    <div class="lic-detail-item"><b>Expiry</b><span><?= ((int)$license['is_lifetime']===1) ? 'Lifetime' : h(date('d M Y', strtotime($license['expiry_date']))) ?></span></div>
    <div class="lic-detail-item"><b>Bound Domain</b><span><?= h($license['bound_domain'] ?: '—') ?></span></div>
  </div>

  <?php if ($_licUsage): ?>
  <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--admin-text3,var(--text3));margin-bottom:4px;">Plan Usage</p>
  <div class="lic-usage-grid">
    <?php foreach ($_licUsage as $u):
      $pct = $u['limit'] === null ? 100 : ($u['limit'] > 0 ? min(100, round(($u['used']/$u['limit'])*100)) : 100);
      $tone = $u['limit'] === null ? 'ok' : ($pct >= 100 ? 'danger' : ($pct >= 80 ? 'warn' : 'ok'));
      $barColor = ['ok'=>'var(--admin-accent,var(--accent))','warn'=>'#E8C468','danger'=>'var(--danger,#E84040)'][$tone];
    ?>
    <div>
      <div style="display:flex;justify-content:space-between;font-size:11.5px;">
        <span style="color:var(--admin-text2,var(--text2));font-weight:600;"><?= h($u['label']) ?></span>
        <span style="font-family:monospace;font-weight:700;"><?= $u['used'] ?> / <?= $u['limit'] === null ? '∞' : $u['limit'] ?></span>
      </div>
      <div class="lic-usage-bar"><div class="lic-usage-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div style="margin-top:20px;">
    <form method="POST" action="index.php">
      <input type="hidden" name="action" value="admin_delete_license"/>
      <?= csrfField() ?>
      <button type="submit" class="btn-admin-danger"
              data-confirm="Delete the current license? This will immediately lock the application until a new key is activated.">
        <?= icon('trash',14) ?> Delete License
      </button>
    </form>
  </div>
  <?php else: ?>
  <p style="font-size:13px;color:var(--admin-text3,var(--text3));">No license is currently activated. Enter an activation key below.</p>
  <?php endif; ?>
</div>

<!-- ══ Activate License ═════════════════════════════════════════════════ -->
<div class="lic-card">
  <p class="lic-card-title"><?= $license ? 'Replace License' : 'Activate License' ?></p>
  <?php if ($license): ?>
  <p style="font-size:12px;color:var(--admin-text3,var(--text3));margin-bottom:14px;">Activating a new key will replace the current license above.</p>
  <?php endif; ?>
  <form method="POST" action="index.php" style="max-width:420px;">
    <input type="hidden" name="action" value="admin_activate_license"/>
    <?= csrfField() ?>
    <div style="margin-bottom:14px;">
      <label class="admin-label">Activation Key</label>
      <input type="text" name="activation_key" class="admin-input"
             placeholder="XXXXX-XXXXX-XXXXX-XXXXX" required autocomplete="off"
             style="font-family:monospace;letter-spacing:1px;text-transform:uppercase;"/>
    </div>
    <button type="submit" class="btn-admin-primary"><?= icon('check',15) ?> Activate</button>
  </form>
</div>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>