<?php
/**
 * includes/clients.php
 * Client & Selection management helpers
 */

// ── Validation ────────────────────────────────────────────────────────────────
function validateMobile(string $mobile): bool {
    $clean = preg_replace('/[\s\-\(\)\.]+/', '', $mobile);
    return (bool)preg_match('/^[6-9]\d{9}$/', $clean);
}

function sanitizeMobile(string $mobile): string {
    return preg_replace('/[\s\-\(\)\.]+/', '', $mobile);
}

// ── Client CRUD ────────────────────────────────────────────────────────────────

function getClients(int $userId, array $opts = []): array {
    $db     = getDB();
    $search = trim($opts['search'] ?? '');
    $limit  = (int)($opts['limit']  ?? 10);
    $offset = (int)($opts['offset'] ?? 0);

    $where  = "WHERE c.user_id = ?";
    $params = [$userId];

    if ($search !== '') {
        $where   .= " AND (c.client_name LIKE ? OR c.client_mobile LIKE ? OR c.mansoner_name LIKE ?)";
        $like     = "%{$search}%";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }

    $countSt = $db->prepare("SELECT COUNT(*) FROM clients c $where");
    $countSt->execute($params);
    $total = (int)$countSt->fetchColumn();

    $sql = "SELECT c.*,
                (SELECT COUNT(*) FROM client_selections cs WHERE cs.client_id = c.id) AS selection_count
            FROM clients c
            $where
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $st = $db->prepare($sql);
    $st->execute($params);

    return ['rows' => $st->fetchAll(), 'total' => $total];
}

function getClient(int $id, int $userId): ?array {
    $st = getDB()->prepare("SELECT * FROM clients WHERE id = ? AND user_id = ?");
    $st->execute([$id, $userId]);
    return $st->fetch() ?: null;
}

function getClientById(int $id): ?array {
    $st = getDB()->prepare("SELECT * FROM clients WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function createClient(int $userId, array $data): array {
    $name    = titleCase($data['client_name']   ?? '');
    $mobile  = sanitizeMobile($data['client_mobile']  ?? '');
    $mName   = titleCase($data['mansoner_name'] ?? '');
    $mMobile = sanitizeMobile($data['mansoner_mobile'] ?? '');
    $addr    = mb_substr(trim($data['site_address'] ?? ''), 0, 500);

    if (!$name)               return ['success' => false, 'error' => 'Client name is required.'];
    if (!$mobile)             return ['success' => false, 'error' => 'Client mobile is required.'];
    if (!validateMobile($mobile)) return ['success' => false, 'error' => 'Enter a valid 10-digit mobile number.'];
    if ($mMobile && !validateMobile($mMobile)) return ['success' => false, 'error' => 'Enter a valid mason mobile number.'];

    $db = getDB();
    $db->prepare("INSERT INTO clients (user_id, client_name, client_mobile, mansoner_name, mansoner_mobile, site_address, created_at, updated_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
       ->execute([$userId, $name, $mobile, $mName, $mMobile, $addr, time(), time()]);

    return ['success' => true, 'id' => (int)$db->lastInsertId()];
}

function updateClient(int $id, int $userId, array $data): array {
    $name    = titleCase($data['client_name']   ?? '');
    $mobile  = sanitizeMobile($data['client_mobile']  ?? '');
    $mName   = titleCase($data['mansoner_name'] ?? '');
    $mMobile = sanitizeMobile($data['mansoner_mobile'] ?? '');
    $addr    = mb_substr(trim($data['site_address'] ?? ''), 0, 500);

    if (!$name)               return ['success' => false, 'error' => 'Client name is required.'];
    if (!$mobile)             return ['success' => false, 'error' => 'Client mobile is required.'];
    if (!validateMobile($mobile)) return ['success' => false, 'error' => 'Enter a valid 10-digit mobile number.'];
    if ($mMobile && !validateMobile($mMobile)) return ['success' => false, 'error' => 'Enter a valid mason mobile number.'];

    getDB()->prepare("UPDATE clients SET client_name=?, client_mobile=?, mansoner_name=?, mansoner_mobile=?, site_address=?, updated_at=? WHERE id=? AND user_id=?")
           ->execute([$name, $mobile, $mName, $mMobile, $addr, time(), $id, $userId]);

    return ['success' => true];
}

function deleteClient(int $id, int $userId): bool {
    $st = getDB()->prepare("DELETE FROM clients WHERE id=? AND user_id=?");
    $st->execute([$id, $userId]);
    return $st->rowCount() > 0;
}

function clientCount(int $userId): int {
    $st = getDB()->prepare("SELECT COUNT(*) FROM clients WHERE user_id=?");
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}

// ── Selection CRUD ────────────────────────────────────────────────────────────

function getSelections(int $clientId, int $userId, array $opts = []): array {
    $db     = getDB();
    $search = trim($opts['search'] ?? '');
    $limit  = (int)($opts['limit']  ?? 10);
    $offset = (int)($opts['offset'] ?? 0);

    $where  = "WHERE cs.client_id = ? AND cs.user_id = ?";
    $params = [$clientId, $userId];

    if ($search !== '') {
        $where   .= " AND (p.name LIKE ? OR p.quarry_number LIKE ?)";
        $like     = "%{$search}%";
        $params[] = $like; $params[] = $like;
    }

    $countSt = $db->prepare("SELECT COUNT(*) FROM client_selections cs JOIN products p ON p.id = cs.product_id $where");
    $countSt->execute($params);
    $total = (int)$countSt->fetchColumn();

    $sql = "SELECT cs.*,
                p.name AS product_name,
                p.quarry_number,
                p.category,
                p.thickness,
                p.sizes_l,
                p.sizes_h,
                p.cutter_size_l,
                p.cutter_size_h,
                p.quantity_available,
                p.palette,
                (SELECT filename FROM product_photos WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS primary_photo
            FROM client_selections cs
            JOIN products p ON p.id = cs.product_id
            $where
            ORDER BY cs.created_at DESC
            LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $st = $db->prepare($sql);
    $st->execute($params);

    return ['rows' => $st->fetchAll(), 'total' => $total];
}

function getSelection(int $id, int $userId): ?array {
    $st = getDB()->prepare("SELECT cs.*, p.name AS product_name, p.quarry_number
                            FROM client_selections cs
                            JOIN products p ON p.id = cs.product_id
                            WHERE cs.id = ? AND cs.user_id = ?");
    $st->execute([$id, $userId]);
    return $st->fetch() ?: null;
}

function createSelection(int $clientId, int $userId, array $data): array {
    $pid    = (int)($data['product_id']       ?? 0);
    $area   = trim($data['selection_area']     ?? '');
    $qty    = (float)($data['quantity_required'] ?? 0);
    $notes  = trim($data['extra_notes']        ?? '');

    if (!$pid)      return ['success' => false, 'error' => 'Invalid product.'];
    if (!$clientId) return ['success' => false, 'error' => 'Invalid client.'];

    // Verify client belongs to user
    $chk = getDB()->prepare("SELECT id FROM clients WHERE id=? AND user_id=?");
    $chk->execute([$clientId, $userId]);
    if (!$chk->fetch()) return ['success' => false, 'error' => 'Client not found.'];

    // Check duplicate
    $dup = getDB()->prepare("SELECT id FROM client_selections WHERE client_id=? AND product_id=? AND user_id=?");
    $dup->execute([$clientId, $pid, $userId]);
    if ($dup->fetch()) return ['success' => false, 'error' => 'This product is already added to this client.'];

    $db = getDB();
    $db->prepare("INSERT INTO client_selections (client_id, user_id, product_id, selection_area, quantity_required, extra_notes, created_at, updated_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
       ->execute([$clientId, $userId, $pid, $area, $qty, $notes, time(), time()]);

    return ['success' => true, 'id' => (int)$db->lastInsertId()];
}

function updateSelection(int $id, int $userId, array $data): array {
    $area  = trim($data['selection_area']     ?? '');
    $qty   = (float)($data['quantity_required'] ?? 0);
    $notes = trim($data['extra_notes']        ?? '');

    getDB()->prepare("UPDATE client_selections SET selection_area=?, quantity_required=?, extra_notes=?, updated_at=? WHERE id=? AND user_id=?")
           ->execute([$area, $qty, $notes, time(), $id, $userId]);

    return ['success' => true];
}

function deleteSelection(int $id, int $userId): bool {
    $st = getDB()->prepare("DELETE FROM client_selections WHERE id=? AND user_id=?");
    $st->execute([$id, $userId]);
    return $st->rowCount() > 0;
}

// ── Admin helpers ─────────────────────────────────────────────────────────────

function adminGetClients(int $userId, array $opts = []): array {
    $db     = getDB();
    $search = trim($opts['search'] ?? '');
    $limit  = (int)($opts['limit']  ?? 10);
    $offset = (int)($opts['offset'] ?? 0);

    $where  = "WHERE c.user_id = ?";
    $params = [$userId];

    if ($search !== '') {
        $where   .= " AND (c.client_name LIKE ? OR c.client_mobile LIKE ? OR c.mansoner_name LIKE ?)";
        $like     = "%{$search}%";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }

    $countSt = $db->prepare("SELECT COUNT(*) FROM clients c $where");
    $countSt->execute($params);
    $total = (int)$countSt->fetchColumn();

    $sql = "SELECT c.*,
                (SELECT COUNT(*) FROM client_selections cs WHERE cs.client_id = c.id) AS selection_count
            FROM clients c
            $where
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $st = $db->prepare($sql);
    $st->execute($params);

    return ['rows' => $st->fetchAll(), 'total' => $total];
}

function adminGetSelections(int $clientId, array $opts = []): array {
    $db     = getDB();
    $search = trim($opts['search'] ?? '');
    $limit  = (int)($opts['limit']  ?? 10);
    $offset = (int)($opts['offset'] ?? 0);

    $where  = "WHERE cs.client_id = ?";
    $params = [$clientId];

    if ($search !== '') {
        $where   .= " AND (p.name LIKE ? OR p.quarry_number LIKE ?)";
        $like     = "%{$search}%";
        $params[] = $like; $params[] = $like;
    }

    $countSt = $db->prepare("SELECT COUNT(*) FROM client_selections cs JOIN products p ON p.id = cs.product_id $where");
    $countSt->execute($params);
    $total = (int)$countSt->fetchColumn();

    $sql = "SELECT cs.*,
                p.name AS product_name,
                p.quarry_number,
                p.category,
                p.thickness,
                p.sizes_l,
                p.sizes_h,
                p.cutter_size_l,
                p.cutter_size_h,
                p.quantity_available,
                p.palette,
                (SELECT filename FROM product_photos WHERE product_id = p.id ORDER BY sort_order LIMIT 1) AS primary_photo
            FROM client_selections cs
            JOIN products p ON p.id = cs.product_id
            $where
            ORDER BY cs.created_at DESC
            LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $st = $db->prepare($sql);
    $st->execute($params);

    return ['rows' => $st->fetchAll(), 'total' => $total];
}