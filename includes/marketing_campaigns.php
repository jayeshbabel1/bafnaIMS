<?php
/**
 * includes/marketing_campaigns.php
 * Fire 7 — Campaign CRUD, audience resolver, safety-review calculations,
 * approval workflow, enqueue pipeline, and test-send.
 *
 * IMPORTANT SCOPE NOTE: enqueueCampaignRecipients() populates
 * marketing_campaign_recipients + marketing_queue only. Actual message
 * dispatch happens in the Fire 8 queue worker — nothing in this file ever
 * calls a provider's send method except sendMarketingTestMessage(), which
 * is a deliberate one-off synchronous exception (a single test message is
 * safe to send inline; a whole campaign never is).
 *
 * KNOWN GAP: recurring campaigns only get their first run enqueued here.
 * Re-triggering after the recurrence interval elapses requires the Fire 8
 * scheduler cron, which does not exist yet.
 */
require_once __DIR__ . '/marketing.php';
require_once __DIR__ . '/marketing_contacts.php';
require_once __DIR__ . '/marketing_templates.php';
require_once __DIR__ . '/marketing_variables.php';
require_once __DIR__ . '/marketing_email.php';
require_once __DIR__ . '/marketing_tracking.php';
require_once __DIR__ . '/marketing_catalog_integration.php';

// ── CRUD ──────────────────────────────────────────────────────────────────
/**
 * Fire 11: automation-triggered sends create one lightweight "shadow"
 * campaign per action per contact-run, reusing the whole campaign
 * pipeline (see includes/marketing_automation.php). They're hidden from
 * the normal Campaigns list by default via the source filter below —
 * otherwise hundreds of automation sends would bury manually-created
 * campaigns. Pass include_automation=true (used by the "View Sends" link
 * on the Automations page) to see them.
 */
function ensureMarketingCampaignAutomationColumns(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $db = getDB();
    $cols = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='marketing_campaigns'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('source', $cols, true)) {
        $db->exec("ALTER TABLE marketing_campaigns ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER approved_by_admin_id");
        $db->exec("ALTER TABLE marketing_campaigns ADD KEY idx_source (source)");
    }
    if (!in_array('automation_run_id', $cols, true)) {
        $db->exec("ALTER TABLE marketing_campaigns ADD COLUMN automation_run_id BIGINT UNSIGNED NULL AFTER source");
    }
    // Fire 12: attach_catalog_id already existed from Fire 2's schema but was
    // never actually written to anywhere until now. attach_selection_pdf is new.
    if (!in_array('attach_selection_pdf', $cols, true)) {
        $db->exec("ALTER TABLE marketing_campaigns ADD COLUMN attach_selection_pdf TINYINT(1) NOT NULL DEFAULT 0 AFTER attach_catalog_id");
    }
}

function getMarketingCampaigns(array $opts = []): array {
    ensureMarketingTables();
    ensureMarketingCampaignAutomationColumns();
    $db = getDB();
    $where = "WHERE 1=1"; $params = [];

    if (empty($opts['include_automation'])) {
        $where .= " AND (source IS NULL OR source='manual')";
    } elseif (!empty($opts['automation_id'])) {
        $where .= " AND source='automation' AND automation_run_id IN (SELECT id FROM marketing_automation_runs WHERE automation_id=?)";
        $params[] = (int)$opts['automation_id'];
    }

    if (!empty($opts['status'])) { $where .= " AND status=?"; $params[] = $opts['status']; }
    if (!empty($opts['channel'])) { $where .= " AND channel=?"; $params[] = $opts['channel']; }
    $search = trim($opts['search'] ?? '');
    if ($search !== '') { $where .= " AND name LIKE ?"; $params[] = "%{$search}%"; }

    $limit = (int)($opts['limit'] ?? 20); $offset = (int)($opts['offset'] ?? 0);
    $cnt = $db->prepare("SELECT COUNT(*) FROM marketing_campaigns $where");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $rowParams = $params; $rowParams[] = $limit; $rowParams[] = $offset;
    $st = $db->prepare("SELECT * FROM marketing_campaigns $where ORDER BY updated_at DESC LIMIT ? OFFSET ?");
    $st->execute($rowParams);
    return ['rows' => $st->fetchAll(), 'total' => $total];
}

function getMarketingCampaign(int $id): ?array {
    ensureMarketingTables();
    $st = getDB()->prepare("SELECT * FROM marketing_campaigns WHERE id=?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function createMarketingCampaignDraft(array $data): array {
    ensureMarketingTables();
    ensureMarketingCampaignAutomationColumns();
    $name = trim($data['name'] ?? '');
    if ($name === '') return ['success' => false, 'error' => 'Campaign name is required.'];
    $channel = in_array($data['channel'] ?? '', ['whatsapp','email','whatsapp_email'], true) ? $data['channel'] : 'email';

    $db = getDB(); $now = time();
    $db->prepare("INSERT INTO marketing_campaigns
        (company_id, name, channel, template_id, email_template_id, audience_json, variables_json,
         attach_catalog_id, attach_selection_pdf,
         status, schedule_type, timezone, created_by_admin_id, created_at, updated_at)
        VALUES (1,?,?,?,?,?,?,?,?, 'draft','now',?,?,?,?)")
       ->execute([
           $name, $channel,
           !empty($data['template_id']) ? (int)$data['template_id'] : null,
           !empty($data['email_template_id']) ? (int)$data['email_template_id'] : null,
           json_encode(['type' => 'all']),
           json_encode([]),
           !empty($data['attach_catalog_id']) ? (int)$data['attach_catalog_id'] : null,
           !empty($data['attach_selection_pdf']) ? 1 : 0,
           $data['timezone'] ?? 'Asia/Kolkata',
           $_SESSION['admin_id'] ?? null, $now, $now,
       ]);
    return ['success' => true, 'id' => (int)$db->lastInsertId()];
}

function updateMarketingCampaignDraft(int $id, array $data): array {
    ensureMarketingTables();
    $c = getMarketingCampaign($id);
    if (!$c) return ['success' => false, 'error' => 'Campaign not found.'];
    if (!in_array($c['status'], ['draft','approved'], true)) {
        return ['success' => false, 'error' => 'Only draft or approved campaigns can be edited.'];
    }

    $name = trim($data['name'] ?? '');
    if ($name === '') return ['success' => false, 'error' => 'Campaign name is required.'];
    $channel = in_array($data['channel'] ?? '', ['whatsapp','email','whatsapp_email'], true) ? $data['channel'] : $c['channel'];

    $audience = $data['audience'] ?? null;
    $audienceJson = $audience !== null ? json_encode($audience) : $c['audience_json'];

    $variables = $data['variables'] ?? null;
    $variablesJson = $variables !== null ? json_encode(array_filter((array)$variables, fn($v) => trim((string)$v) !== '')) : $c['variables_json'];

    // Editing content after approval quietly reverts to draft — an approver
    // signed off on specific content; changing it without re-approval would
    // silently bypass the whole point of the approval permission.
    $newStatus = ($c['status'] === 'approved') ? 'draft' : $c['status'];
    ensureMarketingCampaignAutomationColumns();

    getDB()->prepare("UPDATE marketing_campaigns SET
        name=?, channel=?, template_id=?, email_template_id=?, audience_json=?, variables_json=?,
        attach_catalog_id=?, attach_selection_pdf=?, status=?, updated_at=?
        WHERE id=?")
       ->execute([
           $name, $channel,
           !empty($data['template_id']) ? (int)$data['template_id'] : null,
           !empty($data['email_template_id']) ? (int)$data['email_template_id'] : null,
           $audienceJson, $variablesJson,
           !empty($data['attach_catalog_id']) ? (int)$data['attach_catalog_id'] : null,
           !empty($data['attach_selection_pdf']) ? 1 : 0,
           $newStatus, time(), $id,
       ]);
    return ['success' => true, 'reverted_to_draft' => $newStatus === 'draft' && $c['status'] === 'approved'];
}

function deleteMarketingCampaign(int $id): array {
    $c = getMarketingCampaign($id);
    if (!$c) return ['success' => false, 'error' => 'Campaign not found.'];
    if (!in_array($c['status'], ['draft','cancelled','failed'], true)) {
        return ['success' => false, 'error' => 'Only draft, cancelled, or failed campaigns can be deleted.'];
    }
    getDB()->prepare("DELETE FROM marketing_campaigns WHERE id=?")->execute([$id]);
    return ['success' => true];
}

function duplicateMarketingCampaign(int $id): array {
    $src = getMarketingCampaign($id);
    if (!$src) return ['success' => false, 'error' => 'Campaign not found.'];
    $db = getDB(); $now = time();
    $db->prepare("INSERT INTO marketing_campaigns
        (company_id, name, channel, template_id, email_template_id, audience_json, variables_json,
         status, schedule_type, timezone, created_by_admin_id, created_at, updated_at)
        VALUES (1,?,?,?,?,?,?, 'draft','now',?,?,?,?)")
       ->execute([
           $src['name'] . ' (Copy)', $src['channel'], $src['template_id'], $src['email_template_id'],
           $src['audience_json'], $src['variables_json'], $src['timezone'],
           $_SESSION['admin_id'] ?? null, $now, $now,
       ]);
    return ['success' => true, 'id' => (int)$db->lastInsertId()];
}

// ── Audience resolution ───────────────────────────────────────────────────
function resolveMarketingAudienceContacts(array $audienceConfig): array {
    ensureMarketingContactsModule();
    $db = getDB();
    $type = $audienceConfig['type'] ?? 'all';

    if ($type === 'all') {
        return $db->query("SELECT * FROM marketing_contacts WHERE status='active'")->fetchAll();
    }

    if ($type === 'manual') {
        $ids = array_map('intval', $audienceConfig['contact_ids'] ?? []);
        if (empty($ids)) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $db->prepare("SELECT * FROM marketing_contacts WHERE status='active' AND id IN ($ph)");
        $st->execute($ids);
        return $st->fetchAll();
    }

    if ($type === 'tags') {
        $ids = array_map('intval', $audienceConfig['tag_ids'] ?? []);
        if (empty($ids)) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $db->prepare("SELECT DISTINCT mc.* FROM marketing_contacts mc
                             JOIN marketing_contact_tags mct ON mct.contact_id = mc.id
                             WHERE mc.status='active' AND mct.tag_id IN ($ph)");
        $st->execute($ids);
        return $st->fetchAll();
    }

    if ($type === 'groups') {
        $ids = array_map('intval', $audienceConfig['group_ids'] ?? []);
        if (empty($ids)) return [];
        $byId = [];
        foreach ($ids as $gid) {
            $group = getMarketingGroup($gid);
            if (!$group) continue;
            if ($group['type'] === 'static') {
                $st = $db->prepare("SELECT mc.* FROM marketing_contacts mc
                                     JOIN marketing_group_contacts mgc ON mgc.contact_id = mc.id
                                     WHERE mc.status='active' AND mgc.group_id=?");
                $st->execute([$gid]);
            } else {
                $filter = json_decode($group['filter_json'] ?? '{}', true) ?: [];
                [$where, $params] = buildDynamicGroupWhere($filter);
                $st = $db->prepare("SELECT mc.* FROM marketing_contacts mc WHERE mc.status='active' AND $where");
                $st->execute($params);
            }
            foreach ($st->fetchAll() as $row) $byId[$row['id']] = $row;
        }
        return array_values($byId);
    }

    return [];
}

/** Full safety-review breakdown per channel: eligible/opted-out/invalid/suppressed. */
function getMarketingAudienceBreakdown(array $campaign): array {
    $audienceConfig = json_decode($campaign['audience_json'] ?? '{}', true) ?: [];
    $contacts = resolveMarketingAudienceContacts($audienceConfig);
    $channels = $campaign['channel'] === 'whatsapp_email' ? ['whatsapp', 'email'] : [$campaign['channel']];

    $breakdown = ['total' => count($contacts), 'channels' => []];
    foreach ($channels as $channel) {
        $eligible = 0; $optedOut = 0; $invalid = 0; $suppressed = 0;
        foreach ($contacts as $c) {
            $hasInfo = $channel === 'whatsapp' ? !empty($c['whatsapp_number'] ?: $c['mobile']) : !empty($c['email']);
            $optIn   = $channel === 'whatsapp' ? (bool)$c['whatsapp_opt_in'] : (bool)$c['email_opt_in'];
            $isSuppressed = isMarketingSuppressed((int)$c['id'], $channel);

            if (!$hasInfo)      { $invalid++; continue; }
            if ($isSuppressed)  { $suppressed++; continue; }
            if (!$optIn)        { $optedOut++; continue; }
            $eligible++;
        }
        $breakdown['channels'][$channel] = compact('eligible', 'optedOut', 'invalid', 'suppressed');
    }
    return $breakdown;
}

function estimateMarketingCampaignDuration(string $channel, int $recipientCount): array {
    $hourlyLimit = getMarketingLimit($channel . '_hourly', 0);
    if ($recipientCount === 0) return ['hours' => 0, 'label' => '—'];
    if ($hourlyLimit <= 0)     return ['hours' => 0, 'label' => 'No rate limit configured'];
    $hours = $recipientCount / $hourlyLimit;
    if ($hours < 1) return ['hours' => $hours, 'label' => 'Under 1 hour'];
    $rounded = (int)ceil($hours);
    return ['hours' => $hours, 'label' => $rounded . '+ hour' . ($rounded === 1 ? '' : 's')];
}

// ── Approval workflow ─────────────────────────────────────────────────────
function submitCampaignForApproval(int $id): array {
    $c = getMarketingCampaign($id);
    if (!$c) return ['success' => false, 'error' => 'Campaign not found.'];
    if ($c['status'] !== 'draft') return ['success' => false, 'error' => 'Only draft campaigns can be submitted for approval.'];
    getDB()->prepare("UPDATE marketing_campaigns SET status='pending_approval', updated_at=? WHERE id=?")->execute([time(), $id]);
    return ['success' => true];
}

function approveCampaign(int $id): array {
    $c = getMarketingCampaign($id);
    if (!$c) return ['success' => false, 'error' => 'Campaign not found.'];
    if ($c['status'] !== 'pending_approval') return ['success' => false, 'error' => 'Campaign is not pending approval.'];
    getDB()->prepare("UPDATE marketing_campaigns SET status='approved', approved_by_admin_id=?, updated_at=? WHERE id=?")
           ->execute([$_SESSION['admin_id'] ?? null, time(), $id]);
    return ['success' => true];
}

function rejectCampaign(int $id, string $reason = ''): array {
    $c = getMarketingCampaign($id);
    if (!$c) return ['success' => false, 'error' => 'Campaign not found.'];
    if ($c['status'] !== 'pending_approval') return ['success' => false, 'error' => 'Campaign is not pending approval.'];
    getDB()->prepare("UPDATE marketing_campaigns SET status='draft', updated_at=? WHERE id=?")->execute([time(), $id]);
    logMarketingAudit('campaign_rejected', 'marketing_campaigns', $id, $reason);
    return ['success' => true];
}

// ── Enqueue pipeline (populates recipients + queue; NEVER sends) ─────────
function enqueueCampaignRecipients(int $campaignId): array {
    ensureMarketingCampaignAutomationColumns();
    $campaign = getMarketingCampaign($campaignId);
    if (!$campaign) return ['success' => false, 'error' => 'Campaign not found.'];

    $channels = $campaign['channel'] === 'whatsapp_email' ? ['whatsapp', 'email'] : [$campaign['channel']];
    $audienceConfig = json_decode($campaign['audience_json'], true) ?: [];
    $staticVars = json_decode($campaign['variables_json'] ?? '{}', true) ?: [];
    $emailSettings = getMarketingEmailSettings();

    $contacts = resolveMarketingAudienceContacts($audienceConfig);
    $maxSize = getMarketingLimit('max_campaign_size', 0);
    if ($maxSize > 0 && count($contacts) > $maxSize) {
        return ['success' => false, 'error' => 'Audience size (' . count($contacts) . ") exceeds the configured max campaign size ($maxSize)."];
    }

    $whatsappTemplate = $campaign['template_id'] ? getMarketingTemplate((int)$campaign['template_id']) : null;
    $emailTemplate    = $campaign['email_template_id'] ? getMarketingTemplate((int)$campaign['email_template_id']) : null;

    if (in_array('whatsapp', $channels, true)) {
        if (!$whatsappTemplate) return ['success' => false, 'error' => 'No WhatsApp template selected.'];
        if ($whatsappTemplate['approval_status'] !== 'approved' || !$whatsappTemplate['provider_template_name']) {
            return ['success' => false, 'error' => 'WhatsApp template is not approved/synced from Meta yet — cannot send.'];
        }
    }
    if (in_array('email', $channels, true) && !$emailTemplate) {
        return ['success' => false, 'error' => 'No email template selected.'];
    }

    $db = getDB(); $now = time(); $created = 0;
    $attachmentCache = []; // per-contact, reused across whatsapp+email in a combo campaign — avoids regenerating the same selection PDF twice

    foreach ($contacts as $contact) {
        $attachment = null;
        if (!empty($campaign['attach_catalog_id']) || !empty($campaign['attach_selection_pdf'])) {
            if (!array_key_exists($contact['id'], $attachmentCache)) {
                $attachmentCache[$contact['id']] = resolveMarketingCampaignAttachment($campaign, $contact);
            }
            $attachment = $attachmentCache[$contact['id']];
        }

        foreach ($channels as $channel) {
            if ($channel === 'whatsapp') {
                $number = $contact['whatsapp_number'] ?: $contact['mobile'];
                if (!$number || !$contact['whatsapp_opt_in'] || isMarketingSuppressed((int)$contact['id'], 'whatsapp')) continue;
            } else {
                if (!$contact['email'] || !$contact['email_opt_in'] || isMarketingSuppressed((int)$contact['id'], 'email')) continue;
            }

            $idemKey = marketingIdempotencyKey($campaignId, (int)$contact['id'], $channel);
            $chk = $db->prepare("SELECT id FROM marketing_campaign_recipients WHERE idempotency_key=?");
            $chk->execute([$idemKey]);
            if ($chk->fetch()) continue; // already enqueued — idempotent re-run safety

            $context = buildMarketingVariableContext($contact, $staticVars, $channel);

           if ($channel === 'whatsapp') {
                $bodyComponents = buildWhatsAppBodyComponents($whatsappTemplate, $context);
                $components = $bodyComponents;
                if ($attachment) {
                    $headerComponent = buildWhatsAppHeaderComponent($whatsappTemplate, $attachment);
                    if ($headerComponent) {
                        $components = array_merge([$headerComponent], $bodyComponents);
                    } else {
                        error_log("enqueueCampaignRecipients: attachment requested for contact {$contact['id']} but template '{$whatsappTemplate['name']}' has no document header — sending text-only.");
                    }
                }
                $payload = [
                    'to'                  => $number,
                    'template_name'       => $whatsappTemplate['provider_template_name'],
                    'language'            => $whatsappTemplate['language'],
                    'components'          => $components,
                    'rendered_preview'     => renderMarketingTemplate($whatsappTemplate['body'], $context, 'whatsapp'),
                    'contact_id'          => $contact['id'],
                ];
            } else {
                $footer = renderMarketingTemplate(getMarketingEmailSettings()['unsubscribe_footer'], $context, 'email');
                $payload = [
                    'to'      => $contact['email'],
                    'to_name' => $contact['name'],
                    'subject' => renderMarketingTemplate($emailTemplate['subject'], $context, 'email'),
                    'html'    => renderMarketingTemplate($emailTemplate['body'], $context, 'email')
                                 . '<hr style="margin-top:24px;border:none;border-top:1px solid #eee;"/>'
                                 . '<p style="font-size:11px;color:#999;margin-top:12px;">' . $footer . '</p>',
                    'contact_id'  => $contact['id'],
                    'attachments' => $attachment ? [$attachment['path']] : [],
                ];
            }

                     $db->beginTransaction();
            try {
                $db->prepare("INSERT INTO marketing_campaign_recipients (campaign_id, contact_id, channel, idempotency_key, status, created_at) VALUES (?,?,?,?,?,?)")
                   ->execute([$campaignId, $contact['id'], $channel, $idemKey, 'queued', $now]);
                $recipientId = (int)$db->lastInsertId();

                if ($channel === 'email') {
                    $payload['html'] = rewriteEmailBodyForTracking(
                        $payload['html'], $recipientId,
                        $emailSettings['track_opens'], $emailSettings['track_clicks'],
                        $context['unsubscribe_url'] ?? ''
                    );
                }

                $db->prepare("INSERT INTO marketing_queue (recipient_id, channel, payload_json, status, next_attempt_at, created_at) VALUES (?,?,?,?,?,?)")
                   ->execute([$recipientId, $channel, json_encode($payload), 'pending', $campaign['scheduled_at'] ?: $now, $now]);
                $db->commit();
                $created++;
            } catch (Throwable $e) {
                $db->rollBack();
                error_log('enqueueCampaignRecipients: ' . $e->getMessage());
            }
        }
    }

    return ['success' => true, 'enqueued' => $created, 'audience_size' => count($contacts)];
}

// ── Scheduling / lifecycle ────────────────────────────────────────────────
function scheduleCampaignForSending(int $id, array $data): array {
    $c = getMarketingCampaign($id);
    if (!$c) return ['success' => false, 'error' => 'Campaign not found.'];
    if (!adminCan('marketing.campaigns.send')) return ['success' => false, 'error' => 'You do not have permission to schedule/send campaigns.'];
    if (!in_array($c['status'], ['draft', 'approved'], true)) {
        return ['success' => false, 'error' => 'Campaign must be in draft or approved status to schedule.'];
    }

    $maxConcurrent = getMarketingLimit('max_concurrent_campaigns', 0);
    if ($maxConcurrent > 0) {
        $running = (int)getDB()->query("SELECT COUNT(*) FROM marketing_campaigns WHERE status IN ('running','scheduled')")->fetchColumn();
        if ($running >= $maxConcurrent) {
            return ['success' => false, 'error' => "Concurrent campaign limit reached ($maxConcurrent). Wait for an existing campaign to finish or cancel one."];
        }
    }

    $scheduleType = in_array($data['schedule_type'] ?? '', ['now','once','recurring'], true) ? $data['schedule_type'] : 'now';
    $scheduledAt = time();
    if ($scheduleType === 'once') {
        $scheduledAt = !empty($data['scheduled_at']) ? strtotime($data['scheduled_at']) : false;
        if (!$scheduledAt || $scheduledAt <= time()) return ['success' => false, 'error' => 'Scheduled time must be in the future.'];
    }

    getDB()->prepare("UPDATE marketing_campaigns SET schedule_type=?, scheduled_at=?, timezone=?, recurrence_json=?, updated_at=? WHERE id=?")
           ->execute([
               $scheduleType, $scheduledAt, $data['timezone'] ?? $c['timezone'],
               $scheduleType === 'recurring' ? json_encode($data['recurrence'] ?? []) : null,
               time(), $id,
           ]);

    $enqueueResult = enqueueCampaignRecipients($id);
    if (!$enqueueResult['success']) return $enqueueResult;

    $newStatus = ($scheduleType === 'now') ? 'running' : 'scheduled';
    getDB()->prepare("UPDATE marketing_campaigns SET status=? WHERE id=?")->execute([$newStatus, $id]);

    logMarketingAudit('campaign_scheduled', 'marketing_campaigns', $id, "status={$newStatus} enqueued={$enqueueResult['enqueued']}");
    return ['success' => true, 'status' => $newStatus, 'enqueued' => $enqueueResult['enqueued'], 'audience_size' => $enqueueResult['audience_size']];
}

function pauseCampaign(int $id): array {
    $c = getMarketingCampaign($id);
    if (!$c || $c['status'] !== 'running') return ['success' => false, 'error' => 'Only running campaigns can be paused.'];
    getDB()->prepare("UPDATE marketing_campaigns SET status='paused', updated_at=? WHERE id=?")->execute([time(), $id]);
    logMarketingAudit('campaign_paused', 'marketing_campaigns', $id);
    return ['success' => true];
}

function resumeCampaign(int $id): array {
    $c = getMarketingCampaign($id);
    if (!$c || $c['status'] !== 'paused') return ['success' => false, 'error' => 'Only paused campaigns can be resumed.'];
    getDB()->prepare("UPDATE marketing_campaigns SET status='running', updated_at=? WHERE id=?")->execute([time(), $id]);
    logMarketingAudit('campaign_resumed', 'marketing_campaigns', $id);
    return ['success' => true];
}

function cancelCampaign(int $id): array {
    $c = getMarketingCampaign($id);
    if (!$c || !in_array($c['status'], ['scheduled', 'running', 'paused', 'rate_limited'], true)) {
        return ['success' => false, 'error' => 'Campaign cannot be cancelled from its current status.'];
    }
    $db = getDB();
    $db->prepare("UPDATE marketing_campaigns SET status='cancelled', updated_at=? WHERE id=?")->execute([time(), $id]);
    $db->prepare("UPDATE marketing_queue mq JOIN marketing_campaign_recipients mcr ON mcr.id = mq.recipient_id
                  SET mq.status='cancelled' WHERE mcr.campaign_id=? AND mq.status IN ('pending','rate_limited')")->execute([$id]);
    $db->prepare("UPDATE marketing_campaign_recipients SET status='cancelled' WHERE campaign_id=? AND status IN ('pending','queued','rate_limited')")->execute([$id]);
    logMarketingAudit('campaign_cancelled', 'marketing_campaigns', $id);
    return ['success' => true];
}

// ── Test send (synchronous, one-off — never used for real campaign volume) ─
function sendMarketingTestMessage(int $campaignId, string $channel, string $testTo): array {
    $campaign = getMarketingCampaign($campaignId);
    if (!$campaign) return ['success' => false, 'error' => 'Campaign not found.'];

    $staticVars = array_filter((array)json_decode($campaign['variables_json'] ?? '{}', true) ?: [], fn($v) => $v !== '');
    $context = array_merge(getMarketingSampleContext($channel), $staticVars);

    if ($channel === 'whatsapp') {
        if (!$campaign['template_id']) return ['success' => false, 'error' => 'No WhatsApp template selected for this campaign.'];
        $template = getMarketingTemplate((int)$campaign['template_id']);
        if (!$template) return ['success' => false, 'error' => 'WhatsApp template not found.'];
        if ($template['approval_status'] !== 'approved' || !$template['provider_template_name']) {
            return ['success' => false, 'error' => 'This template is not approved/synced from Meta yet — test sends require an approved template.'];
        }

        require_once __DIR__ . '/marketing/WhatsAppCloudProvider.php';
        $provider = WhatsAppCloudProvider::fromStoredSettings();
        $components = buildWhatsAppBodyComponents($template, $context);
        $result = $provider->sendTemplate($testTo, $template['provider_template_name'], $template['language'], $components);
        if (!$result['success']) return $result;
        return ['success' => true, 'preview' => renderMarketingTemplate($template['body'], $context, 'whatsapp')];
    }

    if ($channel === 'email') {
        if (!$campaign['email_template_id']) return ['success' => false, 'error' => 'No email template selected for this campaign.'];
        $template = getMarketingTemplate((int)$campaign['email_template_id']);
        if (!$template) return ['success' => false, 'error' => 'Email template not found.'];

        require_once __DIR__ . '/marketing/SmtpEmailProvider.php';
        $provider = new SmtpEmailProvider();
        $subject = '[TEST] ' . renderMarketingTemplate($template['subject'], $context, 'email');
        $footer  = renderMarketingTemplate(getMarketingEmailSettings()['unsubscribe_footer'], $context, 'email');
        $html    = renderMarketingTemplate($template['body'], $context, 'email')
                   . '<hr style="margin-top:24px;border:none;border-top:1px solid #eee;"/>'
                   . '<p style="font-size:11px;color:#999;margin-top:12px;">' . $footer . '</p>';

        return $provider->sendTemplate($testTo, $template['name'], $template['language'], ['subject' => $subject, 'html' => $html]);
    }

    return ['success' => false, 'error' => 'Invalid channel.'];
}