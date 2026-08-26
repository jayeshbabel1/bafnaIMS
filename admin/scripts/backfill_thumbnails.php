<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

// Only allow this to run for logged-in admins OR from CLI — never anonymous web.
if (PHP_SAPI !== 'cli') {
    startSecureSession();
    if (!isAdmin()) {
        http_response_code(403);
        die('Forbidden. Log in to the admin panel first, then reload this URL.');
    }
}

echo "<pre>";
$db   = getDB();
$rows = $db->query("SELECT DISTINCT filename FROM product_photos")->fetchAll(PDO::FETCH_COLUMN);
$done = 0; $skip = 0;
foreach ($rows as $rel) {
    $resolved = resolvePhotoPath(PHOTOS_DIR, $rel);
    if (!$resolved) { $skip++; continue; }
    $fullOrig  = PHOTOS_DIR . '/' . $resolved;
    $thumbFull = THUMBS_DIR . '/' . $resolved;
    if (file_exists($thumbFull)) { $skip++; continue; } // already has thumb
    echo (generateThumbnail($fullOrig) ? "OK: " : "FAIL: ") . $resolved . "\n";
    $done++;
}
echo "\nDone. Generated: $done, Skipped: $skip\n";
echo "Thumbs live in: " . THUMBS_DIR . "\n";
echo "</pre>";