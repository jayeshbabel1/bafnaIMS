<?php
/**
 * admin/views/license.php — License / Activation Key management
 */
requireAdminPermission('license.manage');
$adminTitle = 'License Management';
include __DIR__ . '/../_layout_top.php';
require_once BASE_PATH . '/includes/license.php';

$search   = trim($_GET['q'] ?? '');
$licenses = getAllLicenses($search);
$newKey   = getFlash('license_new_key'); // shown once, right after generation
?>

<style>
.lic-toolbar{display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap;}
.lic-search-wrap{position:relative;flex:1;min-width:220px;max-width:380px;}
.lic-search-wrap > svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--admin-text3,var(--text3));}
.lic-search-wrap input{padding-left:34px !important;}
.lic-key-banner{background:var(--success-bg);border:1px solid var(--success);border-radius:10px;padding:16px 18px;margin-bottom:18px;}
.lic-key-value{font-family:monospace;font-size:16px;font-weight:700;letter-spacing:1px;background:#fff;border:1px solid var(--border);border-radius:8px;padding:8px 12px;display:inline-block;margin:8px 0;}
#licModal,#licEditModal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9300;align-items:center;justify-content:center;padding:16px;}
#licModal.open,#licEditModal.open{display:flex;}
.lic-modal-card{background:var(--admin-card-bg,var(--surface));border-radius:14px;width:100%;max-width:460px;padding:24px 22px;box-shadow:0 16px 48px rgba(0,0,0,.2);}
</style>

<?php if ($newKey): ?>
<div class="lic-key-banner">
  <p style="font-weight:700;color:var(--success);margin-bottom:4px;"><?= icon('check',14) ?> Activation key generated</p>
  <p style="font-size:12px;color:var(--text2);margin-bottom:4px;">Copy this now — it will only be shown once and cannot be retrieved again.</p>
  <span class="lic-key-value" id="licNewKeyVal"><?= h($newKey) ?></span>
  <button type="button" class="btn-admin-secondary btn-admin-sm" onclick="navigator.clipboard.writeText(document.getElementById('licNewKeyVal').textContent)">
    <?= icon('copy',13) ?> Copy
  </button>
</div>
<?php endif; ?>

<div class="lic-toolbar">
  <button type="button" class="admin-toolbar-btn admin-toolbar-btn--primary" onclick="openLicModal()">
    <?= icon('plus',14) ?> Generate Activation Key
  </button>
  <form method="GET" action="index.php" class="lic-search-wrap">
    <input type="hidden" name="page" value="license"/>
    <?= icon('search',14) ?>
    <input type="text" name="q" class="admin-input" placeholder="Search customer, project or key…" value="<?= h($search) ?>"/>
  </form>
  <?php if ($search): ?><a href="index.php?page=license" class="btn-admin-secondary btn-admin-sm">Clear</a><?php endif; ?>
  <a href="index.php?page=license&export_licenses=1" class="admin-toolbar-btn admin-toolbar-btn--solid" style="margin-left:auto;">
    <?= icon('download',14) ?> Export CSV
  </a>
</div>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Customer</th><th>Project</th><th>Key</th><th>Activated</th>
        <th>Expiry</th><th>Type</th><th>Status</th><th>Domain</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($licenses)): ?>
      <tr><td colspan="9" class="admin-table-empty">No licenses found.</td></tr>
      <?php else: foreach ($licenses as $l):
        $isLifetime = (int)$l['is_lifetime'] === 1 || (int)date('Y', strtotime($l['expiry_date'])) >= LIFETIME_YEAR_THRESHOLD;
        $statusCls = ['active'=>'badge-green','expired'=>'badge-gray','revoked'=>'badge-red'][$l['status']] ?? 'badge-gray';
      ?>
      <tr>
        <td style="font-weight:600;"><?= h($l['customer_name']) ?></td>
        <td><?= h($l['project_name']) ?></td>
        <td style="font-family:monospace;font-size:12px;"><?= h(maskLicenseKey($l['key_display'])) ?></td>
        <td style="font-size:11px;color:var(--text3);"><?= $l['activation_date'] ? date('d M Y', $l['activation_date']) : '—' ?></td>
        <td style="font-size:12px;"><?= $isLifetime ? '<span class="badge badge-gold">Lifetime</span>' : h(date('d M Y', strtotime($l['expiry_date']))) ?></td>
        <td><?= $isLifetime ? 'One-Time' : 'Term' ?></td>
        <td><span class="badge <?= $statusCls ?>"><?= ucfirst($l['status']) ?></span></td>
        <td style="font-size:11px;color:var(--text3);"><?= h($l['bound_domain'] ?: '—') ?></td>
        <td>
          <div style="display:flex;gap:5px;">
            <button type="button" class="btn-admin-secondary btn-admin-sm"
                    onclick="openLicEditModal(<?= (int)$l['id'] ?>, <?= json_encode($l['expiry_date']) ?>, <?= $isLifetime?'true':'false' ?>)"
                    title="Edit expiry / convert to lifetime"><?= icon('edit',13) ?></button>
            <?php if ($l['status'] === 'revoked'): ?>
            <form method="POST" action="index.php" style="display:inline;">
              <input type="hidden" name="action" value="reactivate_license"/>
              <input type="hidden" name="license_id" value="<?= $l['id'] ?>"/>
              <?= csrfField() ?>
              <button type="submit" class="btn-admin-secondary btn-admin-sm" title="Reactivate"><?= icon('refresh',13) ?></button>
            </form>
            <?php else: ?>
            <form method="POST" action="index.php" style="display:inline;">
              <input type="hidden" name="action" value="revoke_license"/>
              <input type="hidden" name="license_id" value="<?= $l['id'] ?>"/>
              <?= csrfField() ?>
              <button type="submit" class="btn-admin-danger btn-admin-sm" data-confirm="Revoke this activation key?"><?= icon('trash',13) ?></button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- Generate key modal -->
<div id="licModal">
  <div class="lic-modal-card">
    <p style="font-size:16px;font-weight:700;margin-bottom:16px;">Generate Activation Key</p>
    <form method="POST" action="index.php">
      <input type="hidden" name="action" value="generate_license_key"/>
      <?= csrfField() ?>
      <div style="margin-bottom:14px;">
        <label class="admin-label">Customer Name <span style="color:var(--danger);">*</span></label>
        <input type="text" name="customer_name" class="admin-input" required/>
      </div>
      <div style="margin-bottom:14px;">
        <label class="admin-label">Project Name <span style="color:var(--danger);">*</span></label>
        <input type="text" name="project_name" class="admin-input" required/>
      </div>
      <div style="margin-bottom:14px;">
        <label class="admin-label">Expiry Date <span style="color:var(--danger);">*</span></label>
        <input type="date" name="expiry_date" class="admin-input" required/>
        <p style="font-size:11px;color:var(--text3);margin-top:4px;">Use 2099-12-31 (or later) for a Lifetime / One-Time Activation license.</p>
      </div>
      <label class="admin-check-row" style="margin-bottom:16px;">
        <input type="checkbox" name="is_lifetime" value="1"/>
        <span style="font-size:13px;">Lifetime License (never expires)</span>
      </label>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn-admin-primary" style="flex:1;justify-content:center;"><?= icon('check',15) ?> Generate</button>
        <button type="button" class="btn-admin-secondary" onclick="closeLicModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit expiry / convert to lifetime modal -->
<div id="licEditModal">
  <div class="lic-modal-card">
    <p style="font-size:16px;font-weight:700;margin-bottom:16px;">Edit License</p>
    <form method="POST" action="index.php">
      <input type="hidden" name="action" value="update_license"/>
      <input type="hidden" name="license_id" id="licEditId"/>
      <?= csrfField() ?>
      <div style="margin-bottom:14px;">
        <label class="admin-label">Expiry Date</label>
        <input type="date" name="expiry_date" id="licEditExpiry" class="admin-input" required/>
      </div>
      <label class="admin-check-row" style="margin-bottom:16px;">
        <input type="checkbox" name="is_lifetime" id="licEditLifetime" value="1"/>
        <span style="font-size:13px;">Convert to Lifetime License</span>
      </label>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn-admin-primary" style="flex:1;justify-content:center;"><?= icon('check',15) ?> Save</button>
        <button type="button" class="btn-admin-secondary" onclick="closeLicEditModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openLicModal(){ document.getElementById('licModal').classList.add('open'); }
function closeLicModal(){ document.getElementById('licModal').classList.remove('open'); }
function openLicEditModal(id, expiry, isLifetime){
  document.getElementById('licEditId').value = id;
  document.getElementById('licEditExpiry').value = expiry;
  document.getElementById('licEditLifetime').checked = !!isLifetime;
  document.getElementById('licEditModal').classList.add('open');
}
function closeLicEditModal(){ document.getElementById('licEditModal').classList.remove('open'); }
['licModal','licEditModal'].forEach(function(id){
  document.getElementById(id).addEventListener('click', function(e){ if (e.target===this) this.classList.remove('open'); });
});
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>