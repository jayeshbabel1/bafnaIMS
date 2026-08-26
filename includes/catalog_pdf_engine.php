<?php
if (!defined('K_PATH_FONTS')) {
    define('K_PATH_FONTS', BASE_PATH . '/vendor/tecnickcom/tc-lib-pdf-font/target/fonts/');
}
$autoload = BASE_PATH . '/vendor/autoload.php';

if (!class_exists('\TCPDF')) {
    if (!file_exists($autoload)) {
        throw new RuntimeException(
            'TCPDF bootstrap failed: vendor/autoload.php not found.'
        );
    }
    require_once $autoload;
}

if (!class_exists('\TCPDF')) {
    throw new RuntimeException(
        'TCPDF bootstrap failed: TCPDF class not found after loading vendor/autoload.php.'
    );
}

class CatalogTCPDF extends \TCPDF {
    public array $cpeConfig = [];
    public array $cpeCatalog = [];
   public array $cpeSuppressPages = [];  
  
        public function Header() {
          if (in_array($this->getPage(), $this->cpeSuppressPages, true)) return;
        $h = $this->cpeConfig['header'] ?? [];
        if (empty(array_filter($h))) return;

        $pageW = $this->getPageWidth();
        $font  = $this->cpeConfig['_font_family'] ?? 'helvetica';
        $navyRgb = $this->cpeConfig['_colors_rgb']['text'] ?? [26, 40, 55];
        $grayRgb = [130, 130, 130];
        $lineRgb = [225, 225, 225];
        $mL = 15; $contW = $pageW - 30;
        $y = 10;

            if (!empty($h['catalog_name'])) {
            $companyName = mb_strtoupper(getSetting('company_short_name', '') ?: ($this->cpeCatalog['name'] ?? APP_NAME));
            $this->SetFont($font, 'B', 11);
            $this->SetTextColor(...$navyRgb);
            $this->SetXY($mL, $y);
            $this->Cell($contW / 2, 6, $companyName, 0, 0, 'L');
        }
        $this->SetFont($font, '', 10);
        $this->SetTextColor(...$grayRgb);
        $this->SetXY($mL + $contW / 2, $y);
        $this->Cell($contW / 2, 6, date('d M Y'), 0, 0, 'R');
        $y += 9;

        $this->SetDrawColor(...$lineRgb);
        $this->SetLineWidth(0.2);
        $this->Line($mL, $y, $mL + $contW, $y);
        $y += 6;

        // "Prepared for {name}" banner — repurposes the 'page_title' header
        // toggle since there was no dedicated option for this before.
        $preparedFor = trim((string)($this->cpeConfig['cover']['prepared_for'] ?? ''));
        if (!empty($h['page_title']) && $preparedFor !== '') {
            $this->SetFont($font, 'B', 17);
            $this->SetTextColor(...$navyRgb);
            $this->SetXY($mL, $y);
            $this->Cell($contW, 9, 'Prepared for ' . $preparedFor, 0, 1, 'L');
            $y = $this->GetY() + 3;
            $this->SetDrawColor(...$lineRgb);
            $this->Line($mL, $y, $mL + $contW, $y);
        }
    }

       public function Footer() {

    $f = $this->cpeConfig['footer'] ?? [];
    if (empty(array_filter($f))) return;

    // IMPORTANT:
    // Footer is positioned manually at the bottom of the page.
    // Prevent TCPDF Cell() from triggering automatic page creation.
    $this->SetAutoPageBreak(false, 0);

    $pageW = $this->getPageWidth();
    $pageH = $this->getPageHeight();
    $font  = $this->cpeConfig['_font_family'] ?? 'helvetica';

    $grayRgb = [130, 130, 130];
    $lineRgb = [225, 225, 225];

    $mL = 15;
    $contW = $pageW - 30;

    $y = $pageH - 18;

    // Footer separator
    $this->SetDrawColor(...$lineRgb);
    $this->SetLineWidth(0.2);
    $this->Line($mL, $y, $mL + $contW, $y);

    // Footer text
    $this->SetFont($font, '', 8.5);
    $this->SetTextColor(...$grayRgb);

    $colW = $contW / 3;

    // LEFT — website
    if (!empty($f['website'])) {
        $this->SetXY($mL, $y + 3);
        $this->Cell($colW, 6,  BASE_URL,      0,        0,        'L'    );    }

    // CENTER — page number
    if (!empty($f['page_number'])) {
        $this->SetXY($mL + $colW, $y + 3);
        $this->Cell($colW,6,'Page ' . $this->getAliasNumPage(),0,0,'C');
    }

        if (!empty($f['email'])) {
        $this->SetXY($mL + $colW*2, $y + 3);
        $this->Cell($colW,6,getSetting('company_email', ''),0,0,'R');
    }
}
    private function _cpePageNumberPos(string $pos, float $pageW, float $pageH): array {
        return match ($pos) {
            'bottom_left'   => [15, $pageH - 15, 'L'],
            'bottom_right'  => [$pageW - 55, $pageH - 15, 'R'],
            'top_left'      => [15, 8, 'L'],
            'top_right'     => [$pageW - 55, 8, 'R'],
            default         => [($pageW - 40) / 2, $pageH - 15, 'C'], // bottom_center
        };
    }
}

// ── Main entry 
function generateCatalogPdf(int $catalogId): array {
   
    $cat = getCatalog($catalogId);
    if (!$cat) return ['success' => false, 'error' => 'Catalog not found.'];

    $config     = array_replace_recursive(catalogPdfDefaultConfig(), $cat['config'] ?: []);
    $productIds = $cat['product_ids'];
    if (empty($productIds)) return ['success' => false, 'error' => 'No products selected.'];

    $autoload = BASE_PATH . '/vendor/autoload.php';
    if (!file_exists($autoload)) return ['success' => false, 'error' => 'vendor/autoload.php not found.'];
    require_once $autoload;
    if (!class_exists('\TCPDF')) return ['success' => false, 'error' => 'TCPDF not found.'];

    $db = getDB();
    $in = implode(',', array_fill(0, count($productIds), '?'));
    $st = $db->prepare("SELECT * FROM products WHERE id IN ($in)");
    $st->execute($productIds);
    $byId = [];
    foreach ($st->fetchAll() as $r) $byId[$r['id']] = $r;
    $products = [];
    foreach ($productIds as $pid) if (isset($byId[$pid])) $products[] = $byId[$pid];
 if (!empty($config['_selection_map']) && is_array($config['_selection_map'])) {
        foreach ($products as &$selProduct) {
            $sel = $config['_selection_map'][$selProduct['id']] ?? null;
            if ($sel) {
                $selProduct['quantity_required'] = $sel['quantity_required'] ?? '';
                $selProduct['selection_area']    = $sel['selection_area']    ?? '';
            }
        }
        unset($selProduct);
    }
    try {
        // ── Orientation / page size ──────────────────────────────────────
        $orientation = ($config['orientation'] ?? 'portrait') === 'landscape' ? 'L' : 'P';
        $pageSizeKey = $config['page_size'] ?? 'A4';
        $format = match ($pageSizeKey) {
            'A3'     => 'A3',
            'Letter' => 'LETTER',
            'Legal'  => 'LEGAL',
            'Custom' => [(float)($config['custom_w_mm'] ?? 210), (float)($config['custom_h_mm'] ?? 297)],
            default  => 'A4',
        };

        $pdf = new CatalogTCPDF($orientation, 'mm', $format, true, 'UTF-8', false);
        $pdf->cpeConfig  = $config;
        $pdf->cpeCatalog = $cat;
        $GLOBALS['_cpeTempFiles'] = []; 
        $pdf->SetCreator(APP_NAME);
        $pdf->SetTitle($cat['name']);

                $headerOn = !empty(array_filter($config['header'] ?? []));
        $footerOn = !empty(array_filter($config['footer'] ?? []));
        $preparedForShown = $headerOn && !empty($config['header']['page_title'])
            && trim((string)($config['cover']['prepared_for'] ?? '')) !== '';
        $pdf->setPrintHeader($headerOn);
        $pdf->setPrintFooter($footerOn);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        $pdf->SetMargins(15, $headerOn ? ($preparedForShown ? 40 : 25) : 15, 15);
        $pdf->SetAutoPageBreak(true, $footerOn ? 22 : 15);

        // ── Compression / quality 
        $pdf->SetCompression(($config['quality']['compression'] ?? 'compress') === 'compress');

                // ── Font 
        $fontFamily = _cpeResolveFont($config['font'] ?? 'helvetica');
        _cpeRegisterCustomFont($pdf, $fontFamily);
        $config['_font_family'] = $fontFamily;
        $pdf->cpeConfig = $config;

        // ── Colors (hex → rgb, used by layout renderers + header/footer) ──
        $colorsRgb = [];
        foreach (($config['colors'] ?? []) as $k => $hex) $colorsRgb[$k] = _cpeHexToRgb($hex);
        $config['_colors_rgb'] = $colorsRgb;
       _cpeRenderCoverPage($pdf, $cat, $config);
        $pdf->cpeSuppressPages[] = $pdf->getPage(); 

        $layout = $config['layout'] ?? 'one_per_page';
        $gridLayouts = ['two_per_page', 'four_per_page', 'grid'];
        if (in_array($layout, $gridLayouts, true)) {
            // Capture the real bottom margin BEFORE disabling auto-break —
            // SetAutoPageBreak() resets TCPDF's internal bMargin to the
            // value passed in, which corrupts getMargins()['bottom'] for
            // any code that reads it afterward (our grid cell-height math).
            $config['_footer_reserve'] = $footerOn ? 22 : 15;
            $pdf->cpeConfig = $config;
            // Manual absolute-position grids cannot tolerate TCPDF's
            // auto page-break — it inserts pages mid-cell and desyncs
            // the slot math, causing images/text to land on the wrong
            // page (looks like "1 image per page then a stray text page").
            $pdf->SetAutoPageBreak(false, 0);
        }
               switch ($layout) {
            case 'two_per_page':  _cpeRenderLayoutN($pdf, $products, $config, 2); break;
            case 'four_per_page': _cpeRenderLayoutN($pdf, $products, $config, 4); break;
            case 'grid':          _cpeRenderLayoutGrid($pdf, $products, $config); break;
            case 'architect':     foreach ($products as $p) _cpeRenderLayoutArchitect($pdf, $p, $config); break;
            default:
                $totalSel = count($products);
    // Product pages are manually controlled.
    // Prevent TCPDF from creating unexpected blank pages.
    $pdf->SetAutoPageBreak(false, 0);

    foreach ($products as $i => $p) {
        _cpeRenderLayoutOne(
            $pdf,
            $p,
            $config,
            $i + 1,
            $totalSel
        );
    }
    // Restore normal TCPDF behaviour after product pages.
    $footerOn = !empty(array_filter($config['footer'] ?? []));
    $pdf->SetAutoPageBreak(true, $footerOn ? 22 : 15);

    break;
        }
        if (in_array($layout, $gridLayouts, true)) {
            $footerOn = !empty(array_filter($config['footer'] ?? []));
            $pdf->SetAutoPageBreak(true, $footerOn ? 22 : 15); // restore for closing page
        }

        if (!empty($config['closing']['enabled'])) {
    $config['_suppress_hf'] = true; $pdf->cpeConfig = $config;
    _cpeRenderClosingPage($pdf, $config);
}

        // ── Watermark — applied to ALL pages after content built ─────────
        _cpeApplyWatermarkToAllPages($pdf, $config);

        $safeName = preg_replace('/[^A-Za-z0-9 _\-]/u', '', $cat['name']);
    $safeName = trim(preg_replace('/\s+/', '_', $safeName)) ?: ('catalog_' . $catalogId);
    $filename = $safeName . '_' . time() . '.pdf';
    $path     = CATALOG_PDF_DIR . '/' . $filename;

    $pdfString = $pdf->Output('', 'S');
    foreach (($GLOBALS['_cpeTempFiles'] ?? []) as $tf) { @unlink($tf); }
    unset($GLOBALS['_cpeTempFiles']);
    if (empty($pdfString)) return ['success' => false, 'error' => 'TCPDF returned empty output.'];
    file_put_contents($path, $pdfString);

    // NEW: remove the previous rendered PDF now that the new one wrote successfully
    if (!empty($cat['pdf_path']) && $cat['pdf_path'] !== $path && file_exists($cat['pdf_path'])) {
        @unlink($cat['pdf_path']);
    }

    $pages = $pdf->getNumPages();
    $size  = filesize($path);

    $db->prepare("UPDATE catalogs SET status='done', pdf_path=?, pages=?, size_bytes=?, updated_at=? WHERE id=?")
       ->execute([$path, $pages, $size, time(), $catalogId]);

    return ['success' => true, 'path' => $path, 'filename' => $filename, 'pages' => $pages, 'size' => $size];
} catch (\Throwable $e) { 
      foreach (($GLOBALS['_cpeTempFiles'] ?? []) as $tf) { @unlink($tf); }
    unset($GLOBALS['_cpeTempFiles']);
   
        error_log('generateCatalogPdf: ' . $e->getMessage());
        $db->prepare("UPDATE catalogs SET status='failed', updated_at=? WHERE id=?")->execute([time(), $catalogId]);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ── Helpers ─────────────────────────────────────────────────────────────

function _cpeRegisterTemp(?string $path): ?string {
    if ($path) $GLOBALS['_cpeTempFiles'][] = $path;
    return $path;
}
// Resolves the site logo to a TCPDF-safe temp path, converting WEBP → PNG
// (preserves transparency, unlike JPEG) and copying to sys_get_temp_dir()
// so tc-lib-pdf-image doesn't reject it as outside its allowed read paths.
function _cpeResolveLogoImage(): ?array {
    $logoFile = getSetting('site_logo', '');
    if (!$logoFile) return null;
    $fullPath = BASE_PATH . '/uploads/logo/' . $logoFile;
    if (!file_exists($fullPath)) return null;

    $info = @getimagesize($fullPath);
    if (!$info) return null;

    // Always normalize to PNG so the declared TCPDF type is always correct,
    // regardless of the source format (jpeg/png/webp).
  $tmp = sys_get_temp_dir() . '/cpe_logo_' . uniqid('', true) . '_' . random_int(1000,9999) . '.png';

    $gd = match ($info[2]) {
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($fullPath) : false,
        IMAGETYPE_JPEG => @imagecreatefromjpeg($fullPath),
        IMAGETYPE_PNG  => @imagecreatefrompng($fullPath),
        default        => false,
    };
    if (!$gd) return null;

    imagealphablending($gd, false);
    imagesavealpha($gd, true);
    imagepng($gd, $tmp);
    imagedestroy($gd);

    if (!file_exists($tmp)) return null;
    return ['path' => $tmp, 'type' => 'PNG'];
}

function _cpeResolveFont(string $font): string {
    // Helvetica = TCPDF core font, no embed. Others fall back to helvetica
    // unless embedded TTF font defs exist in storage/fonts/ (see
    // tools/generate_font.php) — safe no-op fallback keeps generation from
    // breaking if the font files haven't been generated yet.
    $map = [
        'helvetica' => 'helvetica', 'arial' => 'helvetica',
        'roboto' => 'helvetica', 'open_sans' => 'helvetica', 'noto_sans' => 'helvetica',
    ];
    $custom = BASE_PATH . '/storage/fonts/' . $font . '.php';
    if (file_exists($custom)) return $font; // embedded font def present
    return $map[$font] ?? 'helvetica';
}

// ── Register a custom embedded TCPDF font (if generated files exist) ──────
// _cpeResolveFont() only decides which *name* to use; TCPDF still needs the
// font registered via AddFont() before any SetFont() call can find it,
// since custom fonts live in storage/fonts/ rather than TCPDF's built-in
// K_PATH_FONTS folder.
function _cpeRegisterCustomFont(\TCPDF $pdf, string $fontName): void {
    static $done = [];
    if ($fontName === 'helvetica' || isset($done[$fontName])) return;
    $done[$fontName] = true;
    $styleFiles = ['' => '', 'B' => 'b', 'I' => 'i', 'BI' => 'bi'];
    foreach ($styleFiles as $style => $suffix) {
        $def = BASE_PATH . '/storage/fonts/' . $fontName . $suffix . '.php';
        if (file_exists($def)) {
            try { $pdf->AddFont($fontName, $style, $def); }
            catch (\Throwable $e) { error_log('_cpeRegisterCustomFont: ' . $e->getMessage()); }
        }
    }
}

function _cpeHexToRgb(string $hex): array {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    if (strlen($hex) !== 6) return [0,0,0];
    return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
}

function _cpeQualityImageParams(array $config): array {
    return match ($config['quality']['level'] ?? 'medium') {
        'low'    => ['dpi' => 72,  'jpq' => 60],
        'high'   => ['dpi' => 150, 'jpq' => 90],
        'print'  => ['dpi' => 300, 'jpq' => 95],
        default  => ['dpi' => 110, 'jpq' => 75], // medium
    };
}


// AFTER
function _cpeTempImage(string $fullPath, array $config): ?string {
    $q = _cpeQualityImageParams($config);
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $tmp = sys_get_temp_dir() . '/cpe_' . uniqid('', true) . '_' . random_int(1000,9999) . '.' . ($ext === 'webp' ? 'jpg' : $ext);

    if (empty($config['quality']['optimize_size'])) {
        if (!copy($fullPath, $tmp)) {
            error_log("catalog_pdf: copy failed for $fullPath");
            return null;
        }
        return _cpeRegisterTemp($tmp);
    }

    $maxDim = match ($q['dpi']) { 72 => 900, 150 => 1400, 300 => 2200, default => 1100 };
    $info = @getimagesize($fullPath);
    if (!$info) {
        if (!copy($fullPath, $tmp)) { error_log("catalog_pdf: copy fallback failed for $fullPath"); return null; }
        return _cpeRegisterTemp($tmp);
    }
    [$w, $h, $type] = $info;
    if (max($w,$h) <= $maxDim) {
        if (!copy($fullPath, $tmp)) { error_log("catalog_pdf: copy fallback failed for $fullPath"); return null; }
        return _cpeRegisterTemp($tmp);
    }

    $ratio = $maxDim / max($w,$h);
    $nw = max(1,(int)($w * $ratio)); $nh = max(1,(int)($h * $ratio));
    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($fullPath),
        IMAGETYPE_PNG  => @imagecreatefrompng($fullPath),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($fullPath) : false,
        default        => false,
    };
    if (!$src) {
        error_log("catalog_pdf: imagecreatefrom* failed for $fullPath (type=$type)");
        if (!copy($fullPath, $tmp)) return null;
        return _cpeRegisterTemp($tmp);
    }
    $dst = imagecreatetruecolor($nw, $nh);
    $tmpJpg = sys_get_temp_dir() . '/cpe_' . uniqid('', true) . '_' . random_int(1000,9999) . '.jpg';
    imagecopyresampled($dst, $src, 0,0,0,0, $nw,$nh,$w,$h);
    $ok = imagejpeg($dst, $tmpJpg, $q['jpq']);
    imagedestroy($src); imagedestroy($dst);
    if (!$ok || !file_exists($tmpJpg)) {
        error_log("catalog_pdf: imagejpeg write failed for $fullPath -> $tmpJpg");
        return null;
    }
    return _cpeRegisterTemp($tmpJpg);
}

// Near-lossless copy of the original photo for the one_per_page hero image
// — bypasses _cpeTempImage()'s downscale/recompress path entirely. JPEG/PNG
// sources are copied byte-for-byte; WEBP (which TCPDF can't read natively)
// is converted to PNG at zero compression rather than a lossy JPEG.
// Near-lossless copy of the original photo for the one_per_page hero image
// — bypasses _cpeTempImage()'s downscale/recompress path entirely. JPEG/PNG
// sources are copied byte-for-byte; WEBP (which TCPDF can't read natively)
// is converted to PNG at zero compression rather than a lossy JPEG.
function _cpeTempImageHQ(string $fullPath, array $config): ?string {
    $info = @getimagesize($fullPath);
    $tmp  = sys_get_temp_dir() . '/cpe_hq_' . uniqid('', true) . '_' . random_int(1000, 9999);

    if ($info && $info[2] === IMAGETYPE_WEBP) {
        $tmp .= '.png';
        if (function_exists('imagecreatefromwebp')) {
            $gd = @imagecreatefromwebp($fullPath);
            if ($gd) {
                imagepng($gd, $tmp, 0);
                imagedestroy($gd);
                return file_exists($tmp) ? _cpeRegisterTemp($tmp) : null;
            }
        }
        error_log("catalog_pdf HQ: could not convert WEBP for $fullPath");
        return null;
    }

    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) ?: 'jpg';
    $tmp .= '.' . $ext;
    if (!copy($fullPath, $tmp)) {
        error_log("catalog_pdf HQ: copy failed for $fullPath");
        return null;
    }
    return _cpeRegisterTemp($tmp);
}

function _cpeProductPhotoFull(int $productId): ?string {
    $st = getDB()->prepare("SELECT filename FROM product_photos WHERE product_id=? ORDER BY sort_order LIMIT 1");
    $st->execute([$productId]);
    $row = $st->fetch();
    if (!$row) return null;
    $resolved = resolvePhotoPath(PHOTOS_DIR, $row['filename']);
    return $resolved ? PHOTOS_DIR . '/' . $resolved : null;
}

// Truncate a string with ellipsis so it never exceeds $maxW mm at given font/size.
function _cpeClampText(\TCPDF $pdf, string $text, float $maxW, string $font, string $style, float $size): string {
    $pdf->SetFont($font, $style, $size);
    if ($pdf->GetStringWidth($text) <= $maxW) return $text;
    $ell = '…';
    while (mb_strlen($text) > 1) {
        $text = mb_substr($text, 0, -1);
        if ($pdf->GetStringWidth($text . $ell) <= $maxW) return $text . $ell;
    }
    return $ell;
}

function _cpeFieldRows(array $p, array $fields, array $selectionMap = []): array {
    $labelMap = [
        'category'=>'Stone Type','subcategory'=>'Subcategory','color_subcategory'=>'Color',
        'thickness'=>'Thickness','origin'=>'Origin','finish'=>'Finish',
        'quantity_available'=>'Available Qty','description'=>'Description','sizes'=>'Useable Size','cutter_size'=>'Italian Size', 'quantity_required'=>'Required Qty','selection_area'=>'Selected Area',];
    $slab = formatDimension($p['sizes_l'] ?? '', $p['sizes_h'] ?? '');
    $cut  = formatDimension($p['cutter_size_l'] ?? '', $p['cutter_size_h'] ?? '');
    $sel  = $selectionMap[$p['id']] ?? [];
    $extra = [
        'sizes' => $slab,
        'cutter_size' => $cut,
        'quantity_required' => (isset($sel['quantity_required']) && (float)$sel['quantity_required'] > 0)
            ? number_format((float)$sel['quantity_required']) . ' sq.ft.' : '',
        'selection_area' => trim((string)($sel['selection_area'] ?? '')),
    ];
    $rows = [];
    $fields = array_unique($fields);
    foreach ($fields as $fk) {
        if (in_array($fk, ['name','quarry_number'], true)) continue;
        $val = $extra[$fk] ?? ($p[$fk] ?? '');
        if ($fk === 'quantity_available') $val = $val ? number_format((float)$val) . ' sq.ft.' : '';
        if ($val === '' || $val === null) continue;
        $rows[] = [$labelMap[$fk] ?? ucfirst(str_replace('_',' ',$fk)), (string)$val];
    }
    return $rows;
}

// ── Cover page ──────────────────────────────────────────────────────────
function _cpeRenderCoverPage(\TCPDF $pdf, array $cat, array $config): void {
    $cover = $config['cover'] ?? [];
    $pdf->AddPage();
    $pageW = $pdf->getPageWidth();
    $pageH = $pdf->getPageHeight();
    $mL = 15; $contW = $pageW - 30;
    $font = $config['_font_family'] ?? 'helvetica';

    $navyRgb = $config['_colors_rgb']['text']  ?? [26, 40, 55];
    $goldRgb = $config['_colors_rgb']['accent'] ?? [184, 151, 90];
    $grayRgb = [130, 130, 130];
    $lineRgb = [222, 222, 222];

    // Thin top bar
    $pdf->SetFillColor(...$lineRgb);
    $pdf->Rect(0, 0, $pageW, 2, 'F');

       $y = 44;

    if (!empty($cover['logo'])) {
        $logo = _cpeResolveLogoImage();
        if ($logo) {
            $logoW = 46;
            $info  = @getimagesize($logo['path']);
            $logoH = $info ? ($logoW * $info[1] / max($info[0], 1)) : $logoW * 0.6;
            try {
                $pdf->Image($logo['path'], ($pageW - $logoW) / 2, $y, $logoW, 0, $logo['type'], '', '', true, 150, 'C');
                $y += $logoH + 14;
            } catch (\Throwable $e) {}
            @unlink($logo['path']);
        }
    }

    // Title
    $pdf->SetXY($mL, $y);
    $pdf->SetFont($font, 'B', 28);
    $pdf->SetTextColor(...$navyRgb);
    $pdf->MultiCell($contW, 13, mb_strtoupper($cover['title'] ?? APP_NAME), 0, 'C');
    $y = $pdf->GetY() + 3;

    // Tagline — italic, gold. Falls back to the Company Tagline setting.
    $tagline = trim((string)($cover['tagline'] ?? '')) ?: trim(getSetting('company_tagline', ''));
    if ($tagline !== '') {
        $pdf->SetXY($mL, $y);
        $pdf->SetFont($font, 'I', 13);
        $pdf->SetTextColor(...$goldRgb);
        $pdf->MultiCell($contW, 8, $tagline, 0, 'C');
        $y = $pdf->GetY() + 6;
    }

    // Gold divider
    $pdf->SetDrawColor(...$goldRgb);
    $pdf->SetLineWidth(0.5);
    $pdf->Line($pageW / 2 - 22, $y, $pageW / 2 + 22, $y);
    $y += 16;

    // Section label, e.g. "STONE SELECTIONS"
    $label = trim((string)($cover['label'] ?? ''));
    if ($label !== '') {
        $pdf->SetXY($mL, $y);
        $pdf->SetFont($font, 'B', 15);
        $pdf->SetTextColor(...$navyRgb);
        $pdf->Cell($contW, 8, mb_strtoupper($label), 0, 1, 'C');
        $y = $pdf->GetY() + 4;
    }

    // "Prepared for" + recipient name
    $preparedFor = trim((string)($cover['prepared_for'] ?? ''));
    if ($preparedFor !== '') {
        $pdf->SetXY($mL, $y);
        $pdf->SetFont($font, '', 11);
        $pdf->SetTextColor(...$grayRgb);
        $pdf->Cell($contW, 6, 'Prepared for', 0, 1, 'C');
        $y = $pdf->GetY() + 4;

        $pdf->SetXY($mL, $y);
        $pdf->SetFont($font, 'B', 30);
        $pdf->SetTextColor(...$navyRgb);
        $pdf->MultiCell($contW, 13, $preparedFor, 0, 'C');
        $y = $pdf->GetY() + 16;
    } else {
        $y += 10;
    }

    // Info table — Date / Version / Contact / Website
    $infoRows = [];
    if (!empty($cover['show_date']))  $infoRows[] = ['Date', date($cover['date_format'] ?? 'd M Y')];
    if (!empty($cover['version']))    $infoRows[] = ['Version', $cover['version']];
    if (!empty($cover['contact_details'])) {
        $contactLine = trim(implode('  |  ', array_filter([
            getSetting('company_support_phone', ''),
            getSetting('company_email', ''),
        ])));
        if ($contactLine !== '') $infoRows[] = ['Contact', $contactLine];
    }
    $infoRows[] = ['Website', BASE_URL];

    if ($infoRows) {
        $tableX = $pageW / 2 - 62;
        $pdf->SetY($y);
        foreach ($infoRows as $row) {
            $pdf->SetX($tableX);
            $pdf->SetFont($font, 'B', 10);
            $pdf->SetTextColor(...$navyRgb);
            $pdf->Cell(26, 7, $row[0], 0, 0, 'L');
            $pdf->SetFont($font, '', 10);
            $pdf->SetTextColor(70, 70, 70);
            $pdf->Cell(96, 7, $row[1], 0, 1, 'L');
        }
    }

    if (!empty($cover['footer_text'])) {
        $pdf->SetY($pageH - 34);
        $pdf->SetFont($font, 'I', 9);
        $pdf->SetTextColor(...$grayRgb);
        $pdf->Cell($contW, 6, $cover['footer_text'], 0, 1, 'C');
    }

}
// ── Layout 1: one product per page ─────────────────────────────────────
function _cpeRenderLayoutOne(\TCPDF $pdf, array $p, array $config, int $index = 0, int $total = 0): void {
    $fields = $config['fields'] ?? [];
    $font   = $config['_font_family'] ?? 'helvetica';
    $navyRgb = $config['_colors_rgb']['text']   ?? [26, 40, 55];
    $goldRgb = $config['_colors_rgb']['accent'] ?? [184, 151, 90];
    $grayRgb = [130, 130, 130];
    $altRgb  = [247, 244, 239];
       $pdf->AddPage();
    $pageW = $pdf->getPageWidth();
    $mL = 15; $contW = $pageW - 30;
    $y = $pdf->GetY();

    // Eyebrow — "SELECTION i OF N"
    if ($total > 0) {
        $pdf->SetXY($mL, $y);
        $pdf->SetFont($font, 'B', 9);
        $pdf->SetTextColor(...$goldRgb);
        $pdf->Cell($contW, 5, 'SELECTION ' . $index . ' OF ' . $total, 0, 1, 'L');
        $y = $pdf->GetY() + 2;
    }

    // Product name
    if (in_array('name', $fields, true)) {
        $pdf->SetXY($mL, $y);
        $pdf->SetFont($font, 'B', 19);
        $pdf->SetTextColor(...$navyRgb);
        $pdf->MultiCell($contW, 9, $p['name'] ?? '', 0, 'L');
        $y = $pdf->GetY() + 1;
    }

    // Quarry number
    if (in_array('quarry_number', $fields, true) && !empty($p['quarry_number'])) {
        $pdf->SetXY($mL, $y);
        $pdf->SetFont($font, '', 10.5);
        $pdf->SetTextColor(...$grayRgb);
        $pdf->Cell($contW, 6, 'Quarry No. ' . $p['quarry_number'], 0, 1, 'L');
        $y = $pdf->GetY() + 6;
    }

    // Hero photo — rendered from a near-lossless copy of the original file
    // (see _cpeTempImageHQ), not the downscaled/recompressed path other
    // layouts use, so it stays as close to source quality as TCPDF allows.
    $photoTopY = $y;
    $full = _cpeProductPhotoFull($p['id']);
    $rendered = false;
    if ($full && file_exists($full)) {
        $info = @getimagesize($full);
        if ($info) {
            $maxW = $contW; $maxH = 150;
            $ratio = $info[0] / max($info[1], 1);
            $imgW = $maxW; $imgH = $maxW / $ratio;
            if ($imgH > $maxH) { $imgH = $maxH; $imgW = $maxH * $ratio; }
            $imgX = $mL + ($contW - $imgW) / 2;
            $tmp = _cpeTempImageHQ($full, $config);
            if ($tmp) {
                try {
                    $pdf->Image($tmp, $imgX, $y, $imgW, $imgH, '', '', '', true, 300, 'C');
                    $rendered = true;
                } catch (\Throwable $e) {}
            }
            if ($rendered) $y += $imgH;
        }
    }
    if (!$rendered) $y += 20;

     $y += 8;

    // Details table — alternating row backgrounds, bold navy labels
     $rows = _cpeFieldRows($p, array_diff($fields, ['name']), $config['_selection_map'] ?? []);
    if ($rows) {
        $rowH = 11; $colL = 62;
        $alt = false;
        foreach ($rows as $row) {
            if ($alt) {
                $pdf->SetFillColor(...$altRgb);
                $pdf->Rect($mL, $y, $contW, $rowH, 'F');
            }
            $pdf->SetXY($mL, $y);
            $pdf->SetFont($font, 'B', 11.5);
            $pdf->SetTextColor(...$navyRgb);
            $pdf->Cell($colL, $rowH, $row[0], 0, 0, 'L');
            $pdf->SetFont($font, '', 11.5);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->MultiCell($contW - $colL, $rowH, $row[1], 0, 'L', false, 1, $mL + $colL, $y);
            $y += $rowH;
            $alt = !$alt;
        }
    }
}

// ── Layout N: 2 or 4 products per page (shared grid-cell renderer) ─────
// Fixed-height zones per cell: image zone + name zone (only if selected)
// + detail zone (clamped lines, includes quarry number if selected) —
// guarantees no cell ever exceeds its allotted cellH.
function _cpeRenderLayoutN(\TCPDF $pdf, array $products, array $config, int $perPage): void {
    $font = $config['_font_family'] ?? 'helvetica';
    $fields = $config['fields'] ?? [];
    $showName   = in_array('name', $fields, true);
    $showQuarry = in_array('quarry_number', $fields, true);

    $cols = $perPage === 2 ? 1 : 2;
    $rowsPerPage = 2;
    $pageW = $pdf->getPageWidth();
    $pageH = $pdf->getPageHeight();
    $mL = 15; $mT = $pdf->getMargins()['top'];
    $mB = $config['_footer_reserve'] ?? 15; // fixed value — getMargins()['bottom'] is unreliable after SetAutoPageBreak(false,0)
    $usableW = $pageW - 30;
    $usableH = $pageH - $mT - $mB;
    $cellW = $usableW / $cols;
    $cellH = $usableH / $rowsPerPage;
    $pad = 8;
    $innerW = $cellW - ($pad * 2);

    $nameSize = $perPage === 2 ? 13 : 10;
    $detailSize = $perPage === 2 ? 9 : 7.5;
    $detailLineH = $perPage === 2 ? 5 : 4.2;
    $nameLineH = $perPage === 2 ? 7 : 5.5;

    $nameZoneH = $showName ? $nameLineH : 0;
    $baseImgH = $cellH * 0.58; // shrink image a bit to leave room for more field lines
    $availForDetail = $cellH - $baseImgH - $nameZoneH - ($pad * 2);
    $maxDetailLines = max(1, (int)floor($availForDetail / $detailLineH));
    $detailZoneH = $maxDetailLines * $detailLineH;

    $i = 0;
    foreach ($products as $p) {
        $slot = $i % $perPage;
        if ($slot === 0) $pdf->AddPage();
        $col = $slot % $cols; $row = intdiv($slot, $cols);
        $x = $mL + $col * $cellW; $y = $mT + $row * $cellH;

        $pdf->SetDrawColor(...($config['_colors_rgb']['border'] ?? [225,225,225]));
        $pdf->Rect($x + 3, $y + 3, $cellW - 6, $cellH - 6, 'D');

        $full = _cpeProductPhotoFull($p['id']);
$imgH = $baseImgH; // reset every product — never carry over shrink from prior card
$drawX = null; $drawY = null; // reset draw markers too (see text-position fix below)
if ($full && file_exists($full)) {
    $info = @getimagesize($full);
    if ($info) {
        $ratio = $info[0]/max($info[1],1);
        $imgW = $imgH * $ratio;
        if ($imgW > $innerW) {
            $imgW = $innerW;
            $imgH = $imgW / $ratio;
        }
        $imgW = max(1, min($imgW, $innerW));
        $imgH = max(1, min($imgH, $cellH - $nameZoneH - $detailZoneH - ($pad*2)));

        $tmp = _cpeTempImage($full, $config);
               // Replace the draw line to use local clamped vars explicitly
$drawX = $x + ($cellW - $imgW) / 2;
$drawY = $y + $pad;
if ($tmp) {
    try {
        $pdf->Image($tmp, $drawX, $drawY, $imgW, $imgH, '', '', '', true, 150, '');
    } catch (\Throwable $e) {
      //  error_log('catalog_pdf LayoutN Image() failed for product '.($p['id']??'?').': '.$e->getMessage());
    }
} else {
    //error_log('catalog_pdf LayoutN: no temp image for product '.($p['id']??'?').' file='.$full);
}
            }
        }

       $ty = ($drawY !== null) ? ($drawY + $imgH + 4) : ($y + $pad + $baseImgH + 4);

        if ($showName) {
            $nameTxt = _cpeClampText($pdf, $p['name'] ?? '', $innerW, $font, 'B', $nameSize);
            $pdf->SetXY($x + $pad, $ty);
            $pdf->SetFont($font, 'B', $nameSize);
            $pdf->SetTextColor(...($config['_colors_rgb']['text'] ?? [20,20,20]));
            $pdf->Cell($innerW, $nameZoneH, $nameTxt, 0, 0, 'L');
            $ty += $nameZoneH + 1;
        }

        // Detail lines: quarry number first (if selected), then remaining checked fields
        $rows = _cpeFieldRows($p, array_diff($fields, ['name']), $config['_selection_map'] ?? []);
        if ($showQuarry && !empty($p['quarry_number'])) {
            array_unshift($rows, ['Quarry No', (string)$p['quarry_number']]);
        }

        $pdf->SetFont($font, '', $detailSize);
        $pdf->SetTextColor(100,100,100);
        $shown = array_slice($rows, 0, $maxDetailLines);
        foreach ($shown as $row2) {
            $lineTxt = _cpeClampText($pdf, $row2[0] . ': ' . $row2[1], $innerW, $font, '', $detailSize);
            $pdf->SetXY($x + $pad, $ty);
            $pdf->Cell($innerW, $detailLineH, $lineTxt, 0, 0, 'L');
            $ty += $detailLineH;
            if ($ty > $y + $cellH - 4) break; // hard stop — never exceed cell bottom
        }

        $i++;
    }
}

// ── Grid layout: many products, thumbnails only (3-col grid) ───────────
function _cpeRenderLayoutGrid(\TCPDF $pdf, array $products, array $config): void {
    $font = $config['_font_family'] ?? 'helvetica';
    $cols = 3; $rowsPerPage = 4; $perPage = $cols * $rowsPerPage;
    $pageW = $pdf->getPageWidth(); $pageH = $pdf->getPageHeight();
    $mL = 15; $mT = $pdf->getMargins()['top'];
    $mB = $config['_footer_reserve'] ?? 15; // fixed value — getMargins()['bottom'] unreliable after SetAutoPageBreak(false,0)
    $cellW = ($pageW - 30) / $cols;
    $cellH = ($pageH - $mT - $mB) / $rowsPerPage;
    $innerW = $cellW - 6;
   
    $i = 0;
    foreach ($products as $p) {
        $slot = $i % $perPage;
        if ($slot === 0) $pdf->AddPage();
        $col = $slot % $cols; $row = intdiv($slot, $cols);
        $x = $mL + $col * $cellW; $y = $mT + $row * $cellH;
        $showName   = in_array('name', $fields = $config['fields'] ?? [], true);
        $showQuarry = in_array('quarry_number', $fields, true);
        $nameH = 4; $lotH = 4;
        $imgSize = min($cellW, $cellH - $nameH - $lotH - 12) - 14;
        $imgSize = max(20, $imgSize);

        $full = _cpeProductPhotoFull($p['id']);
        if ($full && file_exists($full)) {
             $tmp = _cpeTempImage($full, $config);
            if ($tmp) {
                try {
                    $pdf->Image($tmp, $x + (($cellW-$imgSize)/2), $y + 4, $imgSize, $imgSize, '', '', '', true, 150, '');
                } catch (\Throwable $e) {
                    error_log('catalog_pdf Grid Image() failed for product '.($p['id']??'?').': '.$e->getMessage());
                }
                
            } else {
                error_log('catalog_pdf Grid: no temp image for product '.($p['id']??'?').' file='.$full);
            }
        }

        $nameTxt = _cpeClampText($pdf, $p['name'] ?? '', $innerW, $font, 'B', 8);
        $pdf->SetXY($x + 3, $y + 4 + $imgSize + 3);
        $pdf->SetFont($font, 'B', 8);
        $pdf->SetTextColor(...($config['_colors_rgb']['text'] ?? [20,20,20]));
        $pdf->Cell($innerW, $nameH, $nameTxt, 0, 0, 'C');

        if (!empty($p['quarry_number'])) {
            $lotTxt = _cpeClampText($pdf, 'Lot ' . $p['quarry_number'], $innerW, $font, '', 7);
            $pdf->SetXY($x + 3, $y + 4 + $imgSize + 3 + $nameH);
            $pdf->SetFont($font, '', 7);
            $pdf->SetTextColor(120,120,120);
            $pdf->Cell($innerW, $lotH, $lotTxt, 0, 0, 'C');
        }
        $i++;
    }
}

// ── Architect layout: minimal, full-bleed-ish large photo, tiny caption ─
function _cpeRenderLayoutArchitect(\TCPDF $pdf, array $p, array $config): void {
    $font = $config['_font_family'] ?? 'helvetica';
    $pdf->AddPage();
    $pageW = $pdf->getPageWidth(); $pageH = $pdf->getPageHeight();
    $mL = 15; $contW = $pageW - 30;
    $full = _cpeProductPhotoFull($p['id']);
    $y = $pdf->GetY();
    // _cpeRenderLayoutArchitect()
$fields = $config['fields'] ?? [];
if (in_array('name', $fields, true)) { $pdf->Cell($contW, 7, $p['name'] ?? '', 0, 1, 'C'); }
$metaParts = [];
if (in_array('quarry_number', $fields, true)) $metaParts[] = $p['quarry_number'] ?? '';
if (in_array('category', $fields, true))      $metaParts[] = $p['category'] ?? '';
$meta = trim(implode('   ', array_filter($metaParts)));
if ($meta !== '') { $pdf->SetFont($font,'',9); $pdf->Cell($contW, 5, $meta, 0, 1, 'C'); }
    if ($full && file_exists($full)) {
        $info = @getimagesize($full);
        if ($info) {
            $maxH = $pageH - $y - 40;
            $ratio = $info[0]/max($info[1],1);
            $imgH = $maxH; $imgW = $imgH * $ratio;
            if ($imgW > $contW) { $imgW = $contW; $imgH = $imgW / $ratio; }
            $tmp = _cpeTempImage($full, $config);
            if ($tmp) {
                try { $pdf->Image($tmp, $mL + ($contW-$imgW)/2, $y, $imgW, $imgH, '', '', '', true, 150, 'C'); } catch (\Throwable $e) {}
                  }
            $y += $imgH + 10;
        }
    }
    $pdf->SetXY($mL, $y);
    $pdf->SetFont($font, '', 14);
    $pdf->SetTextColor(...($config['_colors_rgb']['text'] ?? [20,20,20]));
    $pdf->Cell($contW, 7, $p['name'] ?? '', 0, 1, 'C');
    $pdf->SetFont($font, '', 9);
    $pdf->SetTextColor(140,140,140);
    $meta = trim(implode('   ', array_filter([$p['quarry_number'] ?? '', $p['category'] ?? ''])));
    $pdf->Cell($contW, 5, $meta, 0, 1, 'C');
}

// ── Closing page ────────────────────────────────────────────────────────
// ── Closing page ────────────────────────────────────────────────────────
function _cpeRenderClosingPage(\TCPDF $pdf, array $config): void {
    $closing = $config['closing'] ?? [];
    $font    = $config['_font_family'] ?? 'helvetica';
    $navyRgb = $config['_colors_rgb']['text']  ?? [26, 40, 55];
    $goldRgb = $config['_colors_rgb']['accent'] ?? [184, 151, 90];
    $textRgb = [50, 50, 50];

    $pdf->AddPage();
    $pageW = $pdf->getPageWidth();
    $pageH = $pdf->getPageHeight();
    $mL = 15; $contW = $pageW - 30;
    $y = 55;

    // Logo, centered
    $logo = _cpeResolveLogoImage();
    if ($logo) {
        $logoW = 50;
        $info  = @getimagesize($logo['path']);
        $logoH = $info ? ($logoW * $info[1] / max($info[0], 1)) : $logoW * 0.6;
        try {
            $pdf->Image($logo['path'], ($pageW - $logoW) / 2, $y, $logoW, 0, $logo['type'], '', '', true, 150, 'C');
            $y += $logoH + 18;
        } catch (\Throwable $e) {}
        @unlink($logo['path']);
    }

    // Title
    $pdf->SetXY($mL, $y);
    $pdf->SetFont($font, 'B', 19);
    $pdf->SetTextColor(...$navyRgb);
    $pdf->MultiCell($contW, 10, $closing['thank_you_text'] ?? ('Thank you for choosing ' . APP_NAME), 0, 'C');
    $y = $pdf->GetY() + 8;

    // Gold divider
    $pdf->SetDrawColor(...$goldRgb);
    $pdf->SetLineWidth(0.5);
    $pdf->Line($pageW / 2 - 22, $y, $pageW / 2 + 22, $y);
    $y += 16;

    // Company name + address + contact — all centered
    if (!empty($closing['contact_info'])) {
        $companyName = trim(getSetting('company_name', APP_NAME));
        $address     = trim(getSetting('company_address', ''));
        $phone       = trim(getSetting('company_support_phone', ''));
        $email       = trim(getSetting('company_email', ''));

        if ($companyName !== '') {
            $pdf->SetXY($mL, $y);
            $pdf->SetFont($font, '', 13);
            $pdf->SetTextColor(...$textRgb);
            $pdf->Cell($contW, 7, $companyName, 0, 1, 'C');
            $y = $pdf->GetY() + 2;
        }

        if ($address !== '') {
            $pdf->SetFont($font, '', 11);
            $pdf->SetTextColor(...$textRgb);
            foreach (preg_split('/\r\n|\r|\n|,\s*/', $address) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $pdf->SetX($mL);
                $pdf->Cell($contW, 6.5, $line, 0, 1, 'C');
            }
            $y = $pdf->GetY() + 8;
        }

        if ($phone !== '') {
            $pdf->SetXY($mL, $y);
            $pdf->SetFont($font, '', 11);
            $pdf->SetTextColor(...$textRgb);
            $pdf->Cell($contW, 6.5, 'Mobile: ' . $phone, 0, 1, 'C');
            $y = $pdf->GetY();
        }
        if ($email !== '') {
            $pdf->SetXY($mL, $y);
            $pdf->SetFont($font, '', 11);
            $pdf->SetTextColor(...$textRgb);
            $pdf->Cell($contW, 6.5, 'Email: ' . $email, 0, 1, 'C');
            $y = $pdf->GetY();
        }
        $y += 14;
    }

    // QR code(s) — centered as a group. If both website + gmap are enabled,
    // shown side by side; if only one, it's centered alone (matches the
    // single-QR "Find Us" reference layout).
    $qrSize = 32;
    $qrItems = [];
    if (!empty($closing['website_qr'])) {
        $qrItems[] = ['url' => BASE_URL, 'label' => 'Visit Website'];
    }
    if (!empty($closing['gmap_qr'])) {
        $mapUrl = getSetting('company_location_url', '');
        if ($mapUrl !== '') $qrItems[] = ['url' => $mapUrl, 'label' => 'Find Us'];
    }

    if ($qrItems) {
        $gap = 20;
        $groupW = count($qrItems) * $qrSize + (count($qrItems) - 1) * $gap;
        $qrX = ($pageW - $groupW) / 2;
        foreach ($qrItems as $item) {
            $pdf->write2DBarcode($item['url'], 'QRCODE,M', $qrX, $y, $qrSize, $qrSize, [], 'N');
            $pdf->SetXY($qrX, $y + $qrSize + 3);
            $pdf->SetFont($font, '', 8.5);
            $pdf->SetTextColor(...[130, 130, 130]);
            $pdf->Cell($qrSize, 5, $item['label'], 0, 0, 'C');
            $qrX += $qrSize + $gap;
        }
        $y += $qrSize + 14;
    }

    // Social media / sales team lists — centered, kept optional as before
    if (!empty($closing['social_media']) && !empty($closing['social_links'])) {
        $pdf->SetXY($mL, $y);
        $pdf->SetFont($font, 'B', 10);
        $pdf->SetTextColor(...$navyRgb);
        $pdf->Cell($contW, 6, 'Follow Us', 0, 1, 'C');
        $pdf->SetFont($font, '', 9);
        $pdf->SetTextColor(...$textRgb);
        foreach ($closing['social_links'] as $sl) {
            $pdf->SetX($mL);
            $pdf->Cell($contW, 5.5, ($sl['platform'] ?? '') . ': ' . ($sl['url'] ?? ''), 0, 1, 'C');
        }
        $y = $pdf->GetY() + 6;
    }
    if (!empty($closing['sales_team']) && !empty($closing['sales_team_list'])) {
        $pdf->SetXY($mL, $y);
        $pdf->SetFont($font, 'B', 10);
        $pdf->SetTextColor(...$navyRgb);
        $pdf->Cell($contW, 6, 'Contact Our Team', 0, 1, 'C');
        $pdf->SetFont($font, '', 9);
        $pdf->SetTextColor(...$textRgb);
        foreach ($closing['sales_team_list'] as $member) {
            $line = trim(implode(' · ', array_filter([$member['name'] ?? '', $member['phone'] ?? '', $member['email'] ?? ''])));
            if ($line === '') continue;
            $pdf->SetX($mL);
            $pdf->Cell($contW, 5.5, $line, 0, 1, 'C');
        }
    }
}

// ── Watermark — applied AFTER all pages built, iterate every page ─────
function _cpeApplyWatermarkToAllPages(\TCPDF $pdf, array $config): void {
    $wm = $config['watermark'] ?? [];
    $type = $wm['type'] ?? 'none';
    if ($type === 'none') return;

    $text = match ($type) {
        'confidential' => 'CONFIDENTIAL',
        'sample'       => 'SAMPLE',
        'custom'       => $wm['custom_text'] ?? '',
        default        => '',
    };
    $opacity = max(0, min(100, (int)($wm['opacity'] ?? 15))) / 100;
    $rotation = (float)($wm['rotation'] ?? -45);
    $totalPages = $pdf->getNumPages();
    $pageW = $pdf->getPageWidth(); $pageH = $pdf->getPageHeight();

    for ($p = 1; $p <= $totalPages; $p++) {
        $pdf->setPage($p);
        $pdf->StartTransform();
        $pdf->SetAlpha($opacity);

        if ($type === 'logo') {
    $logo = _cpeResolveLogoImage();
    if ($logo) {
        $size = 80;
        $pdf->Rotate($rotation, $pageW/2, $pageH/2);
        try {
            $pdf->Image($logo['path'], ($pageW-$size)/2, ($pageH-$size)/2, $size, $size, $logo['type'], '', '', true, 150, 'C');
        } catch (\Throwable $e) {}
        @unlink($logo['path']);
    }
}  elseif ($text !== '') {
            $pdf->SetFont($config['_font_family'] ?? 'helvetica', 'B', 60);
            $pdf->SetTextColor(160,160,160);
            $pdf->Rotate($rotation, $pageW/2, $pageH/2);
            $pdf->SetXY(0, $pageH/2 - 15);
            $pdf->Cell($pageW, 20, $text, 0, 0, 'C');
        }

        $pdf->SetAlpha(1);
        $pdf->StopTransform();
    }
}