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
  
  // ════════════════════════════════════════════════════════════
    // ADMIN PANEL — Dedicated color variables
    // These are only applied to admin pages (getCSSVariables(true))
    // ════════════════════════════════════════════════════════════
 
    // Admin shell backgrounds
    '--admin-bg'               => '#F2F5F9',
    '--admin-surface'          => '#FFFFFF',
    '--admin-surface2'         => '#EEF2F7',
    '--admin-surface3'         => '#E6ECF2',
 
    // Admin sidebar
    '--admin-sidebar-from'     => '#1A4D65',   // gradient start
    '--admin-sidebar-to'       => '#0D2E3D',   // gradient end
    '--admin-sidebar-text'     => 'rgba(255,255,255,0.80)',
    '--admin-sidebar-active'   => 'rgba(255,255,255,0.18)',
    '--admin-sidebar-hover'    => 'rgba(255,255,255,0.10)',
    '--admin-sidebar-border'   => 'rgba(255,255,255,0.10)',
 
    // Admin topbar
    '--admin-topbar-bg'        => '#FFFFFF',
    '--admin-topbar-border'    => '#DDE4EB',
    '--admin-topbar-text'      => '#1A2837',
 
    // Admin accent (buttons, links, highlights)
    '--admin-accent'           => '#2C6E8A',
    '--admin-accent2'          => '#1A4D65',
    '--admin-accent-light'     => '#E3EFF4',
    '--admin-accent-mid'       => '#4DA8C9',
 
    // Admin table
    '--admin-table-header-bg'  => '#F7FAFC',
    '--admin-table-row-hover'  => '#F0F6FA',
    '--admin-table-border'     => '#DDE4EB',
 
    // Admin card / section
    '--admin-card-bg'          => '#FFFFFF',
    '--admin-card-border'      => '#DDE4EB',
    '--admin-card-radius'      => '12px',
 
    // Admin nav badge
    '--admin-badge-bg'         => '#E84040',
    '--admin-badge-color'      => '#FFFFFF',
  
   // ── Admin Text ────────────────────────────────────────────────
    '--admin-text'                  => '#1A2837',
    '--admin-text2'                 => '#4A6070',
    '--admin-text3'                 => '#8FA3B1',

    // ── Admin Primary Button ──────────────────────────────────────
    '--admin-btn-primary-bg'        => '#2C6E8A',
    '--admin-btn-primary-color'     => '#FFFFFF',
    '--admin-btn-primary-border'    => '#2C6E8A',
    '--admin-btn-primary-hover-bg'  => '#1A4D65',
    '--admin-btn-primary-hover-color' => '#FFFFFF',
    '--admin-btn-primary-radius'    => '8px',

    // ── Admin Secondary Button ────────────────────────────────────
    '--admin-btn-sec-bg'            => '#FFFFFF',
    '--admin-btn-sec-color'         => '#1A2837',
    '--admin-btn-sec-border'        => '#DDE4EB',
    '--admin-btn-sec-hover-bg'      => '#EEF2F7',
    '--admin-btn-sec-hover-color'   => '#1A2837',
    '--admin-btn-sec-radius'        => '8px',

    // ── Admin Danger Button ───────────────────────────────────────
    '--admin-btn-danger-bg'         => '#FFF0F0',
    '--admin-btn-danger-color'      => '#E84040',
    '--admin-btn-danger-border'     => '#FECACA',
    '--admin-btn-danger-hover-bg'   => '#E84040',
    '--admin-btn-danger-hover-color'=> '#FFFFFF',

    // ── Admin General / Toolbar Button ───────────────────────────
    '--admin-btn-general-bg'        => '#FFFFFF',
    '--admin-btn-general-color'     => '#1A2837',
    '--admin-btn-general-border'    => '#DDE4EB',
    '--admin-btn-general-hover-bg'  => '#EEF2F7',
    '--admin-btn-general-radius'    => '8px',

    // ── Admin Input / Text field ──────────────────────────────────
    '--admin-input-bg'              => '#FFFFFF',
    '--admin-input-color'           => '#1A2837',
    '--admin-input-placeholder'     => '#8FA3B1',
    '--admin-input-border'          => '#DDE4EB',
    '--admin-input-focus-border'    => '#2C6E8A',
    '--admin-input-focus-shadow'    => 'rgba(44,110,138,0.12)',
    '--admin-input-hover-border'    => '#4DA8C9',
    '--admin-input-radius'          => '8px',
    '--admin-input-font-size'       => '13px',

    // ── Admin Textarea ────────────────────────────────────────────
    '--admin-textarea-bg'           => '#FFFFFF',
    '--admin-textarea-color'        => '#1A2837',
    '--admin-textarea-border'       => '#DDE4EB',
    '--admin-textarea-focus-border' => '#2C6E8A',
    '--admin-textarea-radius'       => '8px',

    // ── Admin Label ───────────────────────────────────────────────
    '--admin-label-color'           => '#4A6070',
    '--admin-label-font-size'       => '11px',
    '--admin-label-font-weight'     => '700',
    '--admin-label-transform'       => 'uppercase',
    '--admin-label-letter-spacing'  => '0.4px',
       // ── Admin Sync / Dashed Toolbar Button ─────────────────────────
    '--admin-btn-sync-bg'           => '#E3EFF4',
    '--admin-btn-sync-border'       => '#2C6E8A',
    '--admin-btn-sync-color'        => '#2C6E8A',

  
];