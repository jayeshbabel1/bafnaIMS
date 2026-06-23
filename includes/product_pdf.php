<?php
/**
 * includes/product_pdf.php
 * ─────────────────────────────────────────────────────────────────────────
 * Generates a branded product PDF for WhatsApp sharing.
 *
 * Dependencies: tecnickcom/tcpdf  (composer require tecnickcom/tcpdf)
 *
 * Public API:
 *   generateProductPdf(int $productId) : array
 *     Returns: ['success'=>bool, 'path'=>string, 'url'=>string, 'filename'=>string]
 *              or ['success'=>false, 'error'=>string]
 *
 *   cleanOldProductPdfs(int $maxAgeSeconds = 3600) : int
 *     Deletes temp PDFs older than $maxAgeSeconds. Returns count deleted.
 * ─────────────────────────────────────────────────────────────────────────
 */

define('PDF_TEMP_DIR', BASE_PATH . '/storage/pdfs');
define('PDF_TEMP_URL', BASE_URL . '/storage/pdfs');
define('PDF_MAX_AGE',  3600); // 1 hour — configurable

// ── Ensure temp dir exists ────────────────────────────────────────────────────
if (!is_dir(PDF_TEMP_DIR)) {
    @mkdir(PDF_TEMP_DIR, 0755, true);
}

// ── Place a .htaccess so PDFs are directly downloadable ───────────────────────
$_htFile = PDF_TEMP_DIR . '/.htaccess';
if (!file_exists($_htFile)) {
    file_put_contents($_htFile, "Options -Indexes\nAllow from all\n");
}

// ─────────────────────────────────────────────────────────────────────────────
function generateProductPdf(int $productId): array
{
    // ── 1. Load product ───────────────────────────────────────────────────────
    $db = getDB();
    $st = $db->prepare("SELECT * FROM products WHERE id = ?");
    $st->execute([$productId]);
    $p = $st->fetch();
    if (!$p) {
        return ['success' => false, 'error' => 'Product not found.'];
    }

    // Primary photo
    $photoPst = $db->prepare(
        "SELECT filename FROM product_photos WHERE product_id = ? ORDER BY sort_order LIMIT 1"
    );
    $photoPst->execute([$productId]);
    $photoRow  = $photoPst->fetch();
    $photoPath = null;
    if ($photoRow) {
        $candidate = PHOTOS_DIR . '/' . $photoRow['filename'];
        if (file_exists($candidate)) {
            $photoPath = $candidate;
        }
    }

    // ── 2. Build safe filename ────────────────────────────────────────────────
    $safeName = preg_replace('/[^A-Za-z0-9_\- ]/u', '', $p['name'] ?? 'product');
    $safeName = trim(preg_replace('/\s+/', '_', $safeName));
    if ($safeName === '') $safeName = 'product_' . $productId;
    $filename  = $safeName . '_' . date('Ymd_His') . '.pdf';
    $pdfPath   = PDF_TEMP_DIR . '/' . $filename;
    $pdfUrl    = PDF_TEMP_URL . '/' . $filename;

    // ── 3. Clean old PDFs (opportunistic) ────────────────────────────────────
    cleanOldProductPdfs();

    // ── 4. Build dimension display strings ───────────────────────────────────
    $sizesDisplay  = formatDimension($p['sizes_l']       ?? '', $p['sizes_h']       ?? '');
    $italianDisplay = formatDimension($p['cutter_size_l'] ?? '', $p['cutter_size_h'] ?? '');

    // ── 5. Logo path ──────────────────────────────────────────────────────────
    $logoPath = null;
    if (function_exists('getLogo')) {
        $logoFile = (string)(getSetting('site_logo', ''));
        if ($logoFile !== '') {
            $candidate = BASE_PATH . '/uploads/logo/' . $logoFile;
            if (file_exists($candidate)) {
                $logoPath = $candidate;
            }
        }
    }
    // Fallback: try the known Bafna logo URL (TCPDF can fetch remote images)
    $logoSrc = $logoPath
        ?: 'https://i0.wp.com/www.bafnamarble.com/wp-content/uploads/2023/11/cropped-logo-01.png?fit=317%2C250&ssl=1';

    // ── 6. Generate PDF via TCPDF ─────────────────────────────────────────────
    try {
        _buildPdfWithTcpdf(
            $p, $photoPath, $sizesDisplay, $italianDisplay, $logoSrc, $logoPath, $pdfPath
        );
        return [
            'success'  => true,
            'path'     => $pdfPath,
            'url'      => $pdfUrl,
            'filename' => $filename,
        ];
    } catch (\Throwable $e) {
        error_log('generateProductPdf TCPDF error: ' . $e->getMessage());
        // Fallback: plain HTML-based PDF via output buffering
        try {
            _buildPdfFallback($p, $photoPath, $sizesDisplay, $italianDisplay, $logoSrc, $pdfPath);
            return [
                'success'  => true,
                'path'     => $pdfPath,
                'url'      => $pdfUrl,
                'filename' => $filename,
            ];
        } catch (\Throwable $e2) {
            return ['success' => false, 'error' => $e2->getMessage()];
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Internal: build with TCPDF
// ─────────────────────────────────────────────────────────────────────────────
function _buildPdfWithTcpdf(
    array  $p,
    ?string $photoPath,
    string  $sizesDisplay,
    string  $italianDisplay,
    string  $logoSrc,
    ?string $logoLocalPath,
    string  $pdfPath
): void {
    // Autoload TCPDF from composer vendor
    $autoload = BASE_PATH . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new \RuntimeException('Composer autoload not found — run: composer require tecnickcom/tcpdf');
    }
    // autoload already required by admin/index.php; safe to call again
    require_once $autoload;

    if (!class_exists('\TCPDF')) {
        throw new \RuntimeException('TCPDF class not found — run: composer require tecnickcom/tcpdf');
    }

    // ── Page setup ────────────────────────────────────────────────────────────
    $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(APP_NAME);
    $pdf->SetAuthor(APP_NAME);
    $pdf->SetTitle(($p['name'] ?? 'Product') . ' — ' . APP_NAME);
    $pdf->SetSubject('Product Details');

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    // ── Palette / accent colour ───────────────────────────────────────────────
    // Use a warm dark tone consistent with the app's --text colour
    $accentR = 30; $accentG = 30; $accentB = 30;

    $pageW = $pdf->getPageWidth();   // 210 mm
    $marginL = 15; $marginR = 15;
    $contentW = $pageW - $marginL - $marginR; // 180 mm

    $y = 15; // current Y cursor

    // ─── HEADER ROW: Logo left | Date+Time right ──────────────────────────────
    // Logo
    $logoH = 14; // height in mm
    $logoW = 40; // max width
    if ($logoLocalPath && file_exists($logoLocalPath)) {
        $pdf->Image($logoLocalPath, $marginL, $y, $logoW, $logoH, '', '', '', true, 300);
    } else {
        // Remote logo — TCPDF supports URLs when allow_url_fopen is on
        try {
            $pdf->Image($logoSrc, $marginL, $y, $logoW, $logoH, 'PNG', '', '', true, 72);
        } catch (\Throwable $_) {
            // Logo unavailable — write text fallback
            $pdf->SetXY($marginL, $y + 3);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetTextColor($accentR, $accentG, $accentB);
            $pdf->Cell($logoW, 8, APP_NAME, 0, 0, 'L');
        }
    }

    // Date & Time (top right)
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $dateStr = date('d-m-Y  h:i A');
    $pdf->SetXY($marginL + $logoW + 4, $y + 4);
    $pdf->Cell($contentW - $logoW - 4, 6, $dateStr, 0, 0, 'R');

    $y += $logoH + 5;

    // ─── Horizontal rule ──────────────────────────────────────────────────────
    $pdf->SetDrawColor($accentR, $accentG, $accentB);
    $pdf->SetLineWidth(0.5);
    $pdf->Line($marginL, $y, $marginL + $contentW, $y);
    $y += 5;

    // ─── Product Name (large header) ─────────────────────────────────────────
    $pdf->SetXY($marginL, $y);
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->SetTextColor($accentR, $accentG, $accentB);
    $pdf->MultiCell($contentW, 9, $p['name'] ?? '', 0, 'C', false, 1);
    $y = $pdf->GetY() + 1;

    // ─── Quarry Number (sub-header) ───────────────────────────────────────────
    $pdf->SetXY($marginL, $y);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(100, 100, 100);
    $qLabel = 'Quarry No: ' . ($p['quarry_number'] ?? '—');
    $pdf->Cell($contentW, 6, $qLabel, 0, 1, 'C');
    $y = $pdf->GetY() + 3;

    // ─── Product Photo (large, centred) ──────────────────────────────────────
    if ($photoPath && file_exists($photoPath)) {
        $imgMaxW = min(120, $contentW);  // up to 120 mm wide
        $imgMaxH = 80;                   // max height mm
        // Get real dimensions to maintain aspect ratio
        $imgInfo = @getimagesize($photoPath);
        if ($imgInfo && $imgInfo[0] > 0 && $imgInfo[1] > 0) {
            $ratio = $imgInfo[0] / $imgInfo[1];
            $imgW  = $imgMaxW;
            $imgH  = $imgW / $ratio;
            if ($imgH > $imgMaxH) {
                $imgH = $imgMaxH;
                $imgW = $imgH * $ratio;
            }
        } else {
            $imgW = $imgMaxW;
            $imgH = $imgMaxH;
        }
        $imgX = $marginL + ($contentW - $imgW) / 2;
        $pdf->Image($photoPath, $imgX, $y, $imgW, $imgH, '', '', '', true, 150);
        $y += $imgH + 6;
    } else {
        // No image placeholder
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetXY($marginL + ($contentW - 80) / 2, $y);
        $pdf->RoundedRect($marginL + ($contentW - 80) / 2, $y, 80, 40, 3, '1111', 'FD');
        $pdf->SetXY($marginL, $y + 15);
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell($contentW, 8, 'Image not available', 0, 1, 'C');
        $y += 46;
    }

    // ─── Details Table ────────────────────────────────────────────────────────
    $pdf->SetXY($marginL, $y);

    // Table header row
    $colLabel = 70;   // mm — "Field" column
    $colValue = $contentW - $colLabel;  // mm — "Value" column

    $pdf->SetFillColor($accentR, $accentG, $accentB);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetLineWidth(0.2);
    $pdf->Cell($colLabel, 7, 'Field',  'B', 0, 'L', true);
    $pdf->Cell($colValue, 7, 'Value',  'B', 1, 'L', true);

    // Data rows
    $rows = [
        ['Product Name',    $p['name']                  ?? ''],
        ['Quarry No',       $p['quarry_number']          ?? ''],
        ['Stone Type',      $p['category']              ?? ''],
        ['Color',           $p['color_subcategory']      ?? ''],
        ['Thickness',       $p['thickness']             ?? ''],
        ['Usable Size',     $sizesDisplay],
        ['Italian Size',    $italianDisplay],
        ['No. of Pieces',   $p['pieces']   > 0 ? (string)(int)$p['pieces']  : ''],
        ['No. of Slabs',    $p['pieces']   > 0 ? (string)(int)$p['pieces']  : ''],
        ['Available Qty',   $p['quantity_available'] > 0
                                ? number_format((float)$p['quantity_available'], 0) . ' sq.ft.' : ''],
        ['On Hold',         $p['quantity_on_hold']   > 0
                                ? number_format((float)$p['quantity_on_hold'],   0) . ' sq.ft.' : ''],
        ['Origin',          $p['origin']  ?? ''],
        ['Finish',          $p['finish']  ?? ''],
    ];

    $fillToggle = false;
    $pdf->SetFont('helvetica', '', 9);
    foreach ($rows as $row) {
        if ($row[1] === '') continue; // skip empty rows
        $pdf->SetTextColor($accentR, $accentG, $accentB);
        if ($fillToggle) {
            $pdf->SetFillColor(247, 247, 247);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        $fillToggle = !$fillToggle;

        // Label cell — bold
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($colLabel, 6.5, $row[0], 'B', 0, 'L', true);

        // Value cell — normal
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell($colValue, 6.5, $row[1], 'B', 1, 'L', true);
    }

    $y = $pdf->GetY() + 8;

    // ─── Footer ───────────────────────────────────────────────────────────────
    $pdf->SetDrawColor($accentR, $accentG, $accentB);
    $pdf->SetLineWidth(0.4);
    $pdf->Line($marginL, $y, $marginL + $contentW, $y);
    $y += 4;

    $pdf->SetXY($marginL, $y);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell($contentW, 5, APP_NAME . '  |  Generated on ' . date('d M Y, h:i A'), 0, 0, 'C');

    // ── Save ──────────────────────────────────────────────────────────────────
    $pdf->Output($pdfPath, 'F');
}

// ─────────────────────────────────────────────────────────────────────────────
// Fallback: HTML string → PDF via mPDF (if TCPDF unavailable)
// ─────────────────────────────────────────────────────────────────────────────
function _buildPdfFallback(
    array  $p,
    ?string $photoPath,
    string  $sizesDisplay,
    string  $italianDisplay,
    string  $logoSrc,
    string  $pdfPath
): void {
    $autoload = BASE_PATH . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new \RuntimeException('No PDF library available. Run: composer require tecnickcom/tcpdf');
    }
    require_once $autoload;

    if (!class_exists('\Mpdf\Mpdf')) {
        throw new \RuntimeException('Neither TCPDF nor mPDF found. Run: composer require tecnickcom/tcpdf');
    }

    $rows = [
        ['Product Name',  $p['name']                  ?? ''],
        ['Quarry No',     $p['quarry_number']          ?? ''],
        ['Stone Type',    $p['category']              ?? ''],
        ['Color',         $p['color_subcategory']      ?? ''],
        ['Thickness',     $p['thickness']             ?? ''],
        ['Usable Size',   $sizesDisplay],
        ['Italian Size',  $italianDisplay],
        ['No. of Pieces', $p['pieces']   > 0 ? (int)$p['pieces'] . '' : ''],
        ['No. of Slabs',  $p['pieces']   > 0 ? (int)$p['pieces'] . '' : ''],
        ['Available Qty', $p['quantity_available'] > 0
                            ? number_format((float)$p['quantity_available'], 0) . ' sq.ft.' : ''],
        ['On Hold',       $p['quantity_on_hold'] > 0
                            ? number_format((float)$p['quantity_on_hold'], 0) . ' sq.ft.' : ''],
        ['Origin',        $p['origin'] ?? ''],
        ['Finish',        $p['finish'] ?? ''],
    ];

    $tableRows = '';
    $alt = false;
    foreach ($rows as $row) {
        if ($row[1] === '') continue;
        $bg = $alt ? '#f7f7f7' : '#ffffff';
        $alt = !$alt;
        $tableRows .= '<tr style="background:' . $bg . ';">'
            . '<td style="padding:5px 8px;font-weight:600;border-bottom:1px solid #e0e0e0;width:45%;">' . htmlspecialchars($row[0]) . '</td>'
            . '<td style="padding:5px 8px;border-bottom:1px solid #e0e0e0;">' . htmlspecialchars($row[1]) . '</td>'
            . '</tr>';
    }

    $photoHtml = '';
    if ($photoPath && file_exists($photoPath)) {
        $base64 = base64_encode(file_get_contents($photoPath));
        $mime   = mime_content_type($photoPath) ?: 'image/jpeg';
        $photoHtml = '<div style="text-align:center;margin:12px 0;">
            <img src="data:' . $mime . ';base64,' . $base64 . '"
                 style="max-width:420px;max-height:280px;border-radius:6px;border:1px solid #e0e0e0;"/>
        </div>';
    }

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"/>
    <style>
      body { font-family: Arial, sans-serif; font-size: 11pt; color: #1a1a1a; margin: 0; padding: 20px; }
      .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #1a1a1a; padding-bottom: 10px; margin-bottom: 14px; }
      .logo img { max-height: 42px; }
      .date { font-size: 9pt; color: #666; text-align: right; }
      h1 { font-size: 20pt; text-align: center; margin: 8px 0 2px; }
      .quarry { font-size: 11pt; text-align: center; color: #555; margin-bottom: 12px; }
      table { width: 100%; border-collapse: collapse; margin-top: 14px; }
      thead tr { background: #1a1a1a; color: #fff; }
      thead th { padding: 7px 10px; text-align: left; font-size: 10pt; }
      .footer { margin-top: 20px; border-top: 1px solid #ccc; padding-top: 8px; font-size: 8pt; color: #888; text-align: center; }
    </style></head><body>
    <div class="header">
      <div class="logo"><img src="' . htmlspecialchars($logoSrc) . '" alt="' . htmlspecialchars(APP_NAME) . '"/></div>
      <div class="date">' . date('d-m-Y  h:i A') . '</div>
    </div>
    <h1>' . htmlspecialchars($p['name'] ?? '') . '</h1>
    <div class="quarry">Quarry No: ' . htmlspecialchars($p['quarry_number'] ?? '—') . '</div>
    ' . $photoHtml . '
    <table>
      <thead><tr><th>Field</th><th>Value</th></tr></thead>
      <tbody>' . $tableRows . '</tbody>
    </table>
    <div class="footer">' . htmlspecialchars(APP_NAME) . ' &nbsp;|&nbsp; Generated ' . date('d M Y, h:i A') . '</div>
    </body></html>';

    $mpdf = new \Mpdf\Mpdf(['margin_top' => 10, 'margin_bottom' => 10, 'margin_left' => 15, 'margin_right' => 15]);
    $mpdf->WriteHTML($html);
    $mpdf->Output($pdfPath, 'F');
}

// ─────────────────────────────────────────────────────────────────────────────
function cleanOldProductPdfs(int $maxAgeSeconds = PDF_MAX_AGE): int
{
    $deleted = 0;
    if (!is_dir(PDF_TEMP_DIR)) return 0;
    foreach (glob(PDF_TEMP_DIR . '/*.pdf') ?: [] as $file) {
        if (is_file($file) && (time() - filemtime($file)) > $maxAgeSeconds) {
            @unlink($file);
            $deleted++;
        }
    }
    return $deleted;
}

// ─────────────────────────────────────────────────────────────────────────────
// AJAX endpoint: ?wa_pdf=1&product_id=N
// Returns JSON: {success, pdf_url, filename} or {success:false, error}
// ─────────────────────────────────────────────────────────────────────────────
function handleWaPdfAjax(): void
{
    $pid = (int)($_GET['product_id'] ?? 0);
    if (!$pid) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Missing product_id']);
        exit;
    }
    $result = generateProductPdf($pid);
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}