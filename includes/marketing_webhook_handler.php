<?php
/**
 * includes/marketing_webhook_handler.php
 * Fire 9 — Meta WhatsApp Cloud API webhook processing (GET verification +
 * POST status/inbound events). Entry point is marketing_webhook.php at the
 * project root; logic lives here so it's testable independent of HTTP.
 */
require_once __DIR__ . '/marketing.php';
require_once __DIR__ . '/marketing_whatsapp.php';
require_once __DIR__ . '/marketing_contacts.php';

/** GET verification handshake. Returns the challenge string to echo back, or null on failure. */
function handleWhatsAppWebhookVerification(array $query): ?string {
    // Meta sends "hub.mode" / "hub.verify_token" / "hub.challenge" as query
    // params — PHP auto-converts dots to underscores in superglobal keys.
    $mode      = $query['hub_mode'] ?? '';
    $token     = $query['hub_verify_token'] ?? '';
    $challenge = $query['hub_challenge'] ?? '';

    if ($mode !== 'subscribe') return null;
    $expected = getMarketingProviderSetting('whatsapp_webhook_verify_token', '');
    if ($expected === '' || !hash_equals($expected, (string)$token)) return null;

    return (string)$challenge;
}

/** Main POST entrypoint. */
function handleWhatsAppWebhookPayload(string $rawBody, string $signatureHeader): array {
    ensureMarketingTables();

    if (!verifyMarketingWhatsAppWebhookSignature($rawBody, $signatureHeader)) {
        error_log('WhatsApp webhook: signature verification failed, rejecting payload.');
        return ['success' => false, 'error' => 'Invalid signature.'];
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        return ['success' => false, 'error' => 'Invalid JSON payload.'];
    }

    // Whole-payload idempotency: Meta retries the ENTIRE delivery on any
    // non-200 response. Deduping on a hash of the raw body — rather than
    // extracting a single canonical id from a payload that can carry
    // multiple statuses/messages at once — matches the "idempotency for
    // provider retries" purpose the marketing_webhooks table was built for.
    $eventId = sha1($rawBody);
    $db = getDB();
    try {
        $db->prepare("INSERT INTO marketing_webhooks (provider, event_id, payload_json, processed, created_at) VALUES ('whatsapp',?,?,1,?)")
           ->execute([$eventId, $rawBody, time()]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return ['success' => true, 'note' => 'Duplicate delivery, already processed.'];
        }
        throw $e;
    }

    $statusCount = 0; $messageCount = 0;
    foreach (($payload['entry'] ?? []) as $entry) {
        foreach (($entry['changes'] ?? []) as $change) {
            $value = $change['value'] ?? [];
            foreach (($value['statuses'] ?? []) as $status) {
                processWhatsAppStatusUpdate($status);
                $statusCount++;
            }
            foreach (($value['messages'] ?? []) as $message) {
                processWhatsAppInboundMessage($message);
                $messageCount++;
            }
        }
    }

    return ['success' => true, 'statuses_processed' => $statusCount, 'messages_processed' => $messageCount];
}

/**
 * Status update handler. Uses a simple monotonic rank so an out-of-order
 * "delivered" arriving after "read" never downgrades the recorded status.
 */
function processWhatsAppStatusUpdate(array $status): void {
    $providerMessageId = $status['id'] ?? '';
    $newStatus = $status['status'] ?? '';
    if ($providerMessageId === '' || !in_array($newStatus, ['sent','delivered','read','failed'], true)) return;

    $db = getDB();
    $st = $db->prepare("SELECT id, recipient_id, status FROM marketing_messages WHERE provider_message_id=? ORDER BY id DESC LIMIT 1");
    $st->execute([$providerMessageId]);
    $message = $st->fetch();
    if (!$message) return; // status for a message we have no record of — ignore

    $rank = ['sent' => 1, 'delivered' => 2, 'read' => 3];
    $current = $message['status'];
    $shouldApply = ($newStatus === 'failed')
        ? ($current === 'sent') // never let a failure overwrite a confirmed delivery/read
        : (!isset($rank[$current]) || $rank[$newStatus] > $rank[$current]);
    if (!$shouldApply) return;

    $now = time();
    $errorInfo = $status['errors'][0] ?? null;

    $updates = ['status' => $newStatus];
    if ($newStatus === 'delivered') $updates['delivered_at'] = $now;
    if ($newStatus === 'read') $updates['read_at'] = $now;
    if ($newStatus === 'failed') {
        $updates['error_code'] = $errorInfo['code'] ?? null;
        $updates['error_message'] = $errorInfo['title'] ?? null;
    }

    $setSql = implode(', ', array_map(fn($k) => "$k=?", array_keys($updates)));
    $db->prepare("UPDATE marketing_messages SET $setSql WHERE id=?")
       ->execute(array_merge(array_values($updates), [$message['id']]));

    $db->prepare("INSERT INTO marketing_message_events (message_id, event_type, meta_json, created_at) VALUES (?,?,?,?)")
       ->execute([$message['id'], $newStatus, json_encode($status), $now]);

    $db->prepare("UPDATE marketing_campaign_recipients SET status=? WHERE id=?")
       ->execute([$newStatus, $message['recipient_id']]);
}

/**
 * Inbound message handler — used only to detect opt-out replies. Does not
 * build a full two-way inbox; that's out of scope here. English-keyword
 * matching only — flagged limitation, not a multi-language parser.
 */
function processWhatsAppInboundMessage(array $message): void {
    $from = $message['from'] ?? '';
    $text = $message['text']['body'] ?? '';
    if ($from === '') return;

    $contact = findMarketingContactByPhone($from);
    if (!$contact) return;

    $db = getDB();
    $db->prepare("UPDATE marketing_contacts SET last_interaction_at=? WHERE id=?")->execute([time(), $contact['id']]);

    if (isMarketingOptOutKeyword($text)) {
        addMarketingSuppression((int)$contact['id'], 'whatsapp', 'user_requested', 'whatsapp_reply');
        $db->prepare("UPDATE marketing_contacts SET whatsapp_opt_in=0, updated_at=? WHERE id=?")->execute([time(), $contact['id']]);
        logMarketingAudit('contact_opted_out', 'marketing_contacts', (int)$contact['id'], 'channel=whatsapp source=reply_keyword');
    }
}

function isMarketingOptOutKeyword(string $text): bool {
    $normalized = trim(mb_strtoupper($text));
    return in_array($normalized, ['STOP', 'UNSUBSCRIBE', 'OPT OUT', 'OPTOUT', 'STOP ALL'], true);
}

/**
 * Matches an inbound sender's phone against stored contacts by the last 10
 * digits, sidestepping inconsistent +country-code/spacing formatting
 * between what Meta sends and what's stored. Accepted trade-off for a
 * single-country (India) contact base — flagged as a heuristic, not exact
 * phone-number canonicalization.
 */
function findMarketingContactByPhone(string $phone): ?array {
    $last10 = substr(preg_replace('/\D/', '', $phone), -10);
    if ($last10 === '') return null;
    $like = '%' . $last10;
    $st = getDB()->prepare("SELECT * FROM marketing_contacts WHERE mobile LIKE ? OR whatsapp_number LIKE ? LIMIT 1");
    $st->execute([$like, $like]);
    return $st->fetch() ?: null;
}