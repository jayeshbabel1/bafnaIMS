<?php
/**
 * admin/views/users.php
 * AJAX pagination · 25 per page · Edit user · Verify toggle · Delete
 */

// ── AJAX handler — runs before layout ────────────────────────────────────────
if (!empty($_GET['ajax_users'])) {
    $db      = getDB();
    $search  = trim($_GET['q']   ?? '');
    $perPage = 25;
    $page    = max(1, (int)($_GET['p'] ?? 1));
    $offset  = ($page - 1) * $perPage;

    $where  = "WHERE 1=1";
    $params = [];
    if ($search !== '') {
        $where   .= " AND (name LIKE ? OR email LIKE ? OR firm LIKE ? OR city LIKE ?)";
        $like     = "%{$search}%";
        $params   = [$like, $like, $like, $like];
    }

    $total      = (int)$db->prepare("SELECT COUNT(*) FROM users $where")->execute($params) ?
                  (function() use ($db, $where, $params) {
                      $s = $db->prepare("SELECT COUNT(*) FROM users $where");
                      $s->execute($params);
                      return (int)$s->fetchColumn();
                  })() : 0;
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    $rowParams = array_merge($params, [$perPage, $offset]);
    $st = $db->prepare("SELECT * FROM users $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $st->execute($rowParams);
    $users = $st->fetchAll();

    ob_start();
    // Table rows
    ?>
    <?php if (empty($users)): ?>
    <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text3);">No users found.</td></tr>
    <?php else: foreach ($users as $u):
        $initials  = strtoupper($u['name'][0] ?? 'U');
        $roleLabel = ROLES[$u['role'] ?? ''] ?? ($u['role'] ?? '—');
        $isVerified= (int)($u['is_verified'] ?? 0);
    ?>
    <tr data-uid="<?= $u['id'] ?>">
      <td>
        <div style="display:flex;align-items:center;gap:10px;">
          <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-mid));color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;"><?= h($initials) ?></div>
          <div>
            <p style="font-weight:500;font-size:13px;"><?= h($u['name']) ?></p>
            <p style="font-size:11px;color:var(--text3);"><?= h($u['firm'] ?: '—') ?></p>
          </div>
        </div>
      </td>
      <td style="color:var(--text2);font-size:12px;"><?= h($u['email']) ?></td>
      <td style="font-size:12px;color:var(--text3);"><?= h($u['phone'] ?: '—') ?></td>
      <td><span class="badge badge-blue" style="font-size:10px;"><?= h($roleLabel) ?></span></td>
      <td style="font-size:12px;color:var(--text2);"><?= h($u['city'] ?: '—') ?></td>
      <td>
        <form method="POST" action="index.php" id="vf<?= $u['id'] ?>">
          <input type="hidden" name="action"   value="update_user_status"/>
          <input type="hidden" name="user_id"  value="<?= $u['id'] ?>"/>
          <input type="hidden" name="verified" id="vval<?= $u['id'] ?>" value="<?= $isVerified ? 0 : 1 ?>"/>
          <div class="usr-toggle">
            <input type="checkbox"
                   <?= $isVerified ? 'checked' : '' ?>
                   onchange="document.getElementById('vval<?= $u['id'] ?>').value=this.checked?1:0; document.getElementById('vf<?= $u['id'] ?>').submit();"
                   title="<?= $isVerified ? 'Revoke access' : 'Approve & notify' ?>"/>
            <span class="usr-toggle-label"><?= $isVerified ? '<span style="color:var(--success)">Approved</span>' : '<span style="color:var(--text3)">Pending</span>' ?></span>
          </div>
        </form>
      </td>
      <td style="color:var(--text3);font-size:11px;"><?= date('d M Y', $u['created_at']) ?></td>
      <td>
        <div style="display:flex;gap:5px;flex-wrap:wrap;align-items:center;">
          <!-- Edit -->
          <button type="button"
                  onclick="openEditUser(<?= $u['id'] ?>,<?= h(json_encode($u['name'])) ?>,<?= h(json_encode($u['email'])) ?>,<?= h(json_encode($u['phone']??'')) ?>,<?= h(json_encode($u['firm']??'')) ?>,<?= h(json_encode($u['city']??'')) ?>,<?= h(json_encode($u['role']??'')) ?>)"
                  class="btn-admin-secondary btn-admin-sm" title="Edit user">
            <?= icon('edit',13) ?>
          </button>
          <!-- Password reset -->
          <form method="POST" action="index.php" style="display:inline;">
            <input type="hidden" name="action"  value="send_password_reset"/>
            <input type="hidden" name="user_id" value="<?= $u['id'] ?>"/>
            <button type="submit" class="btn-admin-secondary btn-admin-sm" title="Send password reset">
              <?= icon('mail',13) ?>
            </button>
          </form>
          <!-- View clients -->
          <a href="index.php?page=user_clients&user_id=<?= $u['id'] ?>"
             class="btn-admin-secondary btn-admin-sm" title="View clients"
             style="display:inline-flex;align-items:center;gap:4px;text-decoration:none;">
            <?= icon('users',13) ?>
          </a>
          <!-- Delete -->
          <button type="button" class="btn-admin-danger btn-admin-sm"
                  onclick="openDeleteModal(<?= $u['id'] ?>, '<?= h(addslashes($u['name'])) ?>')"
                  title="Delete user">
            <?= icon('trash',13) ?>
          </button>
        </div>
      </td>
    </tr>
    <?php endforeach; endif;
    $rows = ob_get_clean();

    // Pagination HTML
    ob_start();
    if ($totalPages > 1):
        $range = 2; $s = max(1, $page - $range); $e = min($totalPages, $page + $range);
    ?>
    <div class="admin-pagination">
      <button class="apag-btn <?= $page<=1?'disabled':'' ?>" data-page="<?= $page-1 ?>">&lsaquo;</button>
      <?php if ($s>1): ?><button class="apag-btn" data-page="1">1</button><?php if ($s>2): ?><span class="apag-ellipsis">…</span><?php endif; endif; ?>
      <?php for ($i=$s;$i<=$e;$i++): ?>
      <button class="apag-btn <?= $i===$page?'active':'' ?>" data-page="<?= $i ?>"><?= $i ?></button>
      <?php endfor; ?>
      <?php if ($e<$totalPages): ?><?php if ($e<$totalPages-1): ?><span class="apag-ellipsis">…</span><?php endif; ?><button class="apag-btn" data-page="<?= $totalPages ?>"><?= $totalPages ?></button><?php endif; ?>
      <button class="apag-btn <?= $page>=$totalPages?'disabled':'' ?>" data-page="<?= $page+1 ?>">&rsaquo;</button>
    </div>
    <?php endif;
    $pag = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode([
        'rows'       => $rows,
        'pagination' => $pag,
        'total'      => $total,
        'page'       => $page,
        'pages'      => $totalPages,
    ]);
    exit;
}

// ── save_user_edit POST handler ──────────────────────────────────────────────
// (Handled in admin/index.php via action='save_user_edit' — see bottom of this file for the snippet)

$adminTitle = 'Users';
include __DIR__ . '/../_layout_top.php';
$db = getDB();
?>

<style>
.usr-toggle{display:flex;align-items:center;gap:8px;}
.usr-toggle input[type=checkbox]{position:relative;width:40px;height:22px;appearance:none;background:var(--border);border-radius:11px;cursor:pointer;transition:background .2s;flex-shrink:0;}
.usr-toggle input[type=checkbox]:checked{background:var(--success);}
.usr-toggle input[type=checkbox]::after{content:'';position:absolute;width:16px;height:16px;border-radius:50%;background:#fff;top:3px;left:3px;transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2);}
.usr-toggle input[type=checkbox]:checked::after{left:21px;}
.usr-toggle-label{font-size:11px;font-weight:600;color:var(--text3);}

/* Delete & Edit modals */
#deleteUserModal,#editUserModal{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9100;align-items:center;justify-content:center;padding:16px;display:none;}
#deleteUserModal.open,#editUserModal.open{display:flex;}
.usr-modal-card{background:var(--surface);border-radius:16px;max-width:460px;width:100%;padding:28px 24px;box-shadow:0 16px 48px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto;}

/* Users loader */
#usersLoader{display:none;position:absolute;inset:0;background:rgba(255,255,255,.65);backdrop-filter:blur(2px);align-items:center;justify-content:center;z-index:50;border-radius:var(--card-radius);}
#usersTableWrap{position:relative;}
</style>

<!-- Toolbar -->
<div style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap;">
  <div style="position:relative;flex:1;min-width:200px;max-width:360px;">
    <?= icon('search',14) ?>
    <input type="text" id="userSearch" class="admin-input"
           placeholder="Search name, email, firm, city…"
           autocomplete="off"
           style="padding-left:34px;padding-right:32px;width:100%;"/>
    <button id="userSearchClear" type="button"
            style="position:absolute;right:8px;top:50%;transform:translateY(-50%);display:none;
                   width:20px;height:20px;border-radius:50%;background:var(--surface3);border:none;
                   cursor:pointer;display:none;align-items:center;justify-content:center;">
      <?= icon('close',12) ?>
    </button>
  </div>
  <div id="userCountEl" style="font-size:12px;color:var(--text3);white-space:nowrap;"></div>
</div>

<!-- Table -->
<div class="admin-table-wrap" id="usersTableWrap">
  <div id="usersLoader"><div class="admin-loader-ring"></div></div>
  <table class="admin-table" id="usersTable">
    <thead>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Role</th>
        <th>City</th>
        <th>Verified</th>
        <th>Joined</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="usersTbody">
      <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text3);">Loading…</td></tr>
    </tbody>
  </table>
</div>

<!-- Footer: count + pagination -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-top:12px;flex-wrap:wrap;gap:10px;">
  <p class="admin-products-count" id="userFooterCount"></p>
  <div id="usersPaginationWrap"></div>
</div>

<!-- ── Edit User Modal ─────────────────────────────────────────────────── -->
<div id="editUserModal">
  <div class="usr-modal-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <p style="font-size:16px;font-weight:700;">Edit User</p>
      <button type="button" onclick="closeEditUser()" style="color:var(--text3);cursor:pointer;background:none;border:none;"><?= icon('close',18) ?></button>
    </div>
    <form method="POST" action="index.php" id="editUserForm">
      <input type="hidden" name="action"  value="save_user_edit"/>
      <input type="hidden" name="user_id" id="editUserId"/>
      <div class="admin-form-grid" style="margin-bottom:0;">
        <div>
          <label class="admin-label">Full Name *</label>
          <input type="text" name="name" id="editUserName" class="admin-input" required/>
        </div>
        <div>
          <label class="admin-label">Email *</label>
          <input type="email" name="email" id="editUserEmail" class="admin-input" required/>
        </div>
        <div>
          <label class="admin-label">Phone</label>
          <input type="tel" name="phone" id="editUserPhone" class="admin-input"/>
        </div>
        <div>
          <label class="admin-label">Firm / Studio</label>
          <input type="text" name="firm" id="editUserFirm" class="admin-input"/>
        </div>
        <div>
          <label class="admin-label">City</label>
          <input type="text" name="city" id="editUserCity" class="admin-input"/>
        </div>
        <div>
          <label class="admin-label">Role</label>
          <select name="role" id="editUserRole" class="admin-input admin-select">
            <option value="">— Select —</option>
            <?php foreach (ROLES as $val => $label): ?>
            <option value="<?= h($val) ?>"><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:20px;">
        <button type="submit" class="btn-admin-primary" style="flex:1;justify-content:center;">
          <?= icon('check',15) ?> Save Changes
        </button>
        <button type="button" onclick="closeEditUser()" class="btn-admin-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Delete User Modal ───────────────────────────────────────────────── -->
<div id="deleteUserModal">
  <div class="usr-modal-card">
    <div style="width:52px;height:52px;border-radius:50%;background:var(--danger-bg);color:var(--danger);display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
      <?= icon('trash',22) ?>
    </div>
    <p style="font-size:17px;font-weight:700;color:var(--text);margin-bottom:8px;">Delete User Account?</p>
    <p style="font-size:13px;color:var(--text3);line-height:1.6;margin-bottom:16px;" id="delUserMsg"></p>
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
        <input type="hidden" name="action"       value="delete_user"/>
        <input type="hidden" name="user_id"      id="delUserId" value=""/>
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
(function () {
  'use strict';
  var tbody   = document.getElementById('usersTbody');
  var pagWrap = document.getElementById('usersPaginationWrap');
  var countEl = document.getElementById('userFooterCount');
  var searchEl= document.getElementById('userSearch');
  var clearBtn= document.getElementById('userSearchClear');
  var loader  = document.getElementById('usersLoader');

  var state = { q: '', page: 1, per: 25 };
  var timer = null;

  function load() {
    if (loader) loader.style.display = 'flex';
    if (tbody)  tbody.style.opacity  = '0.4';

    var params = new URLSearchParams({
      page: 'users', ajax_users: '1',
      p: state.page, q: state.q
    });

    fetch('index.php?' + params)
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (tbody)  { tbody.innerHTML = d.rows; tbody.style.opacity = '1'; }
        if (pagWrap){ pagWrap.innerHTML = d.pagination || ''; bindPag(); }
        if (countEl){ countEl.textContent = d.total + ' users'; }
        document.getElementById('userCountEl').textContent = d.total + ' users';
      })
      .catch(function(){ if (tbody) tbody.style.opacity = '1'; })
      .finally(function(){ if (loader) loader.style.display = 'none'; });
  }

  function bindPag() {
    if (!pagWrap) return;
    pagWrap.querySelectorAll('.apag-btn').forEach(function(btn){
      btn.addEventListener('click', function(){
        if (btn.classList.contains('disabled') || btn.classList.contains('active')) return;
        var pg = parseInt(btn.dataset.page, 10);
        if (!isNaN(pg)) { state.page = pg; load(); }
      });
    });
  }

  if (searchEl) {
    searchEl.addEventListener('input', function(){
      var v = this.value.trim();
      if (clearBtn) clearBtn.style.display = v ? 'flex' : 'none';
      clearTimeout(timer);
      // Trigger search only after >= 2 chars, or empty (reset)
      if (v.length > 0 && v.length < 2) return;
      timer = setTimeout(function(){
        state.q    = v;
        state.page = 1;
        load();
      }, 300);
    });
  }
  if (clearBtn) {
    clearBtn.addEventListener('click', function(){
      searchEl.value = ''; clearBtn.style.display = 'none';
      state.q = ''; state.page = 1; load();
      searchEl.focus();
    });
  }

  load();
})();

// ── Edit User Modal ──────────────────────────────────────────────────────────
function openEditUser(id, name, email, phone, firm, city, role) {
  document.getElementById('editUserId').value    = id;
  document.getElementById('editUserName').value  = name;
  document.getElementById('editUserEmail').value = email;
  document.getElementById('editUserPhone').value = phone;
  document.getElementById('editUserFirm').value  = firm;
  document.getElementById('editUserCity').value  = city;
  var roleEl = document.getElementById('editUserRole');
  if (roleEl) roleEl.value = role;
  document.getElementById('editUserModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeEditUser() {
  document.getElementById('editUserModal').classList.remove('open');
  document.body.style.overflow = '';
}

// ── Delete User Modal ────────────────────────────────────────────────────────
function openDeleteModal(id, name) {
  document.getElementById('delUserId').value      = id;
  document.getElementById('delUserMsg').textContent =
    'Delete "' + name + '"? This permanently removes their account and ALL related data. This cannot be undone.';
  document.getElementById('delConfirmInput').value  = '';
  document.getElementById('delConfirmBtn').disabled = true;
  document.getElementById('deleteUserModal').classList.add('open');
  document.body.style.overflow = 'hidden';
  setTimeout(function(){ document.getElementById('delConfirmInput').focus(); }, 100);
}
function closeDeleteModal() {
  document.getElementById('deleteUserModal').classList.remove('open');
  document.body.style.overflow = '';
}

// Close modals on overlay click / Escape
['editUserModal','deleteUserModal'].forEach(function(id){
  var el = document.getElementById(id);
  if (!el) return;
  el.addEventListener('click', function(e){ if (e.target === el) { closeEditUser(); closeDeleteModal(); } });
});
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') { closeEditUser(); closeDeleteModal(); }
});
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>