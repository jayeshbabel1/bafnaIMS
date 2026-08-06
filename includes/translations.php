<?php
/**
 * includes/translations.php
 * Multi-language catalog support — schema bootstrap + core helpers.
 * Mirrors includes/license.php pattern (idempotent ensure*, static caching).
 */

define('SUPPORTED_LANGS', ['en', 'hi', 'gu', 'mr']);
define('LANG_LABELS', [
    'en' => 'English',
    'hi' => 'हिन्दी',
    'gu' => 'ગુજરાતી',
    'mr' => 'मराठी',
]);

// ── Schema bootstrap (idempotent) 
function ensureTranslationTables(): void {
    static $done = false;
    if ($done) return;
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS translations (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(30)  NOT NULL,
        entity_id   VARCHAR(50)  NOT NULL,
        field_key   VARCHAR(50)  NOT NULL,
        lang        VARCHAR(5)   NOT NULL,
        value       TEXT NOT NULL,
        updated_at  INT UNSIGNED NOT NULL,
        UNIQUE KEY uq_translation (entity_type, entity_id, field_key, lang),
        KEY idx_lookup (entity_type, lang)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

// ── Current language (session-based, validated) 
function currentLang(): string {
    $l = $_SESSION['lang'] ?? 'en';
    return in_array($l, SUPPORTED_LANGS, true) ? $l : 'en';
}

function setCurrentLang(string $lang): bool {
    if (!in_array($lang, SUPPORTED_LANGS, true)) return false;
    $_SESSION['lang'] = $lang;
    return true;
}

// ── tr() — translate one entity field, falls back to given English value ────
function tr(string $entityType, $entityId, string $fieldKey, string $fallback): string {
    $lang = currentLang();
    if ($lang === 'en') return $fallback;

    static $cache = [];
    $k = $entityType . ':' . $entityId . ':' . $fieldKey . ':' . $lang;
    if (array_key_exists($k, $cache)) {
        return $cache[$k] !== null ? $cache[$k] : $fallback;
    }

    ensureTranslationTables();
    try {
        $st = getDB()->prepare("SELECT value FROM translations WHERE entity_type=? AND entity_id=? AND field_key=? AND lang=?");
        $st->execute([$entityType, (string)$entityId, $fieldKey, $lang]);
        $v = $st->fetchColumn();
    } catch (Throwable $e) {
        $v = false;
    }
    $cache[$k] = ($v !== false && $v !== '') ? $v : null;
    return $cache[$k] ?? $fallback;
}

// ── trBulk() — batch translate one field across many entity IDs (avoids N+1) ─
// Returns [entityId => translatedValue]; falls back to $fallbacks[entityId] for
// missing/en. $fallbacks is [entityId => englishValue].
function trBulk(string $entityType, string $fieldKey, array $fallbacks): array {
    $lang = currentLang();
    if ($lang === 'en' || empty($fallbacks)) return $fallbacks;

    ensureTranslationTables();
    $ids = array_map('strval', array_keys($fallbacks));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    try {
        $st = getDB()->prepare("SELECT entity_id, value FROM translations
            WHERE entity_type=? AND field_key=? AND lang=? AND entity_id IN ($placeholders)");
        $st->execute(array_merge([$entityType, $fieldKey, $lang], $ids));
        $rows = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $e) {
        $rows = [];
    }

    $out = [];
    foreach ($fallbacks as $id => $fallback) {
        $val = $rows[(string)$id] ?? '';
        $out[$id] = $val !== '' ? $val : $fallback;
    }
    return $out;
}

// ── ui() — static UI strings (nav, buttons, labels), request-cached per lang ─
function ui(string $key, string $fallback = ''): string {
    $lang = currentLang();
    if ($lang === 'en') return $fallback !== '' ? $fallback : $key;

    static $cache = [];
    if (!isset($cache[$lang])) {
        ensureTranslationTables();
        $cache[$lang] = [];
        try {
           $st = getDB()->prepare("SELECT entity_id, value FROM translations WHERE entity_type='ui_string' AND lang=?");
$st->execute([$lang]);
foreach ($st->fetchAll() as $r) $cache[$lang][$r['entity_id']] = $r['value'];
        } catch (Throwable $e) {}
    }
    if (isset($cache[$lang][$key]) && $cache[$lang][$key] !== '') return $cache[$lang][$key];
    return $fallback !== '' ? $fallback : $key;
}

// ── Admin: upsert a batch of translations for one entity_type+lang ──────────
// $rows = [ ['entity_id'=>.., 'field_key'=>.., 'value'=>..], ... ]
function saveTranslations(string $entityType, string $lang, array $rows): array {
    if (!in_array($lang, SUPPORTED_LANGS, true) || $lang === 'en') {
        return ['success' => false, 'error' => 'Invalid language.'];
    }
    ensureTranslationTables();
    $db = getDB();
    $db->beginTransaction();
    try {
        $now = time();
        $up = $db->prepare("INSERT INTO translations (entity_type, entity_id, field_key, lang, value, updated_at)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE value=VALUES(value), updated_at=VALUES(updated_at)");
        $del = $db->prepare("DELETE FROM translations WHERE entity_type=? AND entity_id=? AND field_key=? AND lang=?");
        foreach ($rows as $r) {
            $entityId = (string)($r['entity_id'] ?? '');
            $fieldKey = (string)($r['field_key'] ?? '');
            $value    = trim((string)($r['value'] ?? ''));
            if ($entityId === '' || $fieldKey === '') continue;
            if ($value === '') {
                // Empty value = clear override, fall back to English
                $del->execute([$entityType, $entityId, $fieldKey, $lang]);
            } else {
                $up->execute([$entityType, $entityId, $fieldKey, $lang, $value, $now]);
            }
        }
        $db->commit();
        return ['success' => true];
    } catch (Throwable $e) {
        $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ── Admin: fetch existing translations for one entity_type+lang, keyed ──────
// Returns [entity_id => [field_key => value]]
function getTranslationsFor(string $entityType, string $lang): array {
    ensureTranslationTables();
    if ($lang === 'en') return [];
    $st = getDB()->prepare("SELECT entity_id, field_key, value FROM translations WHERE entity_type=? AND lang=?");
    $st->execute([$entityType, $lang]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[$r['entity_id']][$r['field_key']] = $r['value'];
    }
    return $out;
}

// ── Font family for a given lang (Devanagari for hi/mr, Gujarati for gu) ────
function getLangFontFamily(string $lang): ?string {
    return match ($lang) {
        'hi', 'mr' => "'Noto Sans Devanagari'",
        'gu'       => "'Noto Sans Gujarati'",
        default    => null,
    };
}

function getLangFontEmbedUrl(string $lang): ?string {
    return match ($lang) {
        'hi', 'mr' => 'https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap',
        'gu'       => 'https://fonts.googleapis.com/css2?family=Noto+Sans+Gujarati:wght@400;500;600;700&display=swap',
        default    => null,
    };
}

// ── RBAC: auto-seed 'translations.manage' permission if missing ────────────
function ensureTranslationsPermission(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = getDB();
        $chk = $db->query("SHOW TABLES LIKE 'admin_permissions'")->fetch();
        if (!$chk) return;
        $exists = $db->prepare("SELECT id FROM admin_permissions WHERE action=?");
        $exists->execute(['translations.manage']);
        if ($exists->fetch()) return;
        $maxSort = (int)$db->query("SELECT COALESCE(MAX(sort_order),0) FROM admin_permissions")->fetchColumn();
        $db->prepare("INSERT INTO admin_permissions (module, action, label, sort_order) VALUES (?,?,?,?)")
           ->execute(['Settings', 'translations.manage', 'Manage Translations', $maxSort + 1]);
    } catch (Throwable $e) {
        error_log('ensureTranslationsPermission: ' . $e->getMessage());
    }
}