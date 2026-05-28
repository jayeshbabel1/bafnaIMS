<?php
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


// ── CSV Export (before any output) ───────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'export_csv' && isAdmin()) {
    exportCSV();
    exit;
}

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

    if ($action === 'save_product') {
        saveProduct($_POST, $_FILES);
        redirect('index.php?page=products');
    }

    if ($action === 'delete_product') {
        $pid = (int)($_POST['product_id'] ?? 0);
        // Also delete photos from disk
        $st = getDB()->prepare("SELECT filename FROM product_photos WHERE product_id=?");
        $st->execute([$pid]);
        foreach ($st->fetchAll() as $ph) @unlink(PHOTOS_DIR.'/'.$ph['filename']);
        getDB()->prepare("DELETE FROM products WHERE id=?")->execute([$pid]);
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
  

    if ($action === 'import_excel') {
        importCSV($_FILES['excel_file'] ?? null);
        redirect('index.php?page=products');
    }
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
  
  if ($action === 'reply_inquiry') {

    try {

        $iid   = (int)($_POST['inquiry_id'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');

        if (!$iid || $reply === '') {
            flash('error', 'Invalid request');
            redirect('index.php?page=inquiries');
        }

        $db = getDB();

        // safer query (no subject dependency unless it exists)
        $st = $db->prepare("
            SELECT 
                u.email,
                u.name AS user_name,
                p.name AS product_name,
                p.quarry_number
            FROM inquiries i
            JOIN users u ON u.id = i.user_id
            LEFT JOIN products p ON p.id = i.product_id
            WHERE i.id = ?
        ");

        $st->execute([$iid]);
        $data = $st->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            flash('error', 'Inquiry not found');
            redirect('index.php?page=inquiries');
        }

        // update
        $db->prepare("
            UPDATE inquiries 
            SET admin_reply = ?, status = 'replied' 
            WHERE id = ?
        ")->execute([$reply, $iid]);

        // subject build
        $productLabel = trim(($data['product_name'] ?? '') . ' - ' . ($data['quarry_number'] ?? ''));
        $subject = "Reply: " . $productLabel;

        $message = "
            Hello " . htmlspecialchars($data['user_name'] ?? 'User') . ",<br><br>
            <b> We got your enqiry about <b>Product:</b> {$productLabel}<br><br>
            <b>Reply:</b><br>" . nl2br(htmlspecialchars($reply)) . "
            <br><br>Regards,<br>Support Team
        ";

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: no-reply@yourdomain.com\r\n";

        mail($data['email'], $subject, $message, $headers);

        flash('toast', 'Reply sent successfully.');
        redirect('index.php?page=inquiries');

    } catch (Throwable $e) {
        error_log("reply_inquiry error: " . $e->getMessage());
        flash('error', 'Something went wrong while sending reply.');
        redirect('index.php?page=inquiries');
    }
}

}
  
// ── AJAX Sync endpoint ─────────────────────────────────────────────────────
if (isset($_GET["ajax_sync"]) && isAdmin()) {
    header("Content-Type: application/json");
    $step = (int)($_GET["ajax_sync"]);
    echo json_encode(runSyncStep($step));
    exit;
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
        'sizes'             => trim($data['sizes']             ?? ''),
        'cutter_size'       => trim($data['cutter_size']       ?? ''),
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

    $names  = is_array($files['photos']['name'])
        ? $files['photos']['name']
        : [$files['photos']['name']];

    $tmps = is_array($files['photos']['tmp_name'])
        ? $files['photos']['tmp_name']
        : [$files['photos']['tmp_name']];

    $errors = is_array($files['photos']['error'])
        ? $files['photos']['error']
        : [$files['photos']['error']];

    // get next sort order
    $st = $db->prepare("
        SELECT COALESCE(MAX(sort_order),0)
        FROM product_photos
        WHERE product_id=?
    ");

    $st->execute([$pid]);

    $order = ((int)$st->fetchColumn()) + 1;

    foreach ($names as $i => $origName) {

        if (($errors[$i] ?? 1) !== UPLOAD_ERR_OK)
            continue;

        if (empty($tmps[$i]))
            continue;

        $fn = basename(trim($origName));

        // avoid duplicate DB row
        $chk = $db->prepare("
            SELECT id
            FROM product_photos
            WHERE product_id=?
            AND filename=?
        ");

        $chk->execute([
            $pid,
            $fn
        ]);

        if ($chk->fetch()) {
            continue;
        }

        // move with original filename
        if (!move_uploaded_file(
            $tmps[$i],
            PHOTOS_DIR.'/'.$fn
        )) {
            continue;
        }

        $db->prepare("
            INSERT INTO product_photos
            (
                product_id,
                filename,
                sort_order
            )
            VALUES (?,?,?)
        ")->execute([
            $pid,
            $fn,
            $order++
        ]);
    }
}
syncPhotosFromDirectory();
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
        flash('error','Photo directory not found.');
        return;
    }

    $files = scandir(PHOTOS_DIR);

    foreach ($files as $file) {

        // skip . and ..
        if ($file === '.' || $file === '..')
            continue;

        $fullPath = PHOTOS_DIR.'/'.$file;

        if (!is_file($fullPath))
            continue;

        // extension check
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg','jpeg','png','webp']))
            continue;

        /*
        Supports:
        Q23048-IMG.jpeg
        Q23048-IMG-1.jpeg
        Q23048-IMG-2.jpg
        */

        if (!preg_match('/^(Q\d+)/i', $file, $m))
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

        // avoid duplicate DB entry
        $chk = $db->prepare("
            SELECT id
            FROM product_photos
            WHERE product_id=?
            AND filename=?
        ");

        $chk->execute([
            $prod['id'],
            $file
        ]);

        if ($chk->fetch())
            continue;

        // next sort order
        $ord = $db->prepare("
            SELECT COALESCE(MAX(sort_order),0)
            FROM product_photos
            WHERE product_id=?
        ");

        $ord->execute([$prod['id']]);

        $order = ((int)$ord->fetchColumn()) + 1;

        // save record
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
            $file,
            $order
        ]);

        $count++;
    }

    flash(
        'toast',
        $count.' photos synced successfully.'
    );
}

//----------- Sync Measurement Sheet from directory ---------------------------------
function syncMeasurementSheetsfromdirectory(): void
{
    $db = getDB();
    $count = 0;

    if (!is_dir(MEASUREMENT_DIR)) {
        flash('error','Measurement folder missing');
        return;
    }

    foreach (scandir(MEASUREMENT_DIR) as $file) {

        if ($file=='.' || $file=='..')
            continue;

        $full = MEASUREMENT_DIR.'/'.$file;

        if (!is_file($full))
            continue;

        if (strtolower(pathinfo($file,PATHINFO_EXTENSION))!='pdf')
            continue;

        // Q23048-MS.pdf
        if (!preg_match('/^(Q\d+)-MS/i',$file,$m))
            continue;

        $quarry = strtoupper($m[1]);

        $st = $db->prepare("
            UPDATE products
            SET measurement_sheet=?
            WHERE quarry_number=?
        ");

        $st->execute([
            $file,
            $quarry
        ]);

        $count += $st->rowCount();
    }

    flash(
        'toast',
        $count.' measurement sheet(s) synced'
    );
}

//------- Sync Dna reports --------------------------------
function syncDNAReportsfromdirectory(): void
{
    $db = getDB();
    $count = 0;

    if (!is_dir(DNA_DIR)) {
        flash('error','DNA folder missing');
        return;
    }

    foreach (scandir(DNA_DIR) as $file) {

        if ($file=='.' || $file=='..')
            continue;

        $full = DNA_DIR.'/'.$file;

        if (!is_file($full))
            continue;

        if (strtolower(pathinfo($file,PATHINFO_EXTENSION))!='pdf')
            continue;

        // Q23048-DNA.pdf
        if (!preg_match('/^(Q\d+)-DNA/i',$file,$m))
            continue;

        $quarry = strtoupper($m[1]);

        $st = $db->prepare("
            UPDATE products
            SET dna_report=?
            WHERE quarry_number=?
        ");

        $st->execute([
            $file,
            $quarry
        ]);

        $count += $st->rowCount();
    }

    flash(
        'toast',
        $count.' DNA report(s) synced'
    );
}

// ── CSV ────────────────────────────────────────────────────────────────
function exportCSV(): void {
    $db = getDB();
    $products = $db->query("SELECT * FROM products ORDER BY id ASC")->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="bafna_products_'.date('Ymd').'.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

    $headers = ['name','category','subcategory','color_subcategory','quarry_number',
                'total_quantity','quantity_available','quantity_on_hold','pieces',
                'thickness','sizes','cutter_size','origin','finish','description',
                'in_stock','featured','measurement_sheet','dna_report'];
    fputcsv($out, $headers);

    foreach ($products as $p) {
        $row = array_map(fn($k) => $p[$k] ?? '', $headers);
        fputcsv($out, $row);
    }
    fclose($out);
}
// ── CSV Import ────────────────────────────────────────────────────────────────
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

        // Try multiple header name variants
        $quarry = $g('quarry_number') ?: $g('Quarry Number') ?: $g('quarry');
        $name   = $g('name') ?: $g('Name') ?: $g('Product Name');
        if (!$name) continue;

        $measurement = $g('measurement_sheet') ?: $g('Measurement Sheet');
        $dna         = $g('dna_report')        ?: $g('DNA Report');

        $fields = [
            'name'               => $name,
            'category'           => $g('category')        ?: $g('Category'),
            'subcategory'        => $g('subcategory')      ?: $g('Sub Category'),
            'color_subcategory'  => $g('color_subcategory')?: $g('Color'),
            'quarry_number'      => $quarry,
            'total_quantity'     => $gf('total_quantity')  ?: $gf('Total Quantity'),
            'quantity_available' => $gf('quantity_available') ?: $gf('Quantity Available'),
            'quantity_on_hold'   => $gf('quantity_on_hold')   ?: $gf('Quantity On Hold'),
            'pieces'             => $gi('pieces')          ?: $gi('Pieces'),
            'thickness'          => $g('thickness')        ?: $g('Thickness'),
            'sizes'              => $g('sizes')            ?: $g('Sizes'),
            'cutter_size'        => $g('cutter_size')      ?: $g('Cutter Size'),
            'origin'             => $g('origin')           ?: $g('Origin'),
            'finish'             => $g('finish')           ?: $g('Finish'),
            'description'        => $g('description')      ?: $g('Description'),
            'in_stock'           => $gi('in_stock')        ?: $gi('In Stock')        ?: 1,
            'featured'           => $gi('featured')        ?: $gi('Featured'),
            'measurement_sheet'  => $measurement,
            'dna_report'         => $dna,
        ];

        // Upsert by quarry_number
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


// ── EXCEL Export (.xlsx) ─────────────────────────────────────
function exportExcel(): void {

    try {

        // Prevent accidental output
        if (ob_get_length()) {
            ob_end_clean();
        }

        $db = getDB();

        $products = $db->query(
            "SELECT * FROM products ORDER BY id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);


        $headers = [
            'name',
            'category',
            'subcategory',
            'color_subcategory',
            'quarry_number',
            'total_quantity',
            'quantity_available',
            'quantity_on_hold',
            'pieces',
            'thickness',
            'sizes',
            'cutter_size',
            'origin',
            'finish',
            'description',
            'in_stock',
            'featured',
            'measurement_sheet',
            'dna_report'
        ];


        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();


        // Header row
        foreach ($headers as $col => $header) {

            $column =
                Coordinate::stringFromColumnIndex(
                    $col + 1
                );

            $sheet->setCellValue(
                $column . '1',
                $header
            );
        }


        // Data rows
        $rowNo = 2;

        foreach ($products as $p) {

            foreach ($headers as $col => $key) {

                $column =
                    Coordinate::stringFromColumnIndex(
                        $col + 1
                    );

                $sheet->setCellValue(
                    $column . $rowNo,
                    $p[$key] ?? ''
                );
            }

            $rowNo++;
        }


        // Auto width
        $lastCol =
            Coordinate::stringFromColumnIndex(
                count($headers)
            );

        foreach (range('A', $lastCol) as $col) {

            $sheet
                ->getColumnDimension($col)
                ->setAutoSize(true);
        }


        // Download headers
        header(
            'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        header(
            'Content-Disposition: attachment; filename="bafna_products_'
            .date('Ymd').
            '.xlsx"'
        );

        header('Cache-Control: max-age=0');
        header('Pragma: public');
        header('Expires: 0');


        $writer = new Xlsx($spreadsheet);

        $writer->save('php://output');

        exit;

    } catch (Throwable $e) {

        die(
            '<pre>'
            .'Excel Export Error:'."\n\n"
            .$e->getMessage()."\n\n"
            .'File: '.$e->getFile()."\n"
            .'Line: '.$e->getLine()
            .'</pre>'
        );
    }
}
// ── EXCEL Import (.xlsx/.xls/.csv) ──────────────────────────
function importExcel(?array $file): void {

    if (!$file || $file['error']) {
        flash('error','File upload failed.');
        return;
    }

    $ext = strtolower(
        pathinfo($file['name'], PATHINFO_EXTENSION)
    );

    if (!in_array($ext,['xlsx','xls','csv'])) {
        flash(
            'error',
            'Only .xlsx, .xls or .csv allowed'
        );
        return;
    }

    $dest = EXCEL_DIR.'/'.time().'_'.$file['name'];

    if (!move_uploaded_file(
        $file['tmp_name'],
        $dest
    )) {
        flash('error','Could not save upload');
        return;
    }

    try {

        $spreadsheet =
            IOFactory::load($dest);

        $sheet =
            $spreadsheet->getActiveSheet();

        // simpler structure
        $rows = $sheet->toArray();

        if (count($rows) < 2) {
            flash('error','No rows found');
            return;
        }

        // normalize exported headers
        $headers = array_map(
            function($h){

                return strtolower(
                    trim($h)
                );

            },
            array_shift($rows)
        );


        $db = getDB();
        $count=0;


        foreach($rows as $row){

            if(
                empty(
                    array_filter($row)
                )
            ){
                continue;
            }

            $data = array_combine(
                $headers,
                $row
            );

            if(!$data){
                continue;
            }

            $g=function($k) use($data){

                return trim(
                    (string)($data[$k] ?? '')
                );
            };

            $gf=function($k) use($data){

                return (float)(
                    $data[$k] ?? 0
                );
            };

            $gi=function($k) use($data){

                return (int)(
                    $data[$k] ?? 0
                );
            };


            $name=$g('name');

            if(!$name){
                continue;
            }

            $quarry=
                $g('quarry_number');


            $fields=[

                'name'=>$name,

                'category'=>
                    $g('category'),

                'subcategory'=>
                    $g('subcategory'),

                'color_subcategory'=>
                    $g('color_subcategory'),

                'quarry_number'=>
                    $quarry,

                'total_quantity'=>
                    $gf('total_quantity'),

                'quantity_available'=>
                    $gf('quantity_available'),

                'quantity_on_hold'=>
                    $gf('quantity_on_hold'),

                'pieces'=>
                    $gi('pieces'),

                'thickness'=>
                    $g('thickness'),

                'sizes'=>
                    $g('sizes'),

                'cutter_size'=>
                    $g('cutter_size'),

                'origin'=>
                    $g('origin'),

                'finish'=>
                    $g('finish'),

                'description'=>
                    $g('description'),

                'in_stock'=>
                    $gi('in_stock') ?: 1,

                'featured'=>
                    $gi('featured'),

                'measurement_sheet'=>
                    $g('measurement_sheet'),

                'dna_report'=>
                    $g('dna_report')
            ];


            // Upsert
            $existing=null;

            if($quarry){

                $st=$db->prepare(
                    "SELECT id
                     FROM products
                     WHERE quarry_number=?"
                );

                $st->execute([$quarry]);

                $existing=
                    $st->fetch(
                        PDO::FETCH_ASSOC
                    );
            }


            if($existing){

                $set=implode(
                    ',',
                    array_map(
                        fn($k)=>"$k=?",
                        array_keys($fields)
                    )
                );

                $vals=array_values(
                    $fields
                );

                $vals[]=
                    $existing['id'];

                $db->prepare(
                    "UPDATE products
                     SET $set
                     WHERE id=?"
                )->execute(
                    $vals
                );

            }else{

                $cols=implode(
                    ',',
                    array_keys($fields)
                );

                $ph=implode(
                    ',',
                    array_fill(
                        0,
                        count($fields),
                        '?'
                    )
                );

                $db->prepare(
                    "INSERT INTO products
                    ($cols)
                    VALUES
                    ($ph)"
                )->execute(
                    array_values(
                        $fields
                    )
                );
            }

            $count++;
        }

        flash(
            'toast',
            "Import complete. $count products processed."
        );

    } catch(Throwable $e){

        flash(
            'error',
            $e->getMessage()
        );
    }
}


// ── Step 1: Sync Photos from /assets/uploads/photos/ ─────────────────────────
function syncImages(): array {
    $db      = getDB();
    $result  = ['step'=>1,'label'=>'Photos','found'=>0,'synced'=>0,'skipped'=>0,'errors'=>[],'done'=>false];

    if (!is_dir(PHOTOS_DIR)) {
        $result['errors'][] = 'Photos directory not found: ' . PHOTOS_DIR;
        $result['done'] = true;
        return $result;
    }

    $allowed = ['jpg','jpeg','png','webp'];
    $files   = array_diff(scandir(PHOTOS_DIR), ['.','..']);

    foreach ($files as $file) {
        $fullPath = PHOTOS_DIR . '/' . $file;
        if (!is_file($fullPath)) continue;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) continue;

        $result['found']++;

        // Parse quarry number using shared helper
        $stem   = pathinfo($file, PATHINFO_FILENAME);
        $quarry = parseQuarryFromFilename($stem);

        if (!$quarry) {
            $result['skipped']++;
            $result['errors'][] = "Cannot parse quarry from: $file";
            continue;
        }

        // Find product by quarry number
        $st = $db->prepare("SELECT id FROM products WHERE quarry_number = ?");
        $st->execute([$quarry]);
        $prod = $st->fetch();

        if (!$prod) {
            $result['skipped']++;
            $result['errors'][] = "No product for quarry '$quarry' ($file)";
            continue;
        }

        // Skip if already linked
        $chk = $db->prepare("SELECT id FROM product_photos WHERE product_id=? AND filename=?");
        $chk->execute([$prod['id'], $file]);
        if ($chk->fetch()) {
            $result['skipped']++;
            continue;
        }

        // Get next sort order
        $ord = $db->prepare("SELECT COALESCE(MAX(sort_order),0) as m FROM product_photos WHERE product_id=?");
        $ord->execute([$prod['id']]);
        $order = (int)$ord->fetch()['m'] + 1;

        $db->prepare("INSERT INTO product_photos (product_id,filename,sort_order) VALUES (?,?,?)")
           ->execute([$prod['id'], $file, $order]);

        $result['synced']++;
    }

    $result['done'] = true;
    return $result;
}

// ── Step 2: Sync Measurement Sheets from /assets/uploads/measurement_sheets/ ─
// File naming: Q23048-MS.pdf  or  Q23048-MS-1.pdf
function syncMeasurementSheets(): array {
    $db     = getDB();
    $result = ['step'=>2,'label'=>'Measurement Sheets','found'=>0,'synced'=>0,'skipped'=>0,'errors'=>[],'done'=>false];

    if (!is_dir(MEASUREMENT_DIR)) {
        $result['errors'][] = 'Measurement sheets directory not found.';
        $result['done'] = true;
        return $result;
    }

    $files = array_diff(scandir(MEASUREMENT_DIR), ['.','..']);

    foreach ($files as $file) {
        $fullPath = MEASUREMENT_DIR . '/' . $file;
        if (!is_file($fullPath)) continue;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') continue;

        $result['found']++;
        $stem   = pathinfo($file, PATHINFO_FILENAME); // e.g. Q23048-MS or Q23048-MS-2

        // Strip -MS suffix and optional trailing number to get quarry
        // Q23048-MS.pdf → Q23048
        // Q23048-MS-1.pdf → Q23048
        if (preg_match('/^(.+?)-MS/i', $stem, $m)) {
            $quarry = trim($m[1]);
        } else {
            // Fallback: strip trailing -N
            $quarry = preg_replace('/-\d+$/', '', $stem);
        }

        if (!$quarry) {
            $result['skipped']++;
            $result['errors'][] = "Cannot parse quarry from: $file";
            continue;
        }

        $st = $db->prepare("SELECT id, measurement_sheet FROM products WHERE quarry_number = ?");
        $st->execute([$quarry]);
        $prod = $st->fetch();

        if (!$prod) {
            $result['skipped']++;
            $result['errors'][] = "No product for quarry '$quarry' ($file)";
            continue;
        }

        // Already linked to this exact file?
        if ($prod['measurement_sheet'] === $file) {
            $result['skipped']++;
            continue;
        }

        $db->prepare("UPDATE products SET measurement_sheet = ? WHERE id = ?")
           ->execute([$file, $prod['id']]);

        $result['synced']++;
    }

    $result['done'] = true;
    return $result;
}

// ── Step 3: Sync DNA Reports from /assets/uploads/dna_reports/ ───────────────
// File naming: Q23048-DNA.pdf  or  Q23048-DNA-1.pdf
function syncDnaReports(): array {
    $db     = getDB();
    $result = ['step'=>3,'label'=>'DNA / Lot Reports','found'=>0,'synced'=>0,'skipped'=>0,'errors'=>[],'done'=>false];

    if (!is_dir(DNA_DIR)) {
        $result['errors'][] = 'DNA reports directory not found.';
        $result['done'] = true;
        return $result;
    }

    $files = array_diff(scandir(DNA_DIR), ['.','..']);

    foreach ($files as $file) {
        $fullPath = DNA_DIR . '/' . $file;
        if (!is_file($fullPath)) continue;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') continue;

        $result['found']++;
        $stem = pathinfo($file, PATHINFO_FILENAME);

        // Q23048-DNA.pdf → Q23048
        if (preg_match('/^(.+?)-DNA/i', $stem, $m)) {
            $quarry = trim($m[1]);
        } else {
            $quarry = preg_replace('/-\d+$/', '', $stem);
        }

        if (!$quarry) {
            $result['skipped']++;
            $result['errors'][] = "Cannot parse quarry from: $file";
            continue;
        }

        $st = $db->prepare("SELECT id, dna_report FROM products WHERE quarry_number = ?");
        $st->execute([$quarry]);
        $prod = $st->fetch();

        if (!$prod) {
            $result['skipped']++;
            $result['errors'][] = "No product for quarry '$quarry' ($file)";
            continue;
        }

        if ($prod['dna_report'] === $file) {
            $result['skipped']++;
            continue;
        }

        $db->prepare("UPDATE products SET dna_report = ? WHERE id = ?")
           ->execute([$file, $prod['id']]);

        $result['synced']++;
    }

    $result['done'] = true;
    return $result;
}





$pages = ['dashboard','products','product_edit','colors','users','inquiries','sync'];
$file  = in_array($page, $pages)
       ? __DIR__ . '/views/' . $page . '.php'
       : __DIR__ . '/views/dashboard.php';
include $file;