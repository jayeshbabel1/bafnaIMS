<?php
/**
 * PATCH 01 — config/colors.php
 *
 * REPLACE the entire file contents with this.
 * Adds all new theme variables while keeping every existing one.
 */

// ═══════════════════════════════════════════════════════════════════════════
// FILE: config/colors.php  — FULL REPLACEMENT
// ═══════════════════════════════════════════════════════════════════════════
return [
    // ── Background & Surfaces ─────────────────────────────────────────────
    '--bg'            => '#F7F4EF',
    '--surface'       => '#FFFFFF',
    '--surface2'      => '#E7DED1',
    '--surface3'      => '#DDD0C3',

    // ── Accent Colors ─────────────────────────────────────────────────────
    '--accent'        => '#776B63',
    '--accent2'       => '#5F544D',
    '--accent-light'  => '#E7DED1',
    '--accent-mid'    => '#BDA59E',

    // ── Neutral Stone ────────────────────────────────────────────────────
    '--stone'         => '#C8B8A8',
    '--stone-dark'    => '#776B63',

    // ── Text Colors ──────────────────────────────────────────────────────
    '--text'          => '#111111',
    '--text2'         => '#2B2B2B',
    '--text3'         => '#5C5C5C',

    // ── Borders ──────────────────────────────────────────────────────────
    '--border'        => '#D8CCBF',

    // ── Gold / Premium ────────────────────────────────────────────────────
    '--gold'          => '#776B63',
    '--gold-bg'       => '#E7DED1',

    // ── Status ───────────────────────────────────────────────────────────
    '--success'       => '#2F7A4D',
    '--success-bg'    => '#E3F2E8',
    '--danger'        => '#B23A3A',
    '--danger-bg'     => '#F8E3E3',

    // ── Nav & Topbar ─────────────────────────────────────────────────────
    '--nav-bg'        => '#776B63',
    '--topbar-bg'     => 'rgba(247,244,239,0.96)',

    // ── Radius ───────────────────────────────────────────────────────────
    '--btn-radius'    => '8px',
    '--card-radius'   => '16px',

    // ════════════════════════════════════════════════════════════════════
    // NEW: Button Variables
    // ════════════════════════════════════════════════════════════════════
    '--btn-bg'              => '#111111',
    '--btn-color'           => '#FFFFFF',
    '--btn-border-color'    => '#111111',
    '--btn-hover-bg'        => '#333333',
    '--btn-hover-color'     => '#FFFFFF',
    '--btn-hover-border'    => '#333333',

    // ── Secondary Button ─────────────────────────────────────────────────
    '--btn-sec-bg'          => '#FFFFFF',
    '--btn-sec-color'       => '#111111',
    '--btn-sec-border'      => '#D8CCBF',
    '--btn-sec-hover-bg'    => '#F5F5F5',
    '--btn-sec-hover-color' => '#111111',
    '--btn-sec-hover-border'=> '#776B63',

    // ════════════════════════════════════════════════════════════════════
    // NEW: Label Variables
    // ════════════════════════════════════════════════════════════════════
    '--label-color'         => '#555555',
    '--label-font-size'     => '11.5px',
    '--label-font-weight'   => '600',

    // ════════════════════════════════════════════════════════════════════
    // NEW: Input Variables
    // ════════════════════════════════════════════════════════════════════
    '--input-bg'            => '#FAFAF8',
    '--input-color'         => '#111111',
    '--input-placeholder'   => '#AAAAAA',
    '--input-border'        => '#D8CCBF',
    '--input-focus-border'  => '#776B63',
    '--input-focus-shadow'  => 'rgba(119,107,99,0.15)',
    '--input-hover-border'  => '#BDA59E',
    '--input-radius'        => '10px',
    '--input-font-size'     => '14px',

    // ════════════════════════════════════════════════════════════════════
    // NEW: Navbar Variables
    // ════════════════════════════════════════════════════════════════════
    '--navbar-bg'           => '#FFFFFF',
    '--navbar-color'        => '#111111',
    '--navbar-icon-color'   => '#777777',
    '--navbar-hover-color'  => '#111111',
    '--navbar-active-color' => '#111111',
    '--navbar-border'       => '#E0E0E0',

    // ════════════════════════════════════════════════════════════════════
    // NEW: Font Families
    // ════════════════════════════════════════════════════════════════════
    '--admin-font'          => "'DM Sans', sans-serif",
    '--user-font'           => "'Plus Jakarta Sans', sans-serif",
];