<?php
/**
 * includes/slab_calculator.php
 * Pure calc layer + settings/perm bootstrap. No DB writes in calc fns —
 * keeps reusable for future Weight/Cost calculators w/o rewrite.
 */

define('SLAB_CALC_UNITS', ['ft', 'in', 'mm', 'cm', 'm']);

// ── Settings ──────────────────────────────────────────────────────────
function isSlabCalculatorEnabled(): bool {
    return getSetting('slab_calculator_enabled', '1') === '1';
}
function getSlabCalculatorDefaultWastage(): float {
    $v = getSetting('slab_calculator_default_wastage', '0');
    return is_numeric($v) ? max(0, min(100, (float)$v)) : 0.0;
}
function saveSlabCalculatorSettings(array $d): array {
    $enabled = !empty($d['enabled']) ? '1' : '0';
    $wastage = (float)($d['default_wastage'] ?? 0);
    if ($wastage < 0 || $wastage > 100) $wastage = 0;
    setSettings([
        'slab_calculator_enabled'         => $enabled,
        'slab_calculator_default_wastage' => (string)$wastage,
    ]);
    return ['success' => true];
}

// ── RBAC auto-seed (mirrors ensureWatermarkPermission pattern) ──────────
function ensureSlabCalculatorPermission(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = getDB();
        if (!$db->query("SHOW TABLES LIKE 'admin_permissions'")->fetch()) return;
        $chk = $db->prepare("SELECT id FROM admin_permissions WHERE action=?");
        $chk->execute(['settings.slab_calculator']);
        if ($chk->fetch()) return;
        $max = (int)$db->query("SELECT COALESCE(MAX(sort_order),0) FROM admin_permissions")->fetchColumn();
        $db->prepare("INSERT INTO admin_permissions (module, action, label, sort_order) VALUES (?,?,?,?)")
           ->execute(['Settings', 'settings.slab_calculator', 'Manage Slab Calculator Settings', $max + 1]);
    } catch (Throwable $e) { error_log('ensureSlabCalculatorPermission: ' . $e->getMessage()); }
}

// ── Unit conversion — normalize to FEET (app's existing sqft convention) ─
function slabUnitToFeet(float $value, string $unit): float {
    return match ($unit) {
        'ft' => $value,
        'in' => $value / 12,
        'mm' => $value / 304.8,
        'cm' => $value / 30.48,
        'm'  => $value * 3.28084,
        default => $value,
    };
}

/**
 * Core calc — pure fn, server-side validated. Never trusts pre-computed
 * client values; recomputes from raw length/width/unit/qty/wastage.
 */
function calcSlabArea(array $in): array {
    $length  = $in['length']   ?? null;
    $width   = $in['width']    ?? null;
    $unit    = $in['unit']     ?? 'ft';
    $qty     = $in['quantity'] ?? 1;
    $wastage = $in['wastage']  ?? 0;

    if (!in_array($unit, SLAB_CALC_UNITS, true)) {
        return ['success' => false, 'error' => 'Invalid unit.'];
    }
    foreach (['length' => $length, 'width' => $width] as $label => $v) {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return ['success' => false, 'error' => ucfirst($label) . ' must be a number.'];
        }
        if ((float)$v <= 0 || (float)$v > 100000) {
            return ['success' => false, 'error' => ucfirst($label) . ' must be between 0 and 100000.'];
        }
    }
    if (!is_numeric($qty) || (int)$qty < 1 || (int)$qty > 100000) {
        return ['success' => false, 'error' => 'Quantity must be a positive whole number.'];
    }
    if (!is_numeric($wastage) || (float)$wastage < 0 || (float)$wastage > 100) {
        return ['success' => false, 'error' => 'Wastage must be between 0 and 100%.'];
    }

    $lengthFt = slabUnitToFeet((float)$length, $unit);
    $widthFt  = slabUnitToFeet((float)$width, $unit);
    $qty      = (int)$qty;
    $wastage  = (float)$wastage;

    $areaPerSlab  = $lengthFt * $widthFt;
    $totalArea    = $areaPerSlab * $qty;
    $wastageArea  = $totalArea * ($wastage / 100);
    $requiredArea = $totalArea + $wastageArea;

    return [
        'success'       => true,
        'area_per_slab' => round($areaPerSlab, 2),
        'total_area'    => round($totalArea, 2),
        'wastage_area'  => round($wastageArea, 2),
        'required_area' => round($requiredArea, 2),
        'quantity'      => $qty,
        'unit'          => $unit,
    ];
}