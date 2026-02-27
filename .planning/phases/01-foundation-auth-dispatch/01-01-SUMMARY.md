---
phase: 01-foundation-auth-dispatch
plan: 01
subsystem: infra
tags: [php, apache, htaccess, css, rtl, hebrew, config]

# Dependency graph
requires: []
provides:
  - "api/config.php — centralized constants: MICROPAY_TOKEN, ADMIN_PHONE, BASE_URL, ADMIN_USER, ADMIN_PASS"
  - "Root .htaccess — HTTPS enforcement (301 redirect) and directory listing prevention"
  - "signatures/.htaccess — direct access denial for PNG signature files (dual Apache 2.2/2.4 syntax)"
  - "css/style.css — RTL Hebrew base styles: layout, form, button, error/success messages"
affects: [02-auth, 03-dispatch, all-php-endpoints, all-html-pages]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Config isolation: all secrets in api/config.php, included via require_once __DIR__ . '/config.php'"
    - "No closing ?> tag in PHP-only files to prevent accidental trailing newline (session header issue)"
    - "Dual Apache syntax (.htaccess) for signatures/ — covers both Apache 2.2 and 2.4"
    - "RTL Hebrew CSS: direction rtl + text-align right on body, 16px minimum font on inputs"

key-files:
  created:
    - api/config.php
    - .htaccess
    - signatures/.htaccess
    - css/style.css
  modified: []

key-decisions:
  - "No closing ?> in api/config.php — prevents trailing newline causing 'headers already sent' on session_start()"
  - "Dual Apache syntax in signatures/.htaccess — covers both Apache 2.2 (legacy) and 2.4+ (current cPanel)"
  - "CSS sets 16px minimum on form inputs — prevents iOS auto-zoom on focus"

patterns-established:
  - "Pattern: PHP-only files start with <?php as first bytes, no BOM, no closing ?>"
  - "Pattern: Apache dual-syntax wrapper for maximum cPanel compatibility"
  - "Pattern: RTL CSS base — direction:rtl on body, text-align:right, Segoe UI/Tahoma/Arial font stack"

# Metrics
duration: 1min
completed: 2026-02-27
---

# Phase 1 Plan 01: Foundation — Config, Security, and RTL Styles Summary

**Centralized PHP config with 5 constants (Micropay token, admin credentials, base URL), Apache HTTPS enforcement, signatures directory protection, and RTL Hebrew CSS foundation**

## Performance

- **Duration:** 1 min
- **Started:** 2026-02-27T22:18:05Z
- **Completed:** 2026-02-27T22:19:24Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments
- api/config.php created as the single source of truth for all credentials — MICROPAY_TOKEN, ADMIN_PHONE, BASE_URL, ADMIN_USER, ADMIN_PASS — each as a define() constant
- Root .htaccess enforces HTTPS via 301 redirect and disables directory listing with Options -Indexes
- signatures/.htaccess blocks all direct browser access to signature PNGs using dual Apache 2.2/2.4 syntax
- css/style.css provides complete RTL Hebrew base styles: layout container, form inputs (16px to prevent iOS zoom), primary action button, error/success message components with show/hide pattern

## Task Commits

Each task was committed atomically:

1. **Task 1: Create api/config.php and signatures directory with .htaccess protection** - `6780aaa` (feat)
2. **Task 2: Create root .htaccess and RTL Hebrew CSS stylesheet** - `1c3f993` (feat)

**Plan metadata:** *(final docs commit — see below)*

## Files Created/Modified
- `api/config.php` — All 5 application constants/secrets, no output, no closing ?>
- `signatures/.htaccess` — Dual-syntax directory access denial (Apache 2.2 and 2.4)
- `.htaccess` — HTTPS 301 redirect and Options -Indexes
- `css/style.css` — RTL Hebrew base styles (164 lines): reset, layout, form, button, error/success messages, mobile breakpoint

## Decisions Made
- No closing `?>` tag in api/config.php — follows PHP best practice to prevent accidental trailing newlines that would trigger "headers already sent" when session_start() is called later
- Dual Apache syntax wrapper for signatures/.htaccess — covers both Apache 2.2 legacy syntax (Order deny,allow) and Apache 2.4+ current syntax (Require all denied) for maximum cPanel compatibility
- CSS input font-size set to 16px minimum — prevents iOS Safari from auto-zooming on form field focus

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required for this plan. Files are ready to be deployed to cPanel via FTP.

## Next Phase Readiness
- Foundation complete: all 4 files exist and match the required architecture from RESEARCH.md
- Plan 02 (auth) can now create api/login.php and api/check-session.php using `require_once __DIR__ . '/config.php'`
- Plan 03 (dispatch) can use MICROPAY_TOKEN, ADMIN_PHONE, and BASE_URL constants from config.php
- CSS is ready for index.html and dashboard.html — container, form, button, error/success classes are all defined
- No blockers for Phase 1 continuation

---
*Phase: 01-foundation-auth-dispatch*
*Completed: 2026-02-27*

## Self-Check: PASSED

All files verified present on disk. All commits verified in git log.

| Check | Result |
|-------|--------|
| api/config.php | FOUND |
| .htaccess | FOUND |
| signatures/.htaccess | FOUND |
| css/style.css | FOUND |
| 01-01-SUMMARY.md | FOUND |
| commit 6780aaa | FOUND |
| commit 1c3f993 | FOUND |
