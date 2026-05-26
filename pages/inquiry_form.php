<?php
$pid = (int)($_GET['product_id'] ?? 0);
$p   = getProduct($pid);
if (!$p) { flash('error','Product not found.'); redirect('index.php?page=catalog'); }

$pageTitle = 'Inquiry — ' . APP_NAME;
$showNav   = false;
$pal       = $p['palette_arr'];
$err       = $inlineError ?? null;
?>
<?php include BASE_PATH . '/layouts/header.php'; ?>

<div class="inq-form-page">
  <!-- Header -->
  <div class="inq-form-top">
    <a href="index.php?page=product&id=<?= $pid ?>" class="hero-icon-btn" style="text-decoration:none;"><?= icon('back',18) ?></a>
    <div>
      <p style="font-weight:700;font-size:16px;">Send Inquiry</p>
      <p style="font-size:12px;color:var(--text3);"><?= h($p['name']) ?></p>
    </div>
  </div>

  <!-- Body -->
  <div class="inq-form-body">
    <!-- Product mini card -->
    <div class="card" style="display:flex;gap:14px;align-items:center;padding:14px;margin-bottom:22px;">
      <div style="width:60px;height:60px;border-radius:10px;overflow:hidden;flex-shrink:0;">
        <?php $ph = $p['photos'][0] ?? null; ?>
        <?php if ($ph && file_exists(PHOTOS_DIR.'/'.$ph['filename'])): ?>
        <img src="assets/uploads/photos/<?= h($ph['filename']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;"/>
        <?php else: ?>
        <?= marbleSVG($pal, 60, 60, 'iqf'.$pid) ?>
        <?php endif; ?>
      </div>
      <div>
        <p style="font-weight:700;font-size:14px;"><?= h($p['name']) ?></p>
        <p style="font-size:12px;color:var(--text3);margin-top:3px;"><?= h($p['quarry_number']) ?> · <?= h($p['category']) ?></p>
        <p style="font-size:12px;color:var(--text2);margin-top:2px;"><?= h($p['thickness']) ?>mm · <?= h($p['sizes']) ?></p>
      </div>
    </div>

    <?php if ($err): ?>
    <div class="alert alert-error" style="margin-bottom:16px;"><?= h($err) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php" id="inquiryForm">
      <input type="hidden" name="action"     value="send_inquiry"/>
      <input type="hidden" name="product_id" value="<?= $pid ?>"/>

      <div class="input-wrap">
        <label class="input-label">Your Message</label>
        <textarea name="message" id="inqMessage" class="input-field" rows="5"
                  placeholder="Hi, I'm interested in this product for a residential project. Could you please share availability and pricing details?"
                  required><?= h($_POST['message'] ?? '') ?></textarea>
      </div>

      <!-- Quick fill chips -->
      <p style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">Quick Add</p>
      <div style="margin-bottom:20px;">
        <?php foreach (['Pricing details','Sample request','Availability check','Custom sizes','Bulk order inquiry','Site visit request'] as $chip): ?>
        <button type="button" class="quick-chip" onclick="addChip(this,'<?= h($chip) ?>')"><?= h($chip) ?></button>
        <?php endforeach; ?>
      </div>

      <div class="input-wrap">
        <label class="input-label">Quantity Required (sq.ft.)</label>
        <input type="number" name="qty_required" class="input-field" placeholder="e.g. 200" min="1"/>
      </div>
    </form>
  </div>

  <!-- Footer CTA -->
  <div class="inq-form-footer">
    <button type="submit" form="inquiryForm" class="btn-primary">
      <?= icon('msg',16) ?>&nbsp; Send Inquiry to Bafna Team
    </button>
  </div>
</div>

<?php include BASE_PATH . '/layouts/footer.php'; ?>
