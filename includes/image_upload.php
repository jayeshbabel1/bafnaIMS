<?php
require_once __DIR__ . '/config/config.php';
header('Content-Type: application/json');
 csrfVerify();
$uploadDir = EMAILATTACH_DIR;

// Create folder if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'No file uploaded.'
    ]);
    exit;
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Upload failed. Error code: ' . $file['error']
    ]);
    exit;
}

// Maximum 5 MB
$maxSize = 5 * 1024 * 1024;

if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Image is too large. Maximum size is 5 MB.'
    ]);
    exit;
}

// Validate MIME type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
];

if (!isset($allowedTypes[$mime])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid image type.'
    ]);
    exit;
}

// Generate safe random filename
$filename = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mime];

$destination = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Could not save uploaded image.'
    ]);
    exit;
}

// Change this to your actual domain/path
$imageUrl = EMAILATTACH_DIR . $filename;

echo json_encode([
    'location' => $imageUrl
]);