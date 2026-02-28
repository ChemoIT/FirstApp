---
phase: 04-admin-read-and-create
plan: 02
subsystem: ui
tags: [php, bootstrap-rtl, hebrew, crypto-getRandomValues, fetch-api, live-search, form-validation]

# Dependency graph
requires:
  - phase: 04-admin-read-and-create
    plan: 01
    provides: api/users/list.php and api/users/create.php — the two JSON endpoints admin.php consumes
provides:
  - admin.php — Hebrew RTL admin page at /FirstApp/admin.php with user table, create form, and search
affects:
  - 05-admin-update-delete (admin.php will need edit/delete action buttons added to each table row)

# Tech tracking
tech-stack:
  added:
    - Bootstrap 5.3 RTL CSS via CDN (bootstrap.rtl.min.css)
    - Bootstrap 5.3 JS bundle via CDN
  patterns:
    - Bootstrap 5.3 RTL via CDN — html lang="he" dir="rtl" with bootstrap.rtl.min.css
    - ES5 .then() chain pattern for all fetch() calls (consistent with index.html)
    - crypto.getRandomValues(Uint8Array) for secure client-side password generation
    - Live search via input event on textContent.toLowerCase().includes(query)
    - Frontend validation (validateForm()) before fetch, server error displayed in #form-error on !data.ok
    - loadUsers() called on DOMContentLoaded and again after successful create (no page reload)

key-files:
  created:
    - admin.php
  modified: []

key-decisions:
  - "Bootstrap loaded FIRST then css/style.css — Bootstrap RTL classes take priority; style.css provides fonts and colors only"
  - "container-lg used (not container) to avoid 400px max-width constraint from existing style.css"
  - "Password generator uses crypto.getRandomValues — no Math.random() — 12 chars from charset excluding ambiguous characters"
  - "Password field type toggled to text on generate so admin can see and copy the generated value"
  - "ES5 .then() chains used for all async — no async/await — to match existing codebase pattern (index.html)"
  - "Status badge translation: active=bg-success, blocked=bg-danger, suspended=bg-warning"

patterns-established:
  - "Pattern: loadUsers() as reusable function called on DOMContentLoaded and post-create — single source of truth for table state"
  - "Pattern: validateForm(data) extracts all validation into one function returning error string or null"
  - "Pattern: fetch().then().catch().finally() — re-enable submit button in finally to avoid stuck disabled state"
  - "Pattern: Bootstrap badge for status values in table cells (not raw text)"

# Metrics
duration: ~15min
completed: 2026-02-28
---

# Phase 4 Plan 02: Admin UI Summary

**Bootstrap 5.3 RTL admin.php (303 lines) with Hebrew user table, live search, create form with JS validation, and crypto.getRandomValues password generator — all 10 human-verification checkpoints approved by Sharon**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-02-28T17:22:00Z
- **Completed:** 2026-02-28T17:37:00Z (checkpoint approved)
- **Tasks:** 2 (1 auto + 1 human-verify checkpoint)
- **Files modified:** 1

## Accomplishments

- Created `admin.php` (303 lines) with full Bootstrap 5.3 RTL layout — Hebrew headings, RTL form controls, `dir="rtl"` on `<html>`
- User table populated from `api/users/list.php` on DOMContentLoaded; status shown as Bootstrap badges (פעיל/חסום/מושעה); empty state shows Hebrew fallback row
- Create form covers all 8 fields required by ADMIN-02 with frontend email regex check, password min-8 chars, and all labels in Hebrew (ADMIN-03, ADMIN-04, ADMIN-12)
- Password generator button uses `crypto.getRandomValues` with ambiguous-char-free 64-char charset, reveals generated value in field (ADMIN-05)
- Live search filters table rows on every keypress with no server round-trip (ADMIN-07)
- Phone field strips non-digits on input event (ADMIN-02 digits-only requirement)
- Human verification: all 10 checkpoints confirmed by Sharon — user creation, Supabase bcrypt hash, duplicate email error, frontend validation errors, generator, search

## Task Commits

Each task was committed atomically:

1. **Task 1: Create admin.php — full admin page with table, search, form, and password generator** - `ca9b55a` (feat)
2. **Task 2: Verify admin page end-to-end (checkpoint)** - human verification — no commit (checkpoint approval)

**Plan metadata:** `71dfb7c` (chore: STATE.md update at checkpoint), docs commit below (this summary)

## Files Created/Modified

- `admin.php` — 303-line PHP/HTML/JS page: Bootstrap 5.3 RTL, user table (#users-table / #users-tbody), live search (#search-box), create form (#create-form), password generator (#generate-pw-btn), error/success divs (#form-error / #form-success), inline JS with loadUsers(), validateForm(), phone strip, and fetch submit handler

## Decisions Made

- **Bootstrap RTL via CDN first, then css/style.css:** Bootstrap's RTL stylesheet must load before the project's style.css to prevent the 400px `.container` max-width from overriding `.container-lg`. Using `container-lg` in admin.php sidesteps this entirely.
- **ES5 .then() chains throughout:** The existing `index.html` uses this pattern. Kept consistent to avoid introducing `async/await` to a codebase that does not use it.
- **Password field type toggle on generate:** When the generator button is clicked, the field type changes from `password` to `text` so the admin can see and copy the value. This is an expected UX for an admin-only page.
- **crypto.getRandomValues for password generation:** Uses browser-native CSPRNG instead of Math.random() — matches ADMIN-05 security requirement.

## Deviations from Plan

None - plan executed exactly as written.

All 8 JS behaviors, all 8 form fields, all Bootstrap classes, and all Hebrew strings were implemented as specified in the plan task action.

## Issues Encountered

None. `php -l admin.php` passed on first write. Human-verification checkpoint was approved in full.

## User Setup Required

None - no external service configuration required. admin.php connects to existing API endpoints and Supabase config already on the server.

## Next Phase Readiness

- `admin.php` is live at `https://ch-ah.info/FirstApp/admin.php`
- Phase 04 is fully complete — both API endpoints (plan 01) and the admin UI (plan 02) verified in production
- Phase 05 (admin update/delete) is unblocked: it will add edit/delete action buttons to each `<tr>` in `#users-tbody` using the same loadUsers() refresh pattern
- Pending blocker from STATE.md (test-supabase.php on live server) remains — no change from plan 01 state

---
*Phase: 04-admin-read-and-create*
*Completed: 2026-02-28*

## Self-Check: PASSED

- admin.php: FOUND
- .planning/phases/04-admin-read-and-create/04-02-SUMMARY.md: FOUND
- Commit ca9b55a (Task 1 - admin.php create): FOUND
- Commit 71dfb7c (checkpoint STATE update): FOUND
