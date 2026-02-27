# Architecture Patterns

**Project:** FirstApp — Signature Dispatch System
**Domain:** Simple PHP web app on cPanel shared hosting
**Researched:** 2026-02-27
**Confidence:** HIGH — PHP/cPanel shared hosting conventions are stable and well-established

---

## Recommended Architecture

This app follows the **Thin PHP API** pattern: static HTML/CSS/JS pages handle all presentation,
and PHP files handle only server-side work (auth, SMS dispatch, file saving). No framework needed.

```
Browser (HTML/CSS/JS)
        |
        |  HTTP POST (form / fetch)
        v
   PHP API Endpoints          ← server-side boundary: secrets live here
   (api/*.php)
        |
        |-- PHP Sessions -------> Session store (server temp files)
        |-- Micropay API -------> External SMS gateway (GET over HTTPS)
        |-- File System ---------> signatures/ folder (PNG files)
```

---

## File and Folder Organization

```
ch-ah.info/FirstApp/            ← Web root for this app
│
├── index.html                  ← PUBLIC: Login page
├── dashboard.html              ← PROTECTED (client side): Dispatch UI
├── sign.html                   ← PUBLIC: Mobile signature canvas page
│
├── api/                        ← Server-side PHP — never served as HTML
│   ├── login.php               ← Validates credentials, starts PHP session
│   ├── send-sms.php            ← Sends signing-link SMS via Micropay
│   ├── save-signature.php      ← Saves PNG, sends confirmation SMS
│   └── config.php              ← Credentials/token constants (NOT public)
│
├── signatures/                 ← Saved PNG files
│   └── .htaccess               ← BLOCKS direct browser access to PNGs
│
├── css/
│   └── style.css               ← Shared styles (RTL, Hebrew font)
│
├── js/
│   └── signature-pad.js        ← Canvas drawing logic for sign.html
│
└── .htaccess                   ← Root-level rewrite / security rules
```

---

## Component Boundaries

| Component | Type | Responsibility | Communicates With |
|-----------|------|---------------|-------------------|
| `index.html` | Public HTML | Login form UI, collect credentials | `api/login.php` via POST fetch |
| `dashboard.html` | Protected HTML | Dispatch form UI, trigger SMS send | `api/send-sms.php` via fetch |
| `sign.html` | Public HTML | Canvas signature capture (mobile) | `api/save-signature.php` via fetch |
| `api/login.php` | PHP endpoint | Validate user, set session cookie | Browser (response), PHP session store |
| `api/send-sms.php` | PHP endpoint | Build SMS link, call Micropay API | Micropay (outbound HTTPS GET), session store (auth check) |
| `api/save-signature.php` | PHP endpoint | Receive PNG blob, write to disk, send confirmation SMS | File system (`signatures/`), Micropay (outbound HTTPS GET) |
| `api/config.php` | PHP include | Store Micropay token, admin phone, base URL constants | Included by other PHP files only |
| `signatures/` | File store | Hold signed PNG files | `save-signature.php` (write), admin (download) |

---

## Data Flow

### 1. Login Flow

```
index.html
  -> [user types username + password]
  -> fetch POST /api/login.php {username, password}
     -> PHP checks against hardcoded values in config.php
     -> if valid: session_start(), $_SESSION['logged_in'] = true, return {ok: true}
     -> if invalid: return {ok: false, message: "שגיאה: פרטים שגויים"}
  <- JS reads response
  <- if ok: window.location = 'dashboard.html'
  <- if not ok: show error message on page
```

### 2. Dispatch (Send SMS) Flow

```
dashboard.html
  -> [Sharon clicks "שלח לחתימה"]
  -> fetch POST /api/send-sms.php {phone}
     -> PHP checks $_SESSION['logged_in'] — if missing: return 401
     -> PHP builds signing URL: https://ch-ah.info/FirstApp/sign.html?id=UNIQUE_ID
     -> PHP calls Micropay GET API with token (from config.php), phone, Hebrew message
     -> Micropay sends SMS to recipient's phone
     -> return {ok: true}
  <- JS shows success message on dashboard
```

### 3. Signature Flow

```
sign.html
  [no auth required — accessed via SMS link on recipient's phone]
  -> [recipient draws signature with finger on canvas]
  -> [clicks "אישור"]
  -> JS: canvas.toDataURL('image/png') -> base64 string
  -> fetch POST /api/save-signature.php {signature: base64, id: URL_PARAM}
     -> PHP decodes base64, validates it looks like PNG data
     -> PHP saves file to signatures/{id}.png on disk
     -> PHP calls Micropay GET API: sends "המסמך נחתם" SMS to admin phone
     -> return {ok: true}
  <- JS shows "תודה! החתימה נשמרה" confirmation message
```

---

## Security Boundaries

### What Is Public (no auth required)

| Resource | Why Public |
|----------|-----------|
| `index.html` | Login page — everyone needs to see it |
| `sign.html` | Recipients are not app users — link from SMS |
| `css/`, `js/` | Static assets |

### What Is Protected

| Resource | Protection Mechanism | Why |
|----------|---------------------|-----|
| `dashboard.html` | Client-side redirect (JS checks session via API call) | Prevents casual access |
| `api/send-sms.php` | Server-side PHP session check — returns 401 if not logged in | Real protection — token never exposed |
| `api/config.php` | PHP `include` only — never a direct URL endpoint | Micropay token stays server-side |
| `signatures/` | `.htaccess` deny rule | PNGs not browseable directly |

**Critical:** The Micropay API token lives only in `api/config.php`. It is never placed in HTML,
JS, or any file the browser can request directly. All Micropay calls go through PHP.

### .htaccess Rules Needed

```apache
# Block direct access to signatures folder
# (place in signatures/.htaccess)
deny from all

# Optionally block direct access to api/ PHP source view
# (cPanel PHP executes .php files, so this is mainly defensive)
```

---

## Patterns to Follow

### Pattern 1: PHP Session Auth Guard

Every protected PHP endpoint starts with this block. No exceptions.

```php
<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}
```

### Pattern 2: JSON API Response

All `api/*.php` files return JSON, never HTML. JS reads the response and updates the page.

```php
<?php
header('Content-Type: application/json; charset=utf-8');
// ... do work ...
echo json_encode(['ok' => true]);
```

### Pattern 3: Config Isolation

Secrets go in one place only. All other PHP files include it.

```php
<?php
// api/config.php
define('MICROPAY_TOKEN', 'your-token-here');
define('ADMIN_PHONE',    '0526804680');
define('BASE_URL',       'https://ch-ah.info/FirstApp');
define('ADMIN_USER',     'Sharonb');
define('ADMIN_PASS',     '1532');         // hashed in production
```

### Pattern 4: Canvas-to-Server PNG Transfer

JS converts the canvas to base64 and POSTs it. PHP decodes and saves.

```javascript
// JS (sign.html)
const dataUrl = canvas.toDataURL('image/png');          // "data:image/png;base64,..."
const base64  = dataUrl.split(',')[1];                  // strip the prefix
fetch('/api/save-signature.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ signature: base64, id: sigId })
});
```

```php
// PHP (save-signature.php)
$data = json_decode(file_get_contents('php://input'), true);
$png  = base64_decode($data['signature']);
$id   = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['id']); // sanitize
file_put_contents(__DIR__ . '/../signatures/' . $id . '.png', $png);
```

---

## Anti-Patterns to Avoid

### Anti-Pattern 1: Token in JavaScript

**What:** Putting the Micropay API token in a `.js` file or inline `<script>` tag.
**Why bad:** Any visitor can view page source and steal the token. SMS costs money and the API has no per-number rate limiting.
**Instead:** Token lives in `api/config.php`. JS calls `/api/send-sms.php` — PHP makes the real API call.

### Anti-Pattern 2: Client-Side-Only Auth on Dashboard

**What:** Only using JS to check "are you logged in?" with a cookie or localStorage flag.
**Why bad:** Trivially bypassed by opening DevTools and setting the flag manually.
**Instead:** Every sensitive API endpoint (send-sms.php) checks the PHP session server-side. Even if someone navigates to `dashboard.html`, they cannot trigger SMS dispatch without a valid session.

### Anti-Pattern 3: Unsanitized File Names

**What:** Using raw user input (URL parameter or POST field) as a filename for the PNG.
**Why bad:** Path traversal attack — `../../config.php` as an ID would overwrite server files.
**Instead:** Sanitize the ID with a whitelist regex before using it in `file_put_contents`. See Pattern 4 above.

### Anti-Pattern 4: Mixing Logic Into HTML Files

**What:** Embedding PHP directly in `index.html` (rename it `index.php`), combining presentation and logic.
**Why bad:** Harder to understand, test, and maintain. The clean separation of HTML + API is the learning goal.
**Instead:** Keep `.html` files as pure static HTML/JS. All PHP lives in `api/`. This also makes the architecture obvious and teachable.

### Anti-Pattern 5: Storing Signatures in Web Root Without .htaccess

**What:** Saving PNGs to `signatures/` but not blocking direct URL access.
**Why bad:** Anyone who guesses or intercepts the signing URL can download the signature PNG later.
**Instead:** Add `deny from all` in `signatures/.htaccess`. Admin can download via FTP if needed.

---

## Suggested Build Order (Phase Dependencies)

The components have clear dependencies. Build in this order to always have a testable system:

```
1. Config & Folder Structure
   └── api/config.php, .htaccess files, signatures/.htaccess
       (No dependencies. Sets up the security skeleton first.)

2. Login Flow
   └── index.html + api/login.php
       (Requires: config.php for credentials constant)
       (Test: can login, gets session cookie, gets error on bad password)

3. Dashboard Shell
   └── dashboard.html (static UI, no SMS yet)
       (Requires: login to work, session to redirect)
       (Test: dashboard loads after login, redirects to index if not logged in)

4. SMS Dispatch
   └── api/send-sms.php
       (Requires: config.php for token, session auth from step 2)
       (Test: SMS arrives on 0526804680 with correct Hebrew link)

5. Signature Page
   └── sign.html (canvas UI only, no save yet)
       (Requires: nothing — public page)
       (Test: canvas draws with finger on mobile)

6. Signature Save + Confirmation SMS
   └── api/save-signature.php
       (Requires: signatures/ folder writable, config.php for token and admin phone)
       (Test: PNG appears in signatures/, confirmation SMS received)

7. End-to-End Test
   └── Full flow: login → send SMS → open link → sign → confirm SMS
```

---

## Scalability Considerations

This is a single-user learning project. Scalability is not a concern — but these limits are good to know:

| Concern | At current scale | If it ever grows |
|---------|-----------------|-----------------|
| Sessions | PHP file-based sessions — fine for 1 user | Switch to DB sessions |
| Signatures | Flat file storage in signatures/ — fine for dozens | Move to cloud storage |
| Auth | Hardcoded credentials — fine for 1 user | Add users table in MySQL (cPanel includes it) |
| SMS token | Plaintext in config.php — acceptable on private server | Use cPanel environment variables |

---

## cPanel Deployment Notes

| Concern | Detail |
|---------|--------|
| PHP version | cPanel typically offers PHP 7.4–8.3. Request 8.x via cPanel "Select PHP Version". |
| File permissions | PHP must write to `signatures/` — set folder permissions to `755`, files to `644` |
| Session storage | PHP sessions stored in server `/tmp` by default — no config needed |
| HTTPS | cPanel provides free Let's Encrypt SSL — enable before going live (session cookies need HTTPS) |
| FTP upload | Use FileZilla with cPanel FTP credentials to deploy files |

---

## Sources

- PHP session documentation: https://www.php.net/manual/en/book.session.php (HIGH confidence — official)
- PHP file_put_contents: https://www.php.net/manual/en/function.file-put-contents.php (HIGH confidence — official)
- HTML5 Canvas toDataURL: https://developer.mozilla.org/en-US/docs/Web/API/HTMLCanvasElement/toDataURL (HIGH confidence — MDN official)
- Apache .htaccess deny syntax: cPanel standard Apache configuration (HIGH confidence — established convention)
- cPanel PHP version selection: cPanel documentation (HIGH confidence — standard feature)
- Micropay SMS API: GET-based token API — details from PROJECT.md (project-specific)
