---
phase: 01-foundation-auth-dispatch
plan: 03
subsystem: dispatch
tags: [php, sms, micropay, curl, hebrew, rtl, session-guard, dashboard]

# Dependency graph
requires:
  - phase: 01-01
    provides: "api/config.php with MICROPAY_TOKEN, ADMIN_PHONE, BASE_URL constants"
  - phase: 01-02
    provides: "api/check-session.php session status endpoint, PHP session auth pattern"
provides:
  - "api/send-sms.php — session-guarded SMS dispatch endpoint using cURL to Micropay API with Hebrew ISO-8859-8 message"
  - "dashboard.html — protected Hebrew RTL dispatch page with session guard redirect and SMS send feedback"
affects: [sign.html (Phase 2 — signing link target), full Phase 1 end-to-end flow]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Micropay SMS: iconv UTF-8 -> ISO-8859-8 for Hebrew content, charset=iso-8859-8 parameter, cURL GET to ScheduleSms.php"
    - "Client session guard: fetch check-session.php on DOMContentLoaded, redirect to index.html if ok:false"
    - "Dispatch UX: disable button + show 'שולח...' during request, restore on completion regardless of outcome (finally)"
    - "Status feedback: single #status-msg div toggled between .success and .error CSS classes"

key-files:
  created:
    - api/send-sms.php
    - dashboard.html
  modified: []

key-decisions:
  - "iconv UTF-8 to ISO-8859-8 for Hebrew SMS — Micropay requires ISO-8859-8 encoding; sending raw UTF-8 bytes would produce garbled Hebrew on the recipient's phone"
  - "cURL used instead of file_get_contents — cURL provides CURLOPT_TIMEOUT control (10s), proper error capture via curl_error(), and is the robust approach for external API calls"
  - "Raw Micropay response included in ok:true JSON — response format is not fully documented; including result field aids debugging if SMS delivery issues arise later"
  - "Button re-enabled in .finally() — ensures UI recovers from both success and error paths without duplicating restore logic"

patterns-established:
  - "Pattern: Dashboard session guard — fetch check-session.php in DOMContentLoaded, redirect on ok:false or network error"
  - "Pattern: Disable/restore button pattern for single-fire async actions (prevents double-send)"

# Metrics
duration: 2min
completed: 2026-02-27
---

# Phase 1 Plan 03: Dispatch Page and SMS Endpoint Summary

**Session-guarded SMS dispatch endpoint (Micropay cURL with Hebrew ISO-8859-8 encoding) and protected Hebrew RTL dashboard page with redirect guard and inline success/error feedback**

## Status

**Auto tasks: COMPLETE. Checkpoint (Task 3): PENDING — awaiting human verification after deployment.**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-27T22:25:42Z
- **Completed:** 2026-02-27T22:27:30Z (auto tasks only)
- **Tasks completed:** 2 of 3 (Task 3 is checkpoint:human-verify)
- **Files created:** 2

## Accomplishments

- `api/send-sms.php` created: session guard returns HTTP 401 JSON for unauthenticated requests; authenticated requests build Hebrew SMS "היכנס לקישור הבא: https://ch-ah.info/FirstApp/sign.html", encode to ISO-8859-8, POST to Micropay API via cURL, return `{"ok": true, "result": "..."}` or `{"ok": false, "message": "שגיאה בשליחת SMS: ..."}`
- `dashboard.html` created: Hebrew RTL page (lang=he dir=rtl) with "דף שיגור חתימה" heading; DOMContentLoaded session guard redirects unauthenticated visitors to index.html; dispatch button sends POST to send-sms.php with loading state (button disabled, text "שולח..."); #status-msg div shows green success or red error after response

## Task Commits

Each task committed atomically:

1. **Task 1: Create api/send-sms.php with session guard and Micropay cURL call** — `e6e4594` (feat)
2. **Task 2: Create dashboard.html protected dispatch page with session guard and SMS dispatch UI** — `ec1dd00` (feat)
3. **Task 3: Verify full login-to-SMS flow** — PENDING (checkpoint:human-verify)

## Files Created/Modified

- `api/send-sms.php` — POST endpoint: 401 auth guard, require_once config.php, iconv encoding, http_build_query with 6 Micropay params, cURL with 10s timeout, JSON ok:true/false response in all paths, no closing ?>
- `dashboard.html` — RTL dispatch page: lang=he dir=rtl, DOMContentLoaded session check, POST dispatch button with disabled state, #status-msg success/error feedback, logout link

## Decisions Made

- Used `iconv('UTF-8', 'ISO-8859-8', ...)` for Hebrew SMS message — Micropay requires ISO-8859-8 encoding; raw UTF-8 would produce garbled characters on the phone
- Used cURL (not file_get_contents) — provides timeout control, proper error detection via `curl_error()`, and is the reliable approach for external HTTP calls
- Included raw Micropay `result` in success response — Micropay's response format is not fully documented; including it allows debugging delivery issues in production logs
- Button restore logic placed in `.finally()` — ensures the button is always re-enabled after any outcome, without duplicating code in both `.then()` success and `.catch()` error handlers

## Deviations from Plan

None — plan executed exactly as written.

## Pending: Task 3 — Human Verification Checkpoint

**What to do:** Push code to GitHub (ChemoIT/FirstApp) and deploy to ch-ah.info/FirstApp/ via FTP, then verify:

1. Open https://ch-ah.info/FirstApp/ — Hebrew login form visible with "כניסה למערכת" heading
2. Enter username "test" / password "wrong" — should see Hebrew error "שם כניסה או סיסמא לא תקינים" in red
3. Enter username "Sharonb" / password "1532" — should redirect to dispatch page "דף שיגור חתימה"
4. Refresh the dispatch page (F5) — should remain on dispatch page, not redirected to login
5. Open a private/incognito window, navigate directly to https://ch-ah.info/FirstApp/dashboard.html — should redirect to login page
6. In the logged-in window, click "שלח בקשת חתימה" — check phone 0526804680 for SMS with "היכנס לקישור הבא:" and the signing link

**Respond with "approved" when all 6 checks pass, or describe which steps failed.**

---
*Phase: 01-foundation-auth-dispatch*
*Auto tasks completed: 2026-02-27*
*Checkpoint status: Awaiting human verification after deployment*

## Self-Check: PASSED

| Check | Result |
|-------|--------|
| api/send-sms.php | FOUND |
| dashboard.html | FOUND |
| commit e6e4594 | FOUND |
| commit ec1dd00 | FOUND |
| 01-03-SUMMARY.md | FOUND |
