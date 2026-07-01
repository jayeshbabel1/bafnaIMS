<?php
/**
 * admin/views/smtp.php — SMTP Settings
 */
$adminTitle = 'SMTP Settings';
requireAdminPermission('settings.smtp');
include __DIR__ . '/../_layout_top.php';

$db = getDB();

// Load current settings
$keys = ['smtp_host','smtp_port','smtp_username','smtp_password','smtp_encryption',
         'smtp_from_email','smtp_from_name','smtp_enabled'];
$rows = $db->query("SELECT `key`,`value` FROM settings WHERE `key` LIKE 'smtp_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
$s    = array_merge([
    'smtp_host'       => '',
    'smtp_port'       => '587',
    'smtp_username'   => '',
    'smtp_password'   => '',
    'smtp_encryption' => 'tls',
    'smtp_from_email' => MAIL_FROM,
    'smtp_from_name'  => MAIL_FROM_NAME,
    'smtp_enabled'    => '0',
], $rows);

function sg($s, $k) { return htmlspecialchars($s[$k] ?? '', ENT_QUOTES, 'UTF-8'); }
?>

<style>
.smtp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 640px) { .smtp-grid { grid-template-columns: 1fr; } }
.toggle-wrap { display: flex; align-items: center; gap: 12px; }
.toggle-label { font-size: 13px; font-weight: 600; color: var(--text); }
input[type="checkbox"].toggle { position: relative; width: 44px; height: 24px; appearance: none; background: var(--border); border-radius: 12px; cursor: pointer; transition: background .2s; flex-shrink: 0; }
input[type="checkbox"].toggle:checked { background: var(--accent); }
input[type="checkbox"].toggle::after { content: ''; position: absolute; width: 18px; height: 18px; border-radius: 50%; background: #fff; top: 3px; left: 3px; transition: left .2s; box-shadow: 0 1px 4px rgba(0,0,0,.2); }
input[type="checkbox"].toggle:checked::after { left: 23px; }
</style>

<form method="POST" action="index.php" id="smtpForm">
  <input type="hidden" name="action" value="save_smtp"/>
  <?= csrfField() ?>

  <!-- Enable toggle -->
  <div class="admin-form-section" style="margin-bottom:20px;">
    <div class="toggle-wrap">
      <input type="checkbox" name="smtp_enabled" id="smtpEnabled" class="toggle" value="1"
             <?= $s['smtp_enabled'] === '1' ? 'checked' : '' ?>/>
      <label for="smtpEnabled" class="toggle-label">Enable SMTP (otherwise falls back to PHP mail())</label>
    </div>
  </div>

  <!-- Server settings -->
  <div class="admin-form-section">
    <p class="admin-form-section-title">Server Configuration</p>
    <div class="smtp-grid">
      <div>
        <label class="admin-label">SMTP Host</label>
        <input type="text" name="smtp_host" class="admin-input" placeholder="smtp.gmail.com" value="<?= sg($s,'smtp_host') ?>"/>
      </div>
      <div>
        <label class="admin-label">SMTP Port</label>
        <input type="number" name="smtp_port" class="admin-input" placeholder="587" value="<?= sg($s,'smtp_port') ?>"/>
      </div>
      <div>
        <label class="admin-label">SMTP Username</label>
        <input type="text" name="smtp_username" class="admin-input" placeholder="your@email.com" value="<?= sg($s,'smtp_username') ?>" autocomplete="off"/>
      </div>
      <div>
        <label class="admin-label">SMTP Password</label>
        <div style="position:relative;">
          <input type="password" name="smtp_password" id="smtpPass" class="admin-input" placeholder="••••••••"
                  value="" autocomplete="new-password"
                <?= $s['smtp_password'] !== '' ? 'data-has-saved-password="1"' : '' ?>
                 style="padding-right:42px;"/>
          <button type="button" onclick="toggleSmtpPass()"
                  style="position:absolute;right:10px;top:50%;transform:translateY(-50%);color:var(--text3);cursor:pointer;">
            <?= icon('eye',16) ?>
          </button>
        </div>
      </div>
      <div>
        <label class="admin-label">Encryption</label>
        <select name="smtp_encryption" class="admin-input admin-select">
          <option value="tls"  <?= $s['smtp_encryption']==='tls' ?'selected':'' ?>>TLS (STARTTLS) — Port 587</option>
          <option value="ssl"  <?= $s['smtp_encryption']==='ssl' ?'selected':'' ?>>SSL — Port 465</option>
          <option value="none" <?= $s['smtp_encryption']==='none'?'selected':'' ?>>None — Port 25</option>
        </select>
      </div>
    </div>
  </div>

  <!-- From address -->
  <div class="admin-form-section">
    <p class="admin-form-section-title">Sender Identity</p>
    <div class="smtp-grid">
      <div>
        <label class="admin-label">From Email</label>
        <input type="email" name="smtp_from_email" class="admin-input"
               placeholder="noreply@bafnamarbles.com" value="<?= sg($s,'smtp_from_email') ?>"/>
      </div>
      <div>
        <label class="admin-label">From Name</label>
        <input type="text" name="smtp_from_name" class="admin-input"
               placeholder="Bafna Marbles" value="<?= sg($s,'smtp_from_name') ?>"/>
      </div>
    </div>
  </div>

  <!-- Test email -->
  <div class="admin-form-section">
    <p class="admin-form-section-title">Test Email</p>
    <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
      <div style="flex:1;min-width:200px;">
        <label class="admin-label">Send test to address</label>
        <input type="email" name="test_email" id="testEmailAddr" class="admin-input" placeholder="you@example.com"/>
      </div>
      <button type="button" onclick="sendTestEmail()" class="btn-admin-secondary" id="testEmailBtn"
              style="white-space:nowrap;">
        <?= icon('mail',14) ?> Send Test Mail
      </button>
    </div>
    <div id="testEmailResult" style="margin-top:10px;display:none;"></div>
  </div>

  <div style="display:flex;gap:10px;">
    <button type="submit" class="btn-admin-primary"><?= icon('check',16) ?> Save SMTP Settings</button>
  </div>
</form>

<script>
function toggleSmtpPass() {
  const f = document.getElementById('smtpPass');
  f.type = f.type === 'password' ? 'text' : 'password';
}

async function sendTestEmail() {
  const to  = document.getElementById('testEmailAddr').value.trim();
  const btn = document.getElementById('testEmailBtn');
  const res = document.getElementById('testEmailResult');
  if (!to) { alert('Please enter a test email address.'); return; }
  btn.disabled = true;
  btn.innerHTML = '<?= icon('refresh',14) ?> Sending…';
  res.style.display = 'none';
  try {
    const fd = new FormData(document.getElementById('smtpForm'));
    fd.set('action', 'test_smtp');
    fd.set('test_email', to);
    const r = await fetch('index.php', { method:'POST', body: fd });
    const d = await r.json();
    res.style.display = 'block';
    res.innerHTML = d.success
      ? '<div style="color:var(--success);font-size:13px;font-weight:600;">✓ Test email sent successfully to ' + to + '</div>'
      : '<div style="color:var(--danger);font-size:13px;font-weight:600;">✗ Failed: ' + (d.error || 'Unknown error') + '</div>';
  } catch(e) {
    res.style.display = 'block';
    res.innerHTML = '<div style="color:var(--danger);font-size:13px;">Request failed: ' + e.message + '</div>';
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<?= icon('mail',14) ?> Send Test Mail';
  }
}
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>