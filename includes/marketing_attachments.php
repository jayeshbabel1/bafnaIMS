<?php
/**
 * includes/marketing_attachments.php
 * Fire 12 — signed, time-limited file serving for WhatsApp document-header
 * attachments. Meta's servers FETCH the file from a URL at send time (it's
 * not uploaded inline), so a generated PDF needs a temporary public-but-
 * signed link. Email doesn't need this — sendMail() attaches local files
 * directly (Fire 5).
 */
require_once __DIR__ . '/marketing.php';

// ASSUMPTION — adjust if generated PDFs live elsewhere. Both existing
// sample paths referenced in Fire 6's getMarketingSampleContext() sit under
// storage/catalogs/; storage/selections/ added as a plausible sibling for
// per-client selection PDFs specifically. Confirm/correct if wrong.
const MARKETING_ATTACHMENT_BASE_DIRS = ['/storage/catalogs/', '/storage/selections/'];

function _marketingAttachmentSecret(): string {
    static $key = null;
    if ($key !== null) return $key;
    $key = getSetting('marketing_attachment_secret', '');
    if ($key === '') {
        $key = bin2hex(random_bytes(24));
        setSetting('marketing_attachment_secret', $key);
    }
    return $key;
}

/** Fails safe — an unrecognized directory is never signable, even though the signature alone would already block tampering. */
function isPathWithinMarketingAttachmentDirs(string $absolutePath): bool {
    $real = realpath($absolutePath);
    if ($real === false) return false;
    foreach (MARKETING_ATTACHMENT_BASE_DIRS as $rel) {
        $baseReal = realpath(BASE_PATH . $rel);
        if ($baseReal !== false && str_starts_with($real, $baseReal)) return true;
    }
    return false;
}

function marketingAttachmentUrl(string $absoluteLocalPath, string $displayFilename = '', int $expiresInSeconds = 86400): ?string {
    if (!isPathWithinMarketingAttachmentDirs($absoluteLocalPath)) {
        error_log("marketingAttachmentUrl: path outside allowed directories, refusing to sign: {$absoluteLocalPath}");
        return null;
    }
    $expiry = time() + $expiresInSeconds;
    $encodedPath = rtrim(strtr(base64_encode($absoluteLocalPath), '+/', '-_'), '=');
    $sig = substr(hash_hmac('sha256', $encodedPath . ':' . $expiry, _marketingAttachmentSecret()), 0, 40);
    $url = rtrim(BASE_URL, '/') . '/marketing_attachment.php?f=' . $encodedPath . '&exp=' . $expiry . '&sig=' . $sig;
    if ($displayFilename !== '') $url .= '&n=' . urlencode($displayFilename);
    return $url;
}

function verifyMarketingAttachmentSignature(string $encodedPath, int $expiry, string $sig): bool {
    if ($expiry < time()) return false;
    $expected = substr(hash_hmac('sha256', $encodedPath . ':' . $expiry, _marketingAttachmentSecret()), 0, 40);
    return hash_equals($expected, $sig);
}

function decodeMarketingAttachmentPath(string $encoded): string {
    $padded = strtr($encoded, '-_', '+/');
    $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
    $decoded = base64_decode($padded, true);
    return $decoded !== false ? $decoded : '';
}