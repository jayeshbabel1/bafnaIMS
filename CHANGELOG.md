# Changelog

All notable changes to Bafna Marble IMS are documented here.
Format loosely follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Historical entries (2.0.0 and earlier) are reconstructed from git history rather than
written at release time, so they're grouped at a coarser level than future entries will be.

## [Unreleased]

Work merged to `main` since 3.1.1, not yet tagged.

### Added
- i18n: `ui()`/`h()`-wrapped strings across inputs, buttons, filter labels, product page,
  page headings, and auth pages; Hindi/Gujarati/Marathi translations seeded; admin UI
  strings regrouped by page
- Marketing Queue Health: responsive table wrapping + RBAC/CSRF/throttle-gated
  "Repair & Optimize" action
- Admin: product list redesigned as card-style rows

### Changed
- User panel footer and support page now resolve the displayed version via `appVersion()`
  instead of a hardcoded constant; footer company name now pulled from company profile
  settings instead of being hardcoded

### Fixed
- Admin styling regressions introduced by an in-progress, later-reverted Bootstrap
  migration attempt

## [3.1.1] - 2026-09-09

### Added
- 3D room visualizer (Three.js) and Vue-based room visualizer variant
- Selection PDF generation from the 3D visualizer
- Catalog PDF management, with several follow-up bug fixes
- Watermarking, translations groundwork, and device trust groundwork
- Role-based access groundwork with an OWASP-driven review pass
- Licensing subsystem

### Changed
- General bug fixes and stability cleanup across the above subsystems

## [3.0.0] - 2026-07-01

### Added
- Client selections and shareable/responsive selection views
- Product zoom, admin logo management, catalog/product naming updates
- Admin panel design and responsiveness pass, including mobile hamburger nav
- Company profile settings in the admin panel
- Mailer integration, CSS and filter improvements
- PDF generation and general maintenance pass
- Sync functionality between admin and storefront data

## [2.0.0] - 2026-05-27

### Added
- Initial tracked release: core inventory management system — product catalog,
  admin panel, user panel, and supporting PHP/MySQL foundation
