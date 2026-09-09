<?php
/**
 * includes/marketing.php
 * ─────────────────────────────────────────────────────────────────────────
 * Marketing Automation Module — schema bootstrap + RBAC seeding.
 * Mirrors the ensureCatalogPdfTables()/ensureDeviceTables() idiom used
 * throughout the app: idempotent CREATE TABLE IF NOT EXISTS, static-cached
 * per request, called once near the top of index.php / admin/index.php.
 *
 * Fire 2 scope: tables + permission seeding + credential encryption helpers
 * only. CRUD/query helpers, campaign engine, queue worker, and providers
 * ship in later Fires and will require() this file.
 * ─────────────────────────────────────────────────────────────────────────
 */

// ── Schema bootstrap ────────────────────────────────────────────────────
function ensureMarketingTables(): void {
    static $done = false;
    if ($done) return;
    $db = getDB();

    // ── Contacts (thin linkage over users/clients, never a CRM duplicate) ──
    $db->exec("CREATE TABLE IF NOT EXISTS marketing_contacts (
        id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        company_id            INT UNSIGNED NOT NULL DEFAULT 1,
        source_type           ENUM('user','client','manual','import') NOT NULL DEFAULT 'manual',
        source_id              INT UNSIGNED NULL,
        name                  VARCHAR(200) NOT NULL,
        mobile                VARCHAR(20)  NULL,
        whatsapp_number       VARCHAR(20)  NULL,
        email                 VARCHAR(200) NULL,
        city                  VARCHAR(100) NULL,
        language              VARCHAR(5)   NOT NULL DEFAULT 'en',
        status                ENUM('active','inactive') NOT NULL DEFAULT 'active',
        whatsapp_opt_in       TINYINT(1) NOT NULL DEFAULT 1,
        email_opt_in          TINYINT(1) NOT NULL DEFAULT 1,
        last_whatsapp_sent_at INT UNSIGNED NULL,
        last_email_sent_at    INT UNSIGNED NULL,
        last_interaction_at   INT UNSIGNED NULL,
        created_at            INT UNSIGNED NOT NULL,
        updated_at            INT UNSIGNED NOT NULL,
        KEY idx_source (source_type, source_id),
        KEY idx_mobile (mobile),
        KEY idx_email  (email),
        KEY idx_city   (city)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS marketing_tags (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        company_id INT UNSIGNED NOT NULL DEFAULT 1,
        name       VARCHAR(60) NOT NULL,
        color      VARCHAR(20) NULL,
        created_at INT UNSIGNED NOT NULL,
        UNIQUE KEY uq_tag (company_id, name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS marketing_contact_tags (
        contact_id INT UNSIGNED NOT NULL,
        tag_id     INT UNSIGNED NOT NULL,
        PRIMARY KEY (contact_id, tag_id),
        CONSTRAINT fk_mct_contact FOREIGN KEY (contact_id) REFERENCES marketing_contacts(id) ON DELETE CASCADE,
        CONSTRAINT fk_mct_tag     FOREIGN KEY (tag_id)     REFERENCES marketing_tags(id)     ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS marketing_groups (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        company_id  INT UNSIGNED NOT NULL DEFAULT 1,
        name        VARCHAR(150) NOT NULL,
        type        ENUM('static','dynamic') NOT NULL DEFAULT 'static',
        filter_json TEXT NULL,
        created_at  INT UNSIGNED NOT NULL,
        updated_at  INT UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS marketing_group_contacts (
        group_id   INT UNSIGNED NOT NULL,
        contact_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (group_id, contact_id),
        CONSTRAINT fk_mgc_group   FOREIGN KEY (group_id)   REFERENCES marketing_groups(id)   ON DELETE CASCADE,
        CONSTRAINT fk_mgc_contact FOREIGN KEY (contact_id) REFERENCES marketing_contacts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Templates ────────────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS marketing_templates (
        id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        company_id              INT UNSIGNED NOT NULL DEFAULT 1,
        channel                 ENUM('whatsapp','email') NOT NULL,
        name                    VARCHAR(150) NOT NULL,
        category                VARCHAR(50) NULL,
        language                VARCHAR(10) NOT NULL DEFAULT 'en',
        subject                 VARCHAR(255) NULL,
        header_json             TEXT NULL,
        body                    TEXT NOT NULL,
        footer                  VARCHAR(255) NULL,
        buttons_json            TEXT NULL,
        provider_template_name  VARCHAR(150) NULL,
        approval_status         ENUM('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft',
        created_at              INT UNSIGNED NOT NULL,
        updated_at               INT UNSIGNED NOT NULL,
        KEY idx_channel (channel)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Campaigns ────────────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS marketing_campaigns (
        id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        company_id            INT UNSIGNED NOT NULL DEFAULT 1,
        name                  VARCHAR(200) NOT NULL,
        channel               ENUM('whatsapp','email','whatsapp_email') NOT NULL,
        template_id           INT UNSIGNED NULL,
        email_template_id     INT UNSIGNED NULL,
        audience_json         TEXT NOT NULL,
        variables_json        TEXT NULL,
        attach_catalog_id     INT UNSIGNED NULL,
        status                ENUM('draft','pending_approval','approved','scheduled','running',
                                    'paused','rate_limited','completed','cancelled','failed')
                                    NOT NULL DEFAULT 'draft',
        schedule_type         ENUM('now','once','recurring') NOT NULL DEFAULT 'now',
        scheduled_at          INT UNSIGNED NULL,
        timezone              VARCHAR(60) NOT NULL DEFAULT 'Asia/Kolkata',
        recurrence_json       TEXT NULL,
        created_by_admin_id   INT UNSIGNED NULL,
        approved_by_admin_id  INT UNSIGNED NULL,
        created_at            INT UNSIGNED NOT NULL,
        updated_at            INT UNSIGNED NOT NULL,
        KEY idx_status    (status),
        KEY idx_scheduled (scheduled_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS marketing_campaign_recipients (
        id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        campaign_id     INT UNSIGNED NOT NULL,
        contact_id      INT UNSIGNED NOT NULL,
        channel         ENUM('whatsapp','email') NOT NULL,
        idempotency_key CHAR(40) NOT NULL,
        status          ENUM('pending','queued','processing','sent','delivered','read',
                              'failed','cancelled','rate_limited','opted_out')
                              NOT NULL DEFAULT 'pending',
        created_at      INT UNSIGNED NOT NULL,
        UNIQUE KEY uq_recipient (idempotency_key),
        KEY idx_campaign (campaign_id, status),
        CONSTRAINT fk_mcr_campaign FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns(id) ON DELETE CASCADE,
        CONSTRAINT fk_mcr_contact  FOREIGN KEY (contact_id)  REFERENCES marketing_contacts(id)  ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Queue (the unit a worker claims) ────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS marketing_queue (
        id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        recipient_id     BIGINT UNSIGNED NOT NULL,
        channel          ENUM('whatsapp','email') NOT NULL,
        payload_json     TEXT NOT NULL,
        status           ENUM('pending','processing','sent','failed','cancelled','rate_limited')
                              NOT NULL DEFAULT 'pending',
        attempt_count    TINYINT UNSIGNED NOT NULL DEFAULT 0,
        max_attempts     TINYINT UNSIGNED NOT NULL DEFAULT 5,
        next_attempt_at  INT UNSIGNED NOT NULL,
        last_attempt_at  INT UNSIGNED NULL,
        error_code       VARCHAR(60) NULL,
        error_message    VARCHAR(500) NULL,
        locked_by        VARCHAR(40) NULL,
        locked_at        INT UNSIGNED NULL,
        created_at       INT UNSIGNED NOT NULL,
        KEY idx_dispatch (status, next_attempt_at),
        KEY idx_channel  (channel, status),
        CONSTRAINT fk_mq_recipient FOREIGN KEY (recipient_id) REFERENCES marketing_campaign_recipients(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Permanent message record (queue rows may later be pruned; this can't be) ──
    $db->exec("CREATE TABLE IF NOT EXISTS marketing_messages (
        id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        recipient_id          BIGINT UNSIGNED NOT NULL,
        campaign_id           INT UNSIGNED NULL,
        channel               ENUM('whatsapp','email') NOT NULL,
        provider              VARCHAR(40) NOT NULL,
        provider_message_id   VARCHAR(120) NULL,
        status                ENUM('sent','delivered','read','failed','bounced') NOT NULL,
        sent_at               INT UNSIGNED NULL,
        delivered_at          INT UNSIGNED NULL,
        read_at               INT UNSIGNED NULL,
        error_code            VARCHAR(60) NULL,
        error_message         VARCHAR(500) NULL,
        created_at            INT UNSIGNED NOT NULL,
        KEY idx_provider_msg (provider_message_id),
        KEY idx_campaign     (campaign_id),
        KEY idx_recipient    (recipient_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS marketing_message_events (
        id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        message_id BIGINT UNSIGNED NOT NULL,
        event_type VARCHAR(30) NOT NULL,
        meta_json  TEXT NULL,
        created_at INT UNSIGNED NOT NULL,
        KEY idx_message (message_id),
        CONSTRAINT fk_mme_message FOREIGN KEY (message_id) REFERENCES marketing_messages(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Atomic rate-limit counters (row-lock based, no Redis dependency) ──
    $db->exec("CREATE TABLE IF NOT EXISTS marketing_rate_counters (
        window_key  VARCHAR(80) PRIMARY KEY,
        sent_count  INT UNSIGNED NOT NULL DEFAULT 0,
        updated_at  INT UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS marketing_sending_limits (
        `key`   VARCHAR(60) PRIMARY KEY,
        `value` INT UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Suppression / opt-out ────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS marketing_suppression (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        contact_id INT UNSIGNED NOT NULL,
        channel    ENUM('whatsapp','email') NOT NULL,
        reason     VARCHAR(100) NULL,
        source     VARCHAR(40) NULL,
        created_at INT UNSIGNED NOT NULL,
        UNIQUE KEY uq_suppress (contact_id, channel),
        CONSTRAINT fk_msup_contact FOREIGN KEY (contact_id) REFERENCES marketing_contacts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Provider settings (encrypted at rest, see marketingEncrypt() below) ──
    $db->exec("CREATE TABLE IF NOT EXISTS marketing_provider_settings (
        `key`        VARCHAR(80) PRIMARY KEY,
        `value`      TEXT NULL,
        is_encrypted TINYINT(1) NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Automation ───────────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS marketing_automations (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(150) NOT NULL,
        trigger_type VARCHAR(60) NOT NULL,
        is_active   TINYINT(1) NOT NULL DEFAULT 1,
        created_at  INT UNSIGNED NOT NULL,
        updated_at  INT UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

   $db->exec("CREATE TABLE IF NOT EXISTS marketing_automation_steps (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        automation_id INT UNSIGNED NOT NULL,
        step_order    INT UNSIGNED NOT NULL,
        step_type     ENUM('condition','wait','action') NOT NULL,
        config_json   TEXT NOT NULL,
        CONSTRAINT fk_mas_automation FOREIGN KEY (automation_id) REFERENCES marketing_automations(id) ON DELETE CASCADE,
        KEY idx_automation (automation_id, step_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS marketing_automation_runs (
        id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        automation_id INT UNSIGNED NOT NULL,
        contact_id    INT UNSIGNED NOT NULL,
        current_step  INT UNSIGNED NOT NULL DEFAULT 0,
        next_run_at   INT UNSIGNED NOT NULL,
        status        ENUM('running','completed','cancelled') NOT NULL DEFAULT 'running',
        created_at    INT UNSIGNED NOT NULL,
        KEY idx_dispatch (status, next_run_at),
        CONSTRAINT fk_mar_automation FOREIGN KEY (automation_id) REFERENCES marketing_automations(id) ON DELETE CASCADE,
        CONSTRAINT fk_mar_contact    FOREIGN KEY (contact_id)    REFERENCES marketing_contacts(id)    ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Webhook idempotency log — providers may resend the same event ────
    $db->exec("CREATE TABLE IF NOT EXISTS marketing_webhooks (
        id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        provider     VARCHAR(40) NOT NULL,
        event_id     VARCHAR(120) NOT NULL,
        payload_json TEXT NOT NULL,
        processed    TINYINT(1) NOT NULL DEFAULT 0,
        created_at   INT UNSIGNED NOT NULL,
        UNIQUE KEY uq_webhook_event (provider, event_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Audit log — this app has no generic audit trail yet, so this one is
    // scoped to marketing actions only, mirroring the admin_id/ip_address
    // pattern already used by license_activation_log ─────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS marketing_audit_logs (
        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id    INT UNSIGNED NULL,
        action      VARCHAR(80) NOT NULL,
        entity_type VARCHAR(40) NULL,
        entity_id   INT UNSIGNED NULL,
        detail      TEXT NULL,
        ip_address  VARCHAR(64) NULL,
        created_at  INT UNSIGNED NOT NULL,
        KEY idx_entity (entity_type, entity_id),
        KEY idx_admin  (admin_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Seed default sending limits (only on first bootstrap) ────────────
    $limitCount = (int)$db->query("SELECT COUNT(*) FROM marketing_sending_limits")->fetchColumn();
    if ($limitCount === 0) {
        $defaults = [
            'whatsapp_hourly'          => 250,
            'whatsapp_daily'           => 1000,
            'email_hourly'             => 500,
            'email_daily'              => 2000,
            'max_campaign_size'        => 10000,
            'max_concurrent_campaigns' => 3,
        ];
        $ins = $db->prepare("INSERT IGNORE INTO marketing_sending_limits (`key`,`value`) VALUES (?,?)");
        foreach ($defaults as $k => $v) $ins->execute([$k, $v]);
    }

    $done = true;
}

// ── Credential encryption (AES-256-GCM) ─────────────────────────────────
// Mirrors the per-install-secret pattern in includes/device_auth.php
// (_deviceSecret()) — a key is generated once via random_bytes() and
// persisted through the normal settings table, never hard-coded.
function _marketingEncryptionKey(): string {
    static $key = null;
    if ($key !== null) return $key;
    $stored = getSetting('marketing_encryption_key', '');
    if ($stored === '') {
        $stored = bin2hex(random_bytes(32));
        setSetting('marketing_encryption_key', $stored);
    }
    $key = hex2bin($stored);
    return $key;
}

function marketingEncrypt(string $plain): string {
    if ($plain === '') return '';
    $iv  = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', _marketingEncryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) throw new RuntimeException('marketingEncrypt: openssl_encrypt failed.');
    // pack: iv(12) . tag(16) . ciphertext, then base64 for TEXT-column storage
    return base64_encode($iv . $tag . $cipher);
}

function marketingDecrypt(string $stored): string {
    if ($stored === '') return '';
    $raw = base64_decode($stored, true);
    if ($raw === false || strlen($raw) < 28) return '';
    $iv     = substr($raw, 0, 12);
    $tag    = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain  = openssl_decrypt($cipher, 'aes-256-gcm', _marketingEncryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? '' : $plain;
}

// ── Provider settings get/set — transparently encrypts flagged keys ─────
// Sensitive keys (access tokens, API secrets) are declared here once so
// every read/write path enforces encryption consistently — no call site
// can accidentally store a secret in plaintext.
define('MARKETING_ENCRYPTED_SETTING_KEYS', [
    'whatsapp_access_token',
    'whatsapp_webhook_verify_token',
    'whatsapp_app_secret',
]);

function getMarketingProviderSetting(string $key, string $default = ''): string {
    ensureMarketingTables();
    $st = getDB()->prepare("SELECT `value`, is_encrypted FROM marketing_provider_settings WHERE `key`=?");
    $st->execute([$key]);
    $row = $st->fetch();
    if (!$row) return $default;
    return $row['is_encrypted'] ? marketingDecrypt((string)$row['value']) : (string)$row['value'];
}

function setMarketingProviderSetting(string $key, string $value): void {
    ensureMarketingTables();
    $isEncrypted = in_array($key, MARKETING_ENCRYPTED_SETTING_KEYS, true);
    $stored = $isEncrypted ? marketingEncrypt($value) : $value;
    getDB()->prepare("INSERT INTO marketing_provider_settings (`key`,`value`,is_encrypted) VALUES (?,?,?)
                       ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), is_encrypted=VALUES(is_encrypted)")
           ->execute([$key, $stored, $isEncrypted ? 1 : 0]);
}

// ── Sending limits get/set ───────────────────────────────────────────────
function getMarketingLimit(string $key, int $default = 0): int {
    ensureMarketingTables();
    $st = getDB()->prepare("SELECT `value` FROM marketing_sending_limits WHERE `key`=?");
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v !== false ? (int)$v : $default;
}

function setMarketingLimit(string $key, int $value): void {
    ensureMarketingTables();
    getDB()->prepare("INSERT INTO marketing_sending_limits (`key`,`value`) VALUES (?,?)
                       ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")
           ->execute([$key, max(0, $value)]);
}

// ── RBAC: auto-seed marketing.* permissions (same idiom as
// ensureCatalogPdfPermissions() / ensureCategoryPermissions()) ──────────
function ensureMarketingPermissions(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = getDB();
        if (!$db->query("SHOW TABLES LIKE 'admin_permissions'")->fetch()) return;

        $perms = [
            ['marketing.contacts.view',    'View Marketing Contacts'],
            ['marketing.contacts.manage',  'Manage Marketing Contacts (create/edit/delete/import)'],
            ['marketing.groups.manage',    'Manage Contact Groups & Tags'],
            ['marketing.templates.manage', 'Manage WhatsApp & Email Templates'],
            ['marketing.campaigns.view',   'View Campaigns'],
            ['marketing.campaigns.create', 'Create & Edit Campaigns'],
            ['marketing.campaigns.approve','Approve Campaigns'],
            ['marketing.campaigns.send',   'Schedule / Send Campaigns'],
            ['marketing.automation.manage','Manage Automations'],
            ['marketing.reports.view',     'View Marketing Reports & Analytics'],
            ['marketing.settings.manage',  'Manage Marketing Settings (WhatsApp API, Email, Limits)'],
        ];

        $maxSort = (int)$db->query("SELECT COALESCE(MAX(sort_order),0) FROM admin_permissions")->fetchColumn();
        $chk = $db->prepare("SELECT id FROM admin_permissions WHERE action=?");
        $ins = $db->prepare("INSERT INTO admin_permissions (module, action, label, sort_order) VALUES ('Marketing',?,?,?)");
        foreach ($perms as $p) {
            $chk->execute([$p[0]]);
            if (!$chk->fetch()) $ins->execute([$p[0], $p[1], ++$maxSort]);
        }
    } catch (Throwable $e) {
        error_log('ensureMarketingPermissions: ' . $e->getMessage());
    }
}

// ── Atomic rate-limit slot consumption ───────────────────────────────────
// Row-lock based (SELECT ... FOR UPDATE), no Redis dependency. Safe under
// concurrent worker runs because the lock is held for the whole
// check-then-increment, not just the increment.
function _mktAtomicIncrementIfUnder(PDO $db, string $windowKey, int $limit): int|false {
    if ($limit <= 0) return 0; // 0 = unlimited for this window
    $db->prepare("INSERT IGNORE INTO marketing_rate_counters (window_key, sent_count, updated_at) VALUES (?, 0, ?)")
       ->execute([$windowKey, time()]);

    $st = $db->prepare("SELECT sent_count FROM marketing_rate_counters WHERE window_key=? FOR UPDATE");
    $st->execute([$windowKey]);
    $current = (int)$st->fetchColumn();

    if ($current >= $limit) return false;

    $db->prepare("UPDATE marketing_rate_counters SET sent_count = sent_count + 1, updated_at=? WHERE window_key=?")
       ->execute([time(), $windowKey]);
    return $current + 1;
}

/**
 * Fire 8: detailed version — tells the caller WHICH window was hit (hourly
 * vs daily) so the queue worker can set an accurate next_attempt_at instead
 * of always guessing "next hour". tryConsumeMarketingRateSlot() below stays
 * as a thin bool-returning wrapper so nothing that already called it breaks.
 */
function tryConsumeMarketingRateSlotDetailed(string $channel): array {
    ensureMarketingTables();
    $db = getDB();
    $hourlyLimit = getMarketingLimit($channel . '_hourly', 0);
    $dailyLimit  = getMarketingLimit($channel . '_daily', 0);

    $hourKey = $channel . ':hour:' . date('YmdH');
    $dayKey  = $channel . ':day:'  . date('Ymd');

    $db->beginTransaction();
    try {
        $hourOk = _mktAtomicIncrementIfUnder($db, $hourKey, $hourlyLimit);
        if ($hourOk === false) {
            $db->rollBack();
            return ['allowed' => false, 'retry_after' => marketingNextHourBoundary(), 'limit_hit' => 'hourly'];
        }

        $dayOk = _mktAtomicIncrementIfUnder($db, $dayKey, $dailyLimit);
        if ($dayOk === false) {
            $db->rollBack();
            return ['allowed' => false, 'retry_after' => marketingNextDayBoundary(), 'limit_hit' => 'daily'];
        }

        $db->commit();
        return ['allowed' => true];
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('tryConsumeMarketingRateSlotDetailed: ' . $e->getMessage());
        return ['allowed' => false, 'retry_after' => marketingNextHourBoundary(), 'limit_hit' => 'error'];
    }
}

function tryConsumeMarketingRateSlot(string $channel): bool {
    return tryConsumeMarketingRateSlotDetailed($channel)['allowed'];
}

/** Seconds until the next hourly window opens — used to set next_attempt_at on rate_limited rows. */
function marketingNextHourBoundary(): int {
    return (int)(strtotime(date('Y-m-d H:00:00')) + 3600);
}

/** Seconds until the next daily window opens. */
function marketingNextDayBoundary(): int {
    return (int)(strtotime('tomorrow'));
}

// ── Idempotency key builder — used when enqueueing campaign recipients ──
function marketingIdempotencyKey(int $campaignId, int $contactId, string $channel): string {
    return sha1($campaignId . ':' . $contactId . ':' . $channel);
}

// ── Audit logging ────────────────────────────────────────────────────────
function logMarketingAudit(string $action, ?string $entityType = null, ?int $entityId = null, string $detail = ''): void {
    try {
        ensureMarketingTables();
        getDB()->prepare("INSERT INTO marketing_audit_logs (admin_id, action, entity_type, entity_id, detail, ip_address, created_at)
                           VALUES (?,?,?,?,?,?,?)")
               ->execute([
                   $_SESSION['admin_id'] ?? null,
                   $action,
                   $entityType,
                   $entityId,
                   $detail,
                   $_SERVER['REMOTE_ADDR'] ?? '',
                   time(),
               ]);
    } catch (Throwable $e) {
        error_log('logMarketingAudit: ' . $e->getMessage());
    }
}