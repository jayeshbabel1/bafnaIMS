<?php
$_envFile = dirname(__DIR__) . '/.env';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if (str_starts_with(trim($_line), '#')) continue;
        [$k, $v] = array_pad(explode('=', $_line, 2), 2, '');
        if ($k !== '') putenv(trim($k) . '=' . trim($v));
    }
}

define('APP_NAME',    'Bafna Marble');
define('APP_VERSION', '3.0.0');
define('BASE_PATH',   dirname(__DIR__));
define('BASE_URL',    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost'));

// MySQL Database 
define('DB_HOST',     getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',     getenv('DB_PORT')    ?: '3306');
define('DB_NAME',     getenv('DB_NAME')    ?: 'bmarble_ims');
define('DB_USER',     getenv('DB_USER')    ?: '');
define('DB_PASS',     getenv('DB_PASS')    ?: '');
define('DB_CHARSET',  getenv('DB_CHARSET') ?: 'utf8mb4');

//  Upload Directories
define('PHOTOS_DIR',      BASE_PATH . '/assets/uploads/photos');
define('THUMBS_DIR',      BASE_PATH . '/assets/uploads/_thumb');
define('MEASUREMENT_DIR', BASE_PATH . '/assets/uploads/measurement_sheets');
define('DNA_DIR',         BASE_PATH . '/assets/uploads/dna_reports');
define('EXCEL_DIR',       BASE_PATH . '/assets/uploads/excel');
define('VIDEOS_DIR', BASE_PATH . '/assets/uploads/videos');
define('VIDEOS_URL', 'assets/uploads/videos');
// ── Room Visualizer settings ─────────────────────────────────────
define('ROOM_TEMPLATES_DIR', BASE_PATH . '/assets/uploads/room_templates');
define('ROOM_PREVIEWS_DIR',  BASE_PATH . '/storage/room_previews');
define('ROOM_TEMPLATES_URL', BASE_URL . '/assets/uploads/room_templates');
define('ROOM_PREVIEWS_URL',  'storage/room_previews');
define('CATALOG_PDF_DIR', BASE_PATH . '/storage/catalogs');

define('ROOM_TYPES', [
    'floor'    => 'Floor',
    'wall'     => 'Wall',
    'kitchen'  => 'Kitchen Countertop',
    'bathroom' => 'Bathroom',
    'living_room' => 'Living Room',
    'Bedroom' => 'Bedroom',
]);

foreach ([ROOM_TEMPLATES_DIR, ROOM_PREVIEWS_DIR] as $_rvDir) {
    if (!is_dir($_rvDir)) @mkdir($_rvDir, 0755, true);
}


define('SESSION_TTL',  86400 * 2); // 2 days
// SMTP SETTINGS

define('MAIL_FROM',      'noreply@bafnamarbles.com');
define('MAIL_FROM_NAME', 'Bafna Marbles');

//define('CATEGORIES',         ['Marble','Travertino','Onyx','Quartzite']);
define('COLOR_SUBCATEGORIES',['White','Grey','Beige','Exotic','Color']);

define('ROLES', [
    'architect'         => 'Architect',
    'interior_designer' => 'Interior Designer',
    'contractor'        => 'Contractor',
    'developer'         => 'Developer / Builder',
    'retailer'          => 'Stone Retailer',
    'other'             => 'Other Professional',
]);
define('EXPERIENCE_OPTIONS', ['0–2 years','3–5 years','6–10 years','10+ years']);

//  Ensure upload directories exist 
foreach ([PHOTOS_DIR, THUMBS_DIR, MEASUREMENT_DIR, DNA_DIR, EXCEL_DIR , VIDEOS_DIR,CATALOG_PDF_DIR] as $_dir) {
    if (!is_dir($_dir)) @mkdir($_dir, 0755, true);
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
   header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "img-src 'self' data: blob: https:; " .
    "script-src 'self' 'unsafe-inline' https://static.cloudflareinsights.com https://www.youtube.com https://player.vimeo.com https://cdn.jsdelivr.net; " .
    "connect-src 'self' https://cloudflareinsights.com https://www.youtube.com https://player.vimeo.com https://vimeo.com; " .
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
    "font-src 'self' https://fonts.gstatic.com; " .
    "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com; " .
    "media-src 'self' https: blob:;"
);
}