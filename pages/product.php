<?php
$id = (int)($_GET['id'] ?? 0);
$p  = getProduct($id);
if (!$p) { flash('error','Product not found.'); redirect('index.php?page=catalog'); }

$pageTitle = h($p['name']) . ' — ' . APP_NAME;
$showNav   = false;
$extraJS   = ['product.js'];

$pal    = $p['palette_arr'];
$photos = $p['photos'];
$saved  = isShortlisted($id);
$tab    = $_GET['variant'] ?? 0;

$specs = [
    'Product Name'      => $p['name'],
    'Category'          => $p['category'],
    'Sub-Category'      => $p['subcategory'],
    'Colour Family'     => $p['color_subcategory'],
    'Quarry Number'     => $p['quarry_number'],
    'Quantity Available'=> number_format((float)$p['quantity'],2) . ' sq.ft.',
    'No. of Pieces'     => $p['pieces'] . ' slabs',
    'Thickness'         => $p['thickness'] . ' mm',
    'Available Sizes'   => $p['sizes'],
    'Cutter Size'       => $p['cutter_size'] . ' inches',
    'Origin'            => $p['origin'],
    'Finish'            => $p['finish'],
];
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="detail-page">

  <!-- ── HERO ── -->
  <div class="detail-hero" id="heroWrap">
    <?php if ($photos && file_exists(PHOTOS_DIR . '/' . $photos[0]['filename'])): ?>
    <img id="heroImg" src="assets/uploads/photos/<?= h($photos[0]['filename']) ?>"
         alt="<?= h($p['name']) ?>" class="detail-hero-img" data-lightbox="gallery"/>
    <?php else: ?>
    <div class="detail-hero-svg" id="heroSvg"><?= marbleSVG($pal, 430, 340, 'dh'.$id) ?></div>
    <?php endif; ?>
    <div class="detail-hero-overlay"></div>

    <!-- Controls -->
    <div class="detail-hero-top">
      <a href="javascript:history.back()" class="hero-icon-btn"><?= icon('back',18) ?></a>
      <div class="detail-hero-actions">
        <button class="hero-icon-btn" id="shareBtn" onclick="openShareModal()"><?= icon('share',16) ?></button>
        <form method="POST" action="index.php" style="margin:0">
          <input type="hidden" name="action"     value="toggle_shortlist"/>
          <input type="hidden" name="product_id" value="<?= $id ?>"/>
          <input type="hidden" name="return_url" value="index.php?page=product&id=<?= $id ?>"/>
          <button type="submit" class="hero-icon-btn" title="<?= $saved?'Remove':'Save' ?>">
            <?= $saved ? icon('heart_fill',16) : icon('heart',16) ?>
          </button>
        </form>
      </div>
    </div>

    <!-- Status -->
    <div class="detail-status">
      <?= $p['in_stock'] ? '<span class="badge badge-green">● In Stock</span>' : '<span class="badge badge-gray">Out of Stock</span>' ?>
    </div>

    <!-- Photo Thumbnails -->
    <?php if (count($photos) > 1): ?>
    <div class="detail-thumbs" id="thumbStrip">
      <?php foreach ($photos as $i => $ph):
        $imgSrc = file_exists(PHOTOS_DIR.'/'.$ph['filename']) ? 'assets/uploads/photos/'.h($ph['filename']) : null;
      ?>
      <div class="detail-thumb <?= $i===0?'active':'' ?>" data-index="<?= $i ?>"
           data-src="<?= $imgSrc ? h($imgSrc) : '' ?>"
           data-palette="<?= h(json_encode($pal)) ?>"
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
    <!-- Palette switcher when no photos -->
    <div class="detail-thumbs">
      <?php $variants = [
        $pal, array_reverse($pal), [$pal[2],$pal[0],$pal[1]]
      ];
      foreach ($variants as $vi => $vpal): ?>
      <div class="detail-thumb <?= $vi===0?'active':'' ?>" data-index="<?= $vi ?>"
           data-palette="<?= h(json_encode($vpal)) ?>" onclick="switchPalette(this, <?= h(json_encode($vpal)) ?>)">
        <?= marbleSVG($vpal, 48, 48, 'tv'.$vi) ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── BODY ── -->
  <div class="detail-body">
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;">
      <span class="badge badge-blue"><?= h($p['category']) ?></span>
      <?php if ($p['subcategory']): ?><span class="badge badge-gray"><?= h($p['subcategory']) ?></span><?php endif; ?>
      <?php if ($p['featured']): ?><span class="badge badge-gold">✦ Featured</span><?php endif; ?>
    </div>

    <h1 class="detail-title serif"><?= h($p['name']) ?></h1>
    <?php if ($p['description']): ?>
    <p class="detail-desc"><?= h($p['description']) ?></p>
    <?php endif; ?>

    <!-- Quick spec tiles -->
    <div class="spec-grid">
      <?php foreach (['Quarry Number'=>$p['quarry_number'],'Origin'=>$p['origin'],'Thickness'=>$p['thickness'].' mm','Finish'=>$p['finish']] as $k=>$v): ?>
      <div class="spec-tile">
        <div class="spec-tile-label"><?= h($k) ?></div>
        <div class="spec-tile-value"><?= h($v) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Full spec table -->
    <div class="card" style="padding:0 16px;margin-bottom:18px;">
      <p class="spec-section-title">Full Specifications</p>
      <div class="spec-table">
        <?php foreach ($specs as $k => $v): ?>
        <?php if (!$v) continue; ?>
        <div class="spec-row">
          <span class="spec-key"><?= h($k) ?></span>
          <span class="spec-val"><?= h($v) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Photo Gallery -->
    <?php if ($photos): ?>
    <div style="margin-bottom:18px;">
      <p class="spec-section-title" style="padding:12px 0 10px;">Photo Gallery</p>
      <div class="photo-gallery" id="photoGallery">
        <?php foreach ($photos as $ph):
          $imgSrc = file_exists(PHOTOS_DIR.'/'.$ph['filename']) ? 'assets/uploads/photos/'.h($ph['filename']) : null;
          if (!$imgSrc) continue;
        ?>
        <div class="gallery-item" onclick="openLightbox('<?= h($imgSrc) ?>')">
          <img src="<?= h($imgSrc) ?>" alt="<?= h($p['name']) ?>" loading="lazy"/>
          <div class="gallery-overlay">
            <?= icon('zoom',18) ?>
            <a href="<?= h($imgSrc) ?>" download class="gallery-dl" onclick="event.stopPropagation()"
               title="Download"><?= icon('download',16) ?></a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Documents -->
    <?php if ($p['measurement_sheet'] || $p['dna_report']): ?>
    <p class="spec-section-title" style="padding:4px 0 10px;">Documents</p>

    <?php if ($p['measurement_sheet']): ?>
    <div class="card doc-card" style="margin-bottom:10px;">
      <div class="doc-icon" style="color:var(--accent);"><?= icon('file',20) ?></div>
      <div class="doc-info">
        <p class="doc-name"><?= h($p['measurement_sheet']) ?></p>
        <p class="doc-meta">Measurement Sheet</p>
      </div>
      <a href="assets/uploads/measurement_sheets/<?= h($p['measurement_sheet']) ?>" download="<?= h($p['measurement_sheet']) ?>"
         class="btn-outline btn-sm"><?= icon('download',14) ?> Download</a>
    </div>
    <?php endif; ?>

    <?php if ($p['dna_report']): ?>
    <div class="card doc-card" style="margin-bottom:10px;">
      <div class="doc-icon" style="color:#E84040;"><?= icon('pdf',20) ?></div>
      <div class="doc-info">
        <p class="doc-name"><?= h($p['dna_report']) ?></p>
        <p class="doc-meta">DNA / Lot Report</p>
      </div>
      <a href="assets/uploads/dna_reports/<?= h($p['dna_report']) ?>" download="<?= h($p['dna_report']) ?>"
         class="btn-outline btn-sm"><?= icon('download',14) ?> Download</a>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- CTAs -->
    <div class="detail-cta" style="margin-top:22px;">
      <form method="POST" action="index.php" style="flex:1">
        <input type="hidden" name="action"     value="toggle_shortlist"/>
        <input type="hidden" name="product_id" value="<?= $id ?>"/>
        <input type="hidden" name="return_url" value="index.php?page=product&id=<?= $id ?>"/>
        <button type="submit" class="btn-outline" style="width:100%">
          <?= $saved ? icon('heart_fill',16).'&nbsp;Saved' : icon('heart',16).'&nbsp;Save' ?>
        </button>
      </form>
      <a href="index.php?page=inquiry_form&product_id=<?= $id ?>" class="btn-primary" style="flex:2;text-decoration:none;">
        <?= icon('msg',16) ?>&nbsp;Send Inquiry
      </a>
    </div>
    <button onclick="openShareModal()" class="btn-ghost" style="width:100%;justify-content:center;border-radius:var(--r);background:var(--surface2);margin-top:8px;">
      <?= icon('share',16) ?>&nbsp; Share with Client
    </button>
  </div>

</div><!-- .detail-page -->

<!-- ── LIGHTBOX ── -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
  <button class="lightbox-close" onclick="closeLightbox()"><?= icon('close',22) ?></button>
  <img class="lightbox-img" id="lightboxImg" src="" alt="Product photo"/>
  <a class="lightbox-dl" id="lightboxDl" href="#" download><?= icon('download',18) ?> Download</a>
</div>

<!-- ── SHARE MODAL ── -->
<div class="modal-overlay" id="shareModal" onclick="if(event.target===this)closeShareModal()">
  <div class="modal-sheet">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <p style="font-weight:700;font-size:17px;">Share with Client</p>
      <button class="btn-ghost" style="padding:4px" onclick="closeShareModal()"><?= icon('close',18) ?></button>
    </div>
    <?php $shareUrl = BASE_URL . '/index.php?page=product&id=' . $id;
          $shareText = urlencode('Check out this product from Bafna Marbles: ' . $p['name']); ?>
    <a href="https://wa.me/?text=<?= $shareText ?>%20<?= urlencode($shareUrl) ?>" target="_blank" class="share-option">
      <div class="share-icon" style="color:#25D366;"><?= icon('whatsapp',20) ?></div>
      <span>Share via WhatsApp</span>
    </a>
    <a href="mailto:?subject=<?= urlencode($p['name']).' — Bafna Marbles' ?>&body=<?= $shareText ?>%20<?= urlencode($shareUrl) ?>" class="share-option">
      <div class="share-icon" style="color:var(--accent);"><?= icon('mail',20) ?></div>
      <span>Share via Email</span>
    </a>
    <button class="share-option" onclick="copyLink('<?= h($shareUrl) ?>')">
      <div class="share-icon" style="color:var(--text2);"><?= icon('copy',20) ?></div>
      <span id="copyLinkLabel">Copy Link</span>
    </button>
  </div>
</div>

<?php include BASE_PATH . '/layouts/footer.php'; ?>
