<?php

if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));

// Defensive: prevents any stray PHP warning/notice (e.g. Composer's own
// platform_check.php on a PHP-version mismatch) from printing into output
// that other code might mistake for a real error message. Real errors are
// still fully captured via the try/catch logging already in place below.
ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/marketing_queue_worker.php';

if (php_sapi_name() !== 'cli' && !(defined('ALLOW_CRON_WEB') && ALLOW_CRON_WEB)) {
    http_response_code(403);
    die('CLI only.');
}

set_time_limit(280); // stay comfortably under the 15-minute cron cadence

// Self-contained logging: writes directly to a file in PHP rather than
// relying on shell '>>' redirection in the cron command itself, since
// panel UIs (CWP included) commonly reject/mangle cron commands containing
// shell operators. This also means the log captures a fatal error even if
// something crashes before the echo statements below would run.
$logDir = BASE_PATH . '/storage/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logFile = $logDir . '/marketing_queue.log';

$logLine = '[' . date('Y-m-d H:i:s') . '] ';
try {
    $stats = runMarketingQueueWorkerCycle();
    $logLine .= "Marketing queue worker run: " . json_encode($stats) . "\n";
} catch (Throwable $e) {
    $logLine .= "FATAL: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
@file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
echo $logLine;