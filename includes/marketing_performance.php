<?php
/**
 * includes/marketing_performance.php
 * Fire 14 — index migration + a small EXPLAIN/timing benchmark helper used
 * by tools/marketing_load_test.php. Follows the same idempotent
 * check-then-ALTER idiom as ensureMarketingContactExtraColumns() (Fire 3)
 * and ensureMarketingCampaignAutomationColumns() (Fire 11) — no
 * "ADD KEY IF NOT EXISTS" exists in portable MySQL, so we check
 * information_schema first.
 */
require_once __DIR__ . '/marketing.php';

// Each entry: [table, index_name, ALTER statement]. See Fire 14's
// findings table for why each one exists — every one traces back to a
// real WHERE clause already in the codebase, not a speculative addition.
function _mktPerformanceIndexDefs(): array {
    return [
        ['marketing_campaign_recipients', 'idx_contact',            "ALTER TABLE marketing_campaign_recipients ADD KEY idx_contact (contact_id)"],
        ['marketing_messages',            'idx_recipient',           "ALTER TABLE marketing_messages ADD KEY idx_recipient (recipient_id)"],
        ['marketing_queue',               'idx_claim',               "ALTER TABLE marketing_queue ADD KEY idx_claim (channel, status, next_attempt_at)"],
        ['marketing_queue',               'idx_stale_lock',          "ALTER TABLE marketing_queue ADD KEY idx_stale_lock (status, locked_at)"],
        ['marketing_automation_runs',     'idx_automation_contact',  "ALTER TABLE marketing_automation_runs ADD KEY idx_automation_contact (automation_id, contact_id, status)"],
        ['marketing_campaigns',           'idx_status_scheduled',    "ALTER TABLE marketing_campaigns ADD KEY idx_status_scheduled (status, scheduled_at)"],
        ['marketing_contact_tags',        'idx_tag',                 "ALTER TABLE marketing_contact_tags ADD KEY idx_tag (tag_id)"],
    ];
}

/**
 * Does the actual check-then-ALTER work, every time it's called — no
 * request-scoped guard. Returns one result row per index so a caller (e.g.
 * an admin "Repair" button) can show what happened. Safe to re-run:
 * already-present indexes are skipped, matching the existing idempotent
 * check-then-ALTER idiom used elsewhere in this file.
 */
function applyMarketingPerformanceIndexes(): array {
    $db = getDB();
    $results = [];
    foreach (_mktPerformanceIndexDefs() as [$table, $indexName, $sql]) {
        $chk = $db->prepare("SELECT 1 FROM information_schema.STATISTICS
                              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1");
        $chk->execute([$table, $indexName]);
        if ($chk->fetch()) {
            $results[] = ['table' => $table, 'index' => $indexName, 'status' => 'already_present'];
            continue;
        }
        try {
            $db->exec($sql);
            $results[] = ['table' => $table, 'index' => $indexName, 'status' => 'created'];
        } catch (Throwable $e) {
            error_log("applyMarketingPerformanceIndexes: failed adding {$indexName} on {$table}: " . $e->getMessage());
            $results[] = ['table' => $table, 'index' => $indexName, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }
    return $results;
}

/**
 * Request-scoped, fire-and-forget wrapper used by the admin bootstrap
 * (admin/index.php) so the migration self-heals on the next page load
 * without ever running twice in the same request. For a deliberate,
 * on-demand run (CLI tool, admin "Repair" button) call
 * applyMarketingPerformanceIndexes() directly instead — this wrapper's
 * static guard would otherwise silently no-op a second call.
 */
function ensureMarketingPerformanceIndexes(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    applyMarketingPerformanceIndexes();
}

/**
 * OPTIMIZE TABLE on every table backing the Fire 14 indexes above. Rebuilds
 * indexes and reclaims fragmented space from InnoDB's per-row overhead on
 * high-churn tables like marketing_queue. Not run automatically anywhere —
 * OPTIMIZE TABLE briefly locks the table and can take a while on a large
 * one, so this is deliberately on-demand only (admin "Optimize" button or
 * a future scheduled maintenance job), same reasoning as why
 * tools/marketing_apply_indexes.php exists separately from the automatic
 * ensure*() call.
 */
function optimizeMarketingTables(): array {
    $db = getDB();
    $tables = array_unique(array_column(_mktPerformanceIndexDefs(), 0));
    $results = [];
    foreach ($tables as $table) {
        try {
            $rows = $db->query("OPTIMIZE TABLE `{$table}`")->fetchAll();
            $msg = $rows[0]['Msg_text'] ?? 'OK';
            $results[] = ['table' => $table, 'status' => 'ok', 'message' => $msg];
        } catch (Throwable $e) {
            error_log("optimizeMarketingTables: failed on {$table}: " . $e->getMessage());
            $results[] = ['table' => $table, 'status' => 'failed', 'message' => $e->getMessage()];
        }
    }
    return $results;
}

/**
 * Runs EXPLAIN (read-only, no side effects) then a real timed execution of
 * the same query. Used only by tools/marketing_load_test.php's benchmark
 * command — never called from live request paths.
 */
function _mktBenchmarkQuery(PDO $db, string $label, string $sql, array $params = []): array {
    $explainRows = [];
    try {
        $st = $db->prepare('EXPLAIN ' . $sql);
        $st->execute($params);
        $explainRows = $st->fetchAll();
    } catch (Throwable $e) {
        $explainRows = [['error' => $e->getMessage()]];
    }

    $start = microtime(true);
    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
    $elapsedMs = round((microtime(true) - $start) * 1000, 2);

    return ['label' => $label, 'elapsed_ms' => $elapsedMs, 'row_count' => count($rows), 'explain' => $explainRows];
}