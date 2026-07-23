<?php
/**
 * includes/product_views.php
 * ─────────────────────────────────────────────────────────────────────────
 * Fire 1/5 — Product Listing Views: schema + backend helpers.
 * Require once from index.php + admin/index.php (mirrors includes/license.php pattern).
 *
 * Tables:
 *   product_view_settings   — per panel+view field visibility/order
 *   product_view_defaults   — per panel: which view (grid/list/table) is default
 *
 * panel: 'admin' | 'user'
 * view : 'grid' | 'list' | 'table'
 * ─────────────────────────────────────────────────────────────────────────
 */

define('PV_PANELS', ['admin', 'user']);
define('PV_VIEWS',  ['grid', 'list', 'table']);

// ── All fields the product listing COULD show, keyed by field_key ─────────
// 'label' = human name, 'panels' = which panels may use this field.
function pvAllFields(): array {
    return [
        'photo'               => ['label' => 'Photo / Thumbnail',   'panels' => ['admin','user']],
        'name'                => ['label' => 'Product Name',        'panels' => ['admin','user']],
        'quarry_number'       => ['label' => 'Quarry Number',       'panels' => ['admin','user']],
        'category'            => ['label' => 'Category',            'panels' => ['admin','user']],
        'subcategory'         => ['label' => 'Subcategory',         'panels' => ['admin']],
        'color_subcategory'   => ['label' => 'Color',                'panels' => ['admin','user']],
        'thickness'           => ['label' => 'Thickness',           'panels' => ['admin','user']],
        'sizes'               => ['label' => 'Useable Size',        'panels' => ['admin','user']],
        'cutter_size'         => ['label' => 'Italian Size',        'panels' => ['admin','user']],
        'origin'              => ['label' => 'Origin',              'panels' => ['admin']],
        'finish'              => ['label' => 'Finish',              'panels' => ['admin']],
        'quantity_available'  => ['label' => 'Available Qty',       'panels' => ['admin','user']],
        'quantity_on_hold'    => ['label' => 'On Hold Qty',          'panels' => ['admin']],
        'in_stock'            => ['label' => 'Stock Status',        'panels' => ['admin','user']],
        'featured'            => ['label' => 'Featured Badge',      'panels' => ['admin','user']],
        'actions'             => ['label' => 'Action Buttons',      'panels' => ['admin','user']],
    ];
}

// ── Default field set per panel+view (key => [visible, order]) ────────────
function pvDefaultFieldSet(string $panel, string $view): array {
    if ($panel === 'admin') {
        $order = ($view === 'table')
            ? ['photo','name','quarry_number','category','quantity_available','quantity_on_hold','in_stock','featured','actions']
            : ['photo','name','quarry_number','category','thickness','sizes','quantity_available','in_stock','featured','actions'];
    } else { // user
        $order = ($view === 'table')
            ? ['photo','name','quarry_number','category','thickness','quantity_available','in_stock','actions']
            : ['photo','name','quarry_number','category','thickness','sizes','quantity_available','in_stock','featured','actions'];
    }
    $all = array_keys(pvAllFields());
    $out = [];
    foreach ($order as $i => $key) $out[$key] = ['visible' => 1, 'sort_order' => $i];
    // Any remaining fields valid for this panel default to hidden, appended at end
    $i = count($order);
    foreach ($all as $key) {
        if (!isset($out[$key]) && in_array($panel, pvAllFields()[$key]['panels'], true)) {
            $out[$key] = ['visible' => 0, 'sort_order' => $i++];
        }
    }
    return $out;
}

// ── Schema bootstrap (idempotent, mirrors ensureLicenseTables()) ──────────
function ensureProductViewTables(): void {
    static $done = false;
    if ($done) return;
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS product_view_settings (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        panel       VARCHAR(10)  NOT NULL,
        view_type   VARCHAR(10)  NOT NULL,
        field_key   VARCHAR(50)  NOT NULL,
        visible     TINYINT(1)   NOT NULL DEFAULT 1,
        sort_order  INT          NOT NULL DEFAULT 0,
        updated_at  INT UNSIGNED NOT NULL,
        UNIQUE KEY uq_pv (panel, view_type, field_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS product_view_defaults (
        panel        VARCHAR(10) PRIMARY KEY,
        default_view VARCHAR(10) NOT NULL DEFAULT 'grid',
        updated_at   INT UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed defaults if empty (first run)
    $count = (int)$db->query("SELECT COUNT(*) FROM product_view_settings")->fetchColumn();
    if ($count === 0) {
        $now = time();
        $ins = $db->prepare("INSERT IGNORE INTO product_view_settings
            (panel, view_type, field_key, visible, sort_order, updated_at) VALUES (?,?,?,?,?,?)");
        foreach (PV_PANELS as $panel) {
            foreach (PV_VIEWS as $view) {
                foreach (pvDefaultFieldSet($panel, $view) as $key => $cfg) {
                    $ins->execute([$panel, $view, $key, $cfg['visible'], $cfg['sort_order'], $now]);
                }
            }
        }
    }

    $dcount = (int)$db->query("SELECT COUNT(*) FROM product_view_defaults")->fetchColumn();
    if ($dcount === 0) {
        $now = time();
        $ins = $db->prepare("INSERT IGNORE INTO product_view_defaults (panel, default_view, updated_at) VALUES (?,?,?)");
        foreach (PV_PANELS as $panel) $ins->execute([$panel, 'grid', $now]);
    }

    $done = true;
}

// ── Get field config for one panel+view, ordered, with visible flag ───────
// Returns: [ ['key'=>'name','label'=>'Product Name','visible'=>1,'sort_order'=>0], ... ]
function getViewFieldConfig(string $panel, string $view): array {
    ensureProductViewTables();
    if (!in_array($panel, PV_PANELS, true) || !in_array($view, PV_VIEWS, true)) return [];

    $st = getDB()->prepare("SELECT field_key, visible, sort_order FROM product_view_settings
                             WHERE panel=? AND view_type=? ORDER BY sort_order ASC");
    $st->execute([$panel, $view]);
    $rows = $st->fetchAll();

    $labels = pvAllFields();
    $out = [];
    foreach ($rows as $r) {
        $key = $r['field_key'];
        if (!isset($labels[$key])) continue; // stale/unknown field, skip
        $out[] = [
            'key'        => $key,
            'label'      => $labels[$key]['label'],
            'visible'    => (int)$r['visible'],
            'sort_order' => (int)$r['sort_order'],
        ];
    }
    return $out;
}

// ── Get only the visible field keys, in order (convenience for renderers) ─
function getVisibleFieldKeys(string $panel, string $view): array {
    $cfg = getViewFieldConfig($panel, $view);
    return array_values(array_map(fn($f) => $f['key'], array_filter($cfg, fn($f) => $f['visible'])));
}

// ── Save field config for one panel+view (replace-all, like saveRolePermissions) ─
// $fields = [ ['key'=>'name','visible'=>1], ... ] — array order = sort_order
function saveViewFieldConfig(string $panel, string $view, array $fields): array {
    ensureProductViewTables();
    if (!in_array($panel, PV_PANELS, true) || !in_array($view, PV_VIEWS, true)) {
        return ['success' => false, 'error' => 'Invalid panel or view.'];
    }
    $labels = pvAllFields();
    $db = getDB();
    $db->beginTransaction();
    try {
        $now = time();
        $upd = $db->prepare("INSERT INTO product_view_settings
                (panel, view_type, field_key, visible, sort_order, updated_at)
                VALUES (?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE visible=VALUES(visible), sort_order=VALUES(sort_order), updated_at=VALUES(updated_at)");
        $order = 0;
        foreach ($fields as $f) {
            $key = $f['key'] ?? '';
            if (!isset($labels[$key])) continue; // ignore unknown keys
            if (!in_array($panel, $labels[$key]['panels'], true)) continue; // field not valid for this panel
            $visible = !empty($f['visible']) ? 1 : 0;
            $upd->execute([$panel, $view, $key, $visible, $order, $now]);
            $order++;
        }
        $db->commit();
        return ['success' => true];
    } catch (Throwable $e) {
        $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ── Default view getters/setters ───────────────────────────────────────────
function getDefaultView(string $panel): string {
    ensureProductViewTables();
    if (!in_array($panel, PV_PANELS, true)) return 'grid';
    $st = getDB()->prepare("SELECT default_view FROM product_view_defaults WHERE panel=?");
    $st->execute([$panel]);
    $v = $st->fetchColumn();
    return in_array($v, PV_VIEWS, true) ? $v : 'grid';
}

function setDefaultView(string $panel, string $view): array {
    ensureProductViewTables();
    if (!in_array($panel, PV_PANELS, true))  return ['success' => false, 'error' => 'Invalid panel.'];
    if (!in_array($view, PV_VIEWS, true))    return ['success' => false, 'error' => 'Invalid view.'];
    getDB()->prepare("INSERT INTO product_view_defaults (panel, default_view, updated_at) VALUES (?,?,?)
                       ON DUPLICATE KEY UPDATE default_view=VALUES(default_view), updated_at=VALUES(updated_at)")
           ->execute([$panel, $view, time()]);
    return ['success' => true];
}

// ── RBAC: auto-seed the 'settings.product_views' permission if missing ────
// Safe no-op if admin_permissions table doesn't exist yet or row already present.
function ensureProductViewPermission(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = getDB();
        $chk = $db->query("SHOW TABLES LIKE 'admin_permissions'")->fetch();
        if (!$chk) return;
        $exists = $db->prepare("SELECT id FROM admin_permissions WHERE action=?");
        $exists->execute(['settings.product_views']);
        if ($exists->fetch()) return;
        $maxSort = (int)$db->query("SELECT COALESCE(MAX(sort_order),0) FROM admin_permissions")->fetchColumn();
        $db->prepare("INSERT INTO admin_permissions (module, action, label, sort_order) VALUES (?,?,?,?)")
           ->execute(['Settings', 'settings.product_views', 'Manage Product View Settings', $maxSort + 1]);
    } catch (Throwable $e) {
        error_log('ensureProductViewPermission: ' . $e->getMessage());
    }
}

// ── Catalog Themes (user panel only) — visual presets, stored via settings table ──
define('PV_THEME_SETTING_KEY', 'user_catalog_theme');

function pvCatalogThemes(): array {
    return [
        'classic'   => ['label' => 'Classic',    'desc' => 'Current default look — white cards, subtle borders.'],
        'minimal'   => ['label' => 'Minimal',     'desc' => 'Clean, borderless, extra whitespace.'],
        'bold_gold' => ['label' => 'Bold Gold',   'desc' => 'Larger cards, gold accents, premium feel.'],
        'compact'   => ['label' => 'Compact',     'desc' => 'Dense layout, small thumbs, more per screen.'],
    ];
}

function getCatalogTheme(): string {
    $v = getSetting(PV_THEME_SETTING_KEY, 'classic');
    return array_key_exists($v, pvCatalogThemes()) ? $v : 'classic';
}

function setCatalogTheme(string $theme): array {
    if (!array_key_exists($theme, pvCatalogThemes())) return ['success' => false, 'error' => 'Invalid theme.'];
    setSetting(PV_THEME_SETTING_KEY, $theme);
    return ['success' => true];
}

// ── Fetch full config bundle for a panel (all 3 views + default) — used by settings UI ─
function getPanelViewBundle(string $panel): array {
    ensureProductViewTables();
    $bundle = ['default_view' => getDefaultView($panel), 'views' => []];
    foreach (PV_VIEWS as $view) {
        $bundle['views'][$view] = getViewFieldConfig($panel, $view);
    }
    return $bundle;
}