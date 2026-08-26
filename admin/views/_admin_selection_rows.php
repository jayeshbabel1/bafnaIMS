<?php
/**
 * admin/views/_admin_selection_rows.php — Partial: selection table for admin
 * Expects: $selections, $total, $totalPages, $currentPage, $clientId
 */
?>
<?php if (empty($selections) && $currentPage === 1): ?>
<div class="admin-table-empty" style="padding:40px 20px;text-align:center;">
  <p style="font-weight:600;color:var(--admin-text,var(--text));margin-bottom:6px;">No products selected yet</p>
  <p style="font-size:12px;color:var(--admin-text3,var(--text3));">Click "Add Product" above to add the first product for this client.</p>
</div>

<?php else: ?>

<table class="admin-table">
  <thead>
    <tr>
      <th style="width:52px;">Photo</th>
      <th>Product</th>
      <th>Thickness</th>
      <th>Useable Size</th>
      <th>Italian Size</th>
      <th>Avail. Qty</th>
      <th>Req. Qty</th>
      <th>Area</th>
      <th>Notes</th>
      <th style="width:90px;">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($selections as $sel):
      $pal  = json_decode($sel['palette'] ?? '[]', true) ?: ['F2F0EC', 'D8CFC4', 'BFB0A0'];
      $slab = formatDimension($sel['sizes_l'] ?? '', $sel['sizes_h'] ?? '');
      $cut  = formatDimension($sel['cutter_size_l'] ?? '', $sel['cutter_size_h'] ?? '');
      $exceeds = selectionExceedsAvailable($sel);
    ?>
    <tr>
      <td>
        <div class="tbl-thumb">
          <?php if ($sel['primary_photo'] && file_exists(PHOTOS_DIR.'/'.$sel['primary_photo'])): ?>
          <img src="../assets/uploads/photos/<?= h($sel['primary_photo']) ?>" alt=""/>
          <?php else: ?>
          <?= marbleSVG($pal, 40, 40, 'ast' . $sel['id']) ?>
          <?php endif; ?>
        </div>
      </td>
      <td>
        <div style="display:flex;align-items:center;gap:6px;">
          <a href="../index.php?page=product&id=<?= $sel['product_id'] ?>" target="_blank"
             style="font-weight:600;font-size:13px;color:var(--admin-accent,var(--accent));">
            <?= h($sel['product_name']) ?>
          </a>
          <?php if ($exceeds): ?>
          <span class="sel-exceed-icon" title="Required quantity exceeds available stock.">!</span>
          <?php endif; ?>
        </div>
        <p style="font-size:11px;color:var(--admin-text3,var(--text3));"><?= h($sel['quarry_number']) ?></p>
        <span class="badge badge-blue" style="font-size:9px;margin-top:2px;"><?= h($sel['category']) ?></span>
      </td>
      <td style="font-size:12px;"><?= h($sel['thickness'] ?: '—') ?></td>
      <td style="font-size:12px;"><?= $slab ? h($slab) : '—' ?></td>
      <td style="font-size:12px;"><?= $cut ? h($cut) : '—' ?></td>
      <td style="font-size:13px;font-weight:600;color:var(--success);"><?= number_format((float)$sel['quantity_available']) ?> sqft</td>
      <td style="font-size:13px;font-weight:600;"><?= $sel['quantity_required'] > 0 ? number_format((float)$sel['quantity_required'], 0) . ' sqft' : '—' ?></td>
      <td style="font-size:12px;"><?= h($sel['selection_area'] ?: '—') ?></td>
      <td style="font-size:11px;color:var(--admin-text3,var(--text3));max-width:140px;">
        <?= $sel['extra_notes'] ? h(mb_strimwidth($sel['extra_notes'], 0, 50, '…')) : '—' ?>
      </td>
      <td>
        <div style="display:flex;gap:5px;">
          <button type="button" class="btn-admin-secondary btn-admin-sm acs-edit-btn"
                  data-id="<?= $sel['id'] ?>"
                  data-area="<?= h($sel['selection_area'] ?? '') ?>"
                  data-qty="<?= h($sel['quantity_required'] ?? '') ?>"
                  data-notes="<?= h($sel['extra_notes'] ?? '') ?>"
                  title="Edit">
            <?= icon('edit', 13) ?>
          </button>
          <button type="button" class="btn-admin-danger btn-admin-sm acs-delete-btn"
                  data-id="<?= $sel['id'] ?>"
                  data-name="<?= h(addslashes($sel['product_name'])) ?>"
                  title="Remove">
            <?= icon('trash', 13) ?>
          </button>
          <?php
          // Build WhatsApp share message
          $slab_wa = formatDimension($sel['sizes_l'] ?? '', $sel['sizes_h'] ?? '');
          $cut_wa  = formatDimension($sel['cutter_size_l'] ?? '', $sel['cutter_size_h'] ?? '');
          $wa_msg  = "*Product Selection — " . APP_NAME . "*\n\n";
          $wa_msg .= "*Client:* " . $client['client_name'] . "\n";
		  $wa_msg .= "*Client Belongs To :* " . h($client['owner_name']) . "\n";
          $wa_msg .= "*Mobile:* " . $client['client_mobile'] . "\n";
          if ($client['mansoner_name']) {
              $wa_msg .= "*Mason:* " . $client['mansoner_name'] . "\n";
              if ($client['mansoner_mobile']) {
                  $wa_msg .= "*Mason Mobile:* " . $client['mansoner_mobile'] . "\n";
              }
          }
          if ($client['site_address']) {
              $wa_msg .= "*Site:* " . $client['site_address'] . "\n";
          }
          $wa_msg .= "\n*Product Details*\n";
          $wa_msg .= "Name: " . $sel['product_name'] . "\n";
          $wa_msg .= "Quarry: " . $sel['quarry_number'] . "\n";
          $wa_msg .= "Category: " . $sel['category'] . "\n";
          if ($sel['thickness'])   $wa_msg .= "Thickness: " . $sel['thickness'] . "\n";
          if ($slab_wa)            $wa_msg .= "Slab Size: " . $slab_wa . "\n";
          if ($cut_wa)             $wa_msg .= "Italian Size: " . $cut_wa . "\n";
          $wa_msg .= "Available: " . number_format((float)$sel['quantity_available']) . " sqft\n";
          if ($sel['quantity_required'] > 0) $wa_msg .= "Required: " . number_format((float)$sel['quantity_required']) . " sqft\n";
          if ($sel['selection_area'])  $wa_msg .= "Area: " . $sel['selection_area'] . "\n";
          if ($sel['extra_notes'])     $wa_msg .= "Notes: " . $sel['extra_notes'] . "\n";
          $wa_url = 'https://wa.me/?text=' . rawurlencode($wa_msg);
          ?>
           <a href="<?= h($wa_url) ?>" target="_blank" rel="noopener"
             class="btn-admin-secondary btn-admin-sm btn-admin-labeled"
             style="color:#25D366;border-color:#25D366;text-decoration:none;">
            <?= icon('whatsapp', 13) ?> Share
          </a>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>