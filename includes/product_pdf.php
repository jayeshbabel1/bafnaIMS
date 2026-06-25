<?php
/**
 * includes/product_pdf.php
 * Product PDF generator using TCPDF (tecnickcom/tcpdf v7).
 * Called via: index.php?pdf_download=1&product_id=N  (direct download)
 *             index.php?wa_pdf=1&product_id=N         (WhatsApp share — returns JSON)
 */

define('PDF_TEMP_DIR', BASE_PATH . '/storage/pdfs');
define('PDF_TEMP_URL', BASE_URL  . '/storage/pdfs');
define('PDF_MAX_AGE',  3600);

if (!is_dir(PDF_TEMP_DIR)) {
    @mkdir(PDF_TEMP_DIR, 0755, true);
}
$_htFile = PDF_TEMP_DIR . '/.htaccess';
if (!file_exists($_htFile)) {
    file_put_contents($_htFile, "Options -Indexes\nAllow from all\n");
}

// ── Public entry point ────────────────────────────────────────────────────────
function generateProductPdf(int $productId): array
{
    // 1. Fetch product
    $db = getDB();
    $st = $db->prepare("SELECT * FROM products WHERE id = ?");
    $st->execute([$productId]);
    $p = $st->fetch();
    if (!$p) {
        return ['success' => false, 'error' => 'Product not found.'];
    }

    // 2. Primary photo — local path only
    $photoPst = $db->prepare(
        "SELECT filename FROM product_photos WHERE product_id = ? ORDER BY sort_order LIMIT 1"
    );
    $photoPst->execute([$productId]);
    $photoRow  = $photoPst->fetch();
    $photoPath = null;
    if ($photoRow) {
        $dbFilename = $photoRow['filename'];
        error_log('PDF photo lookup — DB filename: ' . $dbFilename);
        error_log('PDF photo lookup — PHOTOS_DIR: ' . PHOTOS_DIR);

        // Try 1: resolvePhotoPath (handles casing)
        $resolved = resolvePhotoPath(PHOTOS_DIR, $dbFilename);
        if ($resolved) {
            $fullPath = PHOTOS_DIR . '/' . $resolved;
            if (file_exists($fullPath)) {
                $photoPath = realpath($fullPath);
                error_log('PDF photo — resolved path: ' . $photoPath);
            }
        }

        // Try 2: direct path
        if (!$photoPath) {
            $candidate = PHOTOS_DIR . '/' . $dbFilename;
            if (file_exists($candidate)) {
                $photoPath = realpath($candidate);
                error_log('PDF photo — direct path: ' . $photoPath);
            } else {
                error_log('PDF photo — direct path NOT found: ' . $candidate);
            }
        }

        // Try 3: just the basename (no subfolder)
        if (!$photoPath) {
            $basename  = basename($dbFilename);
            $candidate = PHOTOS_DIR . '/' . $basename;
            if (file_exists($candidate)) {
                $photoPath = realpath($candidate);
                error_log('PDF photo — basename path: ' . $photoPath);
            } else {
                error_log('PDF photo — basename NOT found: ' . $candidate);
            }
        }

        // Try 4: scan all subfolders for the filename
        if (!$photoPath) {
            $basename = basename($dbFilename);
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(PHOTOS_DIR, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && strcasecmp($file->getFilename(), $basename) === 0) {
                    $photoPath = $file->getRealPath();
                    error_log('PDF photo — found by scan: ' . $photoPath);
                    break;
                }
            }
            if (!$photoPath) {
                error_log('PDF photo — NOT FOUND anywhere on disk for: ' . $basename);
            }
        }
    } else {
        error_log('PDF photo — no photo row found in DB for product ' . $productId);
    }

    // 3. Safe filename
    $rawName  = $p['name'] ?? 'product';
    $safeName = preg_replace('/[^A-Za-z0-9 _\-]/u', '', $rawName);
    $safeName = trim(preg_replace('/\s+/', '_', $safeName));
    if ($safeName === '') $safeName = 'product_' . $productId;
    $filename = $safeName . '.pdf';
    $pdfPath  = PDF_TEMP_DIR . '/' . time() . '_' . $filename;
    $pdfUrl   = PDF_TEMP_URL . '/' . time() . '_' . $filename;

    // 4. Cleanup old PDFs
    cleanOldProductPdfs();

    // 5. Dimension strings
    $sizesDisplay   = formatDimension($p['sizes_l']       ?? '', $p['sizes_h']       ?? '');
    $italianDisplay = formatDimension($p['cutter_size_l'] ?? '', $p['cutter_size_h'] ?? '');

    // 6. Logo — local only, no remote fetching
    $logoPath = _resolveLogoPath();
if (!is_dir(PDF_TEMP_DIR)) {
    if (!mkdir(PDF_TEMP_DIR, 0755, true)) {
        throw new \RuntimeException("Cannot create PDF directory");
    }
}

if (!is_writable(PDF_TEMP_DIR)) {
    throw new \RuntimeException("PDF directory not writable: " . PDF_TEMP_DIR);
}
    // 7. Generate
    try {
        _buildPdf($p, $photoPath, $sizesDisplay, $italianDisplay, $logoPath, $pdfPath);
        return [
            'success'  => true,
            'path'     => $pdfPath,
            'url'      => $pdfUrl,
            'filename' => $filename,
        ];
    } catch (\Throwable $e) {
        error_log('generateProductPdf error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ── Resolve local logo path ───────────────────────────────────────────────────
function _resolveLogoPath(): ?string
{
    try {
        $logoFile = getSetting('site_logo', '');
        if ($logoFile !== '') {
            $p = BASE_PATH . '/uploads/logo/' . $logoFile;
            if (file_exists($p)) return realpath($p);
        }
    } catch (\Throwable $e) {}
    return null;
}

// ── Core PDF builder using TCPDF ──────────────────────────────────────────────
function _buildPdf(
    array   $p,
    ?string $photoPath,
    string  $sizesDisplay,
    string  $italianDisplay,
    ?string $logoPath,
    string  $pdfPath
): void {
    $autoload = BASE_PATH . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new \RuntimeException('vendor/autoload.php not found.');
    }
    require_once $autoload;
if (!defined('K_PATH_FONTS')) {
        define('K_PATH_FONTS', BASE_PATH . '/vendor/tecnickcom/tc-lib-pdf-font/target/fonts/');
    }
    if (!class_exists('\TCPDF')) {
        throw new \RuntimeException('TCPDF class not found. Run: composer require tecnickcom/tcpdf');
    }

    // ── Page setup ────────────────────────────────────────────────────────────
    $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator(APP_NAME);
    $pdf->SetAuthor(APP_NAME);
    $pdf->SetTitle(($p['name'] ?? 'Product') . ' — ' . APP_NAME);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    $pageW    = $pdf->getPageWidth();
    $mL       = 15;
    $mR       = 15;
    $contW    = $pageW - $mL - $mR;   // 180 mm

    // ── Colours ───────────────────────────────────────────────────────────────
    $black  = [26,  26,  26];
    $mid    = [100, 100, 100];
    $light  = [200, 200, 200];
    $white  = [255, 255, 255];
    $altRow = [248, 248, 248];

    $y = 15;

    // ── HEADER: Logo (left) | Date-time (right) ───────────────────────────────
    $logoW = 44;
    $logoH = 14;

    if ($logoPath) {
        try {
            $pdf->Image($logoPath, $mL, $y, $logoW, $logoH, '', '', '', true, 150, '', false, false, 0, 'CM');
        } catch (\Throwable $e) {
            // logo failed — use text
            $pdf->SetXY($mL, $y + 3);
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(...$black);
            $pdf->Cell($logoW, 8, APP_NAME, 0, 0, 'L');
        }
    } else {
        $pdf->SetXY($mL, $y + 3);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(...$black);
        $pdf->Cell($logoW, 8, APP_NAME, 0, 0, 'L');
    }

    // Date-time right
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(...$mid);
    $pdf->SetXY($mL, $y + 4);
    $pdf->Cell($contW, 6, date('d-m-Y  h:i A'), 0, 0, 'R');

    $y += $logoH + 4;

    // ── Divider ───────────────────────────────────────────────────────────────
    $pdf->SetDrawColor(...$black);
    $pdf->SetLineWidth(0.5);
    $pdf->Line($mL, $y, $mL + $contW, $y);
    $y += 6;

    // ── Product Name ──────────────────────────────────────────────────────────
    $pdf->SetXY($mL, $y);
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->SetTextColor(...$black);
    $pdf->MultiCell($contW, 9, $p['name'] ?? '', 0, 'C', false, 1);
    $y = $pdf->GetY() + 2;

    // ── Quarry sub-header ─────────────────────────────────────────────────────
    $pdf->SetXY($mL, $y);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(...$mid);
    $pdf->Cell($contW, 6, 'Quarry No: ' . ($p['quarry_number'] ?? '—'), 0, 1, 'C');
    $y = $pdf->GetY() + 4;

    // ── Product Photo ─────────────────────────────────────────────────────────
    $imageRendered = false;
    error_log('PDF _buildPdf — photoPath received: ' . ($photoPath ?? 'NULL'));
    if ($photoPath) {
        error_log('PDF _buildPdf — file_exists: ' . (file_exists($photoPath) ? 'YES' : 'NO'));
        error_log('PDF _buildPdf — is_readable: ' . (is_readable($photoPath) ? 'YES' : 'NO'));
        if (file_exists($photoPath)) {
            error_log('PDF _buildPdf — filesize: ' . filesize($photoPath));
            error_log('PDF _buildPdf — mime: ' . (function_exists('mime_content_type') ? mime_content_type($photoPath) : 'n/a'));
        }
    }
    if ($photoPath && file_exists($photoPath)) {
        $info = @getimagesize($photoPath);
        if ($info && $info[0] > 0 && $info[1] > 0) {
            // Determine image type explicitly for TCPDF
            $imgType = '';
            switch ($info[2]) {
                case IMAGETYPE_JPEG: $imgType = 'JPEG'; break;
                case IMAGETYPE_PNG:  $imgType = 'PNG';  break;
                case IMAGETYPE_WEBP: $imgType = 'WEBP'; break;
                default:
                    $ext = strtoupper(pathinfo($photoPath, PATHINFO_EXTENSION));
                    $imgType = in_array($ext, ['JPG','JPEG','PNG','WEBP']) ? $ext : 'JPEG';
            }
            // Always copy image to sys_get_temp_dir() — TCPDF v7 / tc-lib-pdf-image
            // refuses to read files outside certain paths even when readable.
            // Copying to /tmp sidesteps this restriction entirely.
            $tmpImg = sys_get_temp_dir() . '/tcpdf_img_' . getmypid() . '_' . time();

            if ($imgType === 'WEBP' && function_exists('imagecreatefromwebp')) {
                // Convert WEBP → JPEG via GD
                $tmpImg .= '.jpg';
                $gd = @imagecreatefromwebp($photoPath);
                if ($gd) {
                    imagejpeg($gd, $tmpImg, 90);
                    imagedestroy($gd);
                    $imgType = 'JPEG';
                    $info    = @getimagesize($tmpImg) ?: $info;
                } else {
                    $tmpImg = null;
                }
            } elseif ($imgType === 'PNG') {
                $tmpImg .= '.png';
                copy($photoPath, $tmpImg);
            } else {
                // JPEG or fallback
                $tmpImg .= '.jpg';
                copy($photoPath, $tmpImg);
            }

            // Use temp path if copy succeeded, otherwise original
            $renderPath = ($tmpImg && file_exists($tmpImg)) ? $tmpImg : $photoPath;
            error_log('PDF render path: ' . $renderPath . ' | exists: ' . (file_exists($renderPath) ? 'yes' : 'no'));

            $maxW  = 130.0;
            $maxH  = 85.0;
            $ratio = $info[0] / max($info[1], 1);
            $imgW  = $maxW;
            $imgH  = $maxW / $ratio;
            if ($imgH > $maxH) { $imgH = $maxH; $imgW = $maxH * $ratio; }
            $imgX  = $mL + ($contW - $imgW) / 2;

            // Light border
            $pdf->SetDrawColor(...$light);
            $pdf->SetLineWidth(0.3);
            $pdf->RoundedRect($imgX - 1, $y - 1, $imgW + 2, $imgH + 2, 2, '1111', 'D');

            try {
                $pdf->Image(
                    $renderPath,
                    $imgX, $y,
                    $imgW, $imgH,
                    $imgType,
                    '', '', true, 150, '', false, false, 0, 'CM', false, false
                );
                $y += $imgH + 7;
                $imageRendered = true;
            } catch (\Throwable $e) {
                error_log('PDF image error for ' . $renderPath . ': ' . $e->getMessage());
            } finally {
                // Always clean up temp file
                if ($tmpImg && file_exists($tmpImg)) {
                    @unlink($tmpImg);
                }
            }
        }
    }
    if (!$imageRendered) {
        $y = _noImagePlaceholder($pdf, $mL, $contW, $y);
    }

    // ── Details table ─────────────────────────────────────────────────────────
    $colL = 72;
    $colV = $contW - $colL;

    $rows = array_filter([
        ['Product Name',    $p['name']                   ?? ''],
        ['Quarry No',       $p['quarry_number']           ?? ''],
        ['Stone Type',      $p['category']               ?? ''],
        ['Color',           $p['color_subcategory']       ?? ''],
        ['Thickness',       $p['thickness']              ?? ''],
        ['Usable Size',     $sizesDisplay],
        ['Italian Size',    $italianDisplay],
        ['No. of Pieces',   ($p['pieces'] ?? 0) > 0 ? (string)(int)$p['pieces'] : ''],
        ['Available Qty',   ((float)($p['quantity_available'] ?? 0)) > 0
                                ? number_format((float)$p['quantity_available'], 0) . ' sq.ft.' : ''],
        ['On Hold',         ((float)($p['quantity_on_hold'] ?? 0)) > 0
                                ? number_format((float)$p['quantity_on_hold'], 0) . ' sq.ft.' : ''],
        ['Origin',          $p['origin']  ?? ''],
        ['Finish',          $p['finish']  ?? ''],
    ], fn($r) => $r[1] !== '');

    $pdf->SetXY($mL, $y);

    // Table header
    $pdf->SetFillColor(...$black);
    $pdf->SetTextColor(...$white);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetDrawColor(...$light);
    $pdf->SetLineWidth(0.2);
    $pdf->Cell($colL, 7, 'Field',  'B', 0, 'L', true);
    $pdf->Cell($colV, 7, 'Value',  'B', 1, 'L', true);

    $alt = false;
    $pdf->SetFont('helvetica', '', 9);
    foreach ($rows as $row) {
        $pdf->SetFillColor(...($alt ? $altRow : $white));
        $pdf->SetTextColor(...$black);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell($colL, 6.5, $row[0], 'B', 0, 'L', true);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell($colV, 6.5, $row[1], 'B', 1, 'L', true);
        $alt = !$alt;
    }

    $y = $pdf->GetY() + 8;

    // ── Footer ────────────────────────────────────────────────────────────────
    $pdf->SetDrawColor(...$black);
    $pdf->SetLineWidth(0.4);
    $pdf->Line($mL, $y, $mL + $contW, $y);
    $y += 4;

    $pdf->SetXY($mL, $y);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColor(...$black);
    $pdf->Cell($contW, 5, APP_NAME, 0, 1, 'C');

    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(...$mid);
    $pdf->Cell($contW, 5, 'Regards, ' . APP_NAME, 0, 0, 'C');

    // ── Save — write via PHP instead of TCPDF to avoid tc-lib-pdf path validation bug ──
    $pdfString = $pdf->Output('', 'S');   // get as string
    if (empty($pdfString)) {
        throw new \RuntimeException('TCPDF returned empty PDF string.');
    }
    $written = file_put_contents($pdfPath, $pdfString);
    if ($written === false || $written === 0) {
        throw new \RuntimeException(
            'file_put_contents failed writing PDF to: ' . $pdfPath .
            ' | dir_writable=' . (is_writable(dirname($pdfPath)) ? 'yes' : 'no') .
            ' | dir_exists=' . (is_dir(dirname($pdfPath)) ? 'yes' : 'no')
        );
    }
}

// ── No-image placeholder ──────────────────────────────────────────────────────
function _noImagePlaceholder(\TCPDF $pdf, float $mL, float $contW, float $y): float
{
    $ph = 28;
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetLineWidth(0.3);
    $cx = $mL + ($contW - 80) / 2;
    $pdf->RoundedRect($cx, $y, 80, $ph, 3, '1111', 'FD');
    $pdf->SetXY($mL, $y + 10);
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell($contW, 6, 'No image available', 0, 1, 'C');
    return $y + $ph + 6;
}

// ── Cleanup ───────────────────────────────────────────────────────────────────
function cleanOldProductPdfs(int $maxAge = PDF_MAX_AGE): int
{
    $deleted = 0;
    if (!is_dir(PDF_TEMP_DIR)) return 0;
    foreach (glob(PDF_TEMP_DIR . '/*.pdf') ?: [] as $file) {
        if (is_file($file) && (time() - filemtime($file)) > $maxAge) {
            @unlink($file);
            $deleted++;
        }
    }
    return $deleted;
}

// ── AJAX endpoint for WhatsApp share ─────────────────────────────────────────
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