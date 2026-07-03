<?php
/**
 * pages/room_visualizer.php — Marble Room Visualizer
 */
require_once BASE_PATH . '/includes/room_visualizer.php';

$pid = (int)($_GET['product_id'] ?? 0);
$p   = getProduct($pid);
if (!$p) { flash('error', 'Product not found.'); redirect('index.php?page=catalog'); }

$pageTitle = 'Visualize — ' . h($p['name']);
$showNav   = true;
$extraCSS  = ['room_visualizer.css'];
$extraJS   = ['room_visualizer.js'];

$templatesByType = getRoomTemplatesGrouped();
$history         = getUserRoomVisualizations($_SESSION['user_id'], $pid);
$ph              = $p['photos'][0] ?? null;
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

  <div class="rv-layout">

    <!-- Slab preview -->
    <div class="rv-slab-card">
      <p class="rv-section-label">Selected Slab</p>
      <div class="rv-slab-thumb">
        <?php if ($ph && file_exists(PHOTOS_DIR.'/'.$ph['filename'])): ?>
        <img src="assets/uploads/photos/<?= h($ph['filename']) ?>" alt=""/>
        <?php else: ?>
        <?= marbleSVG($p['palette_arr'], 300, 220, 'rv'.$pid) ?>
        <?php endif; ?>
      </div>
      <p class="rv-slab-name"><?= h($p['name']) ?></p>
      <p class="rv-slab-meta">Lot <?= h($p['quarry_number']) ?></p>
    </div>

    <!-- Room type + generate -->
    <div class="rv-main-card">
      <p class="rv-section-label">1. Choose a Room Type</p>
      <div class="rv-room-tabs" id="rvRoomTabs">
        <?php $first = true; foreach ($templatesByType as $type => $tpls): ?>
        <button type="button" class="rv-room-tab <?= $first?'active':'' ?>" data-type="<?= h($type) ?>">
          <?= h(ROOM_TYPES[$type] ?? ucfirst($type)) ?>
        </button>
        <?php $first = false; endforeach; ?>
      </div>

      <p class="rv-section-label" style="margin-top:18px;">2. Choose a Scene</p>
      <div class="rv-scene-grid" id="rvSceneGrid">
        <?php foreach ($templatesByType as $type => $tpls): foreach ($tpls as $t): ?>
        <div class="rv-scene-item" data-type="<?= h($type) ?>" data-id="<?= $t['id'] ?>" style="<?= $type !== array_key_first($templatesByType) ? 'display:none;' : '' ?>">
          <img src="<?= h(ROOM_TEMPLATES_URL.'/'.$t['base_image']) ?>" alt="<?= h($t['label']) ?>"/>
          <p><?= h($t['label']) ?></p>
        </div>
        <?php endforeach; endforeach; ?>
      </div>

      <button type="button" id="rvGenerateBtn" class="btn btn-gold btn-block btn-lg" style="margin-top:20px;" disabled>
        <?= icon('image',16) ?>&nbsp; Select a scene to generate
      </button>

      <!-- Result -->
      <div id="rvResultWrap" style="display:none;margin-top:24px;">
        <p class="rv-section-label">Preview</p>
        <div class="rv-result-frame">
          <img id="rvResultImg" src="" alt="Room preview"/>
          <div class="rv-result-loader" id="rvLoader"><div class="loader-spinner"></div></div>
        </div>
        <div style="display:flex;gap:10px;margin-top:14px;">
          <button type="button" id="rvRegenerateBtn" class="btn btn-secondary" style="flex:1;">
            <?= icon('refresh',15) ?>&nbsp; Regenerate
          </button>
          <a id="rvDownloadBtn" href="#" download class="btn btn-primary" style="flex:1;text-decoration:none;text-align:center;">
            <?= icon('download',15) ?>&nbsp; Download
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- History -->
  <?php if (!empty($history)): ?>
  <div style="margin-top:32px;">
    <p class="rv-section-label">Previous Renders</p>
    <div class="rv-history-grid">
      <?php foreach ($history as $h): ?>
      <div class="rv-history-item">
        <img src="<?= h($h['storage_driver']==='cloudinary' ? $h['result_image'] : ROOM_PREVIEWS_URL.'/'.$h['result_image']) ?>" alt=""/>
        <p><?= h($h['room_label']) ?></p>
        <button type="button" class="rv-history-delete" data-id="<?= $h['id'] ?>" title="Delete"><?= icon('trash',12) ?></button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>

<script>
window.RV_PRODUCT_ID = <?= $pid ?>;
window.CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;
</script>

<?php include BASE_PATH . '/layouts/footer.php'; ?>