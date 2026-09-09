<?php
/**
 * includes/marketing_scheduler.php
 * Fire 8 — campaign lifecycle transitions (scheduled→running→completed)
 * and recurring-campaign successor spawning.
 *
 * Does NOT resolve audiences here — that already happened eagerly in
 * scheduleCampaignForSending() (Fire 7). This file only watches status.
 */
require_once __DIR__ . '/marketing.php';
require_once __DIR__ . '/marketing_campaigns.php';

function runMarketingSchedulerCycle(): array {
    ensureMarketingTables();
    $db = getDB();
    $now = time();
    $flippedToRunning = 0; $completed = 0; $spawned = 0;

    // 1) scheduled -> running (recipients/queue rows already exist from Fire 7;
    //    this is purely a status label change so the admin UI reflects reality)
    $due = $db->prepare("SELECT id FROM marketing_campaigns WHERE status='scheduled' AND scheduled_at <= ?");
    $due->execute([$now]);
    foreach ($due->fetchAll(PDO::FETCH_COLUMN) as $cid) {
        $db->prepare("UPDATE marketing_campaigns SET status='running', updated_at=? WHERE id=?")->execute([$now, $cid]);
        logMarketingAudit('campaign_started', 'marketing_campaigns', $cid);
        $flippedToRunning++;
    }

    // 2) running campaigns with no non-terminal recipients left -> completed
    $running = $db->query("SELECT * FROM marketing_campaigns WHERE status='running'")->fetchAll();
    foreach ($running as $campaign) {
        $chk = $db->prepare("SELECT COUNT(*) FROM marketing_campaign_recipients
                              WHERE campaign_id=? AND status IN ('pending','queued','processing','rate_limited')");
        $chk->execute([$campaign['id']]);
        if ((int)$chk->fetchColumn() > 0) continue;

        $db->prepare("UPDATE marketing_campaigns SET status='completed', updated_at=? WHERE id=?")->execute([$now, $campaign['id']]);
        logMarketingAudit('campaign_completed', 'marketing_campaigns', $campaign['id']);
        $completed++;

        if ($campaign['schedule_type'] === 'recurring') {
            $newId = spawnNextRecurringOccurrence($campaign);
            if ($newId) $spawned++;
        }
    }

    return ['flipped_to_running' => $flippedToRunning, 'completed' => $completed, 'recurring_spawned' => $spawned];
}

/**
 * Spawns the NEXT occurrence of a recurring campaign as a brand-new
 * campaign row (fresh campaign_id → fresh idempotency-key space), rather
 * than trying to reuse the same campaign_id, which would collide with the
 * uq_recipient unique key on marketing_campaign_recipients.
 *
 * KNOWN LIMITATION: no end-condition exists (no max occurrence count, no
 * end date). This will keep spawning successors indefinitely until an
 * admin manually cancels the current occurrence. The Fire 7 wizard doesn't
 * collect an end condition yet — flagged for a later fire if needed.
 */
function spawnNextRecurringOccurrence(array $campaign): ?int {
    $recurrence = json_decode($campaign['recurrence_json'] ?? '{}', true) ?: [];
    if (empty($recurrence['freq']) || !empty($recurrence['spawned'])) return null;

    $base = (int)($campaign['scheduled_at'] ?: time());
    $next = match ($recurrence['freq']) {
        'daily'   => strtotime('+1 day', $base),
        'weekly'  => strtotime('+1 week', $base),
        'monthly' => strtotime('+1 month', $base),
        default   => null,
    };
    if (!$next) return null;

    $db = getDB(); $now = time();
    $db->prepare("INSERT INTO marketing_campaigns
        (company_id, name, channel, template_id, email_template_id, audience_json, variables_json,
         status, schedule_type, scheduled_at, timezone, recurrence_json, created_by_admin_id, created_at, updated_at)
        VALUES (1,?,?,?,?,?,?, 'scheduled','recurring',?,?,?,?,?,?)")
       ->execute([
           $campaign['name'], $campaign['channel'], $campaign['template_id'], $campaign['email_template_id'],
           $campaign['audience_json'], $campaign['variables_json'],
           $next, $campaign['timezone'], json_encode(['freq' => $recurrence['freq'], 'spawned' => false]),
           $campaign['created_by_admin_id'], $now, $now,
       ]);
    $newId = (int)$db->lastInsertId();

    // Eagerly enqueue the successor's recipients — same eager-resolution
    // architecture Fire 7 established for one-time scheduled campaigns.
    enqueueCampaignRecipients($newId);

    // Mark THIS occurrence as having spawned its successor, so a later
    // scheduler run never spawns a second duplicate successor for it.
    $updatedRecurrence = $recurrence; $updatedRecurrence['spawned'] = true;
    $db->prepare("UPDATE marketing_campaigns SET recurrence_json=? WHERE id=?")
       ->execute([json_encode($updatedRecurrence), $campaign['id']]);

    logMarketingAudit('recurring_campaign_spawned', 'marketing_campaigns', $campaign['id'],
        "next_occurrence_id={$newId} next_run=" . date('Y-m-d H:i', $next));

    return $newId;
}