<?php
$id = (int)($_GET['id'] ?? 0);
$p  = getProduct($id);
if (!$p) { flash('error','Product not found.'); redirect('index.php?page=catalog'); }

require_once BASE_PATH . '/includes/clients.php';

$pageTitle = h($p['name']) . ' — ' . APP_NAME;
$showNav   = true;
$extraJS   = ['product.js','zoom.js'];
$extraCSS  = ['zoom.css','clients.css'];

$pal           = $p['palette_arr'];
$photos        = $p['photos'];
$saved         = isShortlisted($id);
$cutterDisplay = $p['cutter_size_display'] ?? '';
$sizesDisplay  = $p['sizes_display'] ?? '';
$hasClients    = clientCount($_SESSION['user_id']) > 0;

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

  <!-- ── HERO ────────────────────────────────────────────────────────────── -->
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

  <!-- ── BODY ─────────────────────────────────────────────────────────────── -->
  <div class="detail-body">

    <!-- Tags -->
    <div class="detail-tags">
      <span class="badge badge-amber"><?= h($p['category']) ?></span>
      <?php if ($p['subcategory']): ?><span class="badge badge-gray"><?= h($p['subcategory']) ?></span><?php endif; ?>
      <?php if ($p['color_subcategory']): ?><span class="badge badge-white"><?= h($p['color_subcategory']) ?></span><?php endif; ?>
    </div>

    <div class="gold-bar"></div>
    <h1 class="detail-title"><?= h($p['name']) ?></h1>
    <p class="detail-quarry">Quarry No. <?= h($p['quarry_number']) ?><?= $p['origin'] ? ' · '.h($p['origin']) : '' ?></p>
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

    <!-- ── CTAs ── -->
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

    <!-- Add to Selection — full width row -->
    <div style="margin-top:10px;">
      <button onclick="openAddToSelection()" class="btn btn-gold btn-block btn-lg">
        <?= icon('plus',16) ?>&nbsp; Add to Client Selection
      </button>
    </div>

    <?php if (!$hasClients): ?>
    <p style="text-align:center;font-size:12px;color:var(--text4);margin-top:8px;">
      No clients yet — <a href="index.php?page=client_form" style="color:var(--black);font-weight:600;">add a client</a> to use selections.
    </p>
    <?php endif; ?>

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

<!-- ════════════════════════════════════════════════════════════════════════
     ADD TO SELECTION MODAL
     ════════════════════════════════════════════════════════════════════════ -->
<div id="addToSelModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9000;align-items:flex-end;justify-content:center;">
  <div style="background:var(--white);border-radius:var(--radius-xl) var(--radius-xl) 0 0;width:100%;max-width:100%;max-height:92vh;overflow-y:auto;">

    <!-- Header -->
    <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--white);z-index:2;">
      <p style="font-family:var(--font-display);font-size:16px;font-weight:700;">Add to Client Selection</p>
      <button onclick="closeAddToSelection()" style="color:var(--text3);cursor:pointer;padding:4px;"><?= icon('close',18) ?></button>
    </div>

    <!-- Product preview -->
    <div style="padding:14px 20px;background:var(--gray-50);border-bottom:1px solid var(--border);">
      <div style="display:flex;gap:12px;align-items:center;">
        <div style="width:52px;height:52px;border-radius:var(--radius);overflow:hidden;flex-shrink:0;background:var(--gray-100);">
          <?php $ph0 = $photos[0] ?? null; ?>
          <?php if ($ph0 && file_exists(PHOTOS_DIR.'/'.$ph0['filename'])): ?>
          <img src="assets/uploads/photos/<?= h($ph0['filename']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;"/>
          <?php else: ?>
          <?= marbleSVG($pal, 52, 52, 'atsprev') ?>
          <?php endif; ?>
        </div>
        <div style="flex:1;min-width:0;">
          <p style="font-weight:700;font-size:14px;line-height:1.3;"><?= h($p['name']) ?></p>
          <p style="font-size:12px;color:var(--text3);margin-top:1px;">Lot <?= h($p['quarry_number']) ?></p>
          <div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:4px;">
            <?php if ($p['thickness']): ?>
            <span class="badge badge-gray" style="font-size:10px;"><?= h($p['thickness']) ?></span>
            <?php endif; ?>
            <?php if ($p['quantity_available']): ?>
            <span class="badge badge-green" style="font-size:10px;"><?= number_format((float)$p['quantity_available']) ?> sqft avail.</span>
            <?php endif; ?>
            <?php if ($sizesDisplay): ?>
            <span class="badge badge-white" style="font-size:10px;">Size: <?= h($sizesDisplay) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Form body -->
    <div style="padding:20px;">
      <?php if (!$hasClients): ?>
      <div style="text-align:center;padding:20px 0;">
        <div class="empty-icon" style="margin:0 auto 14px;"><?= icon('users',26) ?></div>
        <p style="font-weight:700;font-size:15px;margin-bottom:6px;">No clients yet</p>
        <p style="font-size:13px;color:var(--text3);margin-bottom:18px;">Add a client first to save product selections for them.</p>
        <a href="index.php?page=client_form" class="btn btn-primary" style="text-decoration:none;">
          <?= icon('plus',14) ?>&nbsp; Add Client
        </a>
      </div>
      <?php else: ?>
      <form method="POST" action="index.php" id="addToSelForm">
        <input type="hidden" name="action"     value="add_to_selection"/>
        <input type="hidden" name="product_id" value="<?= $id ?>"/>

        <!-- Client search -->
        <div class="input-group">
          <label class="input-label">Client <span style="color:var(--danger);">*</span></label>
          <div style="position:relative;">
            <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text4);pointer-events:none;"><?= icon('search',14) ?></span>
            <input type="text" id="atsClientSearch" class="input-field"
                   placeholder="Type to search client…"
                   autocomplete="off"
                   style="padding-left:36px;"/>
            <input type="hidden" name="client_id" id="atsClientId"/>
            <div id="atsClientDrop"
                 style="display:none;position:absolute;left:0;right:0;top:calc(100% + 2px);background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);max-height:200px;overflow-y:auto;z-index:20;box-shadow:var(--shadow);">
            </div>
          </div>
          <p id="atsClientSelected" style="font-size:12px;color:var(--success);margin-top:5px;display:none;">
            <?= icon('check',12) ?>&nbsp; <span id="atsClientSelectedName"></span>
          </p>
        </div>

        <!-- Area + Qty on same row -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
          <div class="input-group">
            <label class="input-label">Area / Room</label>
            <input type="text" name="selection_area" class="input-field"
                   placeholder="e.g. Living Room"/>
          </div>
          <div class="input-group">
            <label class="input-label">Qty Required (sqft)</label>
            <input type="number" name="quantity_required" class="input-field"
                   min="0" step="0.01" placeholder="0"/>
          </div>
        </div>

        <div class="input-group">
          <label class="input-label">Notes</label>
          <textarea name="extra_notes" class="input-field" rows="2"
                    placeholder="Special requirements, finish preferences…"></textarea>
        </div>

        <div style="display:flex;gap:10px;">
          <button type="submit" class="btn btn-gold btn-block">
            <?= icon('check',15) ?>&nbsp; Save to Selection
          </button>
          <button type="button" onclick="closeAddToSelection()" class="btn btn-secondary">
            Cancel
          </button>
        </div>
      </form>
      <?php endif; ?>
    </div>

  </div>
</div>
<!-- /addToSelModal -->

<script>
// ── Modal open/close ─────────────────────────────────────────────────────────
function openAddToSelection() {
  const modal = document.getElementById('addToSelModal');
  if (modal) { modal.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
  setTimeout(() => document.getElementById('atsClientSearch')?.focus(), 100);
}
function closeAddToSelection() {
  const modal = document.getElementById('addToSelModal');
  if (modal) { modal.style.display = 'none'; document.body.style.overflow = ''; }
}
document.getElementById('addToSelModal')?.addEventListener('click', function(e) {
  if (e.target === this) closeAddToSelection();
});

// ── Client AJAX search ───────────────────────────────────────────────────────
(function () {
  const inp      = document.getElementById('atsClientSearch');
  const hidden   = document.getElementById('atsClientId');
  const drop     = document.getElementById('atsClientDrop');
  const selLabel = document.getElementById('atsClientSelected');
  const selName  = document.getElementById('atsClientSelectedName');
  if (!inp || !drop) return;

  let timer = null;

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function renderDrop(clients) {
    if (!clients.length) {
      drop.innerHTML = '<div style="padding:12px 14px;font-size:13px;color:var(--text3);">No clients found. <a href=\"index.php?page=client_form\" style=\"color:var(--black);font-weight:600;\">Add one</a></div>';
    } else {
      drop.innerHTML = clients.map(c =>
        '<div class="ats-drop-item" data-id="'+c.id+'" data-label="'+esc(c.client_name)+' ('+esc(c.client_mobile)+')" '+
        'style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border);transition:background .1s;">' +
        '<strong style="font-size:13px;">'+esc(c.client_name)+'</strong>' +
        '<br><span style="font-size:11px;color:var(--text3);">'+esc(c.client_mobile)+
        (c.mansoner_name ? ' &nbsp;·&nbsp; Mason: '+esc(c.mansoner_name) : '')+
        '</span></div>'
      ).join('');
    }
    drop.style.display = 'block';
    drop.querySelectorAll('.ats-drop-item').forEach(function(item) {
      item.addEventListener('mouseenter', () => item.style.background = 'var(--gray-50)');
      item.addEventListener('mouseleave', () => item.style.background = '');
      item.addEventListener('mousedown', function(e) { e.preventDefault(); });
      item.addEventListener('click', function() {
        hidden.value   = item.dataset.id;
        inp.value      = item.dataset.label;
        selName.textContent = item.dataset.label;
        selLabel.style.display = 'flex';
        drop.style.display = 'none';
        inp.style.borderColor = '';
      });
    });
  }

  async function doSearch(q) {
    try {
      const r = await fetch('index.php?page=clients&ajax_search=1&q=' + encodeURIComponent(q));
      const d = await r.json();
      renderDrop(d.clients || []);
    } catch(e) { drop.style.display = 'none'; }
  }

  inp.addEventListener('input', function() {
    hidden.value = '';
    selLabel.style.display = 'none';
    drop.style.display = 'none';
    const v = this.value.trim();
    clearTimeout(timer);
    if (v.length < 2) return;          // ← wait for 2 chars minimum
    timer = setTimeout(() => doSearch(v), 300);  // 300 ms debounce
  });

  inp.addEventListener('focus', function() {
    // only trigger search on focus if already has 2+ chars
    const v = this.value.trim();
    if (v.length >= 2) doSearch(v);
  });

  inp.addEventListener('blur', function() {
    setTimeout(() => { drop.style.display = 'none'; }, 220);
  });

  // Preload on modal open
  document.querySelector('[onclick="openAddToSelection()"]')?.addEventListener('click', () => {
    setTimeout(() => doSearch(''), 200);
  });

  // Validate
  document.getElementById('addToSelForm')?.addEventListener('submit', function(e) {
    if (!hidden.value) {
      e.preventDefault();
      inp.focus();
      inp.style.borderColor = 'var(--danger)';
      inp.style.boxShadow = '0 0 0 3px rgba(185,28,28,.1)';
      setTimeout(() => { inp.style.borderColor = ''; inp.style.boxShadow = ''; }, 2500);
    }
  });
})();
</script>

<?php include BASE_PATH . '/layouts/footer.php'; ?>