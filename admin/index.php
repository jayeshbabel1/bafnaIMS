<?php
session_start();
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

    if ($action === 'import_excel') {
        importCSV($_FILES['excel_file'] ?? null);
        redirect('index.php?page=products');
    }

    if ($action === 'import_photos') {
        importPhotos($_FILES['photo_zip'] ?? null);
        redirect('index.php?page=products');
    }

    if ($action === 'reply_inquiry') {
        $iid   = (int)($_POST['inquiry_id'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');
        getDB()->prepare("UPDATE inquiries SET admin_reply=?, status='replied' WHERE id=?")
               ->execute([$reply, $iid]);
        flash('toast', 'Reply sent.');
        redirect('index.php?page=inquiries');
    }

    if ($action === 'update_user_status') {
        $uid      = (int)($_POST['user_id']  ?? 0);
        $verified = (int)($_POST['verified'] ?? 0);
        getDB()->prepare("UPDATE users SET verified=? WHERE id=?")->execute([$verified, $uid]);
        flash('toast', 'User updated.');
        redirect('index.php?page=users');
    }
}

// ── Routing ───────────────────────────────────────────────────────────────────
$page = preg_replace('/[^a-z_]/', '', $_GET['page'] ?? 'dashboard');

if (!isAdmin()) {
    $adminError = $_SESSION['admin_error'] ?? null;
    unset($_SESSION['admin_error']);
    include __DIR__ . '/views/login.php';
    exit;
}

$pages = ['dashboard','products','product_edit','colors','users','inquiries'];
$file  = in_array($page, $pages)
       ? __DIR__ . '/views/' . $page . '.php'
       : __DIR__ . '/views/dashboard.php';
include $file;

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
    if (!empty($files['photos']['name'][0])) {
        $maxOrder = $db->prepare("SELECT COALESCE(MAX(sort_order),0) as m FROM product_photos WHERE product_id=?");
        $maxOrder->execute([$pid]);
        $order = (int)$maxOrder->fetch()['m'] + 1;
        foreach ($files['photos']['tmp_name'] as $i => $tmp) {
            if (!$tmp || $files['photos']['error'][$i]) continue;
            $orig = basename($files['photos']['name'][$i]);
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $fn   = 'p'.$pid.'_'.time().'_'.$i.'.'.$ext;
            if (move_uploaded_file($tmp, PHOTOS_DIR.'/'.$fn)) {
                $db->prepare("INSERT INTO product_photos (product_id,filename,sort_order) VALUES (?,?,?)")
                   ->execute([$pid, $fn, $order++]);
            }
        }
    }

    flash('toast', 'Product saved successfully.');
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

// ── Photo Import (multi-file by quarry prefix) ────────────────────────────────
function importPhotos(?array $files): void {
    if (!$files) { flash('error','No files uploaded.'); return; }
    $db    = getDB();
    $count = 0;

    // Handle multiple files (name[], tmp_name[], etc.)
    $names    = is_array($files['name'])     ? $files['name']     : [$files['name']];
    $tmps     = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors   = is_array($files['error'])    ? $files['error']    : [$files['error']];

    foreach ($names as $idx => $origName) {
        if ($errors[$idx] ?? UPLOAD_ERR_NO_FILE) continue;
        $ext  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) continue;

        // Parse quarry number from filename: Q20205-1.jpg => Q20205
        $base   = pathinfo($origName, PATHINFO_FILENAME);   // e.g. QM-0421-1
        $quarry = preg_replace('/-\d+$/', '', $base);       // strip trailing -N
        if (!$quarry) continue;

        $st = $db->prepare("SELECT id FROM products WHERE quarry_number=?");
        $st->execute([$quarry]);
        $prod = $st->fetch();
        if (!$prod) continue; // No matching product

        $fn = 'p'.$prod['id'].'_'.time().'_'.$idx.'.'.$ext;
        if (!move_uploaded_file($tmps[$idx], PHOTOS_DIR.'/'.$fn)) continue;

        $ord = $db->prepare("SELECT COALESCE(MAX(sort_order),0) as m FROM product_photos WHERE product_id=?");
        $ord->execute([$prod['id']]);
        $order = (int)$ord->fetch()['m'] + 1;

        $db->prepare("INSERT INTO product_photos (product_id,filename,sort_order) VALUES (?,?,?)")
           ->execute([$prod['id'], $fn, $order]);
        $count++;
    }
    flash('toast', "$count photo(s) linked to products.");
}

// ── CSV Export ────────────────────────────────────────────────────────────────
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