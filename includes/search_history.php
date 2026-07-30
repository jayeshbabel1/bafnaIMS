<?php
/**
 * includes/search_history.php
 * Per-user catalog search history + suggestions.
 */

define('SEARCH_HISTORY_MAX', 10);

// ── Schema bootstrap (idempotent, mirrors ensureLicenseTables()) ───────────
function ensureSearchHistoryTable(): void {
    static $done = false;
    if ($done) return;
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS user_search_history (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id     INT UNSIGNED NOT NULL,
        query       VARCHAR(150) NOT NULL,
        created_at  INT UNSIGNED NOT NULL,
        KEY idx_user (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

// ── Save a search query (skip if same as most recent, cap at N per user) ───
function saveSearchQuery(int $userId, string $query): void {
    ensureSearchHistoryTable();
    $query = trim($query);
    if ($query === '' || mb_strlen($query) < 2) return;
    $query = mb_substr($query, 0, 150);

    $db = getDB();

    // Skip if identical to the most recent entry for this user
    $last = $db->prepare("SELECT query FROM user_search_history WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
    $last->execute([$userId]);
    if (strcasecmp((string)$last->fetchColumn(), $query) === 0) return;

    // Remove any older duplicate of the same query so it moves to the top
    $db->prepare("DELETE FROM user_search_history WHERE user_id=? AND query=?")->execute([$userId, $query]);

    $db->prepare("INSERT INTO user_search_history (user_id, query, created_at) VALUES (?,?,?)")
       ->execute([$userId, $query, time()]);

    // Cap history at SEARCH_HISTORY_MAX rows per user
    $db->prepare("
        DELETE FROM user_search_history
        WHERE user_id = ?
        AND id NOT IN (
            SELECT id FROM (
                SELECT id FROM user_search_history WHERE user_id = ?
                ORDER BY created_at DESC LIMIT " . SEARCH_HISTORY_MAX . "
            ) AS keep_ids
        )
    ")->execute([$userId, $userId]);
}

// ── Fetch recent searches (most recent first) ───────────────────────────────
function getRecentSearches(int $userId, int $limit = 8): array {
    ensureSearchHistoryTable();
    $st = getDB()->prepare("SELECT id, query FROM user_search_history WHERE user_id=? ORDER BY created_at DESC LIMIT ?");
    $st->bindValue(1, $userId, PDO::PARAM_INT);
    $st->bindValue(2, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

// ── Recent searches filtered by a partial query (for live suggest) ─────────
function getRecentSearchesFiltered(int $userId, string $q, int $limit = 8): array {
    ensureSearchHistoryTable();
    $st = getDB()->prepare("SELECT id, query FROM user_search_history WHERE user_id=? AND query LIKE ? ORDER BY created_at DESC LIMIT ?");
    $st->bindValue(1, $userId, PDO::PARAM_INT);
    $st->bindValue(2, '%' . $q . '%', PDO::PARAM_STR);
    $st->bindValue(3, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

// ── Product suggestions (name / quarry number match) ────────────────────────
function getSearchProductSuggestions(string $q, int $limit = 8): array {
    if (trim($q) === '') return [];
    $db = getDB();
    $st = $db->prepare("
        SELECT p.id, p.name, p.quarry_number,
            (SELECT filename FROM product_photos WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS primary_photo
        FROM products p
        WHERE p.name LIKE ? OR p.quarry_number LIKE ?
        ORDER BY p.featured DESC, p.name ASC
        LIMIT ?
    ");
    $like = '%' . $q . '%';
    $st->bindValue(1, $like, PDO::PARAM_STR);
    $st->bindValue(2, $like, PDO::PARAM_STR);
    $st->bindValue(3, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

// ── Delete a single history item (ownership enforced via user_id in WHERE) ──
function deleteSearchHistoryItem(int $userId, int $id): void {
    ensureSearchHistoryTable();
    getDB()->prepare("DELETE FROM user_search_history WHERE id=? AND user_id=?")->execute([$id, $userId]);
}

// ── Clear all history for a user ────────────────────────────────────────────
function clearSearchHistory(int $userId): void {
    ensureSearchHistoryTable();
    getDB()->prepare("DELETE FROM user_search_history WHERE user_id=?")->execute([$userId]);
}