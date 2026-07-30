<?php
/**
 * admin/views/catalog_pdf_templates.php — list/rename/delete saved templates
 */
requireAdminPermission('catalog.template.manage');
$adminTitle = 'Catalog PDF Templates';
include __DIR__ . '/../_layout_top.php';

$templates = getDB()->query("SELECT * FROM catalog_templates ORDER BY name ASC")->fetchAll();
?>
<p style="font-size:13px;color:var(--admin-text3,var(--text3));margin-bottom:16px;">
  Templates capture layout, fields, cover/closing pages, watermark, quality, fonts, and colors — save from Step 5 of the wizard, load from Step 1.
</p>
<div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr><th>Name</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if (empty($templates)): ?>
      <tr><td colspan="3" class="admin-table-empty">No templates saved yet.</td></tr>
      <?php else: foreach ($templates as $t): ?>
      <tr>
        <td style="font-weight:600;"><?= h($t['name']) ?></td>
        <td style="font-size:12px;color:var(--admin-text3,var(--text3));"><?= date('d M Y', $t['created_at']) ?></td>
        <td>
          <a href="index.php?page=catalog_pdf_wizard" onclick="localStorage.setItem('cpwPendingTemplate','<?= (int)$t['id'] ?>')"
             class="btn-admin-secondary btn-admin-sm"><?= icon('grid',13) ?> Use in New Catalog</a>
          <form method="POST" action="index.php" style="display:inline;">
            <input type="hidden" name="action" value="delete_catalog_template"/>
            <input type="hidden" name="template_id" value="<?= $t['id'] ?>"/>
            <?= csrfField() ?>
            <button type="submit" class="btn-admin-danger btn-admin-sm" data-confirm="Delete template '<?= h(addslashes($t['name'])) ?>'?"><?= icon('trash',13) ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../_layout_bottom.php'; ?>