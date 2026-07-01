<?php
/**
 * admin/views/admin_accounts.php
 * Manage Admin Panel Accounts — create, edit, delete, assign roles.
 */

requireAdminPermission('admins.view');

// ── AJAX: table rows ──────────────────────────────────────────────────────────
if (!empty($_GET['ajax_admins'])) {
    $search      = trim($_GET['q']   ?? '');
    $perPage     = 20;
    $currentPage = max(1, (int)($_GET['p'] ?? 1));
    $offset      = ($currentPage - 1) * $perPage;

    $db     = getDB();
    $where  = "WHERE 1=1";
    $params = [];
    if ($search !== '') {
        $where   .= " AND (a.username LIKE ? OR a.name LIKE ? OR a.email LIKE ?)";
        $like     = "%{$search}%";
        $params   = [$like, $like, $like];
    }

    $total = (int)$db->prepare("SELECT COUNT(*) FROM admins a $where")->execute($params) ?
        (function() use ($db, $where, $params) {
            $s = $db->prepare("SELECT COUNT(*) FROM admins a $where");
            $s->execute($params); return (int)$s->fetchColumn();
        })() : 0;

    $totalPages = max(1, (int)ceil($total / $perPage));
    $rowParams  = array_merge($params, [$perPage, $offset]);
    $st = $db->prepare("
        SELECT a.id, a.username, a.name, a.email, a.is_active, a.created_at,
               ar.id AS role_id, ar.name AS role_name, ar.slug AS role_slug
        FROM admins a
        LEFT JOIN admin_roles ar ON ar.id = a.role_id
        $where ORDER BY a.id ASC LIMIT ? OFFSET ?
    ");
    $st->execute($rowParams);
    $admins = $st->fetchAll();

    ob_start();
    include __DIR__ . '/_admin_accounts_rows.php';
    $rows = ob_get_clean();

    // Pagination
    ob_start();
    if ($totalPages > 1):
        $range = 2; $s2 = max(1,$currentPage-$range); $e2 = min($totalPages,$currentPage+$range);
    ?>
    <div class="admin-pagination">
      <button class="apag-btn <?= $currentPage<=1?'disabled':'' ?>" data-page="<?= $currentPage-1 ?>">&lsaquo;</button>
      <?php if($s2>1): ?><button class="apag-btn" data-page="1">1</button><?php if($s2>2): ?><span class="apag-ellipsis">…</span><?php endif; endif; ?>
      <?php for($i=$s2;$i<=$e2;$i++): ?><button class="apag-btn <?= $i===$currentPage?'active':'' ?>" data-page="<?= $i ?>"><?= $i ?></button><?php endfor; ?>
      <?php if($e2<$totalPages): ?><?php if($e2<$totalPages-1): ?><span class="apag-ellipsis">…</span><?php endif; ?><button class="apag-btn" data-page="<?= $totalPages ?>"><?= $totalPages ?></button><?php endif; ?>
      <button class="apag-btn <?= $currentPage>=$totalPages?'disabled':'' ?>" data-page="<?= $currentPage+1 ?>">&rsaquo;</button>
    </div>
    <?php endif;
    $pag = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode(['rows' => $rows, 'pagination' => $pag, 'total' => $total]);
    exit;
}

$adminTitle = 'Admin Accounts';
include __DIR__ . '/../_layout_top.php';

$allRoles = getAllRoles();
?>

<style>
.aa-status-dot {
  display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px;
}
.aa-status-dot.active   { background: var(--success, #3D8B6E); }
.aa-status-dot.inactive { background: var(--admin-text3, #8FA3B1); }

/* ── Create / Edit modal ─────────────────────────────────────────── */
#aaModal {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.55); z-index: 9300;
  align-items: center; justify-content: center; padding: 16px;
}
#aaModal.open { display: flex; }
.aa-modal-card {
  background: var(--admin-card-bg, var(--surface));
  border-radius: 14px; width: 100%; max-width: 520px;
  max-height: 90vh; overflow-y: auto;
  box-shadow: 0 16px 48px rgba(0,0,0,.22);
}
.aa-modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 18px 22px; border-bottom: 1px solid var(--admin-table-border, var(--border));
  position: sticky; top: 0; background: var(--admin-card-bg, var(--surface)); z-index: 2;
}
.aa-form-grid {
  display: grid; grid-template-columns: 1fr; gap: 14px;
}
@media (min-width: 480px) { .aa-form-grid { grid-template-columns: 1fr 1fr; } }

/* Role badge pill inside table */
.role-pill {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
  background: var(--admin-accent-light, #E3EFF4);
  color: var(--admin-accent, #2C6E8A);
  white-space: nowrap;
}
.role-pill.super_admin { background: #FFF8E6; color: #92600A; }
.role-pill.no-role     { background: var(--admin-surface2, #EEF2F7); color: var(--admin-text3, #8FA3B1); }
</style>

<!-- Toolbar -->
<div style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap;">
  <?php if (adminCan('admins.manage')): ?>
  <button type="button" onclick="openAaModal()" class="admin-toolbar-btn admin-toolbar-btn--primary">
    <?= icon('plus', 14) ?> Add Admin Account
  </button>
  <?php endif; ?>
  <div style="position:relative;flex:1;min-width:220px;max-width:400px;">
    <?= icon('search', 14) ?>
    <input type="text" id="aaSearch" class="admin-input" placeholder="Search name, username or email…"
           autocomplete="off" style="padding-left:34px !important;"/>
    <button type="button" id="aaSearchClear" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);
      width:20px;height:20px;border-radius:50%;background:var(--admin-surface2,var(--surface2));
      border:none;cursor:pointer;display:none;align-items:center;justify-content:center;color:var(--admin-text3,var(--text3));">
      <?= icon('close', 11) ?>
    </button>
  </div>
  <span id="aaCountEl" style="font-size:12px;color:var(--admin-text3,var(--text3));margin-left:auto;white-space:nowrap;"></span>
</div>

<!-- Table -->
<div class="admin-table-wrap" id="aaTableWrap" style="position:relative;">
  <div id="aaLoader" style="display:none;position:absolute;inset:0;background:rgba(255,255,255,.65);backdrop-filter:blur(2px);align-items:center;justify-content:center;z-index:50;border-radius:var(--admin-card-radius,var(--card-radius));">
    <div class="admin-loader-ring"></div>
  </div>
  <table class="admin-table">
    <thead>
      <tr>
        <th>Admin</th>
        <th>Username</th>
        <th>Email</th>
        <th>Role</th>
        <th>Status</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="aaTbody">
      <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--admin-text3,var(--text3));">Loading…</td></tr>
    </tbody>
  </table>
</div>
<div style="display:flex;align-items:center;justify-content:space-between;margin-top:12px;flex-wrap:wrap;gap:8px;">
  <p class="admin-products-count" id="aaFooterCount"></p>
  <div id="aaPagWrap"></div>
</div>

<!-- ═══ CREATE / EDIT MODAL ════════════════════════════════════════════════ -->
<div id="aaModal">
  <div class="aa-modal-card">
    <div class="aa-modal-header">
      <p id="aaModalTitle" style="font-size:16px;font-weight:700;color:var(--admin-text,var(--text));">Add Admin Account</p>
      <button type="button" onclick="closeAaModal()" style="color:var(--admin-text3,var(--text3));cursor:pointer;background:none;border:none;">
        <?= icon('close', 18) ?>
      </button>
    </div>
    <div style="padding:22px;">
      <form method="POST" action="index.php" id="aaForm">
        <input type="hidden" name="action"   id="aaFormAction" value="create_admin_account"/>
        <input type="hidden" name="admin_id" id="aaFormId"     value=""/>

        <div class="aa-form-grid">
          <div>
            <label class="admin-label">Full Name <span style="color:var(--danger);">*</span></label>
            <input type="text" name="name" id="aaName" class="admin-input"
                   placeholder="e.g. Rahul Shah" required autocomplete="off"/>
          </div>
          <div>
            <label class="admin-label">Username <span style="color:var(--danger);">*</span></label>
            <input type="text" name="username" id="aaUsername" class="admin-input"
                   placeholder="e.g. rahul.shah" required autocomplete="off"/>
            <p style="font-size:11px;color:var(--admin-text3,var(--text3));margin-top:4px;" id="aaUsernamHint">
              Lowercase letters, numbers and dots only.
            </p>
          </div>
          <div>
            <label class="admin-label">Email Address</label>
            <input type="email" name="email" id="aaEmail" class="admin-input"
                   placeholder="admin@example.com" autocomplete="off"/>
          </div>
          <div>
            <label class="admin-label">Role</label>
            <select name="role_id" id="aaRoleId" class="admin-input admin-select">
              <option value="">— No role —</option>
              <?php foreach ($allRoles as $role): ?>
              <option value="<?= $role['id'] ?>"><?= h($role['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div style="margin-top:14px;">
          <label class="admin-label">Password <span id="aaPasswordRequired" style="color:var(--danger);">*</span></label>
          <div style="position:relative;">
            <input type="password" name="password" id="aaPassword" class="admin-input"
                   placeholder="Min. 8 characters" autocomplete="new-password"
                   style="padding-right:44px;"/>
            <button type="button"
                    onclick="var f=document.getElementById('aaPassword');f.type=f.type==='password'?'text':'password';"
                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                           color:var(--admin-text3,var(--text3));cursor:pointer;background:none;border:none;">
              <?= icon('eye', 16) ?>
            </button>
          </div>
          <p id="aaPassHint" style="font-size:11px;color:var(--admin-text3,var(--text3));margin-top:4px;">
            Leave blank to keep the existing password (when editing).
          </p>
        </div>

        <!-- Active toggle -->
        <div style="display:flex;align-items:center;gap:10px;margin-top:16px;padding:12px;
                    background:var(--admin-surface2,var(--surface2));border-radius:8px;">
          <input type="checkbox" name="is_active" id="aaIsActive" value="1" checked
                 style="width:16px;height:16px;accent-color:var(--admin-accent,#2C6E8A);"/>
          <label for="aaIsActive" style="font-size:13px;font-weight:500;cursor:pointer;">
            Account Active
            <span style="display:block;font-size:11px;color:var(--admin-text3,var(--text3));margin-top:1px;">
              Inactive accounts cannot log in to the admin panel.
            </span>
          </label>
        </div>

        <div style="display:flex;gap:10px;margin-top:20px;">
          <button type="submit" class="btn-admin-primary" style="flex:1;justify-content:center;">
            <?= icon('check', 15) ?> Save Account
          </button>
          <button type="button" onclick="closeAaModal()" class="btn-admin-secondary">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div id="aaDeleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9400;align-items:center;justify-content:center;padding:16px;">
  <div class="aa-modal-card" style="max-width:420px;">
    <div style="padding:24px;">
      <div style="width:48px;height:48px;border-radius:50%;background:var(--danger-bg,#FFF0F0);color:var(--danger,#E84040);display:flex;align-items:center;justify-content:center;margin-bottom:14px;">
        <?= icon('trash', 22) ?>
      </div>
      <p style="font-size:16px;font-weight:700;color:var(--admin-text,var(--text));margin-bottom:6px;">Delete Admin Account?</p>
      <p style="font-size:13px;color:var(--admin-text3,var(--text3));line-height:1.6;margin-bottom:18px;" id="aaDeleteMsg"></p>
      <div style="display:flex;gap:10px;">
        <button type="button" class="btn-admin-secondary" style="flex:1;justify-content:center;"
                onclick="document.getElementById('aaDeleteModal').style.display='none'">Cancel</button>
        <form method="POST" action="index.php" style="flex:1;">
          <input type="hidden" name="action"   value="delete_admin_account"/>
          <input type="hidden" name="admin_id" id="aaDeleteId" value=""/>
          <button type="submit" class="btn-admin-danger" style="width:100%;justify-content:center;">
            <?= icon('trash', 14) ?> Delete
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var tbody   = document.getElementById('aaTbody');
  var pagWrap = document.getElementById('aaPagWrap');
  var countEl = document.getElementById('aaCountEl');
  var footEl  = document.getElementById('aaFooterCount');
  var searchEl= document.getElementById('aaSearch');
  var clearBtn= document.getElementById('aaSearchClear');
  var loader  = document.getElementById('aaLoader');

  var state = { q: '', page: 1 };
  var timer = null;

  function load() {
    if (loader) loader.style.display = 'flex';
    if (tbody)  tbody.style.opacity  = '0.4';
    var params = new URLSearchParams({ page: 'admin_accounts', ajax_admins: '1', p: state.page });
    if (state.q) params.set('q', state.q);

    fetch('index.php?' + params)
      .then(function(r){ return r.json(); })
      .then(function(d){
        if (tbody)  { tbody.innerHTML = d.rows; tbody.style.opacity = '1'; }
        if (pagWrap){ pagWrap.innerHTML = d.pagination || ''; bindPag(); }
        var txt = d.total + ' admin account' + (d.total !== 1 ? 's' : '');
        if (countEl) countEl.textContent = txt;
        if (footEl)  footEl.textContent  = txt;
      })
      .catch(function(){ if(tbody) tbody.style.opacity='1'; })
      .finally(function(){ if(loader) loader.style.display='none'; });
  }

  function bindPag() {
    if (!pagWrap) return;
    pagWrap.querySelectorAll('.apag-btn').forEach(function(btn){
      btn.addEventListener('click', function(){
        if (btn.classList.contains('disabled')||btn.classList.contains('active')) return;
        var pg = parseInt(btn.dataset.page,10);
        if (!isNaN(pg)){ state.page=pg; load(); }
      });
    });
  }

  if (searchEl) {
    searchEl.addEventListener('input', function(){
      var v = this.value.trim();
      if (clearBtn) clearBtn.style.display = v ? 'flex' : 'none';
      clearTimeout(timer);
      if (v.length>0&&v.length<2) return;
      timer = setTimeout(function(){ state.q=v; state.page=1; load(); }, 300);
    });
  }
  if (clearBtn) {
    clearBtn.addEventListener('click', function(){
      searchEl.value=''; clearBtn.style.display='none';
      state.q=''; state.page=1; load(); searchEl.focus();
    });
  }

  load();
  window._aaReload = load;
})();

// ── Modal helpers ────────────────────────────────────────────────────────────
function openAaModal(data) {
  var isEdit = !!data;
  document.getElementById('aaModalTitle').textContent = isEdit ? 'Edit Admin Account' : 'Add Admin Account';
  document.getElementById('aaFormAction').value       = isEdit ? 'update_admin_account' : 'create_admin_account';
  document.getElementById('aaFormId').value           = isEdit ? (data.id || '')  : '';
  document.getElementById('aaName').value             = isEdit ? (data.name || '') : '';
  document.getElementById('aaUsername').value         = isEdit ? (data.username || '') : '';
  document.getElementById('aaEmail').value            = isEdit ? (data.email || '')    : '';
  document.getElementById('aaPassword').value         = '';
  document.getElementById('aaIsActive').checked       = isEdit ? (!!data.is_active)    : true;

  // Role select
  var roleEl = document.getElementById('aaRoleId');
  if (roleEl) roleEl.value = isEdit ? (data.role_id || '') : '';

  // Username readonly when editing
  document.getElementById('aaUsername').readOnly = isEdit;
  document.getElementById('aaUsernameHint') && (document.getElementById('aaUsernameHint').style.display = isEdit ? 'none' : '');

  // Password required only for new accounts
  var req = document.getElementById('aaPasswordRequired');
  var hint = document.getElementById('aaPassHint');
  if (req)  req.style.display  = isEdit ? 'none' : 'inline';
  if (hint) hint.style.display = isEdit ? 'block' : 'none';

  document.getElementById('aaModal').classList.add('open');
  document.body.style.overflow = 'hidden';
  setTimeout(function(){ document.getElementById('aaName').focus(); }, 80);
}

function closeAaModal() {
  document.getElementById('aaModal').classList.remove('open');
  document.body.style.overflow = '';
}

function openAaDelete(id, name) {
  document.getElementById('aaDeleteId').value = id;
  document.getElementById('aaDeleteMsg').textContent =
    'Delete admin account "' + name + '"? This cannot be undone.';
  document.getElementById('aaDeleteModal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

document.getElementById('aaModal').addEventListener('click', function(e){
  if (e.target===this) closeAaModal();
});
document.getElementById('aaDeleteModal').addEventListener('click', function(e){
  if (e.target===this){ this.style.display='none'; document.body.style.overflow=''; }
});
document.addEventListener('keydown', function(e){
  if (e.key==='Escape'){ closeAaModal(); document.getElementById('aaDeleteModal').style.display='none'; document.body.style.overflow=''; }
});

// Reload table after form submit (AJAX form submit)
document.getElementById('aaForm').addEventListener('submit', function(e){
  // Let it do a normal POST — page will reload; table reloads on DOMContentLoaded
});
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>