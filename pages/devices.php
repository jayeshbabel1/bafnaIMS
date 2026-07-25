<?php
/**
 * pages/devices.php — User's Trusted Devices (Fire 5)
 * Register happens from profile.php (Fire 3). This page lists all of the
 * user's own trusted devices with rename/revoke, mirroring the simplicity
 * of pages/shortlist.php.
 */
require_once BASE_PATH . '/includes/device_auth.php';
ensureDeviceTables();

$pageTitle = 'Trusted Devices — ' . APP_NAME;
$showNav   = true;

$user    = currentUser();
$devices = getUserDevices($user['id']);
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content">
  <div class="page-header">
    <div class="page-header-left">
      <p class="page-eyebrow">Account Security</p>
      <h1 class="page-title">Trusted Devices</h1>
    </div>
    <div class="page-header-right">
      <span class="badge badge-black"><?= count($devices) ?></span>
    </div>
  </div>

  <!-- Register current device -->
  <div class="card" style="padding:18px;margin-bottom:20px;">
    <p style="font-weight:700;font-size:14px;margin-bottom:6px;">Trust This Device</p>
    <p style="font-size:12px;color:var(--text3);margin-bottom:14px;line-height:1.6;">
      Skip the login screen next time you visit from this browser. You can remove it anytime below.
    </p>
    <form method="POST" action="index.php" style="display:flex;gap:10px;flex-wrap:wrap;">
      <input type="hidden" name="action" value="register_device"/>
      <input type="hidden" name="return_url" value="index.php?page=devices"/>
      <?= csrfField() ?>
      <input type="text" name="device_name" class="input-field" style="flex:1;min-width:180px;"
             placeholder="Device name (e.g. My Laptop)"/>
      <button type="submit" class="btn btn-primary">
        <?= icon('check',15) ?>&nbsp; Trust This Device
      </button>
    </form>
  </div>

  <?php if (empty($devices)): ?>
  <div class="empty-state" style="padding-top:40px;">
    <div class="empty-icon"><?= icon('lock',28) ?></div>
    <p class="empty-title">No trusted devices yet</p>
    <p class="empty-sub">Devices you trust will appear here, along with when they were last used.</p>
  </div>

  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:10px;">
    <?php foreach ($devices as $d):
      $isActive = $d['status'] === 'active';
    ?>
    <div class="card" style="padding:16px 18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
      <div style="width:40px;height:40px;border-radius:10px;background:var(--gray-100);display:flex;align-items:center;justify-content:center;color:var(--text3);flex-shrink:0;">
        <?= icon('lock', 18) ?>
      </div>
      <div style="flex:1;min-width:160px;">
        <p class="dev-name-label" data-id="<?= (int)$d['id'] ?>" style="font-weight:600;font-size:14px;"><?= h($d['device_name']) ?></p>
        <p style="font-size:11px;color:var(--text4);margin-top:2px;">
          <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:<?= $isActive ? 'var(--success)' : 'var(--text4)' ?>;margin-right:4px;"></span>
          <?= $isActive ? 'Active' : 'Disabled' ?>
          <?= $d['last_seen'] ? ' · Last used ' . h(timeAgo((int)$d['last_seen'])) : ' · Never used' ?>
        </p>
        <?php if ($d['ip_last']): ?>
        <p style="font-size:11px;color:var(--text4);margin-top:1px;font-family:monospace;"><?= h($d['ip_last']) ?></p>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:8px;flex-shrink:0;">
        <button type="button" class="btn btn-secondary btn-sm dev-rename-btn"
                data-id="<?= (int)$d['id'] ?>" data-name="<?= h($d['device_name']) ?>">
          <?= icon('edit', 13) ?>&nbsp; Rename
        </button>
        <button type="button" class="btn btn-danger btn-sm dev-forcelogout-btn"
                data-id="<?= (int)$d['id'] ?>" data-name="<?= h($d['device_name']) ?>">
          <?= icon('logout', 13) ?>&nbsp; Forced Logout
        </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <p style="text-align:center;font-size:11px;color:var(--text4);margin-top:24px;padding-bottom:16px;">
    Removing a device signs it out immediately and it will need to log in normally again.
  </p>
</div>
<!-- Forced Logout modal — double confirmation -->
<div id="devForceLogoutModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9100;align-items:center;justify-content:center;padding:16px;">
  <div style="background:var(--white);border-radius:var(--radius-xl);padding:26px;max-width:400px;width:100%;box-shadow:var(--shadow-xl);">

    <!-- Step 1 -->
    <div id="dflStep1">
      <div style="width:52px;height:52px;border-radius:50%;background:var(--danger-bg);color:var(--danger);display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
        <?= icon('logout', 22) ?>
      </div>
      <p style="font-size:16px;font-weight:700;margin-bottom:8px;">Force Logout This Device?</p>
      <p style="font-size:13px;color:var(--text3);line-height:1.6;margin-bottom:20px;">
        "<span id="dflName1"></span>" will be removed from your trusted devices and signed out immediately.
      </p>
      <div style="display:flex;gap:10px;">
        <button type="button" class="btn btn-secondary btn-block" id="dflCancel1">Cancel</button>
        <button type="button" class="btn btn-danger btn-block" id="dflNext">Continue</button>
      </div>
    </div>

    <!-- Step 2 — final confirmation -->
    <div id="dflStep2" style="display:none;">
      <div style="width:52px;height:52px;border-radius:50%;background:var(--danger-bg);color:var(--danger);display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
        <?= icon('lock', 22) ?>
      </div>
      <p style="font-size:16px;font-weight:700;margin-bottom:8px;">Are You Absolutely Sure?</p>
      <p style="font-size:13px;color:var(--text3);line-height:1.6;margin-bottom:20px;">
        You will need your <strong>username and password</strong> to access this device again as "<span id="dflName2"></span>". This cannot be undone.
      </p>
      <form method="POST" action="index.php" id="dflForm">
        <input type="hidden" name="action"     value="revoke_device"/>
        <input type="hidden" name="device_id"  id="dflDeviceId" value=""/>
        <input type="hidden" name="return_url" value="index.php?page=devices"/>
        <?= csrfField() ?>
        <div style="display:flex;gap:10px;">
          <button type="button" class="btn btn-secondary btn-block" id="dflCancel2">Cancel</button>
          <button type="submit" class="btn btn-danger btn-block">Yes, Forced Logout</button>
        </div>
      </form>
    </div>

  </div>
</div>

<script>
(function () {
  var modal = document.getElementById('devForceLogoutModal');
  var step1 = document.getElementById('dflStep1');
  var step2 = document.getElementById('dflStep2');

  function openModal(id, name) {
    document.getElementById('dflName1').textContent = name;
    document.getElementById('dflName2').textContent = name;
    document.getElementById('dflDeviceId').value = id;
    step1.style.display = '';
    step2.style.display = 'none';
    modal.style.display = 'flex';
  }
  function closeModal() { modal.style.display = 'none'; }

  document.querySelectorAll('.dev-forcelogout-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      openModal(btn.dataset.id, btn.dataset.name);
    });
  });

  document.getElementById('dflNext').addEventListener('click', function () {
    step1.style.display = 'none';
    step2.style.display = '';
  });
  document.getElementById('dflCancel1').addEventListener('click', closeModal);
  document.getElementById('dflCancel2').addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
})();
</script>
<!-- Rename modal -->
<div id="devRenameModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9000;align-items:center;justify-content:center;padding:16px;">
  <div style="background:var(--white);border-radius:var(--radius-xl);padding:24px;max-width:380px;width:100%;box-shadow:var(--shadow-xl);">
    <p style="font-size:16px;font-weight:700;margin-bottom:16px;">Rename Device</p>
    <form method="POST" action="index.php">
      <input type="hidden" name="action"    value="rename_device"/>
      <input type="hidden" name="device_id" id="devRenameId" value=""/>
      <input type="hidden" name="return_url" value="index.php?page=devices"/>
      <?= csrfField() ?>
      <div class="input-group">
        <label class="input-label">Device Name</label>
        <input type="text" name="device_name" id="devRenameInput" class="input-field" required maxlength="150"/>
      </div>
      <div style="display:flex;gap:10px;margin-top:6px;">
        <button type="submit" class="btn btn-primary btn-block">Save</button>
        <button type="button" class="btn btn-secondary" id="devRenameCancel">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var modal  = document.getElementById('devRenameModal');
  var idEl   = document.getElementById('devRenameId');
  var nameEl = document.getElementById('devRenameInput');

  document.querySelectorAll('.dev-rename-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      idEl.value   = btn.dataset.id;
      nameEl.value = btn.dataset.name;
      modal.style.display = 'flex';
      setTimeout(function () { nameEl.focus(); }, 80);
    });
  });
  document.getElementById('devRenameCancel').addEventListener('click', function () {
    modal.style.display = 'none';
  });
  modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });
})();
</script>

<?php include BASE_PATH . '/layouts/footer.php'; ?>