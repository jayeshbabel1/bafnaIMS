<?php
require_once __DIR__ . '/db.php';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_strict_mode', '1');
        session_start();
    }
    // Timeout check
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TTL) {
        session_unset(); session_destroy();
        session_start();
        $_SESSION['error'] = 'Session expired. Please log in again.';
    }
    $_SESSION['last_activity'] = time();
}

function isLoggedIn(): bool    { return !empty($_SESSION['user_id']); }
function isAdmin(): bool       { return !empty($_SESSION['admin_id']); }
function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    static $cache = [];
    $id = $_SESSION['user_id'];
    if (!isset($cache[$id])) {
        $cache[$id] = getDB()->prepare("SELECT * FROM users WHERE id=?")->execute([$id]) ? getDB()->prepare("SELECT * FROM users WHERE id=?")->execute([$id]) : null;
        $st = getDB()->prepare("SELECT * FROM users WHERE id=?");
        $st->execute([$id]);
        $cache[$id] = $st->fetch() ?: null;
    }
    return $cache[$id];
}

function loginUser(string $email, string $password): array {
    $db = getDB();
    $st = $db->prepare("SELECT * FROM users WHERE email=?");
    $st->execute([strtolower(trim($email))]);
    $user = $st->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    // ── Verification gate ───────────────────────────────────────────────────
    if (empty($user['is_verified']) || (int)$user['is_verified'] !== 1) {
        return ['success' => false, 'error' => 'not_verified', 'user_id' => $user['id']];
    }

    $_SESSION['user_id'] = $user['id'];
    return ['success' => true];
}

/**
 * Generate a readable random password for admin-created accounts.
 * Avoids ambiguous characters (0/O, 1/l/I) for easier manual sharing.
 */
function generateRandomPassword(int $length = 10): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $max   = strlen($chars) - 1;
    $pass  = '';
    for ($i = 0; $i < $length; $i++) {
        $pass .= $chars[random_int(0, $max)];
    }
    return $pass;
}

/**
 * Create a user from the Admin Panel.
 *
 * Unlike self-registration (registerUser), admin-created accounts are
 * auto-verified (is_verified=1) since an admin is vouching for them, and a
 * password is generated automatically if one isn't supplied.
 *
 * Returns:
 *   ['success'=>true,  'id'=>int, 'plain_password'=>string, 'user'=>array]
 *   ['success'=>false, 'error'=>string]
 *
 * The plain_password is returned ONLY so the caller can email it once.
 * It is never stored anywhere except as a bcrypt hash in the DB, and it
 * must never be logged, flashed, or displayed in the admin UI.
 */
function createUserByAdmin(array $data): array {
    $db    = getDB();
    $name  = titleCase(trim($data['name']  ?? ''));
    $email = strtolower(trim($data['email'] ?? ''));

    if (!$name)  return ['success' => false, 'error' => 'Name is required.'];
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'A valid email address is required.'];
    }

    $check = $db->prepare("SELECT id FROM users WHERE email=?");
    $check->execute([$email]);
    if ($check->fetch()) {
        return ['success' => false, 'error' => 'This email is already registered.'];
    }

    $plainPassword = trim($data['password'] ?? '');
    if ($plainPassword !== '' && strlen($plainPassword) < 8) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
    }
    if ($plainPassword === '') {
        $plainPassword = generateRandomPassword(10);
    }

    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

    $st = $db->prepare("INSERT INTO users
        (name, email, password, phone, firm, city, role, experience, is_verified, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
    $st->execute([
        $name,
        $email,
        $hash,
        trim($data['phone'] ?? ''),
        trim($data['firm']  ?? ''),
        trim($data['city']  ?? ''),
        $data['role']       ?? '',
        $data['experience'] ?? '',
        time(),
    ]);

    $userId = (int)$db->lastInsertId();

    return [
        'success'        => true,
        'id'             => $userId,
        'plain_password' => $plainPassword,
        'user'           => ['id' => $userId, 'name' => $name, 'email' => $email],
    ];
}

function registerUser(array $data): array {
    $db    = getDB();
    $email = strtolower(trim($data['email']));

    $check = $db->prepare("SELECT id FROM users WHERE email=?");
    $check->execute([$email]);
    if ($check->fetch()) return ['success' => false, 'error' => 'This email is already registered.'];

    $hash = password_hash($data['password'], PASSWORD_DEFAULT);
    $st   = $db->prepare("INSERT INTO users (name,email,password,phone,firm,city,role,experience) VALUES (?,?,?,?,?,?,?,?)");
    $st->execute([
        titleCase($data['name']),
        $email,
        $hash,
        trim($data['phone']  ?? ''),
        trim($data['firm']   ?? ''),
        trim($data['city']   ?? ''),
        $data['role']        ?? '',
        $data['experience']  ?? '',
    ]);
    $_SESSION['user_id'] = $db->lastInsertId();
    return ['success' => true];
}

function logoutUser(): void {
    session_unset();
    session_destroy();
}

// ── Password Reset ─────────────────────────────────────────────────────────────

function requestPasswordReset(string $email): array {
    $db = getDB();
    $st = $db->prepare("SELECT id,name,email FROM users WHERE email=?");
    $st->execute([strtolower(trim($email))]);
    $user = $st->fetch();

    // Always return success to prevent email enumeration
    if (!$user) return ['success' => true];

    $token   = bin2hex(random_bytes(32));
    $expires = time() + 3600; // 1 hour

    $db->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE id=?")
       ->execute([$token, $expires, $user['id']]);

    sendResetEmail($user['email'], $user['name'], $token);
    return ['success' => true];
}

function resetPassword(string $token, string $password): array {
    $db = getDB();
    $st = $db->prepare("SELECT id FROM users WHERE reset_token=? AND reset_expires > ?");
    $st->execute([$token, time()]);
    $user = $st->fetch();

    if (!$user) return ['success' => false, 'error' => 'Invalid or expired reset link. Please request a new one.'];

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->prepare("UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?")
       ->execute([$hash, $user['id']]);

    return ['success' => true];
}

function sendResetEmail(string $to, string $name, string $token): void {
    // Use central mailer if loaded; fall back to native mail
    if (function_exists('sendPasswordResetEmail')) {
        sendPasswordResetEmail($to, $name, $token);
        return;
    }
    // Native fallback (same as before)
    $link    = BASE_URL . '/index.php?page=reset_password&token=' . $token;
    $subject = 'Reset Your Password — ' . APP_NAME;
    $body    = "Hi $name,\n\nClick the link below to reset your password:\n$link\n\nThis link expires in 1 hour.\n\nIf you didn't request this, please ignore this email.\n\n— " . APP_NAME;
    $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\nContent-Type: text/plain; charset=UTF-8";
    @mail($to, $subject, $body, $headers);
}
// ── Admin Auth ─────────────────────────────────────────────────────────────────

function loginAdmin(string $username, string $password): bool {
    $db = getDB();
    $st = $db->prepare("SELECT * FROM admins WHERE username=?");
    $st->execute([$username]);
    $admin = $st->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        return true;
    }
    return false;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: index.php?page=login'); exit;
    }
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: admin/index.php'); exit;
    }
}