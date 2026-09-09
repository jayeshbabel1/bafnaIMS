<?php
/**
 * cron/marketing_scheduler.php
 * Fire 8 — campaign lifecycle transitions + recurring successor spawning.
 *
 * Crontab (15-minute cadence, confirmed):
 *   /15 * * * * /usr/bin/php /path/to/app/cron/marketing_scheduler.php >> /path/to/app/storage/logs/marketing_scheduler.log 2>&1
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
require_once BASE_PATH . '/includes/marketing_scheduler.php';

if (php_sapi_name() !== 'cli' && !(defined('ALLOW_CRON_WEB') && ALLOW_CRON_WEB)) {
    http_response_code(403);
    die('CLI only.');
}

set_time_limit(280);

$logDir = BASE_PATH . '/storage/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logFile = $logDir . '/marketing_scheduler.log';

$logLine = '[' . date('Y-m-d H:i:s') . '] ';
try {
    $stats = runMarketingSchedulerCycle();
    $logLine .= "Marketing scheduler run: " . json_encode($stats) . "\n";
} catch (Throwable $e) {
    $logLine .= "FATAL: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
@file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
echo $logLine;