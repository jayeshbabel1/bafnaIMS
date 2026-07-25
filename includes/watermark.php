<?php
/**
 * includes/watermark.php — Watermark settings + CSS renderer
 */
define('WM_DIR',      BASE_PATH . '/uploads/watermark');
define('WM_URL_PATH', 'uploads/watermark');
define('WM_MAX_BYTES', 2 * 1024 * 1024);
define('WM_ALLOWED',  ['image/png','image/jpeg','image/jpg','image/webp']);

define('WM_POSITIONS', ['center','top','bottom','left','right','top-left','top-right','bottom-left','bottom-right']);

function ensureWatermarkPermission(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = getDB();
        if (!$db->query("SHOW TABLES LIKE 'admin_permissions'")->fetch()) return;
        $chk = $db->prepare("SELECT id FROM admin_permissions WHERE action=?");
        $chk->execute(['settings.watermark']);
        if ($chk->fetch()) return;
        $max = (int)$db->query("SELECT COALESCE(MAX(sort_order),0) FROM admin_permissions")->fetchColumn();
        $db->prepare("INSERT INTO admin_permissions (module, action, label, sort_order) VALUES (?,?,?,?)")
           ->execute(['Settings', 'settings.watermark', 'Manage Watermark Settings', $max + 1]);
    } catch (Throwable $e) { error_log('ensureWatermarkPermission: ' . $e->getMessage()); }
}

function getWatermarkSettings(): array {
    return [
        'enable_user' => getSetting('wm_enable_user', '0') === '1',
        'enable_admin'=> getSetting('wm_enable_admin', '0') === '1',
        'image'       => getSetting('wm_image', ''),
        'position'    => in_array(getSetting('wm_position','center'), WM_POSITIONS, true) ? getSetting('wm_position','center') : 'center',
        'color'       => getSetting('wm_color', '#FFFFFF'),
        'opacity'     => getSetting('wm_opacity', '0.5'),
        'size'        => getSetting('wm_size', '40'),
    ];
}

function saveWatermarkSettings(array $d): array {
    $pos = in_array($d['position'] ?? '', WM_POSITIONS, true) ? $d['position'] : 'center';
    $color = preg_match('/^#[0-9a-fA-F]{3,8}$/', $d['color'] ?? '') ? $d['color'] : '#FFFFFF';
    $opacity = (float)($d['opacity'] ?? 0.5);
    if ($opacity < 0 || $opacity > 1) $opacity = 0.5;
    $size = (int)($d['size'] ?? 40);
    if ($size < 10 || $size > 200) $size = 40;

    setSettings([
        'wm_enable_user'  => !empty($d['enable_user']) ? '1' : '0',
        'wm_enable_admin' => !empty($d['enable_admin']) ? '1' : '0',
        'wm_position'     => $pos,
        'wm_color'        => $color,
        'wm_opacity'      => (string)$opacity,
        'wm_size'         => (string)$size,
    ]);
    return ['success' => true];
}

function uploadWatermarkImage(array $file): array {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed. Please try again.'];
    }
    if ($file['size'] > WM_MAX_BYTES) {
        return ['success' => false, 'error' => 'Watermark image must be under 2 MB.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, WM_ALLOWED, true)) {
        return ['success' => false, 'error' => 'Only PNG, JPG, JPEG, and WEBP images are allowed.'];
    }
    $ext = match ($mime) {
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => 'jpg',
    };
    if (!is_dir(WM_DIR)) mkdir(WM_DIR, 0755, true);

    $old = getSetting('wm_image', '');
    if ($old !== '' && file_exists(WM_DIR . '/' . $old)) @unlink(WM_DIR . '/' . $old);

    $filename = 'wm_' . time() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], WM_DIR . '/' . $filename)) {
        return ['success' => false, 'error' => 'Could not save watermark image.'];
    }
    setSetting('wm_image', $filename);
    return ['success' => true, 'filename' => $filename];
}

function removeWatermarkImage(): void {
    $old = getSetting('wm_image', '');
    if ($old !== '' && file_exists(WM_DIR . '/' . $old)) @unlink(WM_DIR . '/' . $old);
    setSetting('wm_image', '');
}

function getWatermarkUrl(bool $admin = false): string {
    $img = getSetting('wm_image', '');
    if ($img === '' || !file_exists(WM_DIR . '/' . $img)) return '';
    return ($admin ? '../' : '') . WM_URL_PATH . '/' . $img;
}

// ── CSS renderer — call inside a <style> block ──────────────────────────────
function renderWatermarkCSS(bool $isAdmin): string {
    $s = getWatermarkSettings();
    $enabled = $isAdmin ? $s['enable_admin'] : $s['enable_user'];
    if (!$enabled) return '';
    $url = getWatermarkUrl($isAdmin);
    if ($url === '') return '';

    $selectors = $isAdmin
        ? ['.tbl-thumb', '.apv-card-photo', '.apv-list-thumb', '.photo-grid-item']
        : ['.product-thumb', '.list-thumb', '.catalog-table-thumb', '.detail-hero .zoom-container',
           '.gallery-item', '.featured-thumb', '.shortlist-thumb', '.sel-thumb'];

    $pos = match ($s['position']) {
        'top'          => 'top:6px;left:50%;transform:translateX(-50%);',
        'bottom'       => 'bottom:6px;left:50%;transform:translateX(-50%);',
        'left'         => 'top:50%;left:6px;transform:translateY(-50%);',
        'right'        => 'top:50%;right:6px;transform:translateY(-50%);',
        'top-left'     => 'top:6px;left:6px;',
        'top-right'    => 'top:6px;right:6px;',
        'bottom-left'  => 'bottom:6px;left:6px;',
        'bottom-right' => 'bottom:6px;right:6px;',
        default        => 'top:50%;left:50%;transform:translate(-50%,-50%);',
    };
    $size    = (int)$s['size'];
    $opacity = (float)$s['opacity'];
    $color   = htmlspecialchars($s['color'], ENT_QUOTES);
    $selJoin = implode(', ', array_map(fn($sel) => $sel . '::after', $selectors));
    $posJoin = implode(', ', array_map(fn($sel) => $sel, $selectors));

    return "
{$posJoin} { position:relative; overflow:hidden; }
{$selJoin} {
  content:'';
  position:absolute;
  {$pos}
  width:{$size}px; height:{$size}px;
  background-color:{$color};
  -webkit-mask-image:url('{$url}');
  mask-image:url('{$url}');
  -webkit-mask-size:contain; mask-size:contain;
  -webkit-mask-repeat:no-repeat; mask-repeat:no-repeat;
  -webkit-mask-position:center; mask-position:center;
  opacity:{$opacity};
  pointer-events:none;
  z-index:5;
}
";
}