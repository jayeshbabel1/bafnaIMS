<?php

/**
 * Product PDF Generator
 * Thin wrapper around the Catalog PDF engine (includes/catalog_pdf.php +
 * includes/catalog_pdf_engine.php). A single-product PDF is just a
 * one-product "catalog" — cover page, one product detail page, closing
 * page — rendered through the exact same TCPDF pipeline used for
 * multi-product catalogs and client-selection catalogs. This removes the
 * previous parallel/duplicate PDF-building implementation entirely.
 */

defined('PDF_TEMP_DIR') || define('PDF_TEMP_DIR', BASE_PATH . '/storage/pdfs');
defined('PDF_TEMP_URL') || define('PDF_TEMP_URL', BASE_URL . '/storage/pdfs');
defined('PDF_MAX_AGE')  || define('PDF_MAX_AGE', 3600);

/**
 * --------------------------------------------------------------------------
 * Public, auto-expiring directory for shareable/downloadable copies.
 *
 * The catalog engine itself writes into CATALOG_PDF_DIR, which is private
 * and only served through the authenticated ?catalog_download= route.
 * Product PDFs are also shared via WhatsApp links that must open for a
 * logged-out recipient, so a copy is placed here — publicly servable
 * (see .htaccess below) and self-cleaning after PDF_MAX_AGE.
 * --------------------------------------------------------------------------
 */
function ensureProductPdfDirectory(): void
{
    if (!is_dir(PDF_TEMP_DIR)) {
        if (!@mkdir(PDF_TEMP_DIR, 0755, true) && !is_dir(PDF_TEMP_DIR)) {
            throw new RuntimeException('Cannot create PDF directory: ' . PDF_TEMP_DIR);
        }
    }
    if (!is_writable(PDF_TEMP_DIR)) {
        throw new RuntimeException('PDF directory is not writable: ' . PDF_TEMP_DIR);
    }
    $htaccess = PDF_TEMP_DIR . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Options -Indexes\nAllow from all\n");
    }
}

/**
 * --------------------------------------------------------------------------
 * Public entry point — generates a single-product PDF.
 *
 * Reuses the Catalog PDF engine end to end:
 *   1. Build a single-product catalog config on top of the admin's saved
 *      Catalog PDF defaults (getCatalogPdfSettingsDefaults()).
 *   2. Create a one-off draft row (createCatalogDraft()).
 *   3. Render it (generateCatalogPdf()) — cover page, one product page,
 *      closing page, watermark, header/footer: all inherited for free.
 *   4. Copy the rendered file into the public temp dir for download/share.
 *   5. Discard the draft catalog row (deleteCatalog()) so single-product
 *      downloads never show up in Catalog PDF History.
 *
 * @param int      $productId
 * @param int|null $userId   Pass when called from the user panel.
 * @param int|null $adminId  Pass when called from the admin panel.
 * --------------------------------------------------------------------------
 */
function generateProductPdf(int $productId, ?int $userId = null, ?int $adminId = null): array
{
    try {
        if ($productId <= 0) {
            return ['success' => false, 'error' => 'Invalid product ID.'];
        }

        require_once BASE_PATH . '/includes/catalog_pdf.php';
        require_once BASE_PATH . '/includes/catalog_pdf_engine.php';

        ensureProductPdfDirectory();
        cleanOldProductPdfs();

        $db = getDB();
        $st = $db->prepare("SELECT id, name FROM products WHERE id = ? LIMIT 1");
        $st->execute([$productId]);
        $product = $st->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            return ['success' => false, 'error' => 'Product not found.'];
        }

        // ── Single-product config: cover -> product detail -> closing ──────
        $config = getCatalogPdfSettingsDefaults();
        $config['layout'] = 'one_per_page';
        foreach ([
            'name', 'quarry_number', 'category', 'color_subcategory', 'thickness',
            'sizes', 'cutter_size', 'pieces', 'quantity_available', 'quantity_on_hold',
            'origin', 'finish',
        ] as $f) {
            if (!in_array($f, $config['fields'], true)) $config['fields'][] = $f;
        }
        $config['cover']['title']        = $product['name'];
        $config['cover']['label']        = 'Product Data Sheet';
        $config['cover']['prepared_for'] = '';
        $config['cover']['version']      = '';
        $config['closing']['enabled']    = 1;
        $config['_source'] = ['type' => 'single_product', 'product_id' => $productId];

        $draft = createCatalogDraft([
            'name'        => $product['name'] . ' — Product Sheet',
            'user_id'     => $userId,
            'admin_id'    => $adminId,
            'product_ids' => [$productId],
            'config'      => $config,
        ]);
        if (empty($draft['success'])) {
            return ['success' => false, 'error' => 'Could not create PDF draft.'];
        }
        $catalogId = (int)$draft['id'];

        $result = generateCatalogPdf($catalogId);
        if (empty($result['success'])) {
            deleteCatalog($catalogId);
            return ['success' => false, 'error' => $result['error'] ?? 'PDF generation failed.'];
        }

        $safeName = preg_replace('/[^A-Za-z0-9 _\-]/u', '', $product['name']);
        $safeName = trim(preg_replace('/\s+/', '_', $safeName));
        if ($safeName === '') $safeName = 'product_' . $productId;

        $uniqueName = date('Ymd_His') . '_' . $productId . '_' . $safeName . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $publicPath = PDF_TEMP_DIR . '/' . $uniqueName;

        if (!@copy($result['path'], $publicPath)) {
            deleteCatalog($catalogId);
            throw new RuntimeException('Could not prepare PDF for download.');
        }

        // Draft only existed to render this one-off sheet — remove it (and
        // its private copy in storage/catalogs) now that the shareable
        // public copy above exists.
        deleteCatalog($catalogId);

        return [
            'success'  => true,
            'path'     => $publicPath,
            'url'      => PDF_TEMP_URL . '/' . $uniqueName,
            'filename' => $uniqueName,
        ];

    } catch (Throwable $e) {
        error_log('generateProductPdf ERROR: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * --------------------------------------------------------------------------
 * Cleanup — removes shareable copies older than PDF_MAX_AGE.
 * --------------------------------------------------------------------------
 */
function cleanOldProductPdfs(int $maxAge = PDF_MAX_AGE): int
{
    $deleted = 0;
    if (!is_dir(PDF_TEMP_DIR)) return 0;

    foreach (glob(PDF_TEMP_DIR . '/*.pdf') ?: [] as $file) {
        if (is_file($file) && (time() - filemtime($file)) > $maxAge) {
            if (@unlink($file)) $deleted++;
        }
    }

    return $deleted;
}

/**
 * --------------------------------------------------------------------------
 * WhatsApp AJAX endpoint — shared by admin panel and user panel.
 * Detects which panel is calling from the active session and tags the
 * ephemeral catalog draft accordingly (cosmetic only — draft is deleted
 * immediately after rendering).
 * --------------------------------------------------------------------------
 */
function handleWaPdfAjax(): void
{
    $productId = (int)($_GET['product_id'] ?? 0);

    header('Content-Type: application/json; charset=utf-8');

    if ($productId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing product_id'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $userId  = (isLoggedIn() && !empty($_SESSION['user_id']))  ? (int)$_SESSION['user_id']  : null;
    $adminId = (isAdmin()    && !empty($_SESSION['admin_id'])) ? (int)$_SESSION['admin_id'] : null;

    $result = generateProductPdf($productId, $userId, $adminId);

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}