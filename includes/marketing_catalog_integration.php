<?php

require_once __DIR__ . '/marketing.php';
require_once __DIR__ . '/marketing_contacts.php';
require_once __DIR__ . '/marketing_templates.php';
require_once __DIR__ . '/marketing_attachments.php';


function listRecentCatalogPdfsForAttachment(int $limit = 20): array {
    try {
        $db = getDB();
        $st = $db->prepare("SELECT id, name, pdf_path AS path, created_at
                             FROM catalogs
                             WHERE  pdf_path IS NOT NULL AND pdf_path != '' 
                             ORDER BY created_at DESC LIMIT ?");
        $st->bindValue(1, $limit, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    } catch (Throwable $e) {
        error_log('listRecentCatalogPdfsForAttachment: ' . $e->getMessage());
        return [];
    }
}

function _resolveStaticCatalogAttachment(int $catalogId): ?array {
    if (!$catalogId) return null;
    try {
        $st = getDB()->prepare("SELECT pdf_path FROM catalogs WHERE id=?");
        $st->execute([$catalogId]);
        $path = $st->fetchColumn();
        if (!$path) return null;

        // pdf_path may be stored relative to BASE_PATH rather than absolute
        // — this app's convention elsewhere (Fire 12's own attachment dirs)
        // uses paths under storage/, so try both rather than assuming.
        $absolute = str_starts_with($path, '/') ? $path : (BASE_PATH . '/' . ltrim($path, '/'));
        if (!is_file($absolute)) return null;
        return ['path' => $absolute, 'filename' => basename($absolute)];
    } catch (Throwable $e) {
        error_log('_resolveStaticCatalogAttachment: ' . $e->getMessage());
        return null;
    }
}


// ── Per-client selection PDF resolution (generated fresh per recipient) ──
/**
 * ASSUMPTION: generateClientSelectionCatalog(int $clientId): array returns
 * a shape containing one of path/file_path/pdf_path. Only works for
 * contacts linked to an actual `clients` row (source_type='client') — a
 * lead/manual/import contact has no selection to generate.
 */
function _resolveSelectionPdfAttachment(array $contact): ?array {
    if (($contact['source_type'] ?? '') !== 'client' || empty($contact['source_id'])) return null;
    if (!function_exists('generateClientSelectionCatalog')) {
        error_log('_resolveSelectionPdfAttachment: generateClientSelectionCatalog() not loaded/found.');
        return null;
    }
    try {
        $result = generateClientSelectionCatalog((int)$contact['source_id']);
        $path = $result['path'] ?? $result['file_path'] ?? $result['pdf_path'] ?? null;
        if (!$path || !is_file($path)) return null;
        return ['path' => $path, 'filename' => $result['filename'] ?? basename($path)];
    } catch (Throwable $e) {
        error_log('_resolveSelectionPdfAttachment: generateClientSelectionCatalog() call failed — ' . $e->getMessage());
        return null;
    }
}

/**
 * Central resolver called from enqueueCampaignRecipients(). Returns
 * ['path'=>, 'filename'=>] or null (silently skip attachment, text still sends).
 */
function resolveMarketingCampaignAttachment(array $campaign, array $contact): ?array {
    if (!empty($campaign['attach_selection_pdf'])) return _resolveSelectionPdfAttachment($contact);
    if (!empty($campaign['attach_catalog_id'])) return _resolveStaticCatalogAttachment((int)$campaign['attach_catalog_id']);
    return null;
}

/** Builds Meta's header component for a document-header template. Returns null if the template isn't a document-header template. */
function buildWhatsAppHeaderComponent(array $template, array $attachment): ?array {
    if (($template['header']['type'] ?? '') !== 'document') return null;
    $url = marketingAttachmentUrl($attachment['path'], $attachment['filename']);
    if (!$url) return null;
    return ['type' => 'header', 'parameters' => [['type' => 'document', 'document' => ['link' => $url, 'filename' => $attachment['filename']]]]];
}

function getMarketingTemplatesWithDocumentHeader(): array {
    $rows = getMarketingTemplates(['channel' => 'whatsapp', 'limit' => 200])['rows'];
    return array_values(array_filter($rows, function ($t) {
        $header = json_decode($t['header_json'] ?? '{}', true) ?: [];
        return ($header['type'] ?? '') === 'document' && $t['approval_status'] === 'approved';
    }));
}

// ── Ad-hoc "Send Selection Catalog" quick action (bypasses the campaign
// system entirely — one contact, one click, reuses the named functions
// directly as the original integration instruction specified) ──────────
function sendAdHocSelectionCatalog(int $contactId, string $channel, int $templateId = 0): array {
   $contact = getMarketingContact($contactId);
    if (!$contact) return ['success' => false, 'error' => 'Contact not found.'];
    if (($contact['source_type'] ?? '') !== 'client' || empty($contact['source_id'])) {
        return ['success' => false, 'error' => 'This contact is not linked to a client record — no selection to send.'];
    }
    // Fire 13: this shortcut previously checked suppression only, not the
    // contact's own opt-in preference — inconsistent with every other send
    // path. If a client needs their selection PDF despite opting out of
    // marketing, send it outside this pipeline (e.g. a direct one-off email).
    $optedIn = $channel === 'whatsapp' ? (bool)$contact['whatsapp_opt_in'] : (bool)$contact['email_opt_in'];
    if (!$optedIn) return ['success' => false, 'error' => "This contact has opted out of {$channel} marketing — cannot send via this pipeline."];
    if (isMarketingSuppressed($contactId, $channel)) return ['success' => false, 'error' => 'This contact is suppressed on this channel.'];

    $attachment = _resolveSelectionPdfAttachment($contact);
    if (!$attachment) return ['success' => false, 'error' => 'Could not generate the selection PDF for this client. Check that they have an active selection.'];

    if ($channel === 'email') {
        if (!$contact['email']) return ['success' => false, 'error' => 'This contact has no email address.'];
        if (!function_exists('sendCatalogPdfEmail')) return ['success' => false, 'error' => 'sendCatalogPdfEmail() not loaded/found — cannot send.'];
        try {
            $result = sendCatalogPdfEmail($contact['email'], $attachment['path'], ['client_name' => $contact['name']]);
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'sendCatalogPdfEmail() call failed: ' . $e->getMessage()];
        }
        if (!empty($result['success'])) {
            _touchMarketingContactSendTimestamp($contactId, 'email');
            logMarketingAudit('adhoc_selection_catalog_sent', 'marketing_contacts', $contactId, 'channel=email');
        }
        return $result['success'] ? ['success' => true] : ['success' => false, 'error' => $result['error'] ?? 'Send failed.'];
    }

    if ($channel === 'whatsapp') {
        $number = $contact['whatsapp_number'] ?: $contact['mobile'];
        if (!$number) return ['success' => false, 'error' => 'This contact has no WhatsApp/mobile number.'];
        if (!$templateId) return ['success' => false, 'error' => 'Select a document-header WhatsApp template first.'];
        $template = getMarketingTemplate($templateId);
        if (!$template || ($template['header']['type'] ?? '') !== 'document') {
            return ['success' => false, 'error' => 'Selected template does not have a document header — cannot attach a PDF to it.'];
        }
        $headerComponent = buildWhatsAppHeaderComponent($template, $attachment);
        if (!$headerComponent) return ['success' => false, 'error' => 'Could not build a signed link for this file.'];

        $context = buildMarketingVariableContext($contact, [], 'whatsapp');
        $bodyComponents = buildWhatsAppBodyComponents($template, $context);

        require_once __DIR__ . '/marketing/WhatsAppCloudProvider.php';
        $provider = WhatsAppCloudProvider::fromStoredSettings();
        $result = $provider->sendTemplate($number, $template['provider_template_name'], $template['language'], array_merge([$headerComponent], $bodyComponents));

        if ($result['success']) {
            _touchMarketingContactSendTimestamp($contactId, 'whatsapp');
            logMarketingAudit('adhoc_selection_catalog_sent', 'marketing_contacts', $contactId, 'channel=whatsapp');
        }
        return $result;
    }

    return ['success' => false, 'error' => 'Invalid channel.'];
}

// ── Trigger wrapper functions — ready to call once the real hook points
// in pages/client_selections.php / includes/clients.php / includes/
// catalog_pdf.php are identified. Send me those function bodies/names and
// I'll turn this into an exact one-line patch per location. ──────────────
function fireMarketingClientSelectionCreatedTrigger(int $clientId, array $extraContext = []): void {
    if (!function_exists('triggerMarketingAutomationEvent')) return;
    require_once __DIR__ . '/marketing_automation.php';
    $contactId = getMarketingContactIdForSource('client', $clientId);
    if (!$contactId) return; // client not yet synced into marketing_contacts
    triggerMarketingAutomationEvent('client_selection_created', array_merge(['contact_id' => $contactId], $extraContext));
}

function fireMarketingCatalogGeneratedTrigger(array $extraContext = []): void {
    if (!function_exists('triggerMarketingAutomationEvent')) return;
    require_once __DIR__ . '/marketing_automation.php';
    triggerMarketingAutomationEvent('catalog_generated', $extraContext); // broadcast — every listening automation resolves its own audience
}