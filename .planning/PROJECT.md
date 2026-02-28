# FirstApp — Signature Dispatch System

## What This Is

A web application for sending SMS signature requests and capturing signed confirmations. Sharon (IT Manager) sends an SMS with a signing link, the recipient opens a mobile-friendly page, draws their signature with their finger, the PNG is saved on the server, and a confirmation SMS is sent back. Built as a learning project to understand web app development from scratch.

## Core Value

A user can dispatch a signature request via SMS and receive a signed document back — the full send-sign-confirm loop must work end to end.

## Requirements

### Validated

- Login with hardcoded credentials (Sharonb/1532) — v1.0
- Hebrew error messages on invalid login — v1.0
- Session persistence across page refresh — v1.0
- Session guard redirects unauthorized access — v1.0
- Protected dispatch page with SMS send button — v1.0
- SMS dispatch to 0526804680 with signing link — v1.0
- Mobile signature page with finger-draw canvas — v1.0
- PNG save to signatures/ folder with GD validation — v1.0
- Confirmation SMS "המסמך נחתם" after save — v1.0
- GitHub CI/CD with FTP deploy — v1.0
- .htaccess security (HTTPS, directory protection) — v1.0

### Active

(None — next milestone will define new requirements)

### Out of Scope

- Password reset / forgot password — single hardcoded user
- Document management — only signature capture, no document upload
- Email notifications — SMS only via Micropay
- Multi-language — Hebrew interface only
- Digital certificates / PKI — commercial e-signing feature, overkill
- Audit trail — commercial compliance feature

## Context

- **v1.0 shipped** — 2026-02-28, 798 LOC (PHP + HTML + CSS), 2 phases, 5 plans
- **First project together** — Sharon learning app development step by step
- **Hosting** — cPanel shared hosting at ch-ah.info with FTP access via GitHub Actions
- **SMS provider** — Micropay API (GET request, token-based, Hebrew via iconv ISO-8859-8)
- **GitHub** — github.com/ChemoIT/FirstApp.git
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
| PHP for backend | cPanel includes PHP; needed to hide Micropay token and save files server-side | Good |
| signature_pad v5.1.3 via CDN | Touch/mouse/bezier curves; raw Canvas API insufficient for mobile quality | Good |
| Hardcoded credentials | Learning project, single user — no database complexity | Good (for v1.0) |
| PHP sessions for auth | Built-in, simple, no external dependencies | Good |
| File-based signature storage | No database needed — PNG files in signatures/ folder | Good |
| php://input for JSON body | $_POST only works for form-encoded; JSON needs raw input stream | Good |
| iconv UTF-8 → ISO-8859-8 | Micropay requires ISO-8859-8 for Hebrew — raw UTF-8 produces garbled chars | Good |
| SMS failure ≠ save failure | PNG is the record of truth; SMS is just notification | Good |
| No closing ?> in PHP | Prevents trailing whitespace causing "headers already sent" errors | Good |
| DPI-aware canvas resize | devicePixelRatio scaling + signaturePad.clear() prevents isEmpty() false positive | Good |

---
*Last updated: 2026-02-28 after v1.0 milestone*
