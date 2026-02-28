# Roadmap: FirstApp — Signature Dispatch System

## Overview

FirstApp is built in two phases that mirror the two sides of the signing loop. Phase 1 establishes the security skeleton, authentication, and SMS dispatch — everything Sharon (the operator) touches. Phase 2 builds the recipient-side signing flow — canvas capture, PNG storage, and confirmation SMS. At the end of Phase 2 the full send-sign-confirm loop is testable end-to-end on a real phone.

## Phases

**Phase Numbering:**
- Integer phases (1, 2): Planned milestone work
- Decimal phases (1.1, 1.2): Urgent insertions (marked with INSERTED)

- [x] **Phase 1: Foundation, Auth, and Dispatch** - Security skeleton, login, protected dispatch page, and SMS sent to phone
- [x] **Phase 2: Signature Capture and Confirmation** - Mobile canvas signing, PNG saved to server, confirmation SMS received

## Phase Details

### Phase 1: Foundation, Auth, and Dispatch
**Goal**: Sharon can log in securely, reach a protected dispatch page, and send an SMS with a signing link that arrives on the target phone
**Depends on**: Nothing (first phase)
**Requirements**: INFR-01, INFR-02, INFR-03, AUTH-01, AUTH-02, AUTH-03, AUTH-04, DISP-01, DISP-02, DISP-03, DISP-04
**Success Criteria** (what must be TRUE):
  1. Sharon visits ch-ah.info/FirstApp/ and sees the Hebrew login form
  2. Entering wrong credentials shows the Hebrew error message "שם כניסה או סיסמא לא תקינים"
  3. Entering Sharonb / 1532 reaches the dispatch page "דף שיגור חתימה" and the session survives a page refresh
  4. Visiting the dispatch page URL directly without being logged in redirects back to login
  5. Clicking the dispatch button sends an SMS to 0526804680 with a Hebrew message and a link — Sharon sees success or error feedback and the SMS arrives on the phone
**Plans**: 3 plans in 3 waves (sequential — each plan depends on the prior)

Plans:
- [x] 01-01-PLAN.md — Folder structure, api/config.php, .htaccess security skeleton, RTL CSS (Wave 1)
- [x] 01-02-PLAN.md — Login page (index.html) and PHP session auth (api/login.php, api/check-session.php) (Wave 2)
- [x] 01-03-PLAN.md — Protected dispatch page (dashboard.html) and SMS send endpoint (api/send-sms.php) + human verify (Wave 3)

### Phase 2: Signature Capture and Confirmation
**Goal**: The recipient opens the SMS link on their phone, draws a signature with their finger, submits it, and both the recipient and Sharon receive confirmation — the full loop is proven end-to-end
**Depends on**: Phase 1
**Requirements**: SIGN-01, SIGN-02, SIGN-03, SIGN-04, SIGN-05, SIGN-06, STOR-01, STOR-02, NOTF-01
**Success Criteria** (what must be TRUE):
  1. Opening the signing link on a mobile phone shows a Hebrew page "חתום פה" with a touch-draw signature area
  2. The recipient can draw a signature with a finger (touch) and with a mouse on desktop, and can clear and redraw
  3. Tapping the "שלח" button saves a non-blank PNG file to the signatures/ folder on the server
  4. The signatures/ folder is not directly browsable or downloadable via a URL
  5. After the PNG is saved, a confirmation SMS "המסמך נחתם" is sent to 0526804680 and arrives on Sharon's phone
**Plans**: 2 plans in 2 waves

Plans:
- [x] 02-01-PLAN.md — Signing page (sign.html) + save endpoint (api/save-signature.php) with GD validation and confirmation SMS (Wave 1)
- [x] 02-02-PLAN.md — End-to-end human verification: full dispatch-sign-confirm loop on real phone (Wave 2)

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Foundation, Auth, and Dispatch | 3/3 | ✓ Complete | 2026-02-28 |
| 2. Signature Capture and Confirmation | 2/2 | ✓ Complete | 2026-02-28 |
