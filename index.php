<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications.php';

startSecureSession();

$page = preg_replace('/[^a-z_]/', '', $_GET['page'] ?? 'catalog');

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Auth actions (no login required) ─────────────────────────────────────
    if ($action === 'login') {
        $result = loginUser($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            $redir = $_SESSION['redirect_after_login'] ?? 'index.php?page=catalog';
            unset($_SESSION['redirect_after_login']);
            redirect($redir);
        }
        $inlineError = $result['error'];
        include BASE_PATH . '/pages/login.php'; exit;
    }

    if ($action === 'register_step1') {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password']         ?? '';
        $conf  = $_POST['password_confirm'] ?? '';
        $phone = trim($_POST['phone'] ?? '');

        if (!$name || !$email || !$pass) { $inlineError = 'Please fill in all required fields.'; include BASE_PATH.'/pages/register.php'; exit; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $inlineError = 'Please enter a valid email address.'; include BASE_PATH.'/pages/register.php'; exit; }
        if (strlen($pass) < 8) { $inlineError = 'Password must be at least 8 characters.'; include BASE_PATH.'/pages/register.php'; exit; }
        if ($pass !== $conf)   { $inlineError = 'Passwords do not match.'; include BASE_PATH.'/pages/register.php'; exit; }

        $st = getDB()->prepare("SELECT id FROM users WHERE email=?");
        $st->execute([strtolower($email)]);
        if ($st->fetch()) { $inlineError = 'This email is already registered.'; include BASE_PATH.'/pages/register.php'; exit; }

        $_SESSION['reg_data'] = compact('name','email','pass','phone');
        redirect('index.php?page=register&step=2');
    }

    if ($action === 'register_step2') {
        $regData = $_SESSION['reg_data'] ?? null;
        if (!$regData) { redirect('index.php?page=register'); }

        $firm       = trim($_POST['firm'] ?? '');
        $city       = trim($_POST['city'] ?? '');
        $role       = $_POST['role']       ?? '';
        $experience = $_POST['experience'] ?? '';

        if (!$firm || !$city || !$role) { $inlineError = 'Please fill in all required fields.'; $step = 2; include BASE_PATH.'/pages/register.php'; exit; }

        $result = registerUser([
            'name'       => $regData['name'],
            'email'      => $regData['email'],
            'password'   => $regData['pass'],
            'phone'      => $regData['phone'],
            'firm'       => $firm,
            'city'       => $city,
            'role'       => $role,
            'experience' => $experience,
        ]);
        unset($_SESSION['reg_data']);
        if ($result['success']) {
            flash('toast', 'Welcome to Bafna Marbles! Your account is ready.');
            redirect('index.php?page=catalog');
        }
        $inlineError = $result['error'];
        $step = 2;
        include BASE_PATH . '/pages/register.php'; exit;
    }

    if ($action === 'forgot_password') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $inlineError = 'Please enter a valid email address.';
            include BASE_PATH . '/pages/forgot_password.php'; exit;
        }
        requestPasswordReset($email);
        redirect('index.php?page=forgot_password&sent=1');
    }

    if ($action === 'reset_password') {
        $token = $_POST['token']            ?? '';
        $pass  = $_POST['password']         ?? '';
        $conf  = $_POST['password_confirm'] ?? '';
        if (strlen($pass) < 8) { $inlineError = 'Password must be at least 8 characters.'; include BASE_PATH.'/pages/reset_password.php'; exit; }
        if ($pass !== $conf)   { $inlineError = 'Passwords do not match.'; include BASE_PATH.'/pages/reset_password.php'; exit; }
        $result = resetPassword($token, $pass);
        if ($result['success']) { redirect('index.php?page=reset_password&done=1'); }
        $inlineError = $result['error'];
        include BASE_PATH . '/pages/reset_password.php'; exit;
    }

    // ── Authenticated actions ─────────────────────────────────────────────────
    requireLogin();

    if ($action === 'logout') {
        logoutUser();
        redirect('index.php?page=login');
    }

    if ($action === 'toggle_shortlist') {
        $pid      = (int)($_POST['product_id'] ?? 0);
        $returnUrl = $_POST['return_url'] ?? 'index.php?page=catalog';
        $db = getDB();
        $st = $db->prepare("SELECT id FROM shortlist WHERE user_id=? AND product_id=?");
        $st->execute([$_SESSION['user_id'], $pid]);
        if ($st->fetch()) {
            $db->prepare("DELETE FROM shortlist WHERE user_id=? AND product_id=?")->execute([$_SESSION['user_id'], $pid]);
        } else {
            $db->prepare("INSERT IGNORE INTO shortlist (user_id,product_id) VALUES (?,?)")->execute([$_SESSION['user_id'], $pid]);
        }
        redirect($returnUrl);
    }

    if ($action === 'send_inquiry') {
        $pid  = (int)($_POST['product_id'] ?? 0);
        $msg  = trim($_POST['message']      ?? '');
        $qty  = trim($_POST['qty_required'] ?? '');
        if (!$pid || !$msg) {
            $inlineError = 'Please write a message before sending.';
            include BASE_PATH . '/pages/inquiry_form.php'; exit;
        }
        $db = getDB();
        $db->prepare("INSERT INTO inquiries (user_id,product_id,message,qty_required) VALUES (?,?,?,?)")
           ->execute([$_SESSION['user_id'], $pid, $msg, $qty]);
        flash('toast', 'Inquiry sent! The Bafna team will respond soon.');
        redirect('index.php?page=inquiries');
    }

    if ($action === 'update_profile') {
        $user = currentUser();
        $db   = getDB();
        $name = trim($_POST['name'] ?? '');
        if (!$name) { $inlineError = 'Name is required.'; include BASE_PATH.'/pages/profile.php'; exit; }
        $db->prepare("UPDATE users SET name=?,firm=?,city=?,phone=? WHERE id=?")
           ->execute([
               $name,
               trim($_POST['firm']  ?? ''),
               trim($_POST['city']  ?? ''),
               trim($_POST['phone'] ?? ''),
               $user['id'],
           ]);
        flash('toast', 'Profile updated.');
        redirect('index.php?page=profile&section=settings');
    }

    if ($action === 'change_password') {
        $user = currentUser();
        $curr = $_POST['current_password'] ?? '';
        $new  = $_POST['new_password']     ?? '';
        if (!password_verify($curr, $user['password'])) {
            $inlineError = 'Current password is incorrect.';
            include BASE_PATH . '/pages/profile.php'; exit;
        }
        if (strlen($new) < 8) { $inlineError = 'New password must be at least 8 characters.'; include BASE_PATH.'/pages/profile.php'; exit; }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        getDB()->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $user['id']]);
        flash('toast', 'Password changed successfully.');
        redirect('index.php?page=profile&section=settings');
    }
}

// ── Page routing ──────────────────────────────────────────────────────────────
$publicPages    = ['login','register','forgot_password','reset_password','support'];
$protectedPages = ['catalog','product','shortlist','inquiries','profile','inquiry_form','support','notifications'];

if (!in_array($page, $publicPages) && !in_array($page, $protectedPages)) {
    $page = isLoggedIn() ? 'catalog' : 'login';
}

if (!in_array($page, $publicPages) && !isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    redirect('index.php?page=login');
}

$pageFile = BASE_PATH . '/pages/' . $page . '.php';
if (!file_exists($pageFile)) {
    $page     = isLoggedIn() ? 'catalog' : 'login';
    $pageFile = BASE_PATH . '/pages/' . $page . '.php';
}

include $pageFile;