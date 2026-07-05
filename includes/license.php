<?php
/**
 * includes/license.php
 * ─────────────────────────────────────────────────────────────────────────
 * Software Activation & License Expiry System.
 * Key generation, activation, server-side validation, admin management.
 * ─────────────────────────────────────────────────────────────────────────
 */

define('LIFETIME_YEAR_THRESHOLD', 2099);

// ── Schema bootstrap (idempotent, mirrors getDB()'s lazy-create pattern) ───
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

    $done = true;
}

// ── Key generation ──────────────────────────────────────────────────────────
// Cryptographically random, unambiguous alphabet (no 0/O/1/I), 20 chars in 4 groups.
function generateLicenseKey(): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $groups = [];
    for ($g = 0; $g < 4; $g++) {
        $chunk = '';
        $bytes = random_bytes(5);
        for ($i = 0; $i < 5; $i++) {
            $chunk .= $alphabet[ord($bytes[$i]) % strlen($alphabet)];
        }
        $groups[] = $chunk;
    }
    return implode('-', $groups); // e.g. AB3FQ-K2M4P-QW7RT-XY9ZL
}

function hashLicenseKey(string $key): string {
    return hash('sha256', strtoupper(trim($key)));
}

// Masked form for display anywhere except the one-time generation screen.
function maskLicenseKey(string $displayKey): string {
    $parts = explode('-', $displayKey);
    if (count($parts) < 2) return substr($displayKey, 0, 4) . '••••••••';
    return $parts[0] . '-••••-••••-' . end($parts);
}

// ── Admin: create a new license/key ─────────────────────────────────────────
function createLicense(array $data): array {
    ensureLicenseTables();
    $db = getDB();

    $customer = trim($data['customer_name'] ?? '');
    $project  = trim($data['project_name']  ?? '');
    $expiry   = trim($data['expiry_date']   ?? '');
    $lifetime = !empty($data['is_lifetime']);

    if (!$customer) return ['success' => false, 'error' => 'Customer name is required.'];
    if (!$project)  return ['success' => false, 'error' => 'Project name is required.'];
    if (!$expiry)   return ['success' => false, 'error' => 'Expiry date is required.'];

    $expiryTs = strtotime($expiry);
    if (!$expiryTs) return ['success' => false, 'error' => 'Invalid expiry date.'];
    if ((int)date('Y', $expiryTs) >= LIFETIME_YEAR_THRESHOLD) $lifetime = true;

    $now = time();
    // Retry on the astronomically unlikely event of a hash collision.
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $plainKey = generateLicenseKey();
        $hash     = hashLicenseKey($plainKey);
        $chk = $db->prepare("SELECT id FROM licenses WHERE key_hash=?");
        $chk->execute([$hash]);
        if (!$chk->fetch()) break;
        if ($attempt === 2) return ['success' => false, 'error' => 'Could not generate a unique key. Please try again.'];
    }

    $db->prepare("INSERT INTO licenses
        (customer_name, project_name, key_hash, key_display, expiry_date, is_lifetime, status, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?)")
       ->execute([$customer, $project, $hash, $plainKey, date('Y-m-d', $expiryTs), $lifetime ? 1 : 0, 'active', $now, $now]);

    $id = (int)$db->lastInsertId();
    logLicenseAction($id, 'created', 'Key generated for ' . $customer);

    return ['success' => true, 'id' => $id, 'plain_key' => $plainKey];
}

function logLicenseAction(int $licenseId, string $action, string $notes = ''): void {
    ensureLicenseTables();
    getDB()->prepare("INSERT INTO license_activation_log (license_id, action, ip_address, notes, created_at) VALUES (?,?,?,?,?)")
        ->execute([$licenseId, $action, $_SERVER['REMOTE_ADDR'] ?? '', $notes, time()]);
}

// ── Activate this installation with a key the customer enters ──────────────
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
    // bound_domain is set once on first activation; subsequent activations on
    // another domain will fail checkLicenseStatus()'s domain_mismatch check.
    $db->prepare("UPDATE licenses
                  SET status='active', activation_date=COALESCE(activation_date, ?),
                      activated_at=?, bound_domain=COALESCE(bound_domain, ?), updated_at=?
                  WHERE id=?")
       ->execute([$now, $now, $domain, $now, $lic['id']]);

    setSetting('license_activated_key_hash', $hash);
    setSetting('license_activated_id', (string)$lic['id']);
    logLicenseAction((int)$lic['id'], 'activated', 'Activated on domain ' . $domain);

    return ['success' => true];
}

// ── Core validation — server-side, called on every request ─────────────────
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
        // Settings value present but no matching row — tampering or DB reset.
        return ['valid' => false, 'state' => 'invalid', 'license' => null];
    }
    if ($lic['status'] === 'revoked') {
        return ['valid' => false, 'state' => 'revoked', 'license' => $lic];
    }

    // Domain binding — soft copy-protection; only enforced once a domain is bound.
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

// ── Middleware: call at the very top of every entry point ──────────────────
// Never trusts the frontend — re-checks server-side on every single request,
// so URL manipulation (?page=catalog etc.) cannot bypass an expired/invalid license.
function enforceLicense(string $currentPage = ''): void {
    if ($currentPage === 'activation') return; // the activation screen must always be reachable

    $result = checkLicenseStatus();
    if ($result['valid']) return;

    $_SESSION['license_block_state'] = $result['state'];
    $isAdminCtx = defined('ADMIN_PANEL') && ADMIN_PANEL;
    header('Location: ' . ($isAdminCtx ? '../index.php?page=activation' : 'index.php?page=activation'));
    exit;
}

// ── Admin management helpers ────────────────────────────────────────────────
function getAllLicenses(string $search = ''): array {
    ensureLicenseTables();
    $db = getDB();
    $sql = "SELECT * FROM licenses WHERE 1=1";
    $params = [];
    if ($search !== '') {
        $sql .= " AND (customer_name LIKE ? OR project_name LIKE ? OR key_display LIKE ?)";
        $like = "%{$search}%";
        $params = [$like, $like, $like];
    }
    $sql .= " ORDER BY created_at DESC";
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function getLicenseActivationHistory(int $licenseId): array {
    ensureLicenseTables();
    $st = getDB()->prepare("SELECT * FROM license_activation_log WHERE license_id=? ORDER BY created_at DESC");
    $st->execute([$licenseId]);
    return $st->fetchAll();
}

function updateLicenseExpiry(int $id, string $expiryDate, bool $lifetime): array {
    ensureLicenseTables();
    $expiryTs = strtotime($expiryDate);
    if (!$expiryTs) return ['success' => false, 'error' => 'Invalid expiry date.'];
    if ((int)date('Y', $expiryTs) >= LIFETIME_YEAR_THRESHOLD) $lifetime = true;

    getDB()->prepare("UPDATE licenses
                      SET expiry_date=?, is_lifetime=?, status=IF(status='expired','active',status), updated_at=?
                      WHERE id=?")
           ->execute([date('Y-m-d', $expiryTs), $lifetime ? 1 : 0, time(), $id]);
    logLicenseAction($id, 'updated', $lifetime ? 'Converted to lifetime license' : ('Expiry set to ' . date('Y-m-d', $expiryTs)));
    return ['success' => true];
}

function revokeLicense(int $id): void {
    ensureLicenseTables();
    getDB()->prepare("UPDATE licenses SET status='revoked', updated_at=? WHERE id=?")->execute([time(), $id]);
    logLicenseAction($id, 'revoked', 'Revoked by admin');
}

function reactivateLicense(int $id): void {
    ensureLicenseTables();
    $st = getDB()->prepare("SELECT expiry_date, is_lifetime FROM licenses WHERE id=?");
    $st->execute([$id]);
    $row = $st->fetch();
    $status = 'active';
    if ($row && !$row['is_lifetime'] && strtotime($row['expiry_date']) < strtotime(date('Y-m-d'))) {
        $status = 'expired';
    }
    getDB()->prepare("UPDATE licenses SET status=?, updated_at=? WHERE id=?")->execute([$status, time(), $id]);
    logLicenseAction($id, 'reactivated', 'Reactivated by admin');
}

function exportLicensesCsv(): void {
    ensureLicenseTables();
    $rows = getAllLicenses();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="licenses_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Customer','Project','Key','Activation Date','Expiry Date','Lifetime','Status','Bound Domain','Created At']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['customer_name'],
            $r['project_name'],
            maskLicenseKey($r['key_display']),
            $r['activation_date'] ? date('Y-m-d', $r['activation_date']) : '',
            $r['expiry_date'],
            $r['is_lifetime'] ? 'Yes' : 'No',
            $r['status'],
            $r['bound_domain'] ?? '',
            date('Y-m-d', $r['created_at']),
        ]);
    }
    fclose($out);
    exit;
}