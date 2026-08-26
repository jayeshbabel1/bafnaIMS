<?php

/**
 * Product PDF Generator
 * TCPDF + tc-lib-pdf-font
 */

defined('PDF_TEMP_DIR') || define(
    'PDF_TEMP_DIR',
    BASE_PATH . '/storage/pdfs'
);

defined('PDF_TEMP_URL') || define(
    'PDF_TEMP_URL',
    BASE_URL . '/storage/pdfs'
);

defined('PDF_MAX_AGE') || define(
    'PDF_MAX_AGE',
    3600
);

/**
 * --------------------------------------------------------------------------
 * PDF directory
 * --------------------------------------------------------------------------
 */
function ensureProductPdfDirectory(): void
{
    if (!is_dir(PDF_TEMP_DIR)) {
        if (!@mkdir(PDF_TEMP_DIR, 0755, true) && !is_dir(PDF_TEMP_DIR)) {
            throw new RuntimeException(
                'Cannot create PDF directory: ' . PDF_TEMP_DIR
            );
        }
    }

    if (!is_writable(PDF_TEMP_DIR)) {
        throw new RuntimeException(
            'PDF directory is not writable: ' . PDF_TEMP_DIR
        );
    }

    $htaccess = PDF_TEMP_DIR . '/.htaccess';

    if (!file_exists($htaccess)) {
        @file_put_contents(
            $htaccess,
            "Options -Indexes\nAllow from all\n"
        );
    }
}


/**
 * --------------------------------------------------------------------------
 * Public entry point
 * --------------------------------------------------------------------------
 */
function generateProductPdf(int $productId): array
{
    try {

        if ($productId <= 0) {
            return [
                'success' => false,
                'error'   => 'Invalid product ID.'
            ];
        }

        ensureProductPdfDirectory();

        cleanOldProductPdfs();

        $db = getDB();

        /**
         * Product
         */
        $stmt = $db->prepare(
            "SELECT *
             FROM products
             WHERE id = ?
             LIMIT 1"
        );

        $stmt->execute([$productId]);

        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return [
                'success' => false,
                'error'   => 'Product not found.'
            ];
        }

        /**
         * Primary product photo
         */
        $photoPath = resolveProductPhotoPath(
            $db,
            $productId
        );

        /**
         * Display dimensions
         */
        $sizesDisplay = formatDimension(
            $product['sizes_l'] ?? '',
            $product['sizes_h'] ?? ''
        );

        $italianDisplay = formatDimension(
            $product['cutter_size_l'] ?? '',
            $product['cutter_size_h'] ?? ''
        );

        /**
         * Logo
         */
        $logoPath = resolveProductPdfLogo();

        /**
         * Safe filename
         */
        $rawName = trim((string)($product['name'] ?? ''));

        $safeName = preg_replace(
            '/[^A-Za-z0-9 _\-]/u',
            '',
            $rawName
        );

        $safeName = trim(
            preg_replace('/\s+/', '_', $safeName)
        );

        if ($safeName === '') {
            $safeName = 'product_' . $productId;
        }

        /**
         * Unique filename.
         *
         * Use the same filename for both path and URL.
         */
        $uniqueName = date('Ymd_His') .
            '_' .
            $productId .
            '_' .
            $safeName .
            '_' .
            bin2hex(random_bytes(4)) .
            '.pdf';

        $pdfPath = PDF_TEMP_DIR . '/' . $uniqueName;
        $pdfUrl  = PDF_TEMP_URL . '/' . $uniqueName;

        /**
         * Build PDF
         */
        buildProductPdf(
            $product,
            $photoPath,
            $sizesDisplay,
            $italianDisplay,
            $logoPath,
            $pdfPath
        );

        if (!file_exists($pdfPath)) {
            throw new RuntimeException(
                'PDF was not created.'
            );
        }

        if (filesize($pdfPath) <= 0) {
            throw new RuntimeException(
                'Generated PDF is empty.'
            );
        }

        return [
            'success'  => true,
            'path'     => $pdfPath,
            'url'      => $pdfUrl,
            'filename' => $uniqueName
        ];

    } catch (Throwable $e) {

        error_log(
            'generateProductPdf ERROR: ' .
            $e->getMessage() .
            "\n" .
            $e->getTraceAsString()
        );

        return [
            'success' => false,
            'error'   => $e->getMessage()
        ];
    }
}


/**
 * --------------------------------------------------------------------------
 * Resolve product photo
 * --------------------------------------------------------------------------
 */
function resolveProductPhotoPath(
    PDO $db,
    int $productId
): ?string {

    try {

        $stmt = $db->prepare(
            "SELECT filename
             FROM product_photos
             WHERE product_id = ?
             ORDER BY sort_order ASC, id ASC
             LIMIT 1"
        );

        $stmt->execute([$productId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['filename'])) {
            error_log(
                'PDF: No product photo found for product ' .
                $productId
            );

            return null;
        }

        $filename = trim((string)$row['filename']);

        error_log(
            'PDF: Product photo DB filename: ' .
            $filename
        );

        /**
         * 1. resolvePhotoPath()
         */
        if (function_exists('resolvePhotoPath')) {

            try {

                $resolved = resolvePhotoPath(
                    PHOTOS_DIR,
                    $filename
                );

                if ($resolved) {

                    $candidate = PHOTOS_DIR .
                        '/' .
                        ltrim($resolved, '/');

                    if (is_file($candidate)) {

                        $real = realpath($candidate);

                        if ($real) {
                            error_log(
                                'PDF: Photo resolved: ' .
                                $real
                            );

                            return $real;
                        }
                    }
                }

            } catch (Throwable $e) {

                error_log(
                    'PDF: resolvePhotoPath failed: ' .
                    $e->getMessage()
                );
            }
        }

        /**
         * 2. Direct path
         */
        $candidate = PHOTOS_DIR .
            '/' .
            ltrim($filename, '/');

        if (is_file($candidate)) {

            $real = realpath($candidate);

            if ($real) {
                error_log(
                    'PDF: Photo direct path: ' .
                    $real
                );

                return $real;
            }
        }

        /**
         * 3. Basename
         */
        $basename = basename($filename);

        $candidate = PHOTOS_DIR .
            '/' .
            $basename;

        if (is_file($candidate)) {

            $real = realpath($candidate);

            if ($real) {
                error_log(
                    'PDF: Photo basename path: ' .
                    $real
                );

                return $real;
            }
        }

        /**
         * 4. Recursive search
         */
        if (is_dir(PHOTOS_DIR)) {

            try {

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        PHOTOS_DIR,
                        FilesystemIterator::SKIP_DOTS
                    )
                );

                foreach ($iterator as $file) {

                    if (
                        $file->isFile() &&
                        strcasecmp(
                            $file->getFilename(),
                            $basename
                        ) === 0
                    ) {

                        $real = $file->getRealPath();

                        if ($real) {

                            error_log(
                                'PDF: Photo found by scan: ' .
                                $real
                            );

                            return $real;
                        }
                    }
                }

            } catch (Throwable $e) {

                error_log(
                    'PDF: Photo scan failed: ' .
                    $e->getMessage()
                );
            }
        }

        error_log(
            'PDF: Product photo NOT FOUND: ' .
            $filename
        );

    } catch (Throwable $e) {

        error_log(
            'PDF: Photo lookup error: ' .
            $e->getMessage()
        );
    }

    return null;
}


/**
 * --------------------------------------------------------------------------
 * Logo
 * --------------------------------------------------------------------------
 */
function resolveProductPdfLogo(): ?string
{
    try {

        $logoFile = getSetting(
            'site_logo',
            ''
        );

        if (!$logoFile) {
            return null;
        }

        $path = BASE_PATH .
            '/uploads/logo/' .
            basename($logoFile);

        if (is_file($path)) {

            $real = realpath($path);

            if ($real) {
                return $real;
            }
        }

    } catch (Throwable $e) {

        error_log(
            'PDF: Logo error: ' .
            $e->getMessage()
        );
    }

    return null;
}


/**
 * --------------------------------------------------------------------------
 * Build PDF
 * --------------------------------------------------------------------------
 */
function buildProductPdf(
    array $product,
    ?string $photoPath,
    string $sizesDisplay,
    string $italianDisplay,
    ?string $logoPath,
    string $pdfPath
): void {

    /**
     * Composer
     */
    $autoload = BASE_PATH .
        '/vendor/autoload.php';

    if (!is_file($autoload)) {
        throw new RuntimeException(
            'vendor/autoload.php not found.'
        );
    }

    require_once $autoload;


    /**
     * IMPORTANT:
     *
     * This is the font directory generated by:
     *
     * tc-lib-pdf-font
     */
    if (!defined('K_PATH_FONTS')) {

        define(
            'K_PATH_FONTS',
            BASE_PATH .
            '/vendor/tecnickcom/tc-lib-pdf-font/target/fonts/'
        );
    }


    /**
     * TCPDF
     */
    if (!class_exists('TCPDF')) {

        throw new RuntimeException(
            'TCPDF class not found. ' .
            'Run: composer require tecnickcom/tcpdf'
        );
    }


    /**
     * Check font directory
     */
    if (!is_dir(K_PATH_FONTS)) {

        throw new RuntimeException(
            'TCPDF font directory not found: ' .
            K_PATH_FONTS
        );
    }


    /**
     * Create PDF
     */
    $pdf = new TCPDF(
        'P',
        'mm',
        'A4',
        true,
        'UTF-8',
        false
    );


    $pdf->SetCreator(APP_NAME);

    $pdf->SetAuthor(APP_NAME);

    $pdf->SetTitle(
        ($product['name'] ?? 'Product') .
        ' - ' .
        APP_NAME
    );

    $pdf->SetSubject(
        'Product Information'
    );

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    $pdf->SetMargins(
        15,
        15,
        15
    );

    $pdf->SetAutoPageBreak(
        true,
        20
    );

    $pdf->AddPage();


    /**
     * Page measurements
     */
    $pageW = $pdf->getPageWidth();

    $mL = 15;
    $mR = 15;

    $contW = $pageW -
        $mL -
        $mR;


    /**
     * Colours
     */
    $black = [26, 26, 26];
    $mid   = [100, 100, 100];
    $light = [200, 200, 200];
    $white = [255, 255, 255];
    $altRow = [248, 248, 248];


    $y = 15;


    /**
     * ==============================================================
     * HEADER
     * ==============================================================
     */

    $logoW = 44;
    $logoH = 14;


    if ($logoPath && is_file($logoPath)) {

        try {

            $pdf->Image(
                $logoPath,
                $mL,
                $y,
                $logoW,
                $logoH,
                '',
                '',
                '',
                true,
                150,
                '',
                false,
                false,
                0,
                'CM'
            );

        } catch (Throwable $e) {

            error_log(
                'PDF: Logo rendering failed: ' .
                $e->getMessage()
            );

            drawPdfTextLogo(
                $pdf,
                $mL,
                $y,
                $logoW,
                $black
            );
        }

    } else {

        drawPdfTextLogo(
            $pdf,
            $mL,
            $y,
            $logoW,
            $black
        );
    }


    /**
     * Date
     */
    $pdf->SetFont(
        'helvetica',
        '',
        9
    );

    $pdf->SetTextColor(
        ...$mid
    );

    $pdf->SetXY(
        $mL,
        $y + 4
    );

    $pdf->Cell(
        $contW,
        6,
        date('d-m-Y h:i A'),
        0,
        0,
        'R'
    );


    $y += $logoH + 4;


    /**
     * Header divider
     */
    $pdf->SetDrawColor(
        ...$black
    );

    $pdf->SetLineWidth(
        0.5
    );

    $pdf->Line(
        $mL,
        $y,
        $mL + $contW,
        $y
    );

    $y += 6;


    /**
     * ==============================================================
     * PRODUCT NAME
     * ==============================================================
     */

    $pdf->SetXY(
        $mL,
        $y
    );

    $pdf->SetFont(
        'helvetica',
        'B',
        18
    );

    $pdf->SetTextColor(
        ...$black
    );

    $pdf->MultiCell(
        $contW,
        9,
        (string)($product['name'] ?? ''),
        0,
        'C',
        false,
        1
    );

    $y = $pdf->GetY() + 2;


    /**
     * Quarry
     */
    $pdf->SetXY(
        $mL,
        $y
    );

    $pdf->SetFont(
        'helvetica',
        '',
        11
    );

    $pdf->SetTextColor(
        ...$mid
    );

    $pdf->Cell(
        $contW,
        6,
        'Quarry No: ' .
        ($product['quarry_number'] ?? '—'),
        0,
        1,
        'C'
    );

    $y = $pdf->GetY() + 4;


    /**
     * ==============================================================
     * PRODUCT IMAGE
     * ==============================================================
     */

    $imageRendered = false;


   if (
    $photoPath &&
    is_file($photoPath) &&
    is_readable($photoPath)
) {

    // Normalize JPEG EXIF orientation
    $renderPath = normalizeProductImage($photoPath);

    if (!$renderPath) {
        $renderPath = $photoPath;
    }

    $info = @getimagesize($renderPath);

    if (
        $info &&
        !empty($info[0]) &&
        !empty($info[1])
    ) {

        $imgType = getTcpdfImageType(
            $info,
            $renderPath
        );

        $maxW = 130;
        $maxH = 85;

        $ratio =
            $info[0] /
            max($info[1], 1);

        $imgW = $maxW;
        $imgH = $maxW / $ratio;

        if ($imgH > $maxH) {

            $imgH = $maxH;
            $imgW = $maxH * $ratio;
        }

        $imgX =
            $mL +
            ($contW - $imgW) / 2;

        // Border
        $pdf->SetDrawColor(
            ...$light
        );

        $pdf->SetLineWidth(0.3);

        $pdf->RoundedRect(
            $imgX - 1,
            $y - 1,
            $imgW + 2,
            $imgH + 2,
            2,
            '1111',
            'D'
        );

        try {

            $pdf->Image(
                $renderPath,
                $imgX,
                $y,
                $imgW,
                $imgH,
                $imgType,
                '',
                '',
                true,
                150,
                '',
                false,
                false,
                0,
                'CM',
                false,
                false
            );

            $y += $imgH + 7;

            $imageRendered = true;

        } catch (Throwable $e) {

            error_log(
                'PDF image error: ' .
                $e->getMessage()
            );

        } finally {

            // Delete normalized temporary image
            if (
                $renderPath !== $photoPath &&
                is_file($renderPath)
            ) {
                @unlink($renderPath);
            }
        }
    }
}


    /**
     * No image
     */
    if (!$imageRendered) {

        $y = drawNoImagePlaceholder(
            $pdf,
            $mL,
            $contW,
            $y
        );
    }


    /**
     * ==============================================================
     * PRODUCT DETAILS TABLE
     * ==============================================================
     */

    $colL = 72;

    $colV =
        $contW -
        $colL;


    $rows = [

        [
            'Product Name',
            $product['name'] ?? ''
        ],

        [
            'Quarry No',
            $product['quarry_number'] ?? ''
        ],

        [
            'Stone Type',
            $product['category'] ?? ''
        ],

        [
            'Color',
            $product['color_subcategory'] ?? ''
        ],

        [
            'Thickness',
            $product['thickness'] ?? ''
        ],

        [
            'Usable Size',
            $sizesDisplay
        ],

        [
            'Italian Size',
            $italianDisplay
        ],

        [
            'No. of Pieces',
            ((int)($product['pieces'] ?? 0) > 0)
                ? (string)(int)$product['pieces']
                : ''
        ],

        [
            'Available Qty',
            ((float)($product['quantity_available'] ?? 0) > 0)
                ? number_format(
                    (float)$product['quantity_available'],
                    0
                ) . ' sq.ft.'
                : ''
        ],

        [
            'On Hold',
            ((float)($product['quantity_on_hold'] ?? 0) > 0)
                ? number_format(
                    (float)$product['quantity_on_hold'],
                    0
                ) . ' sq.ft.'
                : ''
        ],

        [
            'Origin',
            $product['origin'] ?? ''
        ],

        [
            'Finish',
            $product['finish'] ?? ''
        ]

    ];


    /**
     * Remove empty rows
     */
    $rows = array_values(
        array_filter(
            $rows,
            static function ($row) {
                return trim(
                    (string)$row[1]
                ) !== '';
            }
        )
    );


    $pdf->SetXY(
        $mL,
        $y
    );


    /**
     * Table header
     */
    $pdf->SetFillColor(
        ...$black
    );

    $pdf->SetTextColor(
        ...$white
    );

    $pdf->SetFont(
        'helvetica',
        'B',
        9
    );

    $pdf->SetDrawColor(
        ...$light
    );

    $pdf->SetLineWidth(
        0.2
    );


    $pdf->Cell(
        $colL,
        7,
        'Field',
        'B',
        0,
        'L',
        true
    );

    $pdf->Cell(
        $colV,
        7,
        'Value',
        'B',
        1,
        'L',
        true
    );


    /**
     * Table rows
     */
    $alternate = false;


    foreach ($rows as $row) {

        $pdf->SetFillColor(
            ...(
                $alternate
                    ? $altRow
                    : $white
            )
        );

        $pdf->SetTextColor(
            ...$black
        );


        /**
         * Field
         */
        $pdf->SetFont(
            'helvetica',
            'B',
            9
        );

        $pdf->Cell(
            $colL,
            6.5,
            (string)$row[0],
            'B',
            0,
            'L',
            true
        );


        /**
         * Value
         */
        $pdf->SetFont(
            'helvetica',
            '',
            9
        );

        $pdf->Cell(
            $colV,
            6.5,
            (string)$row[1],
            'B',
            1,
            'L',
            true
        );


        $alternate = !$alternate;
    }


    /**
     * ==============================================================
     * FOOTER
     * ==============================================================
     */

    $y =
        $pdf->GetY() +
        8;


    $pdf->SetDrawColor(
        ...$black
    );

    $pdf->SetLineWidth(
        0.4
    );

    $pdf->Line(
        $mL,
        $y,
        $mL + $contW,
        $y
    );


    $y += 4;


    $pdf->SetXY(
        $mL,
        $y
    );

    $pdf->SetFont(
        'helvetica',
        'B',
        9
    );

    $pdf->SetTextColor(
        ...$black
    );

    $pdf->Cell(
        $contW,
        5,
        APP_NAME,
        0,
        1,
        'C'
    );


    $pdf->SetFont(
        'helvetica',
        'I',
        8
    );

    $pdf->SetTextColor(
        ...$mid
    );

    $pdf->Cell(
        $contW,
        5,
        'Regards, ' . APP_NAME,
        0,
        0,
        'C'
    );


    /**
     * ==============================================================
     * SAVE PDF
     * ==============================================================
     */

    $pdfData = $pdf->Output(
        '',
        'S'
    );


    if (
        !is_string($pdfData) ||
        $pdfData === ''
    ) {

        throw new RuntimeException(
            'TCPDF returned an empty PDF.'
        );
    }


    $written = @file_put_contents(
        $pdfPath,
        $pdfData,
        LOCK_EX
    );


    if (
        $written === false ||
        $written <= 0
    ) {

        throw new RuntimeException(
            'Unable to write PDF: ' .
            $pdfPath
        );
    }
}


/**
 * --------------------------------------------------------------------------
 * TCPDF image type
 * --------------------------------------------------------------------------
 */
function getTcpdfImageType(
    array $info,
    string $path
): string {

    switch ($info[2] ?? 0) {

        case IMAGETYPE_JPEG:
            return 'JPEG';

        case IMAGETYPE_PNG:
            return 'PNG';

        case IMAGETYPE_GIF:
            return 'GIF';

        case IMAGETYPE_WEBP:
            /**
             * TCPDF installations can differ in WEBP support.
             *
             * If WEBP causes an error on your server,
             * convert WEBP to JPG before Image().
             */
            return 'WEBP';

        default:

            $ext = strtoupper(
                pathinfo(
                    $path,
                    PATHINFO_EXTENSION
                )
            );

            return in_array(
                $ext,
                [
                    'JPG',
                    'JPEG',
                    'PNG',
                    'GIF',
                    'WEBP'
                ],
                true
            )
                ? $ext
                : 'JPEG';
    }
}


/**
 * --------------------------------------------------------------------------
 * Text logo fallback
 * --------------------------------------------------------------------------
 */
function drawPdfTextLogo(
    TCPDF $pdf,
    float $x,
    float $y,
    float $width,
    array $black
): void {

    $pdf->SetXY(
        $x,
        $y + 3
    );

    $pdf->SetFont(
        'helvetica',
        'B',
        12
    );

    $pdf->SetTextColor(
        ...$black
    );

    $pdf->Cell(
        $width,
        8,
        APP_NAME,
        0,
        0,
        'L'
    );
}


/**
 * --------------------------------------------------------------------------
 * No image placeholder
 * --------------------------------------------------------------------------
 */
function drawNoImagePlaceholder(
    TCPDF $pdf,
    float $mL,
    float $contW,
    float $y
): float {

    $ph = 28;

    $pdf->SetFillColor(
        240,
        240,
        240
    );

    $pdf->SetDrawColor(
        200,
        200,
        200
    );

    $pdf->SetLineWidth(
        0.3
    );


    $boxW = 80;

    $cx =
        $mL +
        ($contW - $boxW) / 2;


    $pdf->RoundedRect(
        $cx,
        $y,
        $boxW,
        $ph,
        3,
        '1111',
        'FD'
    );


    $pdf->SetXY(
        $mL,
        $y + 10
    );

    $pdf->SetFont(
        'helvetica',
        'I',
        9
    );

    $pdf->SetTextColor(
        150,
        150,
        150
    );

    $pdf->Cell(
        $contW,
        6,
        'No image available',
        0,
        1,
        'C'
    );


    return $y +
        $ph +
        6;
}

/**
 * Normalize JPEG EXIF orientation
 */
function normalizeProductImage(string $sourcePath): ?string
{
    if (!is_file($sourcePath) || !is_readable($sourcePath)) {
        return null;
    }

    $info = @getimagesize($sourcePath);

    if (!$info) {
        return null;
    }

    $type = $info[2] ?? 0;

    // Only JPEG has the EXIF orientation problem
    if ($type !== IMAGETYPE_JPEG) {
        return $sourcePath;
    }

    if (!function_exists('imagecreatefromjpeg')) {
        return $sourcePath;
    }

    $image = @imagecreatefromjpeg($sourcePath);

    if (!$image) {
        return $sourcePath;
    }

    $orientation = 1;

    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($sourcePath);

        if (!empty($exif['Orientation'])) {
            $orientation = (int)$exif['Orientation'];
        }
    }

    switch ($orientation) {

        case 2:
            imageflip($image, IMG_FLIP_HORIZONTAL);
            break;

        case 3:
            $image = imagerotate($image, 180, 0);
            break;

        case 4:
            imageflip($image, IMG_FLIP_VERTICAL);
            break;

        case 5:
            imageflip($image, IMG_FLIP_HORIZONTAL);
            $image = imagerotate($image, 90, 0);
            break;

        case 6:
            // Rotate 90° clockwise
            $image = imagerotate($image, -90, 0);
            break;

        case 7:
            imageflip($image, IMG_FLIP_HORIZONTAL);
            $image = imagerotate($image, -90, 0);
            break;

        case 8:
            // Rotate 90° counter-clockwise
            $image = imagerotate($image, 90, 0);
            break;
    }

    /**
     * Always create a normalized temporary JPEG.
     */
    $tmpFile = sys_get_temp_dir() .
        '/product_pdf_' .
        getmypid() .
        '_' .
        bin2hex(random_bytes(5)) .
        '.jpg';

    imageinterlace($image, true);

    if (!imagejpeg($image, $tmpFile, 92)) {
        imagedestroy($image);
        return $sourcePath;
    }

    imagedestroy($image);

    return $tmpFile;
}

/**
 * --------------------------------------------------------------------------
 * Cleanup
 * --------------------------------------------------------------------------
 */
function cleanOldProductPdfs(
    int $maxAge = PDF_MAX_AGE
): int {

    $deleted = 0;


    if (!is_dir(PDF_TEMP_DIR)) {
        return 0;
    }


    foreach (
        glob(PDF_TEMP_DIR . '/*.pdf') ?: []
        as $file
    ) {

        if (
            is_file($file) &&
            (
                time() -
                filemtime($file)
            ) > $maxAge
        ) {

            if (@unlink($file)) {
                $deleted++;
            }
        }
    }


    return $deleted;
}


/**
 * --------------------------------------------------------------------------
 * WhatsApp AJAX endpoint
 * --------------------------------------------------------------------------
 */
function handleWaPdfAjax(): void
{
    $productId = (int)(
        $_GET['product_id'] ?? 0
    );


    header(
        'Content-Type: application/json; charset=utf-8'
    );


    if ($productId <= 0) {

        echo json_encode(
            [
                'success' => false,
                'error'   => 'Missing product_id'
            ],
            JSON_UNESCAPED_UNICODE
        );

        exit;
    }


    $result = generateProductPdf(
        $productId
    );


    echo json_encode(
        $result,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}