<?php

define('DEVICE_COOKIE_NAME', 'trusted_device');
define('DEVICE_COOKIE_TTL',  86400 * 90); // 90 days

// ── Schema bootstrap (idempotent, mirrors ensureLicenseTables() pattern) ───
function ensureDeviceTables(): void {
    static $done = false;
    if ($done) return;
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS trusted_devices (
        id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id            INT UNSIGNED NULL,
        admin_id           INT UNSIGNED NULL,
        panel              ENUM('admin','user','both') NOT NULL DEFAULT 'user',
        device_name        VARCHAR(150) NOT NULL DEFAULT 'Unnamed Device',
        device_token_hash  VARCHAR(64)  NOT NULL,
        fingerprint_hash   VARCHAR(64)  NULL,
        ip_last            VARCHAR(64)  NULL,
        host_name          VARCHAR(150) NULL,
        user_agent         VARCHAR(255) NULL,
        status             ENUM('active','disabled') NOT NULL DEFAULT 'active',
        last_login         INT UNSIGNED NULL,
        last_seen          INT UNSIGNED NULL,
        created_at         INT UNSIGNED NOT NULL,
        updated_at         INT UNSIGNED NOT NULL,
        revoked_at         INT UNSIGNED NULL,
        UNIQUE KEY uq_device_token (device_token_hash),
        KEY idx_user   (user_id),
        KEY idx_admin  (admin_id),
        KEY idx_status (status),
        CONSTRAINT fk_td_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
        CONSTRAINT fk_td_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS device_login_history (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        device_id   INT UNSIGNED NOT NULL,
        ip_address  VARCHAR(64)  NULL,
        user_agent  VARCHAR(255) NULL,
        success     TINYINT(1) NOT NULL DEFAULT 1,
        reason      VARCHAR(150) NULL,
        created_at  INT UNSIGNED NOT NULL,
        KEY idx_device (device_id),
        CONSTRAINT fk_dlh_device FOREIGN KEY (device_id) REFERENCES trusted_devices(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS device_activity_logs (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        device_id   INT UNSIGNED NULL,
        event       VARCHAR(60)  NOT NULL,
        detail      VARCHAR(255) NULL,
        ip_address  VARCHAR(64)  NULL,
        created_at  INT UNSIGNED NOT NULL,
        KEY idx_device (device_id),
        KEY idx_event  (event)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $done = true;
}

// ── Hashing helpers ─────────────────────────────────────────────────────────
function _deviceTokenHash(string $plain): string {
    // Signed with app-local secret so a raw DB leak alone can't be replayed
    // without also compromising the cookie value's signature.
    return hash_hmac('sha256', $plain, _deviceSecret());
}

function _deviceSecret(): string {
    // Derive a stable per-install secret from settings (created once, cached).
    static $secret = null;
    if ($secret !== null) return $secret;
    $secret = getSetting('device_auth_secret', '');
    if ($secret === '') {
        $secret = bin2hex(random_bytes(32));
        setSetting('device_auth_secret', $secret);
    }
    return $secret;
}

/**
 * Build a fingerprint hash from signals available server-side without any
 * client JS requirement (User-Agent + Accept-Language + Accept headers).
 * Callers may pass an additional client-supplied fingerprint (e.g. canvas/
 * screen-based) to strengthen this — optional, backward compatible.
 */
function buildDeviceFingerprint(string $extra = ''): string {
    $raw = ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|'
         . ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') . '|'
         . ($_SERVER['HTTP_ACCEPT'] ?? '') . '|'
         . $extra;
    return hash('sha256', $raw);
}

// ── Issue a new trusted-device token, set cookie, insert DB row ────────────
function issueTrustedDevice(array $opts): array {
    ensureDeviceTables();

   if (licenseCapExceeded('trusted_devices')) {
        return ['success' => false, 'error' => licenseCapExceededMessage('trusted_devices')];
    }
    $userId   = $opts['user_id']   ?? null;
    $adminId  = $opts['admin_id']  ?? null;
    $panel    = $opts['panel']     ?? ($adminId ? 'admin' : 'user');
    $name     = trim($opts['device_name'] ?? '') ?: 'Unnamed Device';
    $extraFp  = $opts['fingerprint_extra'] ?? '';

    if (!$userId && !$adminId) {
        return ['success' => false, 'error' => 'No user or admin specified.'];
    }

    $plainToken = bin2hex(random_bytes(32));
    $tokenHash  = _deviceTokenHash($plainToken);
    $fpHash     = buildDeviceFingerprint($extraFp);
    $ip         = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua         = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $now        = time();

    $db = getDB();
    $db->prepare("INSERT INTO trusted_devices
        (user_id, admin_id, panel, device_name, device_token_hash, fingerprint_hash,
         ip_last, user_agent, status, last_login, last_seen, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([$userId, $adminId, $panel, $name, $tokenHash, $fpHash,
                   $ip, $ua, 'active', $now, $now, $now, $now]);

    $deviceId = (int)$db->lastInsertId();

    setcookie(DEVICE_COOKIE_NAME, $deviceId . ':' . $plainToken, [
        'expires'  => time() + DEVICE_COOKIE_TTL,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    logDeviceActivity($deviceId, 'device_registered', $name);

    return ['success' => true, 'device_id' => $deviceId];
}

/**
 * Verify the trusted-device cookie (if present) against DB.
 * Returns device row on success (status active, hash + fingerprint match),
 * or null otherwise. Does NOT create a session — caller decides that.
 * Never trusts the cookie's device_id alone; always re-derives + compares
 * the HMAC hash server-side.
 */
function verifyTrustedDeviceCookie(): ?array {
    ensureDeviceTables();

    $raw = $_COOKIE[DEVICE_COOKIE_NAME] ?? '';
    if ($raw === '' || !str_contains($raw, ':')) return null;

    [$idPart, $tokenPart] = explode(':', $raw, 2);
    $deviceId = (int)$idPart;
    if (!$deviceId || $tokenPart === '') return null;

    $db = getDB();
    $st = $db->prepare("SELECT * FROM trusted_devices WHERE id=? LIMIT 1");
    $st->execute([$deviceId]);
    $device = $st->fetch();
    if (!$device) return null;

    $expectedHash = $device['device_token_hash'];
    $suppliedHash = _deviceTokenHash($tokenPart);
    if (!hash_equals($expectedHash, $suppliedHash)) {
        logDeviceLoginAttempt($deviceId, false, 'token_mismatch');
        return null;
    }

    if ($device['status'] !== 'active') {
        logDeviceLoginAttempt($deviceId, false, 'device_disabled');
        return null;
    }

    // Fingerprint check — soft, logs mismatch but does not hard-fail by
    // default (UA strings drift across browser updates); can be tightened
    // via device_auth_strict_fingerprint setting later if desired.
    $fp = buildDeviceFingerprint();
    if (!empty($device['fingerprint_hash']) && !hash_equals($device['fingerprint_hash'], $fp)) {
        logDeviceActivity($deviceId, 'fingerprint_mismatch', 'UA/lang signals changed');
    }

    return $device;
}

// ── Mark successful auto-login (updates last_login/last_seen/ip) ───────────
function markDeviceLoginSuccess(int $deviceId): void {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? null;
    $now = time();
    getDB()->prepare("UPDATE trusted_devices SET last_login=?, last_seen=?, ip_last=?, updated_at=? WHERE id=?")
           ->execute([$now, $now, $ip, $now, $deviceId]);
    logDeviceLoginAttempt($deviceId, true, null);
}

// ── Revoke a single device ──────────────────────────────────────────────────
function revokeTrustedDevice(int $deviceId): void {
    getDB()->prepare("UPDATE trusted_devices SET status='disabled', revoked_at=?, updated_at=? WHERE id=?")
           ->execute([time(), time(), $deviceId]);
    logDeviceActivity($deviceId, 'device_revoked', '');
}

// ── Revoke ALL devices for a user/admin (call on password change) ──────────
function revokeAllDevicesFor(?int $userId, ?int $adminId): void {
    $db = getDB();
    if ($userId) {
        $db->prepare("UPDATE trusted_devices SET status='disabled', revoked_at=?, updated_at=? WHERE user_id=?")
           ->execute([time(), time(), $userId]);
    }
    if ($adminId) {
        $db->prepare("UPDATE trusted_devices SET status='disabled', revoked_at=?, updated_at=? WHERE admin_id=?")
           ->execute([time(), time(), $adminId]);
    }
}

// ── Logging helpers ──────────────────────────────────────────────────────────
function logDeviceLoginAttempt(int $deviceId, bool $success, ?string $reason): void {
    try {
        getDB()->prepare("INSERT INTO device_login_history (device_id, ip_address, user_agent, success, reason, created_at) VALUES (?,?,?,?,?,?)")
               ->execute([
                   $deviceId,
                   $_SERVER['REMOTE_ADDR'] ?? null,
                   mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                   $success ? 1 : 0,
                   $reason,
                   time(),
               ]);
    } catch (Throwable $e) { error_log('logDeviceLoginAttempt: ' . $e->getMessage()); }
}

function logDeviceActivity(?int $deviceId, string $event, string $detail = ''): void {
    try {
        getDB()->prepare("INSERT INTO device_activity_logs (device_id, event, detail, ip_address, created_at) VALUES (?,?,?,?,?)")
               ->execute([$deviceId, $event, $detail, $_SERVER['REMOTE_ADDR'] ?? null, time()]);
    } catch (Throwable $e) { error_log('logDeviceActivity: ' . $e->getMessage()); }
}

// ── Clear the device cookie (does not delete DB row — use revoke for that) ──
function clearDeviceCookie(): void {
    setcookie(DEVICE_COOKIE_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// ── Fetch helpers for admin/user device management views (Fires 4/5) ───────
function getUserDevices(int $userId): array {
    ensureDeviceTables();
    $st = getDB()->prepare("SELECT * FROM trusted_devices WHERE user_id=? ORDER BY last_seen DESC, created_at DESC");
    $st->execute([$userId]);
    return $st->fetchAll();
}

function getAdminDevices(int $adminId): array {
    ensureDeviceTables();
    $st = getDB()->prepare("SELECT * FROM trusted_devices WHERE admin_id=? ORDER BY last_seen DESC, created_at DESC");
    $st->execute([$adminId]);
    return $st->fetchAll();
}

// ── Rename a device (ownership checked by caller before calling this) ──────
function renameDevice(int $deviceId, string $newName): void {
    $newName = trim($newName);
    if ($newName === '') return;
    getDB()->prepare("UPDATE trusted_devices SET device_name=?, updated_at=? WHERE id=?")
           ->execute([mb_substr($newName, 0, 150), time(), $deviceId]);
}

// ── Rate-limited cookie verification — caps brute-force token guessing.
// Wraps verifyTrustedDeviceCookie() with a per-IP throttle so repeated bad
// cookies (forged/guessed) can't be replayed indefinitely against the DB.
function verifyTrustedDeviceCookieThrottled(): ?array {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'device_verify:' . $ip;
    if (!throttle($key, 30, 300)) { // 30 attempts / 5 min / IP
        logDeviceActivity(null, 'device_verify_rate_limited', $ip);
        return null;
    }
    return verifyTrustedDeviceCookie();
}

function adminListAllDevices(array $opts = []): array {
    ensureDeviceTables();
    $db      = getDB();
    $search  = trim($opts['search'] ?? '');
    $limit   = (int)($opts['limit']  ?? 25);
    $offset  = (int)($opts['offset'] ?? 0);
    $ownerAdminId = $opts['admin_id'] ?? null; // null = no owner restriction (super admin)

    $where  = "WHERE 1=1";
    $params = [];

    if ($ownerAdminId !== null) {
        $where   .= " AND td.admin_id = ?";
        $params[] = (int)$ownerAdminId;
    }

    if ($search !== '') {
        $where   .= " AND (td.device_name LIKE ? OR td.ip_last LIKE ? OR u.name LIKE ? OR a.name LIKE ?)";
        $like     = "%{$search}%";
        $params   = array_merge($params, [$like, $like, $like, $like]);
    }

    $countSt = $db->prepare("SELECT COUNT(*) FROM trusted_devices td
        LEFT JOIN users u  ON u.id  = td.user_id
        LEFT JOIN admins a ON a.id  = td.admin_id
        $where");
    $countSt->execute($params);
    $total = (int)$countSt->fetchColumn();

    $sql = "SELECT td.*, u.name AS user_name, a.name AS admin_name
            FROM trusted_devices td
            LEFT JOIN users u  ON u.id  = td.user_id
            LEFT JOIN admins a ON a.id  = td.admin_id
            $where
            ORDER BY td.last_seen DESC, td.created_at DESC
            LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $st = $db->prepare($sql);
    $st->execute($params);

    return ['rows' => $st->fetchAll(), 'total' => $total];
}

// ── Full auto-login attempt: verify cookie, panel-match, set session ───────
// Returns true if session was auto-authenticated, false otherwise (falls
// through to normal login page — caller does nothing extra on false).
function attemptDeviceAutoLogin(string $panel): bool {
    if ($panel === 'user'  && isLoggedIn()) return false;
    if ($panel === 'admin' && isAdmin())    return false;

    $device = verifyTrustedDeviceCookieThrottled();
    if (!$device) return false;

    if ($device['panel'] !== 'both' && $device['panel'] !== $panel) {
        logDeviceActivity((int)$device['id'], 'panel_mismatch', "wanted={$panel} have={$device['panel']}");
        return false;
    }

    if ($panel === 'user') {
        if (!$device['user_id']) return false;
        $st = getDB()->prepare("SELECT is_verified FROM users WHERE id=?");
        $st->execute([$device['user_id']]);
        if (!(int)$st->fetchColumn()) {
            logDeviceLoginAttempt((int)$device['id'], false, 'user_not_verified');
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$device['user_id'];
    } else {
        if (!$device['admin_id']) return false;
        $st = getDB()->prepare("SELECT name, is_active FROM admins WHERE id=?");
        $st->execute([$device['admin_id']]);
        $adminRow = $st->fetch();
        if (!$adminRow || (array_key_exists('is_active', $adminRow) && !(int)$adminRow['is_active'])) {
            logDeviceLoginAttempt((int)$device['id'], false, 'admin_inactive');
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['admin_id']   = (int)$device['admin_id'];
        $_SESSION['admin_name'] = $adminRow['name'] ?: 'Admin';
        unset($_SESSION['admin_permissions'], $_SESSION['admin_role_slug']); // force reload via rbac.php
    }

    markDeviceLoginSuccess((int)$device['id']);
    logDeviceActivity((int)$device['id'], 'auto_login', $panel);
    return true;
}
// ── Get current trusted device ONLY if it belongs to the currently
// logged-in session (user or admin). Used to decide whether Logout
// should be a plain logout or a Forced Logout (double-confirm + revoke).
function getCurrentTrustedDevice(string $panel): ?array {
    $device = verifyTrustedDeviceCookie();
    if (!$device) return null;

    if ($panel === 'user' && !empty($device['user_id'])
        && (int)$device['user_id'] === (int)($_SESSION['user_id'] ?? 0)) {
        return $device;
    }
    if ($panel === 'admin' && !empty($device['admin_id'])
        && (int)$device['admin_id'] === (int)($_SESSION['admin_id'] ?? 0)) {
        return $device;
    }
    return null;
}

function touchTrustedDeviceLastSeen(array $device): void {
    $now = time();
    if (!empty($device['last_seen']) && ($now - (int)$device['last_seen']) < 43200) {
        return;
    }
    getDB()->prepare("UPDATE trusted_devices SET last_seen=?, ip_last=?, updated_at=? WHERE id=?")
           ->execute([$now, $_SERVER['REMOTE_ADDR'] ?? null, $now, (int)$device['id']]);
}