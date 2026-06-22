<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';

$db = getDB();
$st = $db->prepare("SELECT pp.id, pp.product_id, pp.filename, p.quarry_number
                     FROM product_photos pp
                     JOIN products p ON p.id = pp.product_id
                     WHERE p.quarry_number LIKE ?");
$st->execute(['%580-97300%']);
$rows = $st->fetchAll();

foreach ($rows as $r) {
    $fullPath = PHOTOS_DIR . '/' . $r['filename'];
    echo "DB filename: "   . $r['filename'] . "\n";
    echo "Full path tried: " . $fullPath . "\n";
    echo "file_exists(): " . (file_exists($fullPath) ? 'YES' : 'NO') . "\n";
    echo "---\n";
}

// Also list what's actually in PHOTOS_DIR for this quarry, recursively
echo "Files found on disk matching '580-97300':\n";
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PHOTOS_DIR, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && stripos($f->getFilename(), '580-97300') !== false) {
        echo str_replace(PHOTOS_DIR . '/', '', $f->getPathname()) . "\n";
    }
}