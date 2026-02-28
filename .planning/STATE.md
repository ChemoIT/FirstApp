# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-28)

**Core value:** A user can dispatch a signature request via SMS and receive a signed document back — the full send-sign-confirm loop must work end to end.
**Current focus:** v2.0 Phase 5 — Admin Update, Delete, Block, Suspend

## Current Position

Milestone: v2.0 User Management
Phase: 5 of 6 (Admin Update, Delete, Block, Suspend) — IN PROGRESS
Plan: Phase 05 Plan 01 complete (API endpoints: update.php + delete.php). Next: Plan 05-02 (admin UI actions)
Status: update.php and delete.php created, syntax-verified, committed. Plan 05-01 done.
Last activity: 2026-02-28 — Phase 05-01 complete; two PHP endpoints created and committed

Progress: [██████░░░░] 70% (v1.0 complete; v2.0 phases 3-5 in progress, phase 6 pending)

## Performance Metrics

**v1.0 Summary:**
- Phases: 2 | Plans: 5 | LOC: 798
- Total execution time: ~8min
- Timeline: 1 day (2026-02-27 → 2026-02-28)

**v2.0 Summary:**
- Phases: 4 (phases 3-6) | Plans: TBD | LOC: TBD
- Total execution time: ~2min so far

| Phase | Plan | Duration | Tasks | Files |
|-------|------|----------|-------|-------|
| 04    | 01   | 2min     | 2     | 2     |
| 04    | 02   | ~15min   | 2     | 1     |
| 05    | 01   | 2min     | 2     | 2     |

## Accumulated Context

### Decisions

All v1.0 decisions logged in PROJECT.md Key Decisions table.

Key v2.0 decisions (pre-build):
- Custom `public.users` table over Supabase GoTrue Auth — keeps auth in PHP sessions, no JWT complexity
- PHP cURL for all Supabase calls — same pattern as Micropay, service_role key stays server-side
- `password_hash()` / `password_verify()` — PHP owns the full password lifecycle, no third-party auth library
- Login replacement built LAST (Phase 6) — requires a real Supabase user to test; highest risk to existing v1.0 flow

Phase 3 Plan 01 decisions (confirmed in production):
- Dual-header auth required: both `apikey` and `Authorization: Bearer` headers must be sent for service_role to bypass RLS
- api/config.php permanently untracked via .gitignore — credentials never enter git history
- test-supabase.php deleted immediately after verification — security exposure via public URL

Phase 4 Plan 01 decisions:
- `__DIR__ . '/../'` (one level up) for require_once in api/users/ — RESEARCH.md example had wrong depth; plan was correct
- password_hash excluded from SELECT query in list.php at query level (not filtered post-fetch) — hash never crosses network
- HTTP 201 returned on successful user creation (matching PostgREST INSERT convention)
- Phone digits-only validation enforced server-side with preg_match('/^\d+$/', $phone)

Phase 4 Plan 02 decisions:
- Bootstrap RTL CSS loaded before css/style.css — Bootstrap classes take priority; container-lg used (not container) to avoid 400px constraint
- ES5 .then() chains throughout admin.php — consistent with index.html, no async/await introduced
- crypto.getRandomValues for password generator — CSPRNG, not Math.random(); field type toggled to text on generate
- loadUsers() as reusable function — called on DOMContentLoaded and after successful create (no full page reload)

Phase 5 Plan 01 decisions:
- Single update.php endpoint with action dispatch (edit/block/suspend) — smaller surface area vs separate files
- id_number excluded from edit action — identity documents immutable after creation (RESEARCH.md Open Question 1)
- block action clears suspended_until to null — PHP null → JSON null → PostgreSQL NULL on TIMESTAMPTZ column
- delete.php checks http_code 204 (not 200) — DELETE returns 204 No Content per PostgREST spec

### Pending Todos

None.

### Blockers/Concerns

- MANUAL ACTION PENDING: Delete `api/test-supabase.php` from cPanel server at `public_html/FirstApp/api/` (removed from git repo in afaf3a0, but the deployed copy on the live server must be deleted manually)
- Verify cPanel PHP version before Phase 4 begins (must be 7.4+ for `password_hash` constant compatibility)

## Session Continuity

Last session: 2026-02-28
Stopped at: Phase 05 Plan 01 complete — api/users/update.php and api/users/delete.php created; next is Phase 05 Plan 02 (admin UI actions)
Resume file: None
