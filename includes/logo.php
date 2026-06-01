<?php
/**
 * includes/logo.php
 * Logo management helpers — require_once after db.php + helpers.php
 */

define('LOGO_DIR',      BASE_PATH . '/uploads/logo');
define('LOGO_URL_PATH', 'uploads/logo');
define('LOGO_MAX_BYTES', 2 * 1024 * 1024); // 2 MB
define('LOGO_ALLOWED',  ['image/png','image/jpeg','image/jpg','image/webp']);
define('LOGO_SETTING_KEY', 'site_logo');

/**
 * Returns the public-facing src for the logo.
 * Pass $admin=true when called from admin panel (path is one level deeper).
 */
function getLogo(bool $admin = false): string {
    static $cache = null;
    if ($cache === null) {
        try {
            $st = getDB()->prepare("SELECT `value` FROM settings WHERE `key` = ?");
            $st->execute([LOGO_SETTING_KEY]);
            $cache = (string)($st->fetchColumn() ?: '');
        } catch (Throwable $e) {
            $cache = '';
        }
    }

    if ($cache !== '' && file_exists(BASE_PATH . '/' . LOGO_URL_PATH . '/' . $cache)) {
        $prefix = $admin ? '../' : '';
        return $prefix . LOGO_URL_PATH . '/' . $cache;
    }

    // Fallback: default SVG logo embedded as data URI
    return '';
}

/**
 * Handles logo upload from $_FILES['logo_file'].
 * Returns ['success'=>bool, 'error'=>string, 'filename'=>string]
 */
function uploadLogo(array $file): array {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed. Please try again.'];
    }

    // Size check
    if ($file['size'] > LOGO_MAX_BYTES) {
        return ['success' => false, 'error' => 'Logo must be under 2 MB.'];
    }

    // MIME check (use finfo, not user-supplied type)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, LOGO_ALLOWED, true)) {
        return ['success' => false, 'error' => 'Only PNG, JPG, JPEG, and WEBP images are allowed.'];
    }

    // Build safe filename
    $ext      = match ($mime) {
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => 'jpg',
    };
    $filename = 'logo_' . time() . '.' . $ext;
    $destDir  = LOGO_DIR;

    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    // Delete old logo file
    try {
        $st = getDB()->prepare("SELECT `value` FROM settings WHERE `key` = ?");
        $st->execute([LOGO_SETTING_KEY]);
        $old = (string)($st->fetchColumn() ?: '');
        if ($old !== '') {
            $oldPath = $destDir . '/' . $old;
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }
    } catch (Throwable $e) {}

    if (!move_uploaded_file($file['tmp_name'], $destDir . '/' . $filename)) {
        return ['success' => false, 'error' => 'Could not save logo. Check directory permissions.'];
    }

    // Persist to settings
    try {
        getDB()->prepare(
            "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
        )->execute([LOGO_SETTING_KEY, $filename]);
    } catch (Throwable $e) {
        return ['success' => false, 'error' => 'Logo saved but DB update failed: ' . $e->getMessage()];
    }

    return ['success' => true, 'filename' => $filename];
}