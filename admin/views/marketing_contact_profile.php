<?php
/**
 * admin/views/marketing_contact_profile.php — Fire 10
 */
require_once BASE_PATH . '/includes/marketing_analytics.php';
require_once BASE_PATH . '/includes/marketing_catalog_integration.php';

$contactId = (int)($_GET['id'] ?? 0);

if (!empty($_GET['ajax_history'])) {
    requireAdminPermissionJson('marketing.contacts.view');
    $perPage = 15; $page = max(1, (int)($_GET['p'] ?? 1));
    $history = getMarketingContactMessageHistory($contactId, ['limit' => $perPage, 'offset' => ($page-1)*$perPage]);
    header('Content-Type: application/json');
    echo json_encode(['rows' => $history['rows'], 'total' => $history['total'], 'pages' => max(1,(int)ceil($history['total']/$perPage)), 'current' => $page]);
    exit;
}

$adminTitle = 'Contact Profile';
requireAdminPermission('marketing.contacts.view');
include __DIR__ . '/../_layout_top.php';

$contact = getMarketingContact($contactId);
if (!$contact) {
    echo '<div class="admin-form-section"><p>Contact not found.</p><a href="index.php?page=marketing_contacts" class="btn-admin-secondary">Back to Contacts</a></div>';
    include __DIR__ . '/../_layout_bottom.php';
    exit;
}

$canManage = adminCan('marketing.contacts.manage');
$tagsSt = getDB()->prepare("SELECT t.* FROM marketing_tags t JOIN marketing_contact_tags mct ON mct.tag_id=t.id WHERE mct.contact_id=?");
$tagsSt->execute([$contactId]);
$contactTags = $tagsSt->fetchAll();
$allTags = getAllMarketingTags();
$memberships = getMarketingContactGroupMemberships($contactId);
$allStaticGroups = array_filter(getMarketingGroups(), fn($g) => $g['type'] === 'static');

$isSuppressedWa = isMarketingSuppressed($contactId, 'whatsapp');
$isSuppressedEmail = isMarketingSuppressed($contactId, 'email');
$sourceLabels = ['user' => 'App User', 'client' => 'Client', 'manual' => 'Manual Entry', 'import' => 'Imported'];
?>
<style>
.mktp-grid{display:grid;grid-template-columns:280px 1fr;gap:20px;}
@media(max-width:800px){.mktp-grid{grid-template-columns:1fr;}}
.mktp-card{background:var(--admin-card-bg,var(--surface));border:1px solid var(--admin-table-border,var(--border));border-radius:12px;padding:18px;margin-bottom:16px;}
.mktp-field{margin-bottom:12px;} .mktp-field label{font-size:10.5px;text-transform:uppercase;color:var(--admin-text3,var(--text3));display:block;margin-bottom:2px;}
.mktp-field p{font-size:13px;font-weight:600;margin:0;}
</style>

<a href="index.php?page=marketing_contacts" style="font-size:12px;display:inline-block;margin-bottom:14px;"><?= icon('chevron-left',12) ?> Back to Contacts</a>

<div class="mktp-grid">
  <div>
    <div class="mktp-card">
      <p style="font-weight:700;font-size:16px;margin-bottom:4px;"><?= h($contact['name']) ?></p>
      <span class="badge badge-gray" style="font-size:10px;"><?= h($sourceLabels[$contact['source_type']] ?? $contact['source_type']) ?><?= $contact['source_id'] ? ' #' . $contact['source_id'] : '' ?></span>
      <span class="badge <?= $contact['status']==='active'?'badge-green':'badge-gray' ?>" style="font-size:10px;"><?= ucfirst($contact['status']) ?></span>

      <div style="margin-top:16px;">
        <div class="mktp-field"><label>Mobile</label><p><?= h($contact['mobile'] ?: '—') ?></p></div>
        <div class="mktp-field"><label>Email</label><p><?= h($contact['email'] ?: '—') ?></p></div>
        <div class="mktp-field"><label>City</label><p><?= h($contact['city'] ?: '—') ?></p></div>
        <?php if (!empty($contact['role'])): ?><div class="mktp-field"><label>Role</label><p><?= h($contact['role']) ?></p></div><?php endif; ?>
      </div>

      <?php if ($canManage): ?>
      <form method="POST" action="index.php" style="margin-top:14px;border-top:1px solid var(--admin-table-border,var(--border));padding-top:14px;">
        <input type="hidden" name="action" value="marketing_update_contact"/>
        <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>"/>
        <input type="hidden" name="return_to" value="index.php?page=marketing_contact_profile&id=<?= $contact['id'] ?>"/>
        <?= csrfField() ?>
        <div class="mktp-field"><label>Name</label><input type="text" name="name" class="admin-input" value="<?= h((string)($contact['name'] ?? '')) ?>"/></div>
        <div class="mktp-field"><label>Mobile</label><input type="text" name="mobile" class="admin-input" value="<?= h((string)($contact['mobile'] ?? '')) ?>"/></div>
        <div class="mktp-field"><label>Email</label><input type="text" inputmode="email" name="email" class="admin-input" value="<?= h((string)($contact['email'] ?? '')) ?>" autocomplete="off" data-lpignore="true" data-form-type="other"/></div>
        <div class="mktp-field"><label>City</label><input type="text" name="city" class="admin-input" value="<?= h((string)($contact['city'] ?? '')) ?>"/></div>
        <div class="mktp-field">
          <label>Status</label>
          <select name="status" class="admin-input admin-select"><option value="active" <?= $contact['status']==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $contact['status']==='inactive'?'selected':'' ?>>Inactive</option></select>
        </div>
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;margin-bottom:6px;"><input type="checkbox" name="whatsapp_opt_in" value="1" <?= $contact['whatsapp_opt_in']?'checked':'' ?>/> WhatsApp Opt-in</label>
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;margin-bottom:10px;"><input type="checkbox" name="email_opt_in" value="1" <?= $contact['email_opt_in']?'checked':'' ?>/> Email Opt-in</label>
        <button type="submit" class="btn-admin-primary btn-admin-sm" style="width:100%;justify-content:center;"><?= icon('check',13) ?> Save</button>
      </form>
      <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
    <div class="mktp-card">
      <p class="admin-form-section-title" style="margin-bottom:10px;">Suppression (manual override)</p>
      <p style="font-size:11px;color:var(--admin-text3,var(--text3));margin-bottom:10px;">Independent of the opt-in checkboxes above — this is a hard block regardless of preference, e.g. for a compliance request.</p>
      <?php foreach (['whatsapp'=>$isSuppressedWa,'email'=>$isSuppressedEmail] as $ch=>$suppressed): ?>
      <form method="POST" action="index.php" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <input type="hidden" name="action" value="marketing_toggle_suppression"/>
        <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>"/>
        <input type="hidden" name="channel" value="<?= $ch ?>"/>
        <input type="hidden" name="suppress" value="<?= $suppressed ? '0' : '1' ?>"/>
        <?= csrfField() ?>
        <span style="font-size:12px;text-transform:capitalize;"><?= $ch ?></span>
        <button type="submit" class="btn-admin-<?= $suppressed?'secondary':'danger' ?> btn-admin-sm"><?= $suppressed ? 'Un-suppress' : 'Suppress' ?></button>
      </form>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="mktp-card">
      <p class="admin-form-section-title" style="margin-bottom:10px;">Tags</p>
      <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:<?= $canManage ? '10px' : '0' ?>;">
        <?php foreach ($contactTags as $t): ?>
        <span class="badge badge-blue" style="font-size:10px;">
          <?= h($t['name']) ?>
          <?php if ($canManage): ?>
          <form method="POST" action="index.php" style="display:inline;">
            <input type="hidden" name="action" value="marketing_contact_remove_tag"/>
            <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>"/>
            <input type="hidden" name="tag_id" value="<?= $t['id'] ?>"/>
            <?= csrfField() ?>
            <button type="submit" style="background:none;border:none;cursor:pointer;color:inherit;font-size:10px;">×</button>
          </form>
          <?php endif; ?>
        </span>
        <?php endforeach; ?>
        <?php if (empty($contactTags)): ?><span style="font-size:12px;color:var(--admin-text3,var(--text3));">No tags.</span><?php endif; ?>
      </div>
      <?php if ($canManage): ?>
      <form method="POST" action="index.php" style="display:flex;gap:6px;">
        <input type="hidden" name="action" value="marketing_contact_add_tag"/>
        <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>"/>
        <?= csrfField() ?>
        <select name="tag_id" class="admin-input admin-select" style="flex:1;"><option value="">Add tag…</option><?php foreach ($allTags as $t): ?><option value="<?= $t['id'] ?>"><?= h($t['name']) ?></option><?php endforeach; ?></select>
        <button type="submit" class="btn-admin-secondary btn-admin-sm">Add</button>
      </form>
      <?php endif; ?>
    </div>

    <div class="mktp-card">
      <p class="admin-form-section-title" style="margin-bottom:10px;">Groups (static)</p>
      <div style="margin-bottom:<?= $canManage ? '10px' : '0' ?>;">
        <?php foreach ($memberships as $g): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;margin-bottom:6px;">
          <span><?= h($g['name']) ?></span>
          <?php if ($canManage): ?>
          <form method="POST" action="index.php">
            <input type="hidden" name="action" value="marketing_contact_remove_group"/>
            <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>"/>
            <input type="hidden" name="group_id" value="<?= $g['id'] ?>"/>
            <?= csrfField() ?>
            <button type="submit" class="btn-admin-danger btn-admin-sm">Remove</button>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($memberships)): ?><p style="font-size:12px;color:var(--admin-text3,var(--text3));">Not in any static group.</p><?php endif; ?>
      </div>
      <?php if ($canManage): ?>
      <form method="POST" action="index.php" style="display:flex;gap:6px;">
        <input type="hidden" name="action" value="marketing_contact_add_group"/>
        <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>"/>
        <?= csrfField() ?>
        <select name="group_id" class="admin-input admin-select" style="flex:1;"><option value="">Add to group…</option><?php foreach ($allStaticGroups as $g): ?><option value="<?= $g['id'] ?>"><?= h($g['name']) ?></option><?php endforeach; ?></select>
        <button type="submit" class="btn-admin-secondary btn-admin-sm">Add</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <?php
    $mktShowSelectionCard = false;
    $docTemplates = [];
    if ($contact['source_type'] === 'client' && $canManage) {
        try {
            $docTemplates = getMarketingTemplatesWithDocumentHeader();
            $mktShowSelectionCard = true;
        } catch (Throwable $e) {
            error_log('marketing_contact_profile: Send Selection Catalog card failed to load — ' . $e->getMessage());
        }
    }
    ?>
    <?php if ($mktShowSelectionCard): ?>
    <div class="mktp-card">
      <p class="admin-form-section-title" style="margin-bottom:10px;">Send Selection Catalog</p>
      <p style="font-size:11px;color:var(--admin-text3,var(--text3));margin-bottom:10px;">
        Generates this client's current selection PDF fresh and sends it now — bypasses the campaign system entirely.
      </p>
      <form method="POST" action="index.php" style="margin-bottom:8px;">
        <input type="hidden" name="action" value="marketing_send_selection_catalog"/>
        <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>"/>
        <input type="hidden" name="channel" value="email"/>
        <?= csrfField() ?>
        <button type="submit" class="btn-admin-secondary btn-admin-sm" style="width:100%;justify-content:center;" <?= $contact['email']?'':'disabled' ?>><?= icon('mail',13) ?> Send via Email<?= $contact['email']?'':' (no email on file)' ?></button>
      </form>
      <?php if (!empty($docTemplates)): ?>
      <form method="POST" action="index.php" style="display:flex;gap:6px;">
        <input type="hidden" name="action" value="marketing_send_selection_catalog"/>
        <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>"/>
        <input type="hidden" name="channel" value="whatsapp"/>
        <?= csrfField() ?>
        <select name="template_id" class="admin-input admin-select" style="flex:1;">
          <?php foreach ($docTemplates as $t): ?><option value="<?= $t['id'] ?>"><?= h($t['name']) ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="btn-admin-secondary btn-admin-sm"><?= icon('whatsapp',13) ?></button>
      </form>
      <?php else: ?>
      <p style="font-size:11px;color:var(--admin-text3,var(--text3));">No approved WhatsApp document-header templates yet — create one on the Templates page to send selections via WhatsApp.</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="mktp-card">
      <p class="admin-form-section-title" style="margin-bottom:10px;">Message History</p>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Date</th><th>Channel</th><th>Campaign</th><th>Status</th><th>Engagement</th></tr></thead>
          <tbody id="mktpHistoryTbody"><tr><td colspan="5" style="text-align:center;padding:20px;color:var(--admin-text3,var(--text3));">Loading…</td></tr></tbody>
        </table>
      </div>
      <div id="mktpPagWrap" class="admin-pagination" style="margin-top:12px;"></div>
    </div>
  </div>
</div>

<script>
function esc(s){var d=document.createElement('div');d.textContent=String(s==null?'':s);return d.innerHTML;}
var mktpPager = null;
function mktpLoadHistory(page) {
  fetch('index.php?page=marketing_contact_profile&id=<?= $contact['id'] ?>&ajax_history=1&p=' + (page||1))
    .then(function(r){return r.json();}).then(function(d) {
      var tbody = document.getElementById('mktpHistoryTbody');
      if (!d.rows.length) { tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px;color:var(--admin-text3,var(--text3));">No messages sent yet.</td></tr>'; return; }
      var statusBadge = { sent:'badge-blue', delivered:'badge-green', read:'badge-green', failed:'badge-red', bounced:'badge-red' };
      tbody.innerHTML = d.rows.map(function(m) {
        var date = new Date(m.created_at*1000).toLocaleDateString();
        var eng = [];
        if (m.events && m.events.opened) eng.push(m.events.opened + ' open(s)');
        if (m.events && m.events.clicked) eng.push(m.events.clicked + ' click(s)');
        return '<tr><td style="font-size:12px;">'+date+'</td><td>'+esc(m.channel)+'</td><td>'+esc(m.campaign_name||'—')+'</td>' +
          '<td><span class="badge '+(statusBadge[m.status]||'badge-gray')+'">'+esc(m.status)+'</span></td>' +
          '<td style="font-size:11px;color:var(--admin-text3,var(--text3));">'+(eng.length?eng.join(', '):'—')+'</td></tr>';
      }).join('');
      if (!mktpPager) mktpPager = initPagination({ wrapEl: document.getElementById('mktpPagWrap'), btnClass: 'apag-btn', onPage: mktpLoadHistory });
      mktpPager.render(d.current, d.pages);
    });
}
mktpLoadHistory(1);
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>