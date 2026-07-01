<?php
/**
 * admin/views/roles.php
 * Role & Permission Management — list all roles, create/edit/delete roles,
 * assign permissions via a rich checkbox interface.
 */

requireAdminPermission('roles.view');

$adminTitle = 'Roles & Permissions';
include __DIR__ . '/../_layout_top.php';

$roles   = getAllRoles();
$grouped = getAllPermissionsGrouped();
?>

<style>
/* ── Page layout ─────────────────────────────────────────────────── */
.rbac-layout {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}
@media (min-width: 1100px) {
  .rbac-layout {
    grid-template-columns: 320px 1fr;
    align-items: start;
  }
}

/* ── Role card list ──────────────────────────────────────────────── */
.role-list { display: flex; flex-direction: column; gap: 8px; }
.role-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 13px 16px;
  border: 1.5px solid var(--admin-table-border, var(--border));
  border-radius: 10px;
  background: var(--admin-card-bg, var(--surface));
  cursor: pointer;
  transition: border-color .15s, background .15s;
}
.role-card:hover   { border-color: var(--admin-accent, #2C6E8A); }
.role-card.selected { border-color: var(--admin-accent, #2C6E8A); background: var(--admin-accent-light, #E3EFF4); }
.role-avatar {
  width: 40px; height: 40px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; font-weight: 700; color: #fff; flex-shrink: 0;
}
.role-info  { flex: 1; min-width: 0; }
.role-name  { font-size: 13px; font-weight: 700; color: var(--admin-text, var(--text)); }
.role-meta  { font-size: 11px; color: var(--admin-text3, var(--text3)); margin-top: 2px; }
.role-badge { flex-shrink: 0; }

/* ── Permission grid ─────────────────────────────────────────────── */
.perm-module {
  background: var(--admin-card-bg, var(--surface));
  border: 1px solid var(--admin-table-border, var(--border));
  border-radius: 10px;
  overflow: hidden;
  margin-bottom: 10px;
}
.perm-module-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: var(--admin-surface2, var(--surface2));
  border-bottom: 1px solid var(--admin-table-border, var(--border));
  cursor: pointer;
  user-select: none;
}
.perm-module-title {
  font-size: 12px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .5px; color: var(--admin-text, var(--text));
  display: flex; align-items: center; gap: 8px;
}
.perm-module-count { font-size: 11px; color: var(--admin-text3, var(--text3)); font-weight: 400; text-transform: none; letter-spacing: 0; }
.perm-module-body { padding: 12px 16px; }
.perm-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
}
@media (min-width: 640px)  { .perm-grid { grid-template-columns: 1fr 1fr; } }
@media (min-width: 1024px) { .perm-grid { grid-template-columns: 1fr 1fr 1fr; } }

.perm-item {
  display: flex;
  align-items: flex-start;
  gap: 9px;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1.5px solid var(--admin-table-border, var(--border));
  background: var(--admin-surface, var(--surface));
  cursor: pointer;
  transition: border-color .15s, background .15s;
  user-select: none;
}
.perm-item:hover { border-color: var(--admin-accent, #2C6E8A); }
.perm-item.checked {
  border-color: var(--admin-accent, #2C6E8A);
  background: var(--admin-accent-light, #E3EFF4);
}
.perm-item input[type=checkbox] {
  width: 16px; height: 16px; accent-color: var(--admin-accent, #2C6E8A);
  flex-shrink: 0; margin-top: 1px; cursor: pointer;
}
.perm-label {
  font-size: 12px; font-weight: 500;
  color: var(--admin-text, var(--text)); line-height: 1.4;
}
.perm-action {
  font-size: 10px; color: var(--admin-text3, var(--text3));
  font-family: monospace; margin-top: 2px;
}

/* ── Select/deselect all row ────────────────────────────────────── */
.perm-module-toggle {
  display: flex; align-items: center; gap: 6px;
  font-size: 11px; font-weight: 600; color: var(--admin-accent, #2C6E8A);
  cursor: pointer; white-space: nowrap;
}

/* ── System role notice ─────────────────────────────────────────── */
.system-badge {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 9px; border-radius: 20px;
  background: #FFF8E6; color: #92600A;
  font-size: 10px; font-weight: 700; letter-spacing: .3px;
}

/* ── Role form modal ─────────────────────────────────────────────── */
#roleModal {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.55); z-index: 9300;
  align-items: center; justify-content: center; padding: 16px;
}
#roleModal.open { display: flex; }
.role-modal-card {
  background: var(--admin-card-bg, var(--surface));
  border-radius: 14px; width: 100%; max-width: 460px;
  padding: 24px 22px;
  box-shadow: 0 16px 48px rgba(0,0,0,.2);
}

/* ── Sticky save bar ─────────────────────────────────────────────── */
.perm-save-bar {
  position: sticky; bottom: 0;
  background: var(--admin-card-bg, var(--surface));
  border-top: 1px solid var(--admin-table-border, var(--border));
  padding: 14px 20px;
  display: flex; align-items: center; gap: 12px;
  margin: 0 -24px;
  z-index: 20;
  flex-wrap: wrap;
}

/* ── Color palette for role avatars ─────────────────────────────── */
.ra-0  { background: #2C6E8A; }
.ra-1  { background: #1A4D65; }
.ra-2  { background: #3D8B6E; }
.ra-3  { background: #B8975A; }
.ra-4  { background: #7B5EA7; }
.ra-5  { background: #C0504D; }
.ra-6  { background: #4DA8C9; }
.ra-7  { background: #8FA3B1; }

/* ── Responsive tweaks ──────────────────────────────────────────── */
@media (max-width: 640px) {
  .perm-save-bar { margin: 0 -16px; }
}
</style>

<div class="rbac-layout">

  <!-- ═══ LEFT: Role List ══════════════════════════════════════════════ -->
  <div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
      <p style="font-size:13px;font-weight:700;color:var(--admin-text,var(--text));">Roles <span style="color:var(--admin-text3,var(--text3));font-weight:400;">(<?= count($roles) ?>)</span></p>
      <?php if (adminCan('roles.manage')): ?>
      <button type="button" onclick="openRoleModal()" class="btn-admin-primary btn-admin-sm">
        <?= icon('plus', 13) ?> New Role
      </button>
      <?php endif; ?>
    </div>

    <div class="role-list" id="roleList">
      <?php foreach ($roles as $i => $role): ?>
      <div class="role-card <?= $i === 0 ? 'selected' : '' ?>"
           data-role-id="<?= $role['id'] ?>"
           onclick="selectRole(<?= $role['id'] ?>)">
        <div class="role-avatar ra-<?= $i % 8 ?>"><?= strtoupper(mb_substr($role['name'], 0, 2)) ?></div>
        <div class="role-info">
          <p class="role-name"><?= h($role['name']) ?></p>
          <p class="role-meta">
            <?= $role['perm_count'] ?> permission<?= $role['perm_count'] != 1 ? 's' : '' ?>
            &nbsp;·&nbsp;
            <?= $role['admin_count'] ?> admin<?= $role['admin_count'] != 1 ? 's' : '' ?>
          </p>
        </div>
        <div class="role-badge">
          <?php if ($role['is_system']): ?>
          <span class="system-badge">⚙ System</span>
          <?php endif; ?>
        </div>
        <?php if (adminCan('roles.manage') && !$role['is_system']): ?>
        <div style="display:flex;gap:4px;margin-left:4px;flex-shrink:0;">
          <button type="button" class="btn-admin-secondary btn-admin-sm"
                  onclick="event.stopPropagation(); editRole(<?= $role['id'] ?>, <?= h(json_encode($role['name'])) ?>, <?= h(json_encode($role['description'])) ?>)"
                  title="Edit role name">
            <?= icon('edit', 13) ?>
          </button>
          <button type="button" class="btn-admin-danger btn-admin-sm"
                  onclick="event.stopPropagation(); deleteRole(<?= $role['id'] ?>, <?= h(json_encode($role['name'])) ?>)"
                  title="Delete role">
            <?= icon('trash', 13) ?>
          </button>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if (empty($roles)): ?>
    <div style="text-align:center;padding:40px 0;color:var(--admin-text3,var(--text3));font-size:13px;">
      No roles yet. Click "+ New Role" to create one.
    </div>
    <?php endif; ?>
  </div>

  <!-- ═══ RIGHT: Permission Editor ═════════════════════════════════════ -->
  <div id="permPanel">
    <?php if (empty($roles)): ?>
    <div class="admin-form-section" style="text-align:center;padding:48px;">
      <p style="font-size:14px;color:var(--admin-text3,var(--text3));">Create a role first, then assign permissions here.</p>
    </div>
    <?php else:
      // Default: first role
      $firstRole = $roles[0];
      $firstRoleData = getRoleWithPermissions((int)$firstRole['id']);
      $selectedPermIds = $firstRoleData['permission_ids'] ?? [];
    ?>

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
      <div>
        <p style="font-size:16px;font-weight:700;color:var(--admin-text,var(--text));" id="permPanelTitle">
          <?= h($firstRole['name']) ?> — Permissions
        </p>
        <?php if ($firstRole['is_system']): ?>
        <p style="font-size:12px;color:var(--admin-text3,var(--text3));margin-top:3px;">
          <?= icon('info', 12) ?> Super Admin has all permissions by default and cannot be restricted.
        </p>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:8px;align-items:center;" id="permPanelActions">
        <button type="button" onclick="selectAllPerms(true)"  class="btn-admin-secondary btn-admin-sm"><?= icon('check', 12) ?> All</button>
        <button type="button" onclick="selectAllPerms(false)" class="btn-admin-secondary btn-admin-sm"><?= icon('close', 12) ?> None</button>
      </div>
    </div>

    <form method="POST" action="index.php" id="permForm">
      <input type="hidden" name="action"  value="save_role_permissions"/>
      <input type="hidden" name="role_id" id="permRoleId" value="<?= $firstRole['id'] ?>"/>

      <div id="permModules">
        <?php foreach ($grouped as $module => $permissions): ?>
        <div class="perm-module">
          <div class="perm-module-header" onclick="toggleModule(this)">
            <p class="perm-module-title">
              <?= h($module) ?>
              <span class="perm-module-count" id="mc-<?= h(str_replace([' ','&'],'_',$module)) ?>">
                <?php
                  $checkedCount = count(array_filter($permissions, fn($p) => in_array($p['id'], $selectedPermIds)));
                ?>
                (<?= $checkedCount ?>/<?= count($permissions) ?>)
              </span>
            </p>
            <div style="display:flex;align-items:center;gap:10px;">
              <span class="perm-module-toggle"
                    onclick="event.stopPropagation(); toggleModuleAll(this, true)"
                    title="Select all in <?= h($module) ?>">All</span>
              <span class="perm-module-toggle"
                    onclick="event.stopPropagation(); toggleModuleAll(this, false)"
                    title="Deselect all in <?= h($module) ?>" style="color:var(--admin-text3,var(--text3));">None</span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                   class="module-chevron" style="transition:transform .2s;">
                <polyline points="6 9 12 15 18 9"/>
              </svg>
            </div>
          </div>
          <div class="perm-module-body">
            <div class="perm-grid">
              <?php foreach ($permissions as $perm): ?>
              <?php $isChecked = in_array($perm['id'], $selectedPermIds); ?>
              <label class="perm-item <?= $isChecked ? 'checked' : '' ?>"
                     data-module="<?= h($module) ?>"
                     data-total="<?= count($permissions) ?>">
                <input type="checkbox" name="permissions[]"
                       value="<?= $perm['id'] ?>"
                       <?= $isChecked ? 'checked' : '' ?>
                       onchange="onPermChange(this)"/>
                <div>
                  <p class="perm-label"><?= h($perm['label']) ?></p>
                  <p class="perm-action"><?= h($perm['action']) ?></p>
                </div>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if (adminCan('roles.assign')): ?>
      <div class="perm-save-bar" id="permSaveBar" style="display:none;">
        <button type="submit" class="btn-admin-primary">
          <?= icon('check', 15) ?> Save Permissions
        </button>
        <p style="font-size:12px;color:var(--admin-text3,var(--text3));" id="permDirtyNote">
          You have unsaved changes.
        </p>
        <button type="button" onclick="cancelPermChanges()" class="btn-admin-secondary">
          Cancel
        </button>
      </div>
      <?php endif; ?>

    </form>

    <?php endif; ?>
  </div>

</div>

<!-- ═══ NEW / EDIT ROLE MODAL ══════════════════════════════════════════════ -->
<div id="roleModal">
  <div class="role-modal-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <p id="roleModalTitle" style="font-size:16px;font-weight:700;color:var(--admin-text,var(--text));">New Role</p>
      <button type="button" onclick="closeRoleModal()" style="color:var(--admin-text3,var(--text3));cursor:pointer;background:none;border:none;">
        <?= icon('close', 18) ?>
      </button>
    </div>
    <form method="POST" action="index.php" id="roleForm">
      <input type="hidden" name="action"  id="roleFormAction" value="create_role"/>
      <input type="hidden" name="role_id" id="roleFormId"     value=""/>
      <div style="margin-bottom:14px;">
        <label class="admin-label">Role Name <span style="color:var(--danger);">*</span></label>
        <input type="text" name="name" id="roleFormName" class="admin-input"
               placeholder="e.g. Marketing Executive" required autocomplete="off"/>
      </div>
      <div style="margin-bottom:20px;">
        <label class="admin-label">Description</label>
        <textarea name="description" id="roleFormDesc" class="admin-input" rows="2"
                  placeholder="Brief description of this role's responsibilities…"></textarea>
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn-admin-primary" style="flex:1;justify-content:center;">
          <?= icon('check', 15) ?> Save Role
        </button>
        <button type="button" onclick="closeRoleModal()" class="btn-admin-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ DELETE CONFIRM MODAL ════════════════════════════════════════════════ -->
<div id="roleDeleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9400;align-items:center;justify-content:center;padding:16px;">
  <div class="role-modal-card" style="max-width:420px;">
    <div style="width:48px;height:48px;border-radius:50%;background:var(--danger-bg,#FFF0F0);color:var(--danger,#E84040);display:flex;align-items:center;justify-content:center;margin-bottom:14px;">
      <?= icon('trash', 22) ?>
    </div>
    <p style="font-size:16px;font-weight:700;color:var(--admin-text,var(--text));margin-bottom:6px;">Delete Role?</p>
    <p style="font-size:13px;color:var(--admin-text3,var(--text3));line-height:1.6;margin-bottom:18px;" id="roleDeleteMsg">
      Admins assigned this role will have their role removed.
    </p>
    <div style="display:flex;gap:10px;">
      <button type="button" class="btn-admin-secondary" style="flex:1;justify-content:center;" onclick="document.getElementById('roleDeleteModal').style.display='none'">Cancel</button>
      <form method="POST" action="index.php" style="flex:1;">
        <input type="hidden" name="action"  value="delete_role"/>
        <input type="hidden" name="role_id" id="roleDeleteId" value=""/>
        <button type="submit" class="btn-admin-danger" style="width:100%;justify-content:center;">
          <?= icon('trash', 14) ?> Delete
        </button>
      </form>
    </div>
  </div>
</div>

<script>
// ── All role data for AJAX loading ──────────────────────────────────────────
var ALL_PERMISSIONS = <?= json_encode(
    array_merge(...array_map(fn($perms) => $perms, array_values($grouped)))
) ?>;

var _permsDirty = false;
var _currentRoleId = <?= (int)($roles[0]['id'] ?? 0) ?>;
var _originalPerms = <?= json_encode($selectedPermIds ?? []) ?>.map(Number);

// ── Select a role and load its permissions via AJAX ─────────────────────────
function selectRole(roleId) {
  if (_permsDirty && !confirm('You have unsaved permission changes. Discard them?')) return;

  _currentRoleId = roleId;
  _permsDirty    = false;

  // Highlight selected card
  document.querySelectorAll('.role-card').forEach(function(c) {
    c.classList.toggle('selected', parseInt(c.dataset.roleId) === roleId);
  });

  // Load permissions for this role
  fetch('index.php?page=roles&ajax_role_perms=1&role_id=' + roleId)
    .then(function(r){ return r.json(); })
    .then(function(d){
      _originalPerms = (d.permission_ids || []).map(Number);
      document.getElementById('permRoleId').value = roleId;
      document.getElementById('permPanelTitle').textContent = d.role_name + ' — Permissions';

      // Reset all checkboxes
      document.querySelectorAll('#permForm input[type=checkbox]').forEach(function(cb){
        var checked = _originalPerms.indexOf(parseInt(cb.value)) !== -1;
        cb.checked = checked;
        cb.closest('.perm-item').classList.toggle('checked', checked);
      });

      // Show system role notice
      var actions = document.getElementById('permPanelActions');
      if (actions) actions.style.display = d.is_system ? 'none' : 'flex';

      // Disable checkboxes for system roles
      document.querySelectorAll('#permForm input[type=checkbox]').forEach(function(cb){
        cb.disabled = !!d.is_system;
      });

      updateAllModuleCounts();
      hideSaveBar();
    })
    .catch(function(e){ console.error('Permission load error:', e); });
}

// ── Permission checkbox change ──────────────────────────────────────────────
function onPermChange(cb) {
  cb.closest('.perm-item').classList.toggle('checked', cb.checked);
  updateModuleCount(cb.closest('.perm-module'));
  setDirty(true);
}

// ── Module collapse toggle ──────────────────────────────────────────────────
function toggleModule(header) {
  var body  = header.nextElementSibling;
  var chev  = header.querySelector('.module-chevron');
  var open  = body.style.display !== 'none';
  body.style.display = open ? 'none' : '';
  if (chev) chev.style.transform = open ? 'rotate(-90deg)' : '';
}

// ── Select all / none inside a module ──────────────────────────────────────
function toggleModuleAll(btn, check) {
  var module = btn.closest('.perm-module');
  module.querySelectorAll('input[type=checkbox]').forEach(function(cb){
    if (cb.disabled) return;
    cb.checked = check;
    cb.closest('.perm-item').classList.toggle('checked', check);
  });
  updateModuleCount(module);
  setDirty(true);
}

// ── Select all / none across all modules ───────────────────────────────────
function selectAllPerms(check) {
  document.querySelectorAll('#permForm input[type=checkbox]').forEach(function(cb){
    if (cb.disabled) return;
    cb.checked = check;
    cb.closest('.perm-item').classList.toggle('checked', check);
  });
  updateAllModuleCounts();
  setDirty(true);
}

// ── Update count badge per module ──────────────────────────────────────────
function updateModuleCount(moduleEl) {
  var checks = moduleEl.querySelectorAll('input[type=checkbox]');
  var total   = checks.length;
  var checked = Array.from(checks).filter(function(c){ return c.checked; }).length;
  var title   = moduleEl.querySelector('.perm-module-title');
  if (!title) return;
  var countSpan = title.querySelector('.perm-module-count');
  if (countSpan) countSpan.textContent = '(' + checked + '/' + total + ')';
}
function updateAllModuleCounts() {
  document.querySelectorAll('.perm-module').forEach(updateModuleCount);
}

// ── Dirty state ────────────────────────────────────────────────────────────
function setDirty(val) {
  _permsDirty = val;
  var bar = document.getElementById('permSaveBar');
  if (bar) bar.style.display = val ? 'flex' : 'none';
}
function hideSaveBar() { setDirty(false); }

function cancelPermChanges() {
  // Restore original
  document.querySelectorAll('#permForm input[type=checkbox]').forEach(function(cb){
    var checked = _originalPerms.indexOf(parseInt(cb.value)) !== -1;
    cb.checked = checked;
    cb.closest('.perm-item').classList.toggle('checked', checked);
  });
  updateAllModuleCounts();
  hideSaveBar();
}

// ── New Role Modal ─────────────────────────────────────────────────────────
function openRoleModal() {
  document.getElementById('roleModalTitle').textContent  = 'New Role';
  document.getElementById('roleFormAction').value        = 'create_role';
  document.getElementById('roleFormId').value            = '';
  document.getElementById('roleFormName').value          = '';
  document.getElementById('roleFormDesc').value          = '';
  document.getElementById('roleModal').classList.add('open');
  document.body.style.overflow = 'hidden';
  setTimeout(function(){ document.getElementById('roleFormName').focus(); }, 100);
}
function editRole(id, name, desc) {
  document.getElementById('roleModalTitle').textContent  = 'Edit Role';
  document.getElementById('roleFormAction').value        = 'update_role';
  document.getElementById('roleFormId').value            = id;
  document.getElementById('roleFormName').value          = name;
  document.getElementById('roleFormDesc').value          = desc || '';
  document.getElementById('roleModal').classList.add('open');
  document.body.style.overflow = 'hidden';
  setTimeout(function(){ document.getElementById('roleFormName').focus(); }, 100);
}
function closeRoleModal() {
  document.getElementById('roleModal').classList.remove('open');
  document.body.style.overflow = '';
}
function deleteRole(id, name) {
  document.getElementById('roleDeleteId').value  = id;
  document.getElementById('roleDeleteMsg').textContent =
    'Delete role "' + name + '"? Admins assigned this role will have their role removed. This cannot be undone.';
  document.getElementById('roleDeleteModal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

// ── Modal close on overlay click / Esc ────────────────────────────────────
document.getElementById('roleModal').addEventListener('click', function(e){
  if (e.target === this) closeRoleModal();
});
document.getElementById('roleDeleteModal').addEventListener('click', function(e){
  if (e.target === this){ this.style.display='none'; document.body.style.overflow=''; }
});
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') { closeRoleModal(); document.getElementById('roleDeleteModal').style.display='none'; document.body.style.overflow=''; }
});

// ── Warn before leaving with unsaved changes ───────────────────────────────
window.addEventListener('beforeunload', function(e){
  if (_permsDirty) { e.preventDefault(); e.returnValue=''; }
});

// ── Init module counts ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function(){
  updateAllModuleCounts();
});
</script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>