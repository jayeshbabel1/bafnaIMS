<?php
/**
 * pages/product.php — Task 3: Image zoom on hero + gallery
 */
$id = (int)($_GET['id'] ?? 0);
$p  = getProduct($id);
if (!$p) { flash('error','Product not found.'); redirect('index.php?page=catalog'); }

$pageTitle = h($p['name']) . ' — ' . APP_NAME;
$showNav   = false;
$extraJS   = ['product.js', 'zoom.js'];
$extraCSS  = ['zoom.css'];

$pal    = $p['palette_arr'];
$photos = $p['photos'];
$saved  = isShortlisted($id);

// Use pre-computed display strings from getProduct()
$cutterDisplay = $p['cutter_size_display'] ?? '';
$sizesDisplay  = $p['sizes_display'] ?? '';

$specs = [
    'Product Name'      => $p['name'],
    'Category'          => $p['category'],
    'Sub-Category'      => $p['subcategory'],
    'Colour Family'     => $p['color_subcategory'],
    'Quarry / Lot No.'  => $p['quarry_number'],
    'No. of Pieces'     => $p['pieces'] . ' slabs',
    'Thickness'         => $p['thickness'],
    'Slab Size'         => $sizesDisplay,
    'Cutter Size'       => $cutterDisplay,
    'Origin'            => $p['origin'],
    'Finish'            => $p['finish'],
];
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="detail-page">

  <!-- ── HERO ─────────────────────────────────────────────────────────────── -->
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
    <div class="detail-hero-svg" id="heroSvg"><?= marbleSVG($pal, 430, 340, 'dh'.$id) ?></div>
    <?php endif; ?>
    <div class="detail-hero-overlay"></div>

    <!-- Top controls -->
    <div class="detail-hero-top">
      <a href="javascript:history.back()" class="hero-icon-btn"><?= icon('back',18) ?></a>
      <div class="detail-hero-actions">
        <button class="hero-icon-btn" onclick="openShareModal()"><?= icon('share',16) ?></button>
        <form method="POST" action="index.php" style="margin:0">
          <input type="hidden" name="action"     value="toggle_shortlist"/>
          <input type="hidden" name="product_id" value="<?= $id ?>"/>
          <input type="hidden" name="return_url" value="index.php?page=product&id=<?= $id ?>"/>
          <button type="submit" class="hero-icon-btn" style="<?= $saved?'color:var(--danger)':'' ?>">
            <?= $saved ? icon('heart_fill',16) : icon('heart',16) ?>
          </button>
        </form>
      </div>
    </div>

    <!-- Zoom controls for hero -->
    <?php if ($photos && file_exists(PHOTOS_DIR.'/'.$photos[0]['filename'])): ?>
    <div class="zoom-controls zoom-controls--hero" id="heroZoomControls" data-target="hero">
      <button class="zoom-btn" data-action="in"  title="Zoom in"><?= icon('zoom', 15) ?></button>
      <button class="zoom-btn" data-action="out" title="Zoom out"><?= icon('search', 15) ?></button>
      <button class="zoom-btn zoom-btn--reset" data-action="reset" title="Reset">1:1</button>
    </div>
    <?php endif; ?>

    <!-- Status badge -->
    <div class="detail-status">
      <?= $p['in_stock'] ? '<span class="badge badge-green">● In Stock</span>' : '<span class="badge badge-gray">Out of Stock</span>' ?>
      <?php if ($p['featured']): ?><span class="badge badge-gold" style="margin-left:6px;">✦ Featured</span><?php endif; ?>
    </div>

    <!-- Photo thumbnails -->
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
        <?php else: ?>
        <?= marbleSVG(array_reverse($pal), 48, 48, 'th'.$i) ?>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php elseif (count($photos) === 0): ?>
    <div class="detail-thumbs">
      <?php foreach ([[$pal[0],$pal[1],$pal[2]], array_reverse($pal), [$pal[2],$pal[0],$pal[1]]] as $vi => $vpal): ?>
      <div class="detail-thumb <?= $vi===0?'active':'' ?>" onclick="switchPalette(this)">
        <?= marbleSVG($vpal, 48, 48, 'tv'.$vi) ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div><!-- .detail-hero -->

  <!-- ── BODY ─────────────────────────────────────────────────────────────── -->
  <div class="detail-body">

    <div class="detail-badges">
      <span class="badge badge-amber"><?= h($p['category']) ?></span>
      <?php if ($p['subcategory']): ?><span class="badge badge-gray"><?= h($p['subcategory']) ?></span><?php endif; ?>
      <?php if ($p['color_subcategory']): ?><span class="badge badge-dark"><?= h($p['color_subcategory']) ?></span><?php endif; ?>
    </div>
    <div class="gold-bar"></div>
    <h1 class="detail-title"><?= h($p['name']) ?></h1>
    <p class="detail-lot">Quarry Number <?= h($p['quarry_number']) ?> · <?= h($p['origin']) ?></p>
    <?php if ($p['description']): ?>
    <p class="detail-desc"><?= h($p['description']) ?></p>
    <?php endif; ?>

    <!-- Quantity tiles -->
    <div class="qty-strip">
      <div class="qty-tile">
        <div class="qty-tile-label">Total Quantity</div>
        <div class="qty-tile-value"><?= number_format((float)$p['total_quantity']) ?></div>
        <div class="qty-tile-unit">sqft</div>
      </div>
      <div class="qty-tile">
        <div class="qty-tile-label">Available</div>
        <div class="qty-tile-value" style="color:var(--gold);"><?= number_format((float)$p['quantity_available']) ?></div>
        <div class="qty-tile-unit">sqft</div>
      </div>
      <div class="qty-tile">
        <div class="qty-tile-label">On Hold</div>
        <div class="qty-tile-value"><?= number_format((float)$p['quantity_on_hold']) ?></div>
        <div class="qty-tile-unit">sqft</div>
      </div>
    </div>

    <!-- Quick spec tiles — now includes Slab Size and Cutter Size -->
    <div class="spec-grid">
      <?php
      $quickSpecs = [
          'Thickness' => $p['thickness'],
          'Finish'    => $p['finish'],
          'Slab Size' => $sizesDisplay,
          'Cutter'    => $cutterDisplay,
      ];
      foreach ($quickSpecs as $k => $v):
          if (!trim($v, ' -\'"')) continue;
      ?>
      <div class="spec-tile">
        <div class="spec-tile-label"><?= h($k) ?></div>
        <div class="spec-tile-value"><?= h($v) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Full specs -->
    <div class="card" style="padding:0 16px;margin-bottom:18px;">
      <p class="spec-section-title">Full Specifications</p>
      <div class="spec-table">
        <?php foreach ($specs as $k => $v): if (!trim((string)$v)) continue; ?>
        <div class="spec-row">
          <span class="spec-key"><?= h($k) ?></span>
          <span class="spec-val"><?= h($v) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Photo gallery with zoom support -->
    <?php if ($photos): ?>
    <p class="spec-section-title">Photo Gallery</p>
    <div class="photo-gallery" style="margin-bottom:18px;">
      <?php foreach ($photos as $ph):
        $imgSrc = file_exists(PHOTOS_DIR.'/'.$ph['filename']) ? 'assets/uploads/photos/'.h($ph['filename']) : null;
        if (!$imgSrc) continue;
      ?>
      <div class="gallery-item" onclick="openLightbox('<?= h($imgSrc) ?>')">
        <img src="<?= h($imgSrc) ?>" alt="" loading="lazy"/>
        <div class="gallery-overlay">
          <?= icon('zoom',18) ?>
          <a href="<?= h($imgSrc) ?>" download class="gallery-dl" onclick="event.stopPropagation()"><?= icon('download',14) ?></a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Documents -->
    <?php if ($p['measurement_sheet'] || $p['dna_report']): ?>
    <p class="spec-section-title">Documents</p>
    <?php if ($p['measurement_sheet']): ?>
    <div class="card doc-card" style="margin-bottom:10px;">
      <div class="doc-icon" style="color:var(--success);"><?= icon('file',20) ?></div>
      <div class="doc-info">
        <p class="doc-name"><?= h(basename($p['measurement_sheet'])) ?> </p>
        <p class="doc-meta">Measurement Sheet</p>
      </div>
      <a href="assets/uploads/measurement_sheets/<?= h($p['measurement_sheet']) ?>" 
   target="_blank"
   class="btn-outline btn-sm">
   <?= icon('eye',13) ?> View 
</a>
      <a href="assets/uploads/measurement_sheets/<?= h($p['measurement_sheet']) ?>" download
         class="btn-outline btn-sm"><?= icon('download',13) ?> Download </a>
    </div>
    <?php endif; ?>
    <?php if ($p['dna_report']): ?>
    <div class="card doc-card" style="margin-bottom:10px;">
      <div class="doc-icon" style="color:var(--danger);"><?= icon('pdf',20) ?></div>
      <div class="doc-info">
        <p class="doc-name"><?= h(basename($p['dna_report'])) ?></p>
        <p class="doc-meta">DNA / Lot Report</p>
      </div>
       <a href="assets/uploads/dna_reports/<?= h($p['dna_report']) ?>" target="_blank"
         class="btn-outline btn-sm"><?= icon('eye',13) ?>View </a>
      <a href="assets/uploads/dna_reports/<?= h($p['dna_report']) ?>" download
         class="btn-outline btn-sm"><?= icon('download',13) ?> Download </a>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- CTAs -->
    <div class="detail-cta">
      <form method="POST" action="index.php" style="flex:1">
        <input type="hidden" name="action"     value="toggle_shortlist"/>
        <input type="hidden" name="product_id" value="<?= $id ?>"/>
        <input type="hidden" name="return_url" value="index.php?page=product&id=<?= $id ?>"/>
        <button type="submit" class="btn-outline" style="width:100%">
          <?= $saved ? icon('heart_fill',16).'&nbsp;Saved' : icon('heart',16).'&nbsp;Save' ?>
        </button>
      </form>
      <a href="index.php?page=inquiry_form&product_id=<?= $id ?>"
         class="btn-outline" style="flex:2;text-decoration:none;">
        <?= icon('msg',16) ?>&nbsp; Send Inquiry
      </a>
    </div>
    <button onclick="openShareModal()" class="btn-ghost"
            style="width:100%;justify-content:center;margin-top:8px;background:var(--surface2);border-radius:var(--btn-radius);">
      <?= icon('share',15) ?>&nbsp; Share with Client
    </button>
  </div><!-- .detail-body -->
</div><!-- .detail-page -->

<!-- ── LIGHTBOX with zoom ────────────────────────────────────────────────── -->
<div class="lightbox" id="lightbox" onclick="lightboxBgClick(event)">
  <button class="lightbox-close" onclick="closeLightbox()"><?= icon('close',22) ?></button>

  <div class="zoom-controls zoom-controls--lightbox" data-target="lightbox">
    <button class="zoom-btn" data-action="in"    title="Zoom in">+</button>
    <button class="zoom-btn" data-action="out"   title="Zoom out">−</button>
    <button class="zoom-btn zoom-btn--reset" data-action="reset" title="Reset">1:1</button>
  </div>

  <div class="zoom-container zoom-container--lightbox" id="lightboxZoomContainer">
    <img class="lightbox-img zoom-target" id="lightboxImg" src="" alt="" data-zoom-id="lightbox"/>
  </div>

  <a class="lightbox-dl" id="lightboxDl" href="#" download><?= icon('download',17) ?> Download</a>
</div>

<!-- Share modal -->
<div class="modal-overlay" id="shareModal" onclick="if(event.target===this)closeShareModal()">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
      <p style="font-weight:700;font-size:17px;">Share with Client</p>
      <button onclick="closeShareModal()" class="btn-ghost" style="padding:4px;"><?= icon('close',18) ?></button>
    </div>
    <?php
    $shareUrl  = BASE_URL . '/index.php?page=product&id=' . $id;
    $shareText = urlencode($p['name'] . ' — Bafna Marbles');
    ?>
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