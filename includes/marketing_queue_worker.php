<?php
/**
 * includes/marketing_queue_worker.php
 * Fire 8 — dispatches marketing_queue rows to the right provider, with
 * atomic per-channel rate limiting and exponential backoff on failure.
 *
 * Retry design note: there is deliberately NO separate retry script. A
 * "failed but retryable" row is simply moved back to status='pending' with
 * a future next_attempt_at — the same claim query picks it up again on a
 * later cron run. This was flagged as a possible simplification back in
 * Fire 5 and is now confirmed: one worker handles both first attempts and
 * retries.
 */
require_once __DIR__ . '/marketing.php';
require_once __DIR__ . '/marketing_contacts.php';
require_once __DIR__ . '/marketing/WhatsAppCloudProvider.php';
require_once __DIR__ . '/marketing/SmtpEmailProvider.php';

/** Scales claim size to the channel's hourly ceiling so a 15-min cron cadence doesn't starve the queue. */
function getMarketingQueueBatchSize(string $channel): int {
    $hourly = getMarketingLimit($channel . '_hourly', 0);
    if ($hourly <= 0) return 100; // no configured ceiling — use a safe flat default
    $perRun = (int)ceil(($hourly * 0.9) / 4); // 4 runs/hour at 15-min cadence, 10% headroom under the ceiling
    return max(10, $perRun);
}

function runMarketingQueueWorkerCycle(): array {
    ensureMarketingTables();
    $db = getDB();
    $now = time();
    $workerId = gethostname() . ':' . getmypid() . ':' . bin2hex(random_bytes(4));

    // Reclaim locks from a worker that crashed mid-send — 20min gives margin over the 15min cadence.
    $db->prepare("UPDATE marketing_queue SET status='pending', locked_by=NULL, locked_at=NULL
                  WHERE status='processing' AND locked_at IS NOT NULL AND locked_at < ?")
       ->execute([$now - 1200]);

    $stats = [
        'whatsapp' => ['claimed' => 0, 'sent' => 0, 'rate_limited' => 0, 'failed' => 0, 'retried' => 0, 'skipped' => 0],
        'email'    => ['claimed' => 0, 'sent' => 0, 'rate_limited' => 0, 'failed' => 0, 'retried' => 0, 'skipped' => 0],
    ];

    foreach (['whatsapp', 'email'] as $channel) {
        $batchSize = getMarketingQueueBatchSize($channel);
        $lockToken = $workerId . ':' . $channel;

        // Atomic multi-row claim: derived-table trick lets us UPDATE rows
        // selected from the same table (MySQL disallows a direct subquery
        // against the table being updated).
        $db->prepare("UPDATE marketing_queue
            SET status='processing', locked_by=?, locked_at=?
            WHERE id IN (SELECT id FROM (
                SELECT id FROM marketing_queue
                WHERE channel=? AND status IN ('pending','rate_limited') AND next_attempt_at <= ?
                ORDER BY id ASC LIMIT {$batchSize}
            ) t)")
           ->execute([$lockToken, $now, $channel, $now]);

        $claimed = $db->prepare("SELECT * FROM marketing_queue WHERE locked_by=? AND status='processing'");
        $claimed->execute([$lockToken]);
        $rows = $claimed->fetchAll();
        $stats[$channel]['claimed'] = count($rows);

        foreach ($rows as $row) {
            try {
                $stats[$channel][_processMarketingQueueRow($row, $channel)]++;
            } catch (Throwable $e) {
                // Catches anything that could still throw OUTSIDE the
                // dispatch try/catch above (e.g. a DB error while reading
                // the recipient row) — same principle: never let one bad
                // row take down the rest of the batch, and never leave a
                // lock behind for something we already know failed.
               error_log("Marketing queue worker: uncaught exception processing queue row {$row['id']}: " . $e->getMessage());
                $db->prepare("UPDATE marketing_queue SET status='pending', locked_by=NULL, locked_at=NULL, attempt_count=attempt_count+1, last_attempt_at=? WHERE id=?")
                   ->execute([time(), $row['id']]);
                $stats[$channel]['retried']++;
            }
        }
    }

    return $stats;
}

function _processMarketingQueueRow(array $row, string $channel): string {
    $db = getDB();
    $now = time();
    $payload = json_decode($row['payload_json'], true) ?: [];

    $recipStmt = $db->prepare("SELECT mcr.*, mc.status AS campaign_status FROM marketing_campaign_recipients mcr
                                JOIN marketing_campaigns mc ON mc.id = mcr.campaign_id
                                WHERE mcr.id=?");
    $recipStmt->execute([$row['recipient_id']]);
    $recipient = $recipStmt->fetch();

    // Guard: campaign or recipient state changed (cancelled/paused/opted-out)
    // since this row was enqueued — never send, just release the lock.
    if (!$recipient
        || in_array($recipient['status'], ['cancelled', 'opted_out'], true)
        || in_array($recipient['campaign_status'], ['cancelled', 'paused'], true)) {
        $db->prepare("UPDATE marketing_queue SET status='cancelled', locked_by=NULL, locked_at=NULL WHERE id=?")->execute([$row['id']]);
        return 'skipped';
    }

    // Fire 13 — AUTHORITATIVE re-check at actual dispatch time, not just at
    // enqueue time. A row can sit in the queue for hours/days (scheduled
    // sends, rate-limit backoff); a contact can go inactive, opt out, or
    // get suppressed at any point in that window. cancelPendingMarketingSendsForContact()
    // proactively clears the backlog the moment that happens, but this check
    // is what closes the race for anything that slips through between that
    // cleanup and this row's next claim.
    $contactId = (int)($payload['contact_id'] ?? 0);
    $eligSt = $db->prepare("SELECT status, whatsapp_opt_in, email_opt_in FROM marketing_contacts WHERE id=?");
    $eligSt->execute([$contactId]);
    $eligRow = $eligSt->fetch();
    $stillEligible = $eligRow
        && $eligRow['status'] === 'active'
        && ($channel === 'whatsapp' ? (bool)$eligRow['whatsapp_opt_in'] : (bool)$eligRow['email_opt_in']);
    if (!$stillEligible || isMarketingSuppressed($contactId, $channel)) {
        $db->prepare("UPDATE marketing_queue SET status='cancelled', locked_by=NULL, locked_at=NULL WHERE id=?")->execute([$row['id']]);
        $db->prepare("UPDATE marketing_campaign_recipients SET status='opted_out' WHERE id=?")->execute([$recipient['id']]);
        return 'skipped';
    }

    $slot = tryConsumeMarketingRateSlotDetailed($channel);
    if (!$slot['allowed']) {
        $db->prepare("UPDATE marketing_queue SET status='rate_limited', locked_by=NULL, locked_at=NULL, next_attempt_at=? WHERE id=?")
           ->execute([$slot['retry_after'], $row['id']]);
        $db->prepare("UPDATE marketing_campaign_recipients SET status='rate_limited' WHERE id=?")->execute([$recipient['id']]);
        return 'rate_limited';
    }

   try {
        $sendResult = $channel === 'whatsapp' ? _dispatchMarketingWhatsApp($payload) : _dispatchMarketingEmail($payload);
    } catch (Throwable $e) {
        // BUGFIX: an uncaught exception here previously crashed the ENTIRE
        // cron invocation mid-loop, leaving this row locked in 'processing'
        // with no error recorded and forcing a 20-minute wait for the
        // stale-lock reclaim — and every OTHER queued row in this same run
        // silently never got processed either. Now it's treated as a normal
        // send failure and flows through the existing attempt/backoff logic
        // below instead of killing the script.
        error_log("Marketing queue: dispatch exception for queue row {$row['id']} ($channel): " . $e->getMessage());
        $sendResult = ['success' => false, 'error' => $e->getMessage(), 'error_code' => 'exception'];
    }

    if ($sendResult['success']) {
        $db->prepare("INSERT INTO marketing_messages (recipient_id, campaign_id, channel, provider, provider_message_id, status, sent_at, created_at)
                      VALUES (?,?,?,?,?, 'sent', ?, ?)")
           ->execute([
               $recipient['id'], $recipient['campaign_id'], $channel,
               $channel === 'whatsapp' ? 'whatsapp_cloud' : 'smtp',
               $sendResult['provider_message_id'] ?? null, $now, $now,
           ]);
        $db->prepare("UPDATE marketing_campaign_recipients SET status='sent' WHERE id=?")->execute([$recipient['id']]);
        $db->prepare("UPDATE marketing_queue SET status='sent', locked_by=NULL, locked_at=NULL, last_attempt_at=? WHERE id=?")->execute([$now, $row['id']]);
        _touchMarketingContactSendTimestamp((int)($payload['contact_id'] ?? 0), $channel);
        return 'sent';
    }

    // Provider-side throttling (Meta/SMTP host itself refusing) is NOT a
    // real failure — don't burn an attempt against max_attempts for it,
    // just back off to the next hour and retry automatically.
    if (_isMarketingProviderRateLimitError($sendResult)) {
        $db->prepare("UPDATE marketing_queue SET status='rate_limited', locked_by=NULL, locked_at=NULL, next_attempt_at=?, last_attempt_at=? WHERE id=?")
           ->execute([marketingNextHourBoundary(), $now, $row['id']]);
        $db->prepare("UPDATE marketing_campaign_recipients SET status='rate_limited' WHERE id=?")->execute([$recipient['id']]);
        return 'rate_limited';
    }

    // Genuine failure — attempt/backoff/dead-letter.
    $attempts    = (int)$row['attempt_count'] + 1;
    $maxAttempts = (int)$row['max_attempts'];
    $errorCode   = substr((string)($sendResult['error_code'] ?? ''), 0, 60);
    $errorMsg    = substr((string)($sendResult['error'] ?? 'Unknown error'), 0, 500);

    if ($attempts >= $maxAttempts) {
        $db->prepare("INSERT INTO marketing_messages (recipient_id, campaign_id, channel, provider, status, error_code, error_message, created_at)
                      VALUES (?,?,?,?, 'failed', ?, ?, ?)")
           ->execute([$recipient['id'], $recipient['campaign_id'], $channel, $channel === 'whatsapp' ? 'whatsapp_cloud' : 'smtp', $errorCode, $errorMsg, $now]);
        $db->prepare("UPDATE marketing_campaign_recipients SET status='failed' WHERE id=?")->execute([$recipient['id']]);
        $db->prepare("UPDATE marketing_queue SET status='failed', locked_by=NULL, locked_at=NULL, attempt_count=?, last_attempt_at=?, error_code=?, error_message=? WHERE id=?")
           ->execute([$attempts, $now, $errorCode, $errorMsg, $row['id']]);
        error_log("Marketing queue: recipient {$recipient['id']} ($channel) permanently failed after $attempts attempts: $errorMsg");
        return 'failed';
    }

    // Exponential backoff: 5min, 10min, 20min, 40min… capped at 2 hours.
    $backoffSeconds = min(7200, 300 * (2 ** ($attempts - 1)));
    $db->prepare("UPDATE marketing_queue SET status='pending', locked_by=NULL, locked_at=NULL, attempt_count=?, last_attempt_at=?, next_attempt_at=?, error_code=?, error_message=? WHERE id=?")
       ->execute([$attempts, $now, $now + $backoffSeconds, $errorCode, $errorMsg, $row['id']]);
    return 'retried';
}

function _dispatchMarketingWhatsApp(array $payload): array {
    $provider = WhatsAppCloudProvider::fromStoredSettings();
    return $provider->sendTemplate(
        $payload['to'] ?? '',
        $payload['template_name'] ?? '',
        $payload['language'] ?? 'en_US',
        $payload['components'] ?? []
    );
}

function _dispatchMarketingEmail(array $payload): array {
    $provider = new SmtpEmailProvider();
    return $provider->sendTemplate($payload['to'] ?? '', 'campaign', 'en', [
        'subject'     => $payload['subject'] ?? '',
        'html'        => $payload['html'] ?? '',
        'to_name'     => $payload['to_name'] ?? '',
        'attachments' => $payload['attachments'] ?? [], // Fire 12: static catalog / per-client selection PDF, resolved at enqueue time
    ]);
}

/**
 * Heuristic — matches known Meta throttling error codes plus a generic
 * message-text fallback. Not exhaustive; revisit once real production
 * traffic surfaces the actual codes Meta returns for this account's tier.
 */
function _isMarketingProviderRateLimitError(array $result): bool {
    $code = (string)($result['error_code'] ?? '');
    $msg  = strtolower((string)($result['error'] ?? ''));
    if (in_array($code, ['130429', '131056', '80007'], true)) return true;
    return str_contains($msg, 'rate limit') || str_contains($msg, 'too many requests');
}

function _touchMarketingContactSendTimestamp(int $contactId, string $channel): void {
    if (!$contactId) return;
    $col = $channel === 'whatsapp' ? 'last_whatsapp_sent_at' : 'last_email_sent_at';
    getDB()->prepare("UPDATE marketing_contacts SET {$col}=?, last_interaction_at=? WHERE id=?")
           ->execute([time(), time(), $contactId]);
}