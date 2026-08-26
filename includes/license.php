<?php
/**
 * includes/license.php
 * Runtime license concerns only: activate the ONE license this install
 * holds, validate it on every request, delete it. Generation lives in
 * Scripts/license_generator.php (vendor tool, not shipped in the UI).
 */

define('LIFETIME_YEAR_THRESHOLD', 2099);

function ensureLicenseTables(): void {
    static $done = false;
    if ($done) return;
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS licenses (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_name   VARCHAR(200) NOT NULL,
        project_name    VARCHAR(200) NOT NULL,
        key_hash        VARCHAR(64)  NOT NULL,
        key_display     VARCHAR(40)  NOT NULL,
        activation_date INT UNSIGNED NULL,
        expiry_date     DATE NOT NULL,
        is_lifetime     TINYINT(1) NOT NULL DEFAULT 0,
        plan            VARCHAR(20) NOT NULL DEFAULT 'lite',
        status          VARCHAR(20) NOT NULL DEFAULT 'active',
        bound_domain    VARCHAR(255) NULL,
        activated_at    INT UNSIGNED NULL,
        created_at      INT UNSIGNED NOT NULL,
        updated_at      INT UNSIGNED NOT NULL,
        UNIQUE KEY uq_key_hash (key_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS license_activation_log (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        license_id  INT UNSIGNED NOT NULL,
        action      VARCHAR(50) NOT NULL,
        ip_address  VARCHAR(64),
        notes       VARCHAR(255),
        created_at  INT UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try {
        $col = $db->query("SHOW COLUMNS FROM licenses LIKE 'plan'")->fetch();
        if (!$col) $db->exec("ALTER TABLE licenses ADD COLUMN plan VARCHAR(20) NOT NULL DEFAULT 'lite' AFTER is_lifetime");
    } catch (Throwable $e) {
        error_log('ensureLicenseTables: plan column migration failed: ' . $e->getMessage());
    }

    $done = true;
}

function hashLicenseKey(string $key): string {
    return hash('sha256', strtoupper(trim($key)));
}

function maskLicenseKey(string $displayKey): string {
    $parts = explode('-', $displayKey);
    if (count($parts) < 2) return substr($displayKey, 0, 4) . '••••••••';
    return $parts[0] . '-••••-••••-' . end($parts);
}

function logLicenseAction(int $licenseId, string $action, string $notes = ''): void {
    ensureLicenseTables();
    getDB()->prepare("INSERT INTO license_activation_log (license_id, action, ip_address, notes, created_at) VALUES (?,?,?,?,?)")
        ->execute([$licenseId, $action, $_SERVER['REMOTE_ADDR'] ?? '', $notes, time()]);
}

/**
 * Activates this installation with a key. Enforces the single-license
 * invariant: any OTHER license row (and its log entries) is deleted so
 * this install never holds more than the one key just activated.
 */
function activateLicenseKey(string $inputKey): array {
    ensureLicenseTables();
    $db   = getDB();
    $hash = hashLicenseKey($inputKey);

    $st = $db->prepare("SELECT * FROM licenses WHERE key_hash=?");
    $st->execute([$hash]);
    $lic = $st->fetch();

    if (!$lic) {
        return ['success' => false, 'error' => 'Invalid activation key.'];
    }
    if ($lic['status'] === 'revoked') {
        return ['success' => false, 'error' => 'This activation key has been revoked.'];
    }
    if (!$lic['is_lifetime'] && strtotime($lic['expiry_date']) < strtotime(date('Y-m-d'))) {
        $db->prepare("UPDATE licenses SET status='expired', updated_at=? WHERE id=?")->execute([time(), $lic['id']]);
        return ['success' => false, 'error' => 'This activation key expired on ' . date('d/m/Y', strtotime($lic['expiry_date'])) . '.'];
    }

    $domain = $_SERVER['HTTP_HOST'] ?? '';
    $now    = time();

    $db->beginTransaction();
    try {
        // Single-license invariant.
        $db->prepare("DELETE FROM license_activation_log WHERE license_id != ?")->execute([$lic['id']]);
        $db->prepare("DELETE FROM licenses WHERE id != ?")->execute([$lic['id']]);

        $db->prepare("UPDATE licenses
                      SET status='active', activation_date=COALESCE(activation_date, ?),
                          activated_at=?, bound_domain=COALESCE(bound_domain, ?), updated_at=?
                      WHERE id=?")
           ->execute([$now, $now, $domain, $now, $lic['id']]);

        setSetting('license_activated_key_hash', $hash);
        setSetting('license_activated_id', (string)$lic['id']);
        logLicenseAction((int)$lic['id'], 'activated', 'Activated on domain ' . $domain);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        return ['success' => false, 'error' => 'Activation failed: ' . $e->getMessage()];
    }

    return ['success' => true];
}

/**
 * Deletes the currently-bound license entirely and clears activation
 * settings, returning this install to the "not activated" state.
 */
function deleteCurrentLicense(): array {
    ensureLicenseTables();
    $db   = getDB();
    $hash = getSetting('license_activated_key_hash', '');
    if ($hash === '') {
        return ['success' => false, 'error' => 'No license is currently activated.'];
    }

    $db->beginTransaction();
    try {
        $st = $db->prepare("SELECT id, customer_name FROM licenses WHERE key_hash=?");
        $st->execute([$hash]);
        $lic = $st->fetch();

        if ($lic) {
            error_log('License deleted via admin panel: id=' . $lic['id'] . ' customer=' . $lic['customer_name']);
            $db->prepare("DELETE FROM license_activation_log WHERE license_id=?")->execute([$lic['id']]);
            $db->prepare("DELETE FROM licenses WHERE id=?")->execute([$lic['id']]);
        }
        $db->exec("DELETE FROM settings WHERE `key` IN ('license_activated_key_hash','license_activated_id')");
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        return ['success' => false, 'error' => 'Could not delete license: ' . $e->getMessage()];
    }

    @unlink(SETTINGS_CACHE_FILE); // mirrors setSetting()'s cache invalidation

    return ['success' => true];
}

function checkLicenseStatus(): array {
    ensureLicenseTables();
    $hash = getSetting('license_activated_key_hash', '');

    if ($hash === '') {
        return ['valid' => false, 'state' => 'not_activated', 'license' => null];
    }

    $st = getDB()->prepare("SELECT * FROM licenses WHERE key_hash=?");
    $st->execute([$hash]);
    $lic = $st->fetch();

    if (!$lic) {
        return ['valid' => false, 'state' => 'invalid', 'license' => null];
    }
    if ($lic['status'] === 'revoked') {
        return ['valid' => false, 'state' => 'revoked', 'license' => $lic];
    }

    $domain = $_SERVER['HTTP_HOST'] ?? '';
    if (!empty($lic['bound_domain']) && !empty($domain) && strcasecmp($lic['bound_domain'], $domain) !== 0) {
        return ['valid' => false, 'state' => 'domain_mismatch', 'license' => $lic];
    }

    $isLifetime = (int)$lic['is_lifetime'] === 1 || (int)date('Y', strtotime($lic['expiry_date'])) >= LIFETIME_YEAR_THRESHOLD;
    if ($isLifetime) {
        return ['valid' => true, 'state' => 'lifetime', 'license' => $lic];
    }

    if (strtotime($lic['expiry_date']) < strtotime(date('Y-m-d'))) {
        if ($lic['status'] !== 'expired') {
            getDB()->prepare("UPDATE licenses SET status='expired', updated_at=? WHERE id=?")->execute([time(), $lic['id']]);
        }
        return ['valid' => false, 'state' => 'expired', 'license' => $lic];
    }

    return ['valid' => true, 'state' => 'active', 'license' => $lic];
}

function enforceLicense(string $currentPage = ''): void {
    if ($currentPage === 'activation') return;

    $result = checkLicenseStatus();
    if ($result['valid']) return;

    $_SESSION['license_block_state'] = $result['state'];
    $isAdminCtx = defined('ADMIN_PANEL') && ADMIN_PANEL;
    header('Location: ' . ($isAdminCtx ? '../index.php?page=activation' : 'index.php?page=activation'));
    exit;
}