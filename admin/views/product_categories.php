<?php
require_once BASE_PATH . '/includes/categories.php';
requireAdminPermission('categories.view');
$adminTitle='Product Categories';
include __DIR__ . '/../_layout_top.php';
$categories=getAllCategories();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
  <p style="font-size:13px;color:var(--text3);"><?= count($categories) ?> categories</p>
  <?php if(adminCan('categories.create')): ?>
  <button type="button" onclick="openCatModal()" class="btn-admin-primary"><?= icon('plus',14) ?> Add Category</button>
  <?php endif; ?>
</div>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead><tr><th>Name</th><th>Products</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if(empty($categories)): ?>
      <tr><td colspan="3" class="admin-table-empty">No categories yet.</td></tr>
      <?php else: foreach($categories as $c): $pc=categoryProductCount($c['name']); ?>
      <tr>
        <td style="font-weight:600;"><?= h($c['name']) ?></td>
        <td><span class="badge badge-blue"><?= $pc ?> products</span></td>
        <td>
          <div style="display:flex;gap:5px;">
            <?php if(adminCan('categories.edit')): ?>
            <button type="button" class="btn-admin-secondary btn-admin-sm"
                    onclick="openCatModal(<?= $c['id'] ?>, <?= h(json_encode($c['name'])) ?>)"><?= icon('edit',13) ?></button>
            <?php endif; ?>
            <?php if(adminCan('categories.delete')): ?>
            <form method="POST" action="index.php" style="display:inline;">
              <input type="hidden" name="action" value="delete_category"/>
              <input type="hidden" name="category_id" value="<?= $c['id'] ?>"/>
              <?= csrfField() ?>
              <button type="submit" class="btn-admin-danger btn-admin-sm"
                      <?= $pc>0?'disabled title="Cannot delete — products assigned"':'data-confirm="Delete this category?"' ?>><?= icon('trash',13) ?></button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<div id="catModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9300;align-items:center;justify-content:center;padding:16px;">
  <div class="role-modal-card" style="max-width:420px;background:var(--surface);border-radius:14px;padding:24px 22px;width:100%;">
    <p id="catModalTitle" style="font-size:16px;font-weight:700;margin-bottom:16px;">Add Category</p>
    <form method="POST" action="index.php" id="catForm">
      <input type="hidden" name="action" id="catFormAction" value="create_category"/>
      <input type="hidden" name="category_id" id="catFormId" value=""/>
      <?= csrfField() ?>
      <div style="margin-bottom:16px;">
        <label class="admin-label">Category Name *</label>
        <input type="text" name="name" id="catFormName" class="admin-input" required autocomplete="off"/>
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn-admin-primary" style="flex:1;justify-content:center;"><?= icon('check',15) ?> Save</button>
        <button type="button" class="btn-admin-secondary" onclick="closeCatModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>
<script>
function openCatModal(id, name){
  document.getElementById('catModalTitle').textContent = id ? 'Edit Category' : 'Add Category';
  document.getElementById('catFormAction').value = id ? 'update_category' : 'create_category';
  document.getElementById('catFormId').value = id || '';
  document.getElementById('catFormName').value = name || '';
  document.getElementById('catModal').style.display='flex';
  setTimeout(()=>document.getElementById('catFormName').focus(),80);
}
function closeCatModal(){ document.getElementById('catModal').style.display='none'; }
document.getElementById('catModal').addEventListener('click',e=>{ if(e.target===e.currentTarget) closeCatModal(); });
</script>
<?php include __DIR__ . '/../_layout_bottom.php'; ?>