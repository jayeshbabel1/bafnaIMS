<?php
/**
 * admin/scripts/normalize_photo_filenames.php
 * ─────────────────────────────────────────────────────────────────────────
 * ONE-TIME cleanup: renames existing photo files on disk (and their
 * product_photos.filename rows) to the normalized "<QUARRY>-IMG(-n).<ext>"
 * casing, and fixes color-subfolder casing to match ucfirst(strtolower()),
 * mirroring the same normalization the sync engine already assumes.
 *
 * SECURITY: requires an existing logged-in admin session. Delete this file
 * after running it once and reviewing the report.
 * ─────────────────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

startSecureSession();
if (!isAdmin()) {
    http_response_code(403);
    die('Forbidden. Log in to the admin panel first, then reload this URL.');
}

$db  = getDB();
$log = [];
$renamedFiles   = 0;
$renamedFolders = 0;
$updatedRows    = 0;
$conflicts      = 0;

if (!is_dir(PHOTOS_DIR)) {
    die('Photos directory not found: ' . PHOTOS_DIR);
}

// ── Step 1: normalize color-folder casing (top-level dirs only) ────────────
$topEntries = array_diff(scandir(PHOTOS_DIR), ['.', '..']);
$folderRenames = []; // oldName => newName, for path rewriting below

foreach ($topEntries as $entry) {
    $path = PHOTOS_DIR . '/' . $entry;
    if (!is_dir($path)) continue;

    $target = ucfirst(strtolower($entry));
    if ($entry === $target) continue;

    $newPath = PHOTOS_DIR . '/' . $target;
    if (file_exists($newPath)) {
        $log[] = "CONFLICT: folder '$entry' -> '$target' already exists, skipped merging. Merge manually.";
        $conflicts++;
        continue;
    }

    if (rename($path, $newPath)) {
        $folderRenames[$entry] = $target;
        $renamedFolders++;
        $log[] = "Renamed folder: '$entry' -> '$target'";
    } else {
        $log[] = "ERROR: could not rename folder '$entry'.";
    }
}

// ── Step 2: walk every file, normalize its name ─────────────────────────────
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(PHOTOS_DIR, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) continue;

    $ext = strtolower(pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) continue;

    $fullPath = $fileInfo->getPathname();
    $oldRelative = ltrim(str_replace(PHOTOS_DIR, '', $fullPath), '/');

    $dir      = dirname($oldRelative);      // '.' if no subfolder
    $oldName  = basename($oldRelative);
    $newName  = normalizePhotoFilename($oldName);

    if ($newName === $oldName) continue; // already normalized

    $newRelative = ($dir !== '.' ? $dir . '/' : '') . $newName;
    $newFullPath = PHOTOS_DIR . '/' . $newRelative;

    if (file_exists($newFullPath)) {
        $log[] = "CONFLICT: '$oldRelative' -> '$newRelative' already exists, skipped. Resolve manually.";
        $conflicts++;
        continue;
    }

    if (!rename($fullPath, $newFullPath)) {
        $log[] = "ERROR: could not rename file '$oldRelative'.";
        continue;
    }

    $renamedFiles++;
    $log[] = "Renamed file: '$oldRelative' -> '$newRelative'";

    // Update any product_photos rows pointing at the old relative path
    $upd = $db->prepare("UPDATE product_photos SET filename = ? WHERE filename = ?");
    $upd->execute([$newRelative, $oldRelative]);
    $updatedRows += $upd->rowCount();

    // Also catch rows that still reference the OLD folder casing but the
    // OLD filename casing (i.e. folder renamed in step 1, file renamed here)
    foreach ($folderRenames as $oldFolder => $newFolder) {
        $legacyRelative = $oldFolder . '/' . $oldName;
        if ($legacyRelative === $oldRelative) continue; // already handled above
        $upd2 = $db->prepare("UPDATE product_photos SET filename = ? WHERE filename = ?");
        $upd2->execute([$newRelative, $legacyRelative]);
        $updatedRows += $upd2->rowCount();
    }
}

// ── Step 3: catch rows whose filename references a renamed folder but whose
//    file itself needed no renaming (name was already correctly cased) ─────
foreach ($folderRenames as $oldFolder => $newFolder) {
    $upd = $db->prepare("UPDATE product_photos SET filename = REPLACE(filename, ?, ?) WHERE filename LIKE ?");
    $upd->execute([$oldFolder . '/', $newFolder . '/', $oldFolder . '/%']);
    $updatedRows += $upd->rowCount();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Photo Filename Normalization — Report</title>
<style>
body{font-family:-apple-system,Segoe UI,sans-serif;background:#F2F5F9;padding:32px;color:#1A2837;}
.box{background:#fff;border-radius:12px;padding:24px 28px;max-width:900px;margin:0 auto 16px;box-shadow:0 2px 12px rgba(0,0,0,.06);}
.summary{font-weight:700;font-size:18px;margin-bottom:6px;}
.row{padding:6px 0;border-bottom:1px solid #eee;font-size:13px;font-family:monospace;}
.row:last-child{border-bottom:none;}
.conflict{color:#B23A3A;} .ok{color:#3D8B6E;}
</style>
</head>
<body>
<div class="box">
  <p class="summary">Photo Filename Normalization — Report</p>
  <p style="color:#8FA3B1;font-size:13px;">Delete <code>admin/scripts/normalize_photo_filenames.php</code> after reviewing this.</p>
  <p style="margin-top:12px;">
    <span class="ok">✅ <?= $renamedFolders ?> folder(s) renamed</span> ·
    <span class="ok"><?= $renamedFiles ?> file(s) renamed</span> ·
    <span class="ok"><?= $updatedRows ?> DB row(s) updated</span>
    <?php if ($conflicts): ?> · <span class="conflict">⚠️ <?= $conflicts ?> conflict(s) need manual review</span><?php endif; ?>
  </p>
</div>
<div class="box">
  <?php if (empty($log)): ?>
    <p>Nothing to do — all filenames were already normalized.</p>
  <?php else: foreach ($log as $line): ?>
    <div class="row <?= str_starts_with($line, 'CONFLICT') || str_starts_with($line, 'ERROR') ? 'conflict' : '' ?>">
      <?= h($line) ?>
    </div>
  <?php endforeach; endif; ?>
</div>
</body>
</html>