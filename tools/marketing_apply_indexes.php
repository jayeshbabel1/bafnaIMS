<?php
/**
 * tools/marketing_apply_indexes.php
 * Fire 14 — proactively applies the Fire 14 index migration.
 *
 * WHY THIS EXISTS SEPARATELY FROM admin/index.php's automatic call: on a
 * marketing_queue/marketing_messages table that's already accumulated a lot
 * of rows in production, an ALTER TABLE ADD KEY can take a noticeable
 * moment to build. Every other ensure*() migration in this app fires
 * automatically on the next admin page load — fine for a fresh table, but
 * this lets you run the migration deliberately during a quiet window
 * instead of it silently happening mid-request for whichever admin loads
 * the page first after deploy.
 *
 * Usage: php tools/marketing_apply_indexes.php
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/marketing_performance.php';

if (php_sapi_name() !== 'cli') { die("CLI only.\n"); }

echo "Applying Fire 14 marketing performance indexes...\n";
$start = microtime(true);
ensureMarketingPerformanceIndexes();
$elapsed = round(microtime(true) - $start, 2);
echo "Done in {$elapsed}s. Safe to re-run — already-present indexes are skipped.\n";