---
phase: 01-foundation-auth-dispatch
plan: 02
subsystem: auth
tags: [php, session, authentication, hebrew, rtl, fetch, json]

# Dependency graph
requires:
  - phase: 01-01
    provides: "api/config.php with ADMIN_USER and ADMIN_PASS constants, css/style.css RTL Hebrew base styles"
provides:
  - "api/login.php — POST endpoint: validates JSON credentials against config constants, session_regenerate_id on success, Hebrew 401 error on failure"
  - "api/check-session.php — GET endpoint: returns {ok: true/false} based on PHP session state"
  - "index.html — Hebrew RTL login page with fetch-based auth, auto-redirect if already logged in"
affects: [03-dispatch, dashboard.html, all-protected-endpoints]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Session auth: session_start() + session_regenerate_id(true) before setting session data"
    - "JSON API: read body via php://input (not $_POST) when Content-Type is application/json"
    - "Client-side guard: fetch check-session.php on DOMContentLoaded, redirect if ok:true"
    - "Error display: fetch response message goes to #error-msg div via textContent + style.display"

key-files:
  created:
    - api/login.php
    - api/check-session.php
    - index.html
  modified: []

key-decisions:
  - "Use php://input (not $_POST) to read JSON body — $_POST only works for form-encoded payloads"
  - "session_regenerate_id(true) called BEFORE setting session data to prevent session fixation"
  - "index.html auto-redirects already-logged-in users via check-session.php on DOMContentLoaded"

patterns-established:
  - "Pattern: PHP JSON endpoints read body via json_decode(file_get_contents('php://input'), true)"
  - "Pattern: Session creation always calls session_regenerate_id(true) first, then sets $_SESSION vars"
  - "Pattern: Client JS shows server error message in #error-msg via textContent (not innerHTML)"

# Metrics
duration: 2min
completed: 2026-02-27
---

# Phase 1 Plan 02: Hebrew Login Page and PHP Session Auth Summary

**PHP session auth with session fixation prevention (session_regenerate_id), Hebrew 401 error response, and RTL fetch-based login page with auto-redirect for already-authenticated users**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-27T22:21:25Z
- **Completed:** 2026-02-27T22:23:03Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- api/login.php validates Sharonb/1532 against config constants, calls session_regenerate_id(true) before setting session, returns Hebrew error "שם כניסה או סיסמא לא תקינים" with HTTP 401 on failure
- api/check-session.php returns {"ok": true/false} based on $_SESSION['logged_in'] — used by both index.html (auto-redirect on load) and dashboard.html (guard, wired in Plan 03)
- index.html is a Hebrew RTL login page (lang=he dir=rtl) using fetch POST with JSON body; displays server error message inline without page reload; redirects to dashboard.html on success

## Task Commits

Each task was committed atomically:

1. **Task 1: Create api/login.php and api/check-session.php PHP endpoints** - `8843a38` (feat)
2. **Task 2: Create index.html Hebrew login page with fetch-based auth** - `4ab1db8` (feat)

**Plan metadata:** *(final docs commit — see below)*

## Files Created/Modified
- `api/login.php` — POST auth endpoint: JSON body via php://input, session_regenerate_id on success, HTTP 401 + Hebrew error on failure, no closing ?>
- `api/check-session.php` — GET session status: returns {"ok": bool} based on $_SESSION['logged_in'], no closing ?>
- `index.html` — Hebrew RTL login page: fetch POST to api/login.php, fetch GET to api/check-session.php on load, inline error display, redirect to dashboard.html on success

## Decisions Made
- Read JSON body via `php://input` instead of `$_POST` — when Content-Type is `application/json`, PHP does not populate `$_POST`; `php://input` is the correct approach for JSON API endpoints
- Called `session_regenerate_id(true)` before setting any `$_SESSION` variables — regenerating after setting data could leave old session ID active during a race condition window; the `true` parameter deletes the old session file
- index.html checks check-session.php on `DOMContentLoaded` to auto-redirect already-logged-in users — this prevents Sharon from seeing the login form again after she's already authenticated

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required for this plan. Files are ready to be deployed to cPanel via FTP.

## Next Phase Readiness
- Auth system complete: index.html login page + api/login.php + api/check-session.php are all in place
- Plan 03 (dispatch) can now create dashboard.html using api/check-session.php as the session guard
- All 3 auth requirements covered: AUTH-01 (credential validation), AUTH-02 (Hebrew error), AUTH-03 (session persistence), AUTH-04 endpoint exists (client-side redirect wiring is Plan 03's task)
- No blockers for Plan 03

---
*Phase: 01-foundation-auth-dispatch*
*Completed: 2026-02-27*

## Self-Check: PASSED

All files verified present on disk. All commits verified in git log.

| Check | Result |
|-------|--------|
| api/login.php | FOUND |
| api/check-session.php | FOUND |
| index.html | FOUND |
| 01-02-SUMMARY.md | FOUND |
| commit 8843a38 | FOUND |
| commit 4ab1db8 | FOUND |
