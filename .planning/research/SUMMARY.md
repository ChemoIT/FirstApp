# Project Research Summary

**Project:** FirstApp — Signature Dispatch System
**Domain:** PHP signature capture web app on cPanel shared hosting
**Researched:** 2026-02-27
**Confidence:** HIGH

## Executive Summary

FirstApp is a two-sided dispatch-and-sign flow: an authenticated operator (Sharon) triggers an SMS containing a signing link, and a recipient opens that link on their mobile phone, draws a finger signature on a canvas, and the system saves the PNG and confirms receipt via a second SMS. This is a well-understood problem domain with mature, stable tooling. The recommended approach is a minimal PHP backend deployed directly on the existing cPanel shared host at ch-ah.info, using vanilla HTML/CSS/JS on the front end and the signature_pad library (szimek, v4.x) for canvas input. No framework, no database, no build pipeline — the simplicity of the toolchain is both appropriate to the scope and maximally educational.

The recommended architecture is a clean separation between static HTML/JS presentation files and PHP API endpoints housed in an `api/` subdirectory. All secrets (Micropay API token, credentials) live exclusively in `api/config.php` and never touch the browser. This boundary is the single most important architectural decision: violating it exposes the SMS API token to any visitor with DevTools open. PHP sessions handle authentication for the one protected endpoint (SMS dispatch). Signature files are saved as PNGs in a directory protected by `.htaccess`. The full end-to-end flow can be built in approximately six sequential components, each independently testable.

The dominant risks are not architectural — they are implementation traps specific to this stack. Three require immediate attention in Phase 1: the token must never appear in JavaScript, `session_start()` must be line 1 of every session-aware PHP file, and Hebrew SMS content must be converted from UTF-8 to ISO-8859-8 before URL-encoding. In Phase 2, the canvas has two well-known mobile pitfalls: touch events on iOS require explicit listeners and `preventDefault()` to stop page scroll, and HiDPI screens require `devicePixelRatio` scaling to avoid blurry saved signatures. Each of these is a predictable failure point with a one-line prevention — the research makes them explicit so they can be addressed on first build rather than discovered by debugging.

---

## Key Findings

### Recommended Stack

The stack is constrained by the hosting environment (cPanel shared hosting at ch-ah.info) and that constraint is a feature, not a limitation. PHP 8.2 is the only viable backend option on cPanel — Node.js persistent server processes are not supported. The only external library needed is `signature_pad` v4.x, loaded via CDN. All other capabilities (sessions, file I/O, HTTP calls) are built into PHP core.

**Core technologies:**
- **PHP 8.2**: Backend logic, session auth, Micropay API calls, file writes — dictated by cPanel; no alternative exists
- **HTML5 / CSS3 / Vanilla JS (ES6+)**: All presentation; no framework; no build step; `dir="rtl"` and `direction: rtl` handle Hebrew natively
- **signature_pad 4.x (szimek)**: Touch-and-mouse canvas signature capture — the de facto standard; handles bezier interpolation, pointer events, and cross-browser quirks that raw Canvas API does not
- **Apache .htaccess**: HTTPS redirect enforcement, directory listing suppression, signatures folder access control
- **cPanel File Manager / FTP**: Deployment — appropriate for this scope; no CI/CD needed

**Version note:** signature_pad 4.1.7 is confirmed in training data (Aug 2025). Verify current release at github.com/szimek/signature_pad/releases before pinning the CDN URL.

### Expected Features

The entire app is the core loop. Every feature in the dependency chain is table stakes; nothing outside it is required for launch.

**Must have (table stakes) — all 10 required for functional loop:**
- Login / PHP session auth — protects paid SMS dispatch endpoint
- Dispatch trigger button — sends Micropay SMS with signing link
- SMS with unique-enough link — delivers sign.html URL to recipient
- Mobile signature canvas — touch/finger drawing; core recipient UX
- Clear / redo button — essential for usable signature input
- Submit and save PNG — base64 canvas export to PHP file_put_contents
- Confirmation to recipient — "thank you" UI state after successful save
- Confirmation SMS to sender — second Micropay call on successful save
- Visible error handling — failed saves or failed SMS must surface in UI, not silently drop
- Logout — session_destroy link

**Should have (recommended stretch goals, in priority order):**
1. Signature preview before submit — low effort, major UX improvement; teaches JS state management
2. Timestamp in saved filename — teaches PHP date(), zero complexity
3. Unique token per dispatch — teaches security thinking; prevents link reuse

**Defer to v2+:**
- Sender views PNGs in browser (flat file list page)
- Signing link expiry
- Dispatch log file (CSV)

**Never build (anti-features):**
- Database (MySQL), user registration, password reset, PDF overlay, PKI/legal signing, email notifications, multi-recipient dispatch, real-time status, admin dashboard, native mobile app — all increase complexity with zero learning-project benefit. See FEATURES.md for full rationale.

### Architecture Approach

The architecture follows the "Thin PHP API" pattern: static HTML/CSS/JS files handle all presentation, and PHP files in `api/` handle all server-side work. The files are separated by type, not by page. This makes the security boundary physically obvious — anything in `api/` touches secrets; anything outside it does not. There are exactly three PHP endpoints, one config include, three HTML pages, one CSS file, one JS file, and one protected folder.

**Major components:**
1. **`index.html` + `api/login.php`** — Login form UI and session creation; the auth gateway for the entire app
2. **`dashboard.html` + `api/send-sms.php`** — Operator dispatch UI; session-protected; all Micropay calls happen here
3. **`sign.html` + `api/save-signature.php`** — Public recipient page; canvas capture, PNG save, confirmation SMS
4. **`api/config.php`** — Single source of truth for all constants and credentials; included by PHP endpoints, never served to browser
5. **`signatures/`** — File store for saved PNGs; protected by `.htaccess` `Deny from all`

### Critical Pitfalls

Eight critical pitfalls were identified, all HIGH confidence. The top five that must be prevented before going live:

1. **Micropay token in client JS** — Token must live only in `api/config.php`. The browser button POSTs to a PHP endpoint; PHP calls Micropay. Verify by opening DevTools and searching for the token string — it must never appear. This is the most dangerous pitfall.
2. **`session_start()` missing or placed after output** — Must be line 1 of every PHP file that reads or writes `$_SESSION`. Forgetting it causes silent auth failures. Enable `display_errors = 1` during development to surface "headers already sent" warnings immediately.
3. **Canvas broken on iOS (touch events)** — The app's primary use case is mobile. Raw canvas mouse listeners do not fire on iPhone. Must register `touchstart`, `touchmove`, `touchend` listeners and call `e.preventDefault()` on touchmove. Test on a real phone before calling Phase 2 done.
4. **Saved PNG is blank white** — Canvas background is transparent by default. Fill white before any drawing. Strip the `data:image/png;base64,` prefix before `base64_decode()` in PHP. Verify `file_put_contents()` returns a non-zero byte count before sending confirmation SMS.
5. **Hebrew SMS garbled** — Micropay expects ISO-8859-8 encoding. Always convert: `iconv('UTF-8', 'ISO-8859-8', $msg)` then `urlencode()`. Keep messages under 70 characters (Hebrew SMS uses UCS-2; 70 char limit per segment). Test on a real phone.

Additional critical pitfalls (full detail in PITFALLS.md): PHP error display off on shared hosting (enable `display_errors` day one), `signatures/` directory publicly accessible without `.htaccess`, hidden input missing `name` attribute causing `$_POST` to be empty.

---

## Implications for Roadmap

The architecture's build order is deterministic. Components have clear dependencies and each phase produces a testable system. Two phases are recommended.

### Phase 1: Foundation — Auth, Security, and SMS Dispatch

**Rationale:** The security skeleton must be established first. The Micropay token exposure risk and the HTTPS enforcement both need to be correct from day one — retrofitting security after the fact on a shared host is harder than building it in. Login is the prerequisite for everything protected. SMS dispatch is the first half of the core loop and validates the Micropay integration before the canvas is touched.

**Delivers:** Working login/logout, protected dispatch page, SMS arrival on target phone with a link, HTTPS enforced, all secrets isolated in config.php.

**Addresses features:** Login/auth, logout, dispatch trigger button, SMS with link.

**Avoids:** Pitfall 1 (token in JS), Pitfall 2 (session_start), Pitfall 5 (Hebrew SMS encoding), Pitfall 7 (error display off), Pitfall 10 (session fixation), Pitfall 11 (HTTPS not enforced), Pitfall 16 (include path with __DIR__).

**Build sequence within phase:**
1. Folder structure + `api/config.php` + both `.htaccess` files (security skeleton)
2. `index.html` + `api/login.php` (session auth)
3. `dashboard.html` + session guard (protected shell)
4. `api/send-sms.php` (Micropay integration + Hebrew encoding)

**Research flag:** No additional research needed. PHP session auth and Micropay GET calls are standard patterns with well-documented behavior.

---

### Phase 2: Signature Capture, Storage, and Confirmation

**Rationale:** Phase 2 builds the recipient-side flow. All the mobile/canvas pitfalls are concentrated here. Building this after Phase 1 is validated means the full end-to-end test is possible at Phase 2 completion. The canvas work requires touch event handling and HiDPI scaling to be correct; these are not optional polish — they are required for the app to function on an iPhone.

**Delivers:** Working mobile signature canvas (touch + mouse), PNG saved to server with timestamp in filename, confirmation SMS to operator, full end-to-end flow tested on a real phone, `signatures/` protected by .htaccess.

**Addresses features:** Mobile signature canvas, clear button, submit/save PNG, confirmation to recipient, confirmation SMS to sender, visible error handling.

**Avoids:** Pitfall 3 (canvas blank on iOS), Pitfall 4 (blank PNG saved), Pitfall 6 (signatures/ publicly accessible), Pitfall 8 (hidden input missing name), Pitfall 9 (HiDPI blurry signature), Pitfall 13 (toDataURL called on empty canvas), Pitfall 15 (file permissions 644).

**Stack elements:** signature_pad 4.x via CDN, HTML5 Canvas API, `devicePixelRatio` scaling, PHP `base64_decode` + `file_put_contents`, second Micropay call.

**Build sequence within phase:**
1. `sign.html` with canvas + signature_pad + touch listeners (no save yet)
2. `api/save-signature.php` (base64 decode, PNG write, confirmation SMS)
3. End-to-end test: login → send SMS → open link on phone → sign → confirm SMS

**Research flag:** No additional research needed. Canvas touch events and base64-to-PNG are well-documented patterns. The signature_pad library handles the complex bits.

---

### Phase 3 (Optional): Polish and Security Hardening

**Rationale:** Once the core loop is proven, these additions each teach a specific concept without risking the working system. All are independent additions — none break the existing flow.

**Delivers:** Signature preview before submit, unique token per dispatch (replay prevention), timestamp in saved filenames, dispatch log (flat CSV), sender can view PNGs in browser.

**Addresses features:** Stretch goals from FEATURES.md (signature preview, unique token, timestamp filename, log file, sender views PNG).

**Avoids:** Pitfall 12 (non-unique signing link) — addressed by unique token feature.

**Research flag:** No additional research needed. All patterns are standard PHP/JS.

---

### Phase Ordering Rationale

- Security foundation comes first because the Micropay token risk is the highest-consequence mistake and it must be wired correctly from the start. Building the dispatch flow before the canvas means the SMS integration is validated before the more complex mobile work begins.
- The signing flow is Phase 2 because it depends on having a real URL to send in the SMS. Building it second also allows both halves to be tested together immediately upon Phase 2 completion.
- The stretch goals are isolated to Phase 3 because every item there is an independent enhancement layered on a working system. Adding them earlier adds risk to the core loop without proportionate benefit.
- The architecture's clean `api/` separation is set up in Phase 1, Step 1 — this means the pattern is established before any feature code is written and cannot be accidentally violated by a beginner.

### Research Flags

Phases with well-established patterns (no `gsd:research-phase` needed):
- **Phase 1:** PHP sessions, cPanel HTTPS, Micropay GET API — all standard, all well-documented, all covered in research.
- **Phase 2:** HTML5 Canvas, touch events, base64-PNG, signature_pad — mature browser APIs; signature_pad README is sufficient reference.
- **Phase 3:** All patterns are PHP/JS fundamentals.

No phase in this project requires a `gsd:research-phase` call. The research is complete. The one action item before writing code is to verify the current signature_pad version at the GitHub releases page and pin the CDN URL accordingly.

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH (PHP, sessions, file I/O) / MEDIUM (signature_pad version) | PHP is dictated by hosting; session/file patterns are core PHP. signature_pad version 4.1.7 is training data (Aug 2025) — verify at GitHub before use. |
| Features | HIGH | Feature set defined by PROJECT.md; domain is mature; table stakes are unambiguous. |
| Architecture | HIGH | PHP/cPanel thin-API pattern is established and stable; component boundaries are clear; all sources are official. |
| Pitfalls | HIGH | All 8 critical pitfalls are based on well-established PHP/browser/hosting fundamentals with official source backing. No speculation. |

**Overall confidence:** HIGH

### Gaps to Address

- **signature_pad current version:** Training data cites 4.1.7 (Aug 2025 cutoff). Before pinning the CDN URL, check https://github.com/szimek/signature_pad/releases for the current v4.x release. This is a one-minute verification, not a research gap.
- **Micropay API encoding confirmation:** The ISO-8859-8 requirement is stated in the project spec. If the API has changed or if UTF-8 is now accepted, the `iconv` call is still harmless but unnecessary. Test with a real SMS on first deploy to confirm.
- **cPanel PHP Selector options:** Research recommends PHP 8.2. The actual available versions depend on the specific cPanel install at ch-ah.info. Check cPanel → MultiPHP Manager before starting Phase 1. PHP 8.x (any minor) is acceptable.

---

## Sources

### Primary (HIGH confidence)
- `PROJECT.md` — project scope, constraints, Micropay API method (GET, ISO-8859-8, token), phone number, credentials
- PHP official documentation: https://www.php.net/manual/en/book.session.php, https://www.php.net/manual/en/function.file-put-contents.php, https://www.php.net/manual/en/function.iconv.php
- MDN Web Docs: https://developer.mozilla.org/en-US/docs/Web/API/Canvas_API, https://developer.mozilla.org/en-US/docs/Web/API/Touch_events, https://developer.mozilla.org/en-US/docs/Web/API/Window/devicePixelRatio, https://developer.mozilla.org/en-US/docs/Web/API/HTMLCanvasElement/toDataURL
- Apache .htaccess access control: standard Apache mod_rewrite / mod_authz_host documentation

### Secondary (MEDIUM confidence)
- signature_pad GitHub (training data Aug 2025): https://github.com/szimek/signature_pad — version 4.1.7 cited; verify current release
- cPanel PHP MultiPHP Manager: training data — verify available PHP versions at ch-ah.info cPanel

### Tertiary (LOW confidence)
- None. No findings in this research are based on single or unverified sources.

---

*Research completed: 2026-02-27*
*Ready for roadmap: yes*
