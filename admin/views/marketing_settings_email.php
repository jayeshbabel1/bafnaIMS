<?php
/**
 * admin/views/marketing_settings_email.php — Fire 5
 */
require_once BASE_PATH . '/includes/marketing_email.php';

$adminTitle = 'Email Marketing Settings';
requireAdminPermission('marketing.settings.manage');
include __DIR__ . '/../_layout_top.php';

$s = getMarketingEmailSettings();
$smtp = getSmtpSettings();
?>
<style>
#mktesTestResult{display:none;margin-top:14px;padding:12px 14px;border-radius:8px;font-size:13px;}
#mktesTestResult.ok{display:block;background:var(--success-bg,#D8EFE6);color:var(--success,#3D8B6E);}
#mktesTestResult.err{display:block;background:var(--danger-bg,#FFF0F0);color:var(--danger,#E84040);}
</style>

<?php if (!$smtp['smtp_enabled'] || trim($smtp['smtp_host']) === ''): ?>
<div class="admin-form-section" style="background:var(--danger-bg,#FFF0F0);border-color:var(--danger,#E84040);margin-bottom:20px;">
  <p style="font-size:13px;color:var(--danger,#E84040);margin:0;">
    <?= icon('info',13) ?> <strong>SMTP is not configured or is disabled.</strong> Email marketing will not send until you
    <a href="index.php?page=smtp">configure SMTP in Settings → Mail Settings</a> and enable it there.
  </p>
</div>
<?php else: ?>
<div class="admin-form-section" style="background:var(--admin-accent-light,var(--accent-light));border-color:var(--admin-accent,var(--accent));margin-bottom:20px;">
  <p style="font-size:12px;color:var(--admin-text2,var(--text2));margin:0;">
    <?= icon('info',13) ?> Using SMTP host <strong><?= h($smtp['smtp_host']) ?></strong> (configured in
    <a href="index.php?page=smtp">Settings → Mail Settings</a>). The overrides below only change the
    <em>From name/email/reply-to</em> shown on marketing sends — not the underlying SMTP connection.
  </p>
</div>
<?php endif; ?>

<form method="POST" action="index.php" id="mktesForm">
  <input type="hidden" name="action" value="marketing_save_email_settings"/>
  <?= csrfField() ?>

  <div class="admin-form-section">
    <p class="admin-form-section-title">Email Marketing</p>
    <label class="admin-check-row" style="margin-bottom:18px;">
      <input type="checkbox" name="enabled" value="1" <?= $s['enabled'] ? 'checked' : '' ?>/>
      <span style="font-size:13px;font-weight:600;">Enable Email Marketing</span>
    </label>

    <div class="cp-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div>
        <label class="admin-label">From Name Override</label>
        <input type="text" name="from_name_override" class="admin-input" value="<?= h($s['from_name_override']) ?>"
               placeholder="<?= h($smtp['smtp_from_name'] ?: APP_NAME) ?> (leave blank to use default)"/>
      </div>
      <div>
        <label class="admin-label">From Email Override</label>
        <input type="email" name="from_email_override" class="admin-input" value="<?= h($s['from_email_override']) ?>"
               placeholder="<?= h($smtp['smtp_from_email'] ?: MAIL_FROM) ?> (leave blank to use default)"/>
      </div>
      <div class="cp-full" style="grid-column:1/-1;">
        <label class="admin-label">Reply-To Address</label>
        <input type="email" name="reply_to" class="admin-input" value="<?= h($s['reply_to']) ?>" placeholder="sales@bafnamarbles.com"/>
        <p style="font-size:11px;color:var(--admin-text3,var(--text3));margin-top:5px;">
          <?= icon('info',11) ?> Left blank, replies go to the From address itself.
        </p>
      </div>
    </div>
  </div>

  <div class="admin-form-section">
    <p class="admin-form-section-title">Compliance — Unsubscribe Footer</p>
    <p style="font-size:12px;color:var(--admin-text3,var(--text3));margin-bottom:12px;">
      Appended to every marketing email. Supports <code>{{company_name}}</code> and <code>{{unsubscribe_url}}</code> placeholders,
      resolved automatically when a campaign sends.
    </p>
    <textarea name="unsubscribe_footer" class="admin-input" rows="3"><?= h($s['unsubscribe_footer']) ?></textarea>
  </div>

  <div class="admin-form-section">
    <p class="admin-form-section-title">Tracking</p>
    <label class="admin-check-row" style="margin-bottom:12px;">
      <input type="checkbox" name="track_opens" value="1" <?= $s['track_opens'] ? 'checked' : '' ?>/>
      <span style="font-size:13px;font-weight:600;">Track Opens (tracking pixel)</span>
    </label>
    <label class="admin-check-row">
      <input type="checkbox" name="track_clicks" value="1" <?= $s['track_clicks'] ? 'checked' : '' ?>/>
      <span style="font-size:13px;font-weight:600;">Track Clicks (link redirect)</span>
    </label>
    <p style="font-size:11px;color:var(--admin-text3,var(--text3));margin-top:8px;">
      <?= icon('info',11) ?> Open tracking embeds an invisible 1×1 pixel; click tracking rewrites links through
      a signed redirect. Both are applied at the moment a campaign is enqueued — flipping a toggle only affects
      campaigns enqueued afterward, not ones already queued.
    </p>
  </div>

  <button type="submit" class="btn-admin-primary"><?= icon('check',15) ?> Save Settings</button>
</form>

<div class="admin-form-section" style="margin-top:20px;">
  <p class="admin-form-section-title">Send Test Email</p>
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
    <div style="flex:1;min-width:220px;">
      <label class="admin-label">Test Email Address</label>
      <input type="email" id="mktesTestAddr" class="admin-input" placeholder="you@example.com"/>
    </div>
    <button type="button" class="btn-admin-secondary" id="mktesTestBtn" onclick="mktesSendTest()"><?= icon('mail',14) ?> Send Test Email</button>
  </div>
  <div id="mktesTestResult"></div>
</div>

<script>
function mktesSendTest() {
  var addr = document.getElementById('mktesTestAddr').value.trim();
  if (!addr) { alert('Enter a test email address.'); return; }
  var btn = document.getElementById('mktesTestBtn');
  var resultEl = document.getElementById('mktesTestResult');
  var orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<?= icon("refresh",14) ?> Sending…';
  resultEl.className = ''; resultEl.style.display = 'none';

  var body = new URLSearchParams();
  body.set('action', 'marketing_test_email_connection');
  body.set('test_email', addr);
  body.set('csrf_token', <?= json_encode(csrfToken()) ?>);

  fetch('index.php?page=marketing_settings_email', { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      resultEl.className = d.success ? 'ok' : 'err';
      resultEl.textContent = d.success ? ('✓ Test email sent to ' + addr) : ('✗ ' + (d.error || 'Send failed.'));
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