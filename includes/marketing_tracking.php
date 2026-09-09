<?php
/**
 * includes/marketing_tracking.php
 * Fire 9 — email open-pixel + click-redirect signing/verification, and the
 * HTML rewriter that injects them into a rendered campaign email body.
 *
 * DESIGN NOTE: links are keyed by recipient_id, not message_id. The pixel/
 * links must be embedded in the HTML at ENQUEUE time (Fire 7's
 * enqueueCampaignRecipients()), before the message is sent — the
 * marketing_messages row (and its id) doesn't exist until Fire 8's queue
 * worker successfully dispatches it. At hit-time we resolve
 * recipient_id -> the matching marketing_messages row ourselves.
 */
require_once __DIR__ . '/marketing.php';
require_once __DIR__ . '/helpers.php';

function _marketingTrackingSecret(): string {
    static $key = null;
    if ($key !== null) return $key;
    $key = getSetting('marketing_tracking_secret', '');
    if ($key === '') {
        $key = bin2hex(random_bytes(24));
        setSetting('marketing_tracking_secret', $key);
    }
    return $key;
}

function _marketingTrackingSign(string $data): string {
    return substr(hash_hmac('sha256', $data, _marketingTrackingSecret()), 0, 32);
}

function marketingTrackingPixelUrl(int $recipientId): string {
    $sig = _marketingTrackingSign($recipientId . ':open');
    return rtrim(BASE_URL, '/') . '/marketing_track.php?a=open&r=' . $recipientId . '&sig=' . $sig;
}

function marketingClickRedirectUrl(int $recipientId, string $targetUrl): string {
    $encoded = rtrim(strtr(base64_encode($targetUrl), '+/', '-_'), '=');
    $sig = _marketingTrackingSign($recipientId . ':click:' . $targetUrl);
    return rtrim(BASE_URL, '/') . '/marketing_track.php?a=click&r=' . $recipientId . '&u=' . $encoded . '&sig=' . $sig;
}

function verifyMarketingTrackingSignature(string $expectedData, string $sig): bool {
    return hash_equals(_marketingTrackingSign($expectedData), $sig);
}

function decodeMarketingClickTarget(string $encoded): string {
    $padded = strtr($encoded, '-_', '+/');
    $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
    $decoded = base64_decode($padded, true);
    return $decoded !== false ? $decoded : '';
}

/**
 * Rewrites an email HTML body to inject an open-tracking pixel and rewrite
 * outbound links through the click-redirect endpoint. The unsubscribe link
 * is deliberately left untouched — a compliance click isn't "engagement"
 * and shouldn't be conflated with marketing click-through rate.
 */
function rewriteEmailBodyForTracking(string $html, int $recipientId, bool $trackOpens, bool $trackClicks, string $unsubscribeUrl): string {
    if ($trackClicks) {
        $html = preg_replace_callback(
            '/href\s*=\s*"(https?:\/\/[^"]+)"/i',
            function (array $m) use ($recipientId, $unsubscribeUrl) {
                // BUGFIX: $m[1] is extracted straight from the HTML source,
                // so it's HTML-entity-ESCAPED (e.g. "&" appears as "&amp;").
                // It was previously used raw both for the unsubscribe-link
                // exemption check (which therefore always failed, since
                // $unsubscribeUrl is the raw un-escaped URL) and as the
                // actual click-redirect target (baking literal "&amp;" text
                // into the tracking payload) — corrupting ANY link with 2+
                // query params, not just unsubscribe links. Decoding once,
                // immediately, fixes both.
                $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                if ($unsubscribeUrl !== '' && $url === $unsubscribeUrl) return $m[0];
                return 'href="' . h(marketingClickRedirectUrl($recipientId, $url)) . '"';
            },
            $html
        );
    }
    if ($trackOpens) {
        $pixelUrl = h(marketingTrackingPixelUrl($recipientId));
        $html .= '<img src="' . $pixelUrl . '" width="1" height="1" style="display:none;" alt=""/>';
    }
    return $html;
}

/** Called by marketing_track.php on a signature-verified open/click hit. */
function recordMarketingTrackingEvent(int $recipientId, string $eventType): void {
    $db = getDB();
    $st = $db->prepare("SELECT id, status FROM marketing_messages WHERE recipient_id=? AND channel='email' ORDER BY id DESC LIMIT 1");
    $st->execute([$recipientId]);
    $message = $st->fetch();
    if (!$message) return; // hit fired before the send row exists — shouldn't normally happen, nothing to attach to

    $now = time();
    $db->prepare("INSERT INTO marketing_message_events (message_id, event_type, created_at) VALUES (?,?,?)")
       ->execute([$message['id'], $eventType, $now]);

    // An open/click proves delivery — SMTP gives no delivery webhook of its
    // own, so this is the best available signal. Only upgrades sent->delivered.
    if ($message['status'] === 'sent') {
        $db->prepare("UPDATE marketing_messages SET status='delivered', delivered_at=? WHERE id=?")->execute([$now, $message['id']]);
        $db->prepare("UPDATE marketing_campaign_recipients SET status='delivered' WHERE id=?")->execute([$recipientId]);
    }

    $contactSt = $db->prepare("SELECT contact_id FROM marketing_campaign_recipients WHERE id=?");
    $contactSt->execute([$recipientId]);
    $contactId = (int)$contactSt->fetchColumn();
    if ($contactId) {
        $db->prepare("UPDATE marketing_contacts SET last_interaction_at=? WHERE id=?")->execute([$now, $contactId]);
    }
}