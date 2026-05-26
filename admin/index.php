<?php
session_start();
define('ADMIN_PANEL', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

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
        foreach ($defaults as $k) {
            if (isset($_POST[$k])) {
                // Allow full CSS values; only basic sanitize
                $val = preg_replace('/[<>"\']/', '', $_POST[$k]);
                setSetting($k, $val);
            }
        }
        flash('toast', 'Color scheme saved.');
        redirect('colors.php');
    }

    if ($action === 'reset_colors') {
        $defaults = require __DIR__ . '/../config/colors.php';
        foreach ($defaults as $k => $v) setSetting($k, $v);
        flash('toast', 'Colors reset to defaults.');
        redirect('colors.php');
    }

    if ($action === 'save_product') {
        saveProduct($_POST, $_FILES);
        redirect('products.php');
    }

    if ($action === 'delete_product') {
        $pid = (int)($_POST['product_id'] ?? 0);
        getDB()->prepare("DELETE FROM products WHERE id=?")->execute([$pid]);
        flash('toast', 'Product deleted.');
        redirect('products.php');
    }

    if ($action === 'delete_photo') {
        $fid = (int)($_POST['photo_id'] ?? 0);
        $st  = getDB()->prepare("SELECT filename,product_id FROM product_photos WHERE id=?");
        $st->execute([$fid]);
        $ph  = $st->fetch();
        if ($ph) {
            @unlink(PHOTOS_DIR . '/' . $ph['filename']);
            getDB()->prepare("DELETE FROM product_photos WHERE id=?")->execute([$fid]);
        }
        flash('toast', 'Photo deleted.');
        redirect('product_edit.php?id=' . ($ph['product_id'] ?? 0));
    }

    if ($action === 'import_excel') {
        importExcel($_FILES['excel_file'] ?? null);
        redirect('products.php');
    }

    if ($action === 'reply_inquiry') {
        $iid   = (int)($_POST['inquiry_id'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');
        getDB()->prepare("UPDATE inquiries SET admin_reply=?, status='replied' WHERE id=?")
               ->execute([$reply, $iid]);
        flash('toast', 'Reply sent.');
        redirect('inquiries.php');
    }

    if ($action === 'update_user_status') {
        $uid     = (int)($_POST['user_id'] ?? 0);
        $verified = (int)($_POST['verified'] ?? 0);
        getDB()->prepare("UPDATE users SET verified=? WHERE id=?")->execute([$verified, $uid]);
        flash('toast', 'User updated.');
        redirect('users.php');
    }
}

// ── Routing ───────────────────────────────────────────────────────────────────
$page = $_GET['page'] ?? 'dashboard';

if (!isAdmin()) {
    // Show login
    $adminError = $_SESSION['admin_error'] ?? null;
    unset($_SESSION['admin_error']);
    include __DIR__ . '/views/login.php';
    exit;
}

$pages = ['dashboard','products','product_edit','colors','users','inquiries'];
$file  = in_array($page, $pages) ? __DIR__ . '/views/' . $page . '.php' : __DIR__ . '/views/dashboard.php';
include $file;

// ── Product save ──────────────────────────────────────────────────────────────
function saveProduct(array $data, array $files): void {
    $db  = getDB();
    $pid = (int)($data['product_id'] ?? 0);

    $fields = [
        'name'              => trim($data['name'] ?? ''),
        'category'          => trim($data['category'] ?? ''),
        'subcategory'       => trim($data['subcategory'] ?? ''),
        'color_subcategory' => trim($data['color_subcategory'] ?? ''),
        'quarry_number'     => trim($data['quarry_number'] ?? ''),
        'quantity'          => (float)($data['quantity'] ?? 0),
        'pieces'            => (int)($data['pieces'] ?? 0),
        'thickness'         => trim($data['thickness'] ?? ''),
        'sizes'             => trim($data['sizes'] ?? ''),
        'cutter_size'       => trim($data['cutter_size'] ?? ''),
        'origin'            => trim($data['origin'] ?? ''),
        'finish'            => trim($data['finish'] ?? ''),
        'description'       => trim($data['description'] ?? ''),
        'in_stock'          => isset($data['in_stock']) ? 1 : 0,
        'featured'          => isset($data['featured']) ? 1 : 0,
        'palette'           => trim($data['palette'] ?? '["F2F0EC","D8CFC4","BFB0A0"]'),
    ];

    // Handle measurement sheet upload
    if (!empty($files['measurement_sheet']['name'])) {
        $fn = $files['measurement_sheet']['name'];
        if (move_uploaded_file($files['measurement_sheet']['tmp_name'], MEASUREMENT_DIR . '/' . $fn)) {
            $fields['measurement_sheet'] = $fn;
        }
    }

    // Handle DNA report upload
    if (!empty($files['dna_report']['name'])) {
        $fn = $files['dna_report']['name'];
        if (move_uploaded_file($files['dna_report']['tmp_name'], DNA_DIR . '/' . $fn)) {
            $fields['dna_report'] = $fn;
        }
    }

    if ($pid) {
        // UPDATE
        $set = implode(',', array_map(fn($k) => "$k=?", array_keys($fields)));
        $vals = array_values($fields);
        $vals[] = $pid;
        $db->prepare("UPDATE products SET $set WHERE id=?")->execute($vals);
    } else {
        $cols = implode(',', array_keys($fields));
        $phs  = implode(',', array_fill(0, count($fields), '?'));
        $db->prepare("INSERT INTO products ($cols) VALUES ($phs)")->execute(array_values($fields));
        $pid = $db->lastInsertId();
    }

    // Handle photo uploads (multiple)
    if (!empty($files['photos']['name'][0])) {
        $maxOrder = $db->prepare("SELECT MAX(sort_order) as m FROM product_photos WHERE product_id=?");
        $maxOrder->execute([$pid]);
        $order = (int)($maxOrder->fetch()['m'] ?? -1) + 1;
        foreach ($files['photos']['tmp_name'] as $i => $tmp) {
            if (!$tmp || $files['photos']['error'][$i]) continue;
            $orig = $files['photos']['name'][$i];
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $fn   = 'p' . $pid . '_' . time() . '_' . $i . '.' . $ext;
            if (move_uploaded_file($tmp, PHOTOS_DIR . '/' . $fn)) {
                $db->prepare("INSERT INTO product_photos (product_id,filename,sort_order) VALUES (?,?,?)")
                   ->execute([$pid, $fn, $order++]);
            }
        }
    }

    flash('toast', 'Product saved successfully.');
}

// ── Excel/CSV Import ──────────────────────────────────────────────────────────
function importExcel(?array $file): void {
    if (!$file || $file['error']) { flash('error', 'File upload failed.'); return; }

    $fn  = $file['name'];
    $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv'])) { flash('error', 'Only CSV files are supported for import.'); return; }

    $dest = EXCEL_DIR . '/' . time() . '_' . $fn;
    if (!move_uploaded_file($file['tmp_name'], $dest)) { flash('error', 'Could not save file.'); return; }

    $handle = fopen($dest, 'r');
    if (!$handle) { flash('error', 'Could not read file.'); return; }

    $headers = array_map('trim', fgetcsv($handle) ?: []);
    $db      = getDB();
    $count   = 0;

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < 2) continue;
        $data = array_combine(array_slice($headers, 0, count($row)), $row) ?: [];
        $g    = fn($k) => trim($data[$k] ?? '');

        $quarry = $g('quarry_number') ?: $g('Quarry Number') ?: $g('quarry');
        $name   = $g('name') ?: $g('Name') ?: $g('Product Name');
        if (!$name) continue;

        $measurement = $g('measurement_sheet') ?: $g('Measurement Sheet');
        $dna         = $g('dna_report') ?: $g('DNA Report');

        $fields = [
            'name'              => $name,
            'category'          => $g('category') ?: $g('Category'),
            'subcategory'       => $g('subcategory') ?: $g('Sub Category'),
            'color_subcategory' => $g('color_subcategory') ?: $g('Color'),
            'quarry_number'     => $quarry,
            'quantity'          => (float)($g('quantity') ?: $g('Quantity') ?: 0),
            'pieces'            => (int)($g('pieces') ?: $g('Pieces') ?: 0),
            'thickness'         => $g('thickness') ?: $g('Thickness'),
            'sizes'             => $g('sizes') ?: $g('Sizes'),
            'cutter_size'       => $g('cutter_size') ?: $g('Cutter Size'),
            'origin'            => $g('origin') ?: $g('Origin'),
            'finish'            => $g('finish') ?: $g('Finish'),
            'description'       => $g('description') ?: $g('Description'),
            'in_stock'          => (int)($g('in_stock') ?: $g('In Stock') ?: 1),
            'featured'          => (int)($g('featured') ?: $g('Featured') ?: 0),
            'measurement_sheet' => $measurement,
            'dna_report'        => $dna,
        ];

        // Check if product with quarry number exists
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
