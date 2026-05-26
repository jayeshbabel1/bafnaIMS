<?php
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function redirect(string $url): never { header("Location: $url"); exit; }

function flash(string $key, string $msg): void  { $_SESSION['flash'][$key] = $msg; }
function getFlash(string $key): ?string {
    $m = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $m;
}

// ── Settings ───────────────────────────────────────────────────────────────────
function getSetting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        $rows  = getDB()->query("SELECT key,value FROM settings")->fetchAll();
        $cache = array_column($rows, 'value', 'key');
    }
    return $cache[$key] ?? $default;
}
function setSetting(string $key, string $value): void {
    getDB()->prepare("INSERT OR REPLACE INTO settings (key,value) VALUES (?,?)")->execute([$key, $value]);
}

// ── Color CSS variables ────────────────────────────────────────────────────────
function getCSSVariables(): string {
    $defaults = require __DIR__ . '/../config/colors.php';
    $db       = getDB();
    $rows     = $db->query("SELECT key,value FROM settings WHERE key LIKE '--%'")->fetchAll();
    $overrides = array_column($rows, 'value', 'key');
    $vars = array_merge($defaults, $overrides);
    $css  = ":root {\n";
    foreach ($vars as $k => $v) {
        if (str_starts_with($k, '--')) $css .= "  $k: " . h($v) . ";\n";
    }
    $css .= "}\n";
    return $css;
}

// ── SVG Marble pattern ─────────────────────────────────────────────────────────
function marbleSVG(array $palette, int $w = 200, int $h_val = 200, string $uid = ''): string {
    [$c1, $c2, $c3] = array_pad($palette, 3, 'CCCCCC');
    if (!$uid) $uid = 'mb' . substr(md5(implode($palette)), 0, 8);
    $vein1 = $c2; $vein2 = $c3;
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h_val}" viewBox="0 0 200 200" preserveAspectRatio="xMidYMid slice">
  <defs>
    <filter id="{$uid}t"><feTurbulence type="fractalNoise" baseFrequency="0.018 0.032" numOctaves="5" seed="7" result="n"/>
    <feDisplacementMap in="SourceGraphic" in2="n" scale="20" xChannelSelector="R" yChannelSelector="G"/></filter>
  </defs>
  <rect width="200" height="200" fill="#{$c1}"/>
  <g filter="url(#{$uid}t)">
    <path d="M15 0 C55 60 140 110 185 200"  stroke="#{$vein1}" stroke-width="2.5" fill="none" opacity="0.55"/>
    <path d="M0 40 C70 80 130 120 200 160"  stroke="#{$vein2}" stroke-width="1.5" fill="none" opacity="0.40"/>
    <path d="M50 0 C90 70 110 130 160 200"  stroke="#{$vein1}" stroke-width="1.2" fill="none" opacity="0.38"/>
    <path d="M100 0 C130 55 70 145 200 180" stroke="#{$vein2}" stroke-width="2.0" fill="none" opacity="0.45"/>
    <path d="M0 80 C60 95 150 105 200 100"  stroke="#{$vein1}" stroke-width="1.0" fill="none" opacity="0.30"/>
    <path d="M10 0 C40 110 160 80 190 200"  stroke="#{$vein2}" stroke-width="1.8" fill="none" opacity="0.35"/>
  </g>
</svg>
SVG;
}

// ── SVG Icons ──────────────────────────────────────────────────────────────────
function icon(string $name, int $size = 20, string $class = ''): string {
    static $paths = [
        'home'      => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'grid'      => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'heart'     => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
        'heart_fill'=> '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" fill="#E84040" stroke="#E84040"/>',
        'msg'       => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'user'      => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'search'    => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'filter'    => '<line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="10" y1="18" x2="14" y2="18"/>',
        'back'      => '<polyline points="15 18 9 12 15 6"/>',
        'forward'   => '<polyline points="9 18 15 12 9 6"/>',
        'share'     => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>',
        'check'     => '<polyline points="20 6 9 17 4 12"/>',
        'close'     => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'upload'    => '<polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>',
        'download'  => '<polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/>',
        'phone'     => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.4 2 2 0 0 1 3.6 1.22h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.96a16 16 0 0 0 6 6l.92-.92a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'verified'  => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'bell'      => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'file'      => '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/>',
        'pdf'       => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
        'excel'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/><polyline points="10 9 9 9 8 9"/>',
        'eye'       => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'edit'      => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'trash'     => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'plus'      => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'star'      => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'palette'   => '<circle cx="12" cy="12" r="10"/><path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72m2.54-15.38c-3.72 4.35-8.94 5.66-16.88 5.85m19.5 1.9c-3.5-.93-6.63-.82-8.94 0-2.58.92-5.01 2.86-7.44 6.32"/>',
        'users'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'lock'      => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'mail'      => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        'image'     => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
        'zoom'      => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/>',
        'whatsapp'  => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
        'copy'      => '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'refresh'   => '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
        'info'      => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
    ];
    $body  = $paths[$name] ?? '<circle cx="12" cy="12" r="10"/>';
    $cls   = $class ? " class=\"{$class}\"" : '';
    return "<svg{$cls} width=\"{$size}\" height=\"{$size}\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\">{$body}</svg>";
}

// ── Product helpers ────────────────────────────────────────────────────────────
function getProducts(array $filters = []): array {
    $db   = getDB();
    $sql  = "SELECT p.*, (SELECT filename FROM product_photos WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS primary_photo FROM products p WHERE 1=1";
    $params = [];
    if (!empty($filters['category'])) {
        $sql .= " AND p.category=?"; $params[] = $filters['category'];
    }
    if (!empty($filters['subcategory'])) {
        $sql .= " AND p.subcategory=?"; $params[] = $filters['subcategory'];
    }
    if (!empty($filters['color_subcategory'])) {
        $sql .= " AND p.color_subcategory=?"; $params[] = $filters['color_subcategory'];
    }
    if (!empty($filters['search'])) {
        $sql .= " AND (p.name LIKE ? OR p.quarry_number LIKE ?)";
        $params[] = '%' . $filters['search'] . '%';
        $params[] = '%' . $filters['search'] . '%';
    }
    $sql .= " ORDER BY p.featured DESC, p.sort_order ASC, p.id ASC";
    $st  = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function getProduct(int $id): ?array {
    $db = getDB();
    $st = $db->prepare("SELECT * FROM products WHERE id=?");
    $st->execute([$id]);
    $p = $st->fetch();
    if (!$p) return null;
    $ps = $db->prepare("SELECT * FROM product_photos WHERE product_id=? ORDER BY sort_order ASC");
    $ps->execute([$id]);
    $p['photos'] = $ps->fetchAll();
    $p['palette_arr'] = json_decode($p['palette'] ?? '["F2F0EC","D8CFC4","BFB0A0"]', true) ?? ['F2F0EC','D8CFC4','BFB0A0'];
    return $p;
}

function isShortlisted(int $productId): bool {
    if (!isLoggedIn()) return false;
    $st = getDB()->prepare("SELECT id FROM shortlist WHERE user_id=? AND product_id=?");
    $st->execute([$_SESSION['user_id'], $productId]);
    return (bool)$st->fetch();
}

function shortlistCount(): int {
    if (!isLoggedIn()) return 0;
    $st = getDB()->prepare("SELECT COUNT(*) as c FROM shortlist WHERE user_id=?");
    $st->execute([$_SESSION['user_id']]);
    return (int)$st->fetch()['c'];
}

function inquiryCount(): int {
    if (!isLoggedIn()) return 0;
    $st = getDB()->prepare("SELECT COUNT(*) as c FROM inquiries WHERE user_id=?");
    $st->execute([$_SESSION['user_id']]);
    return (int)$st->fetch()['c'];
}

function navActive(string $p): string {
    return ($_GET['page'] ?? 'catalog') === $p ? ' active' : '';
}

function getInitials(string $name): string {
    $parts = explode(' ', trim($name));
    return strtoupper(($parts[0][0] ?? '') . (isset($parts[1]) ? $parts[1][0] : ''));
}

function timeAgo(int $timestamp): string {
    $diff = time() - $timestamp;
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return floor($diff/60)   . 'm ago';
    if ($diff < 86400) return floor($diff/3600)  . 'h ago';
    if ($diff < 604800)return floor($diff/86400) . 'd ago';
    return date('d M Y', $timestamp);
}
