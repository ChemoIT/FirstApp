# FirstApp — Signature Dispatch System

## What This Is

A simple web application for sending SMS signature requests. Sharon (IT Manager) sends an SMS with a signing link to a phone number, the recipient opens a mobile-friendly page, draws their signature with their finger, and the signed document is saved on the server. Built as a learning project to understand web app development from scratch.

## Core Value

A user can dispatch a signature request via SMS and receive a signed document back — the full send-sign-confirm loop must work end to end.

## Requirements

### Validated

<!-- Shipped and confirmed valuable. -->

(None yet — ship to validate)

### Active

<!-- Current scope. Building toward these. -->

- [ ] Login page with single hardcoded user (Sharonb/1532)
- [ ] Invalid login attempts show clear error message
- [ ] Protected dispatch page accessible only after login
- [ ] SMS dispatch button sends signing link to 0526804680
- [ ] Mobile-friendly signature page with finger-draw canvas
- [ ] Signature saved as PNG to server (signatures/ folder)
- [ ] Confirmation SMS "המסמך נחתם" sent after successful save
- [ ] GitHub repo connected and code pushed
- [ ] App deployed and accessible at ch-ah.info/FirstApp/

### Out of Scope

- Multiple users / user registration — learning project, single user is sufficient
- Database — no persistent data storage needed beyond file system
- Password reset / forgot password — single hardcoded user
- Document management — only signature capture, no document upload
- Email notifications — SMS only via Micropay
- Multi-language — Hebrew interface only

## Context

- **First project together** — Sharon wants to learn app development step by step
- **Teaching mode** — Every step needs clear explanation in simple language
- **Hosting** — cPanel shared hosting at ch-ah.info with FTP access
- **SMS provider** — Micropay API (GET request, token-based, supports Hebrew)
- **GitHub** — New repo at github.com/ChemoIT/FirstApp.git (just created)
- **Target phone** — 0526804680 for SMS dispatch
- **Hebrew UI** — All user-facing text in Hebrew, RTL layout

## Constraints

- **Tech stack**: Plain HTML/CSS/JS frontend + minimal PHP backend — chosen for learning simplicity
- **Hosting**: cPanel shared hosting (PHP available, no Node.js server) — dictates PHP backend
- **SMS API**: Micropay (GET method, iso-8859-8 charset) — 70 char limit for Hebrew messages
- **Security**: SMS token must be server-side only (PHP) — never exposed in client JavaScript
- **Signature storage**: Local file system on server (ch-ah.info/FirstApp/signatures/)

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| PHP for backend | cPanel includes PHP; needed to hide Micropay token and save files server-side | — Pending |
| HTML5 Canvas for signatures | Native browser API, touch-friendly, no external library needed | — Pending |
| Hardcoded credentials | Learning project, single user — no database complexity | — Pending |
| PHP sessions for auth | Built-in, simple, no external dependencies | — Pending |
| File-based signature storage | No database needed — PNG files in signatures/ folder | — Pending |

---
*Last updated: 2026-02-27 after initialization*
