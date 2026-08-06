<?php
/**
 * includes/selection_history.php
 * ─────────────────────────────────────────────────────────────────────────
 * Client Selection History — shared audit-trail service used by BOTH the
 * User panel (includes/clients.php: createSelection/updateSelection/
 * deleteSelection) and the Admin panel (adminCreateSelectionForClient/
 * adminUpdateSelection/adminDeleteSelection). One table, one set of
 * helpers — no duplicated logging logic between panels.
 * ─────────────────────────────────────────────────────────────────────────
 */

// ── Schema bootstrap (idempotent, mirrors ensureLicenseTables() pattern) ───
function ensureSelectionHistoryTable(): void {
    static $done = false;
    if ($done) return;
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS client_selection_history (
        id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_selection_id  INT UNSIGNED NULL,
        client_id            INT UNSIGNED NOT NULL,
        product_id           INT UNSIGNED NOT NULL,
        product_name         VARCHAR(255) NOT NULL,
        quarry_number        VARCHAR(100) NULL,
        action                VARCHAR(20)  NOT NULL,
        changes_json          TEXT NULL,
        actor_type             VARCHAR(10)  NOT NULL,
        actor_id                INT UNSIGNED NULL,
        actor_name               VARCHAR(200) NOT NULL,
        message                   TEXT NOT NULL,
        created_at                INT UNSIGNED NOT NULL,
        KEY idx_client   (client_id),
        KEY idx_selection(client_selection_id),
        KEY idx_created  (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

// ── Human-friendly labels for the diff line (Qty: 10→15, ...) ──────────────
function _selHistFieldLabel(string $key): string {
    return match ($key) {
        'quantity_required' => 'Qty',
        'selection_area'    => 'Area',
        'extra_notes'       => 'Notes',
        default             => ucfirst(str_replace('_', ' ', $key)),
    };
}

/**
 * Record one history entry. Builds the readable message string per the
 * required format:
 *   "Product Name (Quarry No) has been {action}[, Changes: ...]. By {name}. {date}"
 *
 * @param int|null $selectionId  client_selections.id (nullable — kept even
 *                                after the row itself is deleted, for trace)
 * @param int      $clientId
 * @param array    $product      ['id'=>, 'name'=>, 'quarry_number'=>]
 * @param string   $action       'added'|'edited'|'deleted'
 * @param array    $actor        ['type'=>'user'|'admin','id'=>?int,'name'=>string]
 * @param array    $changes      [ field => [old, new], ... ] — only for 'edited'
 */
function logSelectionHistory(
    ?int $selectionId,
    int $clientId,
    array $product,
    string $action,
    array $actor,
    array $changes = []
): void {
 //   ensureSelectionHistoryTable();

    $productName = (string)($product['name'] ?? '');
    $quarry      = (string)($product['quarry_number'] ?? '');
    $productId   = (int)($product['id'] ?? 0);
    $actorName   = trim((string)($actor['name'] ?? '')) ?: 'Unknown';
    $now         = time();
    $dateStr     = date('d-M-Y h:i A', $now);
    $label       = $quarry !== '' ? "{$productName} ({$quarry})" : $productName;

    $changesText = '';
    if ($action === 'edited' && !empty($changes)) {
        $parts = [];
        foreach ($changes as $field => $pair) {
            $parts[] = _selHistFieldLabel($field) . ': ' . $pair[0] . '→' . $pair[1];
        }
        $changesText = implode(', ', $parts);
    }

    $message = match ($action) {
        'added'   => "{$label} has been added by {$actorName}. {$dateStr}",
        'deleted' => "{$label} has been deleted by {$actorName}. {$dateStr}",
        'edited'  => $changesText !== ''
            ? "{$label} has been edited. Changes: {$changesText}. By {$actorName}. {$dateStr}"
            : "{$label} has been edited by {$actorName}. {$dateStr}",
        default   => "{$label} — {$action} by {$actorName}. {$dateStr}",
    };

    getDB()->prepare("INSERT INTO client_selection_history
        (client_selection_id, client_id, product_id, product_name, quarry_number, action, changes_json, actor_type, actor_id, actor_name, message, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            $selectionId, $clientId, $productId, $productName, $quarry !== '' ? $quarry : null,
            $action, !empty($changes) ? json_encode($changes) : null,
            $actor['type'] ?? 'user', $actor['id'] ?? null, $actorName,
            $message, $now,
        ]);
}

/**
 * Compare old vs new selection field values, returning only what changed.
 * [ field => [oldDisplay, newDisplay], ... ]
 */
function diffSelectionFields(array $old, array $new): array {
    $fields  = ['selection_area', 'quantity_required', 'extra_notes'];
    $changes = [];
    foreach ($fields as $f) {
        if ($f === 'quantity_required') {
            $oldVal = (string)(float)($old[$f] ?? 0);
            $newVal = (string)(float)($new[$f] ?? 0);
        } else {
            $oldVal = trim((string)($old[$f] ?? ''));
            $newVal = trim((string)($new[$f] ?? ''));
        }
        if ($oldVal !== $newVal) {
            $changes[$f] = [$oldVal === '' ? '—' : $oldVal, $newVal === '' ? '—' : $newVal];
        }
    }
    return $changes;
}

// ── Fetch paginated history for one client (used by BOTH panels) ──────────
function getSelectionHistory(int $clientId, array $opts = []): array {
    ensureSelectionHistoryTable();
    $db     = getDB();
    $limit  = (int)($opts['limit']  ?? 15);
    $offset = (int)($opts['offset'] ?? 0);

    $countSt = $db->prepare("SELECT COUNT(*) FROM client_selection_history WHERE client_id=?");
    $countSt->execute([$clientId]);
    $total = (int)$countSt->fetchColumn();

    $st = $db->prepare("SELECT * FROM client_selection_history WHERE client_id=? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $st->execute([$clientId, $limit, $offset]);

    return ['rows' => $st->fetchAll(), 'total' => $total];
}

// ── Cascade cleanup — call when a client (and its selections) is permanently
// deleted, so orphaned history for a non-existent client doesn't linger. ───
function deleteSelectionHistoryForClient(int $clientId): void {
   // ensureSelectionHistoryTable();
    getDB()->prepare("DELETE FROM client_selection_history WHERE client_id=?")->execute([$clientId]);
}