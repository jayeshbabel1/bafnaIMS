<?php

// null = unlimited
const LICENSE_PLAN_CAPS = [
    'demo' => [
        'label'            => 'Demo',
        'products'         => 25,
        'client_selections'=> 10,
        'users'            => 10,
        'admins'           => 2,
        'trusted_devices'  => 1,
    ],
    'lite' => [
        'label'            => 'Lite',
        'products'         => 50,
        'client_selections'=> 20,
        'users'            => 20,
        'admins'           => 5,
        'trusted_devices'  => 2,
    ],
    'pro' => [
        'label'            => 'Pro',
        'products'         => 200,
        'client_selections'=> 50,
        'users'            => 50,
        'admins'           => 50,
        'trusted_devices'  => 5,
    ],
    'pro_plus' => [
        'label'            => 'Pro Plus',
        'products'         => null,
        'client_selections'=> null,
        'users'            => null,
        'admins'           => null,
        'trusted_devices'  => null,
    ],
];

// Human labels for the usage dashboard / error messages.
const LICENSE_CAP_LABELS = [
    'products'          => 'Products',
    'client_selections' => 'Client Selections',
    'users'             => 'Users',
    'admins'            => 'Admin Accounts',
    'trusted_devices'   => 'Trusted Devices',
];

/**
 * Resolve the active license's plan key. Falls back to the tightest
 * tier ('demo') if no valid license is found — enforceLicense() already
 * blocks unlicensed access at the page level, so this is a safety net
 * for any code path that runs before/without that gate (e.g. background
 * cron-style helpers).
 */
function getCurrentLicensePlan(): string {
    static $cache = null;
    if ($cache !== null) return $cache;

    try {
        $status = checkLicenseStatus();
        $plan = $status['license']['plan'] ?? 'demo';
    } catch (Throwable $e) {
        $plan = 'demo';
    }
    $cache = array_key_exists($plan, LICENSE_PLAN_CAPS) ? $plan : 'demo';
    return $cache;
}

/**
 * Full cap row for the current plan, e.g.
 * ['label'=>'Lite','products'=>50,'client_selections'=>20,...]
 */
function getCurrentLicenseCaps(): array {
    return LICENSE_PLAN_CAPS[getCurrentLicensePlan()];
}

/**
 * Current row count for a given cap key, via a direct COUNT(*).
 * Kept as a small match statement rather than a generic table-name param
 * so this file never becomes an arbitrary SQL-count injection point.
 */
function _licenseCapCurrentCount(string $capKey): int {
    $db = getDB();
    try {
        switch ($capKey) {
            case 'products':
                return (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
            case 'client_selections':
                return (int)$db->query("SELECT COUNT(*) FROM client_selections")->fetchColumn();
            case 'users':
                return (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
            case 'admins':
                return (int)$db->query("SELECT COUNT(*) FROM admins")->fetchColumn();
            case 'trusted_devices':
                return (int)$db->query("SELECT COUNT(*) FROM trusted_devices WHERE status='active'")->fetchColumn();
            default:
                return 0;
        }
    } catch (Throwable $e) {
        // Table may not exist yet on a very fresh install — treat as 0 used.
        error_log('_licenseCapCurrentCount(' . $capKey . '): ' . $e->getMessage());
        return 0;
    }
}

/**
 * ['used'=>int, 'limit'=>int|null, 'remaining'=>int|null, 'label'=>string]
 * limit/remaining are null when the current plan is unlimited for this cap.
 */
function getLicenseCapUsage(string $capKey): array {
    $caps  = getCurrentLicenseCaps();
    $limit = $caps[$capKey] ?? null;
    $used  = _licenseCapCurrentCount($capKey);
    return [
        'used'      => $used,
        'limit'     => $limit,
        'remaining' => $limit === null ? null : max(0, $limit - $used),
        'label'     => LICENSE_CAP_LABELS[$capKey] ?? ucfirst(str_replace('_', ' ', $capKey)),
    ];
}

/**
 * True if the plan's cap for $capKey has been reached or exceeded.
 * Always false for unlimited caps.
 */
function licenseCapExceeded(string $capKey): bool {
    $usage = getLicenseCapUsage($capKey);
    if ($usage['limit'] === null) return false;
    return $usage['used'] >= $usage['limit'];
}

/**
 * Friendly error string for use in flash()/JSON error responses when a
 * cap-gated create action is blocked.
 */
function licenseCapExceededMessage(string $capKey): string {
    $usage = getLicenseCapUsage($capKey);
    $plan  = getCurrentLicenseCaps()['label'];
    return "{$usage['label']} limit reached for your {$plan} plan ({$usage['used']}/{$usage['limit']}). Upgrade your plan to add more.";
}

/**
 * Full usage snapshot for all caps — used by the Settings → License
 * usage dashboard.
 */
function getAllLicenseCapUsage(): array {
    $out = [];
    foreach (array_keys(LICENSE_CAP_LABELS) as $key) {
        $out[$key] = getLicenseCapUsage($key);
    }
    return $out;
}
/**
 * Returns cap usage entries that are at/above a warning threshold (default
 * 80%), for surfacing a proactive "approaching limit" banner outside the
 * License settings page itself. Excludes unlimited caps entirely.
 */
function getLicenseCapWarnings(float $thresholdPct = 80.0): array {
    $warnings = [];
    foreach (getAllLicenseCapUsageCached() as $key => $u) {   // ← changed
        if ($u['limit'] === null || $u['limit'] <= 0) continue;
        $pct = ($u['used'] / $u['limit']) * 100;
        if ($pct >= $thresholdPct) {
            $warnings[] = [
                'key'      => $key,
                'label'    => $u['label'],
                'used'     => $u['used'],
                'limit'    => $u['limit'],
                'pct'      => (int)round($pct),
                'at_limit' => $u['used'] >= $u['limit'],
            ];
        }
    }
    return $warnings;
}
// ── Session-cached usage snapshot (for advisory/display use only) ─────────
// Enforcement (licenseCapExceeded → getLicenseCapUsage) always hits the DB
// live and must NOT use this cache — caps are already a soft/non-atomic
// check-then-insert (see Phase 5 notes), and enforcement only runs at the
// moment of an actual create action, so its query cost is already low.
// This cache exists purely to stop the Phase 6 banner (which runs on every
// single admin page load) from re-running 5 COUNT(*) queries per request.
define('LICENSE_CAP_CACHE_TTL', 60); // seconds

function getAllLicenseCapUsageCached(): array {
    $cacheKey = '_license_cap_usage_cache';
    $cached   = $_SESSION[$cacheKey] ?? null;

    if ($cached && (time() - $cached['at']) < LICENSE_CAP_CACHE_TTL) {
        return $cached['data'];
    }

    $fresh = getAllLicenseCapUsage();
    $_SESSION[$cacheKey] = ['at' => time(), 'data' => $fresh];
    return $fresh;
}

/**
 * Force-invalidate the cached snapshot — call after any action that
 * changes a capped table's row count if you want the banner/dashboard to
 * reflect it immediately instead of waiting up to LICENSE_CAP_CACHE_TTL
 * seconds. Optional: the TTL alone self-heals within a minute, so this is
 * a nice-to-have for snappier UX, not a correctness requirement.
 */
function flushLicenseCapUsageCache(): void {
    unset($_SESSION['_license_cap_usage_cache']);
}