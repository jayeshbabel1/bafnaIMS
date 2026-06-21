<?php
/**
 * admin/views/users.php — PATCHED VERSION
 * Changes vs original:
 *  1. Verified column now uses a styled toggle switch (is_verified)
 *  2. Added Delete button with double-confirm modal (type DELETE)
 *  3. Approval email sent on verify
 */
$adminTitle = 'Users';
include __DIR__ . '/../_layout_top.php';
$db = getDB();
$search = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND (name LIKE ? OR email LIKE ? OR firm LIKE ?)"; $p="%$search%"; $params=[$p,$p,$p]; }
$sql .= " ORDER BY created_at DESC";
$st = $db->prepare($sql); $st->execute($params);
$users = $st->fetchAll();
?>

<style>
/* Toggle switch */
.usr-toggle{display:flex;align-items:center;gap:8px;}
.usr-toggle input[type=checkbox]{position:relative;width:40px;height:22px;appearance:none;background:var(--border);border-radius:11px;cursor:pointer;transition:background .2s;flex-shrink:0;}
.usr-toggle input[type=checkbox]:checked{background:var(--success);}
.usr-toggle input[type=checkbox]::after{content:'';position:absolute;width:16px;height:16px;border-radius:50%;background:#fff;top:3px;left:3px;transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2);}
.usr-toggle input[type=checkbox]:checked::after{left:21px;}
.usr-toggle-label{font-size:11px;font-weight:600;color:var(--text3);}

/* Delete confirm modal */
#deleteUserModal{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9100;align-items:center;justify-content:center;padding:16px;}
#deleteUserModal.open{display:flex;}
.del-modal-card{background:var(--surface);border-radius:16px;max-width:420px;width:100%;padding:28px 24px;box-shadow:0 16px 48px rgba(0,0,0,.2);}
</style>

<!-- Search bar -->
<div style="display:flex;gap:12px;align-items:center;margin-bottom:20px;flex-wrap:wrap;">
  <form method="GET" action="index.php" style="display:flex;gap:8px;margin-left:auto;flex-wrap:wrap;">
    <input type="hidden" name="page" value="users"/>
    <input type="text" name="q" class="admin-input" placeholder="Search name / email / firm…" value="<?= h($search) ?>" style="width:220px;"/>
    <button type="submit" class="btn-admin-secondary btn-admin-sm"><?= icon('search',14) ?></button>
    <?php if ($search): ?><a href="index.php?page=users" class="btn-admin-secondary btn-admin-sm">Clear</a><?php endif; ?>
  </form>
</div>

<div class="admin-table-wrap" style="overflow-x:auto;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Name</th><th>Email</th><th>Role</th><th>Firm</th><th>City</th>
        <th>Verified</th><th>Joined</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($users)): ?>
      <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text3);">No users found.</td></tr>
      <?php else: foreach ($users as $u):
        $initials  = strtoupper(($u['name'][0] ?? 'U'));
        $roleLabel = ROLES[$u['role'] ?? ''] ?? $u['role'];
        $isVerified= (int)($u['is_verified'] ?? 0);
      ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-mid));color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;"><?= h($initials) ?></div>
            <span style="font-weight:500;"><?= h($u['name']) ?></span>
          </div>
        </td>
        <td style="color:var(--text2);font-size:12px;"><?= h($u['email']) ?></td>
        <td><span class="badge badge-blue" style="font-size:10px;"><?= h($roleLabel) ?></span></td>
        <td style="color:var(--text2);font-size:12px;"><?= h($u['firm']) ?></td>
        <td style="color:var(--text3);font-size:12px;"><?= h($u['city']) ?></td>
        <td>
          <!-- Verification toggle -->
          <form method="POST" action="index.php" id="vf<?= $u['id'] ?>">
            <input type="hidden" name="action"   value="update_user_status"/>
            <input type="hidden" name="user_id"  value="<?= $u['id'] ?>"/>
            <input type="hidden" name="verified" id="vval<?= $u['id'] ?>" value="<?= $isVerified ? 0 : 1 ?>"/>
            <div class="usr-toggle">
              <input type="checkbox"
                     <?= $isVerified ? 'checked' : '' ?>
                     onchange="document.getElementById('vval<?= $u['id'] ?>').value=this.checked?1:0; document.getElementById('vf<?= $u['id'] ?>').submit();"
                     title="<?= $isVerified ? 'Revoke access' : 'Approve & notify user' ?>"/>
              <span class="usr-toggle-label"><?= $isVerified ? '<span style="color:var(--success)">Approved</span>' : '<span style="color:var(--text3)">Pending</span>' ?></span>
            </div>
          </form>
        </td>
        <td style="color:var(--text3);font-size:11px;"><?= date('d M Y', $u['created_at']) ?></td>
        <td>
          <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            <!-- Password reset -->
            <form method="POST" action="index.php" style="display:inline;">
              <input type="hidden" name="action" value="send_password_reset"/>
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>"/>
              <button type="submit" class="btn-admin-secondary btn-admin-sm" title="Send password reset email">
                <?= icon('mail',13) ?>
              </button>
            </form>
            <!-- View clients -->
            <a href="index.php?page=user_clients&user_id=<?= $u['id'] ?>"
               class="btn-admin-secondary btn-admin-sm" title="View clients"
               style="display:inline-flex;align-items:center;gap:4px;text-decoration:none;">
              <?= icon('users',13) ?>
            </a>
            <!-- Delete user -->
            <button type="button" class="btn-admin-danger btn-admin-sm"
                    onclick="openDeleteModal(<?= $u['id'] ?>, '<?= h(addslashes($u['name'])) ?>')"
                    title="Delete user">
              <?= icon('trash',13) ?>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<p style="font-size:12px;color:var(--text3);margin-top:10px;"><?= count($users) ?> users</p>

<!-- Delete confirmation modal -->
<div id="deleteUserModal" style="display:none;">
  <div class="del-modal-card">
    <div style="width:52px;height:52px;border-radius:50%;background:var(--danger-bg);color:var(--danger);display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
      <?= icon('trash',22) ?>
    </div>
    <p style="font-size:17px;font-weight:700;color:var(--text);margin-bottom:8px;">Delete User Account?</p>
    <p style="font-size:13px;color:var(--text3);line-height:1.6;margin-bottom:16px;" id="delUserMsg">
      This will permanently delete this user and ALL their data (clients, selections, shortlist, inquiries). This cannot be undone.
    </p>
    <div style="background:var(--danger-bg);border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:18px;">
      <p style="font-size:12px;font-weight:700;color:var(--danger);margin-bottom:8px;">
        Type <strong>DELETE</strong> to confirm:
      </p>
      <input type="text" id="delConfirmInput" class="admin-input"
             placeholder="Type DELETE here" autocomplete="off"
             oninput="document.getElementById('delConfirmBtn').disabled=this.value!=='DELETE';"
             style="font-family:monospace;font-size:14px;"/>
    </div>
    <div style="display:flex;gap:10px;">
      <button type="button" class="btn-admin-secondary" onclick="closeDeleteModal()">Cancel</button>
      <form method="POST" action="index.php" style="flex:1;">
        <input type="hidden" name="action"    value="delete_user"/>
        <input type="hidden" name="user_id"   id="delUserId" value=""/>
        <input type="hidden" name="confirm_text" id="delConfirmHidden" value=""/>
        <button type="submit" id="delConfirmBtn" class="btn-admin-danger" style="width:100%;justify-content:center;" disabled
                onclick="document.getElementById('delConfirmHidden').value=document.getElementById('delConfirmInput').value;">
          <?= icon('trash',14) ?> Delete Permanently
        </button>
      </form>
    </div>
  </div>
</div>

<script>
function openDeleteModal(id, name) {
  document.getElementById('delUserId').value = id;
  document.getElementById('delUserMsg').textContent =
    'Delete "' + name + '"? This will permanently remove their account and ALL related data (clients, selections, shortlist, inquiries). This cannot be undone.';
  document.getElementById('delConfirmInput').value = '';
  document.getElementById('delConfirmBtn').disabled = true;
  document.getElementById('deleteUserModal').style.display = 'flex';
  document.getElementById('deleteUserModal').classList.add('open');
  setTimeout(() => document.getElementById('delConfirmInput').focus(), 100);
}
function closeDeleteModal() {
  const m = document.getElementById('deleteUserModal');
  m.style.display = 'none';
  m.classList.remove('open');
}
document.getElementById('deleteUserModal').addEventListener('click', function(e) {
  if (e.target === this) closeDeleteModal();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeDeleteModal();
});
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>