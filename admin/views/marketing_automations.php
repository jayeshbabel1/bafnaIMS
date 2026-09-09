<?php
/**
 * admin/views/marketing_automations.php — Fire 11
 */
require_once BASE_PATH . '/includes/marketing_automation.php';

$adminTitle = 'Marketing Automations';
requireAdminPermission('marketing.automation.manage');
include __DIR__ . '/../_layout_top.php';

$automations = getMarketingAutomations();
$triggerRegistry = marketingAutomationTriggerRegistry();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
  <p style="font-size:12px;color:var(--admin-text3,var(--text3));max-width:560px;">
    Automations wait for an event (a new contact, a client selection, a new catalog) and run a sequence of
    conditions/waits/actions for the matching contact(s). "New Catalog Generated" and "Client Selection Created"
    require a one-time hook in their respective app files — <a href="#" onclick="alert('client_selection_created and catalog_generated need a one-line trigger call added to the code that creates a selection / generates a catalog. Tell your developer which function that is and it can be wired in.'); return false;">details</a>.
  </p>
  <button type="button" class="btn-admin-primary" onclick="mktoOpenCreate()"><?= icon('plus',14) ?> New Automation</button>
</div>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr><th>Name</th><th>Trigger</th><th>Steps</th><th>Active Runs</th><th>Status</th><th style="width:220px;">Actions</th></tr></thead>
    <tbody>
    <?php if (empty($automations)): ?>
    <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--admin-text3,var(--text3));">No automations yet.</td></tr>
    <?php else: foreach ($automations as $a): ?>
    <tr>
      <td style="font-weight:600;"><?= h($a['name']) ?></td>
      <td><?= h($triggerRegistry[$a['trigger_type']]['label'] ?? $a['trigger_type']) ?></td>
      <td><?= $a['step_count'] ?></td>
      <td><?= $a['active_runs'] ?></td>
      <td><span class="badge <?= $a['is_active'] ? 'badge-green' : 'badge-gray' ?>"><?= $a['is_active'] ? 'Active' : 'Inactive' ?></span></td>
      <td>
        <div style="display:flex;gap:5px;flex-wrap:wrap;">
          <a href="index.php?page=marketing_automation_builder&id=<?= $a['id'] ?>" class="btn-admin-secondary btn-admin-sm"><?= icon('edit',13) ?> Edit</a>
          <a href="index.php?page=marketing_campaigns&automation_id=<?= $a['id'] ?>" class="btn-admin-secondary btn-admin-sm" title="View Sends"><?= icon('eye',13) ?></a>
          <form method="POST" action="index.php" style="display:inline;">
            <input type="hidden" name="action" value="marketing_toggle_automation"/>
            <input type="hidden" name="automation_id" value="<?= $a['id'] ?>"/>
            <input type="hidden" name="active" value="<?= $a['is_active'] ? '0' : '1' ?>"/>
            <?= csrfField() ?>
            <button type="submit" class="btn-admin-secondary btn-admin-sm"><?= $a['is_active'] ? 'Deactivate' : 'Activate' ?></button>
          </form>
          <form method="POST" action="index.php" style="display:inline;">
            <input type="hidden" name="action" value="marketing_delete_automation"/>
            <input type="hidden" name="automation_id" value="<?= $a['id'] ?>"/>
            <?= csrfField() ?>
            <button type="submit" class="btn-admin-danger btn-admin-sm" data-confirm="Delete '<?= h(addslashes($a['name'])) ?>'? Contacts mid-chain will be stopped."><?= icon('trash',13) ?></button>
          </form>
        </div>
      </td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<div id="mktoCreateModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9300;align-items:center;justify-content:center;padding:16px;">
  <div style="background:var(--admin-card-bg,var(--surface));border-radius:14px;width:100%;max-width:440px;padding:22px;">
    <p style="font-weight:700;font-size:16px;margin-bottom:16px;">New Automation</p>
    <form method="POST" action="index.php">
      <input type="hidden" name="action" value="marketing_create_automation"/>
      <?= csrfField() ?>
      <div style="margin-bottom:14px;"><label class="admin-label">Name *</label><input type="text" name="name" class="admin-input" required/></div>
      <div style="margin-bottom:18px;">
        <label class="admin-label">Trigger *</label>
        <select name="trigger_type" class="admin-input admin-select" required>
          <?php foreach ($triggerRegistry as $key => $t): ?><option value="<?= $key ?>"><?= h($t['label']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:10px;">
        <button type="button" class="btn-admin-secondary" style="flex:1;" onclick="document.getElementById('mktoCreateModal').style.display='none'">Cancel</button>
        <button type="submit" class="btn-admin-primary" style="flex:1;justify-content:center;">Create &amp; Edit Steps</button>
      </div>
    </form>
  </div>
</div>
<script>
function mktoOpenCreate() { document.getElementById('mktoCreateModal').style.display = 'flex'; }
document.getElementById('mktoCreateModal').addEventListener('click', function(e){ if (e.target === this) this.style.display = 'none'; });
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>