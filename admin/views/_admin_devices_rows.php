<?php
/**
 * admin/views/_admin_devices_rows.php — Partial: table rows for devices.php
 * Expects: $devices, $total
 */
?>
<?php if (empty($devices)): ?>
<tr><td colspan="8" class="admin-table-empty">No trusted devices found.</td></tr>
<?php else: foreach ($devices as $d):
  $ownerName  = $d['user_name'] ?? $d['admin_name'] ?? '—';
  $ownerType  = $d['user_name'] ? 'User' : ($d['admin_name'] ? 'Admin' : '—');
  $initials   = $ownerName !== '—' ? strtoupper(mb_substr($ownerName, 0, 1)) : '?';
  $isActive   = $d['status'] === 'active';
  $panelLabel = ucfirst($d['panel']);
?>
<tr>
  <td>
    <p style="font-weight:600;font-size:13px;color:var(--admin-text,var(--text));"><?= h($d['device_name']) ?></p>
    <p style="font-size:10px;color:var(--admin-text3,var(--text3));font-family:monospace;">#<?= (int)$d['id'] ?></p>
  </td>
  <td>
    <div class="dev-owner-chip">
      <div class="dev-owner-avatar"><?= h($initials) ?></div>
      <div>
        <div style="font-weight:600;color:var(--admin-text,var(--text));font-size:12px;"><?= h($ownerName) ?></div>
        <div style="font-size:10px;"><?= h($ownerType) ?></div>
      </div>
    </div>
  </td>
  <td><span class="badge badge-blue" style="font-size:10px;"><?= h($panelLabel) ?></span></td>
  <td>
    <span class="dev-status-dot <?= $isActive ? 'active' : 'disabled' ?>"></span>
    <span style="font-size:12px;color:var(--admin-text2,var(--text2));"><?= $isActive ? 'Active' : 'Disabled' ?></span>
  </td>
  <td style="font-size:11px;color:var(--admin-text3,var(--text3));white-space:nowrap;">
    <?= $d['last_seen'] ? h(timeAgo((int)$d['last_seen'])) : '—' ?>
  </td>
  <td style="font-size:11px;color:var(--admin-text3,var(--text3));font-family:monospace;"><?= h($d['ip_last'] ?: '—') ?></td>
  <td style="font-size:11px;color:var(--admin-text3,var(--text3));white-space:nowrap;"><?= date('d M Y', $d['created_at']) ?></td>
  <td>
    <div style="display:flex;gap:5px;align-items:center;">
      <form method="POST" action="index.php" style="display:inline;">
        <input type="hidden" name="action"    value="admin_toggle_device"/>
        <input type="hidden" name="device_id" value="<?= (int)$d['id'] ?>"/>
        <input type="hidden" name="new_status" value="<?= $isActive ? 'disabled' : 'active' ?>"/>
        <?= csrfField() ?>
        <button type="submit" class="btn-admin-secondary btn-admin-sm" title="<?= $isActive ? 'Disable' : 'Enable' ?>">
          <?= $isActive ? icon('close',13) : icon('check',13) ?>
        </button>
      </form>
      <button type="button" class="btn-admin-secondary btn-admin-sm dev-history-btn" data-id="<?= (int)$d['id'] ?>" title="View history">
        <?= icon('info', 13) ?>
      </button>
      <button type="button" class="btn-admin-danger btn-admin-sm dev-delete-btn"
              data-id="<?= (int)$d['id'] ?>" data-name="<?= h(addslashes($d['device_name'])) ?>" title="Delete">
        <?= icon('trash', 13) ?>
      </button>
    </div>
  </td>
</tr>
<?php endforeach; endif; ?>