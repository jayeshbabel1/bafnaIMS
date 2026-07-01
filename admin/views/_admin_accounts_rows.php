<?php
/**
 * admin/views/_admin_accounts_rows.php
 * Partial: table rows for admin_accounts.php
 * Expects: $admins array
 */
?>
<?php if (empty($admins)): ?>
<tr><td colspan="7" class="admin-table-empty">No admin accounts found.</td></tr>
<?php else: ?>
<?php foreach ($admins as $admin):
  $initials  = strtoupper(mb_substr($admin['name'] ?? 'A', 0, 2));
  $roleSlug  = $admin['role_slug'] ?? '';
  $roleName  = $admin['role_name'] ?? '';
  $isActive  = (int)($admin['is_active'] ?? 1);
  $isSelf    = ((int)$admin['id'] === (int)($_SESSION['admin_id'] ?? 0));
?>
<tr <?= $isSelf ? 'style="background:var(--admin-accent-light,#E3EFF4);"' : '' ?>>
  <td>
    <div style="display:flex;align-items:center;gap:10px;">
      <div style="width:34px;height:34px;border-radius:50%;
                  background:<?= $isActive ? 'linear-gradient(135deg,var(--admin-accent,#2C6E8A),var(--admin-accent-mid,#4DA8C9))' : 'var(--admin-surface3,#E6ECF2)' ?>;
                  color:<?= $isActive ? '#fff' : 'var(--admin-text3,#8FA3B1)' ?>;
                  display:flex;align-items:center;justify-content:center;
                  font-size:11px;font-weight:700;flex-shrink:0;">
        <?= h($initials) ?>
      </div>
      <div style="min-width:0;">
        <p style="font-weight:600;font-size:13px;color:var(--admin-text,var(--text));">
          <?= h($admin['name']) ?>
          <?php if ($isSelf): ?>
          <span style="font-size:10px;font-weight:600;color:var(--admin-accent,#2C6E8A);margin-left:4px;">(You)</span>
          <?php endif; ?>
        </p>
      </div>
    </div>
  </td>
  <td style="font-size:12px;font-family:monospace;color:var(--admin-text2,#4A6070);">
    <?= h($admin['username']) ?>
  </td>
  <td style="font-size:12px;color:var(--admin-text3,#8FA3B1);">
    <?= $admin['email'] ? h($admin['email']) : '<span style="color:var(--admin-text3,#8FA3B1);">—</span>' ?>
  </td>
  <td>
    <?php if ($roleName): ?>
    <span class="role-pill <?= h($roleSlug) ?>">
      <?php if ($roleSlug === 'super_admin'): ?>⚙ <?php endif; ?>
      <?= h($roleName) ?>
    </span>
    <?php else: ?>
    <span class="role-pill no-role">No role</span>
    <?php endif; ?>
  </td>
  <td>
    <span class="aa-status-dot <?= $isActive ? 'active' : 'inactive' ?>"></span>
    <span style="font-size:12px;color:var(--admin-text2,#4A6070);">
      <?= $isActive ? 'Active' : 'Inactive' ?>
    </span>
  </td>
  <td style="font-size:11px;color:var(--admin-text3,#8FA3B1);white-space:nowrap;">
    <?= $admin['created_at'] ? date('d M Y', $admin['created_at']) : '—' ?>
  </td>
  <td>
    <div style="display:flex;gap:5px;align-items:center;">
      <?php if (adminCan('admins.manage')): ?>
      <button type="button"
              class="btn-admin-secondary btn-admin-sm"
              title="Edit account"
              onclick="openAaModal({
                id:       <?= (int)$admin['id'] ?>,
                name:     <?= json_encode($admin['name']     ?? '') ?>,
                username: <?= json_encode($admin['username'] ?? '') ?>,
                email:    <?= json_encode($admin['email']    ?? '') ?>,
                role_id:  <?= json_encode($admin['role_id']  ?? '') ?>,
                is_active:<?= (int)$isActive ?>
              })">
        <?= icon('edit', 13) ?>
      </button>
      <?php if (!$isSelf): ?>
      <button type="button"
              class="btn-admin-danger btn-admin-sm"
              title="Delete account"
              onclick="openAaDelete(<?= (int)$admin['id'] ?>, <?= json_encode($admin['name'] ?? '') ?>)">
        <?= icon('trash', 13) ?>
      </button>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </td>
</tr>
<?php endforeach; ?>
<?php endif; ?>