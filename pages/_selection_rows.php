<?php
/**
 * pages/_selection_rows.php — Partial: selection table + pagination
 */
?>
<?php if (empty($selections) && $currentPage === 1): ?>
<div class="empty-state" style="padding-top:40px;">
  <div class="empty-icon"><?= icon('grid', 28) ?></div>
  <p class="empty-title">No products selected</p>
  <p class="empty-sub">
    <?= $search ? 'No products match your search.' : 'Browse the catalog and click "Add to Selection" on any product.' ?>
  </p>
  <?php if (!$search): ?>
  <a href="index.php?page=catalog" class="btn btn-primary" style="margin-top:20px;text-decoration:none;">
    <?= icon('grid', 14) ?>&nbsp; Browse Catalog
  </a>
  <?php endif; ?>
</div>

<?php else: ?>

<!-- Desktop table / Mobile cards -->
<div class="sel-table-wrap">
  <table class="sel-table">
    <thead>
      <tr>
        <th style="width:52px;"></th>
        <th>Product</th>
        <th>Thickness</th>
        <th>Useable Size</th>
        <th>Italian Size</th>
        <th>Avail. Qty</th>
        <th>Req. Qty</th>
        <th>Area / Room</th>
        <th>Notes</th>
        <th style="width:80px;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($selections as $sel):
        $pal  = json_decode($sel['palette'] ?? '[]', true) ?: ['F2F0EC', 'D8CFC4', 'BFB0A0'];
        $slab = formatDimension($sel['sizes_l'] ?? '', $sel['sizes_h'] ?? '');
        $cut  = formatDimension($sel['cutter_size_l'] ?? '', $sel['cutter_size_h'] ?? '');
      ?>
      <?php $exceeds = selectionExceedsAvailable($sel); ?>
      <tr>
        <!-- Thumb -->
        <td>
          <div class="sel-thumb">
            <?php if ($sel['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$sel['primary_photo'])): ?>
            <img src="assets/uploads/photos/<?= h($sel['primary_photo']) ?>" alt=""/>
            <?php else: ?>
            <?= marbleSVG($pal, 44, 44, 'st' . $sel['id']) ?>
            <?php endif; ?>
          </div>
        </td>
        <!-- Product -->
        <td>
          <div style="display:flex;align-items:center;gap:6px;">
            <a href="index.php?page=product&id=<?= $sel['product_id'] ?>" style="font-weight:600;font-size:13px;color:var(--text);">
              <?= h($sel['product_name']) ?>
            </a>
            <?php if ($exceeds): ?>
            <span class="sel-exceed-icon" title="You have selected lower quantity product than its available quantity.">!</span>
            <?php endif; ?>
          </div>
          <p style="font-size:11px;color:var(--text4);margin-top:2px;"><?= h($sel['quarry_number']) ?></p>
          <span class="badge badge-amber" style="font-size:9px;margin-top:3px;"><?= h($sel['category']) ?></span>
        </td>
        <td style="font-size:12px;color:var(--text2);"><?= h($sel['thickness'] ?? '—') ?></td>
        <td style="font-size:12px;color:var(--text2);"><?= $slab ? h($slab) : '—' ?></td>
        <td style="font-size:12px;color:var(--text2);"><?= $cut ? h($cut) : '—' ?></td>
        <td style="font-size:13px;font-weight:600;color:var(--success);">
          <?= number_format((float)$sel['quantity_available']) ?> <span style="font-size:10px;color:var(--text4);">sqft</span>
        </td>
        <td style="font-size:13px;font-weight:600;">
          <?= $sel['quantity_required'] > 0 ? number_format((float)$sel['quantity_required'], 0) . ' <span style="font-size:10px;color:var(--text4);">sqft</span>' : '—' ?>
        </td>
        <td style="font-size:12px;color:var(--text2);">
          <?= $sel['selection_area'] ? h($sel['selection_area']) : '<span style="color:var(--text4);">—</span>' ?>
        </td>
        <td style="font-size:12px;color:var(--text3);max-width:140px;">
          <?= $sel['extra_notes'] ? h(mb_strimwidth($sel['extra_notes'], 0, 50, '…')) : '<span style="color:var(--text4);">—</span>' ?>
        </td>
        <td>
          <div style="display:flex;gap:4px;">
            <button class="btn btn-secondary btn-sm btn-icon sel-edit-btn"
                    data-id="<?= $sel['id'] ?>"
                    data-area="<?= h($sel['selection_area'] ?? '') ?>"
                    data-qty="<?= h($sel['quantity_required'] ?? '') ?>"
                    data-notes="<?= h($sel['extra_notes'] ?? '') ?>"
                    title="Edit">
              <?= icon('edit', 13) ?>
            </button>
            <button class="btn btn-danger btn-sm btn-icon sel-delete-btn"
                    data-id="<?= $sel['id'] ?>"
                    data-name="<?= h($sel['product_name']) ?>"
                    title="Remove">
              <?= icon('trash', 13) ?>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Mobile cards (hidden on desktop) -->
<div class="sel-mobile-cards">
  <?php foreach ($selections as $sel):
    $pal  = json_decode($sel['palette'] ?? '[]', true) ?: ['F2F0EC','D8CFC4','BFB0A0'];
    $slab = formatDimension($sel['sizes_l'] ?? '', $sel['sizes_h'] ?? '');
    $cut  = formatDimension($sel['cutter_size_l'] ?? '', $sel['cutter_size_h'] ?? '');
  ?>
   <?php $exceedsM = selectionExceedsAvailable($sel); ?>
  <div class="sel-mobile-card">
    <div style="display:flex;gap:12px;align-items:flex-start;">
      <div class="sel-thumb" style="width:56px;height:56px;flex-shrink:0;">
        <?php if ($sel['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$sel['primary_photo'])): ?>
        <img src="assets/uploads/photos/<?= h($sel['primary_photo']) ?>" alt=""/>
        <?php else: ?>
        <?= marbleSVG($pal, 56, 56, 'sm' . $sel['id']) ?>
        <?php endif; ?>
      </div>
      <div style="flex:1;min-width:0;">
        <div style="display:flex;align-items:center;gap:6px;">
          <a href="index.php?page=product&id=<?= $sel['product_id'] ?>" style="font-weight:600;font-size:14px;color:var(--text);">
            <?= h($sel['product_name']) ?>
          </a>
          <?php if ($exceedsM): ?>
          <span class="sel-exceed-icon" title="You have selected lower quantity product than its available quantity.">!</span>
          <?php endif; ?>
        </div>
        <p style="font-size:11px;color:var(--text4);">Lot <?= h($sel['quarry_number']) ?></p>
        <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:5px;">
          <?php if ($sel['thickness']): ?>
          <span class="badge badge-gray" style="font-size:10px;"><?= h($sel['thickness']) ?></span>
          <?php endif; ?>
          <?php if ($slab): ?>
          <span class="badge badge-white" style="font-size:10px;"><?= h($slab) ?></span>
          <?php endif; ?>
          <?php if ($sel['quantity_required'] > 0): ?>
          <span class="badge badge-amber" style="font-size:10px;"><?= number_format((float)$sel['quantity_required']) ?> sqft</span>
          <?php endif; ?>
          <?php if ($sel['selection_area']): ?>
          <span class="badge" style="background:var(--success-bg);color:var(--success);font-size:10px;"><?= h($sel['selection_area']) ?></span>
          <?php endif; ?>
        </div>
        <?php if ($sel['extra_notes']): ?>
        <p style="font-size:11px;color:var(--text3);margin-top:4px;line-height:1.4;"><?= h(mb_strimwidth($sel['extra_notes'], 0, 80, '…')) ?></p>
        <?php endif; ?>
      </div>
    </div>
    <div style="display:flex;gap:8px;margin-top:12px;">
      <button class="btn btn-secondary btn-sm sel-edit-btn" style="flex:1;"
              data-id="<?= $sel['id'] ?>"
              data-area="<?= h($sel['selection_area'] ?? '') ?>"
              data-qty="<?= h($sel['quantity_required'] ?? '') ?>"
              data-notes="<?= h($sel['extra_notes'] ?? '') ?>">
        <?= icon('edit', 13) ?>&nbsp; Edit
      </button>
      <button class="btn btn-danger btn-sm sel-delete-btn"
              data-id="<?= $sel['id'] ?>"
              data-name="<?= h($sel['product_name']) ?>">
        <?= icon('trash', 13) ?>
      </button>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<?php $range = 2; $s = max(1, $currentPage - $range); $e = min($totalPages, $currentPage + $range); ?>
<div class="pagination">
  <button class="pag-btn <?= $currentPage <= 1 ? 'disabled' : '' ?>" data-page="<?= $currentPage - 1 ?>">&lsaquo;</button>
  <?php if ($s > 1): ?><button class="pag-btn" data-page="1">1</button><?php if ($s > 2): ?><span class="pag-ellipsis">…</span><?php endif; endif; ?>
  <?php for ($pi = $s; $pi <= $e; $pi++): ?>
  <button class="pag-btn <?= $pi === $currentPage ? 'active' : '' ?>" data-page="<?= $pi ?>"><?= $pi ?></button>
  <?php endfor; ?>
  <?php if ($e < $totalPages): ?><?php if ($e < $totalPages - 1): ?><span class="pag-ellipsis">…</span><?php endif; ?><button class="pag-btn" data-page="<?= $totalPages ?>"><?= $totalPages ?></button><?php endif; ?>
  <button class="pag-btn <?= $currentPage >= $totalPages ? 'disabled' : '' ?>" data-page="<?= $currentPage + 1 ?>">&rsaquo;</button>
</div>
<?php endif; ?>

<?php endif; ?>