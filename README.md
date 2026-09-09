# Bafna Marble IMS — Project Context

> **Purpose of this file:** a single reference document for anyone (or any AI assistant) picking up work on this codebase. It captures architecture, conventions, current state, and hard-won gotchas so context doesn't have to be rebuilt from scratch every session.
>
> **Provenance note:** this document was compiled from accumulated working memory of the project across past sessions, cross-checked against the current file inventory. It has not been re-verified line-by-line against live source in this pass — treat specifics (function names, exact line-level behavior) as "last known state" and confirm against the actual file before relying on them for a patch.

---

## 1. Overview

**Bafna Marble IMS** is a production PHP/MySQL web application for a marble/stone slab business, live at `shop.bafnamarble.com` (cPanel host, username `bmarble`). It has two panels sharing a codebase:

- **User-facing portal** — clients browse the catalog, build shortlists/selections, view products, request catalogs.
- **Admin panel** — staff manage products, clients, marketing, catalogs, licensing, and system settings.

The project is a mature, ongoing codebase. Work is primarily iterative: feature additions, bug fixes, security hardening, and performance tuning — not greenfield development.

---

## 2. Tech Stack

| Layer | Technology |
|---|---|
| Backend | Procedural PHP, PDO (prepared statements throughout) |
| Database | MySQL |
| PDF generation | TCPDF |
| Frontend | Vanilla JavaScript, custom multi-breakpoint responsive CSS |
| 3D / visualization | Three.js (room visualizer), a parallel Vue-based room visualizer implementation |
| Email | PHPMailer / SMTP via `includes/mailer.php` |
| Hosting | Apache, cPanel, SSH/CLI access available for scripts |

---

## 3. Architecture

- **Routing:** page-based, not a framework router. `index.php` dispatches user-panel pages from `/pages`; `admin/index.php` dispatches admin views from `/admin/views`.
- **RBAC:** `adminCan()` / `requireAdminPermission()` (see `includes/rbac.php`) gate admin actions. New features get permission entries auto-seeded via `ensure*PermissionSeeded()` functions, auto-granted to `super_admin`, and manageable in the Roles & Permissions UI (`admin/views/roles.php`).
- **CSRF:** `csrfField()` / `csrfVerify()` on every form/handler.
- **Settings:** file-based cache at `storage/cache/settings.php`, backed by DB, upserted via a `setSettings()` / `ON DUPLICATE KEY UPDATE` pattern.
- **Sessions/security:** `session_version` column on both `admins` and `users` tables allows server-side session invalidation (role/permission change, password update) without forcing logout.
- **Device trust:** `includes/device_auth.php` — trusted-device cookie mechanism, currently has a known bug (see §7).
- **Licensing:** `includes/license.php`, `includes/license_caps.php`, admin view at `admin/views/license.php`, bootstrap script `admin/scripts/generate_initial_license.php`.

### Marketing subsystem (large, multi-file)
A full marketing module lives mostly under `includes/marketing*.php` and `admin/views/marketing_*.php`:
- Contacts & groups (`marketing_contacts.php`, `marketing_groups` view)
- Campaigns & automation (`marketing_campaigns.php`, `marketing_automation.php`, campaign/automation wizards)
- Channels: email (`marketing_email.php`, `marketing/SmtpEmailProvider.php`) and WhatsApp (`marketing_whatsapp.php`, `marketing/WhatsAppCloudProvider.php`), unified behind `includes/marketing/ProviderInterface.php`
- Queue processing (`marketing_queue_worker.php`, `admin/views/marketing_queue_health.php`)
- Tracking/analytics (`marketing_tracking.php`, `marketing_analytics.php`, `marketing_performance.php`)
- Standalone public entry points: `marketing_unsubscribe.php`, `marketing_webhook.php`, `marketing_track.php`, `marketing_attachment.php` (these are **outside** `index.php` routing — see bootstrap convention in §5)
- Ops tooling: `tools/marketing_load_test.php`, `tools/marketing_apply_indexes.php`

### Catalog PDF engine
`includes/catalog_pdf_engine.php` is the canonical PDF generator (cover page, header/footer, colors, closing page, fonts, email share, limits — all configurable). `includes/product_pdf.php` is now a thin wrapper over it (see §7 for consolidation history). `includes/catalog_pdf.php` handles the catalog draft lifecycle.

### Room visualizer
Two parallel implementations coexist: a Three.js version (`room_visualizer_three.js`) and a Vue-based version (`room_visualizer.vue.js`, `vue.runtime.global.prod.js`, `room_templates.vue.js`). Backed by `includes/room_visualizer.php`, admin config at `admin/views/room_templates.php`.

---

## 4. Directory Structure (file inventory)

```
/ (root)
├── index.php                      # user-panel router
├── marketing_unsubscribe.php      # standalone entry point
├── marketing_webhook.php          # standalone entry point
├── marketing_track.php            # standalone entry point
├── marketing_attachment.php       # standalone entry point
│
├── admin/
│   ├── index.php                  # admin-panel router
│   ├── colors.php
│   ├── check_room_visualizer.php
│   ├── php.ini
│   ├── _layout_top.php
│   ├── _layout_bottom.php
│   ├── scripts/                   # CLI/admin-gated utility scripts
│   │   ├── debug_photo.php
│   │   ├── fix_folder_casing.php
│   │   ├── generate_initial_license.php
│   │   ├── normalize_photo_filenames.php
│   │   └── backfill_thumbnails.php
│   └── views/                     # admin page templates (~45 files)
│       ├── dashboard.php, login.php, 404.php, _403.php
│       ├── products.php, product_edit.php, product_categories.php, product_view_settings.php
│       ├── admin_clients.php, admin_client_form.php, admin_client_selections.php, user_clients.php
│       ├── admin_accounts.php, roles.php, users.php, translations.php, license.php, smtp.php, sync.php
│       ├── devices.php, notifications.php, colors.php, logo.php, room_templates.php
│       ├── catalog_pdf_settings.php, catalog_pdf_templates.php, catalog_pdf_wizard.php, catalog_pdf_history.php
│       ├── marketing_* (contacts, groups, campaigns, campaign_wizard, automations,
│       │                automation_builder, templates, analytics, queue_health,
│       │                settings_email, settings_whatsapp, contact_profile)
│       ├── inquiries.php           # ⚠ STALE — feature removed, see §8
│       └── _*_rows.php            # partials for AJAX/pagination row rendering
│
├── config/
│   ├── config.php                 # bootstrap: constants, env
│   └── colors.php
│
├── includes/                       # business logic / shared functions (~55 files)
│   ├── auth.php, rbac.php, helpers.php, db.php, check.php
│   ├── device_auth.php, license.php, license_caps.php
│   ├── catalog_pdf_engine.php, catalog_pdf.php, product_pdf.php, product_views.php
│   ├── clients.php, categories.php, notifications.php, translations.php
│   ├── search_history.php, selection_history.php, image_upload.php, watermark.php
│   ├── slab_calculator.php, room_visualizer.php, wa_share.php, cloudinary.php, mailer.php
│   ├── marketing.php, marketing_attachments.php, marketing_contacts.php, marketing_variables.php,
│   │   marketing_email.php, marketing_whatsapp.php, marketing_performance.php,
│   │   marketing_analytics.php, marketing_tracking.php, marketing_scheduler.php,
│   │   marketing_templates.php, marketing_campaigns.php, marketing_catalog_integration.php,
│   │   marketing_automation.php, marketing_queue_worker.php, marketing_webhook_handler.php
│   └── marketing/
│       ├── ProviderInterface.php
│       ├── SmtpEmailProvider.php
│       └── WhatsAppCloudProvider.php
│
├── layouts/
│   ├── header.php, footer.php
│   └── auth_layout.php, auth_footer.php
│
├── pages/                           # user-panel page templates (~22 files)
│   ├── login.php, register.php, forgot_password.php, reset_password.php, activation.php, waiting_approval.php
│   ├── profile.php, devices.php, support.php, notifications.php, 404.php
│   ├── catalog.php, product.php, shortlist.php, room_visualizer.php
│   ├── clients.php, client_form.php, client_selections.php, _clients_rows.php, _selection_rows.php
│   ├── _wa_share_modal_user.php
│   ├── inquiries.php               # ⚠ STALE — feature removed, see §8
│   └── inquiry_form.php            # ⚠ STALE — feature removed, see §8
│
├── assets/
│   ├── css/  (zoom, watermark, watermark1, auth, clients, room_visualizer,
│   │          room_visualizer.vue, style, selection_history, admin)
│   └── js/   (admin.logo, zoom, room_visualizer, room_visualizer.vue,
│              vue.runtime.global.prod, room_templates.vue, admin, app, product,
│              pagination, catalog, selection_history, room_visualizer_three,
│              slab_calculator, admin.products)
│
└── tools/
    ├── marketing_load_test.php
    ├── marketing_apply_indexes.php
    └── generate_font.php
```

---

## 5. Core Conventions & Patterns

These are binding conventions for any new code, not just observations:

- **Zero code duplication** — audit existing modules for reuse before writing anything new. Parallel implementations get consolidated when found (see product PDF consolidation in §7).
- **Full feature parity** between admin and user panels for any shared capability.
- **Security on every handler** — CSRF (`csrfField()`/`csrfVerify()`), XSS escaping, PDO prepared statements (no raw SQL interpolation), input validation.
- **Patch sequencing rule** — if a file exceeds ~30% changed lines, deliver a full rewrite rather than a diff/patch.
- **Admin utility scripts** must be gated with `isAdmin()`; the accepted automation workaround is a `PHP_SAPI !== 'cli'` bypass for CLI-run scripts.
- **Bootstrap require chain** — any standalone PHP entry point (outside `index.php`/`admin/index.php` routing, e.g. `marketing_webhook.php`, `marketing_track.php`) must include, in order: `config/config.php`, `includes/db.php`, `includes/helpers.php`, plus `includes/auth.php` if sessions are used.
- **RBAC seeding** — new admin features register their own permission via `ensure*PermissionSeeded()`.
- **Pagination** — `#paginationWrap` + `assets/js/pagination.js` (`initPagination()`) is the *only* canonical mechanism for list views. The `.admin-pagination` class on `#paginationWrap` is required for correct flex layout. Use `setWrapEl()` on the returned object when pagination re-renders to avoid stale closure bugs.
- **Multi-language strings** — `ui()` lookups must index the cache by `entity_id`, not `field_key` (the latter is always literally `'value'` for `ui_strings` rows and will collapse the cache into one slot if used as the index).
- **Fixed-position dropdown submenus** inside scrollable flex containers must use `position:fixed` with coordinates from `getBoundingClientRect()` in JS (never `position:absolute`, which gets clipped), with a mobile override back to `position:static` for accordion behavior.
- **Open redirects** must go through a `safeReturnUrl()` helper enforcing relative-only paths.
- **`display_errors`** must never be force-enabled in production code paths.
- **Throttle logic** (`registerLoginFailure()`) must only ever be called on the failed-attempt path — calling it on success silently blocks subsequent legitimate actions.

### `/caveman` mode
A recognized working mode for this project: deep audit first, explicit scope confirmation before touching code, sequential numbered patches delivered one at a time, test checklists after each patch. Confirmations are typically terse ("confirm", "Continue", "Next patch"). Diagnostic output is shared as raw logs/descriptions rather than paraphrased interpretation.

### TCPDF gotchas (catalog/product PDF work)
- `$pdf->Image()` calls are **not finalized** until `$pdf->Output()` runs. Deleting temp image files immediately after `Image()` is called will break rendering — use a registry pattern (e.g. `$GLOBALS['_cpeTempFiles']`) and defer cleanup until after `Output('', 'S')` (and in the catch block).
- Temp files must be saved with the extension matching their **actual** byte format — writing JPEG bytes to a `.png`/`.webp` path gets silently rejected by TCPDF.
- Loop variables used for aspect-ratio clamping must be reset to their base value at the top of **every** iteration, or mutation from a prior iteration leaks forward.

---

## 6. Known Issues / Technical Debt

1. **⚠ Stale inquiries files present in the repo/knowledge base.** The inquiries feature was fully removed from the product, but `pages/inquiries.php`, `pages/inquiry_form.php`, and `admin/views/inquiries.php` still exist as files (and were re-uploaded into project knowledge). Per standing decision: **do not reference, edit, or recreate** anything inquiries-related. These files should ideally be deleted from the server and excluded from future project-knowledge uploads to avoid confusing future audits.
2. **Trusted Devices `last_seen` not confirmed fixed.** `touchTrustedDeviceLastSeen()` was patched into `admin/_layout_top.php` but never confirmed working end-to-end. Leading theory: the `trusted_device` cookie name is shared between the admin and user panels, so whichever panel last issued the cookie overwrites the other — causing `getCurrentTrustedDevice('admin')` to silently return `null`. `error_log()` instrumentation (`device_touch:` prefix) was added but log output was never reviewed to confirm the failure mode. A parallel gap exists in the user panel (`layouts/header.php`) pending the same fix once root cause is confirmed.
3. **`marketing_unsubscribe.php` bootstrap error** — diagnosed as a missing require chain (`config/config.php`, `includes/db.php`, `includes/helpers.php`) at the top of the standalone file. A fix was written but not yet confirmed against the actual file contents/paths.
4. **Catalog PDF engine audit in progress** — 13 findings identified across cover page, header, footer, colors, closing page, font, email share, and limits settings. Only patch 1 (footer: Phone toggle, Generated Date toggle, Page Number Position dropdown) has been started; patches 2–13 are outstanding.
5. **Two parallel room-visualizer implementations** (Three.js vs Vue) coexist — worth revisiting for consolidation under the zero-duplication principle if both are still actively maintained.

---

## 7. Recently Completed

- **Product PDF consolidation:** `includes/product_pdf.php` rewritten as a thin wrapper over `catalog_pdf_engine.php`; seven dead functions removed; both admin and user panels now call the single `generateProductPdf()` function.

---

## 8. Roadmap / Backlog

**High priority (flagged as biggest gaps):**
- **Pricing/quotation module** — products currently track quantity but have no price field at all. Highest-priority missing feature.
- **CRM pipeline for clients** — identified as a high-impact gap.
- **Shareable client selection links** — missing feature.

**Admin panel enhancements (scoped, not yet built):**
- Bulk product actions
- Inline quick-edit for table rows
- Global unified search
- Low-stock alerts
- Internal admin notes on clients/products

**Feature upgrades on the horizon:**
- Enhanced dashboard analytics with trend charts
- PWA / offline support
- Notification email digests
- Product comparison view
- Dark mode (color token system already in place — see `config/colors.php`, `admin/colors.php`)
- Duplicate-product action
- Recently-viewed products
- 2FA for admin accounts

---

## 9. Working Agreement / How Sessions on This Project Run

- **Audit-first workflow:** review existing patterns → identify gaps/bugs → present findings → wait for scope confirmation → deliver patches sequentially, one at a time.
- **Rewrite threshold:** >30% of a file's lines changed → full rewrite, not a diff.
- **Confirmation style:** short acknowledgements ("confirm", "Continue", "Next patch") move work forward; raw logs/screenshots are provided rather than paraphrased summaries.
- **Diagnostics:** `error_log()` with a consistent prefix tag (e.g. `catalog_pdf:`, `device_touch:`) is the standard debugging instrument; these are sometimes left in place as permanent structured logging rather than stripped out afterward.
- **SQL conventions:** PDO prepared statements everywhere; upserts follow the `setSettings()` `ON DUPLICATE KEY UPDATE` pattern; schema bootstrapping uses idempotent `ensure*()` functions.

---

## 10. Suggested Next Steps

1. Remove the stale inquiries files from the server and from Project Knowledge uploads.
2. Confirm the Trusted Devices fix by reviewing `device_touch:` log lines, then apply the same cookie-scoping fix to `layouts/header.php` for the user panel.
3. Confirm and apply the `marketing_unsubscribe.php` bootstrap fix against real file contents.
4. Resume the Catalog PDF engine audit at patch 2 of 13.
5. Re-upload full, non-empty file contents to Project Knowledge so future sessions (including this one) can work from real source rather than memory alone — the current knowledge upload came through with file paths but no code content.
