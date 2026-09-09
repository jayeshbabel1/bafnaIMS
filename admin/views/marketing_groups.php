<?php
/**
 * admin/views/marketing_groups.php — Fire 3
 */
require_once BASE_PATH . '/includes/marketing_contacts.php';

$adminTitle = 'Marketing Groups';
requireAdminPermission('marketing.groups.manage');
include __DIR__ . '/../_layout_top.php';

$groups = getMarketingGroups();
$allTags = getAllMarketingTags();
?>
<style>
.mktg-grid{display:grid;grid-template-columns:1fr;gap:12px;}
@media(min-width:768px){.mktg-grid{grid-template-columns:repeat(2,1fr);}}
@media(min-width:1200px){.mktg-grid{grid-template-columns:repeat(3,1fr);}}
.mktg-card{background:var(--admin-card-bg,var(--surface));border:1px solid var(--admin-table-border,var(--border));border-radius:12px;padding:16px;}
.mktg-filter-row{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:8px;margin-bottom:8px;align-items:center;}
.mkt-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9300;align-items:center;justify-content:center;padding:16px;}
.mkt-modal.open{display:flex;}
.mkt-modal-card{background:var(--admin-card-bg,var(--surface));border-radius:14px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 16px 48px rgba(0,0,0,.2);}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:8px;">
  <p style="font-size:13px;color:var(--admin-text3,var(--text3));">
    <?= count($groups) ?> group(s). Static groups hold a fixed manual list (add contacts from the Contacts page). Dynamic groups auto-resolve from live filter rules — membership is recalculated at send time, never a snapshot.
  </p>
  <button type="button" class="btn-admin-primary" onclick="mktgOpenCreate()"><?= icon('plus',14) ?> New Group</button>
</div>

<div class="mktg-grid">
  <?php foreach ($groups as $g): ?>
  <div class="mktg-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
      <p style="font-weight:700;font-size:14px;"><?= h($g['name']) ?></p>
      <span class="badge <?= $g['type']==='dynamic'?'badge-gold':'badge-blue' ?>" style="font-size:9px;"><?= ucfirst($g['type']) ?></span>
    </div>
    <p style="font-size:12px;color:var(--admin-text3,var(--text3));margin-bottom:12px;"><?= $g['contact_count'] ?> contact(s)</p>
    <div style="display:flex;gap:8px;">
      <a href="index.php?page=marketing_contacts&group_id=<?= $g['id'] ?>" class="btn-admin-secondary btn-admin-sm"><?= icon('eye',13) ?> View Contacts</a>
      <button type="button" class="btn-admin-secondary btn-admin-sm" onclick='mktgOpenEdit(<?= json_encode($g) ?>)'><?= icon('edit',13) ?></button>
      <form method="POST" action="index.php" style="display:inline;">
        <input type="hidden" name="action" value="marketing_delete_group"/>
        <input type="hidden" name="group_id" value="<?= $g['id'] ?>"/>
        <?= csrfField() ?>
        <button type="submit" class="btn-admin-danger btn-admin-sm" data-confirm="Delete group '<?= h(addslashes($g['name'])) ?>'?"><?= icon('trash',13) ?></button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($groups)): ?>
  <p style="color:var(--admin-text3,var(--text3));font-size:13px;">No groups yet. Click "New Group" to create one.</p>
  <?php endif; ?>
</div>

<!-- Create/Edit group modal -->
<div id="mktgModal" class="mkt-modal">
  <div class="mkt-modal-card">
    <div style="display:flex;justify-content:space-between;padding:18px 20px;border-bottom:1px solid var(--admin-table-border,var(--border));">
      <p id="mktgModalTitle" style="font-weight:700;font-size:16px;">New Group</p>
      <button type="button" onclick="document.getElementById('mktgModal').classList.remove('open')" style="background:none;border:none;color:var(--admin-text3,var(--text3));cursor:pointer;"><?= icon('close',18) ?></button>
    </div>
    <div style="padding:20px;">
      <form method="POST" action="index.php" id="mktgForm">
        <input type="hidden" name="action" id="mktgFormAction" value="marketing_create_group"/>
        <input type="hidden" name="group_id" id="mktgGroupId" value=""/>
        <input type="hidden" name="filter_json" id="mktgFilterJson" value=""/>
        <?= csrfField() ?>
        <div style="margin-bottom:14px;">
          <label class="admin-label">Group Name *</label>
          <input type="text" name="name" id="mktgName" class="admin-input" required/>
        </div>
        <div style="margin-bottom:14px;" id="mktgTypeRow">
          <label class="admin-label">Type</label>
          <select name="type" id="mktgType" class="admin-input admin-select" onchange="mktgToggleType()">
            <option value="static">Static — manually managed list</option>
            <option value="dynamic">Dynamic — auto-filter rules</option>
          </select>
        </div>

        <div id="mktgFilterBuilder" style="display:none;background:var(--admin-surface2,var(--surface2));border-radius:10px;padding:14px;margin-bottom:14px;">
          <p style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--admin-text3,var(--text3));margin-bottom:10px;">Match</p>
          <select id="mktgMatchType" class="admin-input admin-select" style="max-width:140px;margin-bottom:12px;">
            <option value="all">ALL rules (AND)</option>
            <option value="any">ANY rule (OR)</option>
          </select>
          <div id="mktgRulesList"></div>
          <button type="button" class="btn-admin-secondary btn-admin-sm" onclick="mktgAddRule()"><?= icon('plus',12) ?> Add Rule</button>
        </div>

        <button type="submit" class="btn-admin-primary" style="width:100%;justify-content:center;" onclick="mktgSerializeFilter()"><?= icon('check',15) ?> Save Group</button>
      </form>
    </div>
  </div>
</div>

<script>
var MKTG_TAGS = <?= json_encode(array_map(fn($t)=>['id'=>$t['id'],'name'=>$t['name']], $allTags)) ?>;
var MKTG_FIELDS = [
  { key: 'city', label: 'City', type: 'text' },
  { key: 'role', label: 'Role', type: 'select', options: ['architect','interior_designer','contractor','developer','retailer','other'] },
  { key: 'experience', label: 'Experience', type: 'select', options: ['0–2 years','3–5 years','6–10 years','10+ years'] },
  { key: 'status', label: 'Status', type: 'select', options: ['active','inactive'] },
  { key: 'whatsapp_opt_in', label: 'WhatsApp Opt-in', type: 'select', options: ['1','0'] },
  { key: 'email_opt_in', label: 'Email Opt-in', type: 'select', options: ['1','0'] },
  { key: 'source_type', label: 'Source', type: 'select', options: ['user','client','manual','import'] },
  { key: 'tag', label: 'Has Tag', type: 'tagmulti' },
];

function mktgRuleRowHtml(idx, rule) {
  rule = rule || { field: 'city', op: '=', value: '' };
  var fieldOpts = MKTG_FIELDS.map(function (f) {
    return '<option value="' + f.key + '"' + (f.key === rule.field ? ' selected' : '') + '>' + f.label + '</option>';
  }).join('');
  return '<div class="mktg-filter-row" data-idx="' + idx + '">' +
    '<select class="admin-input admin-select mktg-rule-field" onchange="mktgRenderValueInput(this)">' + fieldOpts + '</select>' +
    '<select class="admin-input admin-select mktg-rule-op"><option value="=">is</option><option value="!=">is not</option><option value="contains">contains</option></select>' +
    '<div class="mktg-rule-value-wrap"></div>' +
    '<button type="button" class="btn-admin-danger btn-admin-sm" onclick="this.closest(\'.mktg-filter-row\').remove()">×</button>' +
  '</div>';
}
function mktgValueInputHtml(fieldKey, currentValue) {
  var field = MKTG_FIELDS.find(function (f) { return f.key === fieldKey; });
  if (!field) return '<input type="text" class="admin-input mktg-rule-value"/>';
  if (field.type === 'select') {
    return '<select class="admin-input admin-select mktg-rule-value">' +
      field.options.map(function (o) { return '<option value="' + o + '"' + (o === currentValue ? ' selected' : '') + '>' + o + '</option>'; }).join('') +
      '</select>';
  }
  if (field.type === 'tagmulti') {
    return '<select class="admin-input admin-select mktg-rule-value" multiple size="1">' +
      MKTG_TAGS.map(function (t) { return '<option value="' + t.id + '">' + t.name + '</option>'; }).join('') +
      '</select>';
  }
  return '<input type="text" class="admin-input mktg-rule-value" value="' + (currentValue || '') + '"/>';
}
function mktgRenderValueInput(fieldSelect) {
  var row = fieldSelect.closest('.mktg-filter-row');
  row.querySelector('.mktg-rule-value-wrap').innerHTML = mktgValueInputHtml(fieldSelect.value, '');
}
function mktgAddRule(rule) {
  var list = document.getElementById('mktgRulesList');
  var idx = list.children.length;
  var wrap = document.createElement('div');
  wrap.innerHTML = mktgRuleRowHtml(idx, rule);
  var rowEl = wrap.firstElementChild;
  list.appendChild(rowEl);
  rowEl.querySelector('.mktg-rule-value-wrap').innerHTML = mktgValueInputHtml((rule && rule.field) || 'city', (rule && rule.value) || '');
  if (rule && rule.op) rowEl.querySelector('.mktg-rule-op').value = rule.op;
}
function mktgToggleType() {
  var isDynamic = document.getElementById('mktgType').value === 'dynamic';
  document.getElementById('mktgFilterBuilder').style.display = isDynamic ? 'block' : 'none';
}
function mktgSerializeFilter() {
  if (document.getElementById('mktgType').value !== 'dynamic') { document.getElementById('mktgFilterJson').value = ''; return; }
  var rules = [];
  document.querySelectorAll('#mktgRulesList .mktg-filter-row').forEach(function (row) {
    var field = row.querySelector('.mktg-rule-field').value;
    var op = row.querySelector('.mktg-rule-op').value;
    var valEl = row.querySelector('.mktg-rule-value');
    var value = valEl.multiple ? Array.from(valEl.selectedOptions).map(function (o) { return o.value; }) : valEl.value;
    rules.push({ field: field, op: op, value: value });
  });
  document.getElementById('mktgFilterJson').value = JSON.stringify({
    match: document.getElementById('mktgMatchType').value, rules: rules,
  });
}
function mktgOpenCreate() {
  document.getElementById('mktgModalTitle').textContent = 'New Group';
  document.getElementById('mktgFormAction').value = 'marketing_create_group';
  document.getElementById('mktgGroupId').value = '';
  document.getElementById('mktgName').value = '';
  document.getElementById('mktgType').value = 'static';
  document.getElementById('mktgType').disabled = false;
  document.getElementById('mktgRulesList').innerHTML = '';
  mktgToggleType();
  document.getElementById('mktgModal').classList.add('open');
}
function mktgOpenEdit(group) {
  document.getElementById('mktgModalTitle').textContent = 'Edit Group';
  document.getElementById('mktgFormAction').value = 'marketing_update_group';
  document.getElementById('mktgGroupId').value = group.id;
  document.getElementById('mktgName').value = group.name;
  document.getElementById('mktgType').value = group.type;
  document.getElementById('mktgType').disabled = true; // type is fixed after creation
  document.getElementById('mktgRulesList').innerHTML = '';
  if (group.type === 'dynamic') {
    var filter = {};
    try { filter = JSON.parse(group.filter_json || '{}'); } catch (e) {}
    document.getElementById('mktgMatchType').value = filter.match || 'all';
    (filter.rules || []).forEach(function (r) { mktgAddRule(r); });
  }
  mktgToggleType();
  document.getElementById('mktgModal').classList.add('open');
}
document.getElementById('mktgModal').addEventListener('click', function (e) {
  if (e.target === this) this.classList.remove('open');
});
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>