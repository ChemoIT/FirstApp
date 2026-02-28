---
phase: 03-database-foundation
plan: 01
subsystem: database
tags: [supabase, postgresql, php, curl, credentials]

# Dependency graph
requires:
  - phase: 01-foundation
    provides: api/config.php credential pattern (Micropay cURL style)
provides:
  - public.users PostgreSQL table in Supabase (13 columns, updated_at trigger)
  - api/supabase.php — reusable cURL helper with dual-header service_role auth
  - api/config.php — untracked credentials file with SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY
  - .gitignore preventing api/config.php from ever being committed
affects:
  - 04-registration
  - 05-user-management
  - 06-login-replacement

# Tech tracking
tech-stack:
  added: [Supabase PostgreSQL (hosted), PHP cURL Supabase REST client]
  patterns: [dual-header service_role auth (apikey + Authorization: Bearer), untracked credentials file, require_once config pattern]

key-files:
  created:
    - .gitignore
    - api/supabase.php
  modified:
    - api/config.php (untracked — Supabase constants appended)

key-decisions:
  - "Dual-header auth required: both apikey and Authorization: Bearer headers must be set for service_role key to bypass RLS"
  - "api/config.php kept untracked via .gitignore — credentials never touch git history"
  - "test-supabase.php deleted from repo after verification to prevent credential exposure via public URL"

patterns-established:
  - "Pattern: All Supabase calls go through supabase_request() in api/supabase.php — never call Supabase REST directly from feature files"
  - "Pattern: require_once __DIR__ . '/supabase.php' is the standard include path from any file in api/"
  - "Pattern: Return format is always ['data' => ..., 'http_code' => int, 'error' => string|null]"

# Metrics
duration: ~45min (across two sessions including human-action checkpoint)
completed: 2026-02-28
---

# Phase 3 Plan 01: Database Foundation Summary

**Supabase public.users table live on PostgreSQL with PHP cURL dual-header service_role auth returning HTTP 200 from ch-ah.info**

## Performance

- **Duration:** ~45 min (two sessions, including manual setup checkpoint)
- **Started:** 2026-02-28
- **Completed:** 2026-02-28
- **Tasks:** 3 (1 auto, 1 human-action, 1 human-verify)
- **Files modified:** 4 (.gitignore created, api/config.php updated untracked, api/supabase.php created, api/test-supabase.php created then deleted)

## Accomplishments

- `public.users` table created in Supabase with 13 columns and an `updated_at` auto-update trigger
- `api/supabase.php` cURL helper implemented with dual-header service_role auth — this is the single shared gateway for all future Supabase calls
- `api/config.php` updated with live Supabase credentials, kept permanently untracked via `.gitignore`
- Live connection verified: GET /rest/v1/users returned `{"data":[],"http_code":200,"error":null}` from production server

## Task Commits

Each task was committed atomically:

1. **Task 1a: Create .gitignore + untrack config.php** - `2dd1afd` (chore)
2. **Task 1b: Add Supabase cURL helper and test script** - `c08efb5` (feat)
3. **Task 2: Sharon creates Supabase table + credentials** - MANUAL (no commit — untracked file)
4. **Task 3 cleanup: Remove test-supabase.php after verification** - `afaf3a0` (chore)

**Plan metadata:** (this commit — docs)

## Files Created/Modified

- `.gitignore` - Prevents api/config.php from being committed; also covers OS/editor noise
- `api/supabase.php` - Reusable cURL helper: `supabase_request(method, path, body, prefer_rep)` with dual-header auth
- `api/config.php` - Untracked; Supabase URL and service_role key appended (credentials live here, never in git)
- `api/test-supabase.php` - Temporary test script; deleted from repo after verification (`afaf3a0`)

## Decisions Made

- **Dual-header requirement confirmed in production:** The `Authorization: Bearer` header is the one that actually bypasses RLS — `apikey` alone is insufficient. Both must be sent for service_role access to work.
- **test-supabase.php removed immediately after verification:** The file exposes the Supabase REST endpoint to anyone who guesses the URL. Keeping it would be a security exposure even with service_role key in config.php (which is server-side only).
- **No Supabase GoTrue / Auth SDK used:** PHP session auth is retained from v1.0. Supabase is used purely as a PostgreSQL host via REST API. This keeps authentication logic entirely in PHP.

## Deviations from Plan

None — plan executed exactly as written. All tasks completed in the specified order. The dual-header auth pattern was pre-specified in 03-RESEARCH.md and confirmed working in production.

## Issues Encountered

None. The live connection test passed on first attempt with `{"data":[],"http_code":200,"error":null}`.

## User Setup Required

**Manual action still needed:** Delete `api/test-supabase.php` from the live server via **cPanel File Manager**.

The file has been removed from the git repo (`afaf3a0`) and will not be re-deployed by CI/CD. However, the copy that was previously deployed to `public_html/FirstApp/api/test-supabase.php` is still on the server. Sharon must delete it manually:

1. Open cPanel File Manager
2. Navigate to `public_html/FirstApp/api/`
3. Delete `test-supabase.php`

This is a security step — the file publicly exposes the Supabase connection endpoint.

## Self-Check: PASSED

All claims verified:

- FOUND: `.gitignore` exists on disk
- FOUND: `api/supabase.php` exists on disk
- FOUND: `api/config.php` exists on disk (untracked — correct)
- CONFIRMED DELETED: `api/test-supabase.php` not present in working directory
- FOUND: commit `2dd1afd` (chore: .gitignore + untrack config.php)
- FOUND: commit `c08efb5` (feat: supabase.php + test script)
- FOUND: commit `afaf3a0` (chore: remove test-supabase.php)

## Next Phase Readiness

- Supabase connection layer is complete and verified. Phase 4 (Registration) can `require_once __DIR__ . '/supabase.php'` and call `supabase_request('POST', '/users', $data, true)` immediately.
- The `users` table is ready to receive INSERT operations (0 rows currently — correct starting state).
- No blockers for Phase 4.

---
*Phase: 03-database-foundation*
*Plan: 01*
*Completed: 2026-02-28*
