<?php
/**
 * cron/marketing_automation_worker.php
 * Fire 11 — advances automation runs (conditions/waits/actions).
 *
 * Crontab (15-minute cadence, matching Fires 8/9):
 *   /15 * * * * /usr/bin/php /path/to/app/cron/marketing_automation_worker.php >> /path/to/app/storage/logs/marketing_automation.log 2>&1
 *
 * Same bootstrap caveat as cron/marketing_queue_worker.php (Fire 8) and
 * cron/marketing_scheduler.php (Fire 8) — mirrors that pattern since
 * cron/maintenance.php's real contents weren't available in my context.
 */
if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));

// Defensive: prevents any stray PHP warning/notice (e.g. Composer's own
// platform_check.php on a PHP-version mismatch) from printing into output
// that other code might mistake for a real error message. Real errors are
// still fully captured via the try/catch logging already in place below.
ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/marketing_automation.php';

if (php_sapi_name() !== 'cli' && !(defined('ALLOW_CRON_WEB') && ALLOW_CRON_WEB)) {
    http_response_code(403);
    die('CLI only.');
}

set_time_limit(280);

$logDir = BASE_PATH . '/storage/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logFile = $logDir . '/marketing_automation.log';

$logLine = '[' . date('Y-m-d H:i:s') . '] ';
try {
    $stats = runMarketingAutomationWorkerCycle();
    $logLine .= "Marketing automation worker run: " . json_encode($stats) . "\n";
} catch (Throwable $e) {
    $logLine .= "FATAL: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
@file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
echo $logLine;