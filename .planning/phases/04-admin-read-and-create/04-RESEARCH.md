# Phase 4: Admin Read and Create — Research

**Researched:** 2026-02-28
**Domain:** PHP REST API endpoints, Bootstrap 5.3 RTL, vanilla JS live search, client/server validation, PostgREST INSERT pattern
**Confidence:** HIGH

---

## Summary

Phase 4 builds two things: a PHP API layer (`api/users/list.php` and `api/users/create.php`) and a front-end admin page (`admin.php`). Both pieces depend on Phase 3's `api/supabase.php` helper, which is already proven in production. The API endpoints follow the same JSON response pattern established by `api/login.php` — read JSON body from `php://input`, validate, call `supabase_request()`, respond with `{'ok': true/false}`. No new PHP patterns are needed.

The admin page is a PHP file (not HTML) so it can require `api/config.php` and `api/supabase.php` server-side if needed in future. For Phase 4 it can be static HTML-equivalent output from PHP. Bootstrap 5.3 RTL mode is activated by loading `bootstrap.rtl.min.css` and adding `lang="he" dir="rtl"` to the `<html>` element — the same pattern already used on `index.html`. The existing `css/style.css` provides the baseline; admin-specific styles go inline or in a new `css/admin.css`.

Live search is a pure client-side filter over already-loaded table rows — no server round-trip on keypress. The `crypto.getRandomValues()` Web Crypto API generates cryptographically secure passwords client-side. Both features work in all modern browsers and require zero libraries beyond Bootstrap. The only new dependency this phase introduces is Bootstrap 5.3 via CDN.

**Primary recommendation:** Build API endpoints first (Plan 04-01), then the UI (Plan 04-02). Each plan is independently verifiable: the API can be tested with curl/browser before the UI touches it.

---

## Standard Stack

### Core

| Technology | Version | Purpose | Why Standard |
|------------|---------|---------|--------------|
| PHP (built-in) | 7.4+ (cPanel) | API endpoints — validation, hashing, Supabase calls | Already proven in api/login.php, api/supabase.php |
| `api/supabase.php` helper | Phase 3 (existing) | All Supabase REST calls via `supabase_request()` | Dual-header auth already working in production |
| `password_hash()` / `PASSWORD_DEFAULT` | PHP 5.5+ built-in | Hash plaintext password before storing | PHP native bcrypt; no library needed |
| `filter_var($email, FILTER_VALIDATE_EMAIL)` | PHP 5.2+ built-in | Server-side email format validation | PHP native; no library needed |
| Bootstrap 5.3 RTL CSS | 5.3.8 (latest as of 2026-02) | Admin page layout, table, form, buttons | Industry standard; built-in RTL support |
| `crypto.getRandomValues()` | Web Crypto API (all modern browsers) | Cryptographically secure password generation | W3C standard; no library; available in all modern browsers |
| Vanilla JS (`addEventListener`, `fetch`, DOM) | ES5+ | Live search, form submit, password generator | Already used in index.html and dashboard.html |

### Supporting

| Technology | Version | Purpose | When to Use |
|------------|---------|---------|-------------|
| Bootstrap JS bundle (Popper included) | 5.3.8 | Modal components, dropdowns (Phase 5) | Load it now so Phase 5 modals work without re-architecting |
| `css/style.css` | Existing | Shared RTL base styles (already sets `direction: rtl`, `text-align: right`) | Included on admin.php alongside Bootstrap RTL |
| PostgREST `select` parameter | PostgREST 12.x | Column selection in GET — return only needed fields | Use `?select=id,first_name,last_name,email,phone,status` for list endpoint |
| PostgREST `order` parameter | PostgREST 12.x | Sort users by created_at desc | `?order=created_at.desc` gives newest users first |
| PostgREST `Prefer: return=representation` | PostgREST standard | Get inserted row back (with auto-generated `id`, `created_at`) | Add to POST insert so PHP can return the new user's ID to the browser |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Bootstrap 5.3 RTL (recommended) | Custom CSS RTL | Bootstrap handles all component mirroring (tables, inputs, buttons) automatically via RTLCSS; custom CSS would require mirroring every component manually |
| `crypto.getRandomValues()` (recommended) | `Math.random()` | `Math.random()` is NOT cryptographically secure — predictable with enough samples. `crypto.getRandomValues()` is the browser standard for secure randomness |
| Client-side live search (recommended) | Server-side search API | Server-side adds latency and requires debounce; for a table of hundreds of users, client-side filter is instant and simpler |
| `filter_var` for email validation (recommended) | Regex | `filter_var` is PHP-native, maintained by the PHP team, and correct for RFC 5321 format. Regex is harder to maintain correctly |
| `api/users/` subdirectory (recommended) | Flat `api/` dir | Phase 5 adds `update.php` and `delete.php` — grouping under `api/users/` prevents collision with future non-user endpoints (e.g., `api/docs/`) |

**No npm. No Composer. No new server-side library.** Bootstrap via CDN; everything else built-in.

---

## Architecture Patterns

### Recommended Project Structure (Phase 4 additions)

```
FirstApp/
├── admin.php                    ← NEW: Admin page (PHP file for future server-side rendering)
├── api/
│   ├── config.php               ← Existing (unchanged)
│   ├── supabase.php             ← Existing (unchanged)
│   ├── login.php                ← Existing (unchanged)
│   └── users/                  ← NEW subdirectory for user management endpoints
│       ├── list.php             ← NEW: GET all users (returns JSON array)
│       └── create.php           ← NEW: POST new user (validates, hashes pw, inserts)
├── css/
│   ├── style.css                ← Existing (shared RTL base)
│   └── admin.css                ← NEW (optional): Admin-specific styles only
└── .planning/
    └── phases/04-admin-read-and-create/
```

### Pattern 1: PHP API Endpoint Structure (established pattern from login.php)

**What:** Every PHP endpoint sets `Content-Type: application/json`, reads `php://input`, validates, calls `supabase_request()`, and echoes `json_encode(['ok' => bool, ...])`. Errors use appropriate HTTP status codes.

**When to use:** All `api/` PHP files follow this pattern.

```php
<?php
/**
 * api/users/list.php — Returns all users as JSON array
 *
 * GET request only. No body expected.
 * Returns: {"ok": true, "users": [...]} on success
 *          {"ok": false, "message": "..."} on error
 */

require_once __DIR__ . '/../../api/config.php';   // Note: two levels up from api/users/
require_once __DIR__ . '/../../api/supabase.php';

header('Content-Type: application/json; charset=utf-8');

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$result = supabase_request(
    'GET',
    '/users?select=id,first_name,last_name,id_number,email,phone,gender,foreign_worker,status,created_at&order=created_at.desc'
);

if ($result['error'] !== null || $result['http_code'] !== 200) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'שגיאה בטעינת המשתמשים']);
    exit;
}

echo json_encode(['ok' => true, 'users' => $result['data']]);
```

**CRITICAL path detail for subdirectory:** `api/users/list.php` is two levels below the project root. The `require_once` path to `config.php` and `supabase.php` must traverse up two directories with `__DIR__ . '/../../api/config.php'`. Using `__DIR__` (not relative paths) is mandatory — relative paths resolve from the working directory, not the script location, and will break on cPanel.

### Pattern 2: User Create Endpoint (validate → hash → insert → return)

**What:** POST endpoint that validates email format and password length both server-side, hashes the password with `password_hash()`, inserts into Supabase with `Prefer: return=representation`, and returns the created user's ID.

```php
<?php
/**
 * api/users/create.php — Creates a new user in public.users
 *
 * Accepts POST with JSON body:
 *   { "first_name": "...", "last_name": "...", "id_number": "...",
 *     "phone": "...", "gender": "male|female", "foreign_worker": bool,
 *     "email": "...", "password": "..." }
 *
 * Returns: {"ok": true, "user": {id, email, ...}} on success
 *          {"ok": false, "message": "..."} on validation/DB error
 */

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/supabase.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

// --- Server-side validation ---
$email    = trim($body['email']    ?? '');
$password = $body['password']      ?? '';
$firstName = trim($body['first_name'] ?? '');
$lastName  = trim($body['last_name']  ?? '');
$idNumber  = trim($body['id_number']  ?? '');
$phone     = trim($body['phone']      ?? '');
$gender    = $body['gender']          ?? '';
$foreignWorker = (bool)($body['foreign_worker'] ?? false);

// Required fields
if (!$firstName || !$lastName || !$idNumber || !$phone || !$email || !$password || !$gender) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'כל השדות חובה']);
    exit;
}

// ADMIN-03: Email format validation (server-side)
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'כתובת אימייל לא תקינה']);
    exit;
}

// ADMIN-04: Password length validation (server-side)
if (strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'הסיסמא חייבת לכלול לפחות 8 תווים']);
    exit;
}

// Gender whitelist (matches database CHECK constraint)
if (!in_array($gender, ['male', 'female'], true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'ערך מגדר לא תקין']);
    exit;
}

// Hash password — PHP owns the password lifecycle (Phase 3 decision)
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Insert into Supabase — Prefer: return=representation to get the created row back
$result = supabase_request(
    'POST',
    '/users',
    [
        'first_name'     => $firstName,
        'last_name'      => $lastName,
        'id_number'      => $idNumber,
        'phone'          => $phone,
        'gender'         => $gender,
        'foreign_worker' => $foreignWorker,
        'email'          => $email,
        'password_hash'  => $passwordHash,
        'status'         => 'active',
    ],
    true   // $prefer_rep = true → adds "Prefer: return=representation" header
);

// Supabase returns 201 on successful INSERT with Prefer: return=representation
if ($result['error'] !== null || $result['http_code'] !== 201) {
    // Check for unique constraint violation (duplicate email or id_number)
    $errMsg = $result['data']['message'] ?? '';
    if (strpos($errMsg, 'duplicate key') !== false || $result['http_code'] === 409) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => 'אימייל או מספר זהות כבר קיים במערכת']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'שגיאה ביצירת המשתמש']);
    }
    exit;
}

// $result['data'] is an array of inserted rows — take the first
$created = is_array($result['data']) ? $result['data'][0] : $result['data'];

// Remove password_hash before returning to client — never expose hash
unset($created['password_hash']);

echo json_encode(['ok' => true, 'user' => $created]);
```

### Pattern 3: Bootstrap 5.3 RTL Admin Page Structure

**What:** `admin.php` loads Bootstrap 5.3 RTL CSS via CDN, uses `lang="he" dir="rtl"`, and includes the existing `css/style.css`. The admin page has a wider container than the 400px login container — Bootstrap's `container-lg` or `container-fluid` works here.

**Key Bootstrap classes for RTL forms:**
- `.me-2` not `.mr-2` (margin-end = right in RTL becomes left)
- `.ms-2` not `.ml-2` (margin-start = left in RTL becomes right)
- `.text-end` for right-aligned text in LTR = left-aligned in RTL (usually skip this; `text-start` stays start)
- `.d-flex .gap-2` for button groups

```html
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול משתמשים</title>
    <!-- Bootstrap 5.3 RTL — official CDN, SRI hash -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.rtl.min.css"
          integrity="sha384-CfCrinSRH2IR6a4e6fy2q6ioOX7O6Mtm1L9vRvFZ1trBncWmMePhzvafv7oIcWiW"
          crossorigin="anonymous">
    <!-- Shared base styles (RTL body, font, existing variables) -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container-lg py-4">
    <h1 class="mb-4">ניהול משתמשים</h1>

    <!-- Search box (ADMIN-07) -->
    <div class="mb-3">
        <input type="search" id="search-box" class="form-control"
               placeholder="חיפוש לפי שם, אימייל או מספר זהות">
    </div>

    <!-- User table (ADMIN-06) -->
    <div class="table-responsive mb-5">
        <table class="table table-striped table-hover align-middle" id="users-table">
            <thead class="table-dark">
                <tr>
                    <th>מזהה</th>
                    <th>שם פרטי</th>
                    <th>שם משפחה</th>
                    <th>מספר זהות</th>
                    <th>אימייל</th>
                    <th>טלפון</th>
                    <th>סטטוס</th>
                </tr>
            </thead>
            <tbody id="users-tbody">
                <!-- populated by JS fetch to api/users/list.php -->
            </tbody>
        </table>
    </div>

    <!-- Create user form (ADMIN-02) -->
    <h2 class="mb-3">הוספת משתמש חדש</h2>
    <form id="create-form" novalidate>
        <!-- form fields here -->
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
<script src="js/admin.js"></script>  <!-- or inline <script> -->
</body>
</html>
```

### Pattern 4: Client-Side Live Search (vanilla JS, no library)

**What:** Filter table rows on `input` event — check if any cell's `textContent` contains the search string (case-insensitive). Works on the in-memory DOM — no server call.

**Source:** Confirmed pattern from multiple vanilla JS resources; W3Schools, dev.to, joshbduncan.com

```javascript
// Source: Standard vanilla JS pattern — no library
document.getElementById('search-box').addEventListener('input', function () {
    var query = this.value.trim().toLowerCase();
    var rows   = document.querySelectorAll('#users-tbody tr');

    rows.forEach(function (row) {
        // Check all cells in the row (name, email, id_number columns)
        var text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
});
```

**Why client-side is correct here:** The full user list is loaded once on page load. Filtering does not need server round-trips for a table of hundreds of users. Client-side filtering is instant and keeps the implementation simple.

### Pattern 5: Cryptographically Secure Password Generator

**What:** Use `crypto.getRandomValues()` — the W3C Web Crypto API — to fill a `Uint8Array` with random bytes, then map each byte to a character set. Do NOT use `Math.random()` — it is not cryptographically secure.

**Source:** W3C Web Crypto API (standard), verified via jsgenerator.com example

```javascript
// Source: W3C Web Crypto API — https://www.w3.org/TR/WebCryptoAPI/
// Available in all modern browsers (Chrome, Firefox, Safari, Edge)
function generateSecurePassword(length) {
    // Character set: uppercase + lowercase + digits + safe symbols
    var charset = 'ABCDEFGHIJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$';
    // Note: removed ambiguous chars: 0, O, l, 1, I
    var randomBytes = new Uint8Array(length);
    window.crypto.getRandomValues(randomBytes);
    var password = '';
    for (var i = 0; i < randomBytes.length; i++) {
        password += charset[randomBytes[i] % charset.length];
    }
    return password;
}

// Wire to button (ADMIN-05)
document.getElementById('generate-pw-btn').addEventListener('click', function () {
    var pw = generateSecurePassword(12);  // 12 chars — well above 8-char minimum
    document.getElementById('password').value = pw;
    // Show the password field so admin can see and copy it
    document.getElementById('password').type = 'text';
});
```

### Pattern 6: Dual Validation (Frontend + Backend)

**What:** Validate on the frontend with HTML5 attributes and JS before `fetch`, AND re-validate on the backend in PHP. Never trust client-side only.

**Frontend (HTML5 + JS before fetch):**
```html
<!-- ADMIN-03: Email format -->
<input type="email" id="email" required>

<!-- ADMIN-04: Password minimum 8 chars -->
<input type="password" id="password" minlength="8" required>
```

```javascript
// Before submitting — explicit JS check as well as HTML5 native
function validateCreateForm(data) {
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(data.email)) {
        return 'כתובת אימייל לא תקינה';
    }
    if (data.password.length < 8) {
        return 'הסיסמא חייבת לכלול לפחות 8 תווים';
    }
    return null;  // null = valid
}
```

**Backend (PHP in create.php):** `filter_var($email, FILTER_VALIDATE_EMAIL)` and `strlen($password) < 8` — always run server-side regardless of what the frontend sends.

### Anti-Patterns to Avoid

- **`Math.random()` for password generation:** Not cryptographically secure. Use `crypto.getRandomValues()`.
- **Relative paths in `require_once` for subdirectory PHP files:** `require_once '../config.php'` resolves from the working directory (which may not be `api/users/`). Always use `__DIR__ . '/../../api/config.php'`.
- **Sending `password_hash` back to the client:** The `create.php` endpoint receives the hash after insertion — remove it with `unset()` before `json_encode()`.
- **Storing plaintext password in Supabase and hashing later:** Always `password_hash()` BEFORE the Supabase POST. Never store plaintext.
- **Client-side only validation:** HTML5 `required`/`type="email"` is easily bypassed with DevTools. Backend validation is mandatory (ADMIN-03, ADMIN-04).
- **Encoding issues with Hebrew text in JSON:** PHP's `json_encode()` by default escapes non-ASCII as `\uXXXX`. Pass `JSON_UNESCAPED_UNICODE` flag to keep Hebrew readable in logs: `json_encode($data, JSON_UNESCAPED_UNICODE)`.
- **Loading Bootstrap LTR CSS instead of RTL:** Using `bootstrap.min.css` (not `bootstrap.rtl.min.css`) will render tables and form controls in wrong direction. The filename difference is `.rtl.min.css`.
- **Using `.ml-*`/`.mr-*` Bootstrap classes:** These are Bootstrap 4 classes. Bootstrap 5 uses logical properties: `.ms-*` (margin-start) and `.me-*` (margin-end). Wrong class = no margin applied.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Password hashing | Custom bcrypt or MD5 | `password_hash($pw, PASSWORD_DEFAULT)` | PHP-native bcrypt with correct cost, timing-safe verify |
| Email format validation | Custom regex | `filter_var($email, FILTER_VALIDATE_EMAIL)` | PHP-maintained, RFC-compliant, no regex maintenance |
| Cryptographically secure randomness | `Math.random()` string | `window.crypto.getRandomValues()` | Standard W3C API; Math.random is not CSPRNG |
| RTL CSS mirroring | Manual CSS per-component | Bootstrap 5.3 `bootstrap.rtl.min.css` | RTLCSS auto-mirrors all Bootstrap components |
| HTTP request to Supabase | New cURL function | `supabase_request()` from `api/supabase.php` | Dual-header auth already tested in production |
| Live search | External library (DataTables, etc.) | 10 lines of vanilla JS on `input` event | DataTables adds 400KB; table data is already in DOM |
| Unique constraint error detection | Custom database check | Catch Supabase 409 / "duplicate key" in response | Supabase returns clear error message; no pre-check needed |

**Key insight:** Every "hard" problem in this phase (hashing, validation, randomness, RTL layout, HTTP) has a built-in browser or PHP native solution. External libraries would add deployment complexity on cPanel with zero benefit.

---

## Common Pitfalls

### Pitfall 1: Wrong `require_once` Path in `api/users/` Subdirectory

**What goes wrong:** `api/users/list.php` uses `require_once '../config.php'` which resolves from the web server's working directory (the project root), not the file's directory — this silently includes the wrong file or throws a fatal error.

**Why it happens:** PHP relative paths in `require_once` are relative to the working directory, NOT to the script file's location.

**How to avoid:** Always use `__DIR__` for requires in subdirectories:
```php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/supabase.php';
```

**Warning signs:** `Fatal error: Call to undefined function supabase_request()` or constants like `SUPABASE_URL` not defined.

### Pitfall 2: Supabase Returns 201 with Empty Body (Missing Prefer Header)

**What goes wrong:** POST to `/users` returns HTTP 201 but `$result['data']` is `null` or `[]`. The insert succeeded but no row data comes back.

**Why it happens:** PostgREST's default INSERT behavior is `return=minimal` — it returns status code only, no body. Without `Prefer: return=representation`, the created row (with its auto-generated `id` and `created_at`) is not returned.

**How to avoid:** Call `supabase_request('POST', '/users', $body, true)` — the fourth argument `true` adds `Prefer: return=representation` header (this is already implemented in `api/supabase.php`).

**Warning signs:** `$result['http_code'] === 201` but `$result['data']` is null/empty.

### Pitfall 3: Duplicate Email or ID Number — 409 vs 500 Error

**What goes wrong:** Admin creates a user with an email that already exists. Supabase returns HTTP 409 (or sometimes 400 with a "duplicate key" message). PHP catches this as a generic 500 error and shows "שגיאה ביצירת המשתמש" instead of a meaningful message.

**Why it happens:** PostgreSQL UNIQUE constraint violations return a specific message in the Supabase response body: `{"code":"23505","message":"duplicate key value violates unique constraint..."}`.

**How to avoid:** Check both `$result['http_code']` and `$result['data']['message']` for "duplicate key":
```php
if (strpos($result['data']['message'] ?? '', 'duplicate key') !== false) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'message' => 'אימייל או מספר זהות כבר קיים במערכת']);
    exit;
}
```

**Warning signs:** User creation fails silently or shows generic error when email is reused.

### Pitfall 4: Hebrew JSON Escaping

**What goes wrong:** PHP logs or API responses show `\u05d0\u05d1\u05d2` instead of Hebrew characters. Hard to debug, unclear error messages to clients.

**Why it happens:** `json_encode()` by default escapes non-ASCII to Unicode escape sequences.

**How to avoid:** Add `JSON_UNESCAPED_UNICODE` flag: `json_encode($data, JSON_UNESCAPED_UNICODE)`.

**Warning signs:** Hebrew text in JSON looks like `\uXXXX` in browser network tab.

### Pitfall 5: Bootstrap LTR vs RTL CSS File

**What goes wrong:** The admin page loads `bootstrap.min.css` (LTR) instead of `bootstrap.rtl.min.css`. Tables render with columns in wrong order, form inputs align left, buttons appear on wrong side.

**Why it happens:** Copy-paste of standard Bootstrap CDN link without changing to the RTL variant.

**How to avoid:** The RTL CSS file has `.rtl` in its filename:
```
bootstrap.rtl.min.css   ← CORRECT for Hebrew
bootstrap.min.css       ← Wrong (LTR)
```

**Warning signs:** Page appears mirror-image wrong — labels are on the left side of inputs, table content flows left-to-right.

### Pitfall 6: Phone Field Accepting Non-Numeric Characters

**What goes wrong:** ADMIN-02 requires phone field accepts digits only. HTML `type="tel"` does NOT enforce digits-only on desktop browsers — it only affects the mobile keyboard.

**Why it happens:** `<input type="tel">` is a hint for mobile keyboards, not a validation constraint.

**How to avoid:** Add a JavaScript `input` event listener that strips non-digit characters:
```javascript
document.getElementById('phone').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '');
});
```
And validate server-side: `!preg_match('/^\d+$/', $phone)`.

**Warning signs:** Admin enters `052-123-456` and it passes to backend with dashes.

### Pitfall 7: `password_hash` Column Returned to Client

**What goes wrong:** `create.php` returns the full inserted row including `password_hash`. The bcrypt hash is exposed to the browser's network tab.

**Why it happens:** `supabase_request('POST', ..., true)` returns the full row; if `json_encode`d directly, `password_hash` leaks.

**How to avoid:** `unset($created['password_hash'])` before `echo json_encode(...)`.

**Warning signs:** Browser network tab shows `password_hash: "$2y$12$..."` in the API response.

---

## Code Examples

Verified patterns from official sources and codebase examination:

### Supabase List Users (GET with column selection and ordering)

```php
// Source: PostgREST official docs — https://docs.postgrest.org/en/v12/references/api/tables_views.html
// + api/supabase.php (Phase 3, production-verified)

$result = supabase_request(
    'GET',
    '/users?select=id,first_name,last_name,id_number,email,phone,gender,foreign_worker,status,created_at&order=created_at.desc'
);
// $result['data'] is array of user objects, or [] if no users
// $result['http_code'] === 200 on success
// Never include 'password_hash' in the select list — filter it at the API level
```

### Supabase Insert User (POST with representation)

```php
// Source: PostgREST official docs — https://docs.postgrest.org/en/v12/references/api/preferences.html
// Fourth arg 'true' → adds "Prefer: return=representation" to headers

$result = supabase_request('POST', '/users', $userArray, true);
// $result['http_code'] === 201 on success (Created)
// $result['data'] is array containing [0 => inserted_row]
// Without the 'true' flag: http_code still 201 but data is null (return=minimal)
```

### PHP Email Validation

```php
// Source: PHP Manual — https://www.php.net/manual/en/function.filter-var.php
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'כתובת אימייל לא תקינה'], JSON_UNESCAPED_UNICODE);
    exit;
}
```

### PHP Password Hash

```php
// Source: PHP Manual — https://www.php.net/manual/en/function.password-hash.php
// PASSWORD_DEFAULT = bcrypt (current default as of PHP 8.x, cost 12 in PHP 8.4)
// TEXT column in Supabase accommodates any hash length
$hash = password_hash($plaintext, PASSWORD_DEFAULT);
// Store $hash, never $plaintext
```

### Vanilla JS Client-Side Live Search

```javascript
// Source: Standard DOM API — no library required
// Works for RTL tables: textContent is language-agnostic
document.getElementById('search-box').addEventListener('input', function () {
    var query = this.value.trim().toLowerCase();
    document.querySelectorAll('#users-tbody tr').forEach(function (row) {
        row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
    });
});
```

### Fetch Pattern for Admin Page (consistent with existing index.html pattern)

```javascript
// Source: Existing index.html / dashboard.html pattern
// No async/await — consistent ES5 .then() chain pattern used throughout the project
fetch('api/users/list.php')
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (data.ok) {
            renderUsersTable(data.users);
        } else {
            showError(data.message);
        }
    })
    .catch(function () {
        showError('שגיאת תקשורת — נסה שוב');
    });
```

### Bootstrap 5.3 RTL HTML Head

```html
<!-- Source: Bootstrap 5.3 RTL docs — https://getbootstrap.com/docs/5.3/getting-started/rtl/ -->
<html lang="he" dir="rtl">
<head>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.rtl.min.css"
          integrity="sha384-CfCrinSRH2IR6a4e6fy2q6ioOX7O6Mtm1L9vRvFZ1trBncWmMePhzvafv7oIcWiW"
          crossorigin="anonymous">
</head>
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `Math.random()` for passwords | `crypto.getRandomValues()` | Web Crypto API (2014, all modern browsers) | Security: Math.random is NOT cryptographically secure |
| Bootstrap 4 `.ml-*`/`.mr-*` | Bootstrap 5 `.ms-*`/`.me-*` (logical properties) | Bootstrap 5 (2021) | RTL works correctly with logical props; directional props break in RTL |
| Separate RTL CSS framework | Bootstrap 5 `bootstrap.rtl.min.css` built-in | Bootstrap 5 (2021) | One CDN, one file, full RTL support |
| Manual salt in `password_hash()` | Omit salt (auto-generated) | PHP 7.0 deprecated, PHP 8.0 removed | Simpler, more secure — PHP generates cryptographic salt |

**Deprecated/outdated:**
- `password_hash($pw, PASSWORD_BCRYPT, ['salt' => '...'])`: The `salt` option was deprecated in PHP 7.0 and removed in PHP 8.0. Never pass a manual salt.
- Bootstrap `.ml-*`/`.mr-*`/`.pl-*`/`.pr-*`: Bootstrap 4 directional classes. Removed in Bootstrap 5. Use `.ms-*`/`.me-*`/`.ps-*`/`.pe-*`.
- `crypt()` for password hashing: Predecessor to `password_hash()`. Never use it — `password_hash()` is correct.

---

## Open Questions

1. **Does `api/users/` directory need a `.htaccess` to block direct browser access?**
   - What we know: PHP files in `api/` on cPanel return raw PHP output if accessed directly (without a host application calling them). Since these are JSON-only endpoints, accidental browser access shows JSON, which is fine.
   - What's unclear: Whether the cPanel configuration serves PHP files in subdirectories correctly (it should — cPanel's Apache parses PHP everywhere under `public_html`).
   - Recommendation: No `.htaccess` needed. If a PHP endpoint is accessed directly without the correct method, it will return a `405 Method Not Allowed` JSON response — this is correct behavior.

2. **Should `admin.php` be a `.php` file or `.html`?**
   - What we know: The existing pages are `.html` files (index.html, dashboard.html, sign.html). However, `admin.php` must be `.php` because future phases (5) may require server-side rendering of user data or session checks.
   - What's unclear: Nothing — the decision is clear.
   - Recommendation: Use `admin.php`. The PHP extension signals server-side intent, consistent with all `api/` files, and keeps Phase 5 options open without a rename.

3. **Bootstrap JS bundle — is it needed for Phase 4?**
   - What we know: Phase 4 has no modals or dropdowns (those come in Phase 5). Bootstrap JS is needed for collapse, modal, dropdown components.
   - What's unclear: Whether to load it now or defer to Phase 5.
   - Recommendation: Load Bootstrap JS bundle in Phase 4 anyway. It is ~60KB gzipped and adding it now means Phase 5 doesn't need to touch the HTML structure. It is harmless if unused.

4. **What columns to show in the user table?**
   - What we know: ADMIN-06 says "key columns." The users table has 13 columns including `password_hash` (never shown), `suspended_until` (Phase 5), and `updated_at` (operational detail).
   - Recommendation for Phase 4 table: `id`, `שם פרטי`, `שם משפחה`, `מספר זהות`, `אימייל`, `טלפון`, `סטטוס`. That's 7 columns — visible on a standard screen without horizontal scroll. Omit `foreign_worker`, `gender`, `created_at`, `suspended_until`, `updated_at` from the table (too many columns for a basic view; Phase 5 can add an expand/edit modal).

---

## Sources

### Primary (HIGH confidence)

- PHP Manual: `password_hash()` — https://www.php.net/manual/en/function.password-hash.php — confirmed `PASSWORD_DEFAULT` = bcrypt, auto-salt, cost 12 default in PHP 8.4, TEXT column recommended (255+ bytes)
- PHP Manual: `filter_var()` — https://www.php.net/manual/en/function.filter-var.php — confirmed `FILTER_VALIDATE_EMAIL` for RFC-compliant email validation
- PostgREST Preferences Docs — https://docs.postgrest.org/en/v12/references/api/preferences.html — confirmed `Prefer: return=representation` returns inserted row; default is `return=minimal` (empty body)
- PostgREST Tables/Views Docs — https://postgrest.org/en/stable/references/api/tables_views.html — confirmed `select=`, `order=`, `eq.`, `ilike.` filter syntax
- Bootstrap 5.3 RTL Docs — https://getbootstrap.com/docs/5.3/getting-started/rtl/ — confirmed `bootstrap.rtl.min.css` CDN URL, `lang` + `dir="rtl"` HTML requirements, RTLCSS auto-mirroring
- `api/supabase.php` (Phase 3, production-verified) — `supabase_request()` function signature and dual-header pattern confirmed working
- `api/login.php` (existing codebase) — PHP endpoint pattern: `php://input`, `json_encode`, Hebrew error messages, `http_response_code()`

### Secondary (MEDIUM confidence)

- W3C Web Crypto API via jsgenerator.com — `crypto.getRandomValues()` code example verified against W3C standard pattern; available in all modern browsers
- Bootstrap 5.3.8 CDN SRI hash — https://getbootstrap.com/docs/5.3/getting-started/introduction/ — CDN link and integrity hash confirmed for 5.3.8 (latest as of 2026-02)
- Supabase discussion #4054 — confirmed 201 with empty body is default INSERT behavior; `Prefer: return=representation` is the standard solution

### Tertiary (LOW confidence — verify before use)

- None. All critical claims are verified against official docs or the existing production codebase.

---

## Metadata

**Confidence breakdown:**

| Area | Level | Reason |
|------|-------|--------|
| PHP API endpoint pattern | HIGH | Directly extends existing api/login.php pattern; no new concepts |
| `supabase_request()` usage | HIGH | Production-verified in Phase 3; dual-header auth confirmed working |
| `password_hash()` / `filter_var()` | HIGH | Official PHP Manual; built-in functions unchanged for years |
| PostgREST INSERT/GET query syntax | HIGH | Official PostgREST docs; pattern already proven in Phase 3 |
| Bootstrap 5.3 RTL | HIGH | Official Bootstrap docs; CDN URL and SRI hash from getbootstrap.com |
| `crypto.getRandomValues()` | HIGH | W3C standard; available in all modern browsers since 2014 |
| Client-side live search | HIGH | Standard DOM API; well-established vanilla JS pattern |
| Bootstrap JS bundle necessity | MEDIUM | Phase 4 doesn't strictly need it; loaded for Phase 5 readiness |

**Research date:** 2026-02-28
**Valid until:** 2026-08-28 (PHP built-ins and PostgREST stable; Bootstrap 5 stable branch; re-verify Bootstrap CDN SRI hash if version bumped)
