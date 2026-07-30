<?php
/**
 * includes/catalog_pdf.php
 * Catalog PDF Management — schema bootstrap + core helpers (Fire 1)
 */

function ensureCatalogPdfTables(): void {
    static $done = false;
    if ($done) return;
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS catalog_pdf_settings (
        `key`   VARCHAR(100) PRIMARY KEY,
        `value` TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS catalogs (
        id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name             VARCHAR(200) NOT NULL,
        user_id          INT UNSIGNED NULL,
        admin_id         INT UNSIGNED NULL,
        product_ids_json LONGTEXT NOT NULL,
        config_json      LONGTEXT NOT NULL,
        status           VARCHAR(20) NOT NULL DEFAULT 'draft',
        pdf_path         VARCHAR(255) NULL,
        pages            INT UNSIGNED NULL,
        size_bytes        BIGINT UNSIGNED NULL,
        created_at       INT UNSIGNED NOT NULL,
        updated_at       INT UNSIGNED NOT NULL,
        KEY idx_user  (user_id),
        KEY idx_admin (admin_id),
        KEY idx_status(status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS catalog_download_logs (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        catalog_id  INT UNSIGNED NOT NULL,
        channel     VARCHAR(20) NOT NULL DEFAULT 'download',
        to_address  VARCHAR(255) NULL,
        ip_address  VARCHAR(64)  NULL,
        success     TINYINT(1) NOT NULL DEFAULT 1,
        created_at  INT UNSIGNED NOT NULL,
        KEY idx_catalog (catalog_id),
        CONSTRAINT fk_cdl_catalog FOREIGN KEY (catalog_id) REFERENCES catalogs(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS catalog_share_links (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        catalog_id  INT UNSIGNED NOT NULL,
        token       VARCHAR(64) NOT NULL UNIQUE,
        expires_at  INT UNSIGNED NULL,
        downloads   INT UNSIGNED NOT NULL DEFAULT 0,
        created_at  INT UNSIGNED NOT NULL,
        CONSTRAINT fk_csl_catalog FOREIGN KEY (catalog_id) REFERENCES catalogs(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS catalog_templates (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(150) NOT NULL,
        config_json LONGTEXT NOT NULL,
        created_by  INT UNSIGNED NULL,
        created_at  INT UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed default settings row-set if empty
    $count = (int)$db->query("SELECT COUNT(*) FROM catalog_pdf_settings")->fetchColumn();
    if ($count === 0) {
        $defaults = catalogPdfDefaultConfig();
        $now = time();
        $ins = $db->prepare("INSERT IGNORE INTO catalog_pdf_settings (`key`,`value`) VALUES (?,?)");
        foreach ($defaults as $k => $v) {
            $ins->execute(['default_' . $k, is_array($v) ? json_encode($v) : (string)$v]);
        }
    }

    // Seed example templates if empty
    $tcount = (int)$db->query("SELECT COUNT(*) FROM catalog_templates")->fetchColumn();
    if ($tcount === 0) {
        $now = time();
        $seedNames = ['Premium Catalog','Builder Catalog','Architect Catalog','Dealer Catalog','Export Catalog','Luxury Collection','Minimal Catalog'];
        $ins = $db->prepare("INSERT INTO catalog_templates (name, config_json, created_by, created_at) VALUES (?,?,NULL,?)");
        foreach ($seedNames as $name) {
            $cfg = catalogPdfDefaultConfig();
            $cfg['template_seed_name'] = $name;
            $ins->execute([$name, json_encode($cfg), $now]);
        }
    }

    $done = true;
}

// ── Full default config structure (used by settings page AND wizard prefill) ──
function catalogPdfDefaultConfig(): array {
    return [
        'layout'       => 'one_per_page', // one_per_page|two_per_page|four_per_page|grid|architect
        'fields'       => ['name','category','color_subcategory','thickness','sizes','cutter_size','origin','finish','quantity_available'],
        'cover' => [
            'bg_image' => '', 'logo' => 1, 'title' => APP_NAME, 'subtitle' => '',
            'show_date' => 1, 'date_format' => 'd M Y', 'version' => 'v1.0',
            'marketing_message' => '', 'contact_details' => 1, 'footer_text' => '',
        ],
        'closing' => [
            'enabled' => 1, 'thank_you_text' => 'Thank you for choosing ' . APP_NAME,
            'contact_info' => 1, 'gmap_qr' => 1, 'website_qr' => 1,
            'social_media' => 0, 'social_links' => [], 'sales_team' => 0, 'sales_team_list' => [],
        ],
        'watermark' => [
            'type' => 'none', 'custom_text' => '', 'opacity' => 15, 'rotation' => -45,
        ],
        'header' => ['logo' => 1, 'catalog_name' => 1, 'page_title' => 0],
        'footer' => ['page_number' => 1, 'website' => 1, 'email' => 0, 'phone' => 0, 'generated_date' => 0],
        'page_number_position' => 'bottom_center',
        'quality' => ['level' => 'medium', 'compression' => 'compress', 'optimize_size' => 1],
        'orientation' => 'portrait',
        'page_size' => 'A4',
        'custom_w_mm' => 210, 'custom_h_mm' => 297,
        'font' => 'helvetica',
        'colors' => [
            'primary' => '#2C6E8A', 'secondary' => '#1A4D65', 'accent' => '#B8975A',
            'background' => '#FFFFFF', 'text' => '#1A2837', 'button' => '#2C6E8A', 'border' => '#DDE4EB',
        ],
        'email_share' => [
            'default_subject' => 'Your ' . APP_NAME . ' Catalog',
            'default_message' => "Hi,\n\nPlease find attached the requested product catalog.\n\nRegards,\n" . APP_NAME,
        ],
    ];
}

// ── Load merged settings-defaults (flat key => value from DB, decoded) ────────
function getCatalogPdfSettingsDefaults(): array {
    ensureCatalogPdfTables();
    $rows = getDB()->query("SELECT `key`,`value` FROM catalog_pdf_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $out = [];
    foreach ($rows as $k => $v) {
        $key = preg_replace('/^default_/', '', $k);
        $decoded = json_decode($v, true);
        $out[$key] = (json_last_error() === JSON_ERROR_NONE && (is_array($decoded))) ? $decoded : $v;
    }
    return array_merge(catalogPdfDefaultConfig(), $out);
}

function saveCatalogPdfSettingsDefaults(array $config): void {
    ensureCatalogPdfTables();
    $db = getDB();
    $stmt = $db->prepare(
        "INSERT INTO catalog_pdf_settings (`key`,`value`) VALUES (?,?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    );
    foreach ($config as $k => $v) {
        $stmt->execute(['default_' . $k, is_array($v) ? json_encode($v) : (string)$v]);
    }
}
// ── RBAC: auto-seed catalog.* permissions ──────────────────────────────────
function ensureCatalogPdfPermissions(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = getDB();
        if (!$db->query("SHOW TABLES LIKE 'admin_permissions'")->fetch()) return;
        $perms = [
            ['catalog.view','View Catalog PDFs'],
            ['catalog.create','Create Catalog PDFs'],
            ['catalog.edit','Edit Catalog PDFs'],
            ['catalog.delete','Delete Catalog PDFs'],
            ['catalog.download','Download Catalog PDFs'],
            ['catalog.share','Share Catalog PDFs (link/email/whatsapp)'],
            ['catalog.template.manage','Manage Catalog Templates'],
            ['catalog.settings','Manage Catalog PDF Settings'],
            ['catalog.history','View Catalog PDF History'],
            ['catalog.regenerate','Regenerate Catalog PDFs'],
        ];
        $maxSort = (int)$db->query("SELECT COALESCE(MAX(sort_order),0) FROM admin_permissions")->fetchColumn();
        $chk = $db->prepare("SELECT id FROM admin_permissions WHERE action=?");
        $ins = $db->prepare("INSERT INTO admin_permissions (module, action, label, sort_order) VALUES ('Catalog PDF',?,?,?)");
        foreach ($perms as $p) {
            $chk->execute([$p[0]]);
            if (!$chk->fetch()) $ins->execute([$p[0], $p[1], ++$maxSort]);
        }
    } catch (Throwable $e) {
        error_log('ensureCatalogPdfPermissions: ' . $e->getMessage());
    }
}

// ── Basic CRUD (enough for settings page + later wizard steps) ────────────
function createCatalogDraft(array $data): array {
    $db = getDB();
    $now = time();
    $name = trim($data['name'] ?? 'Untitled Catalog');
    $db->prepare("INSERT INTO catalogs (name,user_id,admin_id,product_ids_json,config_json,status,created_at,updated_at)
                  VALUES (?,?,?,?,?,?,?,?)")
       ->execute([
           $name,
           $data['user_id']  ?? null,
           $data['admin_id'] ?? null,
           json_encode($data['product_ids'] ?? []),
           json_encode($data['config'] ?? catalogPdfDefaultConfig()),
           'draft', $now, $now,
       ]);
    return ['success' => true, 'id' => (int)$db->lastInsertId()];
}

function getCatalog(int $id): ?array {
    $st = getDB()->prepare("SELECT * FROM catalogs WHERE id=?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) return null;
    $row['product_ids'] = json_decode($row['product_ids_json'], true) ?: [];
    $row['config']       = json_decode($row['config_json'], true) ?: [];
    return $row;
}

function listCatalogs(array $opts = []): array {
    ensureCatalogPdfTables();
    $db = getDB();
    $search = trim($opts['search'] ?? '');
    $limit  = (int)($opts['limit']  ?? 20);
    $offset = (int)($opts['offset'] ?? 0);
    $where  = "WHERE 1=1"; $params = [];
    if ($search !== '') { $where .= " AND name LIKE ?"; $params[] = "%{$search}%"; }
    $cnt = $db->prepare("SELECT COUNT(*) FROM catalogs $where"); $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();
    $params[] = $limit; $params[] = $offset;
    $st = $db->prepare("SELECT * FROM catalogs $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $st->execute($params);
    return ['rows' => $st->fetchAll(), 'total' => $total];
}

function deleteCatalog(int $id): bool {
    $row = getCatalog($id);
    if ($row && !empty($row['pdf_path']) && file_exists($row['pdf_path'])) @unlink($row['pdf_path']);
    $st = getDB()->prepare("DELETE FROM catalogs WHERE id=?");
    $st->execute([$id]);
    return $st->rowCount() > 0;
}

// ── Email share (extends sendMail with attachment support) ────────────────
function sendCatalogPdfEmail(int $catalogId, string $to, string $subject, string $message): array {
    $cat = getCatalog($catalogId);
    if (!$cat || empty($cat['pdf_path']) || !file_exists($cat['pdf_path'])) {
        return ['success' => false, 'error' => 'PDF not generated yet.'];
    }
    require_once BASE_PATH . '/includes/mailer.php';
    $html = nl2br(h($message));
    $result = sendMail($to, $subject, $html, $message, '', [$cat['pdf_path']]); // 6th param = attachments (see mailer.php patch below)
    getDB()->prepare("INSERT INTO catalog_download_logs (catalog_id, channel, to_address, ip_address, success, created_at) VALUES (?,?,?,?,?,?)")
           ->execute([$catalogId, 'email', $to, $_SERVER['REMOTE_ADDR'] ?? '', $result['success'] ? 1 : 0, time()]);
    return $result;
}