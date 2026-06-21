<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
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

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/logo.php';
require_once __DIR__ . '/../includes/clients.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/wa_share.php';



// ── CSV Export (before any output) ───────────────────────────────────────────
//if (isset($_GET['action']) && $_GET['action'] === 'export_csv' && isAdmin()) {
 //   exportCSV();
 //   exit;
//}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'admin_login') {
        if (loginAdmin($_POST['username'] ?? '', $_POST['password'] ?? '')) {
            redirect('index.php');
        }
        $_SESSION['admin_error'] = 'Invalid credentials.';
        redirect('index.php');
    }

    requireAdmin();

    if ($action === 'admin_logout') {
        unset($_SESSION['admin_id'], $_SESSION['admin_name']);
        redirect('index.php');
    }

    if ($action === 'save_colors') {
        $defaults = array_keys(require __DIR__ . '/../config/colors.php');
        // Also handle radius keys
        $defaults[] = '--btn-radius';
        $defaults[] = '--card-radius';
        foreach ($defaults as $k) {
            if (isset($_POST[$k])) {
                $val = preg_replace('/[<>"\']/', '', $_POST[$k]);
                setSetting($k, $val);
            }
        }
        flash('toast', 'Color scheme saved.');
        redirect('index.php?page=colors');
    }

    if ($action === 'reset_colors') {
        $defaults = require __DIR__ . '/../config/colors.php';
        foreach ($defaults as $k => $v) setSetting($k, $v);
        flash('toast', 'Colors reset to defaults.');
        redirect('index.php?page=colors');
    }
    if ($action === 'upload_logo') {
        $result = uploadLogo($_FILES['logo_file'] ?? []);
        if ($result['success']) {
            flash('toast', 'Logo updated successfully.');
        } else {
            flash('error', $result['error']);
        }
        redirect('index.php?page=logo');
    }
    
    if ($action === 'remove_logo') {
        // Delete file
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
        saveProduct($_POST, $_FILES);
        redirect('index.php?page=products');
    }

    if ($action === 'delete_product') {
    $pid = (int)($_POST['product_id'] ?? 0);
       $db = getDB();
    _deleteProductWithDependencies($db, $pid);
    flash('toast', 'Product deleted.');
    redirect('index.php?page=products');
}

    if ($action === 'delete_photo') {
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
            getDB()->exec("DELETE FROM notifications");
            flash('toast', 'All notifications cleared.');
           redirect('index.php?page=notifications');
        }
  
  if ($_POST['action'] == 'send_password_reset') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $st = getDB()->prepare("SELECT email FROM users WHERE id=?");
    $st->execute([$userId]);
    $user = $st->fetch();
    if (!$user) {
        flash('error', 'User not found');
        redirect('index.php?page=users');
        exit;
    }
    // reuse your existing function
    requestPasswordReset($user['email']);
    flash('toast', 'Password reset email sent');
    redirect('index.php?page=users');
    exit;
}
  
  // ─── SMTP SAVE ──────────────────────────────────────────────────────────── */
if ($action === 'save_smtp') {
    $smtpKeys = ['smtp_host','smtp_port','smtp_username','smtp_from_email','smtp_from_name','smtp_encryption'];
    foreach ($smtpKeys as $k) {
        if (isset($_POST[$k])) setSetting($k, trim($_POST[$k]));
    }
    // Password: only update if non-empty
    if (!empty($_POST['smtp_password'])) {
        setSetting('smtp_password', $_POST['smtp_password']);
    }
    setSetting('smtp_enabled', isset($_POST['smtp_enabled']) ? '1' : '0');
    flash('toast', 'SMTP settings saved.');
    redirect('index.php?page=smtp');
}

//─── SMTP TEST (AJAX) ───────────────────────────────────────────────────── */
if ($action === 'test_smtp') {
    header('Content-Type: application/json');
    requireAdmin();
    require_once BASE_PATH . '/includes/mailer.php';
    // Temporarily override settings with POSTed values
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

// ─── USER VERIFICATION TOGGLE ──────────────────────────────────────────── */
if ($action === 'update_user_status') {
    requireAdmin();
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

// ─── DELETE USER ─────────────────────────────────────────────────────── */
if ($action === 'delete_user') {
    requireAdmin();
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
        $clientIds = $db->prepare("SELECT id FROM clients WHERE user_id=?")->execute([$uid])
            ? array_column($db->prepare("SELECT id FROM clients WHERE user_id=?")->execute([$uid]) ? (function() use ($db,$uid) {
                $s=$db->prepare("SELECT id FROM clients WHERE user_id=?");$s->execute([$uid]);return $s->fetchAll();})() : [], 'id')
            : [];
        // Simpler: use subquery deletes
        $db->prepare("DELETE cs FROM client_selections cs
                      JOIN clients c ON cs.client_id=c.id WHERE c.user_id=?")->execute([$uid]);
        $db->prepare("DELETE FROM clients WHERE user_id=?")->execute([$uid]);
        $db->prepare("DELETE FROM shortlist WHERE user_id=?")->execute([$uid]);
        $db->prepare("DELETE FROM inquiries WHERE user_id=?")->execute([$uid]);
        $db->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
        $db->commit();
        flash('toast', 'User and all related data deleted.');
    } catch (Throwable $e) {
        $db->rollBack();
        flash('error', 'Delete failed: ' . $e->getMessage());
    }
    redirect('index.php?page=users');
}

  
 //  import csv file product 
  //  if ($action === 'import_excel') {
    //    importCSV($_FILES['excel_file'] ?? null);
   //     redirect('index.php?page=products');
 //   }
  	if ($_POST['action'] === 'sync_photos') {
    syncPhotosFromDirectory();
      redirect('index.php?page=products');
    }
 	 if ($_POST['action']=='sync_measurements'){
    syncMeasurementSheetsfromdirectory();
     redirect('index.php?page=products');
     }

		if ($_POST['action']=='sync_dna'){
    syncDNAReportsfromdirectory();
          redirect('index.php?page=products');
        }
  
 	 if($_POST['action']=='export'){
    exportExcel();
    redirect('index.php?page=products');
	}

		if($_POST['action']=='import'){
 	   importExcel($_FILES['xls_file'] ?? null);
 	  redirect('index.php?page=products');
		}
   
     if ($action === 'import_photos') {
        importPhotos($_FILES['photo_zip'] ?? null);
        redirect('index.php?page=products');
    }
}
  
// ── AJAX Sync endpoint ─────────────────────────────────────────────────────
if (isset($_GET["ajax_sync"]) && isAdmin()) {
    header("Content-Type: application/json");
    $step = (int)($_GET["ajax_sync"]);
    echo json_encode(runSyncStep($step));
    exit;
}

// ── AJAX WhatsApp preview endpoint ─────────────────────────────────────
    if (isset($_GET['wa_preview']) && isAdmin()) {
        require_once BASE_PATH . '/includes/wa_share.php';
        handleWaPreviewAjax(); // outputs JSON + exits
    }

// ── Routing ───────────────────────────────────────────────────────────────────
$page = preg_replace('/[^a-z_]/', '', $_GET['page'] ?? 'dashboard');

if (!isAdmin()) {
    $adminError = $_SESSION['admin_error'] ?? null;
    unset($_SESSION['admin_error']);
    include __DIR__ . '/views/login.php';
    exit;
}



// ════════════════════════════════════════════════════════════════════════════
// ── Functions
// ════════════════════════════════════════════════════════════════════════════

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
    ];

    // Measurement sheet — keep original filename
    if (!empty($files['measurement_sheet']['name'])) {
        $fn = basename($files['measurement_sheet']['name']);
        if (move_uploaded_file($files['measurement_sheet']['tmp_name'], MEASUREMENT_DIR.'/'.$fn)) {
            $fields['measurement_sheet'] = $fn;
        }
    }

    // DNA report — keep original filename
    if (!empty($files['dna_report']['name'])) {
        $fn = basename($files['dna_report']['name']);
        if (move_uploaded_file($files['dna_report']['tmp_name'], DNA_DIR.'/'.$fn)) {
            $fields['dna_report'] = $fn;
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
            $fn = basename(trim($origName));
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


// ── Photo Import (multi-file by quarry prefix) ────────────────────────────────
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
        $fn = basename(trim($origName));

        // extension check
        $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg','jpeg','png','webp']))
            continue;

        // Extract Q23048 from:
        // Q23048-IMG.jpeg
        // Q23048-IMG-1.jpeg
        // Q23048-IMG-2.jpg

        if (!preg_match('/^(Q\d+)/i', $fn, $m))
            continue;

        $quarry = strtoupper($m[1]);

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
    if (preg_match('/^(.+?)-IMG/i', $stem, $m)) {
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
// ── Sync Step Runner ──────────────────────────────────────────────────────────
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

//--------- sync photo directory -----------------------------------

function syncPhotosFromDirectory(): void
{
    $db = getDB();
    $count = 0;

    if (!is_dir(PHOTOS_DIR)) {
        flash('error', 'Photo directory not found.');
        return;
    }

    // recursive iterator
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            PHOTOS_DIR,
            RecursiveDirectoryIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $fileObj) {

        if (!$fileObj->isFile()) {
            continue;
        }

        $fullPath = $fileObj->getPathname();

        // relative path from photos dir
        $relativePath = str_replace(
            PHOTOS_DIR . '/',
            '',
            $fullPath
        );

        $file = $fileObj->getFilename();

        // extension check
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            continue;
        }

        /*
        Supported formats:

        Q2323-3333-img-1.jpg
        Q555-33333-IMG.jpg
        Q3336-W994-IMG-2.jpg
        */

        if (!preg_match('/^(.+?)(?:-img)?(?:-\d+)?\.(jpg|jpeg|png|webp)$/i', $file, $m)) {
    continue;
}

        // quarry number before -IMG
        $quarry = strtoupper(trim($m[1]));

        // find product
        $st = $db->prepare("
            SELECT id
            FROM products
            WHERE quarry_number = ?
            LIMIT 1
        ");

        $st->execute([$quarry]);

        $prod = $st->fetch(PDO::FETCH_ASSOC);

        if (!$prod) {
            continue;
        }

        // duplicate check
        $chk = $db->prepare("
            SELECT id
            FROM product_photos
            WHERE product_id = ?
            AND filename = ?
        ");

        $chk->execute([
            $prod['id'],
            $relativePath
        ]);

        if ($chk->fetch()) {
            continue;
        }

        // next sort order
        $ord = $db->prepare("
            SELECT COALESCE(MAX(sort_order),0)
            FROM product_photos
            WHERE product_id = ?
        ");

        $ord->execute([$prod['id']]);

        $order = ((int)$ord->fetchColumn()) + 1;

        // insert
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
            $relativePath,
            $order
        ]);

        $count++;
    }

    flash(
        'toast',
        $count . ' photos synced successfully.'
    );
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
// exportCSV() — replace existing function
// ════════════════════════════════════════════════════════════════════════════
function exportCSV(): void {
    $db = getDB();
    $products = $db->query("SELECT * FROM products ORDER BY id ASC")->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="bafna_products_'.date('Ymd').'.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

    $headers = ['name','category','subcategory','color_subcategory','quarry_number',
                'total_quantity','quantity_available','quantity_on_hold','pieces',
                'thickness','sizes_l','sizes_h','cutter_size_l','cutter_size_h',
                'origin','finish','description',
                'in_stock','featured','measurement_sheet','dna_report'];
    fputcsv($out, $headers);

    foreach ($products as $p) {
        $row = array_map(fn($k) => $p[$k] ?? '', $headers);
        fputcsv($out, $row);
    }
    fclose($out);
}

// ════════════════════════════════════════════════════════════════════════════
// importCSV() — replace existing function
// ════════════════════════════════════════════════════════════════════════════
function importCSV(?array $file): void {
    if (!$file || $file['error']) { flash('error','File upload failed.'); return; }
    $fn  = $file['name'];
    $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
    if ($ext !== 'csv') { flash('error','Only CSV files are supported.'); return; }

    $dest = EXCEL_DIR.'/'.time().'_'.$fn;
    if (!move_uploaded_file($file['tmp_name'], $dest)) { flash('error','Could not save file.'); return; }

    $handle = fopen($dest, 'r');
    if (!$handle) { flash('error','Could not read file.'); return; }

    $headers = array_map('trim', fgetcsv($handle) ?: []);
    $db      = getDB();
    $count   = 0;

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < 2) continue;
        $data = array_combine(array_slice($headers, 0, count($row)), $row) ?: [];
        $g    = fn($k) => trim($data[$k] ?? '');
        $gf   = fn($k) => (float)trim($data[$k] ?? 0);
        $gi   = fn($k) => (int)trim($data[$k] ?? 0);

        $quarry = $g('quarry_number') ?: $g('Quarry Number') ?: $g('quarry');
        $name   = $g('name') ?: $g('Name') ?: $g('Product Name');
        if (!$name) continue;

        $measurement = $g('measurement_sheet') ?: $g('Measurement Sheet');
        $dna         = $g('dna_report')        ?: $g('DNA Report');

        // Support both old single-column and new split columns in CSV
        // Old: cutter_size = "104x34" → auto-split; new: cutter_size_l + cutter_size_h
        $csL = $g('cutter_size_l');
        $csH = $g('cutter_size_h');
        if ($csL === '' && $csH === '') {
            // Try legacy column
            $old = $g('cutter_size') ?: $g('Cutter Size');
            if ($old !== '') {
                $parts = preg_split('/[x×]/i', $old);
                $csL   = trim($parts[0] ?? '');
                $csH   = trim($parts[1] ?? '');
            }
        }

        $szL = $g('sizes_l');
        $szH = $g('sizes_h');
        if ($szL === '' && $szH === '') {
            $old = $g('sizes') ?: $g('Sizes');
            if ($old !== '') {
                $parts = preg_split('/[x×]/i', $old);
                $szL   = trim($parts[0] ?? '');
                $szH   = trim($parts[1] ?? '');
            }
        }

        $fields = [
            'name'               => $name,
            'category'           => $g('category')          ?: $g('Category'),
            'subcategory'        => $g('subcategory')        ?: $g('Sub Category'),
            'color_subcategory'  => $g('color_subcategory')  ?: $g('Color'),
            'quarry_number'      => $quarry,
            'total_quantity'     => $gf('total_quantity')    ?: $gf('Total Quantity'),
            'quantity_available' => $gf('quantity_available') ?: $gf('Quantity Available'),
            'quantity_on_hold'   => $gf('quantity_on_hold')   ?: $gf('Quantity On Hold'),
            'pieces'             => $gi('pieces')            ?: $gi('Pieces'),
            'thickness'          => $g('thickness')          ?: $g('Thickness'),
            'sizes_l'            => $szL,
            'sizes_h'            => $szH,
            'cutter_size_l'      => $csL,
            'cutter_size_h'      => $csH,
            'origin'             => $g('origin')             ?: $g('Origin'),
            'finish'             => $g('finish')             ?: $g('Finish'),
            'description'        => $g('description')        ?: $g('Description'),
            'in_stock'           => $gi('in_stock')          ?: $gi('In Stock')    ?: 1,
            'featured'           => $gi('featured')          ?: $gi('Featured'),
            'measurement_sheet'  => $measurement,
            'dna_report'         => $dna,
        ];

        $existing = null;
        if ($quarry) {
            $st = $db->prepare("SELECT id FROM products WHERE quarry_number=?");
            $st->execute([$quarry]);
            $existing = $st->fetch();
        }

        if ($existing) {
            $set  = implode(',', array_map(fn($k) => "$k=?", array_keys($fields)));
            $vals = array_values($fields);
            $vals[] = $existing['id'];
            $db->prepare("UPDATE products SET $set WHERE id=?")->execute($vals);
        } else {
            $cols = implode(',', array_keys($fields));
            $phs  = implode(',', array_fill(0, count($fields), '?'));
            $db->prepare("INSERT INTO products ($cols) VALUES ($phs)")->execute(array_values($fields));
        }
        $count++;
    }
    fclose($handle);
    flash('toast', "Import complete. $count products processed.");
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
/**
 * PATCH — admin/index.php
 *
 * Replace the existing importExcel() function (the one that starts with
 *   "function importExcel(?array $file): void {"
 * and ends with its closing "}" ) with the function below.
 *
 * No other file needs to change.
 * The flash() call at the bottom of the POST handler that calls importExcel()
 * is now handled inside the function itself, so the redirect still works fine.
 */

// ════════════════════════════════════════════════════════════════════════════
// importExcel() — full sync: insert new, update existing, delete orphans
// ════════════════════════════════════════════════════════════════════════════
function importExcel(?array $file): void {

    // ── 1. Basic file validation ──────────────────────────────────────────
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

    // ── 2. Load spreadsheet ───────────────────────────────────────────────
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

    // ── 3. Parse headers ──────────────────────────────────────────────────
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
        'Net Useable Size L' => 'sizes_l',
        'Net Useable Size H' => 'sizes_h',
        'Italian Size L'     => 'cutter_size_l',
        'Italian Size H'     => 'cutter_size_h',
    ];

    $headers = [];
    foreach ($excelHeaders as $h) {
        $clean     = trim($h);
        $headers[] = $headerMap[$clean] ?? strtolower(str_replace(' ', '_', $clean));
    }

    // ── 4. Collect & validate rows ────────────────────────────────────────
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
            'category'           => $g('category'),
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

    // ── 5. Load existing products from DB ─────────────────────────────────
    $db = getDB();

    $existing = $db
        ->query("SELECT id, quarry_number FROM products")
        ->fetchAll(\PDO::FETCH_ASSOC);

    // Map quarry_number (uppercase) => id
    $dbQuarryMap = [];
    foreach ($existing as $row) {
        $dbQuarryMap[strtoupper($row['quarry_number'])] = (int)$row['id'];
    }

    // ── 6. Determine add / update / delete sets ───────────────────────────
    $importedSet = array_keys($importedQuarries);          // quarries in file
    $dbSet       = array_keys($dbQuarryMap);               // quarries in DB

    $toInsert = array_diff($importedSet, $dbSet);          // new
    $toUpdate = array_intersect($importedSet, $dbSet);     // existing
    $toDelete = array_diff($dbSet, $importedSet);          // orphaned

    $countAdded   = 0;
    $countUpdated = 0;
    $countDeleted = 0;

    // ── 7. Run everything inside a transaction ────────────────────────────
    try {
        $db->beginTransaction();

        // ── 7a. DELETE orphaned products ──────────────────────────────────
        foreach ($toDelete as $quarry) {
            $pid = $dbQuarryMap[$quarry];
            _deleteProductWithDependencies($db, $pid);
            $countDeleted++;
        }

        // ── 7b. UPDATE existing products ──────────────────────────────────
        foreach ($toUpdate as $quarry) {
            $pid    = $dbQuarryMap[$quarry];
            $fields = $importedQuarries[$quarry];

            $set  = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
            $vals = array_values($fields);
            $vals[] = $pid;
            $db->prepare("UPDATE products SET $set WHERE id = ?")->execute($vals);
            $countUpdated++;
        }

        // ── 7c. INSERT new products ───────────────────────────────────────
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

    // ── 8. Notification & summary flash ──────────────────────────────────
    createNotification(
        'Inventory Synced',
        "Import complete — {$countAdded} added, {$countUpdated} updated, {$countDeleted} deleted.",
        'product'
    );

    $summary = "Sync complete: {$countAdded} added · {$countUpdated} updated · {$countDeleted} deleted.";
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

// ════════════════════════════════════════════════════════════════════════════
// _deleteProductWithDependencies()
// Removes a product and ALL dependent data/files. Used by importExcel().
// Also add this as a standalone helper so delete_product POST action can
// call it too (optional — it replaces the inline delete logic there).
// ════════════════════════════════════════════════════════════════════════════
function _deleteProductWithDependencies(\PDO $db, int $pid): void {

    // 1. Collect photo filenames and delete files from disk
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

    // 3. Delete dependent DB rows (explicit — avoids relying on FK CASCADE
    //    being configured correctly on all hosts)
    $db->prepare("DELETE FROM product_photos    WHERE product_id = ?")->execute([$pid]);
    $db->prepare("DELETE FROM shortlist         WHERE product_id = ?")->execute([$pid]);
    $db->prepare("DELETE FROM client_selections WHERE product_id = ?")->execute([$pid]);
    // 4. Delete the product itself
    $db->prepare("DELETE FROM products WHERE id = ?")->execute([$pid]);
}

// ── Step 1: Sync Photos from /assets/uploads/photos/ ─────────────────────────
function syncImages(): array {

    $db = getDB();

    $result = [
        'step'    => 1,
        'label'   => 'Photos',
        'found'   => 0,
        'synced'  => 0,
        'skipped' => 0,
        'errors'  => [],
        'done'    => false
    ];

    if (!is_dir(PHOTOS_DIR)) {
        $result['errors'][] = 'Photos directory not found: ' . PHOTOS_DIR;
        $result['done'] = true;
        return $result;
    }

    $allowed = ['jpg','jpeg','png','webp'];

    // Scan color folders
    $colorFolders = array_diff(scandir(PHOTOS_DIR), ['.', '..']);

    foreach ($colorFolders as $colorFolder) {

        $colorPath = PHOTOS_DIR . '/' . $colorFolder;

        if (!is_dir($colorPath)) {
            continue;
        }

        $files = array_diff(scandir($colorPath), ['.', '..']);

        foreach ($files as $file) {

            $fullPath = $colorPath . '/' . $file;

            if (!is_file($fullPath)) {
                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                continue;
            }

            $result['found']++;

            // Example:
            // Q228-IMG.jpg
            // Q23048-IMG-1.jpg
            // A9993-W998899-IMG-1.jpg

            $stem   = pathinfo($file, PATHINFO_FILENAME);
            $quarry = parseQuarryFromFilename($stem);

            if (!$quarry) {
                $result['skipped']++;
                $result['errors'][] = "Cannot parse quarry from: $file";
                continue;
            }

            // Match by quarry 
            $st = $db->prepare("
                SELECT id
                FROM products
                WHERE quarry_number = ?
                LIMIT 1
            ");

            $st->execute([$quarry]);

            $prod = $st->fetch();

            if (!$prod) {
                $result['skipped']++;
                $result['errors'][] =
                    "No product for quarry '$quarry' in color '$colorFolder' ($file)";
                continue;
            }

            // Save relative path
            $relativePath = $colorFolder . '/' . $file;

            // Skip duplicates
            $chk = $db->prepare("
                SELECT id
                FROM product_photos
                WHERE product_id = ?
                AND filename = ?
            ");

            $chk->execute([
                $prod['id'],
                $relativePath
            ]);

            if ($chk->fetch()) {
                $result['skipped']++;
                continue;
            }

            // Next sort order
            $ord = $db->prepare("
                SELECT COALESCE(MAX(sort_order),0) as m
                FROM product_photos
                WHERE product_id = ?
            ");

            $ord->execute([$prod['id']]);

            $order = (int)$ord->fetch()['m'] + 1;

            // Insert
            $db->prepare("
                INSERT INTO product_photos
                (product_id, filename, sort_order)
                VALUES (?, ?, ?)
            ")->execute([
                $prod['id'],
                $relativePath,
                $order
            ]);

            $result['synced']++;
        }
    }

    $result['done'] = true;

    return $result;
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
// ── Step 3: Sync DNA Reports from /assets/uploads/dna_reports/ ───────────────
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
          'notifications','logo','user_clients','admin_selections','smtp'];
$file  = in_array($page, $pages)
       ? __DIR__ . '/views/' . $page . '.php'
       : __DIR__ . '/views/dashboard.php';
include $file;