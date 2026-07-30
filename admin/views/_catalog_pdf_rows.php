<?php
/**
 * admin/views/_catalog_pdf_rows.php — Partial: table rows for catalog_pdf_history.php
 * Expects: $catalogs
 */
?>
<?php if (empty($catalogs)): ?>
<tr><td colspan="8" class="admin-table-empty">No catalogs found. <a href="index.php?page=catalog_pdf_wizard">Create one</a>.</td></tr>
<?php else: foreach ($catalogs as $c):
    $productIds = json_decode($c['product_ids_json'] ?? '[]', true) ?: [];
    $productCount = count($productIds);
    $statusMap = [
        'draft'   => ['badge-gray',  'Draft'],
        'done'    => ['badge-green', 'Ready'],
        'failed'  => ['badge-red',   'Failed'],
    ];
    [$statusClass, $statusLabel] = $statusMap[$c['status']] ?? ['badge-gray', ucfirst($c['status'])];
    $dlCount = (int)(getDB()->query("SELECT COUNT(*) FROM catalog_download_logs WHERE catalog_id=" . (int)$c['id'] . " AND channel IN ('download','email')")->fetchColumn());
    $sizeStr = $c['size_bytes'] ? number_format($c['size_bytes'] / 1048576, 1) . ' MB' : '—';
?>
<tr>
  <td style="font-weight:600;color:var(--admin-text,var(--text));"><?= h($c['name']) ?></td>
  <td style="font-size:12px;color:var(--admin-text3,var(--text3));"><?= $productCount ?> items</td>
  <td style="font-size:12px;"><?= $c['pages'] ?? '—' ?></td>
  <td style="font-size:12px;"><?= h($sizeStr) ?></td>
  <td style="font-size:12px;"><?= $dlCount ?></td>
  <td><span class="badge <?= $statusClass ?>"><?= h($statusLabel) ?></span></td>
  <td style="font-size:11px;color:var(--admin-text3,var(--text3));white-space:nowrap;"><?= date('d M Y', $c['created_at']) ?></td>
  <td>
    <div style="display:flex;gap:5px;flex-wrap:wrap;">
      <a href="index.php?page=catalog_pdf_wizard&id=<?= $c['id'] ?>" class="btn-admin-secondary btn-admin-sm" title="Edit"><?= icon('edit',13) ?></a>
      <?php if ($c['status'] === 'done' && adminCan('catalog.download')): ?>
      <a href="index.php?catalog_download=1&id=<?= $c['id'] ?>" class="btn-admin-secondary btn-admin-sm" title="Download"><?= icon('download',13) ?></a>
      <?php endif; ?>
      <?php if ($c['status'] === 'done' && adminCan('catalog.share')): ?>
      <button type="button" class="btn-admin-secondary btn-admin-sm cph-email-btn" data-id="<?= $c['id'] ?>" data-name="<?= h(addslashes($c['name'])) ?>" title="Email"><?= icon('mail',13) ?></button>
      <?php endif; ?>
      <?php if (adminCan('catalog.regenerate')): ?>
      <form method="POST" action="index.php" style="display:inline;">
        <input type="hidden" name="action" value="catalog_pdf_regenerate"/>
        <input type="hidden" name="catalog_id" value="<?= $c['id'] ?>"/>
        <?= csrfField() ?>
        <button type="submit" class="btn-admin-secondary btn-admin-sm" title="Regenerate"><?= icon('refresh',13) ?></button>
      </form>
      <?php endif; ?>
      <?php if (adminCan('catalog.create')): ?>
      <form method="POST" action="index.php" style="display:inline;">
        <input type="hidden" name="action" value="catalog_pdf_duplicate"/>
        <input type="hidden" name="catalog_id" value="<?= $c['id'] ?>"/>
        <?= csrfField() ?>
        <button type="submit" class="btn-admin-secondary btn-admin-sm" title="Duplicate"><?= icon('copy',13) ?></button>
      </form>
      <?php endif; ?>
      <?php if (adminCan('catalog.delete')): ?>
      <button type="button" class="btn-admin-danger btn-admin-sm cph-delete-btn" data-id="<?= $c['id'] ?>" data-name="<?= h(addslashes($c['name'])) ?>" title="Delete"><?= icon('trash',13) ?></button>
      <?php endif; ?>
    </div>
  </td>
</tr>
<?php endforeach; endif; ?>