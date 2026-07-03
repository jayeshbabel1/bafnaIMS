<?php
/**
 * includes/room_visualizer.php
 * ─────────────────────────────────────────────────────────────────────────
 * Marble Room Visualizer — core generation engine.
 *
 * Default engine ("composite"): perspective-warps the real slab photo onto
 * a masked surface region of a room template, then multiplies a pre-shot
 * shadow/lighting layer over it so the result respects the room's original
 * light direction, shadows and ambient occlusion. Deterministic, free,
 * and never alters the marble's actual color/veining.
 *
 * Optional engine ("huggingface"): calls a free Hugging Face Inference API
 * img2img model. Experimental — result fidelity to the exact slab pattern
 * is not guaranteed, so it's opt-in via ROOM_VIS_ENGINE env / admin toggle.
 * ─────────────────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/cloudinary.php';

// ── Public entry point ────────────────────────────────────────────────────
function generateRoomVisualization(int $userId, int $productId, int $templateId, string $engine = ''): array {
    $db = getDB();

    $product = $db->prepare("SELECT * FROM products WHERE id=?");
    $product->execute([$productId]);
    $product = $product->fetch();
    if (!$product) return ['success' => false, 'error' => 'Product not found.'];

    $photoSt = $db->prepare("SELECT filename FROM product_photos WHERE product_id=? ORDER BY sort_order LIMIT 1");
    $photoSt->execute([$productId]);
    $photoRow = $photoSt->fetch();
    if (!$photoRow) return ['success' => false, 'error' => 'This product has no photo to visualize.'];

    $texturePath = resolvePhotoPath(PHOTOS_DIR, $photoRow['filename']);
    if (!$texturePath) return ['success' => false, 'error' => 'Slab image file missing on disk.'];
    $texturePath = PHOTOS_DIR . '/' . $texturePath;

    $tplSt = $db->prepare("SELECT * FROM room_templates WHERE id=? AND is_active=1");
    $tplSt->execute([$templateId]);
    $template = $tplSt->fetch();
    if (!$template) return ['success' => false, 'error' => 'Room template not found or inactive.'];

    $engine = $engine ?: (getenv('ROOM_VIS_ENGINE') ?: 'composite');

    try {
        if ($engine === 'huggingface' && getenv('HF_API_TOKEN')) {
            $localOut = _rvGenerateViaHuggingFace($texturePath, $template);
        } else {
            $engine   = 'composite';
            $localOut = _rvGenerateViaComposite($texturePath, $template);
        }
    } catch (Throwable $e) {
        error_log('generateRoomVisualization: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Generation failed: ' . $e->getMessage()];
    }

    // ── Persist (local or Cloudinary) ──────────────────────────────────────
    $driver = getenv('STORAGE_DRIVER') ?: 'local';
    $resultImage = basename($localOut);
    $publicId    = null;

    if ($driver === 'cloudinary' && cloudinaryConfigured()) {
        $up = cloudinaryUpload($localOut, 'room_previews');
        if ($up['success']) {
            $resultImage = $up['url'];
            $publicId    = $up['public_id'];
            @unlink($localOut); // no need to keep local copy once hosted
        } else {
            // fall back silently to local storage
            $driver = 'local';
        }
    }

    $db->prepare("
        INSERT INTO room_visualizations
            (user_id, product_id, room_template_id, engine, result_image, storage_driver, cloudinary_public_id, status, created_at)
        VALUES (?,?,?,?,?,?,?,?,?)
    ")->execute([$userId, $productId, $templateId, $engine, $resultImage, $driver, $publicId, 'done', time()]);

    $id = (int)$db->lastInsertId();

    return [
        'success' => true,
        'id'      => $id,
        'url'     => $driver === 'cloudinary' ? $resultImage : (ROOM_PREVIEWS_URL . '/' . $resultImage),
        'engine'  => $engine,
    ];
}

// ── Default engine: perspective-warp compositor (Imagick) ─────────────────
function _rvGenerateViaComposite(string $texturePath, array $template): string {
    if (!class_exists('Imagick')) {
        throw new RuntimeException('Imagick PHP extension is required for the visualizer. Ask your host to enable it.');
    }

    $baseImgPath = ROOM_TEMPLATES_DIR . '/' . $template['base_image'];
    if (!file_exists($baseImgPath)) {
        throw new RuntimeException('Room base image missing: ' . $template['base_image']);
    }

    $maskPoints = json_decode($template['mask_points'], true);
    if (!is_array($maskPoints) || count($maskPoints) !== 4) {
        throw new RuntimeException('Room template mask is not configured correctly.');
    }
    [$tl, $tr, $br, $bl] = $maskPoints;

    $canvasW = (int)$template['canvas_w'];
    $canvasH = (int)$template['canvas_h'];

    // 1. Bounding box of the quad → this is our "flat" texture rectangle
    $xs = array_column($maskPoints, 0);
    $ys = array_column($maskPoints, 1);
    $bx = min($xs); $by = min($ys);
    $bw = max($xs) - $bx; $bh = max($ys) - $by;
    if ($bw <= 0 || $bh <= 0) {
        throw new RuntimeException('Room template mask has zero area.');
    }

    // 2. Build a tiled texture layer sized to the bbox (repeat slab photo
    //    so small close-up shots still cover large surfaces realistically)
    $tex = new Imagick($texturePath);
    $tex->setImageColorspace(Imagick::COLORSPACE_SRGB);

    // Scale the texture down to a sensible "tile" size (~35% of bbox width)
    $tileW = max(200, (int)($bw * 0.35));
    $ratio = $tileW / max(1, $tex->getImageWidth());
    $tileH = max(1, (int)($tex->getImageHeight() * $ratio));
    $tex->resizeImage($tileW, $tileH, Imagick::FILTER_LANCZOS, 1);

    $tiled = new Imagick();
    $tiled->newImage((int)$bw, (int)$bh, new ImagickPixel('none'));
    $tiled->setImageFormat('png');
    for ($y = 0; $y < $bh; $y += $tileH) {
        for ($x = 0; $x < $bw; $x += $tileW) {
            $tiled->compositeImage($tex, Imagick::COMPOSITE_OVER, $x, $y);
        }
    }
    $tex->clear();

    // 3. Perspective-warp the flat tiled rectangle onto the quad's corners
    $controlPoints = [
        0,        0,        $tl[0], $tl[1],
        $bw - 1,  0,        $tr[0], $tr[1],
        $bw - 1,  $bh - 1,  $br[0], $br[1],
        0,        $bh - 1,  $bl[0], $bl[1],
    ];
    $tiled->setImageVirtualPixelMethod(Imagick::VIRTUALPIXELMETHOD_TRANSPARENT);
    $tiled->distortImage(Imagick::DISTORTION_PERSPECTIVE, $controlPoints, true);

    // distortImage repositions the canvas — normalize back to full room size
    $page = $tiled->getImagePage();
    $warped = new Imagick();
    $warped->newImage($canvasW, $canvasH, new ImagickPixel('none'));
    $warped->setImageFormat('png');
    $warped->compositeImage($tiled, Imagick::COMPOSITE_OVER, $page['x'], $page['y']);
    $tiled->clear();

    // 4. Clip to the exact shape — arbitrary polygon if provided, else the quad
$clipJson    = $template['clip_points'] ?? null;
$clipDecoded = $clipJson ? json_decode($clipJson, true) : null;
$clipPoints  = (is_array($clipDecoded) && count($clipDecoded) >= 3) ? $clipDecoded : $maskPoints;

$maskDraw = new ImagickDraw();
$maskDraw->setFillColor('white');
$polygonArr = array_map(fn($pt) => ['x' => $pt[0], 'y' => $pt[1]], $clipPoints);
$maskDraw->polygon($polygonArr);

$maskImg = new Imagick();
$maskImg->newImage($canvasW, $canvasH, new ImagickPixel('black'));
$maskImg->setImageFormat('png');
$maskImg->drawImage($maskDraw);
$warped->compositeImage($maskImg, Imagick::COMPOSITE_DSTIN, 0, 0);
$maskImg->clear();

    // 5. Multiply in the room's shadow/lighting layer (preserves realism)
    if (!empty($template['shadow_layer']) && file_exists(ROOM_TEMPLATES_DIR . '/' . $template['shadow_layer'])) {
        $shadow = new Imagick(ROOM_TEMPLATES_DIR . '/' . $template['shadow_layer']);
        $shadow->resizeImage($canvasW, $canvasH, Imagick::FILTER_LANCZOS, 1, true);
        $warped->compositeImage($shadow, Imagick::COMPOSITE_MULTIPLY, 0, 0);
        $shadow->clear();
    }

    // 6. Composite the finished, lit texture onto the room base photo
    $room = new Imagick($baseImgPath);
    $room->resizeImage($canvasW, $canvasH, Imagick::FILTER_LANCZOS, 1, false);
    $room->compositeImage($warped, Imagick::COMPOSITE_OVER, 0, 0);
    $room->setImageFormat('jpg');
    $room->setImageCompressionQuality(90);

    $outPath = ROOM_PREVIEWS_DIR . '/' . uniqid('rv_', true) . '.jpg';
    $room->writeImage($outPath);

    $warped->clear();
    $room->clear();

    return $outPath;
}

// ── Optional engine: Hugging Face free Inference API (img2img) ────────────
function _rvGenerateViaHuggingFace(string $texturePath, array $template): string {
    $token = getenv('HF_API_TOKEN');
    if (!$token) {
        throw new RuntimeException('Hugging Face API token not configured.');
    }

    // Build the same perspective-warped composite first — it's used as the
    // img2img "init image" so the model only has to blend lighting/shadow,
    // not invent the marble pattern from scratch.
    $initPath = _rvGenerateViaComposite($texturePath, $template);

    $model = 'stabilityai/stable-diffusion-2-1'; // free-tier hosted model
    $url   = "https://api-inference.huggingface.co/models/{$model}";

    $imageData = base64_encode(file_get_contents($initPath));

    $payload = json_encode([
        'inputs' => 'photorealistic interior, natural lighting, marble surface, high detail',
        'parameters' => [
            'image'    => $imageData,
            'strength' => 0.25, // low strength: mostly preserve init image
        ],
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err || $httpCode !== 200 || !$response) {
        // Graceful fallback — the composite image is already good on its own
        error_log('HuggingFace img2img failed (HTTP ' . $httpCode . '): ' . $err);
        return $initPath;
    }

    $outPath = ROOM_PREVIEWS_DIR . '/' . uniqid('rv_ai_', true) . '.jpg';
    file_put_contents($outPath, $response);
    @unlink($initPath);

    return $outPath;
}

// ── Fetch helpers ──────────────────────────────────────────────────────────
function getActiveRoomTemplates(): array {
    return getDB()->query("SELECT * FROM room_templates WHERE is_active=1 ORDER BY sort_order ASC")->fetchAll();
}

function getRoomTemplatesGrouped(): array {
    $rows = getDB()->query("SELECT * FROM room_templates WHERE is_active=1 ORDER BY sort_order ASC")->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[$r['room_type']][] = $r;
    return $out;
}

function getUserRoomVisualizations(int $userId, int $productId): array {
    $st = getDB()->prepare("
        SELECT rv.*, rt.label AS room_label, rt.room_type
        FROM room_visualizations rv
        JOIN room_templates rt ON rt.id = rv.room_template_id
        WHERE rv.user_id=? AND rv.product_id=? AND rv.status='done'
        ORDER BY rv.created_at DESC
    ");
    $st->execute([$userId, $productId]);
    return $st->fetchAll();
}

function deleteRoomVisualization(int $id, int $userId): bool {
    $db = getDB();
    $st = $db->prepare("SELECT * FROM room_visualizations WHERE id=? AND user_id=?");
    $st->execute([$id, $userId]);
    $row = $st->fetch();
    if (!$row) return false;

    if ($row['storage_driver'] === 'cloudinary' && $row['cloudinary_public_id']) {
        cloudinaryDelete($row['cloudinary_public_id']);
    } elseif ($row['storage_driver'] === 'local') {
        $p = ROOM_PREVIEWS_DIR . '/' . $row['result_image'];
        if (file_exists($p)) @unlink($p);
    }

    $del = $db->prepare("DELETE FROM room_visualizations WHERE id=?");
    $del->execute([$id]);
    return $del->rowCount() > 0;
}