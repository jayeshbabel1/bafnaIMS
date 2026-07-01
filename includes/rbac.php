<?php
/**
 * includes/rbac.php
 * ─────────────────────────────────────────────────────────────────────────
 * Role-Based Access Control helpers for the Admin Panel.
 *
 * USAGE
 *   require_once BASE_PATH . '/includes/rbac.php';
 *
 *   // Check a permission (returns bool)
 *   if (adminCan('products.edit')) { ... }
 *
 *   // Hard-gate: redirect if no permission
 *   requireAdminPermission('users.view');
 *
 *   // Check if current admin is super_admin
 *   if (isSuperAdmin()) { ... }
 *
 * The current admin's full permission set is cached in $_SESSION so that
 * every page load only runs one DB query max.
 * ─────────────────────────────────────────────────────────────────────────
 */

// ── Load & cache the current admin's permissions ─────────────────────────────
function _loadAdminPermissions(): void {
    if (isset($_SESSION['admin_permissions'])) return;

    $adminId = $_SESSION['admin_id'] ?? null;
    if (!$adminId) {
        $_SESSION['admin_permissions'] = [];
        $_SESSION['admin_role_slug']   = null;
        return;
    }

    try {
        $db = getDB();

        // Fetch role slug
        $st = $db->prepare("
            SELECT ar.slug
            FROM   admins a
            LEFT JOIN admin_roles ar ON ar.id = a.role_id
            WHERE  a.id = ?
            LIMIT  1
        ");
        $st->execute([$adminId]);
        $row = $st->fetch();
        $slug = $row['slug'] ?? null;
        $_SESSION['admin_role_slug'] = $slug;

        // Super admin → all permissions virtually
        if ($slug === 'super_admin') {
            $_SESSION['admin_permissions'] = ['*'];
            return;
        }

        // Fetch assigned permissions
        $st = $db->prepare("
            SELECT ap.action
            FROM   admins a
            JOIN   admin_roles ar ON ar.id = a.role_id
            JOIN   admin_role_permissions arp ON arp.role_id = ar.id
            JOIN   admin_permissions ap ON ap.id = arp.permission_id
            WHERE  a.id = ?
        ");
        $st->execute([$adminId]);
        $_SESSION['admin_permissions'] = $st->fetchAll(PDO::FETCH_COLUMN) ?: [];

    } catch (Throwable $e) {
        error_log('_loadAdminPermissions: ' . $e->getMessage());
        $_SESSION['admin_permissions'] = [];
        $_SESSION['admin_role_slug']   = null;
    }
}

// ── Flush cached permissions (call after role/permission changes) ─────────────
function flushAdminPermissionCache(): void {
    unset($_SESSION['admin_permissions'], $_SESSION['admin_role_slug']);
}

// ── Check a single permission ─────────────────────────────────────────────────
function adminCan(string $action): bool {
    _loadAdminPermissions();
    $perms = $_SESSION['admin_permissions'] ?? [];
    // Super admin wildcard
    if (in_array('*', $perms, true)) return true;
    return in_array($action, $perms, true);
}

// ── Check multiple permissions (any = OR, all = AND) ─────────────────────────
function adminCanAny(array $actions): bool {
    foreach ($actions as $a) { if (adminCan($a)) return true; }
    return false;
}

function adminCanAll(array $actions): bool {
    foreach ($actions as $a) { if (!adminCan($a)) return false; }
    return true;
}

// ── Convenience: is current admin a super_admin? ──────────────────────────────
function isSuperAdmin(): bool {
    _loadAdminPermissions();
    return ($_SESSION['admin_role_slug'] ?? null) === 'super_admin';
}

// ── Hard gate: die with 403 if permission missing ─────────────────────────────
function requireAdminPermission(string $action): void {
    if (!adminCan($action)) {
        // AJAX?
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
            strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json', true, 403);
            echo json_encode(['error' => 'Access denied.', 'required' => $action]);
            exit;
        }
        // Normal request
        http_response_code(403);
        include_once __DIR__ . '/../admin/views/_403.php';
        exit;
    }
}

// ── Fetch ALL permissions grouped by module (for the permission form) ─────────
function getAllPermissionsGrouped(): array {
    try {
        $rows = getDB()->query("
            SELECT id, module, action, label, sort_order
            FROM   admin_permissions
            ORDER  BY sort_order, module, label
        ")->fetchAll();
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['module']][] = $r;
        }
        return $grouped;
    } catch (Throwable $e) {
        return [];
    }
}

// ── Fetch all roles (with permission count + admin count) ─────────────────────
function getAllRoles(): array {
    try {
        return getDB()->query("
            SELECT r.*,
                (SELECT COUNT(*) FROM admin_role_permissions arp WHERE arp.role_id = r.id) AS perm_count,
                (SELECT COUNT(*) FROM admins a WHERE a.role_id = r.id) AS admin_count
            FROM admin_roles r
            ORDER BY r.id ASC
        ")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

// ── Fetch a single role with its permission IDs ───────────────────────────────
function getRoleWithPermissions(int $roleId): ?array {
    try {
        $db = getDB();
        $st = $db->prepare("SELECT * FROM admin_roles WHERE id = ?");
        $st->execute([$roleId]);
        $role = $st->fetch();
        if (!$role) return null;

        $ps = $db->prepare("SELECT permission_id FROM admin_role_permissions WHERE role_id = ?");
        $ps->execute([$roleId]);
        $role['permission_ids'] = $ps->fetchAll(PDO::FETCH_COLUMN);
        return $role;
    } catch (Throwable $e) {
        return null;
    }
}

// ── Save role permissions (replace-all strategy) ──────────────────────────────
function saveRolePermissions(int $roleId, array $permissionIds): void {
    $db = getDB();
    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM admin_role_permissions WHERE role_id = ?")
           ->execute([$roleId]);
        if (!empty($permissionIds)) {
            $st = $db->prepare("INSERT IGNORE INTO admin_role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($permissionIds as $pid) {
                $st->execute([$roleId, (int)$pid]);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

// ── Create a new role ─────────────────────────────────────────────────────────
function createRole(array $data): array {
    $name = trim($data['name'] ?? '');
    $desc = trim($data['description'] ?? '');
    if (!$name) return ['success' => false, 'error' => 'Role name is required.'];

    // Auto-generate slug
    $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($name));
    $slug = trim($slug, '_');

    // Ensure uniqueness
    $db  = getDB();
    $chk = $db->prepare("SELECT id FROM admin_roles WHERE slug = ? OR name = ?");
    $chk->execute([$slug, $name]);
    if ($chk->fetch()) return ['success' => false, 'error' => 'A role with that name already exists.'];

    $db->prepare("INSERT INTO admin_roles (name, slug, description, is_system, created_at) VALUES (?, ?, ?, 0, ?)")
       ->execute([$name, $slug, $desc, time()]);

    return ['success' => true, 'id' => (int)$db->lastInsertId()];
}

// ── Update a role ─────────────────────────────────────────────────────────────
function updateRole(int $id, array $data): array {
    $name = trim($data['name'] ?? '');
    $desc = trim($data['description'] ?? '');
    if (!$name) return ['success' => false, 'error' => 'Role name is required.'];

    $db = getDB();

    // Check is_system — can still rename description but not slug
    $st = $db->prepare("SELECT is_system, slug FROM admin_roles WHERE id = ?");
    $st->execute([$id]);
    $role = $st->fetch();
    if (!$role) return ['success' => false, 'error' => 'Role not found.'];

    // Check name uniqueness (excluding self)
    $chk = $db->prepare("SELECT id FROM admin_roles WHERE name = ? AND id <> ?");
    $chk->execute([$name, $id]);
    if ($chk->fetch()) return ['success' => false, 'error' => 'Another role already uses that name.'];

    if ($role['is_system']) {
        // System roles: only update description
        $db->prepare("UPDATE admin_roles SET description = ? WHERE id = ?")
           ->execute([$desc, $id]);
    } else {
        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($name));
        $slug = trim($slug, '_');
        $db->prepare("UPDATE admin_roles SET name = ?, slug = ?, description = ? WHERE id = ?")
           ->execute([$name, $slug, $desc, $id]);
    }

    return ['success' => true];
}

// ── Delete a role ─────────────────────────────────────────────────────────────
function deleteRole(int $id): array {
    $db = getDB();
    $st = $db->prepare("SELECT is_system FROM admin_roles WHERE id = ?");
    $st->execute([$id]);
    $role = $st->fetch();
    if (!$role) return ['success' => false, 'error' => 'Role not found.'];
    if ($role['is_system']) return ['success' => false, 'error' => 'System roles cannot be deleted.'];

    // Unassign admins from this role
    $db->prepare("UPDATE admins SET role_id = NULL WHERE role_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM admin_roles WHERE id = ?")->execute([$id]);
    return ['success' => true];
}

// ── Admin account CRUD ────────────────────────────────────────────────────────

function getAllAdminAccounts(): array {
    try {
        return getDB()->query("
            SELECT a.id, a.username, a.name, a.email, a.is_active, a.created_at,
                   ar.id AS role_id, ar.name AS role_name, ar.slug AS role_slug
            FROM admins a
            LEFT JOIN admin_roles ar ON ar.id = a.role_id
            ORDER BY a.id ASC
        ")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function createAdminAccount(array $data): array {
    $username = strtolower(trim($data['username'] ?? ''));
    $name     = trim($data['name']     ?? '');
    $email    = strtolower(trim($data['email'] ?? ''));
    $password = $data['password'] ?? '';
    $roleId   = (int)($data['role_id'] ?? 0);

    if (!$username) return ['success' => false, 'error' => 'Username is required.'];
    if (!$name)     return ['success' => false, 'error' => 'Name is required.'];
    if (strlen($password) < 8) return ['success' => false, 'error' => 'Password must be at least 8 characters.'];

    $db  = getDB();
    $chk = $db->prepare("SELECT id FROM admins WHERE username = ?");
    $chk->execute([$username]);
    if ($chk->fetch()) return ['success' => false, 'error' => 'Username already taken.'];

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->prepare("INSERT INTO admins (username, password, name, email, role_id, is_active, created_at) VALUES (?,?,?,?,?,1,?)")
       ->execute([$username, $hash, $name, $email, $roleId ?: null, time()]);

    return ['success' => true, 'id' => (int)$db->lastInsertId()];
}

function updateAdminAccount(int $id, array $data): array {
    $name   = trim($data['name']  ?? '');
    $email  = strtolower(trim($data['email'] ?? ''));
    $roleId = (int)($data['role_id'] ?? 0);
    $active = isset($data['is_active']) ? 1 : 0;

    if (!$name) return ['success' => false, 'error' => 'Name is required.'];

    $db = getDB();

    // Prevent deactivating or changing role of the only super_admin
    $st = $db->prepare("SELECT a.id FROM admins a JOIN admin_roles r ON r.id=a.role_id WHERE r.slug='super_admin'");
    $st->execute();
    $superAdmins = $st->fetchAll(PDO::FETCH_COLUMN);
    if (count($superAdmins) === 1 && in_array($id, array_map('intval', $superAdmins), true)) {
        // Allow name/email change but not deactivation / role change to non-super_admin
        $chkRole = $db->prepare("SELECT slug FROM admin_roles WHERE id = ?");
        $chkRole->execute([$roleId]);
        $newSlug = $chkRole->fetchColumn();
        if ($newSlug !== 'super_admin' || !$active) {
            return ['success' => false, 'error' => 'Cannot change role or deactivate the only Super Admin account.'];
        }
    }

    $db->prepare("UPDATE admins SET name=?, email=?, role_id=?, is_active=? WHERE id=?")
       ->execute([$name, $email, $roleId ?: null, $active, $id]);

    // Update password if provided
    if (!empty($data['password'])) {
        if (strlen($data['password']) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
        }
        $db->prepare("UPDATE admins SET password=? WHERE id=?")
           ->execute([password_hash($data['password'], PASSWORD_DEFAULT), $id]);
    }

    return ['success' => true];
}

function deleteAdminAccount(int $id): array {
    $db = getDB();

    // Prevent deleting the only super admin
    $st = $db->prepare("
        SELECT a.id FROM admins a
        JOIN admin_roles r ON r.id = a.role_id
        WHERE r.slug = 'super_admin'
    ");
    $st->execute();
    $superAdmins = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

    if (count($superAdmins) === 1 && in_array($id, $superAdmins, true)) {
        return ['success' => false, 'error' => 'Cannot delete the only Super Admin account.'];
    }

    // Prevent self-deletion
    if ((int)($_SESSION['admin_id'] ?? 0) === $id) {
        return ['success' => false, 'error' => 'You cannot delete your own account.'];
    }

    $db->prepare("DELETE FROM admins WHERE id = ?")->execute([$id]);
    return ['success' => true];
}