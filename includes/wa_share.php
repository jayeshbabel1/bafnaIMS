<?php
/**
 * includes/wa_share.php
 * WhatsApp share helpers — AJAX endpoint + message builder
 *
 * Endpoint: index.php?wa_preview=1&product_id=N
 * Returns JSON: { message: string, image_url: string|null }
 */

/**
 * Build the WhatsApp message text for a product.
 */
function buildWaMessage(array $p): string {
    $slab    = formatDimension($p['sizes_l']       ?? '', $p['sizes_h']       ?? '');
    $italian = formatDimension($p['cutter_size_l'] ?? '', $p['cutter_size_h'] ?? '');

    $lines = [];
    $lines[] = '*' . ($p['name'] ?? '') . '*';
    $lines[] = '';
    if (!empty($p['quarry_number']))      $lines[] = '*Quarry No:* '     . $p['quarry_number'];
    if (!empty($p['category']))           $lines[] = '*Stone Type:* '    . $p['category'];
    if (!empty($p['thickness']))          $lines[] = '*Thickness:* '     . $p['thickness'];
    if ($slab !== '')                     $lines[] = '*Usable Size:* '   . $slab;
    if ($italian !== '')                  $lines[] = '*Italian Size:* '  . $italian;
    if (isset($p['pieces']) && $p['pieces'] > 0)
                                          $lines[] = '*No. of Pieces:* ' . number_format((int)$p['pieces']);
    if (isset($p['quantity_available']) && $p['quantity_available'] > 0)
                                          $lines[] = '*Available Qty:* ' . number_format((float)$p['quantity_available']) . ' sq.ft.';
    if (isset($p['quantity_on_hold']) && $p['quantity_on_hold'] > 0)
                                          $lines[] = '*On Hold:* '       . number_format((float)$p['quantity_on_hold']) . ' sq.ft.';
    if (!empty($p['finish']))             $lines[] = '*Finish:* '        . $p['finish'];
    if (!empty($p['origin']))             $lines[] = '*Origin:* '        . $p['origin'];

    $lines[] = '';
    $lines[] = '— ' . APP_NAME;

    return implode("\n", $lines);
}

/**
 * Return the public URL of the product's primary photo, or null.
 */
function getProductImageUrl(int $productId): ?string {
    $db = getDB();
    $st = $db->prepare(
        "SELECT filename FROM product_photos WHERE product_id=? ORDER BY sort_order LIMIT 1"
    );
    $st->execute([$productId]);
    $row = $st->fetch();
    if (!$row) return null;

    $filename = $row['filename'];
    // Physical check
    if (!file_exists(PHOTOS_DIR . '/' . $filename)) return null;

    // Build public URL
    return BASE_URL . '/assets/uploads/photos/' . rawurlencode(ltrim($filename, '/'));
}

/**
 * Handle AJAX request: ?wa_preview=1&product_id=N
 * Outputs JSON and exits.
 */
function handleWaPreviewAjax(): void {
    $pid = (int)($_GET['product_id'] ?? 0);
    if (!$pid) {
        echo json_encode(['error' => 'Missing product_id']);
        exit;
    }

    $db = getDB();
    $st = $db->prepare("SELECT * FROM products WHERE id=?");
    $st->execute([$pid]);
    $p = $st->fetch();

    if (!$p) {
        echo json_encode(['error' => 'Product not found']);
        exit;
    }

    $message   = buildWaMessage($p);
    $imageUrl  = getProductImageUrl($pid);

    header('Content-Type: application/json');
    echo json_encode([
        'message'   => $message,
        'image_url' => $imageUrl,
    ]);
    exit;
}