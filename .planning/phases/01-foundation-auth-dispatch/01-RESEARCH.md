# Phase 1: Foundation, Auth, and Dispatch — Research

**Researched:** 2026-02-28
**Domain:** PHP session authentication, .htaccess security, Micropay SMS API integration on cPanel shared hosting
**Confidence:** HIGH

---

## Summary

Phase 1 builds everything on Sharon's side of the signing loop: a working login flow, a session-protected dispatch page, and a real SMS arriving on a phone. The technology choices are all dictated by the existing hosting constraint (cPanel shared hosting = PHP, no Node.js) and the prior project decisions locked in during initialization.

The three technical domains in this phase are well-established and fully verified: PHP session authentication (a ~30-year-old pattern with zero moving parts), Apache .htaccess security rules (static configuration syntax), and the Micropay SMS API (a GET/POST request with a token). None of these require third-party npm packages, build tools, or framework setup. The entire phase can be built with a text editor and an FTP client.

The most important risk to verify before building: `allow_url_fopen` may be disabled on ch-ah.info's cPanel PHP configuration, which would break `file_get_contents()` for the Micropay API call. The fallback — cURL — is always available. Plans should use cURL as the default (not `file_get_contents`) to avoid a silent failure on first deploy.

**Primary recommendation:** Build in the order Config → Auth → Dispatch, verifying each step on the live server (not just localhost) before proceeding. Every PHP file in `api/` starts with `session_start()` as line 1. The Micropay token lives only in `api/config.php` and never touches any file the browser can read.

---

## Standard Stack

### Core

| Technology | Version | Purpose | Why Standard |
|------------|---------|---------|--------------|
| PHP | 8.2.x | Session auth, SMS API call, JSON responses | cPanel constraint; 8.2 is the current default on cPanel servers as of Dec 2025 (Princeton OIT confirmed). 8.3 also available on most hosts. Never use 7.x (EOL). |
| HTML5 / CSS3 / Vanilla JS (ES6+) | Living Standard | Login form, dispatch UI, fetch API calls | No build step; runs directly in browser; correct for this 2-page scope |
| Apache `.htaccess` | Apache 2.4 syntax | Directory protection, HTTPS redirect | cPanel uses Apache; .htaccess is the standard mechanism for per-directory rules |

### Supporting

| Technology | Version | Purpose | When to Use |
|------------|---------|---------|-------------|
| PHP cURL extension | Built-in | Call Micropay API from PHP | **Always use cURL** (not `file_get_contents`) — `allow_url_fopen` is a security setting that may be disabled on ch-ah.info. cURL is always available. |
| PHP `json_encode` / `json_decode` | Built-in | API response format between PHP and JS | All `api/*.php` files return JSON; JS reads with `response.json()` |
| PHP `iconv` | Built-in | Convert UTF-8 Hebrew to ISO-8859-8 for Micropay | Required for correct Hebrew SMS delivery |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| cURL (recommended) | `file_get_contents($url)` | `file_get_contents` is simpler (1 line) but `allow_url_fopen` may be `Off` on shared hosting — would fail silently. cURL works regardless of `allow_url_fopen`. |
| PHP sessions | JWT tokens | JWT is stateless API auth; overkill for a single-user server-rendered app. Sessions are built into PHP with zero dependencies. |
| `.htaccess` per-directory | Server-level Apache config | cPanel users cannot edit server-level Apache config. `.htaccess` is the only mechanism available. |
| Hardcoded credentials in `config.php` | MySQL users table | No database needed; single user; hardcoding is correct for this scope. |

**No installation required.** All components are built into PHP or configured via text files.

---

## Architecture Patterns

### Recommended Project Structure

```
ch-ah.info/FirstApp/
│
├── index.html              ← PUBLIC: Login form (HTML + vanilla JS)
├── dashboard.html          ← SEMI-PROTECTED: Dispatch UI (JS checks session)
│
├── api/                    ← SERVER-SIDE PHP: secrets live here, never in HTML/JS
│   ├── config.php          ← Credentials + Micropay token constants (NEVER served as HTML)
│   ├── login.php           ← Validates creds, starts session, returns JSON
│   ├── send-sms.php        ← Checks session, calls Micropay, returns JSON
│   └── check-session.php  ← Returns session status (used by dashboard.html on load)
│
├── signatures/             ← (Phase 2) PNG files
│   └── .htaccess           ← Deny from all
│
├── css/
│   └── style.css           ← RTL Hebrew styles
│
└── .htaccess               ← HTTPS redirect + deny directory listing
```

**Note on `api/` protection:** PHP files in `api/` cannot be "protected" with .htaccess in the way static files can — the PHP interpreter executes them normally. Protection comes from: (1) never putting secrets in the HTTP response body, and (2) checking sessions server-side before doing anything sensitive.

### Pattern 1: Config Isolation

**What:** All secrets and constants in one file, included by all other PHP files.

**Why:** Single source of truth; change token in one place; auditing is easy.

```php
<?php
// api/config.php
// SOURCE: Project decision (Init)
define('MICROPAY_TOKEN', '16nI8fd3c366a8a010e76c7a03c7709178af');
define('ADMIN_PHONE',    '0526804680');
define('BASE_URL',       'https://ch-ah.info/FirstApp');
define('ADMIN_USER',     'Sharonb');
define('ADMIN_PASS',     '1532');
```

**Critical:** `api/config.php` is included by other PHP files via `require_once __DIR__ . '/config.php'`. It is NEVER the target of a URL request. The browser cannot request it directly (it would get a blank response since it produces no output), but it is still correct practice to never echo its contents.

### Pattern 2: PHP Session Auth Guard

**What:** Every protected PHP endpoint starts with this block — no exceptions.

**Source:** PHP official documentation (https://www.php.net/manual/en/features.session.security.management.php)

```php
<?php
// First lines of every api/*.php that requires auth
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}
```

### Pattern 3: Login with Session Regeneration

**What:** After successful credential check, regenerate the session ID before writing to `$_SESSION`.

**Why:** Prevents session fixation attacks. Official PHP docs state: "session_regenerate_id() must be called prior to setting the authentication information to $_SESSION."

```php
<?php
// api/login.php
session_start();

header('Content-Type: application/json; charset=utf-8');

$body = json_decode(file_get_contents('php://input'), true);
$username = $body['username'] ?? '';
$password = $body['password'] ?? '';

if ($username === ADMIN_USER && $password === ADMIN_PASS) {
    session_regenerate_id(true);          // MUST be called before setting session data
    $_SESSION['logged_in'] = true;
    $_SESSION['user'] = $username;
    echo json_encode(['ok' => true]);
} else {
    http_response_code(401);
    // Hebrew error message per AUTH-02
    echo json_encode(['ok' => false, 'message' => 'שם כניסה או סיסמא לא תקינים']);
}
```

### Pattern 4: Dashboard Session Check (Client-Side Redirect)

**What:** `dashboard.html` is a static HTML file — PHP cannot guard it at the file level. Instead, on `DOMContentLoaded`, JS calls `api/check-session.php`. If not logged in, redirect to `index.html`.

**This provides usability protection (not security).** Real security is that `api/send-sms.php` checks the session server-side — even if someone bypasses the JS redirect and opens `dashboard.html` directly, clicking the dispatch button will get a 401.

```javascript
// dashboard.html <script>
document.addEventListener('DOMContentLoaded', async () => {
    const res = await fetch('api/check-session.php');
    const data = await res.json();
    if (!data.ok) {
        window.location.href = 'index.html';
    }
});
```

```php
<?php
// api/check-session.php
session_start();
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true
]);
```

### Pattern 5: Micropay SMS API Call via cURL (PHP)

**What:** PHP calls the Micropay GET API using cURL. Token is read from `config.php`. Never in JS.

**Source:** Micropay skill documentation (C:/Users/Alias/.claude/skills/send-sms-micropay/SKILL.md) + verified against Hebrew forum discussion (tchumim.com)

**CRITICAL encoding step:** The message must be converted from UTF-8 to ISO-8859-8 before URL-encoding. Hebrew SMS uses UCS-2 encoding at carrier level (70 char limit, not 160).

```php
<?php
// api/send-sms.php (after auth guard)
require_once __DIR__ . '/config.php';

$phone   = ADMIN_PHONE;                                         // '0526804680'
$signUrl = BASE_URL . '/sign.html';                             // signing page link
// Hebrew message: "היכנס לקישור הבא:" + URL  (DISP-03)
// Count carefully: Hebrew chars use 70-char limit per SMS segment
$messageUtf8 = 'היכנס לקישור הבא: ' . $signUrl;

// Convert UTF-8 → ISO-8859-8 (required by Micropay API)
$messageEncoded = iconv('UTF-8', 'ISO-8859-8', $messageUtf8);

$params = http_build_query([
    'get'     => '1',
    'token'   => MICROPAY_TOKEN,
    'msg'     => $messageEncoded,
    'list'    => $phone,
    'charset' => 'iso-8859-8',
    'from'    => 'Chemo IT',
]);

$url = 'http://www.micropay.co.il/ExtApi/ScheduleSms.php?' . $params;

// Use cURL (not file_get_contents) — allow_url_fopen may be Off
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$result = curl_exec($ch);
$error  = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'SMS send failed: ' . $error]);
    exit;
}

echo json_encode(['ok' => true, 'result' => $result]);
```

### Pattern 6: HTTPS Redirect via .htaccess

**What:** Root `.htaccess` forces all HTTP traffic to HTTPS.

**Source:** Multiple verified hosting KB articles (InMotionHosting, Namecheap, SpeedHub 2025). Apache 2.4 syntax used by cPanel.

```apache
# ch-ah.info/FirstApp/.htaccess
RewriteEngine On

# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]

# Disable directory listing
Options -Indexes
```

### Pattern 7: Directory Protection for signatures/

**What:** `.htaccess` inside `signatures/` blocks all direct browser access to PNG files.

**Source:** Apache 2.4 documentation; verified with multiple hosting KB articles.

```apache
# ch-ah.info/FirstApp/signatures/.htaccess
# Apache 2.4 syntax (cPanel standard)
Require all denied
```

**Note on Apache version:** cPanel uses Apache 2.4+ in all current installations. The old `Order deny,allow / Deny from all` syntax (Apache 2.2) still works in Apache 2.4 via a compatibility module, but `Require all denied` is the current standard. Use both for maximum compatibility:

```apache
# Supports Apache 2.2 (legacy) and 2.4+ (current)
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
</IfModule>
```

### Anti-Patterns to Avoid

- **Micropay token in JavaScript:** Any `<script>` tag or `.js` file containing the token string means anyone with DevTools can steal it and send unlimited SMS. The token lives only in `api/config.php`.
- **`session_start()` after any output:** If anything is echoed before `session_start()` (even a blank line before `<?php`), PHP throws "headers already sent" and the session silently fails. Line 1 of every session-using PHP file must be `<?php` with `session_start()` immediately after.
- **`file_get_contents($url)` for the Micropay call:** Works when `allow_url_fopen = On` but silently returns `false` when it's `Off`. Use cURL — it works regardless of that setting.
- **`include 'config.php'`:** Relative paths break when PHP is called from a different working directory. Always use `require_once __DIR__ . '/config.php'`.
- **Dashboard HTML protected only by JS redirect:** JS redirect is easily bypassed with DevTools. The real protection is the server-side session check in `api/send-sms.php`.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| HTTP-to-HTTPS redirect | Custom PHP redirect script | `.htaccess` RewriteRule | .htaccess fires at server level before PHP runs — PHP redirects can't catch direct requests to `.html` files |
| SMS character encoding | Custom hex encoding for Hebrew | `iconv('UTF-8', 'ISO-8859-8', $msg)` then `http_build_query` | iconv is a tested, production-grade encoding function; manual character mapping is error-prone |
| Session security | Custom token/cookie system | PHP `$_SESSION` with `session_regenerate_id()` | PHP sessions are hardened over decades; building on top of localStorage/cookies adds XSS risk |
| JSON request parsing | `$_POST` direct (for JS fetch) | `json_decode(file_get_contents('php://input'), true)` | JS `fetch()` with `Content-Type: application/json` sends body as JSON stream, not form-encoded POST; `$_POST` will be empty |

**Key insight:** The biggest "hand-roll" trap here is forgetting that JS `fetch()` calls with `Content-Type: application/json` do NOT populate `$_POST`. PHP must read `php://input` and decode the JSON manually.

---

## Common Pitfalls

### Pitfall 1: `allow_url_fopen` Disabled — `file_get_contents` Silently Returns `false`

**What goes wrong:** `file_get_contents('http://www.micropay.co.il/...')` returns `false` but no error is thrown. The SMS is not sent, but PHP happily returns a success response to JS.

**Why it happens:** cPanel shared hosting often has `allow_url_fopen = Off` for security. PHP does not throw an exception when `file_get_contents` fails to open a URL.

**How to avoid:** Use cURL for the Micropay call (see Pattern 5). cURL is enabled on all cPanel hosts by default and does not depend on `allow_url_fopen`.

**Warning signs:** SMS dispatch button shows "success" but no SMS arrives. No PHP errors in error log.

### Pitfall 2: `session_start()` After Output — "Headers Already Sent"

**What goes wrong:** Any character output before `session_start()` — including a BOM, trailing whitespace after `?>`, or an `echo` statement — causes PHP to throw "Warning: Cannot modify header information — headers already sent". The session is not created.

**Why it happens:** PHP sends HTTP headers with the first byte of output. Sessions require setting a `Set-Cookie` header. Once output starts, headers cannot be sent.

**How to avoid:** Every PHP file that uses sessions: `<?php` is the very first character in the file (no BOM, no blank line before it). `session_start()` is the first statement after `<?php`. Never use the closing `?>` tag at the end of PHP-only files (prevents accidental trailing newlines).

**Warning signs:** Login appears to succeed (no error message) but the dispatch page immediately redirects back to login. PHP error log shows "headers already sent."

### Pitfall 3: Hebrew SMS Arrives as `????` Characters

**What goes wrong:** The SMS is sent and the Micropay API returns success, but the message on the recipient's phone shows question marks or garbage characters instead of Hebrew.

**Why it happens:** PHP source files are UTF-8. Micropay requires `charset=iso-8859-8`. Passing a UTF-8 Hebrew string directly without `iconv` conversion sends incorrect byte sequences.

**How to avoid:** Always convert: `$msg = iconv('UTF-8', 'ISO-8859-8', $hebrewText)`. Then URL-encode: `$encoded = urlencode($msg)`. Use `http_build_query()` (see Pattern 5) which handles URL encoding automatically.

**Warning signs:** API returns success code but SMS is garbled. Message length in Hebrew exceeds 70 characters (Hebrew SMS limit vs. 160 for Latin).

### Pitfall 4: JS `fetch()` Body Not in `$_POST`

**What goes wrong:** Login JS sends `fetch('api/login.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({username, password}) })`. PHP reads `$_POST['username']` and gets `null`.

**Why it happens:** `$_POST` is only populated for `application/x-www-form-urlencoded` or `multipart/form-data`. JSON bodies are not parsed into `$_POST`.

**How to avoid:** In PHP, read the raw body: `$data = json_decode(file_get_contents('php://input'), true)`.

**Warning signs:** Login always fails with "wrong credentials" even with correct input. `var_dump($_POST)` in PHP shows empty array.

### Pitfall 5: HTTPS Not Active — Session Cookies Sent Over HTTP

**What goes wrong:** Sharon logs in over HTTP. The session cookie is transmitted in plain text. On a network that can be sniffed, session hijacking is trivial.

**Why it happens:** cPanel provides both HTTP and HTTPS. Without the .htaccess redirect, the login form may submit over HTTP.

**How to avoid:** Add the HTTPS RewriteRule to `.htaccess` (Pattern 6). Verify the Let's Encrypt SSL certificate is active on ch-ah.info before first deploy. Set session cookie flags in PHP config or via `session_set_cookie_params()`.

**Warning signs:** Browser address bar shows `http://` after login. No SSL certificate icon.

### Pitfall 6: Apache 2.2 vs 2.4 `.htaccess` Syntax Mismatch

**What goes wrong:** `Order deny,allow / Deny from all` syntax from Apache 2.2 causes a 500 Internal Server Error on Apache 2.4 servers where `mod_authz_host` is not loaded.

**Why it happens:** Apache 2.4 changed the access control module. The old syntax may not be available.

**How to avoid:** Use `Require all denied` for Apache 2.4, or the dual-syntax wrapper (Pattern 7). cPanel uses Apache 2.4 in all current installations.

**Warning signs:** 500 error when accessing a directory that should return 403.

---

## Code Examples

### Login Form POST with Fetch (index.html)

```javascript
// Source: MDN Fetch API + PHP session pattern
document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    const res = await fetch('api/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password })
    });

    const data = await res.json();

    if (data.ok) {
        window.location.href = 'dashboard.html';
    } else {
        // Display Hebrew error message (AUTH-02)
        document.getElementById('error-msg').textContent = data.message;
        document.getElementById('error-msg').style.display = 'block';
    }
});
```

### PHP Reading JSON Body

```php
<?php
// Source: PHP Manual - php://input
// Works with fetch() + Content-Type: application/json
$body = json_decode(file_get_contents('php://input'), true);
$username = $body['username'] ?? '';
$password = $body['password'] ?? '';
```

### cURL GET Request (Micropay)

```php
<?php
// Source: PHP Manual curl_init / curl_setopt
// Preferred over file_get_contents — works regardless of allow_url_fopen setting
function callUrl(string $url): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);   // Keep SSL verification ON
    $result = curl_exec($ch);
    $error  = curl_error($ch);
    $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['result' => $result, 'error' => $error, 'http_code' => $code];
}
```

### SMS Message Length Check

```php
<?php
// Hebrew SMS: 70 char limit per segment (UCS-2 encoding at carrier level)
// Count bytes AFTER conversion to check segment boundary
$message = 'היכנס לקישור הבא: https://ch-ah.info/FirstApp/sign.html';
$converted = iconv('UTF-8', 'ISO-8859-8', $message);
$charCount = strlen($converted);
// strlen() after iconv gives byte count in ISO-8859-8 = character count for Hebrew
// Keep under 70 to stay in single SMS segment
```

### RTL Hebrew HTML Base

```html
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>כניסה</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- dir="rtl" on <html> gives correct RTL layout for all form elements -->
</body>
</html>
```

---

## Micropay API Reference

Verified from SKILL.md + Hebrew forum confirmation:

| Parameter | Value | Notes |
|-----------|-------|-------|
| Endpoint | `http://www.micropay.co.il/ExtApi/ScheduleSms.php` | HTTP (not HTTPS) for the Micropay API itself |
| Method | GET | Use `http_build_query` to build the query string |
| `get` | `1` | Required — signals single/small dispatch mode |
| `token` | `16nI8fd3c366a8a010e76c7a03c7709178af` | From SKILL.md — server-side only |
| `msg` | Hebrew text after `iconv` to ISO-8859-8 | Max 70 chars for Hebrew per SMS segment |
| `list` | `0526804680` | Israeli mobile format `05xxxxxxxx` |
| `charset` | `iso-8859-8` | Required for Hebrew — do NOT use `utf-8` |
| `from` | `Chemo IT` | Max 11 chars, alphanumeric sender ID |

**Response:** Plain text. Success returns a message ID or confirmation string. Error returns error text. No standard HTTP error codes — check response body content.

**Hebrew message for DISP-03:** `היכנס לקישור הבא:` + space + signing page URL.
Count: "היכנס לקישור הבא: " = 20 Hebrew chars + URL. `https://ch-ah.info/FirstApp/sign.html` = 37 chars. Total = ~57 chars — within 70-char limit.

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `Order deny,allow` / `Deny from all` | `Require all denied` | Apache 2.4 (2012, but cPanel migrated gradually) | Use modern syntax; old syntax may 500 on strict Apache 2.4 |
| PHP 7.x on shared hosting | PHP 8.2 is new cPanel default (Dec 2025) | Dec 2025 Princeton OIT announcement | Select PHP 8.2 explicitly in cPanel MultiPHP Manager |
| `session_start()` anywhere | `session_start()` as first statement, with `session_regenerate_id()` after login | PHP 7.1+ security guidelines | Required for session fixation prevention |
| `file_get_contents($url)` for HTTP calls | cURL with `CURLOPT_RETURNTRANSFER` | Shared hosting security hardening trend | `allow_url_fopen` often disabled; cURL is always available |

**Not deprecated in this stack:**
- PHP `$_SESSION` — still the standard for server-rendered PHP auth
- `.htaccess` RewriteEngine — still the standard for cPanel-level redirects
- Micropay GET API — confirmed working from SKILL.md (project-verified)

---

## Open Questions

1. **Is `allow_url_fopen` enabled at ch-ah.info?**
   - What we know: Many cPanel hosts disable it for security. cURL is always available as fallback.
   - What's unclear: The specific setting on ch-ah.info.
   - Recommendation: Plans should use cURL exclusively — removes this risk entirely.

2. **Does ch-ah.info SSL cert cover ch-ah.info/FirstApp/ path?**
   - What we know: cPanel provides free Let's Encrypt SSL. SSL covers the domain, not specific paths.
   - What's unclear: Whether the cert is currently active and auto-renewing.
   - Recommendation: Verify in cPanel → SSL/TLS before first deploy. This must be true before login testing.

3. **Apache version at ch-ah.info?**
   - What we know: cPanel uses Apache 2.4+ in all current installations.
   - What's unclear: Whether this specific host's Apache version is 2.4 or an older variant.
   - Recommendation: Use dual-syntax `.htaccess` for directory protection (Pattern 7) to cover both versions.

4. **Is GitHub push automated or manual FTP?**
   - What we know: INFR-01 requires code pushed to GitHub (ChemoIT/FirstApp). INFR-02 requires deployment at ch-ah.info/FirstApp/.
   - What's unclear: Whether deployment is a git push that triggers FTP sync, or two separate manual steps.
   - Recommendation: Plans should treat GitHub push and FTP deploy as two separate explicit steps with no assumed automation.

---

## Sources

### Primary (HIGH confidence)

- PHP Session Security Manual — https://www.php.net/manual/en/features.session.security.management.php — verified session_regenerate_id(), session_start() requirements
- Micropay SKILL.md — C:/Users/Alias/.claude/skills/send-sms-micropay/SKILL.md — verified token, endpoint, charset, parameter names
- Project ARCHITECTURE.md — .planning/research/ARCHITECTURE.md — file structure, data flow, component boundaries
- Project STACK.md — .planning/research/STACK.md — PHP module requirements, cPanel configuration
- Project PITFALLS.md — .planning/research/PITFALLS.md — session, encoding, token exposure pitfalls

### Secondary (MEDIUM confidence)

- Princeton OIT — PHP 8.2 as new cPanel default (Dec 2025): https://oit.princeton.edu/news/raising-default-version-php-81-php-82-cpanel-servers
- InMotion Hosting — HTTPS via .htaccess: https://www.inmotionhosting.com/support/website/ssl/how-to-force-https-using-the-htaccess-file/
- Namecheap — .htaccess HTTPS redirect for cPanel: https://www.namecheap.com/support/knowledgebase/article.aspx/9770/38/how-to-use-htaccess-to-redirect-to-https-in-cpanel/
- ChemiCloud — allow_url_fopen in cPanel: https://chemicloud.com/kb/article/how-to-enable-or-disable-allow_url_fopen-in-cpanel/
- Hostinger — .htaccess deny access tutorial: https://www.hostinger.com/tutorials/how-to-restrict-access-to-your-website-using-htaccess
- tchumim.com — Hebrew forum Micropay API discussion (confirmed GET=1 and token parameter format): https://tchumim.com/topic/10026

### Tertiary (LOW confidence — verify before use)

- Micropay API response format (success/error body) — SKILL.md says "Returns message ID or confirmation" — exact response string not confirmed. Plan should log and display the raw response for debugging.

---

## Metadata

**Confidence breakdown:**

| Area | Level | Reason |
|------|-------|--------|
| Standard stack (PHP sessions, .htaccess) | HIGH | Core PHP/Apache patterns verified in official docs; 30+ year history |
| Micropay API parameters | HIGH | Verified against SKILL.md (project-owned) + Hebrew forum confirmation |
| Architecture patterns | HIGH | Based on prior project research (ARCHITECTURE.md) + PHP manual |
| `allow_url_fopen` risk | HIGH | Confirmed risk via multiple cPanel hosting KB articles |
| Apache 2.4 .htaccess syntax | HIGH | Confirmed Apache 2.4 is current cPanel standard |
| Hebrew SMS 70-char limit | HIGH | UCS-2 encoding limitation is a carrier-level standard, not API-specific |
| Micropay API response format | LOW | Raw response content not independently verified |

**Research date:** 2026-02-28
**Valid until:** 2026-08-28 (stable technologies; re-verify Micropay API URL if issues arise after 6 months)
