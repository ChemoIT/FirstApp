---
phase: 05-admin-update-delete-block-suspend
plan: 01
subsystem: api
tags: [php, supabase, postgrest, patch, delete, curl, hebrew-validation]

# Dependency graph
requires:
  - phase: 03-supabase-connection
    provides: supabase_request() helper with PATCH and DELETE support via CURLOPT_CUSTOMREQUEST
  - phase: 04-admin-read-create
    provides: create.php pattern (require_once, JSON body read, Hebrew error messages, JSON_UNESCAPED_UNICODE)
provides:
  - api/users/update.php — PATCH endpoint dispatching edit/block/suspend actions
  - api/users/delete.php — DELETE endpoint for user removal
affects:
  - 05-02 (admin UI plan — these endpoints are the backend admin.php will call via fetch())

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Action-dispatch pattern: single PATCH endpoint with `action` field routing to edit/block/suspend
    - intval() on user ID before URL concatenation (PostgREST injection prevention)
    - DateTime::createFromFormat for YYYY-MM-DD validation + future-date enforcement
    - JSON null sent in PATCH body clears TIMESTAMPTZ column (suspended_until = null on block)

key-files:
  created:
    - api/users/update.php
    - api/users/delete.php
  modified: []

key-decisions:
  - "Action dispatch in single update.php endpoint (edit/block/suspend) — keeps surface area small vs separate block.php/suspend.php"
  - "id_number excluded from edit action — identity documents are immutable after creation (RESEARCH.md Open Question 1)"
  - "block action always clears suspended_until to null — prevents stale date confusion on previously-suspended users"
  - "delete.php checks http_code === 204 (not 200) — DELETE returns 204 No Content per PostgREST spec"

patterns-established:
  - "PATCH = 200, DELETE = 204 — must check different success codes for different Supabase operations"
  - "suspended_until null-clear on block — PHP json_encode(null) maps to PostgreSQL NULL on TIMESTAMPTZ column"
  - "Future-date validation: DateTime::createFromFormat('Y-m-d') + $date <= new DateTime('today') guard"

# Metrics
duration: 2min
completed: 2026-02-28
---

# Phase 05 Plan 01: Admin Update/Delete API Endpoints Summary

**PHP PATCH endpoint (edit/block/suspend via action dispatch) and DELETE endpoint for user removal, both using supabase_request() with correct HTTP status code checks (200 for PATCH, 204 for DELETE)**

## Performance

- **Duration:** ~2 min
- **Started:** 2026-02-28T18:51:03Z
- **Completed:** 2026-02-28T18:52:43Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- `api/users/update.php` created: single PATCH endpoint handling three admin actions (edit, block, suspend) with full Hebrew server-side validation
- `api/users/delete.php` created: DELETE endpoint correctly checking HTTP 204 (not 200) per PostgREST specification
- Both endpoints follow the exact require_once, JSON body read, and JSON_UNESCAPED_UNICODE patterns established in create.php

## Task Commits

Each task was committed atomically:

1. **Task 1: Create api/users/update.php** - `2c77d74` (feat)
2. **Task 2: Create api/users/delete.php** - `74ba62c` (feat)

**Plan metadata:** (docs commit — see below)

## Files Created/Modified
- `api/users/update.php` — PATCH endpoint: action dispatch for edit (6-field validation), block (clears suspended_until), suspend (future-date validation via DateTime)
- `api/users/delete.php` — DELETE endpoint: calls supabase_request('DELETE'), checks http_code === 204 for success

## Decisions Made
- Single `update.php` endpoint with `action` field dispatch (edit/block/suspend) rather than separate files — smaller surface area, consistent entry point
- `id_number` excluded from the edit action — identity documents should not change after creation; adding it later would require duplicate-key handling (RESEARCH.md Open Question 1)
- `block` action sends `['status' => 'blocked', 'suspended_until' => null]` — PHP json_encode(null) becomes JSON null, which PostgREST maps to PostgreSQL NULL, clearing any stale suspend date (RESEARCH.md Pitfall 6)
- `delete.php` checks `http_code !== 204` not 200 — PostgREST returns 204 No Content for DELETE success (RESEARCH.md Pitfall 1)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None. Both files passed php -l syntax checks on first attempt. Pattern from create.php was directly applicable.

## User Setup Required

None - no external service configuration required. Both endpoints use the existing api/config.php credentials.

## Next Phase Readiness
- `api/users/update.php` and `api/users/delete.php` are ready for admin.php to call via `fetch()` (Plan 05-02)
- Plan 05-02 needs to: add action column to users table, wire Edit/Delete/Block/Suspend buttons, create Edit and Suspend modals, implement event delegation on `#users-tbody`
- No blockers

---
*Phase: 05-admin-update-delete-block-suspend*
*Completed: 2026-02-28*

## Self-Check: PASSED

| Item | Status |
|------|--------|
| api/users/update.php | FOUND |
| api/users/delete.php | FOUND |
| 05-01-SUMMARY.md | FOUND |
| Commit 2c77d74 (update.php) | FOUND |
| Commit 74ba62c (delete.php) | FOUND |
