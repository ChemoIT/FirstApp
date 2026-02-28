# Architecture Research — v2.0: Supabase User Management

**Domain:** PHP cPanel app + Supabase REST API integration
**Researched:** 2026-02-28
**Confidence:** HIGH for REST API patterns, MEDIUM for auth decision tradeoffs (depends on project requirements)

---

## Context: What Already Exists (v1.0)

The v1.0 system is a thin PHP API layer over a static HTML/JS frontend, deployed on cPanel shared hosting.
This milestone **adds Supabase as an external data store** for user management without changing the existing pattern.

```
EXISTING (v1.0)
─────────────────────────────────────────────────────────────────
Browser (HTML/CSS/JS)
        |
        |  HTTP POST (JSON body via fetch)
        v
   api/*.php                  ← secrets live here (sessions, tokens)
        |
        |-- PHP Sessions ──────> Server temp files
        |-- Micropay cURL ─────> External SMS gateway (HTTPS GET)
        |-- file_put_contents -> signatures/ (PNG files)

NEW (v2.0 additions, same pattern)
─────────────────────────────────────────────────────────────────
        |
        |-- Supabase cURL ─────> Supabase Cloud (HTTPS REST)
                                  ├── /rest/v1/users  (PostgREST, CRUD)
                                  └── /auth/v1/       (GoTrue, optional)
```

---

## System Overview: Full v2.0 Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                        BROWSER (Client)                           │
│  index.html    dashboard.html    admin.php    sign.html           │
│  (login UI)    (dispatch UI)     (user CRUD)  (signature)         │
└──────────┬───────────────────────────────────────────────────────┘
           │  JSON via fetch() — same pattern as v1.0
           v
┌──────────────────────────────────────────────────────────────────┐
│                   PHP LAYER (cPanel / Apache)                      │
│                                                                    │
│  api/                                                              │
│  ├── config.php           ← ALL secrets: Micropay token,          │
│  │                           Supabase URL, service_role key       │
│  ├── login.php            ← MODIFIED: now queries Supabase        │
│  ├── send-sms.php         ← UNCHANGED                             │
│  ├── save-signature.php   ← UNCHANGED                             │
│  └── supabase.php         ← NEW: shared cURL helper               │
│                                                                    │
│  admin.php                ← NEW: user CRUD page + API             │
│  api/users/                                                        │
│  ├── list.php             ← NEW: GET all users                    │
│  ├── create.php           ← NEW: POST new user                    │
│  ├── update.php           ← NEW: PATCH user by ID                 │
│  └── delete.php           ← NEW: DELETE user by ID               │
└──────────┬───────────────────────────────────────────────────────┘
           │  PHP cURL — HTTPS — service_role key in headers
           v
┌──────────────────────────────────────────────────────────────────┐
│                      SUPABASE CLOUD                                │
│                                                                    │
│  REST Endpoint: https://<ref>.supabase.co/rest/v1/               │
│  ├── /users               ← PostgREST on public.users table       │
│  │   (GET, POST, PATCH, DELETE via query params)                  │
│                                                                    │
│  PostgreSQL: public.users table                                    │
│  ├── id (uuid, PK, auto)                                          │
│  ├── email (text, unique)                                          │
│  ├── password_hash (text)   ← bcrypt, set by PHP                  │
│  ├── first_name (text)                                            │
│  ├── last_name (text)                                             │
│  ├── id_number (text)                                             │
│  ├── phone (text)                                                  │
│  ├── gender (text)          ← 'male' | 'female'                   │
│  ├── foreign_worker (bool)                                        │
│  ├── status (text)          ← 'active' | 'blocked' | 'suspended' │
│  ├── suspended_until (timestamptz, nullable)                      │
│  └── created_at (timestamptz, default now())                      │
└──────────────────────────────────────────────────────────────────┘
```

---

## Key Architectural Decision: Custom Table vs. Supabase GoTrue Auth

This is the most important design choice for this milestone.

### Option A: Custom `public.users` Table (RECOMMENDED)

**What it is:** Create a standard PostgreSQL table in the `public` schema. Store a bcrypt hash in a `password_hash` column. PHP handles all auth logic (hash, verify, session).

**How auth works:**
```
Login request:
  1. PHP fetches user row WHERE email = $email  (Supabase REST GET)
  2. PHP calls password_verify($input, $row['password_hash'])
  3. If valid: $_SESSION['logged_in'] = true, $_SESSION['user_id'] = $row['id']
  4. Session cookie returned to browser — identical to v1.0 behaviour

Admin CRUD:
  1. PHP calls Supabase REST (GET/POST/PATCH/DELETE) on /rest/v1/users
  2. For create/update with password: PHP calls password_hash() first, stores hash
  3. Full control — any fields, any status, any business logic
```

**Why recommended for this project:**
- Admin requires full CRUD including password reset — GoTrue makes this awkward
- No JWT tokens to manage in PHP sessions — existing PHP session pattern unchanged
- Complete control over user fields (id_number, foreign_worker, status, suspended_until)
- password_hash() / password_verify() are built into PHP — no new dependencies
- Matches the "learning project, simple stack" philosophy

**Trade-off:** You are responsible for password hashing security (use PASSWORD_BCRYPT, never MD5/SHA1).

---

### Option B: Supabase GoTrue Auth (NOT recommended for this project)

**What it is:** Use Supabase's built-in authentication service. Users live in `auth.users` (not directly accessible via PostgREST). PHP would call `/auth/v1/token` to sign in, receive a JWT, and store it.

**Why not for this project:**
- `auth.users` is NOT exposed via PostgREST — admin CRUD requires calling `/auth/v1/admin/users` endpoints separately from the data REST API, adding complexity
- Custom fields (id_number, foreign_worker, gender) must go in a separate `public.profiles` table, requiring a JOIN on every read
- Admin password reset requires calling GoTrue admin endpoint (POST `/auth/v1/admin/users/{id}`), not a simple PATCH
- Session management changes: must store JWT in PHP session AND handle JWT expiry/refresh
- Adds GoTrue-specific endpoint URLs, header formats, and response schemas alongside PostgREST — two APIs to learn and debug

**Verdict:** GoTrue auth adds complexity without benefit for a PHP-session-based, admin-CRUD-heavy app.

---

## Integration Point 1: Supabase REST API via PHP cURL

**Confidence:** HIGH — verified from Supabase official docs and PostgREST documentation.

### Required Headers (ALL requests)

```php
[
    'apikey: ' . SUPABASE_SERVICE_KEY,        // Supabase API key header
    'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,  // JWT auth header
    'Content-Type: application/json',
    'Accept: application/json',
]
```

Both `apikey` and `Authorization: Bearer` headers are required. Using service_role key bypasses Row Level Security (RLS) — safe for server-side PHP, never expose to browser.

### Base URL Pattern

```
https://<project-ref>.supabase.co/rest/v1/<table-name>
```

Example: `https://abcdefgh.supabase.co/rest/v1/users`

### CRUD Operations via HTTP

| Operation | Method | URL | Notes |
|-----------|--------|-----|-------|
| List all users | GET | `/rest/v1/users?select=*` | Add `&order=created_at.desc` |
| Get one user | GET | `/rest/v1/users?email=eq.sharon@example.com&select=*` | Filter by any column |
| Get by ID | GET | `/rest/v1/users?id=eq.{uuid}&select=*` | Returns array, take [0] |
| Create user | POST | `/rest/v1/users` | JSON body, `Prefer: return=representation` header |
| Update user | PATCH | `/rest/v1/users?id=eq.{uuid}` | JSON body with changed fields only |
| Delete user | DELETE | `/rest/v1/users?id=eq.{uuid}` | Permanent — no soft delete |

### PostgREST Filter Syntax (query parameters)

```
?column=eq.value       WHERE column = value
?column=neq.value      WHERE column != value
?column=ilike.*term*   WHERE column ILIKE '%term%'  (case-insensitive search)
?column=is.null        WHERE column IS NULL
?select=col1,col2      SELECT specific columns only
?order=col.asc         ORDER BY col ASC
?limit=50&offset=0     LIMIT 50 OFFSET 0 (pagination)
```

---

## Integration Point 2: Shared PHP cURL Helper (`api/supabase.php`)

All Supabase calls go through one reusable function — same pattern as existing cURL usage in `send-sms.php`.

```php
<?php
/**
 * api/supabase.php — Reusable Supabase REST API helper
 *
 * Provides supabase_request() for all HTTP methods against PostgREST.
 * Requires SUPABASE_URL and SUPABASE_SERVICE_KEY constants from config.php.
 * All calls use service_role key — server-side only, never in browser.
 */

/**
 * @param string $method  GET | POST | PATCH | DELETE
 * @param string $path    e.g. '/rest/v1/users?id=eq.abc&select=*'
 * @param array  $body    Associative array for POST/PATCH (will be JSON-encoded)
 * @param array  $extra_headers  Additional headers (e.g. Prefer: return=representation)
 * @return array ['data' => mixed, 'error' => string|null, 'status' => int]
 */
function supabase_request(string $method, string $path, array $body = [], array $extra_headers = []): array
{
    $url = SUPABASE_URL . $path;

    $headers = [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    foreach ($extra_headers as $h) {
        $headers[] = $h;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($method === 'POST' || $method === 'PATCH') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error !== '') {
        return ['data' => null, 'error' => 'cURL error: ' . $error, 'status' => 0];
    }

    $decoded = json_decode($response, true);

    // PostgREST returns error as JSON object with 'message' key on 4xx/5xx
    if ($status >= 400) {
        $msg = $decoded['message'] ?? $decoded['hint'] ?? $response;
        return ['data' => null, 'error' => $msg, 'status' => $status];
    }

    return ['data' => $decoded, 'error' => null, 'status' => $status];
}
```

---

## Integration Point 3: Modified Login Flow

**File:** `api/login.php` — MODIFIED (replaces hardcoded credential check)

**Data flow:**

```
Browser: POST /api/login.php  { "email": "...", "password": "..." }
    |
    v
PHP: 1. Query Supabase → GET /rest/v1/users?email=eq.{email}&select=*
     2. If no row returned → 401 "שם כניסה או סיסמא לא תקינים"
     3. If status = 'blocked' → 401 "המשתמש חסום"
     4. If status = 'suspended' AND suspended_until > now() → 401 "המשתמש מושהה עד {date}"
     5. password_verify($input_password, $row['password_hash'])
     6. If false → 401 "שם כניסה או סיסמא לא תקינים"
     7. If true → session_regenerate_id(true)
                  $_SESSION['logged_in'] = true
                  $_SESSION['user_id']   = $row['id']
                  $_SESSION['user_name'] = $row['first_name']
                  return { ok: true }
    |
    v
Browser: redirect to dashboard.html (identical to v1.0 behaviour)
```

**Key change:** `$body['username']` in v1.0 becomes `$body['email']` in v2.0. Session contents expand to include `user_id` for future use.

---

## Integration Point 4: Admin Page Architecture (`admin.php`)

**Admin page is a PHP file** (not .html) so it can directly include PHP logic without a separate API call for the page itself. The user table UI lives in `admin.php`, and CRUD actions call `api/users/*.php`.

**Why admin.php with no login guard?**
Per PROJECT.md decision: "Admin page without auth — Learning project — acceptable trade-off for simplicity." The admin page is not linked from the main UI. This is a known trade-off.

**File layout:**

```
admin.php                ← HTML page + inline PHP for server-rendered table
api/users/
├── list.php             ← GET  /api/users/list.php    → JSON array of users
├── create.php           ← POST /api/users/create.php  → creates user, returns user object
├── update.php           ← POST /api/users/update.php  → updates by id
└── delete.php           ← POST /api/users/delete.php  → deletes by id
```

**Admin data flow — Read (table view):**

```
admin.php loads in browser
  -> JS: fetch('/api/users/list.php')
       -> PHP: supabase_request('GET', '/rest/v1/users?select=*&order=created_at.desc')
       -> Supabase returns JSON array of user rows
       -> PHP returns JSON to browser
  <- JS renders table rows: name, email, phone, status, actions
```

**Admin data flow — Create user:**

```
Admin fills form, clicks "צור משתמש"
  -> JS: fetch POST /api/users/create.php { first_name, last_name, email, password, ... }
       -> PHP validates: email format, password >= 8 chars
       -> PHP: password_hash($password, PASSWORD_BCRYPT)  ← bcrypt, cost 12
       -> PHP: supabase_request('POST', '/rest/v1/users', $user_data,
                  ['Prefer: return=representation'])
       -> Supabase INSERT, returns created row
       -> PHP returns { ok: true, user: {...} }
  <- JS refreshes table
```

**Admin data flow — Update user:**

```
Admin edits row inline or in modal
  -> JS: fetch POST /api/users/update.php { id, ...changed_fields }
       -> PHP: if password in body → password_hash() it first
       -> PHP: supabase_request('PATCH', '/rest/v1/users?id=eq.' . $id, $fields)
       -> Supabase UPDATE, returns updated row
       -> PHP returns { ok: true }
  <- JS refreshes table row
```

**Admin data flow — Delete user:**

```
Admin clicks delete, confirm dialog
  -> JS: fetch POST /api/users/delete.php { id }
       -> PHP: supabase_request('DELETE', '/rest/v1/users?id=eq.' . $id)
       -> Supabase DELETE
       -> PHP returns { ok: true }
  <- JS removes table row
```

**Admin data flow — Block / Suspend:**

Block and suspend are update operations on the `status` and `suspended_until` fields:

```php
// Block:
supabase_request('PATCH', '/rest/v1/users?id=eq.' . $id,
    ['status' => 'blocked']);

// Suspend until date:
supabase_request('PATCH', '/rest/v1/users?id=eq.' . $id,
    ['status' => 'suspended', 'suspended_until' => '2026-03-15T00:00:00Z']);

// Unblock:
supabase_request('PATCH', '/rest/v1/users?id=eq.' . $id,
    ['status' => 'active', 'suspended_until' => null]);
```

---

## Component Boundaries — v2.0 Complete Map

| Component | Type | Responsibility | Communicates With |
|-----------|------|----------------|-------------------|
| `index.html` | Public HTML | Login form (email+password) | `api/login.php` via POST |
| `dashboard.html` | Protected HTML | SMS dispatch UI | `api/send-sms.php` |
| `sign.html` | Public HTML | Signature canvas | `api/save-signature.php` |
| `admin.php` | PHP page | User management UI | `api/users/*.php` via JS fetch |
| `api/config.php` | PHP include | All secrets: Micropay, Supabase URL, service_role key | Included by all api/*.php |
| `api/supabase.php` | PHP include | Reusable cURL wrapper for Supabase REST | Included by login.php, users/*.php |
| `api/login.php` | PHP endpoint | Auth: query Supabase, verify password, set session | Supabase (read user), session store |
| `api/send-sms.php` | PHP endpoint | SMS dispatch (unchanged) | Micropay API |
| `api/save-signature.php` | PHP endpoint | Save PNG, send confirmation SMS (unchanged) | File system, Micropay API |
| `api/users/list.php` | PHP endpoint | Return all users as JSON | Supabase REST GET |
| `api/users/create.php` | PHP endpoint | Hash password, create user row | Supabase REST POST |
| `api/users/update.php` | PHP endpoint | Update user fields, optionally rehash password | Supabase REST PATCH |
| `api/users/delete.php` | PHP endpoint | Delete user by ID | Supabase REST DELETE |
| `Supabase Cloud` | External service | PostgreSQL + PostgREST | PHP layer (inbound HTTPS) |

---

## Data Flow: Complete Sequences

### Login Flow (v2.0 — replaces hardcoded check)

```
Browser                     api/login.php              Supabase
   |                              |                         |
   |-- POST {email, password} --> |                         |
   |                              |-- GET /rest/v1/users  ->|
   |                              |   ?email=eq.{email}     |
   |                              |   &select=*             |
   |                              |<-- [{user row}]  -------|
   |                              |                         |
   |                              | password_verify()       |
   |                              | check status field      |
   |                              |                         |
   |<-- {ok:true} (session set) --|                         |
   |   OR {ok:false, message}     |                         |
```

### Admin CRUD Flow (generic)

```
Browser (admin.php)         api/users/*.php            Supabase
   |                              |                         |
   |-- fetch POST/GET ----------> |                         |
   |   {action, fields}           |                         |
   |                              | validate input          |
   |                              | [hash password if set]  |
   |                              |                         |
   |                              |-- cURL {method} ------> |
   |                              |   /rest/v1/users        |
   |                              |   [query params]        |
   |                              |   [JSON body]           |
   |                              |<-- {result / error} ----|
   |                              |                         |
   |<-- {ok, data/message} -------|                         |
```

---

## Updated File Structure (v2.0)

```
ch-ah.info/FirstApp/
│
├── index.html              ← MODIFIED: username → email label
├── dashboard.html          ← UNCHANGED
├── sign.html               ← UNCHANGED
├── admin.php               ← NEW: user management UI (HTML + inline PHP rendering)
│
├── api/
│   ├── config.php          ← MODIFIED: add SUPABASE_URL, SUPABASE_SERVICE_KEY constants
│   │                                   remove ADMIN_USER, ADMIN_PASS constants
│   ├── supabase.php        ← NEW: supabase_request() cURL helper
│   ├── login.php           ← MODIFIED: query Supabase instead of config constants
│   ├── send-sms.php        ← UNCHANGED
│   ├── save-signature.php  ← UNCHANGED
│   ├── check-session.php   ← UNCHANGED
│   └── users/
│       ├── list.php        ← NEW: GET /rest/v1/users?select=*
│       ├── create.php      ← NEW: password_hash() + POST /rest/v1/users
│       ├── update.php      ← NEW: optional password_hash() + PATCH /rest/v1/users?id=eq.{id}
│       └── delete.php      ← NEW: DELETE /rest/v1/users?id=eq.{id}
│
├── signatures/             ← UNCHANGED
├── css/style.css           ← MINOR ADDITIONS: admin table styles
└── .htaccess               ← UNCHANGED
```

---

## config.php — Modified Constants

```php
<?php
// Micropay SMS — UNCHANGED
define('MICROPAY_TOKEN', '...');
define('ADMIN_PHONE',    '0526804680');
define('BASE_URL',       'https://ch-ah.info/FirstApp');

// Supabase — NEW (values from Supabase dashboard → Settings → API)
define('SUPABASE_URL',         'https://<ref>.supabase.co');
define('SUPABASE_SERVICE_KEY', 'eyJ...');  // service_role key — server-side ONLY

// REMOVED in v2.0:
// define('ADMIN_USER', 'Sharonb');   ← replaced by Supabase users table
// define('ADMIN_PASS', '1532');      ← replaced by bcrypt hash in Supabase
```

---

## SQL: Supabase Table Definition

```sql
-- Run in Supabase SQL Editor (Dashboard → SQL Editor)
CREATE TABLE public.users (
    id               uuid        DEFAULT gen_random_uuid() PRIMARY KEY,
    email            text        NOT NULL UNIQUE,
    password_hash    text        NOT NULL,
    first_name       text        NOT NULL,
    last_name        text        NOT NULL,
    id_number        text,
    phone            text,
    gender           text        CHECK (gender IN ('male', 'female')),
    foreign_worker   boolean     DEFAULT false,
    status           text        NOT NULL DEFAULT 'active'
                                 CHECK (status IN ('active', 'blocked', 'suspended')),
    suspended_until  timestamptz,
    created_at       timestamptz DEFAULT now()
);

-- Disable RLS — we use service_role key from PHP, which bypasses RLS anyway.
-- Explicitly disabling makes intent clear. Enable if you add anon-key access later.
ALTER TABLE public.users DISABLE ROW LEVEL SECURITY;
```

**Note on RLS:** The service_role key bypasses RLS regardless of whether it's enabled. Disabling is fine because no unauthenticated browser code ever calls the Supabase URL directly — all calls go through PHP.

---

## Architectural Patterns

### Pattern 1: All Supabase Calls Via PHP — Never From Browser

**What:** PHP makes all Supabase REST calls using cURL. The browser never knows the Supabase URL or service_role key.

**Why:** The service_role key bypasses all security. If it were in JavaScript, any user could make admin-level API calls directly to Supabase.

```
CORRECT:  Browser → PHP (api/users/create.php) → Supabase
WRONG:    Browser → Supabase directly (exposes service_role key)
```

### Pattern 2: One cURL Helper, Many Callers

**What:** `api/supabase.php` provides `supabase_request()` used by all user endpoints. Mirrors how `config.php` is the single source of truth for secrets.

**Why:** DRY principle. Headers, timeout, error handling defined once. Easier to change Supabase URL or key later.

### Pattern 3: PHP Owns Password Lifecycle

**What:** `password_hash()` on create/update. `password_verify()` on login. Never store plaintext.

**Why:** Supabase stores whatever you send. PHP is responsible for hashing before the value leaves the server.

```php
// CREATE or UPDATE (with new password)
$hash = password_hash($plain_password, PASSWORD_BCRYPT);
// $hash goes to Supabase as password_hash field

// LOGIN
$row = supabase_request('GET', "/rest/v1/users?email=eq.{$email}&select=*")['data'][0];
$valid = password_verify($plain_password, $row['password_hash']);
```

### Pattern 4: Prefer: return=representation for Create

**What:** Add `Prefer: return=representation` header to POST requests so Supabase returns the created row (including auto-generated `id` and `created_at`).

**Why:** Otherwise POST returns HTTP 201 with empty body — you cannot confirm what was created.

```php
supabase_request('POST', '/rest/v1/users', $user_data, ['Prefer: return=representation']);
// Returns: [{id: "uuid...", email: "...", created_at: "..."}]
```

---

## Anti-Patterns to Avoid

### Anti-Pattern 1: Supabase Key in JavaScript

**What people do:** Embed SUPABASE_SERVICE_KEY in admin.php inline `<script>` to call Supabase from the browser.

**Why wrong:** The service_role key bypasses all database security. Any visitor to admin.php could copy it from page source and delete every user, or exfiltrate the entire database.

**Do this instead:** admin.php JS calls `api/users/list.php` (PHP), which holds the key server-side.

### Anti-Pattern 2: Storing Plaintext Passwords in Supabase

**What people do:** INSERT `password` field directly from the POST body without hashing.

**Why wrong:** A Supabase data breach or dashboard misconfiguration exposes every user's real password.

**Do this instead:** Always `password_hash($password, PASSWORD_BCRYPT)` before any Supabase write.

### Anti-Pattern 3: Using anon Key Server-Side for Writes

**What people do:** Use the `anon` key for all Supabase calls to "be safe."

**Why wrong:** The anon key respects RLS. If RLS is disabled, it still works, but it's semantically wrong. If RLS is later enabled with restrictive policies, write operations from PHP will break.

**Do this instead:** Use service_role key in PHP (server-side only). It bypasses RLS predictably and is designed for backend-to-backend calls.

### Anti-Pattern 4: Password Comparison with `===`

**What people do:** `$row['password_hash'] === $input_password`

**Why wrong:** This compares a bcrypt hash to a plaintext string — always false. Also vulnerable to timing attacks.

**Do this instead:** `password_verify($input_password, $row['password_hash'])` — handles timing safely.

### Anti-Pattern 5: No Input Validation Before Supabase Write

**What people do:** Take POST body fields and pass them directly to `supabase_request()`.

**Why wrong:** PostgREST will reject invalid types, but the error message is a Supabase error, not a user-friendly Hebrew message. Also, email format and password length should be enforced server-side.

**Do this instead:** Validate in PHP before calling Supabase. Return `{ok: false, message: "Hebrew error"}` early.

---

## Suggested Build Order (v2.0, with dependencies)

```
1. SUPABASE SETUP (prerequisite — do in Supabase dashboard)
   └── Create public.users table with SQL above
       (No PHP files needed yet. Test via Supabase dashboard Table Editor.)

2. api/config.php — ADD Supabase constants
   └── Add SUPABASE_URL and SUPABASE_SERVICE_KEY
       Remove ADMIN_USER and ADMIN_PASS
       (Requires: Supabase project URL and service_role key from dashboard)

3. api/supabase.php — NEW cURL helper
   └── supabase_request() function
       (Requires: config.php constants)
       (Test: call supabase_request('GET', '/rest/v1/users?select=*') — should return [])

4. api/users/list.php — NEW (simplest endpoint first)
   └── GET all users, return JSON array
       (Requires: supabase.php)
       (Test: curl or browser fetch, should return empty array initially)

5. api/users/create.php — NEW
   └── Validate input, hash password, POST to Supabase
       (Requires: supabase.php)
       (Test: create first user via admin UI, verify in Supabase dashboard)

6. admin.php — NEW (read + create working first)
   └── HTML table using list.php, form using create.php
       (Requires: list.php, create.php)
       (Test: can create user and see in table)

7. api/users/update.php + api/users/delete.php — NEW
   └── PATCH and DELETE endpoints
       (Requires: supabase.php)
       (Test: edit, block, suspend, delete from admin UI)

8. api/login.php — MODIFY (highest risk — replaces working auth)
   └── Replace hardcoded check with Supabase query + password_verify()
       (Requires: supabase.php, at least one user with known password in Supabase)
       (Test: login with Supabase user → session set → dashboard loads)
       (Rollback plan: config.php can temporarily re-add ADMIN_USER/ADMIN_PASS)

9. index.html — MINOR MODIFY
   └── Change "שם משתמש" label to "אימייל", update fetch body field name
       (Requires: login.php working)
       (Test: full login → dashboard flow)

10. END-TO-END TEST
    └── Create user in admin → login with that user → send SMS → sign → confirm
```

**Why this order:**
- Steps 1-4 are completely safe — no existing functionality touched
- Steps 5-6 build admin UI on top of working API
- Step 8 (login.php change) is highest risk — done last, with a working user ready to test against
- Rollback at step 8 is easy: temporarily re-add config constants and revert login.php

---

## Scalability Considerations

This is still a small-team internal tool. These are good-to-know limits, not immediate concerns.

| Concern | Current Scale | If It Grows |
|---------|---------------|-------------|
| Supabase free tier | 500MB storage, unlimited API calls | Upgrade to Pro ($25/mo) if needed |
| PHP sessions | File-based, fine for <100 concurrent | No change needed |
| Password hashing | bcrypt cost 12, ~100ms per hash | Fine for low-frequency logins |
| User table size | <1000 users likely | PostgREST handles 100K+ rows easily |
| Admin page | No auth — single admin user | Add .htaccess basic auth if needed later |

---

## Integration Points with Existing Files

| Existing File | Change Type | What Changes |
|---------------|-------------|--------------|
| `api/config.php` | Modified | Add 2 Supabase constants, remove 2 auth constants |
| `api/login.php` | Modified | Replace 5-line credential check with Supabase query + password_verify() |
| `index.html` | Minor modify | "שם משתמש" → "אימייל", field name username → email |
| `dashboard.html` | Unchanged | No changes needed |
| `api/send-sms.php` | Unchanged | No changes needed |
| `api/save-signature.php` | Unchanged | No changes needed |
| `api/check-session.php` | Unchanged | No changes needed |

**New files:** `api/supabase.php`, `api/users/list.php`, `api/users/create.php`, `api/users/update.php`, `api/users/delete.php`, `admin.php`

---

## Sources

- Supabase REST API docs: https://supabase.com/docs/guides/api (HIGH — official)
- Supabase API key types: https://supabase.com/docs/guides/api/api-keys (HIGH — official)
- Supabase auth architecture (GoTrue): https://supabase.com/docs/guides/auth/architecture (HIGH — official)
- Supabase self-hosting auth admin endpoints: https://supabase.com/docs/reference/self-hosting-auth/introduction (HIGH — official, reveals `/admin/users` endpoint pattern)
- PostgREST filter operators: https://supabase.com/docs/guides/api (HIGH — official)
- PHPSupabase library (pattern reference): https://github.com/rafaelwendel/phpsupabase (MEDIUM — community library, used for pattern validation only)
- PHP password_hash / password_verify: https://www.php.net/manual/en/function.password-hash.php (HIGH — official PHP docs)
- PHP bcrypt cost factor (8.4 changed default to 12): https://php.watch/versions/8.4/password_hash-bcrypt-cost-increase (MEDIUM — PHP Watch)
- "Both apikey and Authorization headers required" confirmed: https://github.com/supabase-community/postgrest-go/issues/29 (MEDIUM — community issue thread, matches official docs)

---

*Architecture research for: FirstApp v2.0 — Supabase user management integration*
*Researched: 2026-02-28*
