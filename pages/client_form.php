<?php
/**
 * pages/client_form.php — Add or Edit client
 */
$pageTitle = 'Client — ' . APP_NAME;
$showNav   = true;

require_once BASE_PATH . '/includes/clients.php';

$id  = (int)($_GET['id'] ?? 0);
$c   = null;
$err = $inlineError ?? null;

if ($id) {
    $c = getClient($id, $_SESSION['user_id']);
    if (!$c) { flash('error', 'Client not found.'); redirect('index.php?page=clients'); }
    $pageTitle = 'Edit Client — ' . APP_NAME;
}

$g = fn($k) => h($c[$k] ?? '');
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content" style="max-width:640px;">

  <!-- Back + Title -->
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;padding-top:20px;">
    <a href="index.php?page=clients" class="hero-icon-btn" style="flex-shrink:0;"><?= icon('back', 18) ?></a>
    <div>
      <p class="page-eyebrow">Clients</p>
      <h1 class="page-title" style="font-size:22px;"><?= $id ? 'Edit Client' : 'Add Client' ?></h1>
    </div>
  </div>

  <?php if ($err): ?>
  <div class="alert alert-error" style="margin-bottom:20px;"><?= h($err) ?></div>
  <?php endif; ?>

  <div class="card" style="padding:24px;">
    <form method="POST" action="index.php" id="clientForm" novalidate>
      <input type="hidden" name="action"    value="<?= $id ? 'update_client' : 'create_client' ?>"/>
      <input type="hidden" name="client_id" value="<?= $id ?>"/>

      <!-- Client section -->
      <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text4);margin-bottom:14px;">Client Details</p>

      <div class="profile-form-grid" style="grid-template-columns:1fr 1fr;gap:0 16px;">
        <div class="input-group">
          <label class="input-label">Client Name <span style="color:var(--danger);">*</span></label>
          <input type="text" name="client_name" class="input-field"
                 placeholder="e.g. Ramesh Patel"
                 value="<?= $g('client_name') ?>" required/>
        </div>
        <div class="input-group">
          <label class="input-label">Client Mobile <span style="color:var(--danger);">*</span></label>
          <div class="input-prefix-group">
            <span class="input-prefix">+91</span>
            <input type="tel" name="client_mobile" class="input-field"
                   placeholder="98765 43210"
                   value="<?= $g('client_mobile') ?>" maxlength="10" required/>
          </div>
          <p class="input-hint">10-digit Indian mobile number</p>
        </div>
      </div>

      <hr class="divider" style="margin:4px 0 20px;"/>

      <!-- Mason section -->
      <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text4);margin-bottom:14px;">Mason / Contractor (Optional)</p>

      <div class="profile-form-grid" style="grid-template-columns:1fr 1fr;gap:0 16px;">
        <div class="input-group">
          <label class="input-label">Mason Name</label>
          <input type="text" name="mansoner_name" class="input-field"
                 placeholder="e.g. Suresh Kumar"
                 value="<?= $g('mansoner_name') ?>"/>
        </div>
        <div class="input-group">
          <label class="input-label">Mason Mobile</label>
          <div class="input-prefix-group">
            <span class="input-prefix">+91</span>
            <input type="tel" name="mansoner_mobile" class="input-field"
                   placeholder="98765 43210"
                   value="<?= $g('mansoner_mobile') ?>" maxlength="10"/>
          </div>
        </div>
      </div>

      <hr class="divider" style="margin:4px 0 20px;"/>

      <!-- Site address -->
      <div class="input-group">
        <label class="input-label">Site Address</label>
        <textarea name="site_address" class="input-field" rows="3"
                  maxlength="500" id="siteAddr"
                  placeholder="Plot 12, Sector 5, New Mumbai — 400001"><?= $g('site_address') ?></textarea>
        <p class="input-hint">
          <span id="addrCount"><?= mb_strlen($c['site_address'] ?? '') ?></span>/500 characters
        </p>
      </div>

      <!-- Actions -->
      <div style="display:flex;gap:10px;margin-top:8px;">
        <button type="submit" class="btn btn-primary" style="flex:1;">
          <?= icon('check', 15) ?>&nbsp; <?= $id ? 'Update Client' : 'Save Client' ?>
        </button>
        <a href="index.php?page=clients" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>

</div>

<script>
// Character counter
const addr = document.getElementById('siteAddr');
const cnt  = document.getElementById('addrCount');
if (addr && cnt) {
  addr.addEventListener('input', () => { cnt.textContent = addr.value.length; });
}

// Client-side mobile validation
document.getElementById('clientForm').addEventListener('submit', function (e) {
  const clientMob = this.querySelector('[name="client_mobile"]').value.replace(/\D/g,'');
  if (clientMob.length !== 10 || !/^[6-9]/.test(clientMob)) {
    e.preventDefault();
    alert('Please enter a valid 10-digit client mobile number.');
    return;
  }
  const masonMob = this.querySelector('[name="mansoner_mobile"]').value.replace(/\D/g,'');
  if (masonMob && (masonMob.length !== 10 || !/^[6-9]/.test(masonMob))) {
    e.preventDefault();
    alert('Please enter a valid 10-digit mason mobile number.');
  }
});
</script>

<?php include BASE_PATH . '/layouts/footer.php'; ?>