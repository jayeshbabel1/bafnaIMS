<?php
/**
 * admin/scripts/check_room_visualizer.php
 * ─────────────────────────────────────────────────────────────────────────
 * Diagnostic script for the Room Visualizer feature.
 * Run once via browser or CLI, review output, then DELETE this file.
 *
 * Checks:
 *  1. Imagick PHP extension present + usable
 *  2. Imagick supports the DISTORT (perspective) operation
 *  3. Required directories exist and are writable
 *  4. Storage driver config (local / Cloudinary) is valid
 *  5. Optional Hugging Face token presence (informational only)
 *  6. room_templates / room_visualizations DB tables exist
 * ─────────────────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

// Only allow this to run for logged-in admins OR from CLI — never anonymous web.
if (PHP_SAPI !== 'cli') {
    session_start();
    if (empty($_SESSION['admin_id'])) {
        http_response_code(403);
        die('Forbidden. Log in to the admin panel first, then reload this URL.');
    }
}

$isCli = (PHP_SAPI === 'cli');
$nl    = $isCli ? "\n" : "<br/>\n";
$ok    = $isCli ? '[OK]   ' : '✅ ';
$fail  = $isCli ? '[FAIL] ' : '❌ ';
$warn  = $isCli ? '[WARN] ' : '⚠️ ';

$results = []; // ['level' => ok|fail|warn, 'msg' => string]

function rvCheck(array &$results, string $level, string $msg): void {
    $results[] = ['level' => $level, 'msg' => $msg];
}

// ── 1. Imagick extension ──────────────────────────────────────────────────
if (!extension_loaded('imagick')) {
    rvCheck($results, 'fail', "Imagick PHP extension is NOT installed. The composite engine cannot run. Ask your host to enable 'php-imagick' (e.g. `apt install php-imagick` + restart PHP-FPM/Apache, or enable it in cPanel's PHP extension manager).");
} elseif (!class_exists('Imagick')) {
    rvCheck($results, 'fail', "The 'imagick' extension is loaded but the Imagick class is unavailable. This usually means a broken install — reinstall the extension.");
} else {
    try {
        $im = new Imagick();
        $ver = Imagick::getVersion();
        rvCheck($results, 'ok', "Imagick class loads correctly. Version: " . ($ver['versionString'] ?? 'unknown'));
        $im->clear();
    } catch (Throwable $e) {
        rvCheck($results, 'fail', "Imagick class exists but failed to instantiate: " . $e->getMessage());
    }
}

// ── 2. Perspective distort support (core to the compositor) ────────────────
if (class_exists('Imagick') && defined('Imagick::DISTORTION_PERSPECTIVE')) {
    try {
        $test = new Imagick();
        $test->newImage(10, 10, new ImagickPixel('red'));
        $test->setImageFormat('png');
        $test->setImageVirtualPixelMethod(Imagick::VIRTUALPIXELMETHOD_TRANSPARENT);
        $test->distortImage(Imagick::DISTORTION_PERSPECTIVE, [
            0,0, 1,1,  9,0, 8,2,  9,9, 8,8,  0,9, 1,7,
        ], true);
        rvCheck($results, 'ok', "Imagick perspective distortion (DISTORTION_PERSPECTIVE) works — this is the core operation used to warp slab photos onto room surfaces.");
        $test->clear();
    } catch (Throwable $e) {
        rvCheck($results, 'fail', "Imagick is installed but perspective distortion failed: " . $e->getMessage() . ". Your ImageMagick build may be missing the required delegate/feature.");
    }
} elseif (class_exists('Imagick')) {
    rvCheck($results, 'fail', "Imagick::DISTORTION_PERSPECTIVE constant is not defined. Your ImageMagick version may be too old (need ImageMagick 6.4.2+).");
}

// ── 3. Directories ───────────────────────────────────────────────────────
$dirs = [
    'ROOM_TEMPLATES_DIR' => defined('ROOM_TEMPLATES_DIR') ? ROOM_TEMPLATES_DIR : null,
    'ROOM_PREVIEWS_DIR'  => defined('ROOM_PREVIEWS_DIR')  ? ROOM_PREVIEWS_DIR  : null,
];
foreach ($dirs as $label => $path) {
    if ($path === null) {
        rvCheck($results, 'fail', "$label constant is not defined. Check config/config.php has been patched with the Room Visualizer settings block.");
        continue;
    }
    if (!is_dir($path)) {
        $made = @mkdir($path, 0755, true);
        if ($made) {
            rvCheck($results, 'ok', "$label ($path) did not exist — created it successfully.");
        } else {
            rvCheck($results, 'fail', "$label ($path) does not exist and could not be created. Check parent directory permissions.");
            continue;
        }
    }
    if (!is_writable($path)) {
        rvCheck($results, 'fail', "$label ($path) exists but is NOT writable by PHP. Run: chmod 755 " . escapeshellarg($path) . " (or check ownership).");
    } else {
        rvCheck($results, 'ok', "$label ($path) exists and is writable.");
    }
}

// ── 4. Storage driver config ────────────────────────────────────────────
$driver = getenv('STORAGE_DRIVER') ?: 'local';
rvCheck($results, 'ok', "STORAGE_DRIVER is set to '{$driver}'.");

if ($driver === 'cloudinary') {
    if (!function_exists('cloudinaryConfigured')) {
        rvCheck($results, 'fail', "includes/cloudinary.php has not been created/included — cloudinaryConfigured() is undefined.");
    } else {
        require_once __DIR__ . '/../../includes/cloudinary.php';
        if (cloudinaryConfigured()) {
            rvCheck($results, 'ok', "Cloudinary credentials are present in .env (CLOUDINARY_CLOUD_NAME / API_KEY / API_SECRET).");
            // Live connectivity check
            $probe = @curl_init('https://api.cloudinary.com/v1_1/' . getenv('CLOUDINARY_CLOUD_NAME') . '/resources/image');
            if ($probe) {
                curl_setopt_array($probe, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_USERPWD        => getenv('CLOUDINARY_API_KEY') . ':' . getenv('CLOUDINARY_API_SECRET'),
                    CURLOPT_TIMEOUT        => 8,
                ]);
                $resp = curl_exec($probe);
                $code = curl_getinfo($probe, CURLINFO_HTTP_CODE);
                curl_close($probe);
                if ($code === 200) {
                    rvCheck($results, 'ok', "Cloudinary API credentials verified live (HTTP 200 on resources endpoint).");
                } elseif ($code === 401) {
                    rvCheck($results, 'fail', "Cloudinary API rejected the credentials (HTTP 401). Double-check CLOUDINARY_API_KEY / CLOUDINARY_API_SECRET in .env.");
                } else {
                    rvCheck($results, 'warn', "Could not verify Cloudinary credentials live (HTTP {$code}). This may just be a network/firewall issue on this server — uploads might still work.");
                }
            }
        } else {
            rvCheck($results, 'fail', "STORAGE_DRIVER=cloudinary but CLOUDINARY_CLOUD_NAME / API_KEY / API_SECRET are missing from .env. Previews will silently fall back to local storage.");
        }
    }
} else {
    rvCheck($results, 'ok', "Using local disk storage — no external API dependency for storing previews.");
}

if (!function_exists('curl_init')) {
    rvCheck($results, 'fail', "PHP cURL extension is not installed. Both Cloudinary uploads and the optional Hugging Face engine require it.");
}

// ── 5. Hugging Face (optional AI engine) ────────────────────────────────
$hfToken = getenv('HF_API_TOKEN');
$engine  = getenv('ROOM_VIS_ENGINE') ?: 'composite';
if ($engine === 'huggingface' && !$hfToken) {
    rvCheck($results, 'warn', "ROOM_VIS_ENGINE=huggingface but HF_API_TOKEN is not set in .env. Generation will silently fall back to the composite engine.");
} elseif ($hfToken) {
    rvCheck($results, 'ok', "HF_API_TOKEN is present. Experimental Hugging Face img2img engine is available (optional, not required).");
} else {
    rvCheck($results, 'ok', "No Hugging Face token configured — using the deterministic composite engine only (this is the recommended default).");
}

// ── 6. Database tables ──────────────────────────────────────────────────
try {
    $db = getDB();
    foreach (['room_templates', 'room_visualizations'] as $table) {
        $exists = $db->query("SHOW TABLES LIKE " . $db->quote($table))->fetch();
        if ($exists) {
            $count = (int)$db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            rvCheck($results, 'ok', "Table '$table' exists ($count row" . ($count === 1 ? '' : 's') . ").");
        } else {
            rvCheck($results, 'fail', "Table '$table' does not exist. Run migration_room_visualizer.sql against your database.");
        }
    }

    // Active template sanity check
    if ($db->query("SHOW TABLES LIKE 'room_templates'")->fetch()) {
        $active = (int)$db->query("SELECT COUNT(*) FROM room_templates WHERE is_active=1")->fetchColumn();
        if ($active === 0) {
            rvCheck($results, 'warn', "No active room templates found. Users will see 'Visualizer not set up yet' until an admin uploads at least one template via Admin → Room Visualizer Templates.");
        } else {
            rvCheck($results, 'ok', "$active active room template(s) configured — users can generate previews now.");
        }
    }
} catch (Throwable $e) {
    rvCheck($results, 'fail', "Could not query the database: " . $e->getMessage());
}

// ── 7. Memory / execution limits (perspective warps on large photos are heavy) ─
$memLimit = ini_get('memory_limit');
$maxExec  = ini_get('max_execution_time');
rvCheck($results, ($memLimit === '-1' || (int)$memLimit >= 128) ? 'ok' : 'warn',
    "PHP memory_limit is {$memLimit}. Recommend 128M+ for Imagick operations on large slab photos.");
rvCheck($results, (($maxExec == 0) || (int)$maxExec >= 30) ? 'ok' : 'warn',
    "PHP max_execution_time is {$maxExec}s. Recommend 30s+ in case Cloudinary upload is slow.");

// ── Output ──────────────────────────────────────────────────────────────
$failCount = count(array_filter($results, fn($r) => $r['level'] === 'fail'));
$warnCount = count(array_filter($results, fn($r) => $r['level'] === 'warn'));

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Room Visualizer — Diagnostic</title>';
    echo '<style>body{font-family:-apple-system,Segoe UI,sans-serif;background:#F2F5F9;padding:32px;color:#1A2837;}
    .box{background:#fff;border-radius:12px;padding:24px 28px;max-width:800px;margin:0 auto 16px;box-shadow:0 2px 12px rgba(0,0,0,.06);}
    .row{padding:10px 0;border-bottom:1px solid #eee;font-size:14px;line-height:1.6;}
    .row:last-child{border-bottom:none;}
    .summary{font-weight:700;font-size:18px;margin-bottom:6px;}
    .fail{color:#E84040;} .warn{color:#B8975A;} .ok{color:#3D8B6E;}
    code{background:#f0f0f0;padding:1px 5px;border-radius:4px;font-size:12.5px;}
    </style></head><body>';
    echo '<div class="box"><p class="summary">Room Visualizer — Server Diagnostic</p>';
    echo '<p style="color:#8FA3B1;font-size:13px;">Delete <code>admin/scripts/check_room_visualizer.php</code> after reviewing this.</p></div>';
    echo '<div class="box">';
    if ($failCount > 0) {
        echo "<p class=\"summary fail\">$fail $failCount blocking issue(s) found — the visualizer will not work until these are fixed.</p>";
    } elseif ($warnCount > 0) {
        echo "<p class=\"summary warn\">$warn All critical checks passed, but $warnCount warning(s) to review.</p>";
    } else {
        echo "<p class=\"summary ok\">$ok All checks passed. Room Visualizer is ready to use.</p>";
    }
    echo '</div><div class="box">';
    foreach ($results as $r) {
        $icon = $r['level'] === 'fail' ? $fail : ($r['level'] === 'warn' ? $warn : $ok);
        echo '<div class="row ' . $r['level'] . '">' . $icon . h($r['msg']) . '</div>';
    }
    echo '</div></body></html>';
} else {
    echo "Room Visualizer — Server Diagnostic{$nl}" . str_repeat('=', 50) . $nl;
    foreach ($results as $r) {
        $icon = $r['level'] === 'fail' ? $fail : ($r['level'] === 'warn' ? $warn : $ok);
        echo $icon . $r['msg'] . $nl;
    }
    echo str_repeat('=', 50) . $nl;
    if ($failCount > 0) {
        echo "$failCount blocking issue(s) found.$nl";
        exit(1);
    } elseif ($warnCount > 0) {
        echo "All critical checks passed, $warnCount warning(s).$nl";
        exit(0);
    } else {
        echo "All checks passed.$nl";
        exit(0);
    }
}