<?php
/**
 * admin/views/_permission_guards.php
 * ─────────────────────────────────────────────────────────────────────────
 * This file documents ALL the per-view permission guard lines that must be
 * added to each existing admin view file. It also contains inline helper
 * functions used inside the views for conditional rendering of buttons.
 *
 * INSTRUCTIONS
 * ────────────
 * For each view listed below, add the shown requireAdminPermission() call
 * as the VERY FIRST LINE (before the include _layout_top.php call).
 *
 * You can also include this file once at the top of _layout_top.php so the
 * helper functions (permBtn, permLink) are always available, e.g.:
 *
 *   require_once __DIR__ . '/views/_permission_guards.php';
 *
 * ─────────────────────────────────────────────────────────────────────────
 */

// ── Helper: render a button only if permission is granted ─────────────────────
if (!function_exists('permBtn')) {
    /**
     * Render an HTML button only when the current admin has the given permission.
     *
     * Usage:
     *   <?= permBtn('products.delete', '<button ...>Delete</button>') ?>
     *
     * @param string $action     Permission action key (e.g. 'products.delete')
     * @param string $html       The full button HTML to output
     * @param string $fallback   Optional HTML to render when permission is missing
     * @return string
     */
    function permBtn(string $action, string $html, string $fallback = ''): string {
        return adminCan($action) ? $html : $fallback;
    }
}

// ── Helper: render an anchor only if permission is granted ────────────────────
if (!function_exists('permLink')) {
    /**
     * Render an HTML anchor only when the current admin has the given permission.
     *
     * Usage:
     *   <?= permLink('products.create', '<a href="...">Add Product</a>') ?>
     *
     * @param string $action     Permission action key
     * @param string $html       The full anchor HTML to output
     * @param string $fallback   Optional fallback HTML
     * @return string
     */
    function permLink(string $action, string $html, string $fallback = ''): string {
        return adminCan($action) ? $html : $fallback;
    }
}

