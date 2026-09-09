<?php
/**
 * marketing_attachment.php
 * Fire 12 — signed, expiring file server. Only WhatsApp document-header
 * sends use this; email attaches local files directly.
 */
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/marketing_attachments.php';

$encodedPath = $_GET['f'] ?? '';
$expiry = (int)($_GET['exp'] ?? 0);
$sig = $_GET['sig'] ?? '';
$displayName = $_GET['n'] ?? 'document.pdf';

if (!verifyMarketingAttachmentSignature($encodedPath, $expiry, $sig)) {
    http_response_code(403);
    echo 'Invalid or expired link.';
    exit;
}

$path = decodeMarketingAttachmentPath($encodedPath);
if (!isPathWithinMarketingAttachmentDirs($path) || !is_file($path)) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($displayName) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
readfile($path);
exit;