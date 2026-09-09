<?php
/**
 * tools/marketing_load_test.php
 * Fire 14 — synthetic load generator + query benchmark + teardown for the
 * Marketing module. NO REAL SENDS: this tool never touches
 * WhatsAppCloudProvider or SmtpEmailProvider, so no provider API is ever
 * called regardless of how it's run.
 *
 * SAFETY DESIGN (read before running against production):
 *   - Every synthetic contact's mobile starts with "000" and every email
 *     uses the @example.invalid TLD (RFC 2606 — guaranteed to never
 *     resolve). Even if something unexpected caused a real send attempt,
 *     it cannot reach a real person or device.
 *   - Synthetic queue rows sit at status='cancelled' at rest. The
 *     'benchmark' command flips a batch to 'pending' only for the instant
 *     needed to time the real worker's claim query, then flips it straight
 *     back — there is a small residual race window if the real queue-worker
 *     cron happens to run in that exact instant, which is why pausing
 *     cron/marketing_queue_worker.php and cron/marketing_automation_worker.php
 *     during this test is still the recommended precaution, not optional.
 *   - Always run 'teardown' when finished. Left-behind synthetic rows will
 *     otherwise inflate the Fire 10 analytics dashboard and contact counts.
 *
 * Usage:
 *   php tools/marketing_load_test.php generate --count=5000
 *   php tools/marketing_load_test.php benchmark
 *   php tools/marketing_load_test.php teardown
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/marketing_campaigns.php';
require_once BASE_PATH . '/includes/marketing_automation.php';
require_once BASE_PATH . '/includes/marketing_performance.php';

if (php_sapi_name() !== 'cli') { die("CLI only.\n"); }

function mktLoadTestArg(array $argv, string $name, ?string $default = null): ?string {
    foreach ($argv as $a) if (str_starts_with($a, "--{$name}=")) return substr($a, strlen($name) + 3);
    return $default;
}

function mktLoadTestGenerate(PDO $db, int $count): void {
    $count = max(1, min($count, 50000)); // hard cap — protects PHP memory during the later "fetch all active contacts" benchmark
    $now = time();

    echo "Generating {$count} synthetic contacts...\n";
    $db->beginTransaction();
    $ins = $db->prepare("INSERT INTO marketing_contacts
        (source_type, name, mobile, whatsapp_number, email, city, status, whatsapp_opt_in, email_opt_in, created_at, updated_at)
        VALUES ('import', ?, ?, ?, ?, ?, 'active', 1, 1, ?, ?)");
    $cities = ['Mumbai', 'Pune', 'Surat', 'Jaipur'];
    for ($i = 1; $i <= $count; $i++) {
        $mobile = '000' . str_pad((string)$i, 7, '0', STR_PAD_LEFT); // "000..." — deliberately invalid, never a real MSISDN
        $email = "loadtest{$i}@example.invalid";                    // RFC 2606 reserved TLD — guaranteed non-resolving
        $ins->execute(["LOADTEST-Contact-{$i}", $mobile, $mobile, $email, $cities[$i % 4], $now, $now]);
    }
    $db->commit();

    echo "Creating synthetic campaign + recipients + queue rows...\n";
    $db->beginTransaction();
    $db->prepare("INSERT INTO marketing_campaigns
        (company_id, name, channel, audience_json, variables_json, status, schedule_type, timezone, source, created_at, updated_at)
        VALUES (1,'LOADTEST-Campaign','email','{\"type\":\"all\"}','{}', 'completed','now','Asia/Kolkata','manual',?,?)")
       ->execute([$now, $now]);
    $campaignId = (int)$db->lastInsertId();

    $contactIds = $db->query("SELECT id FROM marketing_contacts WHERE name LIKE 'LOADTEST-Contact-%'")->fetchAll(PDO::FETCH_COLUMN);

    $recipIns = $db->prepare("INSERT INTO marketing_campaign_recipients (campaign_id, contact_id, channel, idempotency_key, status, created_at) VALUES (?,?,?,?,'sent',?)");
    $queueIns = $db->prepare("INSERT INTO marketing_queue (recipient_id, channel, payload_json, status, next_attempt_at, created_at) VALUES (?,?,?,?,?,?)");
    foreach ($contactIds as $cid) {
        $idemKey = marketingIdempotencyKey($campaignId, (int)$cid, 'email');
        $recipIns->execute([$campaignId, $cid, 'email', $idemKey, $now]);
        $recipientId = (int)$db->lastInsertId();
        // status='cancelled' at rest, per the safety design noted at the top of this file.
        $queueIns->execute([$recipientId, 'email', json_encode(['to' => "loadtest{$cid}@example.invalid"]), 'cancelled', $now, $now]);
    }

    // Synthetic tag on every 10th contact — exercises the new idx_tag index.
    $db->prepare("INSERT IGNORE INTO marketing_tags (company_id, name, created_at) VALUES (1,'LOADTEST-Tag',?)")->execute([$now]);
    $tagId = (int)$db->query("SELECT id FROM marketing_tags WHERE name='LOADTEST-Tag'")->fetchColumn();
    $tagIns = $db->prepare("INSERT IGNORE INTO marketing_contact_tags (contact_id, tag_id) VALUES (?,?)");
    foreach ($contactIds as $idx => $cid) if ($idx % 10 === 0) $tagIns->execute([$cid, $tagId]);

    // Synthetic automation, pre-set to a terminal state so the real
    // automation-worker cron never has anything to pick up from it.
    $db->prepare("INSERT INTO marketing_automations (name, trigger_type, audience_json, is_active, created_at, updated_at)
                  VALUES ('LOADTEST-Automation','contact_created','{\"type\":\"all\"}',0,?,?)")->execute([$now, $now]);
    $automationId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO marketing_automation_runs (automation_id, contact_id, current_step, next_run_at, status, created_at)
                  VALUES (?,?,0,?, 'completed', ?)")->execute([$automationId, $contactIds[0], $now, $now]);

    $db->commit();
    echo "Done. campaign_id={$campaignId}, tag_id={$tagId}, automation_id={$automationId}.\n";
}

function mktLoadTestBenchmark(PDO $db): void {
    ensureMarketingPerformanceIndexes(); // no-op if already applied — reflects whatever index state currently exists

    $sampleContact  = $db->query("SELECT id FROM marketing_contacts WHERE name LIKE 'LOADTEST-Contact-%' ORDER BY id ASC LIMIT 1")->fetchColumn();
    $sampleCampaign = $db->query("SELECT id FROM marketing_campaigns WHERE name='LOADTEST-Campaign' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $sampleTag      = $db->query("SELECT id FROM marketing_tags WHERE name='LOADTEST-Tag' LIMIT 1")->fetchColumn();
    $sampleAuto     = $db->query("SELECT id FROM marketing_automations WHERE name='LOADTEST-Automation' LIMIT 1")->fetchColumn();
    if (!$sampleContact || !$sampleCampaign) {
        echo "No synthetic data found — run `generate` first.\n";
        return;
    }

    $results = [];

    // The only benchmark that touches 'pending' rows — flipped and flipped
    // straight back around the single timed statement, per this file's
    // safety design note.
    $db->exec("UPDATE marketing_queue SET status='pending' WHERE channel='email' AND status='cancelled' LIMIT 500");
    $results[] = _mktBenchmarkQuery($db, 'Queue claim (worker batch select)',
        "SELECT id FROM marketing_queue WHERE channel=? AND status IN ('pending','rate_limited') AND next_attempt_at<=? ORDER BY id ASC LIMIT 250",
        ['email', time()]);
    $db->exec("UPDATE marketing_queue SET status='cancelled' WHERE channel='email' AND status='pending'");

    $results[] = _mktBenchmarkQuery($db, 'Stale lock reclaim',
        "SELECT id FROM marketing_queue WHERE status='processing' AND locked_at IS NOT NULL AND locked_at<?", [time()]);

    $results[] = _mktBenchmarkQuery($db, 'Audience resolve: all active contacts',
        "SELECT * FROM marketing_contacts WHERE status='active'", []);

    if ($sampleTag) {
        $results[] = _mktBenchmarkQuery($db, 'Audience resolve: contacts by tag',
            "SELECT DISTINCT mc.* FROM marketing_contacts mc JOIN marketing_contact_tags mct ON mct.contact_id=mc.id
             WHERE mc.status='active' AND mct.tag_id IN (?)", [$sampleTag]);
    }

    $results[] = _mktBenchmarkQuery($db, 'Cancel-pending-sends-for-contact join',
        "SELECT mq.id, mq.recipient_id FROM marketing_queue mq JOIN marketing_campaign_recipients mcr ON mcr.id=mq.recipient_id
         WHERE mcr.contact_id=? AND mq.channel=? AND mq.status IN ('pending','rate_limited')", [$sampleContact, 'email']);

    $results[] = _mktBenchmarkQuery($db, 'Contact message history join',
        "SELECT mm.* FROM marketing_messages mm JOIN marketing_campaign_recipients mcr ON mcr.id=mm.recipient_id
         WHERE mcr.contact_id=? ORDER BY mm.created_at DESC LIMIT 20", [$sampleContact]);

    if ($sampleAuto) {
        $results[] = _mktBenchmarkQuery($db, 'Automation enrollment dedup check',
            "SELECT id FROM marketing_automation_runs WHERE automation_id=? AND contact_id=? AND status='running'",
            [$sampleAuto, $sampleContact]);
    }

    $results[] = _mktBenchmarkQuery($db, 'Scheduler: due scheduled campaigns',
        "SELECT id FROM marketing_campaigns WHERE status='scheduled' AND scheduled_at<=?", [time()]);

    $results[] = _mktBenchmarkQuery($db, 'Analytics: per-campaign message status rollup',
        "SELECT campaign_id, status, COUNT(*) c FROM marketing_messages WHERE campaign_id IN (?) GROUP BY campaign_id, status", [$sampleCampaign]);

    echo str_pad('Query', 45) . str_pad('Time (ms)', 12) . "Index Used\n";
    echo str_repeat('-', 90) . "\n";
    foreach ($results as $r) {
        $key = $r['explain'][0]['key'] ?? ($r['explain'][0]['error'] ?? 'NULL (full scan)');
        echo str_pad($r['label'], 45) . str_pad((string)$r['elapsed_ms'], 12) . ($key ?: 'NULL (full scan)') . "\n";
    }
    echo "\nTip: run this once BEFORE applying tools/marketing_apply_indexes.php and once after, to see the 'Index Used' column change from full-scan to the new index names.\n";
}

function mktLoadTestTeardown(PDO $db): void {
    $db->beginTransaction();
    $campaigns    = $db->exec("DELETE FROM marketing_campaigns WHERE name='LOADTEST-Campaign'");     // cascades recipients -> queue
    $automations  = $db->exec("DELETE FROM marketing_automations WHERE name='LOADTEST-Automation'"); // cascades steps + runs
    $tags         = $db->exec("DELETE FROM marketing_tags WHERE name='LOADTEST-Tag'");                // cascades contact_tags
    $contacts     = $db->exec("DELETE FROM marketing_contacts WHERE name LIKE 'LOADTEST-Contact-%'");
    $db->commit();
    echo "Teardown complete: {$campaigns} campaign(s), {$automations} automation(s), {$tags} tag(s), {$contacts} contact(s) removed.\n";
}

$command = $argv[1] ?? '';
$db = getDB();

switch ($command) {
    case 'generate':
        mktLoadTestGenerate($db, (int)(mktLoadTestArg($argv, 'count', '5000')));
        break;
    case 'benchmark':
        mktLoadTestBenchmark($db);
        break;
    case 'teardown':
        mktLoadTestTeardown($db);
        break;
    default:
        echo "Usage: php tools/marketing_load_test.php [generate --count=5000 | benchmark | teardown]\n";
        echo "See the file header for the full safety design before running against production.\n";
}