<?php

define('BASE_PATH', dirname(__DIR__));
// Must be defined before ANY code (including class ... extends \TCPDF
// declarations, which resolve at file-load time, not call time) can
// trigger TCPDF's autoconfig, or PDF generation breaks with
// "unable to read file: helvetica.json".
if (!defined('K_PATH_FONTS')) {
    define('K_PATH_FONTS', BASE_PATH . '/vendor/tecnickcom/tc-lib-pdf-font/target/fonts/');
}
/**
 * includes/catalog_pdf_engine.php
 * Fire 4 — full engine: all 5 layouts, watermark, header/footer, page numbers,
 * quality/compression, orientation, page size, fonts, colors.
 */

// ── Custom TCPDF subclass for header/footer callbacks ──────────────────────
class CatalogTCPDF extends \TCPDF {
    public array $cpeConfig = [];
    public array $cpeCatalog = [];

    public function Header() {
        $h = $this->cpeConfig['header'] ?? [];
        if (empty($h['logo']) && empty($h['catalog_name']) && empty($h['page_title'])) return;

        $pageW = $this->getPageWidth();
        $y = 8;
        if (!empty($h['logo'])) {
    $logo = _cpeResolveLogoImage();
    if ($logo) {
        try {
            $this->Image($logo['path'], 15, $y, 24, 0, $logo['type'], '', '', true, 150, 'L');
        } catch (\Throwable $e) {}
        @unlink($logo['path']);
    }
}
        if (!empty($h['catalog_name'])) {
            $this->SetXY(15, $y + 2);
            $this->SetFont($this->cpeConfig['_font_family'] ?? 'helvetica', 'B', 9);
            $this->SetTextColor(...($this->cpeConfig['_colors_rgb']['text'] ?? [30,30,30]));
            $this->Cell($pageW - 30, 6, $this->cpeCatalog['name'] ?? APP_NAME, 0, 0, 'R');
        }
        $this->SetLineStyle(['width' => 0.2, 'color' => [220,220,220]]);
        $this->Line(15, $y + 10, $pageW - 15, $y + 10);
    }

    public function Footer() {
        $f = $this->cpeConfig['footer'] ?? [];
        $pos = $this->cpeConfig['page_number_position'] ?? 'bottom_center';
        $pageW = $this->getPageWidth();
        $pageH = $this->getPageHeight();
        $y = $pageH - 15;

        $this->SetFont($this->cpeConfig['_font_family'] ?? 'helvetica', '', 8);
        $this->SetTextColor(120,120,120);

        $parts = [];
        if (!empty($f['website'])) $parts[] = BASE_URL;
        if (!empty($f['email']))   $parts[] = getSetting('company_email', '');
        if (!empty($f['phone']))   $parts[] = getSetting('company_support_phone', '');
        if (!empty($f['generated_date'])) $parts[] = date('d M Y');
        $leftText = implode('  ·  ', array_filter($parts));

        if ($leftText !== '') {
            $this->SetXY(15, $y);
            $this->Cell($pageW - 30, 6, $leftText, 0, 0, 'L');
        }

        if (!empty($f['page_number'])) {
            $pageNum = $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages();
            [$x, $y2, $align] = $this->_cpePageNumberPos($pos, $pageW, $pageH);
            $this->SetXY($x, $y2);
            $this->Cell(40, 6, $pageNum, 0, 0, $align);
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
        $pdf->SetCreator(APP_NAME);
        $pdf->SetTitle($cat['name']);

        $headerOn = !empty(array_filter($config['header'] ?? []));
        $footerOn = !empty(array_filter($config['footer'] ?? []));
        $pdf->setPrintHeader($headerOn);
        $pdf->setPrintFooter($footerOn);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        $pdf->SetMargins(15, $headerOn ? 25 : 15, 15);
        $pdf->SetAutoPageBreak(true, $footerOn ? 22 : 15);

        // ── Compression / quality 
        $pdf->SetCompression(($config['quality']['compression'] ?? 'compress') === 'compress');

        // ── Font 
        $fontFamily = _cpeResolveFont($config['font'] ?? 'helvetica');
        $config['_font_family'] = $fontFamily;
        $pdf->cpeConfig = $config;

        // ── Colors (hex → rgb, used by layout renderers + header/footer) ──
        $colorsRgb = [];
        foreach (($config['colors'] ?? []) as $k => $hex) $colorsRgb[$k] = _cpeHexToRgb($hex);
        $config['_colors_rgb'] = $colorsRgb;
        $pdf->cpeConfig = $config;

        _cpeRenderCoverPage($pdf, $cat, $config);

        $layout = $config['layout'] ?? 'one_per_page';
        $gridLayouts = ['two_per_page', 'four_per_page', 'grid'];
        if (in_array($layout, $gridLayouts, true)) {
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
            default:               foreach ($products as $p) _cpeRenderLayoutOne($pdf, $p, $config); break;
        }
        if (in_array($layout, $gridLayouts, true)) {
            $footerOn = !empty(array_filter($config['footer'] ?? []));
            $pdf->SetAutoPageBreak(true, $footerOn ? 22 : 15); // restore for closing page
        }

        if (!empty($config['closing']['enabled'])) {
            _cpeRenderClosingPage($pdf, $config);
        }

        // ── Watermark — applied to ALL pages after content built ─────────
        _cpeApplyWatermarkToAllPages($pdf, $config);

        $safeName = preg_replace('/[^A-Za-z0-9 _\-]/u', '', $cat['name']);
        $safeName = trim(preg_replace('/\s+/', '_', $safeName)) ?: ('catalog_' . $catalogId);
        $filename = $safeName . '_' . time() . '.pdf';
        $path     = CATALOG_PDF_DIR . '/' . $filename;

        $pdfString = $pdf->Output('', 'S');
        if (empty($pdfString)) return ['success' => false, 'error' => 'TCPDF returned empty output.'];
        file_put_contents($path, $pdfString);

        $pages = $pdf->getNumPages();
        $size  = filesize($path);

        $db->prepare("UPDATE catalogs SET status='done', pdf_path=?, pages=?, size_bytes=?, updated_at=? WHERE id=?")
           ->execute([$path, $pages, $size, time(), $catalogId]);

        return ['success' => true, 'path' => $path, 'filename' => $filename, 'pages' => $pages, 'size' => $size];
    } catch (\Throwable $e) {
        error_log('generateCatalogPdf: ' . $e->getMessage());
        $db->prepare("UPDATE catalogs SET status='failed', updated_at=? WHERE id=?")->execute([time(), $catalogId]);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ── Helpers ─────────────────────────────────────────────────────────────

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

    $tmp = sys_get_temp_dir() . '/cpe_logo_' . uniqid() . '.png';

    if ($info[2] === IMAGETYPE_WEBP) {
        if (!function_exists('imagecreatefromwebp')) return null;
        $gd = @imagecreatefromwebp($fullPath);
        if (!$gd) return null;
        imagesavealpha($gd, true);
        imagepng($gd, $tmp);
        imagedestroy($gd);
    } else {
        copy($fullPath, $tmp);
    }

    if (!file_exists($tmp)) return null;
    return ['path' => $tmp, 'type' => 'PNG'];
}

function _cpeResolveFont(string $font): string {
    // Helvetica = TCPDF core font, no embed. Others fall back to helvetica
    // unless embedded TTF font defs exist in storage/fonts/ (add later —
    // safe no-op fallback keeps generation from breaking if fonts missing).
    $map = [
        'helvetica' => 'helvetica', 'arial' => 'helvetica',
        'roboto' => 'helvetica', 'open_sans' => 'helvetica', 'noto_sans' => 'helvetica',
    ];
    $custom = BASE_PATH . '/storage/fonts/' . $font . '.php';
    if (file_exists($custom)) return $font; // embedded font def present
    return $map[$font] ?? 'helvetica';
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

// Copies+optionally downsamples an image to /tmp for TCPDF (path restriction workaround)
function _cpeTempImage(string $fullPath, array $config): ?string {
    $q = _cpeQualityImageParams($config);
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $tmp = sys_get_temp_dir() . '/cpe_' . uniqid() . '.' . ($ext === 'webp' ? 'jpg' : $ext);

    if (empty($config['quality']['optimize_size'])) {
        copy($fullPath, $tmp);
        return $tmp;
    }
    // Optimize: resize to a sane max dimension based on quality level
    $maxDim = match ($q['dpi']) { 72 => 900, 150 => 1400, 300 => 2200, default => 1100 };
    $info = @getimagesize($fullPath);
    if (!$info) { copy($fullPath, $tmp); return $tmp; }
    [$w, $h, $type] = $info;
    if (max($w,$h) <= $maxDim) { copy($fullPath, $tmp); return $tmp; }

    $ratio = $maxDim / max($w,$h);
    $nw = (int)($w * $ratio); $nh = (int)($h * $ratio);
    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($fullPath),
        IMAGETYPE_PNG  => @imagecreatefrompng($fullPath),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($fullPath) : false,
        default        => false,
    };
    if (!$src) { copy($fullPath, $tmp); return $tmp; }
    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $src, 0,0,0,0, $nw,$nh,$w,$h);
    imagejpeg($dst, $tmp, $q['jpq']);
    imagedestroy($src); imagedestroy($dst);
    return $tmp;
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

function _cpeFieldRows(array $p, array $fields): array {
    $labelMap = [
        'category'=>'Stone Type','subcategory'=>'Subcategory','color_subcategory'=>'Color',
        'thickness'=>'Thickness','origin'=>'Origin','finish'=>'Finish',
        'quantity_available'=>'Available Qty','description'=>'Description',
    ];
    $slab = formatDimension($p['sizes_l'] ?? '', $p['sizes_h'] ?? '');
    $cut  = formatDimension($p['cutter_size_l'] ?? '', $p['cutter_size_h'] ?? '');
    $extra = ['sizes' => $slab, 'cutter_size' => $cut];
    $rows = [];
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
    $textRgb = $config['_colors_rgb']['text'] ?? [26,26,26];
    $accentRgb = $config['_colors_rgb']['accent'] ?? [90,90,90];
    $font = $config['_font_family'] ?? 'helvetica';
    $y = 40;

   if (!empty($cover['logo'])) {
    $logo = _cpeResolveLogoImage();
    if ($logo) {
        try {
            $pdf->Image($logo['path'], ($pageW - 50) / 2, $y, 50, 0, $logo['type'], '', '', true, 150, 'C');
            $y += 30;
        } catch (\Throwable $e) {}
        @unlink($logo['path']);
    }
}

    $pdf->SetXY(15, $y + 15);
    $pdf->SetFont($font, 'B', 26);
    $pdf->SetTextColor(...$textRgb);
    $pdf->MultiCell($pageW - 30, 12, $cover['title'] ?? APP_NAME, 0, 'C');
    $y = $pdf->GetY() + 4;

    if (!empty($cover['subtitle'])) {
        $pdf->SetXY(15, $y);
        $pdf->SetFont($font, '', 14);
        $pdf->SetTextColor(...$accentRgb);
        $pdf->MultiCell($pageW - 30, 8, $cover['subtitle'], 0, 'C');
        $y = $pdf->GetY() + 4;
    }

    if (!empty($cover['marketing_message'])) {
        $pdf->SetXY(25, $y + 6);
        $pdf->SetFont($font, 'I', 11);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->MultiCell($pageW - 50, 6, $cover['marketing_message'], 0, 'C');
    }

    $pdf->SetY(-60);
    $pdf->SetFont($font, '', 10);
    $pdf->SetTextColor(60, 60, 60);
    if (!empty($cover['show_date'])) $pdf->Cell($pageW - 30, 6, date($cover['date_format'] ?? 'd M Y'), 0, 1, 'C');
    if (!empty($cover['version']))   $pdf->Cell($pageW - 30, 6, $cover['version'], 0, 1, 'C');
    if (!empty($cover['contact_details'])) {
        $phone = getSetting('company_support_phone', '');
        $email = getSetting('company_email', '');
        $line  = trim(implode('  ·  ', array_filter([$phone ? "Tel: $phone" : '', $email])));
        if ($line) $pdf->Cell($pageW - 30, 6, $line, 0, 1, 'C');
    }
    if (!empty($cover['footer_text'])) {
        $pdf->SetFont($font, 'I', 9);
        $pdf->Cell($pageW - 30, 6, $cover['footer_text'], 0, 1, 'C');
    }
}

// ── Layout 1: one product per page ─────────────────────────────────────
function _cpeRenderLayoutOne(\TCPDF $pdf, array $p, array $config): void {
    $fields = $config['fields'] ?? [];
    $font = $config['_font_family'] ?? 'helvetica';
    $pdf->AddPage();
    $pageW = $pdf->getPageWidth();
    $mL = 15; $contW = $pageW - 30;
    $y = $pdf->GetY();

    $full = _cpeProductPhotoFull($p['id']);
    $rendered = false;
    if ($full && file_exists($full)) {
        $info = @getimagesize($full);
        if ($info) {
            $maxW = $contW; $maxH = 130;
            $ratio = $info[0] / max($info[1], 1);
            $imgW = $maxW; $imgH = $maxW / $ratio;
            if ($imgH > $maxH) { $imgH = $maxH; $imgW = $maxH * $ratio; }
            $imgX = $mL + ($contW - $imgW) / 2;
            $tmp = _cpeTempImage($full, $config);
            if ($tmp) {
                try { $pdf->Image($tmp, $imgX, $y, $imgW, $imgH, '', '', '', true, 150, 'C'); $rendered = true; }
                catch (\Throwable $e) {}
                @unlink($tmp);
            }
            if ($rendered) $y += $imgH + 8;
        }
    }
    if (!$rendered) $y += 20;

    if (in_array('name', $fields, true)) {
        $pdf->SetXY($mL, $y);
        $pdf->SetFont($font, 'B', 18);
        $pdf->SetTextColor(...($config['_colors_rgb']['text'] ?? [20,20,20]));
        $pdf->MultiCell($contW, 9, $p['name'] ?? '', 0, 'L');
        $y = $pdf->GetY() + 2;
    }
    if (in_array('quarry_number', $fields, true) && !empty($p['quarry_number'])) {
        $pdf->SetXY($mL, $y);
        $pdf->SetFont($font, '', 11);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell($contW, 6, 'Quarry No: ' . $p['quarry_number'], 0, 1, 'L');
        $y = $pdf->GetY() + 2;
    }

    $rows = _cpeFieldRows($p, $fields);
    if ($rows) {
        $colL = 55; $colV = $contW - $colL;
        $borderRgb = $config['_colors_rgb']['border'] ?? [220,220,220];
        $pdf->SetY($y + 4);
        foreach ($rows as $row) {
            $pdf->SetX($mL);
            $pdf->SetDrawColor(...$borderRgb);
            $pdf->SetFont($font, 'B', 9);
            $pdf->SetTextColor(80,80,80);
            $pdf->Cell($colL, 7, $row[0], 'B', 0, 'L');
            $pdf->SetFont($font, '', 9);
            $pdf->SetTextColor(...($config['_colors_rgb']['text'] ?? [20,20,20]));
            $pdf->MultiCell($colV, 7, $row[1], 'B', 'L');
        }
    }
}

// ── Layout N: 2 or 4 products per page (shared grid-cell renderer) ─────
// Fixed-height zones per cell: image zone (55%) + name zone (fixed 1 line)
// + detail zone (fixed N lines, clamped) — guarantees no cell ever exceeds
// its allotted cellH, so cells can never bleed into the one below.
function _cpeRenderLayoutN(\TCPDF $pdf, array $products, array $config, int $perPage): void {
    $font = $config['_font_family'] ?? 'helvetica';
    $fields = $config['fields'] ?? [];
    $cols = $perPage === 2 ? 1 : 2;
    $rowsPerPage = 2;
    $pageW = $pdf->getPageWidth();
    $pageH = $pdf->getPageHeight();
    $mL = 15; $mT = $pdf->getMargins()['top']; $mB = $pdf->getMargins()['bottom'];
    $usableW = $pageW - 30;
    $usableH = $pageH - $mT - $mB;
    $cellW = $usableW / $cols;
    $cellH = $usableH / $rowsPerPage;
    $pad = 8;
    $innerW = $cellW - ($pad * 2);

    $nameSize = $perPage === 2 ? 13 : 10;
    $detailSize = $perPage === 2 ? 9 : 7.5;
    $detailLineH = $perPage === 2 ? 5 : 4.2;
    $maxDetailLines = $perPage === 2 ? 4 : 2;
    $nameLineH = $perPage === 2 ? 7 : 5.5;

    $imgH = $cellH * 0.48;
    $nameZoneH = $nameLineH;
    $detailZoneH = $maxDetailLines * $detailLineH;
    // Guard: if computed zones exceed cell (very small custom page sizes), shrink image zone
    $usedH = $imgH + $nameZoneH + $detailZoneH + ($pad * 2);
    if ($usedH > $cellH) $imgH = max(20, $cellH - $nameZoneH - $detailZoneH - ($pad * 2));

    $i = 0;
    foreach ($products as $p) {
        $slot = $i % $perPage;
        if ($slot === 0) $pdf->AddPage();
        $col = $slot % $cols; $row = intdiv($slot, $cols);
        $x = $mL + $col * $cellW; $y = $mT + $row * $cellH;

        $pdf->SetDrawColor(...($config['_colors_rgb']['border'] ?? [225,225,225]));
        $pdf->Rect($x + 3, $y + 3, $cellW - 6, $cellH - 6, 'D');

        $full = _cpeProductPhotoFull($p['id']);
        if ($full && file_exists($full)) {
            $info = @getimagesize($full);
            if ($info) {
                $ratio = $info[0]/max($info[1],1);
                $imgW = min($innerW, $imgH * $ratio);
                $tmp = _cpeTempImage($full, $config);
                if ($tmp) {
                    try { $pdf->Image($tmp, $x + ($cellW-$imgW)/2, $y + $pad, $imgW, $imgH, '', '', '', true, 150, 'C'); } catch (\Throwable $e) {}
                    @unlink($tmp);
                }
            }
        }

        $ty = $y + $pad + $imgH + 4;

        // Name — clamp to single line, ellipsis if too long
        $nameTxt = _cpeClampText($pdf, $p['name'] ?? '', $innerW, $font, 'B', $nameSize);
        $pdf->SetXY($x + $pad, $ty);
        $pdf->SetFont($font, 'B', $nameSize);
        $pdf->SetTextColor(...($config['_colors_rgb']['text'] ?? [20,20,20]));
        $pdf->Cell($innerW, $nameZoneH, $nameTxt, 0, 0, 'L');
        $ty += $nameZoneH + 1;

        // Detail lines — one field per line, clamped, capped at $maxDetailLines
        $rows = _cpeFieldRows($p, array_diff($fields, ['name']));
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
    $mL = 15; $mT = $pdf->getMargins()['top']; $mB = $pdf->getMargins()['bottom'];
    $cellW = ($pageW - 30) / $cols;
    $cellH = ($pageH - $mT - $mB) / $rowsPerPage;
    $innerW = $cellW - 6;

    $i = 0;
    foreach ($products as $p) {
        $slot = $i % $perPage;
        if ($slot === 0) $pdf->AddPage();
        $col = $slot % $cols; $row = intdiv($slot, $cols);
        $x = $mL + $col * $cellW; $y = $mT + $row * $cellH;

        $nameH = 4; $lotH = 4;
        $imgSize = min($cellW, $cellH - $nameH - $lotH - 12) - 14;
        $imgSize = max(20, $imgSize);

        $full = _cpeProductPhotoFull($p['id']);
        if ($full && file_exists($full)) {
            $tmp = _cpeTempImage($full, $config);
            if ($tmp) {
                try { $pdf->Image($tmp, $x + (($cellW-$imgSize)/2), $y + 4, $imgSize, $imgSize, '', '', '', true, 150, 'C'); } catch (\Throwable $e) {}
                @unlink($tmp);
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
                @unlink($tmp);
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
function _cpeRenderClosingPage(\TCPDF $pdf, array $config): void {
    $closing = $config['closing'] ?? [];
    $font = $config['_font_family'] ?? 'helvetica';
    $pdf->AddPage();
    $pageW = $pdf->getPageWidth();
    $y = 50;

    $pdf->SetXY(15, $y);
    $pdf->SetFont($font, 'B', 20);
    $pdf->SetTextColor(...($config['_colors_rgb']['text'] ?? [20,20,20]));
    $pdf->MultiCell($pageW - 30, 10, $closing['thank_you_text'] ?? ('Thank you for choosing ' . APP_NAME), 0, 'C');
    $y = $pdf->GetY() + 10;

    if (!empty($closing['contact_info'])) {
        $pdf->SetXY(15, $y);
        $pdf->SetFont($font, '', 11);
        $pdf->SetTextColor(80,80,80);
        $addr  = getSetting('company_address', '');
        $phone = getSetting('company_support_phone', '');
        $email = getSetting('company_email', '');
        foreach (array_filter([$addr, $phone ? "Tel: $phone" : '', $email]) as $line) {
            $pdf->Cell($pageW - 30, 7, $line, 0, 1, 'C');
        }
        $y = $pdf->GetY() + 6;
    }

    // QR codes (website / gmap) — TCPDF built-in 2D barcode, no external lib needed
    $qrY = $y;
    $qrSize = 30;
    $qrX = 15;
    if (!empty($closing['website_qr'])) {
        $pdf->write2DBarcode(BASE_URL, 'QRCODE,M', $qrX, $qrY, $qrSize, $qrSize, [], 'N');
        $pdf->SetXY($qrX, $qrY + $qrSize + 1);
        $pdf->SetFont($font, '', 7);
        $pdf->Cell($qrSize, 4, 'Visit Website', 0, 0, 'C');
        $qrX += $qrSize + 15;
    }
    if (!empty($closing['gmap_qr'])) {
        $mapUrl = getSetting('company_location_url', '');
        if ($mapUrl) {
            $pdf->write2DBarcode($mapUrl, 'QRCODE,M', $qrX, $qrY, $qrSize, $qrSize, [], 'N');
            $pdf->SetXY($qrX, $qrY + $qrSize + 1);
            $pdf->SetFont($font, '', 7);
            $pdf->Cell($qrSize, 4, 'Find Us', 0, 0, 'C');
        }
    }

    // Social media / sales team lists (simple text rows)
    $y2 = $qrY + $qrSize + 12;
    if (!empty($closing['social_media']) && !empty($closing['social_links'])) {
        $pdf->SetXY(15, $y2);
        $pdf->SetFont($font, 'B', 9);
        $pdf->Cell($pageW-30, 6, 'Follow Us', 0, 1, 'C');
        $pdf->SetFont($font, '', 8);
        foreach ($closing['social_links'] as $sl) {
            $pdf->Cell($pageW-30, 5, ($sl['platform'] ?? '') . ': ' . ($sl['url'] ?? ''), 0, 1, 'C');
        }
        $y2 = $pdf->GetY() + 4;
    }
    if (!empty($closing['sales_team']) && !empty($closing['sales_team_list'])) {
        $pdf->SetXY(15, $y2);
        $pdf->SetFont($font, 'B', 9);
        $pdf->Cell($pageW-30, 6, 'Contact Our Team', 0, 1, 'C');
        $pdf->SetFont($font, '', 8);
        foreach ($closing['sales_team_list'] as $member) {
            $line = trim(implode(' · ', array_filter([$member['name'] ?? '', $member['phone'] ?? '', $member['email'] ?? ''])));
            if ($line) $pdf->Cell($pageW-30, 5, $line, 0, 1, 'C');
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