<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/device_auth.php';

startSecureSession(); 

if (!isAdmin()) {
    attemptDeviceAutoLogin('admin');
}
$autoload = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoload)) {
    die('Not found');
}

require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

define('ADMIN_PANEL', true);


require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/logo.php';
require_once __DIR__ . '/../includes/clients.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/wa_share.php';
require_once __DIR__ . '/../includes/product_pdf.php';
require_once __DIR__ . '/views/_permission_guards.php';
require_once __DIR__ . '/../includes/room_visualizer.php';
require_once __DIR__ . '/../includes/license.php';
require_once __DIR__ . '/../includes/product_views.php';
require_once __DIR__ . '/../includes/watermark.php';
require_once __DIR__ . '/../includes/categories.php';
require_once __DIR__ . '/../includes/translations.php';
require_once __DIR__ . '/../includes/catalog_pdf.php';
ensureCatalogPdfPermissions();
ensureCategoryPermissions();
ensureWatermarkPermission();
ensureProductViewPermission();
ensureTranslationsPermission();

// Handle POST 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

  
   $_jsonOnlyActions = ['admin_add_selection','admin_update_selection','admin_delete_selection','test_smtp'];
    csrfVerify(in_array($action, $_jsonOnlyActions, true));
    csrfVerify(); 
  
   if ($action === 'admin_login') {
        if (loginAdmin($_POST['username'] ?? '', $_POST['password'] ?? '')) {
          csrfVerify();
            redirect('index.php');
        }
        $_SESSION['admin_error'] = 'Invalid credentials.';
        redirect('index.php');
    }

   requireAdmin();
    
  if ($action === 'register_admin_device') {
        $name = trim($_POST['device_name'] ?? '') ?: 'My Device';
        $result = issueTrustedDevice([
            'admin_id'    => (int)($_SESSION['admin_id'] ?? 0),
            'panel'       => 'admin',
            'device_name' => $name,
        ]);
        flash($result['success'] ? 'toast' : 'error',
              $result['success'] ? 'Device trusted — you\'ll be auto-signed in on this device.' : ($result['error'] ?? 'Could not trust device.'));
        redirect('index.php?page=devices');
    }

   if ($action === 'revoke_admin_device') {
        $did = (int)($_POST['device_id'] ?? 0);
        $chk = getDB()->prepare("SELECT id FROM trusted_devices WHERE id=? AND admin_id=?");
        $chk->execute([$did, (int)$_SESSION['admin_id']]);
        if ($chk->fetch()) {
            revokeTrustedDevice($did);
            flash('toast', 'Device removed.');
        } else {
            flash('error', 'Device not found.');
        }
        redirect('index.php?page=dashboard');
    }

    if ($action === 'admin_toggle_device') {
    requireAdmin();
    requireAdminPermission('devices.manage');
    $did       = (int)($_POST['device_id']  ?? 0);
    $newStatus = ($_POST['new_status'] ?? '') === 'active' ? 'active' : 'disabled';

    if (!isSuperAdmin()) {
        $chk = getDB()->prepare("SELECT id FROM trusted_devices WHERE id=? AND admin_id=?");
        $chk->execute([$did, (int)$_SESSION['admin_id']]);
        if (!$chk->fetch()) {
            flash('error', 'Device not found.');
            redirect('index.php?page=devices');
        }
    }

    if ($newStatus === 'disabled') {
        revokeTrustedDevice($did);
    } else {
        getDB()->prepare("UPDATE trusted_devices SET status='active', revoked_at=NULL, updated_at=? WHERE id=?")
               ->execute([time(), $did]);
        logDeviceActivity($did, 'device_reenabled', '');
    }
    flash('toast', 'Device status updated.');
    redirect('index.php?page=devices');
}

if ($action === 'admin_delete_device') {
    requireAdmin();
    requireAdminPermission('devices.manage');
    $did = (int)($_POST['device_id'] ?? 0);

    if (!isSuperAdmin()) {
        $chk = getDB()->prepare("SELECT id FROM trusted_devices WHERE id=? AND admin_id=?");
        $chk->execute([$did, (int)$_SESSION['admin_id']]);
        if (!$chk->fetch()) {
            flash('error', 'Device not found.');
            redirect('index.php?page=devices');
        }
    }

    getDB()->prepare("DELETE FROM trusted_devices WHERE id=?")->execute([$did]);
    flash('toast', 'Device deleted.');
    redirect('index.php?page=devices');
}
  
   if ($action === 'admin_logout') {
        unset($_SESSION['admin_id'], $_SESSION['admin_name']);
        $adminBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        header('Location: ' . $adminBase . '/index.php');
        exit;
    }
  
  if ($action === 'admin_forced_logout') {
        $device = getCurrentTrustedDevice('admin');
        if ($device) {
            revokeTrustedDevice((int)$device['id']);
        }
        clearDeviceCookie();
        unset($_SESSION['admin_id'], $_SESSION['admin_name']);
        $adminBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        header('Location: ' . $adminBase . '/index.php');
        exit;
    }
  
  if ($action === 'save_catalog_pdf_settings') {
    requireAdminPermission('catalog.settings');
    require_once __DIR__ . '/../includes/catalog_pdf.php';

    $cfg = getCatalogPdfSettingsDefaults();
    $cfg['layout'] = $_POST['layout'] ?? $cfg['layout'];
    $cfg['fields'] = array_values((array)($_POST['fields'] ?? []));

    $cfg['cover']['logo']       = isset($_POST['cover_logo']) ? 1 : 0;
    $cfg['cover']['title']      = trim($_POST['cover_title'] ?? '');
    $cfg['cover']['subtitle']   = trim($_POST['cover_subtitle'] ?? '');
    $cfg['cover']['show_date']  = isset($_POST['cover_show_date']) ? 1 : 0;
    $cfg['cover']['version']    = trim($_POST['cover_version'] ?? '');

    $cfg['closing']['enabled']    = isset($_POST['closing_enabled']) ? 1 : 0;
    $cfg['closing']['thank_you_text'] = trim($_POST['closing_text'] ?? '');
    $cfg['closing']['website_qr'] = isset($_POST['closing_website_qr']) ? 1 : 0;
    $cfg['closing']['gmap_qr']    = isset($_POST['closing_gmap_qr']) ? 1 : 0;

    $cfg['watermark']['type']        = $_POST['watermark_type'] ?? 'none';
    $cfg['watermark']['custom_text'] = trim($_POST['watermark_custom_text'] ?? '');
    $cfg['watermark']['opacity']     = (int)($_POST['watermark_opacity'] ?? 15);
    $cfg['watermark']['rotation']    = (int)($_POST['watermark_rotation'] ?? -45);

    $cfg['quality']['level']         = $_POST['quality_level'] ?? 'medium';
    $cfg['quality']['optimize_size'] = isset($_POST['quality_optimize']) ? 1 : 0;
    $cfg['orientation'] = $_POST['orientation'] ?? 'portrait';
    $cfg['page_size']   = $_POST['page_size'] ?? 'A4';
    $cfg['font']        = $_POST['font'] ?? 'helvetica';

    foreach (['primary','secondary','accent','background','text','button','border'] as $ck) {
        if (isset($_POST['color_'.$ck])) $cfg['colors'][$ck] = $_POST['color_'.$ck];
    }

    $cfg['email_share']['default_subject'] = trim($_POST['email_subject'] ?? '');
    $cfg['email_share']['default_message'] = trim($_POST['email_message'] ?? '');

    saveCatalogPdfSettingsDefaults($cfg);
    flash('toast', 'Catalog PDF settings saved.');
    redirect('index.php?page=catalog_pdf_settings');
}
  
  
  // ── CATALOG PDF: regenerate ─────────────────────────────────────────────────
if ($action === 'catalog_pdf_regenerate') {
    requireAdmin();
    requireAdminPermission('catalog.regenerate');
    require_once __DIR__ . '/../includes/catalog_pdf.php';
    require_once __DIR__ . '/../includes/catalog_pdf_engine.php';
    $cid = (int)($_POST['catalog_id'] ?? 0);
  $cat = getCatalog($cid);
if (!$cat || !catalogOwnedByCurrentAdmin($cat)) { flash('error','Catalog not be Regenerated.'); redirect('index.php?page=catalog_pdf_history'); }
    $result = generateCatalogPdf($cid);
    flash($result['success'] ? 'toast' : 'error', $result['success'] ? 'Catalog PDF regenerated.' : ('Failed: ' . ($result['error'] ?? '')));
    redirect('index.php?page=catalog_pdf_history');
}

// ── CATALOG PDF: delete ──────────────────────────────────────────────────────
if ($action === 'catalog_pdf_delete') {
    requireAdmin();
    requireAdminPermission('catalog.delete');
    require_once __DIR__ . '/../includes/catalog_pdf.php';
    $cid = (int)($_POST['catalog_id'] ?? 0);
  $cat = getCatalog($cid);
if (!$cat || !catalogOwnedByCurrentAdmin($cat)) { flash('error','Catalog not found.'); redirect('index.php?page=catalog_pdf_history'); }
    deleteCatalog($cid);
    flash('toast', 'Catalog deleted.');
    redirect('index.php?page=catalog_pdf_history');
}

  // ── CATALOG PDF: delete template ────────────────────────────────────────────
if ($action === 'delete_catalog_template') {
    requireAdmin();
    requireAdminPermission('catalog.template.manage');
    $tid = (int)($_POST['template_id'] ?? 0);
    getDB()->prepare("DELETE FROM catalog_templates WHERE id=?")->execute([$tid]);
    flash('toast', 'Template deleted.');
    redirect('index.php?page=catalog_pdf_templates');
}


// ── CATALOG PDF: duplicate ───────────────────────────────────────────────────
if ($action === 'catalog_pdf_duplicate') {
    requireAdmin();
    requireAdminPermission('catalog.create');
    require_once __DIR__ . '/../includes/catalog_pdf.php';
    $cid = (int)($_POST['catalog_id'] ?? 0);
    $src = getCatalog($cid);
    if ($src) {
        $res = createCatalogDraft([
            'name'        => $src['name'] . ' (Copy)',
            'admin_id'    => $_SESSION['admin_id'] ?? null,
            'product_ids' => $src['product_ids'],
            'config'      => $src['config'],
        ]);
        flash('toast', 'Catalog duplicated as draft.');
        redirect('index.php?page=catalog_pdf_wizard&id=' . $res['id']);
    }
    flash('error', 'Catalog not found.');
    redirect('index.php?page=catalog_pdf_history');
}
  // ── ROOM TEMPLATE: SAVE 
if ($action === 'save_room_template') {
    requireAdmin();
  requireAdminPermission('settings.logo');
    $roomType = $_POST['room_type'] ?? 'floor';
    $label    = trim($_POST['label'] ?? '');
    $maskJson = $_POST['mask_points'] ?? '[]';
    $points   = json_decode($maskJson, true);

    if (!$label || !is_array($points) || count($points) !== 4) {
        flash('error', 'Please provide a label and click all 4 corner points.');
        redirect('index.php?page=room_templates');
    }

    $baseFile = '';
    if (!empty($_FILES['base_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['base_image']['name'], PATHINFO_EXTENSION));
        $baseFile = 'room_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['base_image']['tmp_name'], ROOM_TEMPLATES_DIR . '/' . $baseFile);
    }

    $shadowFile = null;
    if (!empty($_FILES['shadow_layer']['name'])) {
        $ext = strtolower(pathinfo($_FILES['shadow_layer']['name'], PATHINFO_EXTENSION));
        $shadowFile = 'shadow_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['shadow_layer']['tmp_name'], ROOM_TEMPLATES_DIR . '/' . $shadowFile);
    }

    [$w, $h] = @getimagesize(ROOM_TEMPLATES_DIR . '/' . $baseFile) ?: [1200, 800];

   $clipJson = $_POST['clip_points'] ?? '';
    $clipPoints = json_decode($clipJson, true);
    $clipToStore = (is_array($clipPoints) && count($clipPoints) >= 3)
        ? json_encode($clipPoints)
        : null; // null = fallback to the 4-point quad at render time

    getDB()->prepare("
        INSERT INTO room_templates (room_type, label, base_image, shadow_layer, mask_points, clip_points, canvas_w, canvas_h, is_active, sort_order, created_at)
        VALUES (?,?,?,?,?,?,?,?,1,0,?)
    ")->execute([$roomType, $label, $baseFile, $shadowFile, json_encode($points), $clipToStore, $w, $h, time()]);

    flash('toast', 'Room template saved.');
    redirect('index.php?page=room_templates');
}

// ── ROOM TEMPLATE: TOGGLE 
if ($action === 'toggle_room_template') {
    requireAdmin();
  requireAdminPermission('settings.logo');
    getDB()->prepare("UPDATE room_templates SET is_active=? WHERE id=?")
        ->execute([(int)$_POST['is_active'], (int)$_POST['template_id']]);
    flash('toast', 'Template updated.');
    redirect('index.php?page=room_templates');
}

//  ROOM TEMPLATE: DELETE 
if ($action === 'delete_room_template') {
    requireAdmin();
  requireAdminPermission('settings.logo');
    $tid = (int)($_POST['template_id'] ?? 0);
    $st  = getDB()->prepare("SELECT * FROM room_templates WHERE id=?");
    $st->execute([$tid]);
    if ($row = $st->fetch()) {
        if ($row['base_image'])   @unlink(ROOM_TEMPLATES_DIR . '/' . $row['base_image']);
        if ($row['shadow_layer']) @unlink(ROOM_TEMPLATES_DIR . '/' . $row['shadow_layer']);
    }
    getDB()->prepare("DELETE FROM room_templates WHERE id=?")->execute([$tid]);
    flash('toast', 'Template deleted.');
    redirect('index.php?page=room_templates');
}
  if ($action === 'create_category') {
    requireAdmin(); requireAdminPermission('categories.create');
    $r=createCategory($_POST);
    $r['success']?flash('toast','Category created.'):flash('error',$r['error']);
    redirect('index.php?page=categories');
}
if ($action === 'update_category') {
    requireAdmin(); requireAdminPermission('categories.edit');
    $r=updateCategory((int)($_POST['category_id']??0), $_POST);
    $r['success']?flash('toast','Category updated.'):flash('error',$r['error']);
    redirect('index.php?page=categories');
}
if ($action === 'delete_category') {
    requireAdmin(); requireAdminPermission('categories.delete');
    $r=deleteCategory((int)($_POST['category_id']??0));
    $r['success']?flash('toast','Category deleted.'):flash('error',$r['error']);
    redirect('index.php?page=categories');
}
  
  //  ADMIN: CREATE CLIENT 
if ($action === 'admin_create_client') {
    requireAdmin();
    requireAdminPermission('clients.create');
    $userId = (int)($_POST['user_id'] ?? 0);
    $result = adminCreateClient($userId, $_POST);
    if ($result['success']) {
        flash('toast', 'Client created successfully.');
        redirect('index.php?page=admin_client_selections&client_id=' . $result['id']);
    }
    $inlineError = $result['error'];
    include __DIR__ . '/views/admin_client_form.php';
    exit;
}
 
//  ADMIN: UPDATE CLIENT 
if ($action === 'admin_update_client') {
    requireAdmin();
    requireAdminPermission('clients.edit');
    $clientId = (int)($_POST['client_id'] ?? 0);
    $userId   = (int)($_POST['user_id']   ?? 0);
    $result   = adminUpdateClient($clientId, $userId, $_POST);
    if ($result['success']) {
        flash('toast', 'Client updated successfully.');
        redirect('index.php?page=admin_client_form&id=' . $clientId);
    }
    $inlineError = $result['error'];
    $_GET['id'] = $clientId; 
    include __DIR__ . '/views/admin_client_form.php';
    exit;
}
 
//  ADMIN: DELETE CLIENT 
if ($action === 'admin_delete_client') {
    requireAdmin();
    requireAdminPermission('clients.delete');
    $clientId = (int)($_POST['client_id'] ?? 0);
    adminDeleteClient($clientId);
    flash('toast', 'Client and all related selections deleted.');
    redirect('index.php?page=admin_clients');
}
 
//  ADMIN: ADD PRODUCT SELECTION (AJAX — returns JSON)
if ($action === 'admin_add_selection') {
    requireAdmin();
    requireAdminPermissionJson('clients.edit');
    $clientId  = (int)($_POST['client_id']  ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    $result    = adminCreateSelectionForClient($clientId, $productId, $_POST);
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}
 
//  ADMIN: UPDATE PRODUCT SELECTION (AJAX) 
if ($action === 'admin_update_selection') {
    requireAdmin();
  requireAdminPermissionJson('clients.edit');
    $selectionId = (int)($_POST['selection_id'] ?? 0);
    $result = adminUpdateSelection($selectionId, $_POST);
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}
 
//  ADMIN: DELETE PRODUCT SELECTION 
if ($action === 'admin_delete_selection') {
    requireAdmin();
  requireAdminPermissionJson('clients.edit');
    $selectionId = (int)($_POST['selection_id'] ?? 0);
    $clientId    = (int)($_POST['client_id']    ?? 0);
    adminDeleteSelection($selectionId);
    flash('toast', 'Product removed from selection.');
    redirect('index.php?page=admin_client_selections&client_id=' . $clientId);
}
  
  // ── LICENSE: GENERATE 
    if ($action === 'generate_license_key') {
        requireAdmin();
        requireAdminPermission('license.manage');
        $result = createLicense($_POST);
        if ($result['success']) {
            flash('license_new_key', $result['plain_key']);
            flash('toast', 'Activation key generated successfully.');
        } else {
            flash('error', $result['error']);
        }
        redirect('index.php?page=license');
    }

    // ── LICENSE: UPDATE EXPIRY / CONVERT TO LIFETIME 
    if ($action === 'update_license') {
        requireAdmin();
        requireAdminPermission('license.manage');
        $id = (int)($_POST['license_id'] ?? 0);
        $result = updateLicenseExpiry($id, $_POST['expiry_date'] ?? '', !empty($_POST['is_lifetime']));
        $result['success'] ? flash('toast', 'License updated.') : flash('error', $result['error']);
        redirect('index.php?page=license');
    }

    //  LICENSE: REVOKE 
    if ($action === 'revoke_license') {
        requireAdmin();
        requireAdminPermission('license.manage');
        revokeLicense((int)($_POST['license_id'] ?? 0));
        flash('toast', 'License revoked.');
        redirect('index.php?page=license');
    }

    //  LICENSE: REACTIVATE 
    if ($action === 'reactivate_license') {
        requireAdmin();
        requireAdminPermission('license.manage');
        reactivateLicense((int)($_POST['license_id'] ?? 0));
        flash('toast', 'License reactivated.');
        redirect('index.php?page=license');
    }
    
    if ($action === 'save_watermark_settings') {
    requireAdminPermission('settings.watermark');
    saveWatermarkSettings($_POST);
    flash('toast', 'Watermark settings saved.');
    redirect('index.php?page=product_view_settings');
}

if ($action === 'upload_watermark_image') {
    requireAdminPermission('settings.watermark');
    $result = uploadWatermarkImage($_FILES['wm_image_file'] ?? []);
    flash($result['success'] ? 'toast' : 'error', $result['success'] ? 'Watermark image uploaded.' : $result['error']);
    redirect('index.php?page=product_view_settings');
}

if ($action === 'remove_watermark_image') {
    requireAdminPermission('settings.watermark');
    removeWatermarkImage();
    flash('toast', 'Watermark image removed.');
    redirect('index.php?page=product_view_settings');
}
  
    if ($action === 'save_colors') {
    requireAdminPermission('settings.colors');
    $defaults = array_keys(require __DIR__ . '/../config/colors.php');
    $extraKeys = [
        '--btn-radius','--card-radius',
        '--btn-bg','--btn-color','--btn-border-color',
        '--btn-hover-bg','--btn-hover-color','--btn-hover-border',
        '--btn-sec-bg','--btn-sec-color','--btn-sec-border',
        '--btn-sec-hover-bg','--btn-sec-hover-color','--btn-sec-hover-border',
        '--label-color','--label-font-size','--label-font-weight',
        '--input-bg','--input-color','--input-placeholder',
        '--input-border','--input-focus-border','--input-focus-shadow',
        '--input-hover-border','--input-radius','--input-font-size',
        '--navbar-bg','--navbar-color','--navbar-icon-color',
        '--navbar-hover-color','--navbar-active-color','--navbar-border',
        '--admin-font','--user-font',
        '--admin-bg','--admin-surface','--admin-surface2','--admin-surface3',
        '--admin-sidebar-from','--admin-sidebar-to',
        '--admin-sidebar-text','--admin-sidebar-active',
        '--admin-sidebar-hover','--admin-sidebar-border',
        '--admin-topbar-bg','--admin-topbar-border','--admin-topbar-text',
        '--admin-accent','--admin-accent2',
        '--admin-accent-light','--admin-accent-mid',
        '--admin-table-header-bg','--admin-table-row-hover',
        '--admin-table-border',
        '--admin-card-bg','--admin-card-border','--admin-card-radius',
        '--admin-badge-bg','--admin-badge-color',
        '--admin-text','--admin-text2','--admin-text3',
        '--admin-btn-primary-bg','--admin-btn-primary-color',
        '--admin-btn-primary-border','--admin-btn-primary-hover-bg',
        '--admin-btn-primary-hover-color','--admin-btn-primary-radius',
        '--admin-btn-sec-bg','--admin-btn-sec-color',
        '--admin-btn-sec-border','--admin-btn-sec-hover-bg',
        '--admin-btn-sec-hover-color','--admin-btn-sec-radius',
        '--admin-btn-danger-bg','--admin-btn-danger-color',
        '--admin-btn-danger-border','--admin-btn-danger-hover-bg',
        '--admin-btn-danger-hover-color',
        '--admin-btn-general-bg','--admin-btn-general-color',
        '--admin-btn-general-border','--admin-btn-general-hover-bg',
        '--admin-btn-general-radius',
        '--admin-input-bg','--admin-input-color','--admin-input-placeholder',
        '--admin-input-border','--admin-input-focus-border',
        '--admin-input-focus-shadow','--admin-input-hover-border',
        '--admin-input-radius','--admin-input-font-size',
        '--admin-textarea-bg','--admin-textarea-color',
        '--admin-textarea-border','--admin-textarea-focus-border',
        '--admin-textarea-radius',
        '--admin-label-color','--admin-label-font-size',
        '--admin-label-font-weight','--admin-label-transform',
        '--admin-label-letter-spacing',
        '--admin-btn-sync-bg','--admin-btn-sync-border','--admin-btn-sync-color',
    ];
    $defaults = array_unique(array_merge($defaults, $extraKeys));
    $fontKeys   = ['--admin-font', '--user-font'];
    $textKeys   = [ // free-form-ish but constrained (px/keyword lists), still whitelisted
        '--btn-radius','--card-radius','--input-radius','--input-font-size',
        '--label-font-size','--label-font-weight',
        '--admin-card-radius','--admin-btn-primary-radius','--admin-btn-sec-radius',
        '--admin-btn-general-radius','--admin-input-radius','--admin-input-font-size',
        '--admin-textarea-radius','--admin-label-font-size','--admin-label-font-weight',
        '--admin-label-transform','--admin-label-letter-spacing',
    ];

    function _sanitizeCssValue(string $key, string $raw, array $fontKeys, array $textKeys): ?string {
        $v = trim($raw);
        if ($v === '') return '';

        // Hard block: no CSS statement/rule-breaking characters ever allowed.
        if (preg_match('/[;{}]/', $v)) return null;
        if (stripos($v, 'expression(') !== false) return null;
        if (stripos($v, 'javascript:') !== false) return null;

        if (in_array($key, $fontKeys, true)) {
            // Allow: 'Font Name', sans-serif  (quoted family list only)
            return preg_match("/^[A-Za-z0-9 ,'\"-]+$/", $v) ? $v : null;
        }

        if (in_array($key, $textKeys, true)) {
            // font-weight keyword/number, uppercase/none, px/em/rem length, or number
            return preg_match('/^-?[A-Za-z0-9.%]+(px|em|rem)?$/', $v) ? $v : null;
        }

        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $v)) return $v;
        if (preg_match('/^rgba?\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+\s*(,\s*[\d.]+\s*)?\)$/', $v)) return $v;
        if (preg_match('/^linear-gradient\([a-zA-Z0-9#, .%()-]+\)$/', $v)) return $v;

        return null; // unrecognized shape — reject rather than trust
    }

    $rejected = [];
    $toSave   = [];
    foreach ($defaults as $k) {
        if (!isset($_POST[$k])) continue;
        $clean = _sanitizeCssValue($k, (string)$_POST[$k], $fontKeys, $textKeys);
        if ($clean === null) {
            $rejected[] = $k;
            continue;
        }
        $toSave[$k] = $clean;
    }

    setSettings($toSave); // one transaction, one cache invalidation

    if ($rejected) {
        flash('error', 'Some values were rejected as invalid and not saved: ' . implode(', ', $rejected));
    } else {
        flash('toast', 'Theme settings saved.');
    }
    redirect('index.php?page=colors');
}
  
    if ($action === 'reset_colors') {
    requireAdminPermission('settings.colors');
    $defaults = require __DIR__ . '/../config/colors.php';
    setSettings($defaults); // batched, single cache flush
    flash('toast', 'All theme settings reset to defaults.');
    redirect('index.php?page=colors');
}
  
    if ($action === 'upload_logo') {
      requireAdminPermission('settings.logo');
        $result = uploadLogo($_FILES['logo_file'] ?? []);
        if ($result['success']) {
            flash('toast', 'Logo updated successfully.');
        } else {
            flash('error', $result['error']);
        }
        redirect('index.php?page=logo');
    }
  
  
  if ($action === 'save_company_profile') {
    requireAdminPermission('settings.logo');
        $fields = [
            'company_name', 'company_short_name', 'company_tagline',
            'company_address', 'company_gst', 'company_whatsapp',
            'company_support_phone', 'company_email', 'company_location_url',
        ];
        foreach ($fields as $f) {
            setSetting($f, trim($_POST[$f] ?? ''));
        }
        flash('toast', 'Company profile saved.');
        redirect('index.php?page=logo');
    }
    
    if ($action === 'remove_logo') {
      requireAdminPermission('settings.logo');
        $st = getDB()->prepare("SELECT `value` FROM settings WHERE `key` = ?");
        $st->execute([LOGO_SETTING_KEY]);
        $old = (string)($st->fetchColumn() ?: '');
        if ($old !== '') {
            $oldPath = LOGO_DIR . '/' . $old;
            if (file_exists($oldPath)) @unlink($oldPath);
        }
        getDB()->prepare("DELETE FROM settings WHERE `key` = ?")->execute([LOGO_SETTING_KEY]);
        flash('toast', 'Logo removed. Default logo is now shown.');
        redirect('index.php?page=logo');
    }

    if ($action === 'save_product') {
        requireAdminPermission((int)($_POST['product_id'] ?? 0) ? 'products.edit' : 'products.create'); 
        saveProduct($_POST, $_FILES);
        redirect('index.php?page=products');
    }

    if ($action === 'delete_product') {
        requireAdminPermission('products.delete'); 
        $pid = (int)($_POST['product_id'] ?? 0);
        $db = getDB();
        _deleteProductWithDependencies($db, $pid);
        flash('toast', 'Product deleted.');
        redirect('index.php?page=products');
    }
  
     if ($action === 'delete_video') {
    requireAdminPermission('products.edit');
    $pid = (int)($_POST['product_id'] ?? 0);
    $st = getDB()->prepare("SELECT video_file FROM products WHERE id=?"); $st->execute([$pid]);
    if ($f = $st->fetchColumn()) @unlink(VIDEOS_DIR.'/'.$f);
    getDB()->prepare("UPDATE products SET video_file=NULL, video_url=NULL WHERE id=?")->execute([$pid]);
    flash('toast','Video removed.');
    redirect('index.php?page=product_edit&id='.$pid);
}

    if ($action === 'delete_photo') {
      requireAdminPermission('products.edit');
        $fid = (int)($_POST['photo_id'] ?? 0);
        $st  = getDB()->prepare("SELECT filename,product_id FROM product_photos WHERE id=?");
        $st->execute([$fid]);
        $ph  = $st->fetch();
        if ($ph) {
            @unlink(PHOTOS_DIR.'/'.$ph['filename']);
            getDB()->prepare("DELETE FROM product_photos WHERE id=?")->execute([$fid]);
        }
        flash('toast', 'Photo deleted.');
        redirect('index.php?page=product_edit&id='.($ph['product_id'] ?? 0));
    }
  
    if ($action === 'clear_notifications') {
       requireAdminPermission('notifications.clear');
            getDB()->exec("DELETE FROM notifications");
            flash('toast', 'All notifications cleared.');
           redirect('index.php?page=notifications');
        }
  
  if ($_POST['action'] == 'send_password_reset') {
    requireAdminPermission('users.reset_password');
    $userId = (int)($_POST['user_id'] ?? 0);
    $st = getDB()->prepare("SELECT email FROM users WHERE id=?");
    $st->execute([$userId]);
    $user = $st->fetch();
    if (!$user) {
        flash('error', 'User not found');
        redirect('index.php?page=users');
        exit;
    }
    requestPasswordReset($user['email']);
    flash('toast', 'Password reset email sent');
    redirect('index.php?page=users');
    exit;
}
  
  //  CREATE USER (Admin Panel)  */
if ($action === 'create_user') {
  requireAdminPermission('users.create');
    $result = createUserByAdmin([
        'name'       => $_POST['name']       ?? '',
        'email'      => $_POST['email']      ?? '',
        'password'   => $_POST['password']   ?? '',
        'phone'      => $_POST['phone']      ?? '',
        'firm'       => $_POST['firm']       ?? '',
        'city'       => $_POST['city']       ?? '',
        'role'       => $_POST['role']       ?? '',
        'experience' => $_POST['experience'] ?? '',
    ]);
 
    if (!$result['success']) {
        flash('error', $result['error']);
        redirect('index.php?page=users');
        exit;
    }
 
    $emailSent = false;
    try {
        $mailResult = sendNewUserEmail(
            $result['user']['email'],
            $result['user']['name'],
            $result['plain_password']
        );
        $emailSent = !empty($mailResult['success']);
        if (!$emailSent) {
            error_log('create_user: welcome email failed for user #' . $result['id'] . ': ' . ($mailResult['error'] ?? 'unknown error'));
        }
    } catch (Throwable $e) {
        error_log('create_user: welcome email exception for user #' . $result['id'] . ': ' . $e->getMessage());
    }
 
    flash('toast', $emailSent
        ? 'User created successfully. Login details emailed to ' . $result['user']['email'] . '.'
        : 'User created successfully, but the welcome email could not be sent. Please share login details manually.');
    redirect('index.php?page=users');
    exit;
}
  
  // SMTP SAVE  
if ($action === 'save_smtp') {
  requireAdminPermission('settings.smtp');
    $smtpKeys = ['smtp_host','smtp_port','smtp_username','smtp_from_email','smtp_from_name','smtp_encryption'];
    foreach ($smtpKeys as $k) {
        if (isset($_POST[$k])) setSetting($k, trim($_POST[$k]));
    }
   
    if (!empty($_POST['smtp_password'])) {
        setSetting('smtp_password', $_POST['smtp_password']);
    }
    setSetting('smtp_enabled', isset($_POST['smtp_enabled']) ? '1' : '0');
    flash('toast', 'SMTP settings saved.');
    redirect('index.php?page=smtp');
}

//  SMTP TEST (AJAX) 
if ($action === 'test_smtp') {
    header('Content-Type: application/json');
    requireAdmin();
  requireAdminPermissionJson('settings.smtp');
    require_once BASE_PATH . '/includes/mailer.php';
    $to = trim($_POST['test_email'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email address.']);
        exit;
    }
    $subject = 'SMTP Test — ' . APP_NAME;
    $html    = emailTemplate($subject,
        '<h2 style="color:#0a0a0a;font-size:20px;margin:0 0 12px;">SMTP Test Successful</h2>
         <p style="color:#555;font-size:14px;line-height:1.7;">This is a test email from your <strong>' . APP_NAME . '</strong> admin panel. Your SMTP configuration is working correctly.</p>'
    );
    $result = sendMail($to, $subject, $html, 'SMTP Test: This is a test email from ' . APP_NAME);
    echo json_encode($result);
    exit;
}

//  USER VERIFICATION TOGGLE 
if ($action === 'update_user_status') {
    requireAdmin();
  requireAdminPermission('users.edit');
    $uid      = (int)($_POST['user_id']  ?? 0);
    $verified = (int)($_POST['verified'] ?? 0);
    $db = getDB();
    $db->prepare("UPDATE users SET is_verified=? WHERE id=?")->execute([$verified, $uid]);

    // Send approval email when verified=1
    if ($verified === 1) {
        require_once BASE_PATH . '/includes/mailer.php';
        $st = $db->prepare("SELECT name, email FROM users WHERE id=?");
        $st->execute([$uid]);
        $u = $st->fetch();
        if ($u) {
            sendApprovalEmail($u['email'], $u['name']);
        }
    }
    flash('toast', $verified ? 'User verified & approval email sent.' : 'User access revoked.');
    redirect('index.php?page=users');
}
  
  if ($action === 'save_user_edit') {
    requireAdmin();
    requireAdminPermission('users.edit');
    $uid = (int)($_POST['user_id'] ?? 0);
    if (!$uid) {
        flash('error', 'Invalid user.');
        redirect('index.php?page=users');
    }

    $name  = titleCase(trim($_POST['name']  ?? ''));
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $firm  = titlecase(trim($_POST['firm']  ?? ''));
    $city  = titlecase(trim($_POST['city']  ?? ''));
    $role  = $_POST['role'] ?? '';

    if (!$name) {
        flash('error', 'Name is required.');
        redirect('index.php?page=users');
    }
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'A valid email address is required.');
        redirect('index.php?page=users');
    }

    $db = getDB();

    // Make sure email isn't already used by a different user
    $chk = $db->prepare("SELECT id FROM users WHERE email=? AND id<>?");
    $chk->execute([$email, $uid]);
    if ($chk->fetch()) {
        flash('error', 'This email is already used by another user.');
        redirect('index.php?page=users');
    }

    $db->prepare("UPDATE users SET name=?, email=?, phone=?, firm=?, city=?, role=? WHERE id=?")
       ->execute([$name, $email, $phone, $firm, $city, $role, $uid]);

    flash('toast', 'User updated successfully.');
    redirect('index.php?page=users');
}

//  DELETE USER 
if ($action === 'delete_user') {
    requireAdmin();
    requireAdminPermission('users.delete');
    $uid     = (int)($_POST['user_id']    ?? 0);
    $confirm = trim($_POST['confirm_text'] ?? '');
    if ($confirm !== 'DELETE') {
        flash('error', 'Confirmation text did not match. User not deleted.');
        redirect('index.php?page=users');
    }
    $db = getDB();
    try {
        $db->beginTransaction();
        // Delete selections → clients → shortlist → inquiries → user
        $db->prepare("DELETE cs FROM client_selections cs
                      JOIN clients c ON cs.client_id=c.id WHERE c.user_id=?")->execute([$uid]);
        $db->prepare("DELETE FROM clients WHERE user_id=?")->execute([$uid]);
        $db->prepare("DELETE FROM shortlist WHERE user_id=?")->execute([$uid]);
        $db->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
        $db->commit();
        flash('toast', 'User and all related data deleted.');
    } catch (Throwable $e) {
        $db->rollBack();
        flash('error', 'Delete failed: ' . $e->getMessage());
    }
    redirect('index.php?page=users');
}

  	if ($_POST['action'] === 'sync_photos') {
      requireAdminPermission('sync.run');
    syncPhotosFromDirectory();
      redirect('index.php?page=products');
    }
 	 if ($_POST['action']=='sync_measurements'){
       requireAdminPermission('sync.run');
    syncMeasurementSheetsfromdirectory();
     redirect('index.php?page=products');
     }

		if ($_POST['action']=='sync_dna'){
          requireAdminPermission('sync.run');
    syncDNAReportsfromdirectory();
          redirect('index.php?page=products');
        }
  
 	 if($_POST['action']=='export'){
       requireAdminPermission('products.export');
    exportExcel();
    redirect('index.php?page=products');
	}

		if($_POST['action']=='import'){
          requireAdminPermission('products.import');
 	   importExcel($_FILES['xls_file'] ?? null);
 	  redirect('index.php?page=products');
		}
   
     if ($action === 'import_photos') {
       requireAdminPermission('products.upload_photos'); 
        importPhotos($_FILES['photo_zip'] ?? null);
        redirect('index.php?page=products');
    }
   //  ROLE: CREATE 
    if ($action === 'create_role') {
        requireAdmin();
        requireAdminPermission('roles.manage');
        $result = createRole($_POST);
        if ($result['success']) {
            flash('toast', 'Role created successfully.');
        } else {
            flash('error', $result['error']);
        }
        redirect('index.php?page=roles');
    }
 
    //  ROLE: UPDATE 
    if ($action === 'update_role') {
        requireAdmin();
        requireAdminPermission('roles.manage');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $result = updateRole($roleId, $_POST);
        if ($result['success']) {
            flash('toast', 'Role updated.');
            flushAdminPermissionCache();
        } else {
            flash('error', $result['error']);
        }
        redirect('index.php?page=roles');
    }
 
    //  ROLE: DELETE 
    if ($action === 'delete_role') {
        requireAdmin();
        requireAdminPermission('roles.manage');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $result = deleteRole($roleId);
        if ($result['success']) {
            flash('toast', 'Role deleted.');
            flushAdminPermissionCache();
        } else {
            flash('error', $result['error']);
        }
        redirect('index.php?page=roles');
    }
 
    //  ROLE: SAVE PERMISSIONS 
    if ($action === 'save_role_permissions') {
        requireAdmin();
        requireAdminPermission('roles.assign');
        $roleId  = (int)($_POST['role_id'] ?? 0);
        $permIds = array_map('intval', (array)($_POST['permissions'] ?? []));
        // Guard: cannot modify super_admin permissions
        $st = getDB()->prepare("SELECT is_system FROM admin_roles WHERE id=?");
        $st->execute([$roleId]);
        $r = $st->fetch();
        if ($r && $r['is_system']) {
            flash('error', 'Super Admin permissions cannot be changed.');
            redirect('index.php?page=roles');
        }
        try {
            saveRolePermissions($roleId, $permIds);
            flushAdminPermissionCache();
            flash('toast', 'Permissions saved successfully.');
        } catch (Throwable $e) {
            flash('error', 'Failed to save permissions: ' . $e->getMessage());
        }
        redirect('index.php?page=roles');
    }
 
    //  ADMIN ACCOUNT: CREATE 
    if ($action === 'create_admin_account') {
        requireAdmin();
        requireAdminPermission('admins.manage');
        $result = createAdminAccount($_POST);
        if ($result['success']) {
            flash('toast', 'Admin account created.');
        } else {
            flash('error', $result['error']);
        }
        redirect('index.php?page=admin_accounts');
    }
 
    // ADMIN ACCOUNT: UPDATE 
    if ($action === 'update_admin_account') {
        requireAdmin();
        requireAdminPermission('admins.manage');
        $adminId = (int)($_POST['admin_id'] ?? 0);
        $result  = updateAdminAccount($adminId, $_POST);
        if ($result['success']) {
            // Security: if a password was set, revoke that admin's trusted
            // devices so old device cookies can't bypass the new credentials.
            if (!empty($_POST['password'])) {
                require_once __DIR__ . '/../includes/device_auth.php';
                revokeAllDevicesFor(null, $adminId);
            }
            flash('toast', 'Admin account updated.');
            flushAdminPermissionCache();
        } else {
            flash('error', $result['error']);
        }
        redirect('index.php?page=admin_accounts');
    }
 
    // ADMIN ACCOUNT: DELETE 
    if ($action === 'delete_admin_account') {
        requireAdmin();
        requireAdminPermission('admins.manage');
        $adminId = (int)($_POST['admin_id'] ?? 0);
        $result  = deleteAdminAccount($adminId);
        if ($result['success']) {
            flash('toast', 'Admin account deleted.');
        } else {
            flash('error', $result['error']);
        }
        redirect('index.php?page=admin_accounts');
    }
}
  
//  AJAX Sync endpoint 
if (isset($_GET["ajax_sync"]) && isAdmin()) {
    requireAdminPermissionJson('sync.run');
    header("Content-Type: application/json");
    $step = (int)($_GET["ajax_sync"]);
    echo json_encode(runSyncStep($step));
    exit;
}
//  License CSV export 
if (isset($_GET['export_licenses']) && isAdmin()) {
    requireAdminPermission('license.manage');
    exportLicensesCsv();
}

if (isset($_GET['ajax_role_perms']) && isAdmin()) {
    requireAdminPermissionJson('roles.view');
    $roleId = (int)($_GET['role_id'] ?? 0);
    $role   = getRoleWithPermissions($roleId);
    header('Content-Type: application/json');
    if (!$role) {
        echo json_encode(['error' => 'Role not found']);
    } else {
        echo json_encode([
            'role_id'        => $role['id'],
            'role_name'      => $role['name'],
            'is_system'      => (bool)$role['is_system'],
            'permission_ids' => array_map('intval', $role['permission_ids']),
        ]);
    }
    exit;
}


//  AJAX WhatsApp PDF generation endpoint 
if (isset($_GET['wa_pdf']) && isAdmin()) {
    requireAdminPermission('products.whatsapp');
    // max 10 PDF generations/min/session
    if (!throttle('wa_pdf', 10, 60)) { 
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Too many requests. Please wait a moment.']);
        exit;
    }
    handleWaPdfAjax();
}

//  Catalog PDF download endpoint 
if (isset($_GET['catalog_download']) && isAdmin()) {
    requireAdminPermission('catalog.download');
    $cid = (int)($_GET['id'] ?? 0);
    $cat = getCatalog($cid);
    if (!$cat || empty($cat['pdf_path']) || !file_exists($cat['pdf_path'])) {
        http_response_code(404);
        echo 'Catalog PDF not found. Generate it first.';
        exit;
    }
    if (!throttle('catalog_download', 20, 60)) {
        http_response_code(429);
        echo 'Too many requests. Please wait a moment.';
        exit;
    }

    getDB()->prepare("INSERT INTO catalog_download_logs (catalog_id, channel, ip_address, success, created_at) VALUES (?,?,?,?,?)")
           ->execute([$cid, 'download', $_SERVER['REMOTE_ADDR'] ?? '', 1, time()]);

    $safeName = preg_replace('/[^A-Za-z0-9 _\-]/u', '', $cat['name']);
    $safeName = trim(preg_replace('/\s+/', '_', $safeName)) ?: ('catalog_' . $cid);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $safeName . '.pdf"');
    header('Content-Length: ' . filesize($cat['pdf_path']));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($cat['pdf_path']);
    exit;
}

//  Direct PDF download endpoint 
if (isset($_GET['pdf_download']) && isAdmin()) {
    requireAdminPermission('products.pdf');
    $pid = (int)($_GET['product_id'] ?? 0);
    if (!$pid) { http_response_code(400); echo 'Missing product_id'; exit; }
    if (!throttle('pdf_download', 10, 60)) { 
        http_response_code(429);
        echo 'Too many requests. Please wait a moment.';
        exit;
    }

    $result = generateProductPdf($pid);

    if (!$result['success']) {
        http_response_code(500);
        echo $result['error'] ?? 'PDF generation failed.';
        exit;
    }

    // Build safe filename from product name
    $db  = getDB();
    $st  = $db->prepare("SELECT name FROM products WHERE id = ?");
    $st->execute([$pid]);
    $row = $st->fetch();
    $rawName  = $row['name'] ?? 'product';
    $safeName = preg_replace('/[^A-Za-z0-9 _\-]/u', '', $rawName);
    $safeName = trim(preg_replace('/\s+/', '_', $safeName));
    if ($safeName === '') $safeName = 'product_' . $pid;
    $downloadName = $safeName . '.pdf';

    // Stream to browser
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($result['path']));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($result['path']);

    // Delete temp file after streaming
    @unlink($result['path']);
    exit;
}
//  Routing 
$page = preg_replace('/[^a-z_]/', '', $_GET['page'] ?? 'dashboard');

if (!isAdmin()) {
    $adminError = $_SESSION['admin_error'] ?? null;
    unset($_SESSION['admin_error']);
    include __DIR__ . '/views/login.php';
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// Functions
// ════════════════════════════════════════════════════════════════════════════
function catalogOwnedByCurrentAdmin(array $cat): bool {
    return isSuperAdmin() || (int)($cat['admin_id'] ?? 0) === (int)($_SESSION['admin_id'] ?? 0);
}

function saveProduct(array $data, array $files): void {
    $db  = getDB();
    $pid = (int)($data['product_id'] ?? 0);

    $fields = [
        'name'              => trim($data['name']              ?? ''),
        'category'          => trim($data['category']          ?? ''),
        'subcategory'       => trim($data['subcategory']       ?? ''),
        'color_subcategory' => trim($data['color_subcategory'] ?? ''),
        'quarry_number'     => trim($data['quarry_number']     ?? ''),
        'total_quantity'    => (float)($data['total_quantity']    ?? 0),
        'quantity_available'=> (float)($data['quantity_available']?? 0),
        'quantity_on_hold'  => (float)($data['quantity_on_hold']  ?? 0),
        'pieces'            => (int)($data['pieces']           ?? 0),
        'thickness'         => trim($data['thickness']         ?? ''),
        // Split dimension columns
        'sizes_l'           => trim($data['sizes_l']           ?? ''),
        'sizes_h'           => trim($data['sizes_h']           ?? ''),
        'cutter_size_l'     => trim($data['cutter_size_l']     ?? ''),
        'cutter_size_h'     => trim($data['cutter_size_h']     ?? ''),
        'origin'            => trim($data['origin']            ?? ''),
        'finish'            => trim($data['finish']            ?? ''),
        'description'       => trim($data['description']       ?? ''),
        'in_stock'          => isset($data['in_stock'])  ? 1 : 0,
        'featured'          => isset($data['featured'])  ? 1 : 0,
        'sort_order'        => (int)($data['sort_order'] ?? 0),
        'palette'           => trim($data['palette'] ?? '["F2F0EC","D8CFC4","BFB0A0"]'),
        'video_url'         => trim($data['video_url'] ?? ''),
    ];

    // Measurement sheet — MIME validated, original filename kept for sync
     if (!empty($files['measurement_sheet']['name'])) {
         $v = validateUploadMime(
             $files['measurement_sheet'],
             ['application/pdf'],
             10 * 1024 * 1024
         );
         if ($v['valid']) {
             $fn = basename($files['measurement_sheet']['name']);
             if (move_uploaded_file($files['measurement_sheet']['tmp_name'], MEASUREMENT_DIR . '/' . $fn)) {
                 $fields['measurement_sheet'] = $fn;
             }
         } else {
             error_log('saveProduct: measurement_sheet rejected — ' . $v['error']);
         }
     }
      
     if (!empty($files['video_file']['name'])) {
    $v = validateUploadMime($files['video_file'], ['video/mp4','video/webm','video/quicktime'], 50*1024*1024);
    if ($v['valid']) {
        $fn = uniqid('vid_').'.'.strtolower(pathinfo($files['video_file']['name'], PATHINFO_EXTENSION));
        if (move_uploaded_file($files['video_file']['tmp_name'], VIDEOS_DIR.'/'.$fn)) {
            if ($pid) { $old=$db->prepare("SELECT video_file FROM products WHERE id=?"); $old->execute([$pid]); if($o=$old->fetchColumn()) @unlink(VIDEOS_DIR.'/'.$o); }
            $fields['video_file'] = $fn;
        }
    } else { error_log('saveProduct: video rejected — '.$v['error']); }
}
     // DNA report — MIME validated, original filename kept for sync
     if (!empty($files['dna_report']['name'])) {
         $v = validateUploadMime(
             $files['dna_report'],
             ['application/pdf'],
             10 * 1024 * 1024
         );
         if ($v['valid']) {
             $fn = basename($files['dna_report']['name']);
             if (move_uploaded_file($files['dna_report']['tmp_name'], DNA_DIR . '/' . $fn)) {
                 $fields['dna_report'] = $fn;
             }
         } else {
             error_log('saveProduct: dna_report rejected — ' . $v['error']);
         }
     }

    if ($pid) {
        $set  = implode(',', array_map(fn($k) => "$k=?", array_keys($fields)));
        $vals = array_values($fields);
        $vals[] = $pid;
        $db->prepare("UPDATE products SET $set WHERE id=?")->execute($vals);
    } else {
        $cols = implode(',', array_keys($fields));
        $phs  = implode(',', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO products ($cols) VALUES ($phs)")->execute(array_values($fields));
        $pid  = $db->lastInsertId();
    }

    // Handle photo uploads
    if (isset($files['photos']) && !empty($files['photos']['name'])) {
        $names  = is_array($files['photos']['name'])     ? $files['photos']['name']     : [$files['photos']['name']];
        $tmps   = is_array($files['photos']['tmp_name']) ? $files['photos']['tmp_name'] : [$files['photos']['tmp_name']];
        $errors = is_array($files['photos']['error'])    ? $files['photos']['error']    : [$files['photos']['error']];

        $st = $db->prepare("SELECT COALESCE(MAX(sort_order),0) FROM product_photos WHERE product_id=?");
        $st->execute([$pid]);
        $order = ((int)$st->fetchColumn()) + 1;

        foreach ($names as $i => $origName) {
            if (($errors[$i] ?? 1) !== UPLOAD_ERR_OK) continue;
            if (empty($tmps[$i])) continue;
          // Validate real MIME before accepting — keeps original filename for sync
         $v = validateUploadMime(
             ['tmp_name' => $tmps[$i], 'size' => $files['photos']['size'][$i] ?? 0, 'error' => $errors[$i]],
             ['image/jpeg', 'image/png', 'image/webp'],8 * 1024 * 1024);
         if (!$v['valid']) {
             error_log('saveProduct: photo rejected at index ' . $i . ' — ' . $v['error']);
             continue;
         }
           $fn = normalizePhotoFilename(basename(trim($origName)));
            $chk = $db->prepare("SELECT id FROM product_photos WHERE product_id=? AND filename=?");
            $chk->execute([$pid, $fn]);
            if ($chk->fetch()) continue;
            if (!move_uploaded_file($tmps[$i], PHOTOS_DIR.'/'.$fn)) continue;
            $db->prepare("INSERT INTO product_photos (product_id,filename,sort_order) VALUES (?,?,?)")
               ->execute([$pid, $fn, $order++]);
        }
    }

    syncPhotosFromDirectory();

    if (!(int)($data['product_id'] ?? 0)) {
        createNotification(
            'New Product Added',
            'Product "' . trim($data['name'] ?? '') . '" has been added to the catalog.',
            'product'
        );
    }
    flash('toast', 'Product saved successfully.');
}


//  Photo Import (multi-file by quarry prefix) 
function importPhotos(?array $files): void
{
    if (!$files || empty($files['name'])) {
        flash('error','No files uploaded.');
        return;
    }

    $db = getDB();
    $count = 0;

    $names  = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmps   = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];

    foreach ($names as $idx => $origName) {

        if (($errors[$idx] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
            continue;

        // keep exact filename
        $fn = normalizePhotoFilename(basename(trim($origName)));

        // extension check
        $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg','jpeg','png','webp']))
            continue;

        // Extract Q23048 from:
        // Q23048-IMG.jpeg
        // Q23048-IMG-1.jpeg
        // Q23048-IMG-2.jpg

       if (!preg_match('/^(.+)-IMG(?:-\d+)?\.[a-z]+$/i', $fn, $m))
    continue;
$quarry = strtoupper(trim($m[1]));

      

        // product lookup
        $st = $db->prepare("
            SELECT id
            FROM products
            WHERE quarry_number=?
            LIMIT 1
        ");

        $st->execute([$quarry]);

        $prod = $st->fetch(PDO::FETCH_ASSOC);

        if (!$prod)
            continue;

        // avoid duplicate db row
        $chk = $db->prepare("
            SELECT id
            FROM product_photos
            WHERE product_id=?
            AND filename=?
        ");

        $chk->execute([
            $prod['id'],
            $fn
        ]);

        if ($chk->fetch())
            continue;

        // ensure directory exists
        if (!is_dir(PHOTOS_DIR))
            mkdir(PHOTOS_DIR,0777,true);

        $dest = rtrim(PHOTOS_DIR,'/').'/'.$fn;

        // remove existing file if present
        if (file_exists($dest))
            unlink($dest);

        // save exact filename
        if (!move_uploaded_file($tmps[$idx], $dest))
            continue;

        // order
        $ord = $db->prepare("
            SELECT COALESCE(MAX(sort_order),0) m
            FROM product_photos
            WHERE product_id=?
        ");

        $ord->execute([$prod['id']]);

        $order = ((int)$ord->fetchColumn()) + 1;

        // insert row
        $db->prepare("
            INSERT INTO product_photos
            (
                product_id,
                filename,
                sort_order
            )
            VALUES (?,?,?)
        ")->execute([
            $prod['id'],
            $fn,
            $order
        ]);

        $count++;
    }

    flash(
        'toast',
        $count.' photo(s) imported successfully.'
    );
}
function parseQuarryFromFilename(string $stem): string {
    // Pattern 1: Q228-IMG_jpg  or  Q23048-IMG-1  → everything before -IMG (case-insensitive)
    if (preg_match('/^(.+)-IMG/i', $stem, $m)) {
        return trim($m[1]);
    }
    // Pattern 2: QM-0421-1  → strip trailing hyphen + digits only
    $stripped = preg_replace('/-\d+$/', '', $stem);
    // Sanity check: result must still contain something meaningful
    if ($stripped !== '' && $stripped !== $stem) {
        return trim($stripped);
    }
    // Pattern 3: no suffix at all — use the whole stem as quarry number
    return trim($stem);
}
//  Sync Step Runner 
// Called via AJAX: ?ajax_sync=1|2|3
// Returns JSON: { step, label, found, synced, skipped, errors[], done }
function runSyncStep(int $step): array {
    set_time_limit(120);
    switch ($step) {
        case 1: return syncImages();
        case 2: return syncMeasurementSheets();
        case 3: return syncDnaReports();
        default: return ['step'=>$step,'done'=>true,'error'=>'Unknown step'];
    }
}


function fixUploadCasingAfterImport(): array {
    $db = getDB();
    $log = ['folders' => 0, 'photos_db' => 0, 'measurement' => 0, 'dna' => 0];

    // 1. Fix photo color-folder casing (Q123/White -> Q123/White ucfirst-lower)
    if (is_dir(PHOTOS_DIR)) {
        $folders = array_diff(scandir(PHOTOS_DIR), ['.', '..']);
        foreach ($folders as $folder) {
            $path = PHOTOS_DIR . '/' . $folder;
            if (!is_dir($path)) continue;
            $target = ucfirst(strtolower($folder));
            if ($folder !== $target) {
                $newPath = PHOTOS_DIR . '/' . $target;
                if (!file_exists($newPath)) {
                    rename($path, $newPath);
                    $log['folders']++;
                } else {
                    continue; // conflict — leave alone
                }
            }
            $upd = $db->prepare("UPDATE product_photos SET filename = REPLACE(filename, ?, ?) WHERE filename LIKE ?");
            $upd->execute([$folder . '/', $target . '/', $folder . '/%']);
            $log['photos_db'] += $upd->rowCount();
        }
    }
  
    return $log;
}
//--------- sync photo directory -----------------------------------
/**
 * syncPhotoFiles() — SINGLE shared engine for photo synchronization.
 * Used by both:
 *   - syncImages()              → Sync page, Step 1 (AJAX, returns step info)
 *   - syncPhotosFromDirectory() → called after admin upload / Excel import (flash + void)
 *
 * Responsibilities:
 *   1. Normalize color-folder casing directly under PHOTOS_DIR (white -> White)
 *   2. Recursively normalize every image filename's casing (q23048-img.JPG -> Q23048-IMG.jpg)
 *   3. Keep any existing product_photos.filename rows in sync with renamed paths
 *   4. Match normalized filenames to products by quarry number and insert new rows
 *
 * Returns a stats array: ['found','synced','skipped','errors']
 */
function syncPhotoFiles(): array {

    $db = getDB();

    $stats = [
        'found'   => 0,
        'synced'  => 0,
        'skipped' => 0,
        'errors'  => [],
    ];

    if (!is_dir(PHOTOS_DIR)) {
        $stats['errors'][] = 'Photos directory not found: ' . PHOTOS_DIR;
        return $stats;
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    // ── Pass 1: normalize direct-child folder casing (e.g. white -> White) ────
    $topEntries = array_diff(scandir(PHOTOS_DIR), ['.', '..']);
    foreach ($topEntries as $entry) {
        $path = PHOTOS_DIR . '/' . $entry;
        if (!is_dir($path)) continue;

        $target = ucfirst(strtolower($entry));
        if ($entry === $target) continue;

        $newPath = PHOTOS_DIR . '/' . $target;
        if (file_exists($newPath)) {
            $stats['errors'][] = "Folder casing conflict: both '$entry' and '$target' exist — merge manually.";
            continue;
        }

        if (rename($path, $newPath)) {
            $db->prepare("
                UPDATE product_photos
                SET filename = REPLACE(filename, ?, ?)
                WHERE filename LIKE ?
            ")->execute([$entry . '/', $target . '/', $entry . '/%']);
            $stats['errors'][] = "Renamed folder: '$entry' -> '$target'"; // informational, not an error
        } else {
            $stats['errors'][] = "Could not rename folder '$entry' — check permissions.";
        }
    }

    // ── Pass 2: recursively normalize + sync every image file ─────────────────
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(PHOTOS_DIR, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) continue;

        $ext = strtolower(pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) continue;

        $stats['found']++;

        $fullPath = $fileInfo->getPathname();
        $dir      = dirname($fullPath);
        $file     = $fileInfo->getFilename();

        // Normalize filename casing on disk (q23048-img.JPG -> Q23048-IMG.jpg)
        $normalizedFile = normalizePhotoFilename($file);
        if ($normalizedFile !== $file) {
            $normalizedFullPath = $dir . '/' . $normalizedFile;
            if (file_exists($normalizedFullPath)) {
                $stats['errors'][] = "Filename casing conflict: '$normalizedFile' already exists, left '$file' as-is.";
            } elseif (rename($fullPath, $normalizedFullPath)) {
                $oldRelative = str_replace(PHOTOS_DIR . '/', '', $fullPath);
                $fullPath    = $normalizedFullPath;
                $file        = $normalizedFile;
                $newRelative = str_replace(PHOTOS_DIR . '/', '', $fullPath);

                $db->prepare("UPDATE product_photos SET filename=? WHERE filename=?")
                   ->execute([$newRelative, $oldRelative]);
            } else {
                $stats['errors'][] = "Could not rename file '$file' — check permissions.";
            }
        }

        $relativePath = str_replace(PHOTOS_DIR . '/', '', $fullPath);
        $stem         = pathinfo($file, PATHINFO_FILENAME);
        $quarry       = parseQuarryFromFilename($stem);

        if (!$quarry) {
            $stats['skipped']++;
            $stats['errors'][] = "Cannot parse quarry from: $relativePath";
            continue;
        }

        $st = $db->prepare("SELECT id FROM products WHERE quarry_number = ? LIMIT 1");
        $st->execute([$quarry]);
        $prod = $st->fetch();

        if (!$prod) {
            $stats['skipped']++;
            $stats['errors'][] = "No product for quarry '$quarry' ($relativePath)";
            continue;
        }

        // Skip if already linked
        $chk = $db->prepare("SELECT id FROM product_photos WHERE product_id=? AND filename=?");
        $chk->execute([$prod['id'], $relativePath]);
        if ($chk->fetch()) {
            $stats['skipped']++;
            continue;
        }

        $ord = $db->prepare("SELECT COALESCE(MAX(sort_order),0) FROM product_photos WHERE product_id=?");
        $ord->execute([$prod['id']]);
        $order = ((int)$ord->fetchColumn()) + 1;

        $db->prepare("INSERT INTO product_photos (product_id, filename, sort_order) VALUES (?,?,?)")
           ->execute([$prod['id'], $relativePath, $order]);

        $stats['synced']++;
    }

    return $stats;
}
//--------- sync photo directory -----------------------------------

function syncPhotosFromDirectory(): void {
    $stats = syncPhotoFiles();
    flash('toast', $stats['synced'] . ' photos synced successfully.');
}

//----------- Sync Measurement Sheet from directory ---------------------------------
function syncMeasurementSheetsfromdirectory(): void
{
    $db = getDB();
    $count = 0;

    $baseDir = MEASUREMENT_DIR;

    if (!is_dir($baseDir)) {
        flash('error', 'Measurement folder missing');
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $baseDir,
            RecursiveDirectoryIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $item) {

        if (!$item->isFile()) {
            continue;
        }

        $fullPath = $item->getPathname();

        // only pdf
        if (strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) !== 'pdf') {
            continue;
        }

        $file = $item->getFilename();

        /*
        Supported:

        MS-Q23048.pdf
        MS-Q23048-1.pdf
        MS-3243-34343.pdf
        MS-Q3336-W994.pdf
        */

        if (!preg_match('/^MS-(.+?)\.pdf$/i', $file, $m)) {
            continue;
        }

        // quarry number
        $quarry = strtoupper(trim($m[1]));

        // relative path
        $relativePath = str_replace(
            $baseDir . '/',
            '',
            $fullPath
        );

        // update product
        $st = $db->prepare("
            UPDATE products
            SET measurement_sheet = ?
            WHERE UPPER(quarry_number) = ?
        ");

        $st->execute([
            $relativePath,
            $quarry
        ]);

        $count += $st->rowCount();
    }

    flash(
        'toast',
        $count . ' measurement sheet(s) synced'
    );
}

//------- Sync Dna reports --------------------------------
function syncDNAReportsfromdirectory(): void
{
    $db = getDB();
    $count = 0;

    $baseDir = DNA_DIR;

    if (!is_dir($baseDir)) {
        flash('error', 'DNA folder missing');
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $baseDir,
            RecursiveDirectoryIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $item) {

        if (!$item->isFile()) {
            continue;
        }

        $fullPath = $item->getPathname();

        // only pdf
        if (strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) !== 'pdf') {
            continue;
        }

        $file = $item->getFilename();

        /*
        Supported:

        DNA-Q23048.pdf
        DNA-324-3333.pdf
        DNA-Q3336-W994.pdf
        */

        if (!preg_match('/^DNA-(.+?)\.pdf$/i', $file, $m)) {
            continue;
        }

        // quarry number
        $quarry = strtoupper(trim($m[1]));

        // relative path
        $relativePath = str_replace(
            $baseDir . '/',
            '',
            $fullPath
        );

        // update product
        $st = $db->prepare("
            UPDATE products
            SET dna_report = ?
            WHERE UPPER(quarry_number) = ?
        ");

        $st->execute([
            $relativePath,
            $quarry
        ]);

        $count += $st->rowCount();
    }

    flash(
        'toast',
        $count . ' DNA report(s) synced'
    );
}

// ════════════════════════════════════════════════════════════════════════════
// exportExcel() — replace existing function
// ════════════════════════════════════════════════════════════════════════════
function exportExcel(): void {
    try {
        if (ob_get_length()) ob_end_clean();

        $db       = getDB();
        $products = $db->query("SELECT * FROM products ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

        $headers = [
            'name','category','subcategory','color_subcategory','quarry_number',
            'total_quantity','quantity_available','quantity_on_hold','pieces',
            'thickness','sizes_l','sizes_h','cutter_size_l','cutter_size_h',
            'origin','finish','description','in_stock','featured',
            'measurement_sheet','dna_report',
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        foreach ($headers as $col => $header) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
            $sheet->setCellValue($column . '1', $header);
        }

        $rowNo = 2;
        foreach ($products as $p) {
            foreach ($headers as $col => $key) {
                $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
                $sheet->setCellValue($column . $rowNo, $p[$key] ?? '');
            }
            $rowNo++;
        }

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="bafna_products_'.date('Ymd').'.xlsx"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        header('Expires: 0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;

    } catch (\Throwable $e) {
        die('<pre>Excel Export Error:'."\n\n".$e->getMessage()."\n\nFile: ".$e->getFile()."\nLine: ".$e->getLine().'</pre>');
    }
}

// ════════════════════════════════════════════════════════════════════════════
// importExcel() — full sync: insert new, update existing, delete orphans
// ════════════════════════════════════════════════════════════════════════════
function importExcel(?array $file): void {

    //  1. Basic file validation 
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'File upload failed.');
        return;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
        flash('error', 'Only .xlsx, .xls or .csv files are allowed.');
        return;
    }

    $dest = EXCEL_DIR . '/' . time() . '_' . basename($file['name']);
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        flash('error', 'Could not save uploaded file. Check directory permissions.');
        return;
    }

    //  2. Load spreadsheet 
    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($dest);
        $rows        = $spreadsheet->getActiveSheet()->toArray();
    } catch (\Throwable $e) {
        flash('error', 'Could not read spreadsheet: ' . $e->getMessage());
        return;
    }

    if (count($rows) < 2) {
        flash('error', 'Spreadsheet has no data rows.');
        return;
    }

    //  3. Parse headers 
    $excelHeaders = array_map(fn($h) => trim((string)$h), array_shift($rows));

    $headerMap = [
        'Product Name'       => 'name',
        'Stone Type'         => 'category',
        'Stone Color'        => 'color_subcategory',
        'Quarry Number'      => 'quarry_number',
        'Total Quantity'     => 'total_quantity',
        'Hold Quantity'      => 'quantity_on_hold',
        'Available Quantity' => 'quantity_available',
        'Total Piece'        => 'pieces',
        'Thickness'          => 'thickness',
        'Net Usable Size L' => 'sizes_l',
        'Net Usable Size H' => 'sizes_h',
        'Italian Size L'     => 'cutter_size_l',
        'Italian Size H'     => 'cutter_size_h',
    ];

    // Build a case-insensitive lookup once: normalized-key => db-column
$headerMapCI = [];
foreach ($headerMap as $k => $v) {
    $headerMapCI[mb_strtolower(trim($k))] = $v;
}

$headers = [];
foreach ($excelHeaders as $h) {
    $clean     = trim($h);
    $lookupKey = mb_strtolower($clean);
    $headers[] = $headerMapCI[$lookupKey] ?? strtolower(str_replace(' ', '_', $clean));
}

    //  4. Collect & validate rows 
    $importedQuarries = [];   // quarry_number => field array
    $errors           = [];

    foreach ($rows as $rowIdx => $row) {
        if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) {
            continue; // skip blank rows
        }

        $padded = array_pad($row, count($headers), null);
        $data   = array_combine($headers, $padded);
        if (!$data) continue;

        $g  = fn($k) => trim((string)($data[$k] ?? ''));
        $gf = fn($k) => (float)($data[$k] ?? 0);
        $gi = fn($k) => (int)($data[$k] ?? 0);

        $name   = $g('name');
        $quarry = strtoupper($g('quarry_number'));

        if (!$name || !$quarry) {
            $errors[] = "Row " . ($rowIdx + 2) . ": skipped — missing name or quarry number.";
            continue;
        }

        if (isset($importedQuarries[$quarry])) {
            $errors[] = "Row " . ($rowIdx + 2) . ": duplicate quarry '$quarry' in file — skipped.";
            continue;
        }

        // Dimension split (support both split columns and legacy single column)
        $csL = $g('cutter_size_l');
        $csH = $g('cutter_size_h');
        if ($csL === '' && $csH === '') {
            $old = $g('cutter_size');
            if ($old !== '') {
                $parts = preg_split('/[x×]/i', $old);
                $csL   = trim($parts[0] ?? '');
                $csH   = trim($parts[1] ?? '');
            }
        }

        $szL = $g('sizes_l');
        $szH = $g('sizes_h');
        if ($szL === '' && $szH === '') {
            $old = $g('sizes');
            if ($old !== '') {
                $parts = preg_split('/[x×]/i', $old);
                $szL   = trim($parts[0] ?? '');
                $szH   = trim($parts[1] ?? '');
            }
        }

        $importedQuarries[$quarry] = [
            'name'               => $name,
            'category' => $g('category') !== '' ? resolveCategoryByName($g('category')) : '',
            'subcategory'        => $g('subcategory'),
            'color_subcategory'  => $g('color_subcategory'),
            'quarry_number'      => $quarry,
            'total_quantity'     => $gf('total_quantity'),
            'quantity_available' => $gf('quantity_available'),
            'quantity_on_hold'   => $gf('quantity_on_hold'),
            'pieces'             => $gi('pieces'),
            'thickness'          => $g('thickness'),
            'sizes_l'            => $szL,
            'sizes_h'            => $szH,
            'cutter_size_l'      => $csL,
            'cutter_size_h'      => $csH,
            'origin'             => $g('origin'),
            'finish'             => $g('finish'),
            'description'        => $g('description'),
            'in_stock'           => $gi('in_stock') ?: 1,
            'featured'           => $gi('featured'),
            'measurement_sheet'  => $g('measurement_sheet'),
            'dna_report'         => $g('dna_report'),
        ];
    }

    if (empty($importedQuarries)) {
        flash('error', 'No valid rows found in the file.');
        return;
    }

    //  5. Load existing products from DB 
    $db = getDB();

    $existing = $db
        ->query("SELECT id, quarry_number FROM products")
        ->fetchAll(\PDO::FETCH_ASSOC);

    // Map quarry_number (uppercase) => id
    $dbQuarryMap = [];
    foreach ($existing as $row) {
        $dbQuarryMap[strtoupper($row['quarry_number'])] = (int)$row['id'];
    }

    //  6. Determine add / update / delete sets 
    $importedSet = array_keys($importedQuarries);          // quarries in file
    $dbSet       = array_keys($dbQuarryMap);               // quarries in DB

    $toInsert = array_diff($importedSet, $dbSet);          // new
    $toUpdate = array_intersect($importedSet, $dbSet);     // existing
    $toDelete = array_diff($dbSet, $importedSet);          // orphaned

    $countAdded   = 0;
    $countUpdated = 0;
    $countDeleted = 0;

    //  7. Run everything inside a transaction 
    try {
        $db->beginTransaction();

        //  7a. DELETE orphaned products 
        foreach ($toDelete as $quarry) {
            $pid = $dbQuarryMap[$quarry];
            _deleteProductWithDependencies($db, $pid);
            $countDeleted++;
        }

        //  7b. UPDATE existing products 
        foreach ($toUpdate as $quarry) {
            $pid    = $dbQuarryMap[$quarry];
            $fields = $importedQuarries[$quarry];

            $set  = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
            $vals = array_values($fields);
            $vals[] = $pid;
            $db->prepare("UPDATE products SET $set WHERE id = ?")->execute($vals);
            $countUpdated++;
        }

        //  7c. INSERT new products 
        foreach ($toInsert as $quarry) {
            $fields = $importedQuarries[$quarry];
            $cols   = implode(', ', array_keys($fields));
            $phs    = implode(', ', array_fill(0, count($fields), '?'));
            $db->prepare("INSERT INTO products ($cols) VALUES ($phs)")->execute(array_values($fields));
            $countAdded++;
        }

        $db->commit();

    } catch (\Throwable $e) {
        $db->rollBack();
        flash('error', 'Import failed (transaction rolled back): ' . $e->getMessage());
        return;
    }
     finally {
    if (file_exists($dest)) {
        unlink($dest);}
    }
  
      // 7d. Fix casing mismatches introduced by spreadsheet text
    $casingLog = fixUploadCasingAfterImport();

    //  8. Notification & summary flash 
    createNotification(
        'Inventory Synced',
        "Import complete — {$countAdded} added, {$countUpdated} updated, {$countDeleted} deleted.",
        'product'
    );

    $summary = "Sync complete: {$countAdded} added · {$countUpdated} updated · {$countDeleted} deleted.";
  if (array_sum($casingLog) > 0) {
        $summary .= " Casing fixed: {$casingLog['folders']} folders, {$casingLog['photos_db']} photo rows.";
    }
        syncImages();
        syncMeasurementSheets();
         syncDnaReports();
    if (!empty($errors)) {
        $summary .= ' ' . count($errors) . ' row(s) skipped (see error log).';
        // Log to file for admin review
        $logFile = BASE_PATH . '/storage/logs/import.log';
        if (!is_dir(dirname($logFile))) {
            @mkdir(dirname($logFile), 0755, true);
        }
        $logLines = '[' . date('Y-m-d H:i:s') . '] Import: ' . $file['name'] . "\n"
            . implode("\n", $errors) . "\n\n";
        file_put_contents($logFile, $logLines, FILE_APPEND | LOCK_EX);
    }
    
       flash('toast', $summary);
  
}

// _deleteProductWithDependencies()

function _deleteProductWithDependencies(\PDO $db, int $pid): void {

    $photos = $db->prepare("SELECT filename FROM product_photos WHERE product_id = ?");
    $photos->execute([$pid]);
    foreach ($photos->fetchAll(\PDO::FETCH_COLUMN) as $filename) {
        $fullPath = rtrim(PHOTOS_DIR, '/') . '/' . $filename;
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }

    // 2. Collect measurement sheet / dna report paths and delete files
    $docs = $db->prepare("SELECT measurement_sheet, dna_report FROM products WHERE id = ?");
    $docs->execute([$pid]);
    $doc = $docs->fetch(\PDO::FETCH_ASSOC);
    if ($doc) {
        if (!empty($doc['measurement_sheet'])) {
            $p = rtrim(MEASUREMENT_DIR, '/') . '/' . $doc['measurement_sheet'];
            if (file_exists($p)) @unlink($p);
        }
        if (!empty($doc['dna_report'])) {
            $p = rtrim(DNA_DIR, '/') . '/' . $doc['dna_report'];
            if (file_exists($p)) @unlink($p);
        }
    }

   
    $db->prepare("DELETE FROM product_photos    WHERE product_id = ?")->execute([$pid]);
    $db->prepare("DELETE FROM shortlist         WHERE product_id = ?")->execute([$pid]);
    $db->prepare("DELETE FROM client_selections WHERE product_id = ?")->execute([$pid]);
    
    $db->prepare("DELETE FROM products WHERE id = ?")->execute([$pid]);
}

function syncImages(): array {
    $stats = syncPhotoFiles();
    return array_merge(
        ['step' => 1, 'label' => 'Photos', 'done' => true],
        $stats
    );
}
// ── Step 2: Sync Measurement Sheets from /assets/uploads/measurement_sheets/ ─
// File naming: Q23048-MS.pdf  or  Q23048-MS-1.pdf
function syncMeasurementSheets(): array {

    $db = getDB();

    $result = [
        'step'    => 2,
        'label'   => 'Measurement Sheets',
        'found'   => 0,
        'synced'  => 0,
        'skipped' => 0,
        'errors'  => [],
        'done'    => false
    ];

    if (!is_dir(MEASUREMENT_DIR)) {

        $result['errors'][] = 'Measurement sheets directory not found: ' . MEASUREMENT_DIR;
        $result['done'] = true;

        return $result;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            MEASUREMENT_DIR,
            RecursiveDirectoryIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $fileInfo) {

        // Skip folders
        if (!$fileInfo->isFile()) {
            continue;
        }

        $file = $fileInfo->getFilename();

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if ($ext !== 'pdf') {
            continue;
        }

        $result['found']++;

        $fullPath = $fileInfo->getPathname();

        // Save relative path
        $relativePath = str_replace(
            rtrim(MEASUREMENT_DIR, '/') . '/',
            '',
            $fullPath
        );

        $stem = pathinfo($file, PATHINFO_FILENAME);

        // Examples:
        // Q23048-MS.pdf
        // Q23048-MS-1.pdf
        // A9993-W998899-MS.pdf

       if (preg_match('/^MS-(.+)$/i', $stem, $m)) {
            
            $quarry = trim($m[1]);

        } else {

            $quarry = preg_replace('/-\d+$/', '', $stem);
        }

        if (!$quarry) {

            $result['skipped']++;
            $result['errors'][] = "Cannot parse quarry from: $file";

            continue;
        }

        $st = $db->prepare("
            SELECT id, measurement_sheet
            FROM products
            WHERE quarry_number = ?
            LIMIT 1
        ");

        $st->execute([$quarry]);

        $prod = $st->fetch();

        if (!$prod) {

            $result['skipped']++;
            $result['errors'][] =
                "No product for quarry '$quarry' ($file)";

            continue;
        }

        // Already linked
        if ($prod['measurement_sheet'] === $relativePath) {

            $result['skipped']++;
            continue;
        }

        $db->prepare("
            UPDATE products
            SET measurement_sheet = ?
            WHERE id = ?
        ")->execute([
            $relativePath,
            $prod['id']
        ]);

        $result['synced']++;
    }

    $result['done'] = true;

    return $result;
}
//  Step 3: Sync DNA Reports from /assets/uploads/dna_reports/ 
// File naming: Q23048-DNA.pdf  or  Q23048-DNA-1.pdf
function syncDnaReports(): array {

    $db = getDB();

    $result = [
        'step'    => 3,
        'label'   => 'DNA / Lot Reports',
        'found'   => 0,
        'synced'  => 0,
        'skipped' => 0,
        'errors'  => [],
        'done'    => false
    ];

    if (!is_dir(DNA_DIR)) {

        $result['errors'][] = 'DNA reports directory not found: ' . DNA_DIR;
        $result['done'] = true;

        return $result;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            DNA_DIR,
            RecursiveDirectoryIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $fileInfo) {

        // Skip folders
        if (!$fileInfo->isFile()) {
            continue;
        }

        $file = $fileInfo->getFilename();

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if ($ext !== 'pdf') {
            continue;
        }

        $result['found']++;

        $fullPath = $fileInfo->getPathname();

        // Relative path for DB
        $relativePath = str_replace(
            rtrim(DNA_DIR, '/') . '/',
            '',
            $fullPath
        );

        $stem = pathinfo($file, PATHINFO_FILENAME);

        // Examples:
        // Q23048-DNA.pdf
        // Q23048-DNA-1.pdf
        // A9993-W998899-DNA.pdf

        if (preg_match('/^DNA-(.+)$/i', $stem, $m)) {

            $quarry = trim($m[1]);

        } else {

            $quarry = preg_replace('/-\d+$/', '', $stem);
        }

        if (!$quarry) {

            $result['skipped']++;
            $result['errors'][] = "Cannot parse quarry from: $file";

            continue;
        }

        $st = $db->prepare("
            SELECT id, dna_report
            FROM products
            WHERE quarry_number = ?
            LIMIT 1
        ");

        $st->execute([$quarry]);

        $prod = $st->fetch();

        if (!$prod) {

            $result['skipped']++;
            $result['errors'][] =
                "No product for quarry '$quarry' ($file)";

            continue;
        }

        // Already linked
        if ($prod['dna_report'] === $relativePath) {

            $result['skipped']++;
            continue;
        }

        $db->prepare("
            UPDATE products
            SET dna_report = ?
            WHERE id = ?
        ")->execute([
            $relativePath,
            $prod['id']
        ]);

        $result['synced']++;
    }

    $result['done'] = true;

    return $result;
}


$pages = ['dashboard','products','product_edit','colors','users','inquiries','sync',
              'notifications','logo','user_clients','admin_selections','smtp',
              'admin_clients','admin_client_form','admin_client_selections',             'roles','admin_accounts','room_templates','license','product_view_settings','devices','product_categories','translations','catalog_pdf_settings','catalog_pdf_history', 'catalog_pdf_wizard','catalog_pdf_templates'];

// Unknown ?page= value in admin panel → 404 instead of silently falling
// back to the dashboard.
if (!in_array($page, $pages, true)) {
    http_response_code(404);
    include __DIR__ . '/views/404.php';
    exit;
}

$file = __DIR__ . '/views/' . $page . '.php';

//  Route-level permission gates 
    $routePermissions = [
        'products'               => 'products.view',
        'users'                  => 'users.view',
        'user_clients'           => 'users.view',
        'admin_clients'          => 'clients.view',
        'admin_client_form'      => 'clients.view',
        'admin_client_selections'=> 'clients.view',
        'admin_selections'       => 'clients.view',
        'notifications'          => 'notifications.view',
        'sync'                   => 'sync.run',
        'logo'                   => 'settings.logo',
        'colors'                 => 'settings.colors',
        'smtp'                   => 'settings.smtp',
        'room_templates'         => 'settings.room_templates',
        'roles'                  => 'roles.view',
        'admin_accounts'         => 'admins.view',
        'inquiries'              => 'users.view',
        'license'                => 'license.manage',
        'product_view_settings'  => 'settings.product_views',
        'devices'                => 'devices.view',
        'categories'			 => 'categories.view',
        'translations' => 'translations.manage',
      'catalog_pdf_settings' => 'catalog.settings',
      'catalog_pdf_history'  => 'catalog.history',
        'catalog_pdf_wizard'   => 'catalog.create',
      'catalog_pdf_templates'=> 'catalog.template.manage',
    ];
    if (isset($routePermissions[$page])) {
        requireAdminPermission($routePermissions[$page]);
    }
include $file;