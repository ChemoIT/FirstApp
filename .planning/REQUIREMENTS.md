# Requirements: FirstApp — Signature Dispatch System

**Defined:** 2026-02-27
**Core Value:** A user can dispatch a signature request via SMS and receive a signed document back — the full send-sign-confirm loop must work end to end.

## v1 Requirements

Requirements for initial release. Each maps to roadmap phases.

### Authentication

- [ ] **AUTH-01**: User can log in with username Sharonb and password 1532
- [ ] **AUTH-02**: Invalid login attempt shows Hebrew error message "שם כניסה או סיסמא לא תקינים"
- [ ] **AUTH-03**: Logged-in session persists across page refresh
- [ ] **AUTH-04**: Unauthorized access to protected pages redirects to login

### Dispatch

- [ ] **DISP-01**: Logged-in user sees "דף שיגור חתימה" page with dispatch button
- [ ] **DISP-02**: Clicking dispatch button sends SMS to 0526804680 with signing link
- [ ] **DISP-03**: SMS message is in Hebrew: "היכנס לקישור הבא:" followed by signing page URL
- [ ] **DISP-04**: User sees success/error feedback after dispatch attempt

### Signature

- [ ] **SIGN-01**: Signing page opens from SMS link on mobile browser
- [ ] **SIGN-02**: Page displays Hebrew title "חתום פה" with signature area
- [ ] **SIGN-03**: User can draw signature with finger on touch screen
- [ ] **SIGN-04**: User can draw signature with mouse on desktop
- [ ] **SIGN-05**: User can clear and redraw signature
- [ ] **SIGN-06**: User taps "שלח" button to submit signature

### Storage

- [ ] **STOR-01**: Signature is saved as PNG file in signatures/ folder on server
- [ ] **STOR-02**: Signatures folder is protected from direct URL access

### Notification

- [ ] **NOTF-01**: After successful signature save, confirmation SMS "המסמך נחתם" is sent to 0526804680

### Infrastructure

- [ ] **INFR-01**: Code is pushed to GitHub repository (ChemoIT/FirstApp)
- [ ] **INFR-02**: App is deployed and accessible at ch-ah.info/FirstApp/
- [ ] **INFR-03**: Security headers and .htaccess configured

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Enhanced Signature

- **SIGN-07**: Preview signature before submitting
- **SIGN-08**: Timestamp embedded in saved filename (signature_2026-02-27_143052.png)
- **SIGN-09**: Unique token per dispatch for tracking which signature belongs to which request

### Enhanced Dispatch

- **DISP-05**: Dispatch to custom phone number (not just hardcoded)
- **DISP-06**: Dispatch history log showing sent/signed/pending status

### Enhanced Security

- **AUTH-05**: Login attempt rate limiting
- **INFR-04**: HTTPS enforcement via .htaccess redirect

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Multiple users / registration | Learning project, single user sufficient |
| Database | No persistent storage needed beyond file system |
| Password reset | Single hardcoded user |
| Document upload / management | Only signature capture |
| Email notifications | SMS only via Micropay |
| Digital certificates / PKI | Commercial e-signing feature, overkill |
| Audit trail | Commercial compliance feature |
| OAuth / social login | Email/password sufficient |
| Real-time notifications | Simple request/response sufficient |
| Mobile native app | Web-only |
| Multi-language | Hebrew only |
| PDF generation | PNG signature only |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| AUTH-01 | Phase 1 | Pending |
| AUTH-02 | Phase 1 | Pending |
| AUTH-03 | Phase 1 | Pending |
| AUTH-04 | Phase 1 | Pending |
| DISP-01 | Phase 1 | Pending |
| DISP-02 | Phase 1 | Pending |
| DISP-03 | Phase 1 | Pending |
| DISP-04 | Phase 1 | Pending |
| SIGN-01 | Phase 2 | Pending |
| SIGN-02 | Phase 2 | Pending |
| SIGN-03 | Phase 2 | Pending |
| SIGN-04 | Phase 2 | Pending |
| SIGN-05 | Phase 2 | Pending |
| SIGN-06 | Phase 2 | Pending |
| STOR-01 | Phase 2 | Pending |
| STOR-02 | Phase 2 | Pending |
| NOTF-01 | Phase 2 | Pending |
| INFR-01 | Phase 1 | Pending |
| INFR-02 | Phase 1 | Pending |
| INFR-03 | Phase 1 | Pending |

**Coverage:**
- v1 requirements: 20 total
- Mapped to phases: 20
- Unmapped: 0 (complete)

---
*Requirements defined: 2026-02-27*
*Last updated: 2026-02-27 — traceability filled after roadmap creation*
