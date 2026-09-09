<?php
/**
 * includes/marketing_contacts.php
 * Fire 3 — Contacts / Groups / Tags CRUD + sync-from-users/clients + CSV import.
 * Requires includes/marketing.php (schema bootstrap, encryption, rate limiter).
 */

require_once __DIR__ . '/marketing.php';

// ── Migration: extra columns needed for dynamic-group segmentation ────────
function ensureMarketingContactExtraColumns(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $db = getDB();
    $cols = $db->query("
        SELECT COLUMN_NAME FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marketing_contacts'
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('role', $cols, true)) {
        $db->exec("ALTER TABLE marketing_contacts ADD COLUMN role VARCHAR(50) NULL AFTER city");
        $db->exec("ALTER TABLE marketing_contacts ADD KEY idx_role (role)");
    }
    if (!in_array('experience', $cols, true)) {
        $db->exec("ALTER TABLE marketing_contacts ADD COLUMN experience VARCHAR(50) NULL AFTER role");
    }
}

function ensureMarketingContactsModule(): void {
    ensureMarketingTables();
    ensureMarketingContactExtraColumns();
}

// ── Sync: pull from existing users/clients tables (never a CRM duplicate) ──
function syncContactsFromUsers(): array {
    ensureMarketingContactsModule();
    $db = getDB();
    $users = $db->query("SELECT * FROM users")->fetchAll();
    $created = 0; $updated = 0; $now = time();

    foreach ($users as $u) {
        $chk = $db->prepare("SELECT id FROM marketing_contacts WHERE source_type='user' AND source_id=?");
        $chk->execute([$u['id']]);
        $existing = $chk->fetch();

        $fields = [
            'name'            => $u['name'],
            'mobile'          => $u['phone'] ?: null,
            'whatsapp_number' => $u['phone'] ?: null,
            'email'           => $u['email'] ?: null,
            'city'            => $u['city'] ?: null,
            'role'            => $u['role'] ?: null,
            'experience'      => $u['experience'] ?: null,
            'status'          => (int)($u['is_verified'] ?? 1) ? 'active' : 'inactive',
        ];

        if ($existing) {
            $set  = implode(',', array_map(fn($k) => "$k=?", array_keys($fields)));
            $vals = array_values($fields);
            $vals[] = $now; $vals[] = $existing['id'];
            $db->prepare("UPDATE marketing_contacts SET $set, updated_at=? WHERE id=?")->execute($vals);
            $updated++;
        } else {
            $insert = array_merge(['source_type' => 'user', 'source_id' => $u['id']], $fields,
                                   ['created_at' => $now, 'updated_at' => $now]);
            $colsList = implode(',', array_keys($insert));
            $phList   = implode(',', array_fill(0, count($insert), '?'));
            $db->prepare("INSERT INTO marketing_contacts ($colsList) VALUES ($phList)")
               ->execute(array_values($insert));
            $created++;
        }
    }
    return ['created' => $created, 'updated' => $updated, 'scanned' => count($users)];
}

function syncContactsFromClients(): array {
    ensureMarketingContactsModule();
    $db = getDB();
    $clients = $db->query("SELECT * FROM clients")->fetchAll();
    $created = 0; $updated = 0; $now = time();

    foreach ($clients as $c) {
        $chk = $db->prepare("SELECT id FROM marketing_contacts WHERE source_type='client' AND source_id=?");
        $chk->execute([$c['id']]);
        $existing = $chk->fetch();

        $fields = [
            'name'            => $c['client_name'],
            'mobile'          => $c['client_mobile'] ?: null,
            'whatsapp_number' => $c['client_mobile'] ?: null,
            'email'           => null, // clients table has no email column
            'city'            => null, // site_address is free text, not reliably parseable
            'status'          => 'active',
        ];

        if ($existing) {
            $set  = implode(',', array_map(fn($k) => "$k=?", array_keys($fields)));
            $vals = array_values($fields);
            $vals[] = $now; $vals[] = $existing['id'];
            $db->prepare("UPDATE marketing_contacts SET $set, updated_at=? WHERE id=?")->execute($vals);
            $updated++;
        } else {
            $insert = array_merge(['source_type' => 'client', 'source_id' => $c['id']], $fields,
                                   ['created_at' => $now, 'updated_at' => $now]);
            $colsList = implode(',', array_keys($insert));
            $phList   = implode(',', array_fill(0, count($insert), '?'));
            $db->prepare("INSERT INTO marketing_contacts ($colsList) VALUES ($phList)")
               ->execute(array_values($insert));
            $created++;
        }
    }
    return ['created' => $created, 'updated' => $updated, 'scanned' => count($clients)];
}

// ── Dynamic-group filter → SQL (whitelisted fields only) ───────────────────
function buildDynamicGroupWhere(array $filter): array {
    $allowedFields = ['city', 'role', 'experience', 'status', 'whatsapp_opt_in', 'email_opt_in', 'source_type'];
    $match = (($filter['match'] ?? 'all') === 'any') ? 'OR' : 'AND';
    $clauses = []; $params = [];

    foreach (($filter['rules'] ?? []) as $rule) {
        $field = $rule['field'] ?? '';
        $op    = $rule['op'] ?? '=';
        $value = $rule['value'] ?? '';

        if ($field === 'tag') {
            if (!is_array($value) || empty($value)) continue;
            $ph = implode(',', array_fill(0, count($value), '?'));
            $clauses[] = "mc.id IN (SELECT contact_id FROM marketing_contact_tags WHERE tag_id IN ($ph))";
            foreach ($value as $v) $params[] = (int)$v;
            continue;
        }
        if (!in_array($field, $allowedFields, true)) continue;

        if ($op === 'contains')   { $clauses[] = "mc.$field LIKE ?";  $params[] = '%' . $value . '%'; }
        elseif ($op === '!=')     { $clauses[] = "mc.$field != ?";    $params[] = $value; }
        else                      { $clauses[] = "mc.$field = ?";     $params[] = $value; }
    }

    if (empty($clauses)) return ['1=1', []];
    return ['(' . implode(" $match ", $clauses) . ')', $params];
}

// ── Contacts CRUD ────────────────────────────────────────────────────────
function getMarketingContacts(array $opts = []): array {
    ensureMarketingContactsModule();
    $db = getDB();
    $where = "WHERE 1=1"; $params = [];

    $search = trim($opts['search'] ?? '');
    if ($search !== '') {
        $where .= " AND (mc.name LIKE ? OR mc.mobile LIKE ? OR mc.email LIKE ?)";
        $like = "%{$search}%"; $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if (!empty($opts['source_type'])) { $where .= " AND mc.source_type=?"; $params[] = $opts['source_type']; }
    if (!empty($opts['status']))      { $where .= " AND mc.status=?";      $params[] = $opts['status']; }
    if (!empty($opts['tag_id'])) {
        $where .= " AND mc.id IN (SELECT contact_id FROM marketing_contact_tags WHERE tag_id=?)";
        $params[] = (int)$opts['tag_id'];
    }
    if (!empty($opts['group_id'])) {
        $group = getMarketingGroup((int)$opts['group_id']);
        if ($group) {
            if ($group['type'] === 'static') {
                $where .= " AND mc.id IN (SELECT contact_id FROM marketing_group_contacts WHERE group_id=?)";
                $params[] = $group['id'];
            } else {
                $filter = json_decode($group['filter_json'] ?? '{}', true) ?: [];
                [$dynWhere, $dynParams] = buildDynamicGroupWhere($filter);
                $where .= " AND $dynWhere";
                $params = array_merge($params, $dynParams);
            }
        }
    }

    $limit = (int)($opts['limit'] ?? 20); $offset = (int)($opts['offset'] ?? 0);

    $cnt = $db->prepare("SELECT COUNT(*) FROM marketing_contacts mc $where");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $rowParams = $params; $rowParams[] = $limit; $rowParams[] = $offset;
    $st = $db->prepare("SELECT mc.* FROM marketing_contacts mc $where ORDER BY mc.created_at DESC LIMIT ? OFFSET ?");
    $st->execute($rowParams);
    $rows = $st->fetchAll();

    if ($rows) {
        $ids = array_column($rows, 'id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $tagSt = $db->prepare("SELECT mct.contact_id, t.id, t.name, t.color
                                FROM marketing_contact_tags mct JOIN marketing_tags t ON t.id = mct.tag_id
                                WHERE mct.contact_id IN ($ph)");
        $tagSt->execute($ids);
        $byContact = [];
        foreach ($tagSt->fetchAll() as $t) $byContact[$t['contact_id']][] = $t;
        foreach ($rows as &$r) $r['tags'] = $byContact[$r['id']] ?? [];
        unset($r);
    }

    return ['rows' => $rows, 'total' => $total];
}

function getMarketingContact(int $id): ?array {
    ensureMarketingContactsModule();
    $st = getDB()->prepare("SELECT * FROM marketing_contacts WHERE id=?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function createMarketingContact(array $data): array {
    ensureMarketingContactsModule();
    $name   = trim($data['name'] ?? '');
    $mobile = trim($data['mobile'] ?? '');
    $email  = trim($data['email'] ?? '');
    if ($name === '')                     return ['success' => false, 'error' => 'Name is required.'];
    if ($mobile === '' && $email === '')  return ['success' => false, 'error' => 'Provide at least a mobile or email.'];
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Enter a valid email address.'];
    }

    $db = getDB(); $now = time();
    $db->prepare("INSERT INTO marketing_contacts
        (source_type, name, mobile, whatsapp_number, email, city, status, whatsapp_opt_in, email_opt_in, created_at, updated_at)
        VALUES ('manual',?,?,?,?,?,?,?,?,?,?)")
       ->execute([
           $name, $mobile ?: null, $mobile ?: null, $email ?: null,
           trim($data['city'] ?? '') ?: null, 'active',
           !empty($data['whatsapp_opt_in']) ? 1 : 0,
           !empty($data['email_opt_in']) ? 1 : 0,
           $now, $now,
       ]);
    $newId = (int)$db->lastInsertId();

    // Fire 11: fires 'contact_created'. Deliberately wired ONLY here (single
    // manual "Add Contact") — NOT in syncContactsFromUsers/Clients() or the
    // CSV importer, where a first bulk sync would otherwise enroll hundreds
    // of pre-existing people into a "welcome" automation all at once.
    // function_exists() guard means this file has no hard dependency on
    // marketing_automation.php being loaded elsewhere.
    if (function_exists('triggerMarketingAutomationEvent')) {
        triggerMarketingAutomationEvent('contact_created', ['contact_id' => $newId]);
    }

    return ['success' => true, 'id' => $newId];
}

function updateMarketingContact(int $id, array $data): array {
    ensureMarketingContactsModule();
    $name   = trim($data['name'] ?? '');
    $mobile = trim($data['mobile'] ?? '');
    $email  = trim($data['email'] ?? '');
    if ($name === '') return ['success' => false, 'error' => 'Name is required.'];
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Enter a valid email address.'];
    }

    // Fire 13: capture prior opt-in state so a manual flip-off (independent
    // of the suppression table — see Fire 10's comment on the two being
    // separate mechanisms) also clears any backlog on that channel.
    $before = getMarketingContact($id);
    $newWaOptIn = !empty($data['whatsapp_opt_in']) ? 1 : 0;
    $newEmailOptIn = !empty($data['email_opt_in']) ? 1 : 0;

    getDB()->prepare("UPDATE marketing_contacts
        SET name=?, mobile=?, whatsapp_number=?, email=?, city=?, status=?, whatsapp_opt_in=?, email_opt_in=?, updated_at=?
        WHERE id=?")
       ->execute([
           $name, $mobile ?: null, $mobile ?: null, $email ?: null,
           trim($data['city'] ?? '') ?: null,
           in_array($data['status'] ?? '', ['active','inactive'], true) ? $data['status'] : 'active',
           $newWaOptIn, $newEmailOptIn,
           time(), $id,
       ]);

    if ($before && (int)$before['whatsapp_opt_in'] === 1 && $newWaOptIn === 0) {
        cancelPendingMarketingSendsForContact($id, 'whatsapp');
    }
    if ($before && (int)$before['email_opt_in'] === 1 && $newEmailOptIn === 0) {
        cancelPendingMarketingSendsForContact($id, 'email');
    }

    return ['success' => true];
}

function deleteMarketingContact(int $id): void {
    ensureMarketingContactsModule();
    getDB()->prepare("DELETE FROM marketing_contacts WHERE id=?")->execute([$id]);
}

// ── Tags CRUD ────────────────────────────────────────────────────────────
function getAllMarketingTags(): array {
    ensureMarketingContactsModule();
    return getDB()->query("
        SELECT t.*, (SELECT COUNT(*) FROM marketing_contact_tags mct WHERE mct.tag_id = t.id) AS contact_count
        FROM marketing_tags t ORDER BY t.name ASC
    ")->fetchAll();
}

function createMarketingTag(string $name, string $color = ''): array {
    ensureMarketingContactsModule();
    $name = trim($name);
    if ($name === '') return ['success' => false, 'error' => 'Tag name is required.'];
    $db = getDB();
    $chk = $db->prepare("SELECT id FROM marketing_tags WHERE company_id=1 AND name=?");
    $chk->execute([$name]);
    if ($chk->fetch()) return ['success' => false, 'error' => 'A tag with that name already exists.'];
    $db->prepare("INSERT INTO marketing_tags (company_id, name, color, created_at) VALUES (1,?,?,?)")
       ->execute([$name, $color ?: null, time()]);
    return ['success' => true, 'id' => (int)$db->lastInsertId()];
}

function updateMarketingTag(int $id, string $name, string $color = ''): array {
    ensureMarketingContactsModule();
    $name = trim($name);
    if ($name === '') return ['success' => false, 'error' => 'Tag name is required.'];
    $db = getDB();
    $chk = $db->prepare("SELECT id FROM marketing_tags WHERE company_id=1 AND name=? AND id<>?");
    $chk->execute([$name, $id]);
    if ($chk->fetch()) return ['success' => false, 'error' => 'Another tag already uses that name.'];
    $db->prepare("UPDATE marketing_tags SET name=?, color=? WHERE id=?")->execute([$name, $color ?: null, $id]);
    return ['success' => true];
}

function deleteMarketingTag(int $id): void {
    ensureMarketingContactsModule();
    getDB()->prepare("DELETE FROM marketing_tags WHERE id=?")->execute([$id]);
}

// ── Groups CRUD (static + dynamic) ──────────────────────────────────────
function getMarketingGroups(): array {
    ensureMarketingContactsModule();
    $groups = getDB()->query("SELECT * FROM marketing_groups ORDER BY created_at DESC")->fetchAll();
    foreach ($groups as &$g) $g['contact_count'] = getMarketingGroupContactCount($g);
    unset($g);
    return $groups;
}

function getMarketingGroup(int $id): ?array {
    ensureMarketingContactsModule();
    $st = getDB()->prepare("SELECT * FROM marketing_groups WHERE id=?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function getMarketingGroupContactCount(array $group): int {
    $db = getDB();
    if ($group['type'] === 'static') {
        $st = $db->prepare("SELECT COUNT(*) FROM marketing_group_contacts WHERE group_id=?");
        $st->execute([$group['id']]);
        return (int)$st->fetchColumn();
    }
    $filter = json_decode($group['filter_json'] ?? '{}', true) ?: [];
    [$where, $params] = buildDynamicGroupWhere($filter);
    $st = $db->prepare("SELECT COUNT(*) FROM marketing_contacts mc WHERE $where");
    $st->execute($params);
    return (int)$st->fetchColumn();
}

function createMarketingGroup(array $data): array {
    ensureMarketingContactsModule();
    $name = trim($data['name'] ?? '');
    $type = (($data['type'] ?? 'static') === 'dynamic') ? 'dynamic' : 'static';
    if ($name === '') return ['success' => false, 'error' => 'Group name is required.'];

    $filterJson = null;
    if ($type === 'dynamic') {
        $filter = json_decode($data['filter_json'] ?? '{}', true);
        $filterJson = json_encode(is_array($filter) ? $filter : []);
    }

    $db = getDB(); $now = time();
    $db->prepare("INSERT INTO marketing_groups (company_id, name, type, filter_json, created_at, updated_at)
                  VALUES (1,?,?,?,?,?)")
       ->execute([$name, $type, $filterJson, $now, $now]);
    return ['success' => true, 'id' => (int)$db->lastInsertId()];
}

function updateMarketingGroup(int $id, array $data): array {
    ensureMarketingContactsModule();
    $name = trim($data['name'] ?? '');
    if ($name === '') return ['success' => false, 'error' => 'Group name is required.'];
    $group = getMarketingGroup($id);
    if (!$group) return ['success' => false, 'error' => 'Group not found.'];

    $filterJson = $group['filter_json'];
    if ($group['type'] === 'dynamic' && isset($data['filter_json'])) {
        $filter = json_decode($data['filter_json'], true);
        $filterJson = json_encode(is_array($filter) ? $filter : []);
    }

    getDB()->prepare("UPDATE marketing_groups SET name=?, filter_json=?, updated_at=? WHERE id=?")
           ->execute([$name, $filterJson, time(), $id]);
    return ['success' => true];
}

function deleteMarketingGroup(int $id): void {
    ensureMarketingContactsModule();
    $db = getDB();
    $db->prepare("DELETE FROM marketing_group_contacts WHERE group_id=?")->execute([$id]);
    $db->prepare("DELETE FROM marketing_groups WHERE id=?")->execute([$id]);
}

// ── Bulk actions ─────────────────────────────────────────────────────────
function bulkAddTagToContacts(array $contactIds, int $tagId): void {
    if (empty($contactIds) || !$tagId) return;
    $db = getDB();
    $st = $db->prepare("INSERT IGNORE INTO marketing_contact_tags (contact_id, tag_id) VALUES (?,?)");
    foreach ($contactIds as $cid) $st->execute([(int)$cid, $tagId]);
}

function bulkRemoveTagFromContacts(array $contactIds, int $tagId): void {
    if (empty($contactIds) || !$tagId) return;
    $db = getDB();
    $ph = implode(',', array_fill(0, count($contactIds), '?'));
    $params = array_map('intval', $contactIds); $params[] = $tagId;
    $db->prepare("DELETE FROM marketing_contact_tags WHERE contact_id IN ($ph) AND tag_id=?")->execute($params);
}

function bulkAddContactsToGroup(array $contactIds, int $groupId): void {
    if (empty($contactIds) || !$groupId) return;
    $db = getDB();
    $st = $db->prepare("INSERT IGNORE INTO marketing_group_contacts (group_id, contact_id) VALUES (?,?)");
    foreach ($contactIds as $cid) $st->execute([$groupId, (int)$cid]);
}

function bulkRemoveContactsFromGroup(array $contactIds, int $groupId): void {
    if (empty($contactIds) || !$groupId) return;
    $db = getDB();
    $ph = implode(',', array_fill(0, count($contactIds), '?'));
    $params = [$groupId]; foreach ($contactIds as $c) $params[] = (int)$c;
    $db->prepare("DELETE FROM marketing_group_contacts WHERE group_id=? AND contact_id IN ($ph)")->execute($params);
}

function bulkDeleteContacts(array $contactIds): int {
    if (empty($contactIds)) return 0;
    $db = getDB();
    $ph = implode(',', array_fill(0, count($contactIds), '?'));
    $st = $db->prepare("DELETE FROM marketing_contacts WHERE id IN ($ph)");
    $st->execute(array_map('intval', $contactIds));
    return $st->rowCount();
}

// ── CSV import — fixed header map (mirrors admin/index.php::importExcel()) ─
function importMarketingContactsCsv(string $filePath): array {
    ensureMarketingContactsModule();
    $db = getDB();
    $fh = fopen($filePath, 'r');
    if (!$fh) return ['success' => false, 'error' => 'Could not open uploaded file.'];

    $headerRow = fgetcsv($fh);
    if (!$headerRow) { fclose($fh); return ['success' => false, 'error' => 'File appears empty.']; }

    $headerMap = [
        'name' => 'name', 'mobile' => 'mobile', 'phone' => 'mobile',
        'whatsapp' => 'whatsapp_number', 'whatsapp number' => 'whatsapp_number',
        'email' => 'email', 'city' => 'city',
    ];
    $cols = [];
    foreach ($headerRow as $h) $cols[] = $headerMap[mb_strtolower(trim($h))] ?? null;

    $created = 0; $updated = 0; $skipped = 0; $errors = [];
    $now = time(); $rowNum = 1;

    while (($row = fgetcsv($fh)) !== false) {
        $rowNum++;
        if (empty(array_filter($row, fn($v) => trim((string)$v) !== ''))) continue;

        $data = [];
        foreach ($cols as $i => $field) if ($field) $data[$field] = trim((string)($row[$i] ?? ''));

        $name   = $data['name'] ?? '';
        $mobile = $data['mobile'] ?? '';
        $email  = $data['email'] ?? '';
        if ($name === '' || ($mobile === '' && $email === '')) {
            $skipped++; $errors[] = "Row {$rowNum}: missing name or contact info."; continue;
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $skipped++; $errors[] = "Row {$rowNum}: invalid email '{$email}'."; continue;
        }

        // Duplicate priority per spec: mobile first, then email.
        $existing = null;
        if ($mobile !== '') {
            $st = $db->prepare("SELECT id FROM marketing_contacts WHERE mobile=? LIMIT 1"); $st->execute([$mobile]); $existing = $st->fetch();
        }
        if (!$existing && $email !== '') {
            $st = $db->prepare("SELECT id FROM marketing_contacts WHERE email=? LIMIT 1"); $st->execute([$email]); $existing = $st->fetch();
        }

        $fields = [
            'name'            => $name,
            'mobile'          => $mobile ?: null,
            'whatsapp_number' => $data['whatsapp_number'] ?? ($mobile ?: null),
            'email'           => $email ?: null,
            'city'            => $data['city'] ?? null,
        ];

        if ($existing) {
            $set  = implode(',', array_map(fn($k) => "$k=?", array_keys($fields)));
            $vals = array_values($fields); $vals[] = $now; $vals[] = $existing['id'];
            $db->prepare("UPDATE marketing_contacts SET $set, updated_at=? WHERE id=?")->execute($vals);
            $updated++;
        } else {
            $insert = array_merge(['source_type' => 'import'], $fields, ['created_at' => $now, 'updated_at' => $now]);
            $colsList = implode(',', array_keys($insert));
            $phList   = implode(',', array_fill(0, count($insert), '?'));
            $db->prepare("INSERT INTO marketing_contacts ($colsList) VALUES ($phList)")->execute(array_values($insert));
            $created++;
        }
    }
    fclose($fh);
    return ['success' => true, 'created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors];
}

// ── Suppression helpers (full engine lands in Fire 8/9; groundwork now) ───
function isMarketingSuppressed(int $contactId, string $channel): bool {
    $st = getDB()->prepare("SELECT id FROM marketing_suppression WHERE contact_id=? AND channel=?");
    $st->execute([$contactId, $channel]);
    return (bool)$st->fetch();
}

function addMarketingSuppression(int $contactId, string $channel, string $reason = '', string $source = 'manual'): void {
    getDB()->prepare("INSERT INTO marketing_suppression (contact_id, channel, reason, source, created_at)
                       VALUES (?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE reason=VALUES(reason), source=VALUES(source)")
           ->execute([$contactId, $channel, $reason, $source, time()]);

    // Fire 13: proactively clear this contact's backlog on this channel.
    // Not strictly required — the queue worker's dispatch-time re-check
    // (also Fire 13) is the authoritative backstop — but this avoids
    // burning rate-limit slots and worker cycles on sends that would be
    // skipped anyway, and reflects "cancelled" in the UI immediately
    // instead of only at the next 15-minute worker cycle.
    cancelPendingMarketingSendsForContact($contactId, $channel);
}

/**
 * Fire 13 — cancels PENDING/rate_limited queue rows for one contact on one
 * channel. Called whenever a contact becomes ineligible to receive further
 * sends (suppression added, or opt-in manually flipped off).
 */
function cancelPendingMarketingSendsForContact(int $contactId, string $channel): int {
    $db = getDB();
    $st = $db->prepare("SELECT mq.id, mq.recipient_id FROM marketing_queue mq
                         JOIN marketing_campaign_recipients mcr ON mcr.id = mq.recipient_id
                         WHERE mcr.contact_id=? AND mq.channel=? AND mq.status IN ('pending','rate_limited')");
    $st->execute([$contactId, $channel]);
    $rows = $st->fetchAll();
    if (empty($rows)) return 0;

    $queueIds = array_column($rows, 'id');
    $recipientIds = array_column($rows, 'recipient_id');

    $qPh = implode(',', array_fill(0, count($queueIds), '?'));
    $db->prepare("UPDATE marketing_queue SET status='cancelled' WHERE id IN ($qPh)")->execute($queueIds);

    $rPh = implode(',', array_fill(0, count($recipientIds), '?'));
    $db->prepare("UPDATE marketing_campaign_recipients SET status='opted_out' WHERE id IN ($rPh)")->execute($recipientIds);

    return count($queueIds);
}

// ── RBAC: seed the two contact/group permission rows not already covered
// by ensureMarketingPermissions() in marketing.php (that already seeded
// marketing.contacts.view/manage and marketing.groups.manage — this
// function is a no-op placeholder kept for symmetry with the Fire
// convention; nothing further to seed here). ─────────────────────────────

/**
 * Fire 10 — manual admin-facing suppression toggle. Deliberately separate
 * from the whatsapp_opt_in/email_opt_in flags on marketing_contacts: those
 * represent the contact's own stated preference (editable via the contact
 * form), while marketing_suppression is an independent enforcement list
 * with its own reason/source trail (webhook opt-out, unsubscribe link, or
 * now a manual admin override for e.g. a compliance request). Both are
 * checked at enqueue time — either one blocks sending.
 */
function toggleMarketingSuppression(int $contactId, string $channel, bool $suppress): array {
    if (!in_array($channel, ['whatsapp', 'email'], true)) return ['success' => false, 'error' => 'Invalid channel.'];
    if ($suppress) {
        addMarketingSuppression($contactId, $channel, 'manual_admin', 'admin_action');
    } else {
        getDB()->prepare("DELETE FROM marketing_suppression WHERE contact_id=? AND channel=?")->execute([$contactId, $channel]);
    }
    return ['success' => true];
}


/**
 * Fire 11 — cheap lookup used by the client_selection_created /
 * catalog_generated trigger hooks (once wired up) to translate a
 * users.id/clients.id into the linked marketing_contacts.id.
 */
function getMarketingContactIdForSource(string $sourceType, int $sourceId): ?int {
    if (!in_array($sourceType, ['user', 'client'], true) || $sourceId <= 0) return null;
    $st = getDB()->prepare("SELECT id FROM marketing_contacts WHERE source_type=? AND source_id=? LIMIT 1");
    $st->execute([$sourceType, $sourceId]);
    $id = $st->fetchColumn();
    return $id !== false ? (int)$id : null;
}




