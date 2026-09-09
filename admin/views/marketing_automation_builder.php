<?php
/**
 * admin/views/marketing_automation_builder.php — Fire 11
 */
require_once BASE_PATH . '/includes/marketing_automation.php';

$adminTitle = 'Edit Automation';
requireAdminPermission('marketing.automation.manage');

$automationId = (int)($_GET['id'] ?? 0);
$automation = getMarketingAutomation($automationId);
if (!$automation) {
    include __DIR__ . '/../_layout_top.php';
    echo '<div class="admin-form-section"><p>Automation not found.</p><a href="index.php?page=marketing_automations" class="btn-admin-secondary">Back</a></div>';
    include __DIR__ . '/../_layout_bottom.php';
    exit;
}
include __DIR__ . '/../_layout_top.php';

$triggerRegistry = marketingAutomationTriggerRegistry();
$allGroups = getMarketingGroups();
$allTags = getAllMarketingTags();
$waTemplates = getMarketingTemplates(['channel' => 'whatsapp', 'limit' => 200])['rows'];
$emailTemplates = getMarketingTemplates(['channel' => 'email', 'limit' => 200])['rows'];
?>
<style>
.mkto-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9300;align-items:center;justify-content:center;padding:16px;}
.mkto-modal.open{display:flex;}
.mkto-modal-card{background:var(--admin-card-bg,var(--surface));border-radius:14px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;padding:22px;}
.mkto-step-card{display:flex;align-items:center;gap:12px;background:var(--admin-card-bg,var(--surface));border:1px solid var(--admin-table-border,var(--border));border-radius:10px;padding:14px;margin-bottom:8px;}
.mkto-step-num{width:26px;height:26px;border-radius:50%;background:var(--admin-accent,var(--accent));color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;}
.mkto-step-icon{font-size:11px;text-transform:uppercase;font-weight:700;color:var(--admin-text3,var(--text3));}
</style>

<a href="index.php?page=marketing_automations" style="font-size:12px;display:inline-block;margin-bottom:14px;"><?= icon('chevron-left',12) ?> Back to Automations</a>

<div class="admin-form-section">
  <form method="POST" action="index.php" id="mktoSettingsForm">
    <input type="hidden" name="action" value="marketing_update_automation"/>
    <input type="hidden" name="automation_id" value="<?= $automation['id'] ?>"/>
    <input type="hidden" name="audience_json" id="mktoAudienceJson" value="<?= h(json_encode($automation['audience'])) ?>"/>
    <?= csrfField() ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
      <div><label class="admin-label">Name *</label><input type="text" name="name" class="admin-input" value="<?= h($automation['name']) ?>" required/></div>
      <div>
        <label class="admin-label">Trigger *</label>
        <select name="trigger_type" class="admin-input admin-select">
          <?php foreach ($triggerRegistry as $key => $t): ?>
          <option value="<?= $key ?>" <?= $automation['trigger_type'] === $key ? 'selected' : '' ?>><?= h($t['label']) ?><?= $t['scope'] === 'broadcast' ? ' (broadcast)' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <p class="admin-form-section-title" style="border:none;padding:0;margin-bottom:8px;">Audience</p>
    <p style="font-size:11px;color:var(--admin-text3,var(--text3));margin-bottom:10px;">
      For contact-scoped triggers (e.g. a new contact), this filters WHICH matching contacts get enrolled.
      For broadcast triggers (e.g. new catalog), this defines the ENTIRE recipient list.
    </p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
      <?php foreach (['all'=>'All Contacts','groups'=>'Group(s)','tags'=>'Tag(s)'] as $val=>$label): ?>
      <label style="display:flex;align-items:center;gap:6px;font-size:12px;">
        <input type="radio" name="mkto_audience_type" value="<?= $val ?>" <?= $automation['audience']['type'] === $val ? 'checked' : '' ?> onchange="mktoAudienceTypeChanged()"/> <?= $label ?>
      </label>
      <?php endforeach; ?>
    </div>
    <div id="mktoAudienceGroups" style="display:none;margin-bottom:12px;">
      <select id="mktoGroupSelect" class="admin-input admin-select" multiple size="4">
        <?php foreach ($allGroups as $g): $sel = in_array($g['id'], $automation['audience']['group_ids'] ?? []); ?>
        <option value="<?= $g['id'] ?>" <?= $sel?'selected':'' ?>><?= h($g['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div id="mktoAudienceTags" style="display:none;margin-bottom:12px;">
      <select id="mktoTagSelect" class="admin-input admin-select" multiple size="4">
        <?php foreach ($allTags as $t): $sel = in_array($t['id'], $automation['audience']['tag_ids'] ?? []); ?>
        <option value="<?= $t['id'] ?>" <?= $sel?'selected':'' ?>><?= h($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <button type="submit" class="btn-admin-primary" onclick="mktoSerializeAudience()"><?= icon('check',14) ?> Save Settings</button>
  </form>
</div>

<div class="admin-form-section">
  <p class="admin-form-section-title">Steps</p>
  <?php if (empty($automation['steps'])): ?>
  <p style="font-size:13px;color:var(--admin-text3,var(--text3));margin-bottom:12px;">No steps yet — this automation won't do anything until you add at least one.</p>
  <?php endif; ?>
  <div id="mktoStepsList">
    <?php foreach ($automation['steps'] as $i => $s): ?>
    <div class="mkto-step-card">
      <div class="mkto-step-num"><?= $i + 1 ?></div>
      <div style="flex:1;">
        <p class="mkto-step-icon"><?= strtoupper($s['step_type']) ?></p>
        <p style="font-size:13px;"><?= h(mktoDescribeStep($s)) ?></p>
      </div>
      <div style="display:flex;gap:4px;">
        <form method="POST" action="index.php"><input type="hidden" name="action" value="marketing_automation_move_step"/><input type="hidden" name="step_id" value="<?= $s['id'] ?>"/><input type="hidden" name="direction" value="up"/><input type="hidden" name="automation_id" value="<?= $automation['id'] ?>"/><?= csrfField() ?><button type="submit" class="btn-admin-secondary btn-admin-sm" <?= $i===0?'disabled':'' ?>>↑</button></form>
        <form method="POST" action="index.php"><input type="hidden" name="action" value="marketing_automation_move_step"/><input type="hidden" name="step_id" value="<?= $s['id'] ?>"/><input type="hidden" name="direction" value="down"/><input type="hidden" name="automation_id" value="<?= $automation['id'] ?>"/><?= csrfField() ?><button type="submit" class="btn-admin-secondary btn-admin-sm" <?= $i===count($automation['steps'])-1?'disabled':'' ?>>↓</button></form>
        <button type="button" class="btn-admin-secondary btn-admin-sm" onclick='mktoEditStep(<?= json_encode($s) ?>)'><?= icon('edit',12) ?></button>
        <form method="POST" action="index.php"><input type="hidden" name="action" value="marketing_automation_delete_step"/><input type="hidden" name="step_id" value="<?= $s['id'] ?>"/><input type="hidden" name="automation_id" value="<?= $automation['id'] ?>"/><?= csrfField() ?><button type="submit" class="btn-admin-danger btn-admin-sm"><?= icon('trash',12) ?></button></form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;gap:8px;margin-top:10px;">
    <button type="button" class="btn-admin-secondary btn-admin-sm" onclick="mktoOpenStep('condition')"><?= icon('plus',12) ?> Condition</button>
    <button type="button" class="btn-admin-secondary btn-admin-sm" onclick="mktoOpenStep('wait')"><?= icon('plus',12) ?> Wait</button>
    <button type="button" class="btn-admin-secondary btn-admin-sm" onclick="mktoOpenStep('action')"><?= icon('plus',12) ?> Action</button>
  </div>
</div>

<!-- Step editor modal -->
<div id="mktoStepModal" class="mkto-modal">
  <div class="mkto-modal-card">
    <p id="mktoStepTitle" style="font-weight:700;font-size:16px;margin-bottom:16px;">Add Step</p>
    <form method="POST" action="index.php" id="mktoStepForm">
      <input type="hidden" name="action" id="mktoStepFormAction" value="marketing_automation_add_step"/>
      <input type="hidden" name="automation_id" value="<?= $automation['id'] ?>"/>
      <input type="hidden" name="step_id" id="mktoStepId" value=""/>
      <input type="hidden" name="step_type" id="mktoStepType" value=""/>
      <?= csrfField() ?>

      <div id="mktoConditionFields" style="display:none;">
        <div style="margin-bottom:12px;">
          <label class="admin-label">Field</label>
          <select name="field" class="admin-input admin-select">
            <option value="city">City</option><option value="role">Role</option><option value="experience">Experience</option>
            <option value="status">Status</option><option value="whatsapp_opt_in">WhatsApp Opt-in</option>
            <option value="email_opt_in">Email Opt-in</option><option value="source_type">Source</option><option value="tag">Has Tag</option>
          </select>
        </div>
        <div style="margin-bottom:12px;">
          <label class="admin-label">Comparison</label>
          <select name="op" class="admin-input admin-select"><option value="=">is</option><option value="!=">is not</option><option value="contains">contains</option></select>
        </div>
        <div style="margin-bottom:12px;"><label class="admin-label">Value</label><input type="text" name="value" class="admin-input" placeholder="e.g. Mumbai, or a tag ID"/></div>
        <div style="margin-bottom:16px;">
          <label class="admin-label">If condition fails</label>
          <select name="on_false" class="admin-input admin-select"><option value="stop">Stop this contact's chain</option><option value="skip">Skip to next step anyway</option></select>
        </div>
      </div>

      <div id="mktoWaitFields" style="display:none;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
          <div><label class="admin-label">Wait</label><input type="number" name="value" class="admin-input" min="1" value="1"/></div>
          <div><label class="admin-label">Unit</label><select name="unit" class="admin-input admin-select"><option value="minutes">Minutes</option><option value="hours" selected>Hours</option><option value="days">Days</option></select></div>
        </div>
      </div>

      <div id="mktoActionFields" style="display:none;">
        <div style="margin-bottom:12px;">
          <label class="admin-label">Action</label>
          <select name="action_type" class="admin-input admin-select" onchange="mktoActionTypeChanged(this.value)">
            <option value="send_whatsapp">Send WhatsApp Template</option>
            <option value="send_email">Send Email Template</option>
            <option value="add_tag">Add Tag</option>
            <option value="add_to_group">Add to Group</option>
          </select>
        </div>
        <div id="mktoActionWaTemplate" style="margin-bottom:16px;">
          <label class="admin-label">WhatsApp Template</label>
          <select name="template_id" class="admin-input admin-select">
            <option value="">— Select —</option>
            <?php foreach ($waTemplates as $t): ?><option value="<?= $t['id'] ?>"><?= h($t['name']) ?> (<?= h($t['approval_status']) ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div id="mktoActionEmailTemplate" style="display:none;margin-bottom:16px;">
          <label class="admin-label">Email Template</label>
          <select name="email_template_id" class="admin-input admin-select">
            <option value="">— Select —</option>
            <?php foreach ($emailTemplates as $t): ?><option value="<?= $t['id'] ?>"><?= h($t['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div id="mktoActionTag" style="display:none;margin-bottom:16px;">
          <label class="admin-label">Tag</label>
          <select name="tag_id" class="admin-input admin-select"><option value="">— Select —</option><?php foreach ($allTags as $t): ?><option value="<?= $t['id'] ?>"><?= h($t['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div id="mktoActionGroup" style="display:none;margin-bottom:16px;">
          <label class="admin-label">Group</label>
          <select name="group_id" class="admin-input admin-select"><option value="">— Select —</option><?php foreach ($allGroups as $g): if ($g['type']==='static'): ?><option value="<?= $g['id'] ?>"><?= h($g['name']) ?></option><?php endif; endforeach; ?></select>
        </div>
      </div>

      <div style="display:flex;gap:10px;">
        <button type="button" class="btn-admin-secondary" style="flex:1;" onclick="document.getElementById('mktoStepModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn-admin-primary" style="flex:1;justify-content:center;">Save Step</button>
      </div>
    </form>
  </div>
</div>

<?php
/** Human-readable one-liner for a step, shown in the step list. */
function mktoDescribeStep(array $s): string {
    $c = $s['config'];
    if ($s['step_type'] === 'condition') return "If {$c['field']} {$c['op']} \"{$c['value']}\" (else " . ($c['on_false'] ?? 'stop') . ")";
    if ($s['step_type'] === 'wait') return "Wait {$c['value']} " . ($c['unit'] ?? 'hours');
    if ($s['step_type'] === 'action') {
        return match ($c['action_type'] ?? '') {
            'send_whatsapp' => 'Send WhatsApp template #' . ($c['template_id'] ?? '?'),
            'send_email'    => 'Send email template #' . ($c['template_id'] ?? '?'),
            'add_tag'       => 'Add tag #' . ($c['tag_id'] ?? '?'),
            'add_to_group'  => 'Add to group #' . ($c['group_id'] ?? '?'),
            default         => 'Unknown action',
        };
    }
    return '';
}
?>

<script>
function mktoAudienceTypeChanged() {
  var type = document.querySelector('input[name="mkto_audience_type"]:checked').value;
  document.getElementById('mktoAudienceGroups').style.display = type === 'groups' ? '' : 'none';
  document.getElementById('mktoAudienceTags').style.display = type === 'tags' ? '' : 'none';
}
mktoAudienceTypeChanged();
function mktoSerializeAudience() {
  var type = document.querySelector('input[name="mkto_audience_type"]:checked').value;
  var cfg = { type: type };
  if (type === 'groups') cfg.group_ids = Array.from(document.getElementById('mktoGroupSelect').selectedOptions).map(function(o){return parseInt(o.value,10);});
  if (type === 'tags') cfg.tag_ids = Array.from(document.getElementById('mktoTagSelect').selectedOptions).map(function(o){return parseInt(o.value,10);});
  document.getElementById('mktoAudienceJson').value = JSON.stringify(cfg);
}

function mktoOpenStep(stepType) {
  document.getElementById('mktoStepTitle').textContent = 'Add ' + stepType.charAt(0).toUpperCase() + stepType.slice(1) + ' Step';
  document.getElementById('mktoStepFormAction').value = 'marketing_automation_add_step';
  document.getElementById('mktoStepId').value = '';
  document.getElementById('mktoStepType').value = stepType;
  document.getElementById('mktoConditionFields').style.display = stepType === 'condition' ? '' : 'none';
  document.getElementById('mktoWaitFields').style.display = stepType === 'wait' ? '' : 'none';
  document.getElementById('mktoActionFields').style.display = stepType === 'action' ? '' : 'none';
  document.getElementById('mktoStepModal').classList.add('open');
}
function mktoEditStep(step) {
  mktoOpenStep(step.step_type);
  document.getElementById('mktoStepTitle').textContent = 'Edit Step';
  document.getElementById('mktoStepFormAction').value = 'marketing_automation_update_step';
  document.getElementById('mktoStepId').value = step.id;
  var form = document.getElementById('mktoStepForm');
  var c = step.config;
  Object.keys(c).forEach(function(key) {
    var el = form.querySelector('[name="' + key + '"]');
    if (el) el.value = c[key];
  });
  if (step.step_type === 'action') mktoActionTypeChanged(c.action_type || 'send_whatsapp');
}
function mktoActionTypeChanged(val) {
  document.getElementById('mktoActionWaTemplate').style.display = val === 'send_whatsapp' ? '' : 'none';
  document.getElementById('mktoActionEmailTemplate').style.display = val === 'send_email' ? '' : 'none';
  document.getElementById('mktoActionTag').style.display = val === 'add_tag' ? '' : 'none';
  document.getElementById('mktoActionGroup').style.display = val === 'add_to_group' ? '' : 'none';
}
document.getElementById('mktoStepModal').addEventListener('click', function(e){ if (e.target === this) this.classList.remove('open'); });
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>