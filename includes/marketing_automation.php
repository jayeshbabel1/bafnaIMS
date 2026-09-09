<?php
/**
 * includes/marketing_automation.php
 * Fire 11 — Automation engine: trigger dispatch, condition/wait/action step
 * execution, and the Marble-specific trigger registry.
 *
 * ARCHITECTURE: automation-triggered messages are NOT a separate send path.
 * Each "send" action lazily creates a single-recipient shadow campaign row
 * and hands it to Fire 7's enqueueCampaignRecipients() — so rate limiting
 * (Fire 8), retry/backoff (Fire 8), tracking (Fire 9), and analytics
 * (Fire 10) all apply automatically with zero duplicated logic. A fresh
 * shadow campaign is created per RUN per ACTION (never reused across runs)
 * because marketing_campaign_recipients' idempotency_key is scoped to
 * campaign+contact+channel — reusing one campaign across multiple
 * occurrences of the same automation for the same contact would silently
 * block the second legitimate send.
 */
require_once __DIR__ . '/marketing_campaigns.php'; // enqueueCampaignRecipients(), ensureMarketingCampaignAutomationColumns()

function ensureMarketingAutomationSchema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $db = getDB();

    $autoCols = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='marketing_automations'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('audience_json', $autoCols, true)) {
        $db->exec("ALTER TABLE marketing_automations ADD COLUMN audience_json TEXT NULL AFTER trigger_type");
    }

    $runCols = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='marketing_automation_runs'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('trigger_context_json', $runCols, true)) {
        $db->exec("ALTER TABLE marketing_automation_runs ADD COLUMN trigger_context_json TEXT NULL AFTER status");
    }

    ensureMarketingCampaignAutomationColumns();
}

/**
 * Trigger registry. 'scope' = 'contact' means the event names one specific
 * contact (e.g. the client who made a selection); 'broadcast' means the
 * event has no single contact — the automation's OWN audience config
 * decides who gets enrolled (e.g. "notify everyone in the Architects group
 * about a new catalog").
 */
function marketingAutomationTriggerRegistry(): array {
    return [
        'client_selection_created' => ['label' => 'Client Selection Created', 'scope' => 'contact'],
        'catalog_generated'        => ['label' => 'New Catalog Generated',    'scope' => 'broadcast'],
        'contact_created'          => ['label' => 'New Contact Added',        'scope' => 'contact'],
    ];
}

// ── Automation CRUD ─────────────────────────────────────────────────────
function getMarketingAutomations(): array {
    ensureMarketingAutomationSchema();
    $db = getDB();
    $rows = $db->query("SELECT * FROM marketing_automations ORDER BY updated_at DESC")->fetchAll();
    foreach ($rows as &$a) {
        $stepCnt = $db->prepare("SELECT COUNT(*) FROM marketing_automation_steps WHERE automation_id=?");
        $stepCnt->execute([$a['id']]);
        $runCnt = $db->prepare("SELECT COUNT(*) FROM marketing_automation_runs WHERE automation_id=? AND status='running'");
        $runCnt->execute([$a['id']]);
        $a['step_count'] = (int)$stepCnt->fetchColumn();
        $a['active_runs'] = (int)$runCnt->fetchColumn();
    }
    unset($a);
    return $rows;
}

function getMarketingAutomation(int $id): ?array {
    ensureMarketingAutomationSchema();
    $st = getDB()->prepare("SELECT * FROM marketing_automations WHERE id=?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) return null;
    $row['audience'] = json_decode($row['audience_json'] ?? '{}', true) ?: ['type' => 'all'];
    $row['steps'] = getAutomationSteps($id);
    return $row;
}

function createMarketingAutomation(array $data): array {
    ensureMarketingAutomationSchema();
    $name = trim($data['name'] ?? '');
    $trigger = $data['trigger_type'] ?? '';
    if ($name === '') return ['success' => false, 'error' => 'Automation name is required.'];
    if (!isset(marketingAutomationTriggerRegistry()[$trigger])) return ['success' => false, 'error' => 'Invalid trigger type.'];

    $db = getDB(); $now = time();
    $db->prepare("INSERT INTO marketing_automations (name, trigger_type, audience_json, is_active, created_at, updated_at) VALUES (?,?,?,0,?,?)")
       ->execute([$name, $trigger, json_encode(['type' => 'all']), $now, $now]);
    return ['success' => true, 'id' => (int)$db->lastInsertId()];
}

function updateMarketingAutomation(int $id, array $data): array {
    ensureMarketingAutomationSchema();
    $name = trim($data['name'] ?? '');
    $trigger = $data['trigger_type'] ?? '';
    if ($name === '') return ['success' => false, 'error' => 'Automation name is required.'];
    if (!isset(marketingAutomationTriggerRegistry()[$trigger])) return ['success' => false, 'error' => 'Invalid trigger type.'];

    $audience = json_decode($data['audience_json'] ?? '{}', true);
    getDB()->prepare("UPDATE marketing_automations SET name=?, trigger_type=?, audience_json=?, updated_at=? WHERE id=?")
           ->execute([$name, $trigger, json_encode(is_array($audience) ? $audience : ['type' => 'all']), time(), $id]);
    return ['success' => true];
}

function toggleMarketingAutomationActive(int $id, bool $active): array {
    if ($active) {
        $steps = getAutomationSteps($id);
        if (empty($steps)) return ['success' => false, 'error' => 'Add at least one step before activating.'];
    }
    getDB()->prepare("UPDATE marketing_automations SET is_active=?, updated_at=? WHERE id=?")->execute([$active ? 1 : 0, time(), $id]);
    return ['success' => true];
}

function deleteMarketingAutomation(int $id): void {
    ensureMarketingAutomationSchema();
    // FKs (fk_mas_automation, fk_mar_automation) cascade steps + runs automatically.
    getDB()->prepare("DELETE FROM marketing_automations WHERE id=?")->execute([$id]);
}

// ── Step CRUD ────────────────────────────────────────────────────────────
function getAutomationSteps(int $automationId): array {
    $st = getDB()->prepare("SELECT * FROM marketing_automation_steps WHERE automation_id=? ORDER BY step_order ASC");
    $st->execute([$automationId]);
    $rows = $st->fetchAll();
    foreach ($rows as &$s) $s['config'] = json_decode($s['config_json'], true) ?: [];
    unset($s);
    return $rows;
}

function addAutomationStep(int $automationId, string $stepType, array $config): array {
    if (!in_array($stepType, ['condition', 'wait', 'action'], true)) return ['success' => false, 'error' => 'Invalid step type.'];
    $db = getDB();
    $maxOrder = (int)$db->query("SELECT COALESCE(MAX(step_order), -1) FROM marketing_automation_steps WHERE automation_id=" . (int)$automationId)->fetchColumn();
    $db->prepare("INSERT INTO marketing_automation_steps (automation_id, step_order, step_type, config_json) VALUES (?,?,?,?)")
       ->execute([$automationId, $maxOrder + 1, $stepType, json_encode($config)]);
    return ['success' => true, 'id' => (int)$db->lastInsertId()];
}

function updateAutomationStep(int $stepId, array $config): array {
    getDB()->prepare("UPDATE marketing_automation_steps SET config_json=? WHERE id=?")->execute([json_encode($config), $stepId]);
    return ['success' => true];
}

function deleteAutomationStep(int $stepId): void {
    getDB()->prepare("DELETE FROM marketing_automation_steps WHERE id=?")->execute([$stepId]);
}

function moveAutomationStep(int $stepId, string $direction): array {
    $db = getDB();
    $st = $db->prepare("SELECT * FROM marketing_automation_steps WHERE id=?");
    $st->execute([$stepId]);
    $step = $st->fetch();
    if (!$step) return ['success' => false, 'error' => 'Step not found.'];

    $cmp = $direction === 'up' ? '<' : '>';
    $order = $direction === 'up' ? 'DESC' : 'ASC';
    $neighborSt = $db->prepare("SELECT * FROM marketing_automation_steps WHERE automation_id=? AND step_order {$cmp} ? ORDER BY step_order {$order} LIMIT 1");
    $neighborSt->execute([$step['automation_id'], $step['step_order']]);
    $neighbor = $neighborSt->fetch();
    if (!$neighbor) return ['success' => true]; // already at the edge — no-op, not an error

    $db->prepare("UPDATE marketing_automation_steps SET step_order=? WHERE id=?")->execute([$neighbor['step_order'], $step['id']]);
    $db->prepare("UPDATE marketing_automation_steps SET step_order=? WHERE id=?")->execute([$step['step_order'], $neighbor['id']]);
    return ['success' => true];
}

// ── Trigger dispatch ──────────────────────────────────────────────────────
/**
 * Entry point called from wherever a real app event happens (a client
 * selection is created, a catalog is generated, a contact is added).
 * $context['contact_id'] scopes to one contact (contact-scoped triggers);
 * omitting it treats the trigger as a broadcast — every active automation
 * listening for $eventType enrolls contacts from ITS OWN audience config.
 */
function triggerMarketingAutomationEvent(string $eventType, array $context = []): array {
    ensureMarketingAutomationSchema();
    $db = getDB();
    $st = $db->prepare("SELECT * FROM marketing_automations WHERE trigger_type=? AND is_active=1");
    $st->execute([$eventType]);
    $automations = $st->fetchAll();
    if (empty($automations)) return ['enrolled' => 0, 'automations_matched' => 0];

    $enrolled = 0;
    foreach ($automations as $automation) {
        $audience = json_decode($automation['audience_json'] ?? '{}', true) ?: ['type' => 'all'];

        if (!empty($context['contact_id'])) {
            $contact = getMarketingContact((int)$context['contact_id']);
            if (!$contact || $contact['status'] !== 'active') continue;
            if (!_marketingContactMatchesAudience($contact, $audience)) continue;
            $enrolled += _enrollContactInAutomation($automation, $contact, $context) ? 1 : 0;
        } else {
            foreach (resolveMarketingAudienceContacts($audience) as $contact) {
                $enrolled += _enrollContactInAutomation($automation, $contact, $context) ? 1 : 0;
            }
        }
    }
    return ['enrolled' => $enrolled, 'automations_matched' => count($automations)];
}

/**
 * Reuses the campaign-audience resolver as a membership check. Fine at this
 * app's scale (hundreds–low thousands of contacts, infrequent trigger
 * events) — flagged as a shortcut, not built for high-frequency dispatch.
 */
function _marketingContactMatchesAudience(array $contact, array $audience): bool {
    if (($audience['type'] ?? 'all') === 'all') return true;
    foreach (resolveMarketingAudienceContacts($audience) as $m) {
        if ((int)$m['id'] === (int)$contact['id']) return true;
    }
    return false;
}

function _enrollContactInAutomation(array $automation, array $contact, array $context): bool {
    $db = getDB();
    // Dedupe: don't stack a second concurrent chain of the SAME automation
    // for the SAME contact. A later trigger after the first chain finishes
    // is always allowed — this only blocks overlapping *active* runs.
    $chk = $db->prepare("SELECT id FROM marketing_automation_runs WHERE automation_id=? AND contact_id=? AND status='running'");
    $chk->execute([$automation['id'], $contact['id']]);
    if ($chk->fetch()) return false;

    $db->prepare("INSERT INTO marketing_automation_runs (automation_id, contact_id, current_step, next_run_at, status, trigger_context_json, created_at)
                  VALUES (?,?,0,?, 'running', ?, ?)")
       ->execute([$automation['id'], $contact['id'], time(), json_encode($context), time()]);
    return true;
}

// ── Run execution (called by cron/marketing_automation_worker.php) ──────
function runMarketingAutomationWorkerCycle(): array {
    ensureMarketingAutomationSchema();
    $db = getDB();
    $now = time();
    $stats = ['claimed' => 0, 'advanced' => 0, 'completed' => 0, 'stopped' => 0, 'actions_fired' => 0];

    $due = $db->prepare("SELECT * FROM marketing_automation_runs WHERE status='running' AND next_run_at<=? ORDER BY id ASC LIMIT 200");
    $due->execute([$now]);
    $runs = $due->fetchAll();
    $stats['claimed'] = count($runs);

    foreach ($runs as $run) _processAutomationRun($run, $stats);
    return $stats;
}

function _processAutomationRun(array $run, array &$stats): void {
    $db = getDB();
    $steps = getAutomationSteps((int)$run['automation_id']);
    $idx = (int)$run['current_step'];

    if (!isset($steps[$idx])) {
        $db->prepare("UPDATE marketing_automation_runs SET status='completed' WHERE id=?")->execute([$run['id']]);
        $stats['completed']++;
        return;
    }

    $contact = getMarketingContact((int)$run['contact_id']);
    if (!$contact || $contact['status'] !== 'active') {
        $db->prepare("UPDATE marketing_automation_runs SET status='cancelled' WHERE id=?")->execute([$run['id']]);
        $stats['stopped']++;
        return;
    }

    $step = $steps[$idx];
    $config = $step['config'];

    if ($step['step_type'] === 'condition') {
        if (_evaluateAutomationCondition($contact, $config)) {
            $db->prepare("UPDATE marketing_automation_runs SET current_step=current_step+1, next_run_at=? WHERE id=?")->execute([time(), $run['id']]);
            $stats['advanced']++;
        } elseif (($config['on_false'] ?? 'stop') === 'skip') {
            $db->prepare("UPDATE marketing_automation_runs SET current_step=current_step+1, next_run_at=? WHERE id=?")->execute([time(), $run['id']]);
            $stats['advanced']++;
        } else {
            $db->prepare("UPDATE marketing_automation_runs SET status='cancelled' WHERE id=?")->execute([$run['id']]);
            $stats['stopped']++;
        }
        return;
    }

    if ($step['step_type'] === 'wait') {
        $unit = $config['unit'] ?? 'hours';
        $value = max(1, (int)($config['value'] ?? 1));
        $seconds = match ($unit) { 'minutes' => $value * 60, 'days' => $value * 86400, default => $value * 3600 };
        $db->prepare("UPDATE marketing_automation_runs SET current_step=current_step+1, next_run_at=? WHERE id=?")->execute([time() + $seconds, $run['id']]);
        $stats['advanced']++;
        return;
    }

    if ($step['step_type'] === 'action') {
        _executeAutomationAction($run, $contact, $config);
        $db->prepare("UPDATE marketing_automation_runs SET current_step=current_step+1, next_run_at=? WHERE id=?")->execute([time(), $run['id']]);
        $stats['actions_fired']++;
    }
}

function _evaluateAutomationCondition(array $contact, array $config): bool {
    $field = $config['field'] ?? '';
    $op = $config['op'] ?? '=';
    $value = $config['value'] ?? '';

    if ($field === 'tag') {
        $tagIds = array_map('intval', (array)$value);
        if (empty($tagIds)) return true;
        $ph = implode(',', array_fill(0, count($tagIds), '?'));
        $st = getDB()->prepare("SELECT COUNT(*) FROM marketing_contact_tags WHERE contact_id=? AND tag_id IN ($ph)");
        $st->execute(array_merge([$contact['id']], $tagIds));
        return (int)$st->fetchColumn() > 0;
    }

    $allowed = ['city', 'role', 'experience', 'status', 'whatsapp_opt_in', 'email_opt_in', 'source_type'];
    if (!in_array($field, $allowed, true)) return true; // unknown/misconfigured field — fail OPEN rather than silently halting every run
    $actual = (string)($contact[$field] ?? '');
    if ($op === 'contains') return str_contains(mb_strtolower($actual), mb_strtolower((string)$value));
    if ($op === '!=') return $actual !== (string)$value;
    return $actual === (string)$value;
}

function _executeAutomationAction(array $run, array $contact, array $config): void {
    $actionType = $config['action_type'] ?? '';
    switch ($actionType) {
        case 'add_tag':
            if (!empty($config['tag_id'])) bulkAddTagToContacts([$contact['id']], (int)$config['tag_id']);
            break;
        case 'add_to_group':
            if (!empty($config['group_id'])) bulkAddContactsToGroup([$contact['id']], (int)$config['group_id']);
            break;
        case 'send_whatsapp':
            _dispatchAutomationMessage($run, $contact, 'whatsapp', (int)($config['template_id'] ?? 0));
            break;
        case 'send_email':
            _dispatchAutomationMessage($run, $contact, 'email', (int)($config['template_id'] ?? 0));
            break;
    }
    logMarketingAudit('automation_action_executed', 'marketing_automation_runs', $run['id'], "action={$actionType}");
}

function _dispatchAutomationMessage(array $run, array $contact, string $channel, int $templateId): void {
    if (!$templateId) return;
    ensureMarketingCampaignAutomationColumns();
    $db = getDB(); $now = time();

    $nameSt = $db->prepare("SELECT name FROM marketing_automations WHERE id=?");
    $nameSt->execute([$run['automation_id']]);
    $automationName = $nameSt->fetchColumn() ?: 'Automation';

    // Trigger-time context (e.g. product_name from a client_selection_created
    // event) flows straight into the shadow campaign's variables_json, so
    // Fire 7's buildMarketingVariableContext() merges it in as a static
    // override at enqueue time — the template can reference it immediately.
    $variablesJson = $run['trigger_context_json'] ?: '{}';

    $db->prepare("INSERT INTO marketing_campaigns
        (company_id, name, channel, template_id, email_template_id, audience_json, variables_json,
         status, schedule_type, scheduled_at, timezone, source, automation_run_id, created_at, updated_at)
        VALUES (1,?,?,?,?,?,?, 'draft','now',?,?,'automation',?,?,?)")
       ->execute([
           "Automation: {$automationName} → {$contact['name']}", $channel,
           $channel === 'whatsapp' ? $templateId : null,
           $channel === 'email' ? $templateId : null,
           json_encode(['type' => 'manual', 'contact_ids' => [$contact['id']]]),
           $variablesJson, $now, 'Asia/Kolkata', $run['id'], $now, $now,
       ]);
    $shadowCampaignId = (int)$db->lastInsertId();

    $result = enqueueCampaignRecipients($shadowCampaignId);
    $db->prepare("UPDATE marketing_campaigns SET status='running' WHERE id=?")->execute([$shadowCampaignId]);

    if (!$result['success']) {
        error_log("Automation shadow campaign {$shadowCampaignId} (run {$run['id']}) failed to enqueue: " . ($result['error'] ?? 'unknown'));
    }
}