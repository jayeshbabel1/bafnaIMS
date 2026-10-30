<?php
/**
 * admin/views/admin_client_form.php — Admin: Add or Edit a client for any user
 */
require_once BASE_PATH . '/includes/clients.php';

$id  = (int)($_GET['id'] ?? 0);
$c   = null;
$err = $inlineError ?? null;

if ($id) {
    $c = adminGetClientWithOwner($id);
    if (!$c) { flash('error', 'Client not found.'); redirect('index.php?page=admin_clients'); }
}

$adminTitle = $id ? 'Edit Client' : 'Add Client';
requireAdminPermission('clients.view');
include __DIR__ . '/../_layout_top.php';

$users = getAllUsersForDropdown();
$g     = fn($k) => h($c[$k] ?? '');
$selectedUserId = (int)($c['user_id'] ?? 0);
// New clients default to walk-in (the showroom case); existing rows keep their stored type.
$srcRaw           = $_POST['client_source'] ?? ($c['client_source'] ?? ($id ? 'referred' : 'walkin'));
$srcWalkin        = ($srcRaw === 'walkin');
$selectedReferrer = (string)($_POST['referrer_type'] ?? ($c['referrer_type'] ?? ''));
?>

<style>
.acf-grid { display:grid; grid-template-columns:1fr; gap:14px; }
@media (min-width:640px) { .acf-grid { grid-template-columns:1fr 1fr; } }
.acf-section-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--admin-text3,var(--text3)); margin-bottom:12px; padding-bottom:8px; border-bottom:1px solid var(--admin-table-border,var(--border)); }
.acf-card { max-width:760px; }
.acf-type-card { flex:1; min-width:220px; display:flex; gap:10px; align-items:flex-start; padding:12px 14px; border:1.5px solid var(--admin-table-border,var(--border)); border-radius:10px; cursor:pointer; background:var(--admin-card-bg,var(--surface)); }
.acf-type-card.selected { border-color:var(--admin-accent,var(--accent)); background:var(--admin-accent-light,var(--accent-light)); }
</style>

<div class="acf-card">

  <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;">
    <a href="index.php?page=admin_clients" class="btn-admin-secondary btn-admin-sm"><?= icon('back', 14) ?> Back</a>
  </div>

  <?php if ($err): ?>
  <div class="alert alert-error" style="margin-bottom:18px;"><?= h($err) ?></div>
  <?php endif; ?>

  <form method="POST" action="index.php" id="adminClientForm" novalidate>
    <input type="hidden" name="action"    value="<?= $id ? 'admin_update_client' : 'admin_create_client' ?>"/>
    <input type="hidden" name="client_id" value="<?= $id ?>"/>
    <?= csrfField() ?>
    <!-- Client type / owner -->
    <div class="admin-form-section">
      <p class="acf-section-label">Client Type</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;" id="acfTypeCards">
        <label class="acf-type-card <?= $srcWalkin ? 'selected' : '' ?>">
          <input type="radio" name="client_source" value="walkin" <?= $srcWalkin ? 'checked' : '' ?> style="margin-top:3px;"/>
          <span><strong style="font-size:13px;">Walk-in customer</strong><br/>
            <span style="font-size:11px;color:var(--admin-text3,var(--text3));">Visited the showroom directly — no architect, designer, builder or contractor in the system.</span></span>
        </label>
        <label class="acf-type-card <?= $srcWalkin ? '' : 'selected' ?>">
          <input type="radio" name="client_source" value="referred" <?= $srcWalkin ? '' : 'checked' ?> style="margin-top:3px;"/>
          <span><strong style="font-size:13px;">Referred by a registered user</strong><br/>
            <span style="font-size:11px;color:var(--admin-text3,var(--text3));">Belongs to an architect, designer, builder or contractor who has an account.</span></span>
        </label>
      </div>

      <!-- Walk-in: optional referrer category -->
      <div id="acfWalkinPanel" style="<?= $srcWalkin ? '' : 'display:none;' ?>">
        <label class="admin-label">Came via (optional)</label>
        <select name="referrer_type" class="admin-input admin-select">
          <option value="">— Came directly —</option>
          <?php foreach (ROLES as $rk => $rl): ?>
          <option value="<?= h($rk) ?>" <?= $selectedReferrer === $rk ? 'selected' : '' ?>><?= h($rl) ?></option>
          <?php endforeach; ?>
        </select>
        <p style="font-size:11px;color:var(--admin-text3,var(--text3));margin-top:6px;">
          <?= icon('info', 11) ?> Use this if a professional sent them but has no account here. This client is managed by showroom staff and does not appear on any user's panel.
        </p>
      </div>

      <!-- Referred: owning user -->
      <div id="acfReferredPanel" style="<?= $srcWalkin ? 'display:none;' : '' ?>">
        <label class="admin-label">Select User <span style="color:var(--danger);">*</span></label>
        <select name="user_id" id="acfUserSelect" class="admin-input admin-select">
          <option value="">— Select a user —</option>
          <?php foreach ($users as $u):
            $label = $u['name'] . ($u['firm'] ? ' (' . $u['firm'] . ')' : '');
          ?>
          <option value="<?= $u['id'] ?>" <?= $selectedUserId === (int)$u['id'] ? 'selected' : '' ?>>
            <?= h($label) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <p style="font-size:11px;color:var(--admin-text3,var(--text3));margin-top:6px;">
          <?= icon('info', 11) ?> This client and its selections will appear under
          <strong>Clients → Client Selections</strong> for the chosen user.
        </p>
      </div>
    </div>

    <!-- Client details -->
    <div class="admin-form-section">
      <p class="acf-section-label">Client Details</p>
      <div class="acf-grid">
        <div>
          <label class="admin-label">Client Name <span style="color:var(--danger);">*</span></label>
          <input type="text" name="client_name" class="admin-input"
                 placeholder="e.g. Ramesh Patel" value="<?= $g('client_name') ?>" required/>
        </div>
        <div>
          <label class="admin-label">Client Mobile <span style="color:var(--danger);">*</span></label>
          <div style="display:flex;border:1.5px solid var(--admin-input-border,var(--border));border-radius:var(--admin-input-radius,8px);overflow:hidden;background:var(--admin-input-bg,#fff);">
            <span style="padding:9px 10px;background:var(--surface2);border-right:1px solid var(--border);font-size:13px;color:var(--text2);flex-shrink:0;">+91</span>
            <input type="tel" name="client_mobile" id="acfClientMobile" class="admin-input"
                   placeholder="98765 43210" value="<?= $g('client_mobile') ?>" maxlength="10" required
                   style="border:none;border-radius:0;"/>
          </div>
          <p style="font-size:11px;color:var(--admin-text3,var(--text3));margin-top:5px;">10-digit Indian mobile number</p>
        </div>
        <div>
  <label class="admin-label">Email Address</label>
  <input type="email" name="email" class="admin-input" id="acfClientEmail"
         placeholder="client@example.com" value="<?= $g('email') ?>"/>
</div>
<div>
  <label class="admin-label">City</label>
  <input type="text" name="city" class="admin-input" id="acfClientCity"
         placeholder="e.g. Mumbai" value="<?= $g('city') ?>"/>
</div>
      </div>
    </div>

    <!-- Mason details -->
    <div class="admin-form-section">
      <p class="acf-section-label">Mason / Contractor (Optional)</p>
      <div class="acf-grid">
        <div>
          <label class="admin-label">Mason Name</label>
          <input type="text" name="mansoner_name" class="admin-input"
                 placeholder="e.g. Suresh Kumar" value="<?= $g('mansoner_name') ?>"/>
        </div>
        <div>
          <label class="admin-label">Mason Mobile</label>
          <div style="display:flex;border:1.5px solid var(--admin-input-border,var(--border));border-radius:var(--admin-input-radius,8px);overflow:hidden;background:var(--admin-input-bg,#fff);">
            <span style="padding:9px 10px;background:var(--surface2);border-right:1px solid var(--border);font-size:13px;color:var(--text2);flex-shrink:0;">+91</span>
            <input type="tel" name="mansoner_mobile" id="acfMasonMobile" class="admin-input"
                   placeholder="98765 43210" value="<?= $g('mansoner_mobile') ?>" maxlength="10"
                   style="border:none;border-radius:0;"/>
          </div>
        </div>
      </div>
    </div>

    <!-- Site address -->
    <div class="admin-form-section">
      <p class="acf-section-label">Site Address</p>
      <textarea name="site_address" class="admin-input" rows="3" maxlength="500" id="acfSiteAddr"
                placeholder="Plot 12, Sector 5, New Mumbai — 400001"><?= h($c['site_address'] ?? '') ?></textarea>
      <p style="font-size:11px;color:var(--admin-text3,var(--text3));margin-top:5px;">
        <span id="acfAddrCount"><?= mb_strlen($c['site_address'] ?? '') ?></span>/500 characters
      </p>
    </div>

    <!-- Actions -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button type="submit" class="btn-admin-primary">
        <?= icon('check', 15) ?> <?= $id ? 'Update Client' : 'Save Client' ?>
      </button>
      <a href="index.php?page=admin_clients" class="btn-admin-secondary">Cancel</a>
      <?php if ($id): ?>
      <a href="index.php?page=admin_client_selections&client_id=<?= $id ?>" class="btn-admin-secondary" style="margin-left:auto;">
        <?= icon('grid', 14) ?> Manage Product Selections →
      </a>
      <?php endif; ?>
    </div>
  </form>

</div>

<script>
// Character counter
(function () {
  var addr = document.getElementById('acfSiteAddr');
  var cnt  = document.getElementById('acfAddrCount');
  if (addr && cnt) addr.addEventListener('input', function () { cnt.textContent = addr.value.length; });
})();

// Walk-in / referred toggle
document.querySelectorAll('input[name="client_source"]').forEach(function (r) {
  r.addEventListener('change', function () {
    var walkin = this.value === 'walkin';
    document.getElementById('acfWalkinPanel').style.display   = walkin ? '' : 'none';
    document.getElementById('acfReferredPanel').style.display = walkin ? 'none' : '';
    document.querySelectorAll('.acf-type-card').forEach(function (c) {
      c.classList.toggle('selected', c.querySelector('input').checked);
    });
  });
});

// Client-side mobile validation
document.getElementById('adminClientForm').addEventListener('submit', function (e) {
  var srcEl = document.querySelector('input[name="client_source"]:checked');
  if (srcEl && srcEl.value === 'referred' && !document.getElementById('acfUserSelect').value) {
    e.preventDefault();
    alert('Please select a user.');
    return;
  }
  var clientMob = document.getElementById('acfClientMobile').value.replace(/\D/g, '');
  if (clientMob.length !== 10 || !/^[6-9]/.test(clientMob)) {
    e.preventDefault();
    alert('Please enter a valid 10-digit client mobile number.');
    return;
  }
  var masonMob = document.getElementById('acfMasonMobile').value.replace(/\D/g, '');
  if (masonMob && (masonMob.length !== 10 || !/^[6-9]/.test(masonMob))) {
    e.preventDefault();
    alert('Please enter a valid 10-digit mason mobile number.');
  }
   const emailVal = this.querySelector('[name="email"]').value.trim();
  if (emailVal && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
    e.preventDefault();
    alert('Please enter a valid email address.');
  }
});
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>