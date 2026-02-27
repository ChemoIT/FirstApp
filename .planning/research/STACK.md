# Technology Stack

**Project:** FirstApp — Signature Dispatch System
**Researched:** 2026-02-27
**Research tools available:** Training data only (WebSearch/WebFetch/Context7 unavailable in this session)

---

## Recommended Stack

### Core Framework

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| PHP | 8.2.x | Backend logic, SMS API calls, file I/O, session auth | cPanel standard; 8.2 is active support through Dec 2026; 8.3+ may not be available on all shared hosts. Required for server-side token hiding. |
| HTML5 | Living Standard | Page structure, Canvas element | Native browser API; no dependencies; RTL via `dir="rtl"` attribute. |
| CSS3 | — | Styling, RTL layout, mobile responsiveness | Native; `direction: rtl` and `text-align: right` built-in; no framework overhead. |
| Vanilla JavaScript (ES6+) | — | Canvas interaction, fetch calls to PHP endpoints | No build step; runs directly in browser; appropriate for learning project scope. |

### Signature Canvas

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| signature_pad (szimek) | 4.x (latest: 4.1.7 as of Aug 2025) | Finger-draw signature capture on HTML5 Canvas | The de facto standard library for this use case. Handles pointer events, touch events, pressure sensitivity, and cross-browser quirks that raw Canvas API does not handle well. MIT license. CDN-available, no build step. |

**Confidence:** MEDIUM — version 4.1.7 is from training data (Aug 2025 cutoff). Verify current version at https://github.com/szimek/signature_pad/releases before pinning.

**Why not raw Canvas API:** Drawing smooth bezier curves from touch events requires Catmull-Rom or similar interpolation. Building this manually adds ~100+ lines of fiddly code. signature_pad solves it correctly in one `<script>` tag. For a learning project, this is the right tradeoff — learn the concept, don't reimplement a solved problem.

### PHP Modules Required

| Module | Purpose | Why |
|--------|---------|-----|
| `session` | Login state management | Built into PHP core; `session_start()` + `$_SESSION` is the simplest possible stateful auth |
| `gd` or `imagick` | PNG save from base64 canvas data | Needed to decode the base64 PNG that the Canvas/JS sends and write it to disk |
| `curl` or `file_get_contents` with `allow_url_fopen` | Micropay SMS API (GET request) | `file_get_contents(url)` is simplest for a single GET; cPanel hosts almost always have this enabled |
| `openssl` | HTTPS (handled by host, not your code) | Already active on ch-ah.info; no action needed |

**Confidence:** HIGH — these are core PHP modules present on every cPanel shared host.

### Infrastructure

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| cPanel Shared Hosting (ch-ah.info) | — | Web server, file storage, PHP execution | Already decided by constraint. Apache + mod_php is the standard cPanel stack. |
| Apache `.htaccess` | — | Redirect rules, directory protection | Required to block direct access to `signatures/` folder and enforce HTTPS redirect |
| FTP / cPanel File Manager | — | Deployment | Standard cPanel deployment; no CI/CD needed for this scope |

### Supporting Libraries / Utilities

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| signature_pad (CDN) | 4.x | Canvas signature drawing | Loaded via `<script src="https://cdn.jsdelivr.net/npm/signature_pad@4/dist/signature_pad.umd.min.js">` on the sign.php page only |
| No CSS framework | — | — | Bootstrap/Tailwind is overkill for 3 pages. Write 50 lines of plain CSS instead. |
| No JavaScript framework | — | — | React/Vue adds build tooling complexity for zero benefit here. |
| No database | — | — | No persistent state beyond PNG files. SQLite or MySQL would be over-engineering. |

---

## PHP Configuration for cPanel

These settings matter for this project specifically:

```ini
; Verify these in cPanel → PHP Selector or php.ini override
upload_max_filesize = 2M      ; Signature PNGs are small; default is fine
post_max_size = 8M            ; Fine as-is
session.save_path = /tmp      ; cPanel default; sessions work without changes
allow_url_fopen = On          ; Required for file_get_contents() SMS call
display_errors = Off          ; Never show errors in production
log_errors = On               ; Log to error_log file instead
error_log = /home/[user]/logs/php_errors.log
```

**PHP version selection in cPanel:** Go to cPanel → Software → MultiPHP Manager → set ch-ah.info to PHP 8.2. Avoid PHP 7.x (security EOL). PHP 8.3 is safe if available, but 8.2 has the widest shared hosting support as of early 2026.

**Confidence:** HIGH for the configuration advice. MEDIUM for "8.2 widest support" — verify PHP Selector options in your actual cPanel panel.

---

## File Structure Recommendation

```
FirstApp/
├── index.php          ← Login form (POST → validates hardcoded creds → session → redirect)
├── dispatch.php       ← Protected page: send SMS button (checks session at top)
├── sign.php           ← Public mobile page: Canvas + signature_pad
├── save-signature.php ← AJAX endpoint: receives base64 PNG, saves to disk, sends confirm SMS
├── logout.php         ← Destroys session, redirects to index.php
├── signatures/        ← Saved PNG files (must be web-inaccessible or .htaccess protected)
│   └── .htaccess      ← "Deny from all" — blocks direct URL access to PNGs
├── css/
│   └── style.css      ← 50-80 lines of RTL-ready mobile CSS
└── .htaccess          ← Root: force HTTPS, disable directory listing
```

---

## Alternatives Considered

| Category | Recommended | Alternative | Why Not |
|----------|-------------|-------------|---------|
| Signature library | signature_pad 4.x | Raw HTML5 Canvas API | Raw canvas requires manual bezier interpolation for smooth strokes — 100+ lines of fiddly code; not worth it for this scope |
| Signature library | signature_pad 4.x | jSignature (jQuery-based) | Requires jQuery dependency; last significant update was 2018; effectively unmaintained |
| CSS framework | None (plain CSS) | Bootstrap 5 | Adds 200KB for 3 simple pages; RTL requires extra Bootstrap RTL bundle; overkill |
| JavaScript framework | None (vanilla JS) | React / Vue | Requires Node.js build pipeline; incompatible with simple FTP deployment; wrong tool for this scale |
| Backend | PHP | Node.js | cPanel shared hosting does not support persistent Node.js server processes — PHP is the only backend option here |
| Auth | PHP sessions | JWT tokens | JWT is stateless API auth; overkill and more complex than sessions for a single-user app with server-rendered pages |
| Storage | File system (PNG) | MySQL database | No relational data to store; PNG files in a folder is simpler, faster to implement, and perfectly sufficient |
| SMS API call | `file_get_contents()` | cURL | Both work; `file_get_contents` is 1 line vs 8 lines of cURL setup for a simple GET request. Use cURL only if you need SSL verification control. |

---

## Installation / Integration

```php
<!-- In sign.php <head> — no npm, no build step -->
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4/dist/signature_pad.umd.min.js"></script>
```

```javascript
// Minimal signature_pad setup (sign.php)
const canvas = document.getElementById('signature-canvas');
const signaturePad = new SignaturePad(canvas, {
  backgroundColor: 'rgb(255, 255, 255)', // Required for PNG with white background
  penColor: 'rgb(0, 0, 0)'
});

// Resize canvas to device pixel ratio (prevents blurry signatures on HiDPI screens)
function resizeCanvas() {
  const ratio = Math.max(window.devicePixelRatio || 1, 1);
  canvas.width = canvas.offsetWidth * ratio;
  canvas.height = canvas.offsetHeight * ratio;
  canvas.getContext('2d').scale(ratio, ratio);
  signaturePad.clear(); // Clear on resize to prevent distortion
}
window.addEventListener('resize', resizeCanvas);
resizeCanvas();

// Export as PNG for PHP endpoint
function submitSignature() {
  if (signaturePad.isEmpty()) {
    alert('נא לחתום לפני שליחה'); // Hebrew: "Please sign before submitting"
    return;
  }
  const dataURL = signaturePad.toDataURL('image/png');
  // POST dataURL to save-signature.php via fetch()
}
```

```php
<?php
// save-signature.php — Save base64 PNG to disk
session_start();
$data = $_POST['signature']; // base64 data URL from JS

// Strip the "data:image/png;base64," prefix
$imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data));

$filename = 'signatures/' . uniqid('sig_', true) . '.png';
file_put_contents($filename, $imageData);

// Send confirmation SMS via Micropay (GET request)
// Token stored only here in PHP — never in JS
$token = 'YOUR_MICROPAY_TOKEN';
$phone = '0526804680';
$message = urlencode(iconv('UTF-8', 'ISO-8859-8', 'המסמך נחתם'));
file_get_contents("https://api.micropay.co.il/send?token={$token}&to={$phone}&msg={$message}");

echo json_encode(['status' => 'ok', 'file' => $filename]);
?>
```

---

## Security Essentials for This Stack

| Risk | Prevention |
|------|------------|
| Micropay token exposed in client JS | Token lives only in PHP files. Never echoed to page. |
| Direct URL access to saved PNGs | `signatures/.htaccess` with `Deny from all` |
| Session fixation | Call `session_regenerate_id(true)` after successful login |
| Path traversal in filename | Use `uniqid()` for filenames — never user input |
| Unauthenticated access to dispatch.php | First line: `session_start(); if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }` |
| Directory listing of signatures/ | Root `.htaccess`: `Options -Indexes` |

---

## Confidence Assessment

| Area | Confidence | Reason |
|------|------------|--------|
| PHP as backend | HIGH | Dictated by cPanel constraint; no alternative exists |
| PHP 8.2 recommendation | MEDIUM | Training data Aug 2025; verify cPanel PHP Selector options at ch-ah.info |
| signature_pad 4.x | MEDIUM | Training data confirms 4.1.7 as of Aug 2025; verify current version at github.com/szimek/signature_pad/releases |
| signature_pad vs raw Canvas | HIGH | Raw canvas touch interpolation is a well-documented pain point; this tradeoff is stable |
| No CSS framework | HIGH | 3-page app with straightforward RTL; framework adds zero value |
| PHP sessions for auth | HIGH | Industry standard for server-rendered PHP single-user apps |
| file_get_contents for Micropay | HIGH | Simple GET request; this is the standard pattern |
| PNG file storage | HIGH | No relational data; file system is correct for this scope |

---

## Sources

- signature_pad GitHub: https://github.com/szimek/signature_pad (training data, Aug 2025 — verify release page for current version)
- PHP supported versions: https://www.php.net/supported-versions.php (verify PHP 8.2 EOL date)
- Micropay API: Constraint provided in project spec (GET method, ISO-8859-8, token-based)
- cPanel PHP configuration: Training data — verify MultiPHP Manager availability at ch-ah.info cPanel

**Note:** WebSearch and WebFetch tools were unavailable in this research session. All version numbers and PHP support dates are from training data (cutoff Aug 2025). The two items flagged MEDIUM confidence should be spot-checked before the roadmap is finalized.
