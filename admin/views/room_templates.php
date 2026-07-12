<?php

$adminTitle = 'Room Visualizer Templates';
requireAdminPermission('settings.logo'); // reuse an existing settings permission, or add 'room_templates.manage' to RBAC
include __DIR__ . '/../_layout_top.php';

$db        = getDB();
$templates = $db->query("SELECT * FROM room_templates ORDER BY room_type, sort_order")->fetchAll();
?>
<style>
.rt-grid{display:grid;grid-template-columns:1fr;gap:16px;}
@media(min-width:900px){.rt-grid{grid-template-columns:1fr 1fr;}}
.rt-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--card-radius);padding:16px;}
.rt-thumb{width:100%;aspect-ratio:16/10;object-fit:cover;border-radius:8px;background:var(--surface2);margin-bottom:10px;}

/* ── Vue app transition (used by the create-form panel) ─────────────── */
.rv-fade-enter-active,.rv-fade-leave-active{transition:opacity .25s ease, transform .25s ease;}
.rv-fade-enter-from{opacity:0;transform:translateY(6px);}
.rv-fade-leave-to{opacity:0;transform:translateY(-6px);}

/* ── Touch magnifier loupe ───────────────────────────────────────────── */
.rt-loupe{
  position:absolute;width:110px;height:110px;border-radius:50%;
  border:3px solid #B8975A;box-shadow:0 6px 20px rgba(0,0,0,.4);
  overflow:hidden;pointer-events:none;z-index:30;background:#000;
}
.rt-loupe canvas{display:block;}
@media(max-width:768px){
  .rt-loupe{width:96px;height:96px;}
  .rt-loupe canvas{width:96px;height:96px;}
}
</style>

<!-- ══════════════════ Vue app mounts here (create/edit form) ══════════════════ -->
<div id="rtVueApp"></div>

<!-- ══════════════════ Existing templates (plain PHP list) ═════════════════════ -->
<div class="rt-grid" style="margin-top:20px;">
  <?php foreach ($templates as $t): ?>
  <div class="rt-card">
    <?php if ($t['base_image']): ?>
    <img class="rt-thumb" src="<?= h(ROOM_TEMPLATES_URL.'/'.$t['base_image']) ?>" alt=""/>
    <?php endif; ?>
    <p style="font-weight:700;font-size:14px;"><?= h($t['label']) ?></p>
    <p style="font-size:12px;color:var(--text3);margin-bottom:8px;">
      <?= h(ROOM_TYPES[$t['room_type']] ?? $t['room_type']) ?> ·
      <?= $t['is_active'] ? '<span style="color:var(--success);">Active</span>' : '<span style="color:var(--danger);">Inactive (needs setup)</span>' ?>
    </p>
    <div style="display:flex;gap:8px;">
      <form method="POST" action="index.php" style="display:inline;">
        <input type="hidden" name="action" value="toggle_room_template"/>
        <input type="hidden" name="template_id" value="<?= $t['id'] ?>"/>
        <input type="hidden" name="is_active" value="<?= $t['is_active'] ? 0 : 1 ?>"/>
        <?= csrfField() ?>
        <button type="submit" class="btn-admin-secondary btn-admin-sm"><?= $t['is_active'] ? 'Deactivate' : 'Activate' ?></button>
      </form>
      <form method="POST" action="index.php" style="display:inline;">
        <input type="hidden" name="action" value="delete_room_template"/>
        <input type="hidden" name="template_id" value="<?= $t['id'] ?>"/>
        <?= csrfField() ?>
        <button type="submit" class="btn-admin-danger btn-admin-sm" data-confirm="Delete this template?"><?= icon('trash',13) ?></button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php if (empty($templates)): ?>
<div class="admin-table-empty" style="margin-top:20px;text-align:center;padding:40px 20px;">
  <p style="font-weight:600;color:var(--admin-text,var(--text));margin-bottom:6px;">No room templates yet</p>
  <p style="font-size:12px;color:var(--admin-text3,var(--text3));">Click "+ Add Room Template" above to create the first one.</p>
</div>
<?php endif; ?>

<!-- ══════════════════ Vue mount config + scripts ═══════════════════════════════ -->
<script>
window.RT_CONFIG = <?= json_encode([
    'roomTypes' => ROOM_TYPES,
    'csrfToken' => csrfToken(),
]) ?>;
</script>
<script src="../assets/js/vue.runtime.global.prod.js"></script>
<script src="../assets/js/room_templates.vue.js"></script>

<?php include __DIR__ . '/../_layout_bottom.php'; ?>