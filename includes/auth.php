<?php
require_once __DIR__ . '/db.php';
 
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
             'lifetime' => 0,
             'path'     => '/',
             'domain'   => '',
             'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
             'httponly' => true,
             'samesite' => 'Lax',
         ]);
         ini_set('session.use_strict_mode', '1');
        session_start();
    }
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
        $st = getDB()->prepare("SELECT * FROM users WHERE id=?");
        $st->execute([$id]);
        $cache[$id] = $st->fetch() ?: null;
    }
    return $cache[$id];
}
 
function loginUser(string $email, string $password): array {
  if (isLoginLocked($email)) {
         return ['success' => false, 'error' => 'Too many failed attempts. Please try again in 15 minutes.'];
     }
    $db = getDB();
    $st = $db->prepare("SELECT * FROM users WHERE email=?");
    $st->execute([strtolower(trim($email))]);
    $user = $st->fetch();
 
    if (!$user || !password_verify($password, $user['password'])) {
       registerLoginFailure($email);
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }
    if (empty($user['is_verified']) || (int)$user['is_verified'] !== 1) {
        return ['success' => false, 'error' => 'not_verified', 'user_id' => $user['id']];
    }
     clearLoginFailures($email);
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    return ['success' => true];
}
 
function generateRandomPassword(int $length = 10): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789@$#*';
    $max   = strlen($chars) - 1;
    $pass  = '';
    for ($i = 0; $i < $length; $i++) $pass .= $chars[random_int(0, $max)];
    return $pass;
}
 
function createUserByAdmin(array $data): array {
    $db    = getDB();
    $name  = titleCase(trim($data['name']  ?? ''));
    $email = strtolower(trim($data['email'] ?? ''));
    $firm  = titleCase(trim($data['firm'] ?? ''));
    $city  = titleCase(trim($data['city'] ?? ''));
    if (!$name)  return ['success' => false, 'error' => 'Name is required.'];
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'error' => 'A valid email address is required.'];
    $check = $db->prepare("SELECT id FROM users WHERE email=?");
    $check->execute([$email]);
    if ($check->fetch()) return ['success' => false, 'error' => 'This email is already registered.'];
    $plainPassword = trim($data['password'] ?? '');
    if ($plainPassword !== '' && strlen($plainPassword) < 8) return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
    if ($plainPassword === '') $plainPassword = generateRandomPassword(10);
    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $st = $db->prepare("INSERT INTO users (name,email,password,phone,firm,city,role,experience,is_verified,created_at) VALUES (?,?,?,?,?,?,?,?,1,?)");
    $st->execute([$name,$email,$hash,trim($data['phone']??''),$firm,$city,$data['role']??'',$data['experience']??'',time()]);
    $userId = (int)$db->lastInsertId();
    return ['success'=>true,'id'=>$userId,'plain_password'=>$plainPassword,'user'=>['id'=>$userId,'name'=>$name,'email'=>$email]];
}
 
function registerUser(array $data): array {
    $db    = getDB();
    $email = strtolower(trim($data['email']));
    $check = $db->prepare("SELECT id FROM users WHERE email=?");
    $check->execute([$email]);
    if ($check->fetch()) return ['success' => false, 'error' => 'This email is already registered.'];
    $hash = password_hash($data['password'], PASSWORD_DEFAULT);
    $st   = $db->prepare("INSERT INTO users (name,email,password,phone,firm,city,role,experience) VALUES (?,?,?,?,?,?,?,?)");
    $st->execute([titleCase($data['name']),$email,$hash,trim($data['phone']??''),titleCase(trim($data['firm']??'')),titleCase(trim($data['city']??'')),$data['role']??'',$data['experience']??'']);
    $_SESSION['user_id'] = $db->lastInsertId();
    return ['success' => true];
}
 
function logoutUser(): void {
    session_unset();
    session_destroy();
}
 
function requestPasswordReset(string $email): array {
    $db = getDB();
  // Rate-limit reset requests per email to prevent mail-bombing a victim
     if (isLoginLocked('reset:' . $email)) {
         return ['success' => true]; // pretend success, don't reveal lock state
     }
    $st = $db->prepare("SELECT id,name,email FROM users WHERE email=?");
    $st->execute([strtolower(trim($email))]);
    $user = $st->fetch();
    if (!$user) {
         registerLoginFailure('reset:' . $email); // count attempts even for unknown emails
         return ['success' => true];
     }
    $token   = bin2hex(random_bytes(32));
    $expires = time() + 3600;
    $db->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE id=?")->execute([$token, $expires, $user['id']]);
    sendResetEmail($user['email'], $user['name'], $token);
    registerLoginFailure('reset:' . $email);
    return ['success' => true];
}
 
function resetPassword(string $token, string $password): array {
    $db = getDB();
    $st = $db->prepare("SELECT id FROM users WHERE reset_token=? AND reset_expires > ?");
    $st->execute([$token, time()]);
    $user = $st->fetch();
    if (!$user) return ['success' => false, 'error' => 'Invalid or expired reset link.'];
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->prepare("UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?")->execute([$hash, $user['id']]);
    return ['success' => true];
}
 
function sendResetEmail(string $to, string $name, string $token): void {
    if (function_exists('sendPasswordResetEmail')) { sendPasswordResetEmail($to, $name, $token); return; }
    $link    = BASE_URL . '/index.php?page=reset_password&token=' . $token;
    $subject = 'Reset Your Password — ' . APP_NAME;
    $body    = "Hi $name,\n\nClick the link below to reset your password:\n$link\n\nThis link expires in 1 hour.\n\n— " . APP_NAME;
    $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\nContent-Type: text/plain; charset=UTF-8";
    @mail($to, $subject, $body, $headers);
}
 

//  Login throttling 
function loginThrottleKey(string $ident): string {
    return strtolower(trim($ident)) . '|' . ($_SERVER['REMOTE_ADDR'] ?? '');
}
function isLoginLocked(string $ident): bool {
    $st = getDB()->prepare("SELECT locked_until FROM login_attempts WHERE ident=?");
    $st->execute([loginThrottleKey($ident)]);
    $row = $st->fetch();
    return $row && (int)$row['locked_until'] > time();
}
function registerLoginFailure(string $ident): void {
    $key = loginThrottleKey($ident);
    $db  = getDB();
    $db->prepare("
        INSERT INTO login_attempts (ident, attempts, locked_until, updated_at)
        VALUES (?, 1, 0, ?)
        ON DUPLICATE KEY UPDATE
            attempts     = attempts + 1,
            locked_until = IF(attempts + 1 >= 5, ? , locked_until),
            updated_at   = ?
    ")->execute([$key, time(), time() + 900, time()]); // 15 min lock after 5 fails
}
function clearLoginFailures(string $ident): void {
    getDB()->prepare("DELETE FROM login_attempts WHERE ident=?")->execute([loginThrottleKey($ident)]);
}

function loginAdmin(string $username, string $password): bool {
    if (isLoginLocked($username)) {
        return false;
    }
    $db = getDB();
    $st = $db->prepare("SELECT * FROM admins WHERE username = ?");
    $st->execute([strtolower(trim($username))]);
    $admin = $st->fetch();

    if (!$admin || !password_verify($password, $admin['password'])) {
        registerLoginFailure($username); 
        return false;
    }

    if (array_key_exists('is_active', $admin) && !(int)$admin['is_active']) {
        return false;
    }

    clearLoginFailures($username);
    session_regenerate_id(true);
    $_SESSION['admin_id']   = $admin['id'];
    $_SESSION['admin_name'] = $admin['name'];
    
    unset($_SESSION['admin_permissions'], $_SESSION['admin_role_slug']);

    return true;
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