---
phase: 04-admin-read-and-create
plan: 01
subsystem: api
tags: [php, supabase, postgrest, bcrypt, password_hash, json, hebrew]

# Dependency graph
requires:
  - phase: 03-database-foundation
    provides: api/supabase.php helper with dual-header auth and supabase_request() function
provides:
  - GET api/users/list.php — returns all users as JSON array (excludes password_hash at query level)
  - POST api/users/create.php — validates, bcrypt-hashes, inserts user into Supabase, returns created user without password_hash
affects:
  - 04-02 (admin UI Plan — both endpoints are the backend for admin.php)
  - 05-admin-update-delete (update.php and delete.php will follow the same api/users/ subdirectory pattern)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - api/users/ subdirectory pattern for grouping user management endpoints
    - __DIR__ . '/../' one-level-up require_once for PHP files in api subdirectories
    - password_hash(PASSWORD_DEFAULT) bcrypt before INSERT — PHP owns full password lifecycle
    - unset($created['password_hash']) before json_encode — never expose hash to client
    - filter_var(FILTER_VALIDATE_EMAIL) for server-side email validation
    - preg_match('/^\d+$/', $phone) for digits-only phone validation
    - gender whitelist with in_array(['male','female'], true) matching DB CHECK constraint
    - JSON_UNESCAPED_UNICODE on all json_encode calls for readable Hebrew in logs

key-files:
  created:
    - api/users/list.php
    - api/users/create.php
  modified: []

key-decisions:
  - "__DIR__ . '/../' (one level up) for require_once in api/users/ — RESEARCH.md example used two levels which was wrong; plan correctly identified the actual directory depth"
  - "password_hash field excluded from list.php SELECT query at API level — not filtered after fetch"
  - "HTTP 201 returned on successful user creation (matching PostgREST convention), not 200"
  - "Phone validation (digits-only) added server-side with preg_match in addition to planned validations"

patterns-established:
  - "Pattern: PHP subdirectory endpoints use __DIR__ . '/../' to reach sibling api/ files"
  - "Pattern: All json_encode calls include JSON_UNESCAPED_UNICODE flag (Hebrew-safe)"
  - "Pattern: password_hash excluded from SELECT at query level, not filtered post-fetch"
  - "Pattern: unset(password_hash) before json_encode on any endpoint that returns user rows"

# Metrics
duration: 2min
completed: 2026-02-28
---

# Phase 4 Plan 01: Admin API Endpoints Summary

**Two PHP endpoints that power the admin page: GET /api/users/list.php returns all users (bcrypt hash excluded at query level), POST /api/users/create.php validates 5 server-side rules, hashes with password_hash(PASSWORD_DEFAULT), inserts into Supabase with Prefer: return=representation, and strips password_hash before response**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-28T17:17:01Z
- **Completed:** 2026-02-28T17:19:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- Created `api/users/` subdirectory with two JSON endpoints consumable directly by curl
- `list.php` fetches all users from Supabase excluding password_hash at the SELECT level, ordered newest-first
- `create.php` implements 5 server-side validations (required fields, email format, password length, gender whitelist, phone digits-only), bcrypt-hashes the password, inserts via PostgREST with representation, and strips password_hash from the response

## Task Commits

Each task was committed atomically:

1. **Task 1: Create api/users/list.php — GET all users endpoint** - `fd81e3e` (feat)
2. **Task 2: Create api/users/create.php — POST new user endpoint** - `c3b69d7` (feat)

**Plan metadata:** (docs commit — see below)

## Files Created/Modified

- `api/users/list.php` — GET endpoint: calls supabase_request GET /users with column selection (no password_hash), returns {ok, users} or HTTP 500 with Hebrew error, rejects non-GET with 405
- `api/users/create.php` — POST endpoint: validates 5 rules server-side, hashes password, inserts into Supabase with Prefer: return=representation, returns {ok, user} with password_hash removed, handles duplicate key as 409

## Decisions Made

- **__DIR__ . '/../' vs __DIR__ . '/../../api/':** The RESEARCH.md example had incorrect path (two levels up to project root then re-entering api/). The plan correctly identified this. `api/users/` is one level below `api/`, so `__DIR__ . '/../config.php'` is the correct relative path. Verified against actual directory structure.
- **password_hash excluded from SELECT query in list.php:** Excluded at the PostgREST query level (`select=...` without password_hash) rather than fetching and filtering in PHP. This means the hash never crosses the network.
- **HTTP 201 on successful create:** create.php returns HTTP 201 (Created) on success — matching the PostgREST INSERT convention and more semantically correct than 200.
- **Phone digits-only validation added:** Plan specified this validation; implemented server-side with `preg_match('/^\d+$/', $phone)` returning 422 with Hebrew error if non-digits present.

## Deviations from Plan

None - plan executed exactly as written.

The one noted discrepancy (require_once path depth) was explicitly documented in the plan task action and resolved as specified.

## Issues Encountered

None. Both PHP files passed `php -l` syntax check on first write.

## User Setup Required

None - no external service configuration required. The endpoints connect to the existing Supabase project via `api/config.php` (already on server, already untracked in git).

## Next Phase Readiness

- Both endpoints are deployed via git push to cPanel (same deploy pipeline as Phase 3)
- `list.php` can be verified: `curl -s https://ch-ah.info/FirstApp/api/users/list.php` should return `{"ok":true,"users":[]}`
- `create.php` can be verified with curl POST — see plan verify steps
- Phase 04-02 (admin.php UI) is unblocked: it has a backend to fetch from and POST to
- **Pending blocker from STATE.md:** `api/test-supabase.php` may still exist on live server — should be deleted before any user-facing testing

---
*Phase: 04-admin-read-and-create*
*Completed: 2026-02-28*

## Self-Check: PASSED

- api/users/list.php: FOUND
- api/users/create.php: FOUND
- 04-01-SUMMARY.md: FOUND
- Commit fd81e3e (Task 1): FOUND
- Commit c3b69d7 (Task 2): FOUND
