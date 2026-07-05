<?php

//  AJAX handler — runs before layout 
if (!empty($_GET['ajax_users'])) {
  requireAdminPermission('users.view');
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

    $total = (function() use ($db, $where, $params) {
        $s = $db->prepare("SELECT COUNT(*) FROM users $where");
        $s->execute($params);
        return (int)$s->fetchColumn();
    })();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $perPage;

    $rowParams = array_merge($params, [$perPage, $offset]);
    $st = $db->prepare("SELECT * FROM users $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $st->execute($rowParams);
    $users = $st->fetchAll();

    ob_start();
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
          <?= csrfField() ?>
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
    <?php if (adminCan('users.edit')): ?>
    <button type="button"
                  onclick="openEditUser(<?= $u['id'] ?>,<?= h(json_encode($u['name'])) ?>,<?= h(json_encode($u['email'])) ?>,<?= h(json_encode($u['phone']??'')) ?>,<?= h(json_encode($u['firm']??'')) ?>,<?= h(json_encode($u['city']??'')) ?>,<?= h(json_encode($u['role']??'')) ?>)"
                  class="btn-admin-secondary btn-admin-sm" title="Edit user">
            <?= icon('edit',13) ?>
          </button>
    <?php endif; ?>
    <?php if (adminCan('users.reset_password')): ?>
    <form method="POST" action="index.php" style="display:inline;">
      <input type="hidden" name="action"  value="send_password_reset"/>
      <input type="hidden" name="user_id" value="<?= $u['id'] ?>"/>
      <?= csrfField() ?>
      <button type="submit" class="btn-admin-secondary btn-admin-sm" title="Send password reset">
        <?= icon('mail',13) ?>
      </button>
    </form>
    <?php endif; ?>
    <a href="index.php?page=user_clients&user_id=<?= $u['id'] ?>"
       class="btn-admin-secondary btn-admin-sm" title="View clients"
       style="display:inline-flex;align-items:center;gap:4px;text-decoration:none;">
      <?= icon('users',13) ?>
    </a>
    <?php if (adminCan('users.delete')): ?>
    <button type="button" class="btn-admin-danger btn-admin-sm"
            onclick="openDeleteModal(<?= $u['id'] ?>, '<?= h(addslashes($u['name'])) ?>')"
            title="Delete user">
      <?= icon('trash',13) ?>
    </button>
    <?php endif; ?>
  </div>
      </td>
    </tr>
    <?php endforeach; endif;
    $rows = ob_get_clean();

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

#deleteUserModal,#editUserModal,#createUserModal{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9100;align-items:center;justify-content:center;padding:16px;display:none;}
#deleteUserModal.open,#editUserModal.open,#createUserModal.open{display:flex;}
.usr-modal-card{background:var(--surface);border-radius:16px;max-width:460px;width:100%;padding:28px 24px;box-shadow:0 16px 48px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto;}

#usersLoader{display:none;position:absolute;inset:0;background:rgba(255,255,255,.65);backdrop-filter:blur(2px);align-items:center;justify-content:center;z-index:50;border-radius:var(--card-radius);}
#usersTableWrap{position:relative;}

/* Password strength bar inside modal */
.pwd-strength{height:3px;border-radius:2px;margin-top:6px;background:var(--border);overflow:hidden;}
.pwd-strength::after{content:'';display:block;height:100%;border-radius:2px;transition:width .3s,background .3s;}
.pwd-strength[data-level="1"]::after{width:25%;background:var(--danger);}
.pwd-strength[data-level="2"]::after{width:50%;background:var(--gold);}
.pwd-strength[data-level="3"]::after{width:75%;background:var(--text3);}
.pwd-strength[data-level="4"]::after{width:100%;background:var(--success);}

/* Auto-generate toggle */
.auto-pwd-row{display:flex;align-items:center;gap:8px;margin-bottom:10px;font-size:12px;color:var(--text3);}
.auto-pwd-row input[type=checkbox]{width:15px;height:15px;accent-color:var(--accent);cursor:pointer;}
</style>

<!--  Toolbar  -->
<div class="users-toolbar">
 
  <!-- Add User -->
  <?php if (adminCan('users.create')): ?>
  <button type="button" onclick="openCreateUser()" class="admin-toolbar-btn admin-toolbar-btn--primary">
    <?= icon('plus',14) ?> Add User
  </button>
  <?php endif; ?>
 
  <!-- Search -->
  <div class="users-search-wrap" id="userSearchWrap">
    <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);
                 color:var(--text3);pointer-events:none;line-height:0;">
      <?= icon('search',14) ?>
    </span>
    <input type="text" id="userSearch"
           placeholder="Search name, email, firm, city…"
           autocomplete="off"
           style="padding-left:34px;"/>
    <button type="button" id="userSearchClear">
      <?= icon('close',11) ?>
    </button>
  </div>
 
  <!-- Count badge -->
  <div id="userCountEl"
       style="font-size:12px;color:var(--text3);white-space:nowrap;flex-shrink:0;"></div>
 
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

<div style="display:flex;align-items:center;justify-content:space-between;margin-top:12px;flex-wrap:wrap;gap:10px;">
  <p class="admin-products-count" id="userFooterCount"></p>
  <div id="usersPaginationWrap"></div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     ▶ NEW: Create User Modal
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="createUserModal">
  <div class="usr-modal-card">

    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <div>
        <p style="font-size:16px;font-weight:700;color:var(--text);">Create New User</p>
        <p style="font-size:12px;color:var(--text3);margin-top:2px;">
          Login credentials will be emailed automatically.
        </p>
      </div>
      <button type="button" onclick="closeCreateUser()"
              style="color:var(--text3);cursor:pointer;background:none;border:none;padding:4px;">
        <?= icon('close',18) ?>
      </button>
    </div>

    <!-- Info banner -->
    <div style="background:var(--accent-light);border:1px solid var(--border);border-radius:8px;
                padding:10px 14px;margin-bottom:20px;display:flex;align-items:flex-start;gap:8px;">
      <?= icon('mail',14) ?>
      <p style="font-size:12px;color:var(--text2);line-height:1.5;">
        The user will receive a welcome email with their <strong>username</strong> and
        <strong>password</strong> at the address you enter below.
        The account is auto-verified so they can log in immediately.
      </p>
    </div>

    <form method="POST" action="index.php" id="createUserForm">
      <input type="hidden" name="action" value="create_user"/>
      <?= csrfField() ?>
      <!-- Name + Email -->
      <div class="admin-form-grid" style="margin-bottom:0;">
        <div style="margin-bottom:14px;">
          <label class="admin-label">Full Name <span style="color:var(--danger);">*</span></label>
          <input type="text" name="name" id="cuName" class="admin-input"
                 placeholder="Rahul Sharma" required autocomplete="off"/>
        </div>
        <div style="margin-bottom:14px;">
          <label class="admin-label">Email <span style="color:var(--danger);">*</span></label>
          <input type="email" name="email" id="cuEmail" class="admin-input"
                 placeholder="user@studio.com" required autocomplete="off"/>
        </div>
        <div style="margin-bottom:14px;">
          <label class="admin-label">Phone</label>
          <input type="tel" name="phone" id="cuPhone" class="admin-input"
                 placeholder="98765 43210" autocomplete="off"/>
        </div>
        <div style="margin-bottom:14px;">
          <label class="admin-label">Firm / Studio</label>
          <input type="text" name="firm" id="cuFirm" class="admin-input"
                 placeholder="Design Studio" autocomplete="off"/>
        </div>
        <div style="margin-bottom:14px;">
          <label class="admin-label">City</label>
          <input type="text" name="city" id="cuCity" class="admin-input"
                 placeholder="Mumbai" autocomplete="off"/>
        </div>
        <div style="margin-bottom:14px;">
          <label class="admin-label">Role</label>
          <select name="role" id="cuRole" class="admin-input admin-select">
            <option value="">— Select —</option>
            <?php foreach (ROLES as $val => $label): ?>
            <option value="<?= h($val) ?>"><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Password section -->
      <div style="margin-bottom:18px;">
        <label class="admin-label">Password</label>

        <!-- Auto-generate toggle -->
        <div class="auto-pwd-row">
          <input type="checkbox" id="cuAutoGen" checked
                 onchange="toggleAutoPassword(this.checked)"/>
          <label for="cuAutoGen" style="cursor:pointer;">
            Auto-generate a secure password <span style="color:var(--success);font-weight:600;">(recommended)</span>
          </label>
        </div>

        <!-- Manual password field — hidden when auto-generate is on -->
        <div id="cuManualPwdWrap" style="display:none;">
          <div style="position:relative;">
            <input type="password" name="password" id="cuPassword" class="admin-input"
                   placeholder="Min. 8 characters" minlength="8"
                   autocomplete="new-password"
                   style="padding-right:44px;"/>
            <button type="button"
                    onclick="togglePwdVisibility('cuPassword', this)"
                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                           color:var(--text3);cursor:pointer;background:none;border:none;">
              <?= icon('eye',16) ?>
            </button>
          </div>
          <div class="pwd-strength" id="cuPwdStrength"></div>
          <p style="font-size:11px;color:var(--text3);margin-top:5px;">
            Min. 8 characters. Leave blank to auto-generate.
          </p>
        </div>

        <p id="cuAutoNote"
           style="font-size:12px;color:var(--text3);margin-top:6px;display:flex;align-items:center;gap:5px;">
          <?= icon('check',12) ?>
          A random 10-character password will be generated and emailed to the user.
        </p>
      </div>

      <!-- Actions -->
      <div style="display:flex;gap:10px;margin-top:4px;">
        <button type="submit" class="btn-admin-primary" id="cuSubmitBtn"
                style="flex:1;justify-content:center;">
          <?= icon('plus',15) ?>&nbsp; Create User &amp; Send Email
        </button>
        <button type="button" onclick="closeCreateUser()"
                class="btn-admin-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Edit User Modal (unchanged) ──────────────────────────────────────── -->
<div id="editUserModal">
  <div class="usr-modal-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <p style="font-size:16px;font-weight:700;">Edit User</p>
      <button type="button" onclick="closeEditUser()" style="color:var(--text3);cursor:pointer;background:none;border:none;"><?= icon('close',18) ?></button>
    </div>
    <form method="POST" action="index.php" id="editUserForm">
      <input type="hidden" name="action"  value="save_user_edit"/>
      <input type="hidden" name="user_id" id="editUserId"/>
      <?= csrfField() ?>
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

<!-- ── Delete User Modal (unchanged) ────────────────────────────────────── -->
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
        <?= csrfField() ?>
        <button type="submit" id="delConfirmBtn" class="btn-admin-danger" style="width:100%;justify-content:center;" disabled
                onclick="document.getElementById('delConfirmHidden').value=document.getElementById('delConfirmInput').value;">
          <?= icon('trash',14) ?> Delete Permanently
        </button>
      </form>
    </div>
  </div>
</div>

<script>
// ── AJAX users loader (unchanged) ────────────────────────────────────────────
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

// ── Edit User Modal (unchanged) ──────────────────────────────────────────────
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

// ── Delete User Modal (unchanged) ────────────────────────────────────────────
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

// ── Overlay + Escape key (close all modals) ──────────────────────────────────
['editUserModal','deleteUserModal','createUserModal'].forEach(function(id){
  var el = document.getElementById(id);
  if (!el) return;
  el.addEventListener('click', function(e){
    if (e.target === el) { closeEditUser(); closeDeleteModal(); closeCreateUser(); }
  });
});
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') { closeEditUser(); closeDeleteModal(); closeCreateUser(); }
});

// ════════════════════════════════════════════════════════════════════════════
// ▶ NEW: Create User Modal helpers
// ════════════════════════════════════════════════════════════════════════════

function openCreateUser() {
  // Reset form fields
  var form = document.getElementById('createUserForm');
  if (form) form.reset();

  // Reset password UI to auto-generate state
  document.getElementById('cuAutoGen').checked = true;
  toggleAutoPassword(true);

  // Clear strength meter
  var s = document.getElementById('cuPwdStrength');
  if (s) s.removeAttribute('data-level');

  document.getElementById('createUserModal').classList.add('open');
  document.body.style.overflow = 'hidden';
  setTimeout(function(){ document.getElementById('cuName').focus(); }, 120);
}

function closeCreateUser() {
  document.getElementById('createUserModal').classList.remove('open');
  document.body.style.overflow = '';
}

// Toggle between auto-generate and manual password entry
function toggleAutoPassword(isAuto) {
  var wrap = document.getElementById('cuManualPwdWrap');
  var note = document.getElementById('cuAutoNote');
  var inp  = document.getElementById('cuPassword');

  wrap.style.display = isAuto ? 'none' : 'block';
  note.style.display = isAuto ? 'flex' : 'none';

  // When switching to auto, clear and un-require the password field
  if (isAuto) {
    inp.value    = '';
    inp.required = false;
    inp.removeAttribute('minlength');
  } else {
    inp.required = true;
    inp.setAttribute('minlength', '8');
    setTimeout(function(){ inp.focus(); }, 60);
  }
}

// Show/hide password toggle
function togglePwdVisibility(inputId, btn) {
  var inp = document.getElementById(inputId);
  if (!inp) return;
  var isText = inp.type === 'text';
  inp.type = isText ? 'password' : 'text';
  // Swap icon (same SVGs as app.js pwd-toggle)
  btn.innerHTML = isText
    ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
    : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
}

// Password strength meter for manual entry
document.getElementById('cuPassword').addEventListener('input', function() {
  var v = this.value;
  var level = 0;
  if (v.length >= 8)          level++;
  if (/[A-Z]/.test(v))        level++;
  if (/[0-9]/.test(v))        level++;
  if (/[^A-Za-z0-9]/.test(v)) level++;
  var s = document.getElementById('cuPwdStrength');
  if (s) s.dataset.level = v.length ? level : '';
});

// Submit button loading state + basic client-side guard
document.getElementById('createUserForm').addEventListener('submit', function(e) {
  var autoGen  = document.getElementById('cuAutoGen').checked;
  var password = document.getElementById('cuPassword').value;

  // If manual mode and password too short, block
  if (!autoGen && password.length > 0 && password.length < 8) {
    e.preventDefault();
    alert('Password must be at least 8 characters.');
    return;
  }

  // If auto-generate, clear the password field before submit so PHP
  // knows to auto-generate (empty = auto-generate in createUserByAdmin)
  if (autoGen) {
    document.getElementById('cuPassword').value = '';
  }

  // Show loading state on button
  var btn = document.getElementById('cuSubmitBtn');
  btn.disabled   = true;
  btn.innerHTML  = '<?= icon('refresh',15) ?>&nbsp; Creating…';
});
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>