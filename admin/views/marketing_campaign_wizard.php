<?php

require_once BASE_PATH . '/includes/marketing_campaigns.php';

// ── AJAX endpoints ────────────────────────────────────────────────────────
if (!empty($_GET['ajax_audience_preview']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    requireAdminPermissionJson('marketing.campaigns.create');
    csrfVerify(true);
    try {
        $audience = json_decode($_POST['audience'] ?? '{}', true) ?: [];
        $contacts = resolveMarketingAudienceContacts($audience);
        echo json_encode(['count' => count($contacts)]);
    } catch (Throwable $e) {
        error_log('marketing_campaign_wizard ajax_audience_preview: ' . $e->getMessage());
        echo json_encode(['count' => 0, 'error' => 'Could not calculate audience.']);
    }
    exit;
}
if (!empty($_GET['ajax_contact_search'])) {
    header('Content-Type: application/json'); // set FIRST, so even an early fatal still reaches the browser as JSON-content-type (may still be empty/malformed body, but never silently downgrades to text/html)
    requireAdminPermissionJson('marketing.contacts.view');
    try {
        $result = getMarketingContacts(['search' => trim($_GET['q'] ?? ''), 'limit' => 20, 'offset' => 0]);
        echo json_encode(['contacts' => array_map(fn($c) => ['id'=>$c['id'],'name'=>$c['name'],'mobile'=>$c['mobile'],'email'=>$c['email']], $result['rows'])]);
    } catch (Throwable $e) {
        error_log('ajax_contact_search: ' . $e->getMessage());
        echo json_encode(['contacts' => [], 'error' => 'Search failed.']);
    }
    exit;
}

if (!empty($_GET['ajax_catalog_list'])) {
    header('Content-Type: application/json');
    requireAdminPermissionJson('marketing.campaigns.create');
    try {
        echo json_encode(['catalogs' => listRecentCatalogPdfsForAttachment(20)]);
    } catch (Throwable $e) {
        error_log('ajax_catalog_list: ' . $e->getMessage());
        echo json_encode(['catalogs' => [], 'error' => 'Could not load catalogs.']);
    }
    exit;
}

if (!empty($_GET['ajax_review']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Content-Type set FIRST, before any logic — so even an early fatal
    // still gets rendered as (empty/malformed) JSON to the browser rather
    // than falling back to text/html and confusing JSON.parse() further.
    header('Content-Type: application/json');
    requireAdminPermissionJson('marketing.campaigns.create');
    csrfVerify(true); // BUGFIX: this endpoint never verified CSRF before — the JS also never sent a token (fixed below). A rejected/missing token was almost certainly the "Security..." text breaking JSON.parse in console.

    try {
        $id = (int)($_POST['campaign_id'] ?? 0);
        $campaign = getMarketingCampaign($id);
        if (!$campaign) { echo json_encode(['success'=>false,'error'=>'Campaign not found.']); exit; }
        $breakdown = getMarketingAudienceBreakdown($campaign);
        $durations = [];
        foreach ($breakdown['channels'] as $ch => $b) $durations[$ch] = estimateMarketingCampaignDuration($ch, $b['eligible']);
        $maxConcurrent = getMarketingLimit('max_concurrent_campaigns', 0);
        $running = (int)getDB()->query("SELECT COUNT(*) FROM marketing_campaigns WHERE status IN ('running','scheduled')")->fetchColumn();
        echo json_encode([
            'success' => true, 'breakdown' => $breakdown, 'durations' => $durations,
            'concurrent' => ['current' => $running, 'max' => $maxConcurrent],
            'limits' => [
                'whatsapp_hourly' => getMarketingLimit('whatsapp_hourly', 0), 'whatsapp_daily' => getMarketingLimit('whatsapp_daily', 0),
                'email_hourly' => getMarketingLimit('email_hourly', 0), 'email_daily' => getMarketingLimit('email_daily', 0),
            ],
        ]);
    } catch (Throwable $e) {
        // Guarantees valid JSON reaches the browser no matter what breaks
        // inside — a PHP warning/fatal here previously could otherwise
        // print raw HTML/text ahead of (or instead of) the JSON payload.
        error_log('marketing_campaign_wizard ajax_review: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Could not load review data — check server logs.']);
    }
    exit;
}

if (!empty($_POST) && ($_POST['action'] ?? '') === 'marketing_wizard_save') {
    header('Content-Type: application/json');
    requireAdminPermissionJson('marketing.campaigns.create');
    csrfVerify(true);
    try {
        $id = (int)($_POST['campaign_id'] ?? 0);
        $payload = [
            'name' => $_POST['name'] ?? '', 'channel' => $_POST['channel'] ?? 'email',
            'template_id' => $_POST['template_id'] ?? null, 'email_template_id' => $_POST['email_template_id'] ?? null,
            'audience' => json_decode($_POST['audience'] ?? '{}', true) ?: [],
            'variables' => json_decode($_POST['variables'] ?? '{}', true) ?: [],
            'attach_catalog_id' => $_POST['attach_catalog_id'] ?? null,
            'attach_selection_pdf' => !empty($_POST['attach_selection_pdf']),
        ];
        $result = $id ? updateMarketingCampaignDraft($id, $payload) : createMarketingCampaignDraft($payload);
        if ($result['success'] && !$id) $id = $result['id'];
        $result['campaign_id'] = $id;
        echo json_encode($result);
    } catch (Throwable $e) {
        error_log('marketing_wizard_save: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Save failed — check server logs.']);
    }
    exit;
}

if (!empty($_POST) && ($_POST['action'] ?? '') === 'marketing_wizard_test_send') {
    header('Content-Type: application/json');
    requireAdminPermissionJson('marketing.campaigns.create');
    csrfVerify(true);
    if (!throttle('marketing_wizard_test_send', 10, 60)) {
        echo json_encode(['success' => false, 'error' => 'Too many test sends. Please wait a moment.']);
        exit;
    }
    try {
        $result = sendMarketingTestMessage((int)($_POST['campaign_id'] ?? 0), $_POST['channel'] ?? '', trim($_POST['test_to'] ?? ''));
        echo json_encode($result);
    } catch (Throwable $e) {
        error_log('marketing_wizard_test_send: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Test send failed — check server logs.']);
    }
    exit;
}

$adminTitle = 'Campaign Wizard';
requireAdminPermission('marketing.campaigns.create');
include __DIR__ . '/../_layout_top.php';

$campaignId = (int)($_GET['id'] ?? 0);
$existing   = $campaignId ? getMarketingCampaign($campaignId) : null;

$waTemplates    = getMarketingTemplates(['channel' => 'whatsapp', 'limit' => 200])['rows'];
$emailTemplates = getMarketingTemplates(['channel' => 'email', 'limit' => 200])['rows'];
$allGroups      = getMarketingGroups();
$allTags        = getAllMarketingTags();
$variableRegistry = marketingVariableRegistry();

$canApprove = adminCan('marketing.campaigns.approve');
$canSend    = adminCan('marketing.campaigns.send');
?>
<style>
.cpw-steps{display:flex;flex-wrap:wrap;gap:4px 0;margin-bottom:22px;border-bottom:2px solid var(--admin-table-border,var(--border));}
.cpw-step{padding:10px 18px;font-size:13px;font-weight:600;color:var(--admin-text3,var(--text3));border-bottom:2px solid transparent;margin-bottom:-2px;display:flex;align-items:center;gap:6px;}
.cpw-step.active{color:var(--admin-accent,var(--accent));border-bottom-color:var(--admin-accent,var(--accent));}
.cpw-step-num{width:20px;height:20px;border-radius:50%;background:var(--admin-surface2,var(--surface2));display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;}
.cpw-step.active .cpw-step-num{background:var(--admin-accent,var(--accent));color:#fff;}
.cpw-panel{display:none;}
.cpw-panel.active{display:block;}
.mktw-audience-type{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-bottom:16px;}
.mktw-audience-card{border:1.5px solid var(--admin-table-border,var(--border));border-radius:10px;padding:14px;cursor:pointer;text-align:center;}
.mktw-audience-card.selected{border-color:var(--admin-accent,var(--accent));background:var(--admin-accent-light,var(--accent-light));}
.mktw-count-badge{font-size:20px;font-weight:700;color:var(--admin-accent,var(--accent));}
.mktw-var-btn{padding:5px 9px;border-radius:6px;border:1px solid var(--admin-table-border,var(--border));background:var(--admin-surface,var(--surface));font-size:11px;cursor:pointer;font-family:monospace;margin:2px;}
.mktw-review-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.mktw-review-card{background:var(--admin-surface2,var(--surface2));border-radius:10px;padding:14px;}
</style>

<div class="cpw-steps" id="mktwSteps">
  <div class="cpw-step active" data-step="1"><span class="cpw-step-num">1</span> Type &amp; Name</div>
  <div class="cpw-step" data-step="2"><span class="cpw-step-num">2</span> Audience</div>
  <div class="cpw-step" data-step="3"><span class="cpw-step-num">3</span> Content</div>
  <div class="cpw-step" data-step="4"><span class="cpw-step-num">4</span> Schedule</div>
  <div class="cpw-step" data-step="5"><span class="cpw-step-num">5</span> Review &amp; Confirm</div>
</div>

<!-- STEP 1 -->
<div class="cpw-panel active" id="mktwPanel1">
  <div class="admin-form-section">
    <label class="admin-label">Campaign Name *</label>
    <input type="text" id="mktwName" class="admin-input" style="max-width:400px;" value="<?= h($existing['name'] ?? '') ?>"/>
  </div>
  <div class="admin-form-section">
    <p class="admin-form-section-title">Channel</p>
   <div style="display:flex;gap:10px;flex-wrap:wrap;" id="mktwChannelCards">
      <?php foreach (['whatsapp'=>'WhatsApp Only','email'=>'Email Only','whatsapp_email'=>'WhatsApp + Email'] as $val => $label): ?>
      <label class="mktw-audience-card<?= ($existing['channel'] ?? 'email') === $val ? ' selected' : '' ?>" style="flex:1;min-width:160px;">
        <input type="radio" name="mktw_channel" value="<?= $val ?>" <?= ($existing['channel'] ?? 'email') === $val ? 'checked' : '' ?> style="display:none;" onchange="mktwPickChannel(this)"/>
        <?= h($label) ?>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- STEP 2: Audience -->
<div class="cpw-panel" id="mktwPanel2">
  <p class="admin-form-section-title" style="border:none;">Who should receive this campaign?</p>
  <div class="mktw-audience-type" id="mktwAudienceTypes">
    <?php foreach (['all'=>'All Contacts','groups'=>'Group(s)','tags'=>'Tag(s)','manual'=>'Manual Selection'] as $val => $label): ?>
    <div class="mktw-audience-card" data-type="<?= $val ?>" onclick="mktwPickAudienceType('<?= $val ?>')"><?= h($label) ?></div>
    <?php endforeach; ?>
  </div>

  <div id="mktwAudienceGroups" style="display:none;margin-bottom:16px;">
    <label class="admin-label">Select Group(s)</label>
    <select id="mktwGroupSelect" class="admin-input admin-select" multiple size="6">
      <?php foreach ($allGroups as $g): ?><option value="<?= $g['id'] ?>"><?= h($g['name']) ?> (<?= ucfirst($g['type']) ?>, <?= $g['contact_count'] ?> contacts)</option><?php endforeach; ?>
    </select>
  </div>
  <div id="mktwAudienceTags" style="display:none;margin-bottom:16px;">
    <label class="admin-label">Select Tag(s)</label>
    <select id="mktwTagSelect" class="admin-input admin-select" multiple size="6">
      <?php foreach ($allTags as $t): ?><option value="<?= $t['id'] ?>"><?= h($t['name']) ?> (<?= $t['contact_count'] ?> contacts)</option><?php endforeach; ?>
    </select>
  </div>
  <div id="mktwAudienceManual" style="display:none;margin-bottom:16px;">
    <label class="admin-label">Search &amp; Add Contacts</label>
    <input type="text" id="mktwManualSearch" class="admin-input" placeholder="Type name/mobile/email…" style="margin-bottom:8px;"/>
    <div id="mktwManualResults" style="max-height:180px;overflow-y:auto;margin-bottom:10px;"></div>
    <div id="mktwManualSelected" style="display:flex;flex-wrap:wrap;gap:6px;"></div>
  </div>

  <div class="admin-form-section" style="text-align:center;">
    <p style="font-size:12px;color:var(--admin-text3,var(--text3));margin-bottom:4px;">Matched Contacts</p>
    <p class="mktw-count-badge" id="mktwAudienceCount">—</p>
  </div>
</div>

<!-- STEP 3: Content -->
<div class="cpw-panel" id="mktwPanel3">
  <div id="mktwWaContentSection" class="admin-form-section">
    <p class="admin-form-section-title">WhatsApp Content</p>
    <label class="admin-label">Template</label>
    <select id="mktwWaTemplate" class="admin-input admin-select" style="max-width:400px;margin-bottom:14px;">
      <option value="">— Select —</option>
      <?php foreach ($waTemplates as $t): ?>
      <option value="<?= $t['id'] ?>" <?= ($existing['template_id'] ?? null) == $t['id'] ? 'selected' : '' ?>>
        <?= h($t['name']) ?> (<?= h($t['approval_status']) ?>)
      </option>
      <?php endforeach; ?>
    </select>
    <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
      <div style="flex:1;min-width:180px;"><label class="admin-label">Test Mobile (with country code)</label><input type="text" id="mktwWaTestTo" class="admin-input" placeholder="+919876543210"/></div>
      <button type="button" class="btn-admin-secondary" onclick="mktwSendTest('whatsapp')"><?= icon('whatsapp',14) ?> Send Test</button>
    </div>
        <div id="mktwTestResultWa" style="margin-top:8px;font-size:12px;"></div>
  </div>

  <div id="mktwEmailContentSection" class="admin-form-section">
    <p class="admin-form-section-title">Email Content</p>
    <label class="admin-label">Template</label>
    <select id="mktwEmailTemplate" class="admin-input admin-select" style="max-width:400px;margin-bottom:14px;">
      <option value="">— Select —</option>
      <?php foreach ($emailTemplates as $t): ?>
      <option value="<?= $t['id'] ?>" <?= ($existing['email_template_id'] ?? null) == $t['id'] ? 'selected' : '' ?>><?= h($t['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
      <div style="flex:1;min-width:180px;"><label class="admin-label">Test Email Address</label><input type="email" id="mktwEmailTestTo" class="admin-input" placeholder="you@example.com"/></div>
      <button type="button" class="btn-admin-secondary" onclick="mktwSendTest('email')"><?= icon('mail',14) ?> Send Test</button>
    </div>
    <div id="mktwTestResultEmail" style="margin-top:8px;font-size:12px;"></div>
  </div>

  <div class="admin-form-section">
    <p class="admin-form-section-title">Static Variable Overrides (optional)</p>
    <p style="font-size:12px;color:var(--admin-text3,var(--text3));margin-bottom:12px;">
      These override the auto-detected per-contact values for variables the template can't otherwise resolve —
      e.g. a specific <code>{{product_name}}</code> for a promo blast that isn't tied to one contact's own data.
      Leave blank to use each contact's own data (or the template's fallback text).
    </p>
    <div id="mktwStaticVarsGrid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;"></div>
  </div>

  <div class="admin-form-section">
    <p class="admin-form-section-title">Attach a Catalog PDF (optional)</p>
    <p style="font-size:12px;color:var(--admin-text3,var(--text3));margin-bottom:12px;">
      Email: attaches the file directly. WhatsApp: requires the selected template above to have a
      <strong>Document header</strong> (set on the Templates page) — otherwise the attachment is silently skipped and only the text sends.
    </p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
      <label style="display:flex;align-items:center;gap:6px;font-size:12px;"><input type="radio" name="mktw_attach_type" value="none" checked onchange="mktwAttachTypeChanged()"/> None</label>
      <label style="display:flex;align-items:center;gap:6px;font-size:12px;"><input type="radio" name="mktw_attach_type" value="static" onchange="mktwAttachTypeChanged()"/> Same catalog PDF for everyone</label>
      <label style="display:flex;align-items:center;gap:6px;font-size:12px;"><input type="radio" name="mktw_attach_type" value="selection" onchange="mktwAttachTypeChanged()"/> Each recipient's own selection PDF</label>
    </div>
    <div id="mktwAttachStaticWrap" style="display:none;">
      <select id="mktwAttachCatalogSelect" class="admin-input admin-select" style="max-width:400px;"><option value="">Loading…</option></select>
    </div>
    <div id="mktwAttachSelectionNote" style="display:none;font-size:11px;color:var(--admin-text3,var(--text3));">
      Only recipients linked to an actual client record will receive an attachment — leads/manual/imported contacts are sent text-only.
    </div>
  </div>
</div>


<!-- STEP 4: Schedule -->
<div class="cpw-panel" id="mktwPanel4">
  <div class="admin-form-section">
    <p class="admin-form-section-title">When should this send?</p>
 <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;" id="mktwScheduleCards">
      <?php foreach (['now'=>'Send Now','once'=>'Schedule Once','recurring'=>'Recurring'] as $val => $label): ?>
      <label class="mktw-audience-card<?= $val === 'now' ? ' selected' : '' ?>" style="flex:1;min-width:150px;">
        <input type="radio" name="mktw_schedule_type" value="<?= $val ?>" <?= $val === 'now' ? 'checked' : '' ?> style="display:none;" onchange="mktwPickScheduleType(this.value, this)"/>
        <?= h($label) ?>
      </label>
      <?php endforeach; ?>
    </div>

    <div id="mktwScheduleOnce" style="display:none;margin-bottom:16px;max-width:320px;">
      <label class="admin-label">Date &amp; Time</label>
      <input type="datetime-local" id="mktwScheduledAt" class="admin-input"/>
    </div>

    <div id="mktwScheduleRecurring" style="display:none;margin-bottom:16px;">
      <div style="background:var(--admin-accent-light,var(--accent-light));border-radius:8px;padding:12px 14px;margin-bottom:14px;">
        <p style="font-size:12px;color:var(--admin-text2,var(--text2));margin:0;">
          <?= icon('info',12) ?> Recurring campaigns currently enqueue only their <strong>first</strong> run.
          Automatic re-triggering on each interval requires the queue scheduler (shipping in Fire 8) — until then,
          treat this as "send once at the first occurrence," and re-run this wizard manually for subsequent sends.
        </p>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <div>
          <label class="admin-label">Repeat</label>
          <select id="mktwRecurFreq" class="admin-input admin-select"><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select>
        </div>
        <div>
          <label class="admin-label">First Send Date &amp; Time</label>
          <input type="datetime-local" id="mktwRecurStart" class="admin-input"/>
        </div>
      </div>
    </div>

    <div>
      <label class="admin-label">Timezone</label>
      <input type="text" id="mktwTimezone" class="admin-input" style="max-width:220px;" value="<?= h($existing['timezone'] ?? 'Asia/Kolkata') ?>"/>
    </div>
  </div>
</div>

<!-- STEP 5: Review & Confirm -->
<div class="cpw-panel" id="mktwPanel5">
  <div id="mktwReviewLoading" style="text-align:center;padding:30px;color:var(--admin-text3,var(--text3));">Calculating audience &amp; safety checks…</div>
  <div id="mktwReviewContent" style="display:none;">
    <div class="mktw-review-grid" style="margin-bottom:16px;">
      <div class="mktw-review-card">
        <p style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--admin-text3,var(--text3));margin-bottom:10px;">Audience Breakdown</p>
        <div id="mktwBreakdownOutput"></div>
      </div>
      <div class="mktw-review-card">
        <p style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--admin-text3,var(--text3));margin-bottom:10px;">Estimated Duration &amp; Limits</p>
        <div id="mktwDurationOutput"></div>
      </div>
    </div>
    <div id="mktwConcurrentWarning" style="display:none;background:var(--danger-bg,#FFF0F0);color:var(--danger,#E84040);border-radius:8px;padding:12px 14px;font-size:13px;margin-bottom:16px;"></div>

    <div class="admin-form-section">
      <p class="admin-form-section-title">Confirm &amp; Proceed</p>
      <?php if (!$canSend): ?>
      <p style="font-size:13px;color:var(--admin-text3,var(--text3));margin-bottom:14px;">
        <?= icon('info',13) ?> You don't have permission to schedule/send campaigns. Submit for approval instead — someone with send permission can pick it up from there.
      </p>
      <button type="button" class="btn-admin-primary" onclick="mktwSubmitForApproval()"><?= icon('check',15) ?> Submit for Approval</button>
      <?php else: ?>
      <label class="admin-check-row" style="margin-bottom:16px;">
        <input type="checkbox" id="mktwConfirmCheck"/>
        <span style="font-size:13px;">I've reviewed the audience breakdown and safety checks above and confirm this campaign is ready to send.</span>
      </label>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button type="button" class="btn-admin-primary" id="mktwConfirmSendBtn" onclick="mktwConfirmAndSend()" disabled><?= icon('send',15) ?> Confirm &amp; Schedule</button>
        <?php if ($canApprove): ?>
        <button type="button" class="btn-admin-secondary" onclick="mktwSubmitForApproval()"><?= icon('check',15) ?> Save as Draft / Submit for Approval Instead</button>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <div id="mktwFinalResult" style="display:none;margin-top:16px;padding:14px;border-radius:8px;"></div>
  </div>
</div>

<div class="admin-form-section" style="display:flex;justify-content:space-between;margin-top:10px;background:none;border:none;padding:0;">
  <button type="button" class="btn-admin-secondary" id="mktwPrevBtn" onclick="mktwPrevStep()" style="visibility:hidden;display:flex;align-items:center;gap:6px;">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    Back
  </button>
  <button type="button" class="btn-admin-primary" id="mktwNextBtn" onclick="mktwNextStep()" style="display:flex;align-items:center;gap:6px;">
    Next
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
  </button>
</div>

<script>
var MKTW_VARIABLES = <?= json_encode($variableRegistry) ?>;
var mktwCampaignId = <?= json_encode($campaignId ?: null) ?>;
var mktwCurrentStep = 1;
var mktwAudienceType = <?= json_encode($existing ? (json_decode($existing['audience_json'] ?? '{}', true)['type'] ?? 'all') : 'all') ?>;
var mktwManualIds = <?= json_encode($existing ? (json_decode($existing['audience_json'] ?? '{}', true)['contact_ids'] ?? []) : []) ?>;
var mktwManualLabels = {};
var mktwStaticVars = <?= json_encode($existing ? (json_decode($existing['variables_json'] ?? '{}', true) ?: []) : []) ?>;
var mktwCsrf = <?= json_encode(csrfToken()) ?>;

function esc(s){ var d=document.createElement('div'); d.textContent=String(s==null?'':s); return d.innerHTML; }

// ── Step navigation ────────────────────────────────────────────────────
function mktwGoToStep(n) {
  mktwCurrentStep = n;
  document.querySelectorAll('.cpw-step').forEach(function(s){ s.classList.toggle('active', parseInt(s.dataset.step,10) === n); });
  document.querySelectorAll('.cpw-panel').forEach(function(p){ p.classList.toggle('active', p.id === 'mktwPanel'+n); });
  document.getElementById('mktwPrevBtn').style.visibility = n === 1 ? 'hidden' : 'visible';
  document.getElementById('mktwNextBtn').style.display = n === 5 ? 'none' : '';
  if (n === 2) mktwRefreshAudienceCount();
  if (n === 3) mktwRenderStaticVarsGrid();
  if (n === 5) mktwLoadReview();
}
function mktwNextStep() {
  if (mktwCurrentStep === 1 && !document.getElementById('mktwName').value.trim()) { alert('Enter a campaign name.'); return; }
  mktwSaveDraft(function (ok) { if (ok) mktwGoToStep(Math.min(5, mktwCurrentStep + 1)); });
}
function mktwPrevStep() { mktwGoToStep(Math.max(1, mktwCurrentStep - 1)); }

// ── Step 1: channel ────────────────────────────────────────────────────
function mktwPickChannel(radio) {
  document.querySelectorAll('#mktwPanel1 .mktw-audience-card').forEach(function(c){ c.classList.remove('selected'); });
  radio.closest('.mktw-audience-card').classList.add('selected');
  mktwApplyChannelSections();
}
function mktwGetChannel() {
  var checked = document.querySelector('input[name="mktw_channel"]:checked');
  return checked ? checked.value : 'email';
}
function mktwApplyChannelSections() {
  var ch = mktwGetChannel();
  document.getElementById('mktwWaContentSection').style.display = (ch === 'whatsapp' || ch === 'whatsapp_email') ? '' : 'none';
  document.getElementById('mktwEmailContentSection').style.display = (ch === 'email' || ch === 'whatsapp_email') ? '' : 'none';
}
(function(){ var c = document.querySelector('input[name="mktw_channel"]:checked'); if (c) c.closest('.mktw-audience-card').classList.add('selected'); mktwApplyChannelSections(); })();

// ── Step 2: audience ───────────────────────────────────────────────────
function mktwPickAudienceType(type) {
  mktwAudienceType = type;
  document.querySelectorAll('#mktwAudienceTypes .mktw-audience-card').forEach(function(c){ c.classList.toggle('selected', c.dataset.type === type); });
  document.getElementById('mktwAudienceGroups').style.display = type === 'groups' ? '' : 'none';
  document.getElementById('mktwAudienceTags').style.display = type === 'tags' ? '' : 'none';
  document.getElementById('mktwAudienceManual').style.display = type === 'manual' ? '' : 'none';
  mktwRefreshAudienceCount();
}
(function(){ var el = document.querySelector('.mktw-audience-card[data-type="'+mktwAudienceType+'"]'); if (el) mktwPickAudienceType(mktwAudienceType); })();

function mktwBuildAudienceConfig() {
  if (mktwAudienceType === 'groups') return { type: 'groups', group_ids: Array.from(document.getElementById('mktwGroupSelect').selectedOptions).map(function(o){return parseInt(o.value,10);}) };
  if (mktwAudienceType === 'tags') return { type: 'tags', tag_ids: Array.from(document.getElementById('mktwTagSelect').selectedOptions).map(function(o){return parseInt(o.value,10);}) };
  if (mktwAudienceType === 'manual') return { type: 'manual', contact_ids: mktwManualIds };
  return { type: 'all' };
}
function mktwRefreshAudienceCount() {
  var body = new URLSearchParams();
  body.set('action', 'noop'); // harmless placeholder — just ensures $_POST['action'] exists so admin/index.php's front-controller checks don't warn on a missing key
  body.set('audience', JSON.stringify(mktwBuildAudienceConfig()));
  body.set('csrf_token', mktwCsrf);
  document.getElementById('mktwAudienceCount').textContent = '…';

  fetch('index.php?page=marketing_campaign_wizard&ajax_audience_preview=1', { method:'POST', body: body })
    .then(function(r) { return r.text(); })
    .then(function(raw) {
      var d = null;
      try { d = JSON.parse(raw); } catch (e) {}
      if (!d) {
        console.error('mktwRefreshAudienceCount: server did not return JSON. Raw response:', raw);
        document.getElementById('mktwAudienceCount').textContent = 'Error loading count — see console';
        return;
      }
      document.getElementById('mktwAudienceCount').textContent = (d.count || 0) + ' contact(s)';
    })
    .catch(function(err) {
      console.error('mktwRefreshAudienceCount: network error', err);
      document.getElementById('mktwAudienceCount').textContent = 'Network error';
    });
}
document.getElementById('mktwGroupSelect').addEventListener('change', mktwRefreshAudienceCount);
document.getElementById('mktwTagSelect').addEventListener('change', mktwRefreshAudienceCount);

var mktwManualTimer = null;
document.getElementById('mktwManualSearch').addEventListener('input', function() {
  var q = this.value.trim();
  clearTimeout(mktwManualTimer);
  if (q.length < 2) { document.getElementById('mktwManualResults').innerHTML = ''; return; }
  mktwManualTimer = setTimeout(function() {
    fetch('index.php?page=marketing_campaign_wizard&ajax_contact_search=1&q=' + encodeURIComponent(q))
      .then(function(r){return r.text();})
      .then(function(raw){
        var d = null;
        try { d = JSON.parse(raw); } catch (e) {}
        if (!d || !d.contacts) {
          console.error('mktwManualSearch: non-JSON response', raw);
          document.getElementById('mktwManualResults').innerHTML = '<div style="font-size:11px;color:var(--danger,#E84040);">Search failed — see console.</div>';
          return;
        }
        document.getElementById('mktwManualResults').innerHTML = d.contacts.map(function(c) {
          mktwManualLabels[c.id] = c.name + (c.mobile ? ' — ' + c.mobile : (c.email ? ' — ' + c.email : ''));
          return '<div style="padding:6px 8px;border:1px solid var(--admin-table-border,var(--border));border-radius:6px;margin-bottom:4px;cursor:pointer;font-size:12px;" onclick="mktwAddManual(' + c.id + ')">' + esc(mktwManualLabels[c.id]) + '</div>';
        }).join('');
      })
      .catch(function(err) { console.error('mktwManualSearch: network error', err); });
  }, 300);
});
function mktwAddManual(id) {
  if (mktwManualIds.indexOf(id) === -1) mktwManualIds.push(id);
  mktwRenderManualChips();
  mktwRefreshAudienceCount();
}
function mktwRemoveManual(id) {
  mktwManualIds = mktwManualIds.filter(function(x){ return x !== id; });
  mktwRenderManualChips();
  mktwRefreshAudienceCount();
}
function mktwRenderManualChips() {
  document.getElementById('mktwManualSelected').innerHTML = mktwManualIds.map(function(id) {
    return '<span class="badge badge-blue" style="cursor:pointer;" onclick="mktwRemoveManual(' + id + ')">' + esc(mktwManualLabels[id] || ('Contact #' + id)) + ' ×</span>';
  }).join('');
}
mktwRenderManualChips();

// ── Step 3: content ─────────────────────────────────────────────────────
function mktwRenderStaticVarsGrid() {
  var grid = document.getElementById('mktwStaticVarsGrid');
  grid.innerHTML = '';
  Object.keys(MKTW_VARIABLES).forEach(function(key) {
    var v = MKTW_VARIABLES[key];
    var wrap = document.createElement('div');
    wrap.innerHTML = '<label class="admin-label">' + esc(v.label) + '</label>' +
      '<input type="text" class="admin-input mktw-static-var" data-key="' + key + '" value="' + esc(mktwStaticVars[key] || '') + '" placeholder="Leave blank to use contact data"/>';
    grid.appendChild(wrap);
  });
}
function mktwCollectStaticVars() {
  var out = {};
  document.querySelectorAll('.mktw-static-var').forEach(function(i){ if (i.value.trim() !== '') out[i.dataset.key] = i.value.trim(); });
  return out;
}
function mktwSendTest(channel) {
  var testTo = channel === 'whatsapp' ? document.getElementById('mktwWaTestTo').value.trim() : document.getElementById('mktwEmailTestTo').value.trim();
  var resultEl = document.getElementById(channel === 'whatsapp' ? 'mktwTestResultWa' : 'mktwTestResultEmail');
  if (!testTo) { alert('Enter a test ' + (channel === 'whatsapp' ? 'mobile number' : 'email address') + '.'); return; }

  resultEl.style.color = '';
  resultEl.textContent = 'Saving your selections…';

  // BUGFIX: previously fired the test-send directly against mktwCampaignId
  // without saving first — so a just-picked template or a just-typed
  // variable override was never persisted before the test read the
  // campaign back from the DB. Saving first (same as every "Next" step
  // already does) closes that gap. This also auto-creates the campaign
  // if the wizard was never advanced past Step 1, so the old "save first"
  // guard is no longer needed.
  mktwSaveDraft(function (ok) {
    if (!ok) { resultEl.textContent = ''; return; }

    resultEl.textContent = 'Sending…';
    var body = new URLSearchParams();
    body.set('action', 'marketing_wizard_test_send'); body.set('campaign_id', mktwCampaignId);
    body.set('channel', channel); body.set('test_to', testTo); body.set('csrf_token', mktwCsrf);
    fetch('index.php?page=marketing_campaign_wizard', { method:'POST', body: body })
      .then(function(r){return r.json();}).then(function(d) {
        resultEl.style.color = d.success ? 'var(--success,#3D8B6E)' : 'var(--danger,#E84040)';
        resultEl.textContent = d.success ? '✓ Test sent.' : ('✗ ' + (d.error || 'Failed.'));
      })
      .catch(function() {
        resultEl.style.color = 'var(--danger,#E84040)';
        resultEl.textContent = '✗ Request failed — check your connection.';
      });
  });
}
function mktwAttachTypeChanged() {
  var type = document.querySelector('input[name="mktw_attach_type"]:checked').value;
  document.getElementById('mktwAttachStaticWrap').style.display = type === 'static' ? '' : 'none';
  document.getElementById('mktwAttachSelectionNote').style.display = type === 'selection' ? '' : 'none';
  if (type === 'static') mktwLoadCatalogPicker();
}
var mktwCatalogsLoaded = false;

  function mktwLoadCatalogPicker() {
  if (mktwCatalogsLoaded) return;
  mktwCatalogsLoaded = true;
  var sel = document.getElementById('mktwAttachCatalogSelect');
  fetch('index.php?page=marketing_campaign_wizard&ajax_catalog_list=1')
    .then(function(r){return r.text();})
    .then(function(raw) {
      var d = null;
      try { d = JSON.parse(raw); } catch (e) {}
      if (!d || !d.catalogs) {
        console.error('mktwLoadCatalogPicker: non-JSON response', raw);
        sel.innerHTML = '<option value="">Could not load catalogs — see console</option>';
        return;
      }
      if (!d.catalogs.length) { sel.innerHTML = '<option value="">No catalogs found — integration pending confirmation, ask your developer</option>'; return; }
      sel.innerHTML = '<option value="">— Select —</option>' + d.catalogs.map(function(c) {
        return '<option value="' + c.id + '">' + esc(c.path.split('/').pop()) + ' (' + new Date(c.created_at*1000).toLocaleDateString() + ')</option>';
      }).join('');
    })
    .catch(function(err) {
      console.error('mktwLoadCatalogPicker: network error', err);
      sel.innerHTML = '<option value="">Network error</option>';
    });
}
// ── Step 4: schedule ────────────────────────────────────────────────────
function mktwPickScheduleType(type, radio) {
  document.querySelectorAll('#mktwScheduleCards .mktw-audience-card').forEach(function(c){ c.classList.remove('selected'); });
  if (radio) radio.closest('.mktw-audience-card').classList.add('selected');
  document.getElementById('mktwScheduleOnce').style.display = type === 'once' ? '' : 'none';
  document.getElementById('mktwScheduleRecurring').style.display = type === 'recurring' ? '' : 'none';
}

// ── Save draft (called before every Next + before test send) ───────────
function mktwSaveDraft(cb) {
  var body = new URLSearchParams();
  body.set('action', 'marketing_wizard_save');
  if (mktwCampaignId) body.set('campaign_id', mktwCampaignId);
  body.set('name', document.getElementById('mktwName').value.trim());
  body.set('channel', mktwGetChannel());
  var waTpl = document.getElementById('mktwWaTemplate').value;
  var emailTpl = document.getElementById('mktwEmailTemplate').value;
  if (waTpl) body.set('template_id', waTpl);
  if (emailTpl) body.set('email_template_id', emailTpl);
body.set('audience', JSON.stringify(mktwBuildAudienceConfig()));
  body.set('variables', JSON.stringify(mktwCollectStaticVars()));
  var attachType = document.querySelector('input[name="mktw_attach_type"]:checked').value;
  if (attachType === 'static') body.set('attach_catalog_id', document.getElementById('mktwAttachCatalogSelect').value);
  if (attachType === 'selection') body.set('attach_selection_pdf', '1');
  body.set('csrf_token', mktwCsrf);

  fetch('index.php?page=marketing_campaign_wizard', { method:'POST', body: body })
    .then(function(r){return r.json();}).then(function(d) {
      if (d.success) {
        mktwCampaignId = d.campaign_id;
        if (!location.search.includes('id=')) {
          history.replaceState(null, '', 'index.php?page=marketing_campaign_wizard&id=' + mktwCampaignId);
        }
        cb(true);
      } else {
        alert(d.error || 'Could not save campaign.');
        cb(false);
      }
    })
    .catch(function() { alert('Save failed — check your connection.'); cb(false); });
}

// ── Step 5: review ──────────────────────────────────────────────────────
function mktwLoadReview() {
  document.getElementById('mktwReviewLoading').style.display = '';
  document.getElementById('mktwReviewContent').style.display = 'none';
  var body = new URLSearchParams();
  body.set('action', 'noop');
  body.set('campaign_id', mktwCampaignId);
  body.set('csrf_token', mktwCsrf);

  fetch('index.php?page=marketing_campaign_wizard&ajax_review=1', { method:'POST', body: body })
    .then(function(r) { return r.text(); })
    .then(function(raw) {
      document.getElementById('mktwReviewLoading').style.display = 'none';
      document.getElementById('mktwReviewContent').style.display = '';

      var d = null;
      try { d = JSON.parse(raw); } catch (e) {}

      if (!d) {
        console.error('mktwLoadReview: server did not return JSON. Raw response:', raw);
        document.getElementById('mktwBreakdownOutput').innerHTML =
          '<p style="color:var(--danger,#E84040);font-size:12px;">Server returned something that isn\'t JSON. ' +
          'Raw response (also logged to console) — copy this to your developer:</p>' +
          '<pre style="white-space:pre-wrap;font-size:11px;background:#f4f1ec;padding:8px;border-radius:6px;max-height:180px;overflow:auto;">' +
          esc(raw.slice(0, 1500)) + '</pre>';
        return;
      }

      if (!d.success) {
        document.getElementById('mktwBreakdownOutput').textContent = d.error || 'Unknown error.';
        return;
      }

      var bOut = '<p style="font-size:13px;margin-bottom:6px;"><strong>' + d.breakdown.total + '</strong> total matched contact(s)</p>';
      Object.keys(d.breakdown.channels).forEach(function(ch) {
        var b = d.breakdown.channels[ch];
        bOut += '<div style="margin-top:8px;font-size:12px;"><strong>' + esc(ch) + ':</strong><br/>' +
          '✓ ' + b.eligible + ' eligible &nbsp; · &nbsp; ⊘ ' + b.optedOut + ' opted-out &nbsp; · &nbsp; ⚠ ' + b.invalid + ' missing contact info &nbsp; · &nbsp; 🚫 ' + b.suppressed + ' suppressed</div>';
      });
      document.getElementById('mktwBreakdownOutput').innerHTML = bOut;

      var durOut = '';
      Object.keys(d.durations).forEach(function(ch) {
        durOut += '<div style="font-size:12px;margin-bottom:6px;"><strong>' + esc(ch) + ':</strong> ' + esc(d.durations[ch].label) + '</div>';
      });
      durOut += '<div style="font-size:11px;color:var(--admin-text3,var(--text3));margin-top:8px;">' +
        'WhatsApp: ' + d.limits.whatsapp_hourly + '/hr, ' + d.limits.whatsapp_daily + '/day &nbsp;·&nbsp; Email: ' + d.limits.email_hourly + '/hr, ' + d.limits.email_daily + '/day</div>';
      document.getElementById('mktwDurationOutput').innerHTML = durOut;

      var warnEl = document.getElementById('mktwConcurrentWarning');
      if (d.concurrent.max > 0 && d.concurrent.current >= d.concurrent.max) {
        warnEl.style.display = 'block';
        warnEl.textContent = '⚠ Concurrent campaign limit reached (' + d.concurrent.current + '/' + d.concurrent.max + '). This campaign cannot start until another finishes or is cancelled.';
      } else { warnEl.style.display = 'none'; }
    })
    .catch(function(err) {
      document.getElementById('mktwReviewLoading').style.display = 'none';
      document.getElementById('mktwReviewContent').style.display = '';
      console.error('mktwLoadReview: network error', err);
      document.getElementById('mktwBreakdownOutput').textContent = 'Network error — could not reach the server.';
    });
}

var confirmCheck = document.getElementById('mktwConfirmCheck');
if (confirmCheck) confirmCheck.addEventListener('change', function() {
  document.getElementById('mktwConfirmSendBtn').disabled = !this.checked;
});

function mktwConfirmAndSend() {
  var scheduleType = document.querySelector('input[name="mktw_schedule_type"]:checked').value;
  var body = new URLSearchParams();
  body.set('action', 'marketing_schedule_campaign');
  body.set('campaign_id', mktwCampaignId);
  body.set('schedule_type', scheduleType);
  body.set('timezone', document.getElementById('mktwTimezone').value.trim() || 'Asia/Kolkata');
  if (scheduleType === 'once') body.set('scheduled_at', document.getElementById('mktwScheduledAt').value);
  if (scheduleType === 'recurring') {
    body.set('scheduled_at', document.getElementById('mktwRecurStart').value);
    body.set('recurrence', JSON.stringify({ freq: document.getElementById('mktwRecurFreq').value }));
  }
  body.set('csrf_token', mktwCsrf);

  var btn = document.getElementById('mktwConfirmSendBtn');
  btn.disabled = true; btn.textContent = 'Processing…';

  fetch('index.php', { method:'POST', body: body })
    .then(function(r){return r.json();}).then(function(d) {
      var resultEl = document.getElementById('mktwFinalResult');
      resultEl.style.display = 'block';
      if (d.success) {
        resultEl.style.background = 'var(--success-bg,#D8EFE6)'; resultEl.style.color = 'var(--success,#3D8B6E)';
        resultEl.innerHTML = '✓ Campaign ' + (d.status === 'running' ? 'is now running' : 'scheduled') + '. ' + d.enqueued + ' of ' + d.audience_size + ' contact(s) enqueued.' +
          '<br/><a href="index.php?page=marketing_campaigns" style="color:inherit;text-decoration:underline;">Back to Campaigns</a>';
      } else {
        resultEl.style.background = 'var(--danger-bg,#FFF0F0)'; resultEl.style.color = 'var(--danger,#E84040)';
        resultEl.textContent = '✗ ' + (d.error || 'Could not schedule campaign.');
        btn.disabled = false; btn.textContent = 'Confirm & Schedule';
      }
    });
}

function mktwSubmitForApproval() {
  var body = new URLSearchParams();
  body.set('action', 'marketing_submit_campaign_approval');
  body.set('campaign_id', mktwCampaignId);
  body.set('csrf_token', mktwCsrf);
  fetch('index.php', { method:'POST', body: body }).then(function(){ location.href = 'index.php?page=marketing_campaigns'; });
}
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>