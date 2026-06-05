<?php
define('APP_NAME',    'Bafna Marbles');
define('APP_VERSION', '2.0.0');
define('BASE_PATH',   dirname(__DIR__));
define('BASE_URL',    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost'));

// ── MySQL Database ─────────────────────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_PORT',     '3306');
define('DB_NAME',     '');
define('DB_USER',     '');
define('DB_PASS',     '');
define('DB_CHARSET',  'utf8mb4');

// ── Upload Directories ─────────────────────────────────────────────────────
define('PHOTOS_DIR',      BASE_PATH . '/assets/uploads/photos');
define('MEASUREMENT_DIR', BASE_PATH . '/assets/uploads/measurement_sheets');
define('DNA_DIR',         BASE_PATH . '/assets/uploads/dna_reports');
define('EXCEL_DIR',       BASE_PATH . '/assets/uploads/excel');

define('SESSION_TTL',  86400 * 2); // 7 days
// SMTP SETTINGS

define('MAIL_FROM',      'noreply@bafnamarbles.com');
define('MAIL_FROM_NAME', 'Bafna Marbles');

define('CATEGORIES',         ['Marble','Travertino','Onyx','Quartzite']);
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

// ── Ensure upload directories exist ───────────────────────────────────────
foreach ([PHOTOS_DIR, MEASUREMENT_DIR, DNA_DIR, EXCEL_DIR] as $_dir) {
    if (!is_dir($_dir)) @mkdir($_dir, 0755, true);
}