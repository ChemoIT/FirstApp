# Milestones: FirstApp

## v1.0 — Signature Dispatch System

**Shipped:** 2026-02-28
**Phases:** 2 | **Plans:** 5 | **LOC:** 798

### Delivered

Complete SMS signature dispatch loop: login, send SMS with signing link, recipient draws signature on mobile canvas, PNG saved to server, confirmation SMS sent back.

### Key Accomplishments

1. Security skeleton — .htaccess HTTPS enforcement, directory protection, signatures/ folder locked
2. PHP session auth — login with Hebrew error messages, session guard on protected pages
3. SMS dispatch — Micropay integration with Hebrew iconv encoding via cURL
4. Signature capture — signature_pad v5.1.3 DPI-aware canvas with touch support
5. Server-side PNG validation — GD imagecreatefromstring before save, uniqid filenames
6. Full loop verified on real phone — dispatch SMS → sign → PNG saved → confirmation SMS received

### Archives

- [v1.0-ROADMAP.md](milestones/v1.0-ROADMAP.md)
- [v1.0-REQUIREMENTS.md](milestones/v1.0-REQUIREMENTS.md)
