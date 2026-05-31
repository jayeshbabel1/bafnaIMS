<?php
/**
 * cron/maintenance.php
 * ─────────────────────────────────────────────────────────────────────────
 * Run via cron every hour (or daily):
 *   php /path/to/cron/maintenance.php
 
   CRON — schedule maintenance
 *    ──────────────────────────────
 *    Add to crontab (runs daily at 2 AM):
 *      0 2 * * * php /var/www/html/cron/maintenance.php >> /var/www/html/storage/logs/cron.log 2>&1
 *
 *
 * Tasks:
 *  1. Auto-archive inquiries older than 25 days (status → 'closed')
 *  2. Delete notifications older than 20 days
 * ─────────────────────────────────────────────────────────────────────────
 */

// Allow CLI + web-cron call; block direct browser access in production
if (PHP_SAPI !== 'cli' && !defined('ALLOW_CRON_WEB')) {
    // Uncomment the next line in production:
    // exit('Forbidden');
}

define('CRON_RUN', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$db  = getDB();
$now = time();
$log = [];

// ── 1. Archive stale inquiries ───────────────────────────────────────────────
$cutoff25 = $now - (25 * 86400); // 25 days ago
$stmt = $db->prepare("
    UPDATE inquiries
    SET    status = 'closed'
    WHERE  status IN ('pending','replied')
      AND  created_at < ?
");
$stmt->execute([$cutoff25]);
$archived = $stmt->rowCount();
$log[] = "[".date('Y-m-d H:i:s')."] Archived $archived inquiries older than 25 days.";

// ── 2. Delete old notifications ──────────────────────────────────────────────
$cutoff20 = $now - (20 * 86400); // 20 days ago
$stmt2 = $db->prepare("DELETE FROM notifications WHERE created_at < ?");
$stmt2->execute([$cutoff20]);
$deleted = $stmt2->rowCount();
$log[] = "[".date('Y-m-d H:i:s')."] Deleted $deleted notifications older than 20 days.";

// ── Log output ───────────────────────────────────────────────────────────────
foreach ($log as $line) {
    echo $line . PHP_EOL;
}

// Optional: write to a log file
$logFile = __DIR__ . '/../storage/logs/cron.log';
if (is_dir(dirname($logFile)) || @mkdir(dirname($logFile), 0755, true)) {
    file_put_contents($logFile, implode(PHP_EOL, $log) . PHP_EOL, FILE_APPEND);
}