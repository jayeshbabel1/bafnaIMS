<?php
/**
 * pages/room_visualizer.php — Marble Room Visualizer (Vue-powered UI)
 */
require_once BASE_PATH . '/includes/room_visualizer.php';

$pid = (int)($_GET['product_id'] ?? 0);
$p   = getProduct($pid);
if (!$p) { flash('error', 'Product not found.'); redirect('index.php?page=catalog'); }

$pageTitle = 'Visualize — ' . h($p['name']);
$showNav   = true;
$extraCSS  = ['room_visualizer.css', 'room_visualizer.vue.css'];

$templatesByType = getRoomTemplatesGrouped();
$history         = getUserRoomVisualizations($_SESSION['user_id'], $pid);
$ph              = $p['photos'][0] ?? null;

// Build JSON payload for Vue — includes resolved URLs so Vue never needs to
// know PHOTOS_DIR / ROOM_TEMPLATES_URL path logic.
$templatesJson = [];
foreach ($templatesByType as $type => $tpls) {
    $templatesJson[$type] = array_map(function ($t) {
        return [
            'id'       => (int)$t['id'],
            'label'    => $t['label'],
            'base_url' => ROOM_TEMPLATES_URL . '/' . $t['base_image'],
        ];
    }, $tpls);
}

$historyJson = array_map(function ($h) {
    return [
        'id'             => (int)$h['id'],
        'result_image'   => $h['storage_driver'] === 'cloudinary' ? $h['result_image'] : $h['result_image'],
        'storage_driver' => $h['storage_driver'],
        'room_label'     => $h['room_label'],
    ];
}, $history);

$productPhotoUrl = ($ph && file_exists(PHOTOS_DIR.'/'.$ph['filename']))
    ? 'assets/uploads/photos/' . $ph['filename']
    : '';
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="page-content">
  <div class="page-header">
    <div class="page-header-left">
      <p class="page-eyebrow">Room Visualizer</p>
      <h1 class="page-title"><?= h($p['name']) ?></h1>
    </div>
    <a href="index.php?page=product&id=<?= $pid ?>" class="btn btn-secondary btn-sm">
      <?= icon('back',14) ?>&nbsp;Back to Product
    </a>
  </div>

  <?php if (empty($templatesByType)): ?>
  <div class="empty-state" style="padding-top:60px;">
    <div class="empty-icon"><?= icon('image',28) ?></div>
    <p class="empty-title">Visualizer not set up yet</p>
    <p class="empty-sub">The Bafna team hasn't added room templates yet. Please check back soon.</p>
  </div>
  <?php else: ?>

  <!-- Vue mounts here -->
  <div id="rvApp"></div>

  <?php endif; ?>
</div>

<script>
window.RV_CONFIG = <?= json_encode([
    'productId'      => $pid,
    'productName'    => $p['name'],
    'productQuarry'  => $p['quarry_number'],
    'productPhoto'   => $productPhotoUrl,
    'csrfToken'      => csrfToken(),
    'templatesByType'=> $templatesJson,
    'history'        => $historyJson,
    'roomTypeLabels' => ROOM_TYPES,
]) ?>;
</script>
<script src="assets/js/vue.runtime.global.prod.js"></script>
<script src="assets/js/room_visualizer.vue.js"></script>

<?php include BASE_PATH . '/layouts/footer.php'; ?>