<?php
/**
 * includes/notifications.php
 * ──────────────────────────────────────────────────────────────
 * Notification helpers — include once after db.php + helpers.php
 * ──────────────────────────────────────────────────────────────
 */

// ── Create a notification ────────────────────────────────────────────────────
function createNotification(string $title, string $message, string $type = 'info'): void {
    try {
        getDB()->prepare("
            INSERT INTO notifications (title, message, type, created_at)
            VALUES (?, ?, ?, ?)
        ")->execute([$title, $message, $type, time()]);
    } catch (Throwable $e) {
        // Silently fail — notifications are non-critical
        error_log('createNotification error: ' . $e->getMessage());
    }
}

// ── Get notifications (admin) ────────────────────────────────────────────────
function getNotifications(int $limit = 30): array {
    try {
        $cutoff = time() - (20 * 86400);
        return getDB()->prepare("
            SELECT * FROM notifications
            WHERE  created_at >= ?
            ORDER  BY created_at DESC
            LIMIT  ?
        ")->execute([$cutoff, $limit])
            ? (function() use ($limit, $cutoff) {
                $st = getDB()->prepare("
                    SELECT * FROM notifications
                    WHERE  created_at >= ?
                    ORDER  BY created_at DESC
                    LIMIT  ?
                ");
                $st->execute([$cutoff, $limit]);
                return $st->fetchAll();
            })()
            : [];
    } catch (Throwable $e) {
        return [];
    }
}

// ── Cleaner version using static cache ───────────────────────────────────────
function fetchNotifications(int $limit = 50): array {
    static $cache = null;
    if ($cache !== null) return array_slice($cache, 0, $limit);
    try {
        $cutoff = time() - (20 * 86400);
        $st = getDB()->prepare("
            SELECT * FROM notifications
            WHERE  created_at >= ?
            ORDER  BY created_at DESC
            LIMIT  100
        ");
        $st->execute([$cutoff]);
        $cache = $st->fetchAll() ?: [];
    } catch (Throwable $e) {
        $cache = [];
    }
    return array_slice($cache, 0, $limit);
}

// ── Count unread notifications ───────────────────────────────────────────────
function countUnreadNotifications(): int {
    try {
        $cutoff = time() - (20 * 86400);
        $st = getDB()->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE  is_read = 0
              AND  created_at >= ?
        ");
        $st->execute([$cutoff]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

// ── Mark all notifications as read ──────────────────────────────────────────
function markAllNotificationsRead(): void {
    try {
        getDB()->exec("UPDATE notifications SET is_read = 1");
    } catch (Throwable $e) {
        error_log('markAllNotificationsRead: ' . $e->getMessage());
    }
}

// ── Mark single notification read ───────────────────────────────────────────
function markNotificationRead(int $id): void {
    try {
        getDB()->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")
               ->execute([$id]);
    } catch (Throwable $e) {}
}

// ── Auto-archive stale inquiries (cron-safe, idempotent) ─────────────────────
function autoArchiveInquiries(): int {
    try {
        $cutoff = time() - (25 * 86400);
        $st = getDB()->prepare("
            UPDATE inquiries
            SET    status = 'closed'
            WHERE  status IN ('pending','replied')
              AND  created_at < ?
        ");
        $st->execute([$cutoff]);
        return $st->rowCount();
    } catch (Throwable $e) {
        error_log('autoArchiveInquiries: ' . $e->getMessage());
        return 0;
    }
}

// ── Purge old notifications (call from cron or admin action) ─────────────────
function purgeOldNotifications(): int {
    try {
        $cutoff = time() - (20 * 86400);
        $st = getDB()->prepare("DELETE FROM notifications WHERE created_at < ?");
        $st->execute([$cutoff]);
        return $st->rowCount();
    } catch (Throwable $e) {
        return 0;
    }
}