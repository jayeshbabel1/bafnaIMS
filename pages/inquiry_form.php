<?php
$pid = (int)($_GET['product_id'] ?? 0);
$p   = getProduct($pid);
if (!$p) { flash('error','Product not found.'); redirect('index.php?page=catalog'); }

$pageTitle = 'Inquiry — ' . APP_NAME;
$showNav   = false;
$pal       = $p['palette_arr'];
$err       = $inlineError ?? null;

// Dimension display strings (computed by getProduct() via helpers)
$cutterDisplay = $p['cutter_size_display'] ?? '';
$sizesDisplay  = $p['sizes_display']       ?? '';

// Build a compact spec line: thickness · sizes · cutter
$specParts = [];
if ($p['thickness'])    $specParts[] = h($p['thickness']);
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="inq-form-page" id="inqFormPage">

  <!-- Top bar -->
  <div class="inq-form-top">
    <a href="index.php?page=product&id=<?= $pid ?>" class="hero-icon-btn"
       style="background:var(--surface2);text-decoration:none;flex-shrink:0;"><?= icon('back',18) ?></a>
    <div style="min-width:0;">
      <p style="font-weight:700;font-size:15px;color:var(--text);">Send Inquiry</p>
      <p style="font-size:11.5px;color:var(--text3);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= h($p['name']) ?></p>
    </div>
  </div>

  <!-- Body -->
  <div class="inq-form-body">

    <!-- Product mini card -->
    <div class="card" style="display:flex;gap:14px;align-items:center;padding:14px;margin-bottom:22px;">
      <div style="width:68px;height:68px;border-radius:8px;overflow:hidden;flex-shrink:0;">
        <?php $ph = $p['photos'][0] ?? null; ?>
        <?php if ($ph && file_exists(PHOTOS_DIR.'/'.$ph['filename'])): ?>
        <img src="assets/uploads/photos/<?= h($ph['filename']) ?>" alt=""
             style="width:100%;height:100%;object-fit:cover;"/>
        <?php else: ?>
        <?= marbleSVG($pal, 68, 68, 'iqf'.$pid) ?>
        <?php endif; ?>
      </div>
      <div style="flex:1;min-width:0;">
        <p style="font-family:'Cormorant Garamond',serif;font-size:16px;font-weight:600;color:var(--text);line-height:1.3;"><?= h($p['name']) ?></p>
        <p style="font-size:11.5px;color:var(--text3);margin-top:2px;">Lot <?= h($p['quarry_number']) ?> · <?= h($p['category']) ?></p>
        <?php if ($specParts): ?>
        <p style="font-size:11.5px;color:var(--text2);margin-top:2px;"><?= implode('', $specParts) ?></p>
        <?php endif; ?>
        <?php if ($sizesDisplay): ?>
        <p style="font-size:11px;color:var(--text3);margin-top:2px;">Useable: <?= h($sizesDisplay) ?></p>
        <?php endif; ?>
        <?php if ($cutterDisplay): ?>
        <p style="font-size:11px;color:var(--text3);margin-top:2px;">Cutter: <?= h($cutterDisplay) ?></p>
        <?php endif; ?>
      </div>
      <div style="flex-shrink:0;">
        <?= $p['in_stock']
          ? '<span class="badge badge-green">● In Stock</span>'
          : '<span class="badge badge-gray">Out</span>' ?>
      </div>
    </div>

    <?php if ($err): ?>
    <div class="alert alert-error"><?= h($err) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php" id="inquiryForm">
      <input type="hidden" name="action"     value="send_inquiry"/>
      <input type="hidden" name="product_id" value="<?= $pid ?>"/>

      <div class="inq-form-grid">
        <div class="input-wrap" style="margin-bottom:0;">
          <label class="input-label">Your Message</label>
          <textarea name="message" id="inqMessage" class="input-field" rows="6"
                    placeholder="Hi, I'm interested in this product for a residential project. Could you please share availability and pricing?"
                    required><?= h($_POST['message'] ?? '') ?></textarea>
        </div>
        <div>
          <div class="input-wrap">
            <label class="input-label">Quantity Required (sq.ft.)</label>
            <input type="number" name="qty_required" class="input-field"
                   placeholder="e.g. 200" min="1"/>
          </div>

          <p style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:9px;">Quick Add</p>
          <div style="display:flex;flex-wrap:wrap;gap:0;">
            <?php foreach (['Pricing details','Sample request','Availability','Custom sizes','Bulk order','Site visit'] as $chip): ?>
            <button type="button" class="quick-chip" onclick="addChip(this,'<?= h($chip) ?>')"><?= h($chip) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="inq-form-submit-inline">
        <button type="submit" class="btn-primary">
          <?= icon('msg',16) ?>&nbsp; Send Inquiry to Bafna Team
        </button>
      </div>
    </form>
  </div>

  <!-- Sticky footer CTA (mobile only) -->
  <div class="inq-form-footer inq-form-footer-mobile">
    <button type="submit" form="inquiryForm" class="btn-primary btn-gold">
      <?= icon('msg',16) ?>&nbsp; Send Inquiry to Bafna Team
    </button>
  </div>
</div>

<style>
.inq-form-page{ display:flex; flex-direction:column; height:100vh; height:100dvh; }
.inq-form-grid{ display:block; }
.inq-form-submit-inline{ display:none; }
.inq-form-footer-mobile{ display:block; }

@media(min-width:768px){
  .inq-form-page{ height:auto; max-width:720px; margin:0 auto; width:100%; }
  .inq-form-body{ max-height:none; overflow:visible; }
  .inq-form-footer-mobile{ padding-bottom:20px; }
}
@media(min-width:1024px){
  .inq-form-page{ max-width:860px; }
  .inq-form-grid{ display:grid; grid-template-columns:1fr 1fr; gap:20px; }
  .inq-form-submit-inline{ display:block; margin-top:20px; }
  .inq-form-footer-mobile{ display:none !important; }
  textarea.input-field{ min-height:140px; }
}
</style>

<?php include BASE_PATH . '/layouts/footer.php'; ?>