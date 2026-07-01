<?php
/**
 * admin/views/_admin_clients_rows.php — Partial: table rows for admin_clients.php
 * Expects: $clients, $total
 */
?>
<?php if (empty($clients)): ?>
<tr><td colspan="6" class="admin-table-empty">No clients found.</td></tr>
<?php else: foreach ($clients as $c):
  $initials = strtoupper(mb_substr($c['owner_name'] ?? 'U', 0, 1));
?>
<tr>
  <td>
    <div style="display:flex;align-items:center;gap:8px;">
      <div class="client-avatar" style="width:34px;height:34px;font-size:13px;">
        <?= strtoupper(mb_substr($c['client_name'], 0, 1)) ?>
      </div>
      <div style="min-width:0;">
        <p style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;"><?= h($c['client_name']) ?></p>
        <p style="font-size:11px;color:var(--admin-text3,var(--text3));"><?= h($c['client_mobile']) ?></p>
      </div>
    </div>
  </td>
  <td style="font-size:12px;color:var(--admin-text2,var(--text2));">
    <?php if ($c['mansoner_name']): ?>
      <?= h($c['mansoner_name']) ?>
      <?php if ($c['mansoner_mobile']): ?><br/><span style="font-size:11px;color:var(--admin-text3,var(--text3));"><?= h($c['mansoner_mobile']) ?></span><?php endif; ?>
    <?php else: ?>
      <span style="color:var(--admin-text3,var(--text3));">—</span>
    <?php endif; ?>
  </td>
  <td>
    <div class="ac-owner-chip">
      <div class="ac-owner-avatar"><?= h($initials) ?></div>
      <div>
        <div style="font-weight:600;color:var(--admin-text,var(--text));font-size:12px;"><?= h($c['owner_name']) ?></div>
        <?php if ($c['owner_firm']): ?><div style="font-size:10px;"><?= h($c['owner_firm']) ?></div><?php endif; ?>
      </div>
    </div>
  </td>
  <td>
    <a href="index.php?page=admin_client_selections&client_id=<?= $c['id'] ?>" class="badge badge-blue" style="text-decoration:none;cursor:pointer;">
      <?= $c['selection_count'] ?> items
    </a>
  </td>
  <td style="font-size:11px;color:var(--admin-text3,var(--text3));white-space:nowrap;"><?= date('d M Y', $c['created_at']) ?></td>
  <td>
    <div style="display:flex;gap:5px;">
    <a href="index.php?page=admin_client_selections&client_id=<?= $c['id'] ?>"
       class="btn-admin-secondary btn-admin-sm btn-admin-labeled">
      <?= icon('grid', 13) ?> Selections
    </a>
    <?php if (adminCan('clients.edit')): ?>
    <a href="index.php?page=admin_client_form&id=<?= $c['id'] ?>"
       class="btn-admin-secondary btn-admin-sm">
      <?= icon('edit', 13) ?>
    </a>
    <?php endif; ?>
    <?php if (adminCan('clients.delete')): ?>
    <button type="button" class="btn-admin-danger btn-admin-sm ac-delete-btn"
            data-id="<?= $c['id'] ?>" data-name="<?= h(addslashes($c['client_name'])) ?>">
      <?= icon('trash', 13) ?>
    </button>
    <?php endif; ?>
  </div>
  </td>
</tr>
<?php endforeach; endif; ?>