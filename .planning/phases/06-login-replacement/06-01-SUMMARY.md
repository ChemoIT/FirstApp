---
phase: 06-login-replacement
plan: 01
subsystem: auth
tags: [supabase, php-sessions, bcrypt, password-verify, status-enforcement]

# Dependency graph
requires:
  - phase: 04-user-management
    provides: public.users table with email, password_hash, status, suspended_until columns
  - phase: 03-supabase-connection
    provides: api/supabase.php with supabase_request() helper

provides:
  - Supabase-backed login replacing hardcoded sharonb/1532 credentials
  - Email+password authentication via api/login.php
  - Status enforcement (active/blocked/suspended) on every login attempt
  - Session-safe logout via api/logout.php destroying PHP session before redirect
affects: [future auth changes, session handling, any phase that touches login flow]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - password_verify() against Supabase-stored bcrypt hash — PHP owns full password lifecycle
    - Generic Hebrew error message for ALL auth failures — never reveal which step failed
    - filter_var FILTER_VALIDATE_EMAIL guard before Supabase call — reject malformed input early
    - session_regenerate_id(true) before setting $_SESSION — session fixation protection
    - api/logout.php destroys session server-side before redirecting — prevents redirect loop

key-files:
  created:
    - api/logout.php
  modified:
    - api/login.php
    - index.html
    - dashboard.html
    - api/config.php (local only — gitignored, ADMIN_USER/ADMIN_PASS removed)

key-decisions:
  - "Generic error 'פרטי הכניסה שגויים' used for ALL auth failures — email not found, wrong password, blocked, suspended — never reveal which step failed"
  - "session_regenerate_id(true) called before $_SESSION assignment — prevents session fixation attack"
  - "filter_var FILTER_VALIDATE_EMAIL applied before Supabase call — avoids wasted API round-trip on malformed input"
  - "Logout handled by dedicated api/logout.php (session_destroy + unset + redirect) — not a client-side redirect — prevents session lingering on server"
  - "api/config.php kept gitignored — ADMIN_USER/ADMIN_PASS removed locally but change never enters git history"

patterns-established:
  - "Pattern: PHP auth flow — validate input → fetch from Supabase → bcrypt verify → status check → session_regenerate_id → set session"
  - "Pattern: All auth error responses use JSON_UNESCAPED_UNICODE for Hebrew safety"
  - "Pattern: Logout endpoint (not client-side redirect) required when PHP sessions are in use"

# Metrics
duration: ~15min
completed: 2026-02-28
---

# Phase 6 Plan 01: Login Replacement Summary

**Supabase-backed email+password login with bcrypt verify, status enforcement, and server-side logout replacing hardcoded sharonb/1532 credentials**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-02-28T23:35:28+02:00
- **Completed:** 2026-02-28T23:41:40+02:00 (deploy + human verify)
- **Tasks:** 2 (1 auto + 1 human-verify checkpoint)
- **Files modified:** 5 (4 in git, 1 gitignored)

## Accomplishments

- Rewrote api/login.php: reads email from JSON body, looks up user in Supabase, verifies bcrypt hash, enforces active/blocked/suspended status, sets PHP session
- Updated index.html: username field replaced with email field, Hebrew label changed to "אימייל", JS fetch body updated to send email
- Added api/logout.php: destroys PHP session server-side before redirecting to index.html (auto-fix for redirect loop discovered during verify)
- Hardcoded ADMIN_USER and ADMIN_PASS constants removed from api/config.php permanently
- All 5 human verification checks passed at ch-ah.info/FirstApp/

## Task Commits

Each task was committed atomically:

1. **Task 1: Replace login.php, update index.html email field, remove hardcoded credentials** - `b3a4cfa` (feat)
2. **Fix: Add logout endpoint to destroy session before redirect** - `3d8c171` (fix)
3. **Human checkpoint: Task 2 verification** - APPROVED (no commit — human action)

**Plan metadata:** pending this commit (docs)

## Files Created/Modified

- `api/login.php` - Rewritten: Supabase lookup by email, password_verify() bcrypt check, status enforcement, session_regenerate_id, JSON_UNESCAPED_UNICODE on all responses
- `index.html` - Login form: username field replaced with email field, label "שם משתמש" → "אימייל", JS body updated to send {email, password}
- `api/logout.php` - New file: session_destroy() + $_SESSION unset + header redirect to index.html
- `dashboard.html` - Logout link updated to call api/logout.php instead of client-side redirect
- `api/config.php` - Local-only: ADMIN_USER and ADMIN_PASS constants removed (file is gitignored, change never enters git history)

## Decisions Made

- Generic Hebrew error `'פרטי הכניסה שגויים'` used for ALL failure paths (no-user, wrong-password, blocked, suspended) — prevents enumeration of valid emails
- `session_regenerate_id(true)` called before `$_SESSION` assignment — standard session fixation protection
- `filter_var($email, FILTER_VALIDATE_EMAIL)` applied before Supabase call — avoids a wasted API round-trip on malformed input
- Supabase SELECT limited to `id,email,status,suspended_until,password_hash` — minimal data fetch
- `api/config.php` kept gitignored; credential removal is a local-only change that never enters git history

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Added api/logout.php to fix redirect loop on logout**

- **Found during:** Task 2 (human-verify checkpoint)
- **Issue:** The logout link in dashboard.html redirected directly to index.html without destroying the PHP session. On arrival at index.html, the session check detected an active session and immediately redirected back to dashboard.html — creating an infinite redirect loop.
- **Fix:** Created `api/logout.php` which calls `session_start()`, unsets `$_SESSION`, calls `session_destroy()`, then issues a `header('Location: ../index.html')` redirect. Updated `dashboard.html` logout link to point to `api/logout.php` instead of `../index.html` directly.
- **Files modified:** api/logout.php (new), dashboard.html (logout href updated)
- **Verification:** Human verified — logout now works end-to-end at ch-ah.info
- **Committed in:** 3d8c171 (separate fix commit, pre-deploy)

---

**Total deviations:** 1 auto-fixed (Rule 3 — blocking issue)
**Impact on plan:** Fix was essential for correct session lifecycle. No scope creep — logout was implicitly required by the login flow.

## Issues Encountered

None beyond the logout redirect loop deviation documented above.

## User Setup Required

None — no external service configuration required. Supabase credentials were already in place from Phase 3.

## Next Phase Readiness

- v2.0 milestone is complete. All 6 phases (Supabase connection, user CRUD, admin UI with CRUD actions, login replacement) are verified in production.
- Login is now fully Supabase-backed. Hardcoded credentials are gone permanently.
- No blockers for future development.
- Old concern: delete `api/test-supabase.php` from cPanel server — still applies if not yet done manually.

---
*Phase: 06-login-replacement*
*Completed: 2026-02-28*
