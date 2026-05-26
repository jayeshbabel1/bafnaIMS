<?php
define('APP_NAME',    'Bafna Marbles');
define('APP_VERSION', '2.0');
define('BASE_URL',    ''); // e.g. http://localhost/bafna-marbles
define('BASE_PATH',   __DIR__ . '/..');

define('DB_PATH',     BASE_PATH . '/database/bafna.sqlite');
define('UPLOAD_PATH', BASE_PATH . '/assets/uploads');

define('PHOTOS_DIR',        UPLOAD_PATH . '/photos');
define('MEASUREMENT_DIR',   UPLOAD_PATH . '/measurement_sheets');
define('DNA_DIR',           UPLOAD_PATH . '/dna_reports');
define('EXCEL_DIR',         UPLOAD_PATH . '/excel_imports');

// Mail config (update for production)
define('MAIL_FROM',    'noreply@bafnamarbles.com');
define('MAIL_FROM_NAME', 'Bafna Marbles');
define('SMTP_HOST',    'smtp.mailtrap.io'); // Replace with real SMTP
define('SMTP_PORT',    587);
define('SMTP_USER',    '');
define('SMTP_PASS',    '');

// Session timeout in seconds (8 hours)
define('SESSION_TTL', 28800);

// Max file sizes (bytes)
define('MAX_PHOTO_SIZE',   5 * 1024 * 1024);   // 5 MB
define('MAX_DOC_SIZE',     10 * 1024 * 1024);  // 10 MB

define('ALLOWED_PHOTO_TYPES', ['image/jpeg','image/jpg','image/png','image/webp']);
define('ALLOWED_DOC_TYPES',   ['application/pdf','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','text/csv','application/vnd.ms-excel']);

// Roles
define('ROLES', [
    'architect'          => 'Architect',
    'interior_designer'  => 'Interior Designer',
    'landscape_architect'=> 'Landscape Architect',
    'design_consultant'  => 'Design Consultant',
]);

// Product categories
define('CATEGORIES', [
    'Marble','Granite','Travertine','Onyx','Slate','Limestone','Quartzite','Sandstone'
]);

// Color subcategories
define('COLOR_SUBCATEGORIES', ['Grey','Beige','White','Black','Green','Pink','Exotic']);

// Experience options
define('EXPERIENCE_OPTIONS', ['0–2 years','3–5 years','6–10 years','10+ years']);