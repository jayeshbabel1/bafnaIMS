<?php
// admin/fix_folder_casing.php — run once, then delete
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';

$db = getDB();
$folders = array_diff(scandir(PHOTOS_DIR), ['.', '..']);

foreach ($folders as $folder) {
    $path = PHOTOS_DIR . '/' . $folder;
    if (!is_dir($path)) continue;

    // Normalize to initial-capital: "white" -> "White", "EXOTIC" -> "Exotic"
    $target = ucfirst(strtolower($folder));

    if ($folder !== $target) {
        $newPath = PHOTOS_DIR . '/' . $target;
        if (!file_exists($newPath)) {
            rename($path, $newPath);
            echo "Renamed folder: $folder -> $target\n <br>";
        } else {
            echo "Conflict: both '$folder' and '$target' exist - merge manually.\n <br>";
            continue;
        }
    }

    // Fix DB filename rows referencing the old casing
    $st = $db->prepare("UPDATE product_photos SET filename = REPLACE(filename, ?, ?) WHERE filename LIKE ?");
    $st->execute([$folder . '/', $target . '/', $folder . '/%']);
    echo "Updated " . $st->rowCount() . " DB row(s) for folder '$folder'\n <br>";
}

echo "Done.\n";