<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/translations.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/clients.php';
require_once __DIR__ . '/includes/room_visualizer.php';
require_once __DIR__ . '/includes/license.php';
require_once __DIR__ . '/includes/product_views.php';
require_once __DIR__ . '/includes/device_auth.php';
require_once __DIR__ . '/includes/catalog_pdf.php';
require_once __DIR__ . '/includes/selection_history.php';
ensureCatalogPdfPermissions();
ensureSelectionHistoryTable();
startSecureSession();

if (!isLoggedIn()) {
    attemptDeviceAutoLogin('user');
}
 
 // Verify CSRF token on every state-changing POST (public auth forms excluded
 // only where there is no session yet to bind the token to)
 
$page = preg_replace('/[^a-z_]/', '', $_GET['page'] ?? 'catalog');

// ── License / Activation middleware — runs before ANY routing, so a
// bare ?page=catalog etc. can never bypass an invalid/expired license.
enforceLicense($page);
// Client AJAX search
if (!empty($_GET['ajax_search']) && ($_GET['page'] ?? '') === 'clients' && isLoggedIn()) {
    $q      = trim($_GET['q'] ?? '');
    $db     = getDB();
    $sql    = "SELECT id, client_name, client_mobile, mansoner_name FROM clients WHERE user_id = ?";
    $params = [$_SESSION['user_id']];
    if ($q !== '') {
        $sql     .= " AND (client_name LIKE ? OR client_mobile LIKE ? OR mansoner_name LIKE ?)";
        $like     = "%{$q}%";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    $sql .= " ORDER BY client_name ASC LIMIT 20";
    $st = $db->prepare($sql);
    $st->execute($params);
    header('Content-Type: application/json');
    echo json_encode(['clients' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}
//  Handle POST actions 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    csrfVerify();
    //  Public auth actions 
    if ($action === 'login') {
        $result = loginUser($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            $redir = $_SESSION['redirect_after_login'] ?? 'index.php?page=catalog';
            unset($_SESSION['redirect_after_login']);
            redirect($redir);
        }
        elseif (($result['error'] ?? '') === 'not_verified'){
        redirect('index.php?page=waiting_approval');
    }  else {
        $inlineError = $result['error'];
        include BASE_PATH . '/pages/login.php'; exit;
    }
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
            redirect('index.php?page=waiting_approval');
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
    
  if ($action === 'switch_language') {
    $lang   = $_POST['lang'] ?? 'en';
    $return = $_POST['return_url'] ?? 'index.php?page=catalog';
    setCurrentLang($lang);
    // Guard against open-redirect — only allow internal relative paths
    if (!preg_match('#^index\.php#', $return)) $return = 'index.php?page=catalog';
    redirect($return);
}
      // ── Public activation action ────────────────────────────────────────
    if ($action === 'activate_license') {
        $result = activateLicenseKey($_POST['activation_key'] ?? '');
        if ($result['success']) {
            flash('toast', 'License activated successfully.');
            redirect('index.php?page=catalog');
        }
        $inlineError = $result['error'];
        include BASE_PATH . '/pages/activation.php'; exit;
    }
   //  Authenticated actions 
    requireLogin();
  // ── Room Visualizer: generate preview ────────────────────────────────────
if ($action === 'generate_room_preview') {
    header('Content-Type: application/json');
    $productId  = (int)($_POST['product_id']  ?? 0);
    $templateId = (int)($_POST['template_id'] ?? 0);
    if (!$productId || !$templateId) {
        echo json_encode(['success' => false, 'error' => 'Missing product or template.']);
        exit;
    }
    $result = generateRoomVisualization($_SESSION['user_id'], $productId, $templateId);
    echo json_encode($result);
    exit;
}

// ── Room Visualizer: delete a saved render ───────────────────────────────
if ($action === 'delete_room_visualization') {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    $ok = deleteRoomVisualization($id, $_SESSION['user_id']);
    echo json_encode(['success' => $ok]);
    exit;
}
  
  //  Create client
if ($action === 'create_client') {
    $result = createClient($_SESSION['user_id'], $_POST);
    if ($result['success']) {
        flash('toast', 'Client added successfully.');
        redirect('index.php?page=clients');
    }
    $inlineError = $result['error'];
    include BASE_PATH . '/pages/client_form.php'; exit;
}
  
  

// ── Update client
if ($action === 'update_client') {
    $id     = (int)($_POST['client_id'] ?? 0);
    $result = updateClient($id, $_SESSION['user_id'], $_POST);
    if ($result['success']) {
        flash('toast', 'Client updated.');
        redirect('index.php?page=client_form&id=' . $id);
    }
    $inlineError = $result['error'];
    include BASE_PATH . '/pages/client_form.php'; exit;
}

//  Delete client
if ($action === 'delete_client') {
    $id = (int)($_POST['client_id'] ?? 0);
    deleteClient($id, $_SESSION['user_id']);
    flash('toast', 'Client deleted.');
    redirect('index.php?page=clients');
}

//  Add to selection
if ($action === 'add_to_selection') {
    $clientId = (int)($_POST['client_id'] ?? 0);
    $result   = createSelection($clientId, $_SESSION['user_id'], $_POST);
    if ($result['success']) {
        flash('toast', 'Product added to selection.');
        redirect('index.php?page=client_selections&client_id=' . $clientId);
    }
    flash('error', $result['error']);
    redirect('index.php?page=product&id=' . (int)($_POST['product_id'] ?? 0));
}

// ── Update selection
if ($action === 'update_selection') {
    $id       = (int)($_POST['selection_id'] ?? 0);
    $clientId = (int)($_POST['client_id']    ?? 0);
    updateSelection($id, $_SESSION['user_id'], $_POST);
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
        stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]); exit;
    }
    flash('toast', 'Selection updated.');
    redirect('index.php?page=client_selections&client_id=' . $clientId);
}

//  Delete selection
if ($action === 'delete_selection') {
    $id       = (int)($_POST['selection_id'] ?? 0);
    $clientId = (int)($_POST['client_id']    ?? 0);
    deleteSelection($id, $_SESSION['user_id']);
    flash('toast', 'Removed from selection.');
    redirect('index.php?page=client_selections&client_id=' . $clientId);
}


    if ($action === 'logout') {
        logoutUser();
        redirect('index.php?page=login');
    }
  
   if ($action === 'forced_logout') {
        $device = getCurrentTrustedDevice('user');
        if ($device) {
            revokeTrustedDevice((int)$device['id']);
        }
        clearDeviceCookie();
        logoutUser();
        flash('toast', 'Signed out and this device is no longer trusted.');
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

    if ($action === 'update_profile') {
        $user = currentUser();
        $db   = getDB();
        $name = titleCase(trim($_POST['name'] ?? ''));
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
        redirect('index.php?page=profile');
    }
 if ($action === 'register_device') {
        $user   = currentUser();
        $name   = trim($_POST['device_name'] ?? '') ?: 'My Device';
        $return = $_POST['return_url'] ?? 'index.php?page=profile';
        $result = issueTrustedDevice([
            'user_id'     => $user['id'],
            'panel'       => 'user',
            'device_name' => $name,
        ]);
        flash($result['success'] ? 'toast' : 'error',
              $result['success'] ? 'Device trusted — you\'ll be auto-signed in on this device.' : ($result['error'] ?? 'Could not trust device.'));
        redirect($return);
    }

    if ($action === 'revoke_device') {
        $user   = currentUser();
        $did    = (int)($_POST['device_id'] ?? 0);
        $return = $_POST['return_url'] ?? 'index.php?page=profile';
        // Ownership check — only allow revoking own device
        $chk = getDB()->prepare("SELECT id FROM trusted_devices WHERE id=? AND user_id=?");
        $chk->execute([$did, $user['id']]);
        if ($chk->fetch()) {
            revokeTrustedDevice($did);
            flash('toast', 'Device removed.');
        } else {
            flash('error', 'Device not found.');
        }
        redirect($return);
    }

    if ($action === 'rename_device') {
        $user   = currentUser();
        $did    = (int)($_POST['device_id'] ?? 0);
        $name   = trim($_POST['device_name'] ?? '');
        $return = $_POST['return_url'] ?? 'index.php?page=devices';
        $chk = getDB()->prepare("SELECT id FROM trusted_devices WHERE id=? AND user_id=?");
        $chk->execute([$did, $user['id']]);
        if ($chk->fetch() && $name !== '') {
            renameDevice($did, $name);
            flash('toast', 'Device renamed.');
        } else {
            flash('error', 'Could not rename device.');
        }
        redirect($return);
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
        // Security: password change invalidates all trusted devices — a
        // compromised password shouldn't leave old trusted-device cookies
        // valid on other machines.
        revokeAllDevicesFor($user['id'], null);
        clearDeviceCookie();
        flash('toast', 'Password changed successfully. All trusted devices have been signed out for security.');
        redirect('index.php?page=profile');
    }
}

///  Page routing 
$publicPages    = ['login','register','forgot_password','reset_password','waiting_approval','activation'];
$protectedPages = ['catalog','product','shortlist','profile','support','notifications',
                   'clients','client_form','client_selections','room_visualizer','devices'];

// Unknown ?page= value → 404 immediately (checked first so bogus page
// names never silently fall back to catalog/login).
if (!in_array($page, $publicPages, true) && !in_array($page, $protectedPages, true)) {
    http_response_code(404);
    $pageTitle = 'Page Not Found — ' . APP_NAME;
    include BASE_PATH . '/pages/404.php';
    exit;
}

if (in_array($page, $protectedPages, true) && isLoggedIn()) {
    $db = getDB();
    $st = $db->prepare("SELECT is_verified FROM users WHERE id=?");
    $st->execute([$_SESSION['user_id']]);
    $uv = $st->fetchColumn();
    if (!(int)$uv) {
        logoutUser();
        redirect('index.php?page=waiting_approval');
    }
}

if (!in_array($page, $publicPages, true) && !isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    redirect('index.php?page=login');
}

$pageFile = BASE_PATH . '/pages/' . $page . '.php';
if (!file_exists($pageFile)) {
    http_response_code(404);
    $pageTitle = 'Page Not Found — ' . APP_NAME;
    include BASE_PATH . '/pages/404.php';
    exit;
}

include $pageFile;