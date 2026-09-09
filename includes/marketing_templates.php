<?php
/**
 * includes/marketing_templates.php
 * Fire 6 — WhatsApp & Email template CRUD + read-only Meta template sync.
 */
require_once __DIR__ . '/marketing.php';
require_once __DIR__ . '/marketing_variables.php';
require_once __DIR__ . '/marketing_whatsapp.php';

const MARKETING_WHATSAPP_CATEGORIES = ['MARKETING', 'UTILITY', 'AUTHENTICATION'];
const MARKETING_APPROVAL_STATUSES   = ['draft', 'pending', 'approved', 'rejected'];

// ── CRUD ──────────────────────────────────────────────────────────────────
function getMarketingTemplates(array $opts = []): array {
    ensureMarketingTables();
    $db = getDB();
    $where = "WHERE 1=1"; $params = [];

    if (!empty($opts['channel'])) { $where .= " AND channel=?"; $params[] = $opts['channel']; }
    $search = trim($opts['search'] ?? '');
    if ($search !== '') { $where .= " AND name LIKE ?"; $params[] = "%{$search}%"; }

    $limit = (int)($opts['limit'] ?? 50); $offset = (int)($opts['offset'] ?? 0);
    $cnt = $db->prepare("SELECT COUNT(*) FROM marketing_templates $where");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $rowParams = $params; $rowParams[] = $limit; $rowParams[] = $offset;
    $st = $db->prepare("SELECT * FROM marketing_templates $where ORDER BY updated_at DESC LIMIT ? OFFSET ?");
    $st->execute($rowParams);
    return ['rows' => $st->fetchAll(), 'total' => $total];
}

function ensureMarketingTemplateVariableMapping(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $db = getDB();
    $cols = $db->query("
        SELECT COLUMN_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marketing_templates'
    ")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('variable_mapping_json', $cols, true)) {
        $db->exec("ALTER TABLE marketing_templates ADD COLUMN variable_mapping_json TEXT NULL AFTER buttons_json");
    }
}

function getMarketingTemplate(int $id): ?array {
    ensureMarketingTables();
    ensureMarketingTemplateVariableMapping();
    $st = getDB()->prepare("SELECT * FROM marketing_templates WHERE id=?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) return null;
    $row['header'] = json_decode($row['header_json'] ?? '{}', true) ?: [];
    $row['buttons'] = json_decode($row['buttons_json'] ?? '[]', true) ?: [];
    $row['variable_mapping'] = json_decode($row['variable_mapping_json'] ?? '[]', true) ?: [];
    return $row;
}

/** Positions ({{1}}, {{2}}, …) Meta uses in a synced-from-Meta template's body text. */
function extractWhatsAppTemplatePositions(string $body): array {
    preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $m);
    $positions = array_unique(array_map('intval', $m[1] ?? []));
    sort($positions);
    return $positions;
}

function getTemplateVariableMapping(array $template): array {
    return json_decode($template['variable_mapping_json'] ?? '[]', true) ?: [];
}

/** $mapping is sequential: index 0 = Meta position {{1}}, index 1 = {{2}}, etc. */
function saveTemplateVariableMapping(int $templateId, array $mapping): void {
    ensureMarketingTemplateVariableMapping();
    $registry = array_keys(marketingVariableRegistry());
    $clean = array_map(fn($v) => in_array($v, $registry, true) ? $v : '', $mapping);
    getDB()->prepare("UPDATE marketing_templates SET variable_mapping_json=? WHERE id=?")
           ->execute([json_encode(array_values($clean)), $templateId]);
}

/**
 * Builds Meta's positional body-component parameters from our named-variable
 * context. Returns [] if the template has no mapping (i.e. it's a locally
 * drafted template never synced/approved — such templates cannot actually
 * be sent via Cloud API at all; the campaign engine checks approval_status
 * separately before attempting a send).
 */
function buildWhatsAppBodyComponents(array $template, array $context): array {
    $mapping = getTemplateVariableMapping($template);
    if (empty($mapping)) return [];
    $params = [];
    foreach ($mapping as $varName) {
        $value = $varName !== '' ? ($context[$varName] ?? '') : '';
        $params[] = ['type' => 'text', 'text' => (string)$value !== '' ? (string)$value : '—'];
    }
    return [['type' => 'body', 'parameters' => $params]];
}

function createMarketingTemplate(array $data): array {
    ensureMarketingTables();
    $channel = ($data['channel'] ?? '') === 'whatsapp' ? 'whatsapp' : (($data['channel'] ?? '') === 'email' ? 'email' : null);
    if (!$channel) return ['success' => false, 'error' => 'Invalid channel.'];

    $name = trim($data['name'] ?? '');
    $body = trim($data['body'] ?? '');
    if ($name === '') return ['success' => false, 'error' => 'Template name is required.'];
    if ($body === '') return ['success' => false, 'error' => 'Template body cannot be empty.'];
    if ($channel === 'email' && trim($data['subject'] ?? '') === '') {
        return ['success' => false, 'error' => 'Email templates require a subject line.'];
    }

    $language = trim($data['language'] ?? '') ?: ($channel === 'whatsapp' ? 'en_US' : 'en');
    $db = getDB();

    $chk = $db->prepare("SELECT id FROM marketing_templates WHERE channel=? AND name=? AND language=?");
    $chk->execute([$channel, $name, $language]);
    if ($chk->fetch()) return ['success' => false, 'error' => 'A template with that name and language already exists for this channel.'];

    $category = null;
    if ($channel === 'whatsapp') {
        $category = in_array($data['category'] ?? '', MARKETING_WHATSAPP_CATEGORIES, true) ? $data['category'] : 'MARKETING';
    }

    $now = time();
    $db->prepare("INSERT INTO marketing_templates
        (company_id, channel, name, category, language, subject, header_json, body, footer, buttons_json,
         provider_template_name, approval_status, variable_mapping_json, created_at, updated_at)
        VALUES (1,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([
           $channel, $name, $category, $language,
           $channel === 'email' ? trim($data['subject'] ?? '') : null,
           $channel === 'whatsapp' ? json_encode(_buildWhatsAppHeaderJson($data)) : null,
           $body,
           $channel === 'whatsapp' ? trim($data['footer'] ?? '') ?: null : null,
           $channel === 'whatsapp' ? json_encode(_buildWhatsAppButtonsJson($data)) : null,
           $channel === 'whatsapp' ? trim($data['provider_template_name'] ?? '') ?: null : null,
           $channel === 'whatsapp' ? (in_array($data['approval_status'] ?? '', MARKETING_APPROVAL_STATUSES, true) ? $data['approval_status'] : 'draft') : 'approved',
           $channel === 'whatsapp' ? json_encode(array_values((array)($data['var_map'] ?? []))) : null,
           $now, $now,
       ]);
    return ['success' => true, 'id' => (int)$db->lastInsertId()];
}


function updateMarketingTemplate(int $id, array $data): array {
    ensureMarketingTables();
    $existing = getMarketingTemplate($id);
    if (!$existing) return ['success' => false, 'error' => 'Template not found.'];

    $name = trim($data['name'] ?? '');
    $body = trim($data['body'] ?? '');
    if ($name === '') return ['success' => false, 'error' => 'Template name is required.'];
    if ($body === '') return ['success' => false, 'error' => 'Template body cannot be empty.'];
    if ($existing['channel'] === 'email' && trim($data['subject'] ?? '') === '') {
        return ['success' => false, 'error' => 'Email templates require a subject line.'];
    }

    $language = trim($data['language'] ?? '') ?: $existing['language'];
    $db = getDB();
    $chk = $db->prepare("SELECT id FROM marketing_templates WHERE channel=? AND name=? AND language=? AND id<>?");
    $chk->execute([$existing['channel'], $name, $language, $id]);
    if ($chk->fetch()) return ['success' => false, 'error' => 'Another template already uses that name and language.'];

    $category = $existing['category'];
    if ($existing['channel'] === 'whatsapp') {
        $category = in_array($data['category'] ?? '', MARKETING_WHATSAPP_CATEGORIES, true) ? $data['category'] : $existing['category'];
    }

    $db->prepare("UPDATE marketing_templates SET
        name=?, category=?, language=?, subject=?, header_json=?, body=?, footer=?, buttons_json=?,
        provider_template_name=?, approval_status=?, variable_mapping_json=?, updated_at=?
        WHERE id=?")
       ->execute([
           $name, $category, $language,
           $existing['channel'] === 'email' ? trim($data['subject'] ?? '') : null,
           $existing['channel'] === 'whatsapp' ? json_encode(_buildWhatsAppHeaderJson($data)) : null,
           $body,
           $existing['channel'] === 'whatsapp' ? trim($data['footer'] ?? '') ?: null : null,
           $existing['channel'] === 'whatsapp' ? json_encode(_buildWhatsAppButtonsJson($data)) : null,
           $existing['channel'] === 'whatsapp' ? trim($data['provider_template_name'] ?? '') ?: null : null,
           $existing['channel'] === 'whatsapp'
               ? (in_array($data['approval_status'] ?? '', MARKETING_APPROVAL_STATUSES, true) ? $data['approval_status'] : $existing['approval_status'])
               : 'approved',
           $existing['channel'] === 'whatsapp' ? json_encode(array_values((array)($data['var_map'] ?? []))) : null,
           time(), $id,
       ]);
    return ['success' => true];
}

function deleteMarketingTemplate(int $id): void {
    ensureMarketingTables();
    getDB()->prepare("DELETE FROM marketing_templates WHERE id=?")->execute([$id]);
}

function duplicateMarketingTemplate(int $id): array {
    $src = getMarketingTemplate($id);
    if (!$src) return ['success' => false, 'error' => 'Template not found.'];
    $db = getDB(); $now = time();
    $copyName = $src['name'] . ' (Copy)';
    $db->prepare("INSERT INTO marketing_templates
        (company_id, channel, name, category, language, subject, header_json, body, footer, buttons_json,
         provider_template_name, approval_status, created_at, updated_at)
        VALUES (1,?,?,?,?,?,?,?,?,?,NULL,'draft',?,?)")
       ->execute([
           $src['channel'], $copyName, $src['category'], $src['language'], $src['subject'],
           $src['header_json'], $src['body'], $src['footer'], $src['buttons_json'], $now, $now,
       ]);
    return ['success' => true, 'id' => (int)$db->lastInsertId()];
}

function _buildWhatsAppHeaderJson(array $data): array {
    $type = $data['header_type'] ?? 'none';
    if ($type === 'text' && trim($data['header_text'] ?? '') !== '') {
        return ['type' => 'text', 'text' => trim($data['header_text'])];
    }
    if ($type === 'image') {
        return ['type' => 'image'];
    }
    return ['type' => 'none'];
}

function _buildWhatsAppButtonsJson(array $data): array {
    $types  = (array)($data['button_type'] ?? []);
    $texts  = (array)($data['button_text'] ?? []);
    $values = (array)($data['button_value'] ?? []);
    $out = [];
    foreach ($types as $i => $type) {
        $text = trim($texts[$i] ?? '');
        if ($text === '') continue;
        if (!in_array($type, ['quick_reply', 'url', 'phone_number'], true)) continue;
        $out[] = ['type' => $type, 'text' => $text, 'value' => trim($values[$i] ?? '')];
    }
    return $out;
}

// ── Read-only sync of approved templates FROM Meta (never submits new ones) ──
function syncWhatsAppTemplatesFromMeta(): array {
    require_once __DIR__ . '/marketing/WhatsAppCloudProvider.php';
    $provider = WhatsAppCloudProvider::fromStoredSettings();
    $result = $provider->listTemplates();
    if (!$result['success']) {
        return ['success' => false, 'error' => $result['error']];
    }

    $db = getDB(); $now = time();
    $created = 0; $updated = 0;

    foreach ($result['templates'] as $tpl) {
        $providerName = $tpl['name'] ?? '';
        $language     = $tpl['language'] ?? 'en_US';
        if ($providerName === '') continue;

        $status = strtolower($tpl['status'] ?? 'pending');
        $status = in_array($status, MARKETING_APPROVAL_STATUSES, true) ? $status : 'pending';
        $category = in_array($tpl['category'] ?? '', MARKETING_WHATSAPP_CATEGORIES, true) ? $tpl['category'] : 'MARKETING';

        // Parse Meta's components array into our header/body/footer/buttons shape.
        $bodyText = ''; $footerText = null; $headerJson = ['type' => 'none']; $buttonsJson = [];
        foreach (($tpl['components'] ?? []) as $comp) {
            $compType = strtoupper($comp['type'] ?? '');
            if ($compType === 'BODY') $bodyText = $comp['text'] ?? '';
            elseif ($compType === 'FOOTER') $footerText = $comp['text'] ?? null;
            elseif ($compType === 'HEADER') {
                $format = strtoupper($comp['format'] ?? 'NONE');
                $headerJson = $format === 'TEXT' ? ['type' => 'text', 'text' => $comp['text'] ?? ''] : ['type' => strtolower($format)];
            } elseif ($compType === 'BUTTONS') {
                foreach (($comp['buttons'] ?? []) as $btn) {
                    $btnType = strtolower($btn['type'] ?? 'quick_reply');
                    $buttonsJson[] = [
                        'type'  => in_array($btnType, ['quick_reply','url','phone_number'], true) ? $btnType : 'quick_reply',
                        'text'  => $btn['text'] ?? '',
                        'value' => $btn['url'] ?? ($btn['phone_number'] ?? ''),
                    ];
                }
            }
        }
        if ($bodyText === '') continue; // template has no usable body, skip

        $chk = $db->prepare("SELECT id FROM marketing_templates WHERE channel='whatsapp' AND provider_template_name=? AND language=?");
        $chk->execute([$providerName, $language]);
        $existing = $chk->fetch();

        if ($existing) {
            $db->prepare("UPDATE marketing_templates SET
                category=?, body=?, footer=?, header_json=?, buttons_json=?, approval_status=?, updated_at=?
                WHERE id=?")
               ->execute([$category, $bodyText, $footerText, json_encode($headerJson), json_encode($buttonsJson), $status, $now, $existing['id']]);
            $updated++;
        } else {
            $db->prepare("INSERT INTO marketing_templates
                (company_id, channel, name, category, language, subject, header_json, body, footer, buttons_json,
                 provider_template_name, approval_status, created_at, updated_at)
                VALUES (1,'whatsapp',?,?,?,NULL,?,?,?,?,?,?,?,?)")
               ->execute([$providerName, $category, $language, json_encode($headerJson), $bodyText, $footerText,
                          json_encode($buttonsJson), $providerName, $status, $now, $now]);
            $created++;
        }
    }

    return ['success' => true, 'created' => $created, 'updated' => $updated, 'scanned' => count($result['templates'])];
}