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

function ensureMarketingPerformanceIndexes(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $db = getDB();

    // Each entry: [table, index_name, ALTER statement]. See Fire 14's
    // findings table for why each one exists — every one traces back to a
    // real WHERE clause already in the codebase, not a speculative addition.
    $indexes = [
        ['marketing_campaign_recipients', 'idx_contact',            "ALTER TABLE marketing_campaign_recipients ADD KEY idx_contact (contact_id)"],
        ['marketing_messages',            'idx_recipient',           "ALTER TABLE marketing_messages ADD KEY idx_recipient (recipient_id)"],
        ['marketing_queue',               'idx_claim',               "ALTER TABLE marketing_queue ADD KEY idx_claim (channel, status, next_attempt_at)"],
        ['marketing_queue',               'idx_stale_lock',          "ALTER TABLE marketing_queue ADD KEY idx_stale_lock (status, locked_at)"],
        ['marketing_automation_runs',     'idx_automation_contact',  "ALTER TABLE marketing_automation_runs ADD KEY idx_automation_contact (automation_id, contact_id, status)"],
        ['marketing_campaigns',           'idx_status_scheduled',    "ALTER TABLE marketing_campaigns ADD KEY idx_status_scheduled (status, scheduled_at)"],
        ['marketing_contact_tags',        'idx_tag',                 "ALTER TABLE marketing_contact_tags ADD KEY idx_tag (tag_id)"],
    ];

    foreach ($indexes as [$table, $indexName, $sql]) {
        $chk = $db->prepare("SELECT 1 FROM information_schema.STATISTICS
                              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1");
        $chk->execute([$table, $indexName]);
        if ($chk->fetch()) continue;
        try {
            $db->exec($sql);
        } catch (Throwable $e) {
            error_log("ensureMarketingPerformanceIndexes: failed adding {$indexName} on {$table}: " . $e->getMessage());
        }
    }
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