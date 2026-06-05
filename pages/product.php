<?php
$id = (int)($_GET['id'] ?? 0);
$p  = getProduct($id);
if (!$p) { flash('error','Product not found.'); redirect('index.php?page=catalog'); }

$pageTitle = h($p['name']) . ' — ' . APP_NAME;
$showNav   = true;
$extraJS   = ['product.js','zoom.js'];
$extraCSS  = ['zoom.css'];

$pal           = $p['palette_arr'];
$photos        = $p['photos'];
$saved         = isShortlisted($id);
$cutterDisplay = $p['cutter_size_display'] ?? '';
$sizesDisplay  = $p['sizes_display'] ?? '';

$specs = [
    'Stone Type'     => $p['category'],
    'Subcategory'    => $p['subcategory'],
    'Colour'         => $p['color_subcategory'],
    'Quarry No.'     => $p['quarry_number'],
    'Total Pieces'   => $p['pieces'] ? $p['pieces'].' slabs' : '',
    'Thickness'      => $p['thickness'],
    'Useable Size'   => $sizesDisplay,
    'Italian Size'   => $cutterDisplay,
    'Origin'         => $p['origin'],
    'Finish'         => $p['finish'],
];
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="detail-page">
  <!-- ── HERO ─────────────────────────────────────────────────────────── -->
  <div class="detail-hero" id="heroWrap">
    <?php if ($photos && file_exists(PHOTOS_DIR.'/'.$photos[0]['filename'])): ?>
    <div class="zoom-container" id="heroZoomContainer">
      <img id="heroImg"
           src="assets/uploads/photos/<?= h($photos[0]['filename']) ?>"
           alt="<?= h($p['name']) ?>"
           class="detail-hero-img zoom-target"
           data-zoom-id="hero"/>
    </div>
    <?php else: ?>
    <div id="heroSvg" style="width:100%;height:100%;"><?= marbleSVG($pal, 430, 340, 'dh'.$id) ?></div>
    <?php endif; ?>
    <div class="detail-hero-overlay"></div>

    <!-- Top bar -->
    <div class="detail-hero-top">
      <a href="javascript:history.back()" class="hero-icon-btn"><?= icon('back',18) ?></a>
      <div class="detail-hero-actions">
        <button class="hero-icon-btn" onclick="openShareModal()"><?= icon('share',16) ?></button>
        <form method="POST" action="index.php" style="margin:0">
          <input type="hidden" name="action"     value="toggle_shortlist"/>
          <input type="hidden" name="product_id" value="<?= $id ?>"/>
          <input type="hidden" name="return_url" value="index.php?page=product&id=<?= $id ?>"/>
          <button type="submit" class="hero-icon-btn" style="<?= $saved?'color:#e11d48':'' ?>">
            <?= $saved ? icon('heart_fill',16) : icon('heart',16) ?>
          </button>
        </form>
      </div>
    </div>

    <!-- Zoom controls -->
    <?php if ($photos && file_exists(PHOTOS_DIR.'/'.$photos[0]['filename'])): ?>
    <div class="zoom-controls zoom-controls--hero" data-target="hero">
      <button class="zoom-btn" data-action="in" title="Zoom in"><?= icon('zoom',14) ?></button>
      <button class="zoom-btn" data-action="out" title="Zoom out"><?= icon('search',14) ?></button>
      <button class="zoom-btn zoom-btn--reset" data-action="reset">1:1</button>
    </div>
    <?php endif; ?>

    <!-- Status -->
    <div class="detail-status">
      <?= $p['in_stock'] ? '<span class="badge badge-green">● In Stock</span>' : '<span class="badge badge-gray">Out of Stock</span>' ?>
      <?php if ($p['featured']): ?><span class="badge badge-gold">★ Featured</span><?php endif; ?>
    </div>

    <!-- Thumbnails -->
    <?php if (count($photos) > 1): ?>
    <div class="detail-thumbs">
      <?php foreach ($photos as $i => $ph):
        $imgSrc = file_exists(PHOTOS_DIR.'/'.$ph['filename']) ? 'assets/uploads/photos/'.h($ph['filename']) : null;
      ?>
      <div class="detail-thumb <?= $i===0?'active':'' ?>"
           data-src="<?= $imgSrc ? h($imgSrc) : '' ?>"
           onclick="switchPhoto(this)">
        <?php if ($imgSrc): ?>
        <img src="<?= h($imgSrc) ?>" alt="" style="width:100%;height:100%;object-fit:cover;"/>
        <?php else: ?><?= marbleSVG(array_reverse($pal), 48, 48, 'th'.$i) ?><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div><!-- .detail-hero -->

  <!-- ── BODY ─────────────────────────────────────────────────────────── -->
  <div class="detail-body">
   
    <!-- Tags -->
    <div class="detail-tags">
      <span class="badge badge-amber"><?= h($p['category']) ?></span>
      <?php if ($p['subcategory']): ?><span class="badge badge-gray"><?= h($p['subcategory']) ?></span><?php endif; ?>
      <?php if ($p['color_subcategory']): ?><span class="badge badge-white"><?= h($p['color_subcategory']) ?></span><?php endif; ?>
    </div>

    <div class="gold-bar"></div>
    <h1 class="detail-title"><?= h($p['name']) ?></h1>
    <p class="detail-quarry">Lot <?= h($p['quarry_number']) ?><?= $p['origin'] ? ' · '.h($p['origin']) : '' ?></p>
    <?php if ($p['description']): ?>
    <p class="detail-desc"><?= h($p['description']) ?></p>
    <?php endif; ?>

    <!-- Quantities -->
    <div class="qty-strip">
      <div class="qty-tile">
        <div class="qty-tile-label">Total</div>
        <div class="qty-tile-value"><?= number_format((float)$p['total_quantity']) ?></div>
        <div class="qty-tile-unit">sqft</div>
      </div>
      <div class="qty-tile">
        <div class="qty-tile-label">On Hold</div>
        <div class="qty-tile-value"><?= number_format((float)$p['quantity_on_hold']) ?></div>
        <div class="qty-tile-unit">sqft</div>
      </div>
      <div class="qty-tile" style="border:1px solid var(--black);">
        <div class="qty-tile-label">Available</div>
        <div class="qty-tile-value" style="color:var(--black);"><?= number_format((float)$p['quantity_available']) ?></div>
        <div class="qty-tile-unit">sqft</div>
      </div>
    </div>

    <!-- Quick specs -->
    <div class="spec-grid">
      <?php
      $quickSpecs = ['Thickness'=>$p['thickness'],'Finish'=>$p['finish'],'Useable Size'=>$sizesDisplay,'Italian Size'=>$cutterDisplay];
      foreach ($quickSpecs as $k => $v):
        if (!trim((string)$v)) continue;
      ?>
      <div class="spec-tile">
        <div class="spec-tile-label"><?= h($k) ?></div>
        <div class="spec-tile-value"><?= h($v) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Full specs -->
    <div class="spec-table-card">
      <div class="spec-table-header">Full Specifications</div>
      <?php foreach ($specs as $k => $v): if (!trim((string)$v)) continue; ?>
      <div class="spec-row">
        <span class="spec-key"><?= h($k) ?></span>
        <span class="spec-val"><?= h($v) ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Photo gallery -->
    <?php if ($photos): ?>
    <p class="section-label">Gallery</p>
    <div class="photo-gallery">
      <?php foreach ($photos as $ph):
        $imgSrc = file_exists(PHOTOS_DIR.'/'.$ph['filename']) ? 'assets/uploads/photos/'.h($ph['filename']) : null;
        if (!$imgSrc) continue;
      ?>
      <div class="gallery-item" onclick="openLightbox('<?= h($imgSrc) ?>')">
        <img src="<?= h($imgSrc) ?>" alt="" loading="lazy"/>
        <div class="gallery-overlay"><?= icon('zoom',18) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Documents -->
    <?php if ($p['measurement_sheet'] || $p['dna_report']): ?>
    <p class="section-label">Documents</p>
    <?php if ($p['measurement_sheet']): ?>
    <div class="doc-card">
      <div class="doc-icon"><?= icon('file',20) ?></div>
      <div class="doc-info">
        <p class="doc-name"><?= h(basename($p['measurement_sheet'])) ?></p>
        <p class="doc-meta">Measurement Sheet</p>
      </div>
      <a href="assets/uploads/measurement_sheets/<?= h($p['measurement_sheet']) ?>" target="_blank"
         class="btn btn-secondary btn-sm"><?= icon('eye',13) ?> View</a>
      <a href="assets/uploads/measurement_sheets/<?= h($p['measurement_sheet']) ?>" download
         class="btn btn-secondary btn-sm"><?= icon('download',13) ?>Download</a>
    </div>
    <?php endif; ?>
    <?php if ($p['dna_report']): ?>
    <div class="doc-card">
      <div class="doc-icon" style="color:var(--danger);"><?= icon('pdf',20) ?></div>
      <div class="doc-info">
        <p class="doc-name"><?= h(basename($p['dna_report'])) ?></p>
        <p class="doc-meta">DNA Report</p>
      </div>
      <a href="assets/uploads/dna_reports/<?= h($p['dna_report']) ?>" target="_blank"
         class="btn btn-secondary btn-sm"><?= icon('eye',13) ?> View</a>
      <a href="assets/uploads/dna_reports/<?= h($p['dna_report']) ?>" download
         class="btn btn-secondary btn-sm"><?= icon('download',13) ?>Download</a>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- CTAs -->
    <div class="detail-cta" style="margin-top:22px;">
      <form method="POST" action="index.php" style="flex:1">
        <input type="hidden" name="action"     value="toggle_shortlist"/>
        <input type="hidden" name="product_id" value="<?= $id ?>"/>
        <input type="hidden" name="return_url" value="index.php?page=product&id=<?= $id ?>"/>
        <button type="submit" class="btn btn-secondary btn-block">
          <?= $saved ? icon('heart_fill',16).'&nbsp;Saved' : icon('heart',16).'&nbsp;Save' ?>
        </button>
      </form>
      <button onclick="openShareModal()" class="btn btn-secondary" style="flex:1;">
        <?= icon('share',16) ?>&nbsp; Share
      </button>
    </div>
  </div><!-- .detail-body -->
</div><!-- .detail-page -->

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="lightboxBgClick(event)">
  <button class="lightbox-close" onclick="closeLightbox()"><?= icon('close',22) ?></button>
  <div class="zoom-controls zoom-controls--lightbox" data-target="lightbox">
    <button class="zoom-btn" data-action="in">+</button>
    <button class="zoom-btn" data-action="out">−</button>
    <button class="zoom-btn zoom-btn--reset" data-action="reset">1:1</button>
  </div>
  <div class="zoom-container zoom-container--lightbox">
    <img class="lightbox-img zoom-target" id="lightboxImg" src="" alt="" data-zoom-id="lightbox"/>
  </div>
  <a class="lightbox-dl" id="lightboxDl" href="#" download><?= icon('download',17) ?> Download</a>
</div>

<!-- Share modal -->
<div class="modal-overlay" id="shareModal" onclick="if(event.target===this)closeShareModal()">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
      <p style="font-family:var(--font-display);font-size:17px;font-weight:700;">Share Product</p>
      <button onclick="closeShareModal()" style="color:var(--text3);padding:4px;cursor:pointer;"><?= icon('close',18) ?></button>
    </div>
    <?php $shareUrl = BASE_URL.'/index.php?page=product&id='.$id; $shareText = urlencode($p['name'].' — Bafna Marbles'); ?>
    <a href="https://wa.me/?text=<?= $shareText ?>%20<?= urlencode($shareUrl) ?>" target="_blank" class="share-option">
      <div class="share-icon" style="color:#25D366;"><?= icon('whatsapp',20) ?></div><span>Share via WhatsApp</span>
    </a>
    <a href="mailto:?subject=<?= $shareText ?>&body=<?= urlencode($shareUrl) ?>" class="share-option">
      <div class="share-icon" style="color:var(--gold);"><?= icon('mail',20) ?></div><span>Share via Email</span>
    </a>
    <button class="share-option" onclick="copyLink('<?= h($shareUrl) ?>')">
      <div class="share-icon"><?= icon('copy',20) ?></div><span id="copyLinkLabel">Copy Link</span>
    </button>
  </div>
</div>

<?php include BASE_PATH . '/layouts/footer.php'; ?>