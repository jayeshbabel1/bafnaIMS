<?php
/**
 * admin/views/marketing_settings_whatsapp.php — Fire 4
 */
require_once BASE_PATH . '/includes/marketing_whatsapp.php';

$adminTitle = 'WhatsApp Business API';
requireAdminPermission('marketing.settings.manage');
include __DIR__ . '/../_layout_top.php';

$s = getMarketingWhatsAppSettings();
$webhookUrl = getMarketingWhatsAppWebhookUrl();
?>
<style>
.mktws-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
@media(max-width:640px){.mktws-grid{grid-template-columns:1fr;}}
.mktws-webhook-box{background:var(--admin-surface2,var(--surface2));border:1px solid var(--admin-table-border,var(--border));border-radius:8px;padding:12px 14px;font-family:monospace;font-size:12.5px;word-break:break-all;display:flex;align-items:center;gap:10px;justify-content:space-between;flex-wrap:wrap;}
#mktwsTestResult{display:none;margin-top:14px;padding:12px 14px;border-radius:8px;font-size:13px;}
#mktwsTestResult.ok{display:block;background:var(--success-bg,#D8EFE6);color:var(--success,#3D8B6E);}
#mktwsTestResult.err{display:block;background:var(--danger-bg,#FFF0F0);color:var(--danger,#E84040);}
</style>

<div class="admin-form-section" style="background:var(--admin-accent-light,var(--accent-light));border-color:var(--admin-accent,var(--accent));margin-bottom:20px;">
  <p style="font-size:12px;color:var(--admin-text2,var(--text2));line-height:1.6;margin:0;">
    <?= icon('info',13) ?> This connects the Marketing module to <strong>Meta's WhatsApp Cloud API</strong>.
    You'll need a WhatsApp Business Account, a registered phone number, and a permanent access token from
    <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">Meta for Developers</a>.
        The webhook endpoint below is live and processes delivery status updates and opt-out replies.
  </p>
</div>
<form method="POST" action="index.php" id="mktwsForm">
  <input type="hidden" name="action" value="marketing_save_whatsapp_settings"/>
  <?= csrfField() ?>

  <div class="admin-form-section">
    <p class="admin-form-section-title">Connection</p>
    <label class="admin-check-row" style="margin-bottom:18px;">
      <input type="checkbox" name="enabled" value="1" <?= $s['enabled'] ? 'checked' : '' ?>/>
      <span style="font-size:13px;font-weight:600;">Enable WhatsApp Marketing</span>
    </label>
    <div class="mktws-grid">
      <div>
        <label class="admin-label">Phone Number ID *</label>
        <input type="text" name="phone_number_id" class="admin-input" value="<?= h($s['phone_number_id']) ?>" placeholder="e.g. 109876543210987"/>
      </div>
      <div>
        <label class="admin-label">WhatsApp Business Account ID</label>
        <input type="text" name="business_account_id" class="admin-input" value="<?= h($s['business_account_id']) ?>" placeholder="e.g. 987654321098765"/>
      </div>
            <div>
        <label class="admin-label">Access Token <?= $s['access_token'] ? '' : '*' ?></label>
        <input type="password" name="access_token" class="admin-input" placeholder="<?= $s['access_token'] ? 'Leave blank to keep existing token' : 'Paste permanent access token' ?>" autocomplete="new-password"/>
        <p style="font-size:11px;color:var(--admin-text3,var(--text3));margin-top:5px;">
          <?= $s['access_token'] ? '✓ A token is currently saved (hidden for security).' : 'No token saved yet.' ?>
        </p>
      </div>
      <div>
        <label class="admin-label">App Secret <?= $s['app_secret'] ? '' : '*' ?></label>
        <input type="password" name="app_secret" class="admin-input" placeholder="<?= $s['app_secret'] ? 'Leave blank to keep existing secret' : 'From Meta App Dashboard → Settings → Basic' ?>" autocomplete="new-password"/>
        <p style="font-size:11px;color:var(--admin-text3,var(--text3));margin-top:5px;">
          <?= $s['app_secret'] ? '✓ An app secret is currently saved (hidden for security).' : 'No app secret saved — inbound webhook signature verification will be skipped until this is set.' ?>
          This is different from the Access Token above; find it under your Meta App's Basic Settings.
        </p>
      </div>
      <div>
        <label class="admin-label">API Version</label>
        <input type="text" name="api_version" class="admin-input" value="<?= h($s['api_version']) ?>" placeholder="v21.0"/>
      </div>
      <div>
        <label class="admin-label">Default Language Code</label>
        <input type="text" name="default_language" class="admin-input" value="<?= h($s['default_language']) ?>" placeholder="en_US"/>
      </div>
      <div>
        <label class="admin-label">Timezone</label>
        <input type="text" name="timezone" class="admin-input" value="<?= h($s['timezone']) ?>" placeholder="Asia/Kolkata"/>
      </div>
      <div class="cp-full" style="grid-column:1/-1;">
        <label class="admin-label">Sender Label (internal reference only)</label>
        <input type="text" name="sender_label" class="admin-input" value="<?= h($s['sender_label']) ?>" placeholder="e.g. Bafna Marble — Sales Line"/>
      </div>
    </div>
  </div>

  <div class="admin-form-section">
    <p class="admin-form-section-title">Webhook</p>
    <p style="font-size:12px;color:var(--admin-text3,var(--text3));margin-bottom:12px;">
      Paste this URL into Meta App Dashboard → WhatsApp → Configuration → Webhook, and set a verify token below (any string you choose).
    </p>
    <div class="mktws-webhook-box">
      <span id="mktwsWebhookUrl"><?= h($webhookUrl) ?></span>
      <button type="button" class="btn-admin-secondary btn-admin-sm" onclick="mktwsCopyWebhook()"><?= icon('copy',12) ?> Copy</button>
    </div>
    <div style="margin-top:14px;max-width:400px;">
      <label class="admin-label">Webhook Verify Token</label>
      <input type="password" name="webhook_verify_token" class="admin-input" placeholder="<?= $s['webhook_verify_token'] ? 'Leave blank to keep existing token' : 'Choose a random string' ?>" autocomplete="new-password"/>
      <p style="font-size:11px;color:var(--admin-text3,var(--text3));margin-top:5px;">
        <?= $s['webhook_verify_token'] ? '✓ A verify token is currently saved (hidden for security).' : 'No verify token saved yet.' ?>
      </p>
    </div>
  </div>

  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <button type="submit" class="btn-admin-primary"><?= icon('check',15) ?> Save Settings</button>
    <button type="button" class="btn-admin-secondary" id="mktwsTestBtn" onclick="mktwsTestConnection()"><?= icon('refresh',14) ?> Test Connection</button>
  </div>
  <div id="mktwsTestResult"></div>
</form>

<script>
function mktwsCopyWebhook() {
  var text = document.getElementById('mktwsWebhookUrl').textContent;
  navigator.clipboard.writeText(text).then(function () {
    alert('Webhook URL copied.');
  });
}

function mktwsTestConnection() {
  var btn = document.getElementById('mktwsTestBtn');
  var resultEl = document.getElementById('mktwsTestResult');
  var orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<?= icon("refresh",14) ?> Testing…';
  resultEl.className = ''; resultEl.style.display = 'none';

  var body = new URLSearchParams();
  body.set('action', 'marketing_test_whatsapp_connection');
  body.set('csrf_token', <?= json_encode(csrfToken()) ?>);

  fetch('index.php?page=marketing_settings_whatsapp', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (d.valid) {
        var phone = d.phone || {};
        var waba = d.business_account;
        var lines = [
          '✓ Connected successfully.',
          phone.verified_name ? ('Verified Name: ' + phone.verified_name) : '',
          phone.display_phone_number ? ('Phone: ' + phone.display_phone_number) : '',
          phone.quality_rating ? ('Quality Rating: ' + phone.quality_rating) : '',
          waba && waba.name ? ('Business Account: ' + waba.name) : '',
        ].filter(Boolean);
        resultEl.className = 'ok';
        resultEl.innerHTML = lines.join('<br/>');
      } else {
        resultEl.className = 'err';
        resultEl.textContent = '✗ ' + (d.error || 'Connection failed.');
      }
    })
    .catch(function (e) {
      resultEl.className = 'err';
      resultEl.textContent = '✗ Request failed: ' + e.message;
    })
    .finally(function () {
      btn.disabled = false;
      btn.innerHTML = orig;
    });
}
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>