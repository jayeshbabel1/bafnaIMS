<?php
/**
 * marketing_track.php
 * Fire 9 — email open-pixel + click-redirect public endpoint.
 *   ?a=open&r={recipient_id}&sig=...
 *   ?a=click&r={recipient_id}&u={base64url target}&sig=...
 */
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/marketing_tracking.php';

$action      = $_GET['a'] ?? '';
$recipientId = (int)($_GET['r'] ?? 0);
$sig         = $_GET['sig'] ?? '';

// Served regardless of validity — a broken/invalid pixel must never show a
// broken-image icon inside the recipient's inbox.
$transparentGif = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');

if ($action === 'open') {
    if ($recipientId && verifyMarketingTrackingSignature($recipientId . ':open', $sig)) {
        recordMarketingTrackingEvent($recipientId, 'opened');
    }
    header('Content-Type: image/gif');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $transparentGif;
    exit;
}

if ($action === 'click') {
    $target = decodeMarketingClickTarget($_GET['u'] ?? '');
    $valid = $recipientId && $target !== '' && verifyMarketingTrackingSignature($recipientId . ':click:' . $target, $sig);

    if ($valid) {
        recordMarketingTrackingEvent($recipientId, 'clicked');
        // The signature is what prevents this endpoint being abused as an
        // open redirector — only a URL that was actually embedded by us at
        // enqueue time, with a valid signature, is ever redirected to.
        header('Location: ' . $target, true, 302);
        exit;
    }
    http_response_code(400);
    echo 'Invalid or expired link.';
    exit;
}

http_response_code(400);
echo 'Invalid tracking request.';