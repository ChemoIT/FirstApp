# Phase 6: Login Replacement — Research

**Researched:** 2026-02-28
**Domain:** PHP session auth, bcrypt password verification, PostgREST GET filter, status enforcement logic
**Confidence:** HIGH

---

## Summary

Phase 6 replaces the hardcoded `sharonb/1532` credentials in `api/login.php` with a real Supabase lookup against `public.users`. The login form on `index.html` changes its `username` field to `email`. On submit, PHP fetches the user row by email via `supabase_request()`, calls `password_verify()` against the stored bcrypt hash, and checks the `status` and `suspended_until` columns before allowing the session to be set.

The entire implementation is a modification of two existing files: `api/login.php` and `index.html`. No new files are required. The full stack — `supabase_request()`, `password_hash()` / `password_verify()`, `session_start()` / `session_regenerate_id()`, PHP sessions — is already in production and verified. Phase 6 wires them together in one PHP endpoint. There is no new dependency to install, no new pattern to introduce.

The only non-trivial logic is the status enforcement: `active` users log in, `blocked` users are always refused, `suspended` users are refused if `suspended_until` is in the future and allowed if the date has passed (the suspension expired). All three cases return a generic Hebrew error message to avoid leaking which users exist or what their status is.

**Primary recommendation:** Modify `api/login.php` in a single task — lookup by email, `password_verify()`, status check, set session. Modify `index.html` in the same task (it is a small HTML change: rename the `username` field to `email`). One plan, one plan file (`06-01`).

---

## Standard Stack

### Core

| Technology | Version | Purpose | Why Standard |
|------------|---------|---------|--------------|
| `supabase_request()` from `api/supabase.php` | Phase 3 (existing, production) | Fetch user row by email from `public.users` | Dual-header auth proven across Phases 3, 4, 5; identical GET pattern |
| `password_verify($password, $hash)` | PHP 5.5+ built-in | Compare submitted password against bcrypt hash stored in Supabase | Phase 3 decision: PHP owns full password lifecycle; timing-safe by spec |
| `session_start()` / `session_regenerate_id(true)` | PHP built-in | Start session, prevent session fixation on login | Already in `api/login.php` — unchanged; `true` deletes old session file |
| `$_SESSION['logged_in']` / `$_SESSION['user']` | PHP built-in | Persist login state across page refreshes | Already used in `api/check-session.php` and `api/login.php` |
| PostgREST `?email=eq.X&select=...` | PostgREST 12.x | Filter `public.users` by email, include `password_hash` and `status` | Same filter syntax proven in Phase 5; GET returns `200 + []` if no match |

### Supporting

| Technology | Version | Purpose | When to Use |
|------------|---------|---------|-------------|
| `PHP DateTime` comparison | PHP built-in | Compare `suspended_until` (ISO 8601 string from Supabase) against today | Already used in `api/users/update.php` for suspend-date validation |
| `JSON_UNESCAPED_UNICODE` flag | PHP built-in | Keep Hebrew error messages readable in responses | Already used in all Phase 4/5 endpoints |
| `filter_var($email, FILTER_VALIDATE_EMAIL)` | PHP built-in | Optional server-side email format guard before hitting Supabase | Lightweight, consistent with create.php pattern |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Custom `public.users` table (locked decision) | Supabase GoTrue (Auth) | GoTrue adds JWT complexity; project decision locks this out |
| PHP session (locked decision) | JWT tokens | Session is simpler, server-side, already working |
| `password_verify()` (locked decision) | Third-party auth library | No library needed; PHP native bcrypt is sufficient |
| Single generic Hebrew error for all failures (recommended) | Distinct messages per failure reason | Distinct messages leak whether the email exists, whether the password is wrong, or what status the user has — security anti-pattern for public-facing login |

**No installation required.** Zero new dependencies. Zero new files beyond modifying two existing ones.

---

## Architecture Patterns

### Files Modified (Phase 6 scope)

```
FirstApp/
├── index.html                  ← MODIFIED: rename username→email field, update JS field reference
└── api/
    ├── login.php               ← MODIFIED: replace hardcoded check with Supabase lookup + status check
    └── config.php              ← MODIFIED: remove ADMIN_USER and ADMIN_PASS constants
```

No new files. No new directories.

### Pattern 1: Login Flow — Lookup, Verify, Status Check, Session

**What:** The new `api/login.php` performs these steps in order:

1. Read `email` and `password` from JSON body (replaces `username`)
2. Basic email format check (reject obviously malformed before hitting Supabase)
3. Fetch user row from Supabase: `GET /users?email=eq.<email>&select=id,email,status,suspended_until,password_hash`
4. If result is empty (`[]`): return 401 with generic Hebrew error (user not found)
5. `password_verify($password, $row['password_hash'])`: if false, return 401
6. Check `$row['status']`:
   - `'blocked'`: return 401 with generic Hebrew error
   - `'suspended'`: compare `suspended_until` date against today
     - Future date → still suspended → return 401 with generic Hebrew error
     - Past date → suspension expired → fall through to login success
   - `'active'`: fall through to login success
7. `session_regenerate_id(true)` then set `$_SESSION['logged_in'] = true` and `$_SESSION['user'] = $row['email']`
8. Return `{'ok': true}`

**Example — new api/login.php:**

```php
<?php
/**
 * api/login.php — Supabase-backed session login
 *
 * Accepts POST with JSON body: {"email": "...", "password": "..."}
 * Looks up user by email in public.users, verifies bcrypt password,
 * checks status (active/blocked/suspended), sets PHP session on success.
 *
 * On success:  {"ok": true}
 * On failure:  HTTP 401 + {"ok": false, "message": "פרטי הכניסה שגויים"}
 */

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';

header('Content-Type: application/json; charset=utf-8');

$body     = json_decode(file_get_contents('php://input'), true);
$email    = trim($body['email']    ?? '');
$password = $body['password']      ?? '';

// Generic error — never reveal whether email exists, password was wrong, or user is blocked
$genericError = json_encode(['ok' => false, 'message' => 'פרטי הכניסה שגויים'], JSON_UNESCAPED_UNICODE);

// Guard: reject empty input immediately (no Supabase call needed)
if ($email === '' || $password === '') {
    http_response_code(401);
    echo $genericError;
    exit;
}

// Optional: basic email format guard (avoids PostgREST call on obviously malformed input)
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(401);
    echo $genericError;
    exit;
}

// Fetch user by email — include password_hash for verify, status + suspended_until for status check
// SECURITY: password_hash is fetched here (login only) — excluded from list.php at query level
$result = supabase_request(
    'GET',
    '/users?email=eq.' . rawurlencode($email)
    . '&select=id,email,status,suspended_until,password_hash'
);

// Supabase transport error
if ($result['error'] !== null || $result['http_code'] !== 200) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'שגיאת שרת — נסה שוב'], JSON_UNESCAPED_UNICODE);
    exit;
}

// No user found — empty array is 200 OK in PostgREST (not 404)
if (empty($result['data'])) {
    http_response_code(401);
    echo $genericError;
    exit;
}

$user = $result['data'][0];

// Bcrypt verification — timing-safe by spec
if (!password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo $genericError;
    exit;
}

// Status enforcement
$status = $user['status'] ?? 'active';

if ($status === 'blocked') {
    http_response_code(401);
    echo $genericError;
    exit;
}

if ($status === 'suspended') {
    $suspendedUntil = $user['suspended_until'] ?? null;

    // If suspended_until is set and in the future: refuse login
    // If null or past: suspension expired — allow login (fall through)
    if ($suspendedUntil !== null) {
        // Supabase returns TIMESTAMPTZ as ISO 8601 string e.g. "2026-03-15T00:00:00+00:00"
        $suspendDate = new DateTime($suspendedUntil);
        $today       = new DateTime('today', new DateTimeZone('UTC'));

        if ($suspendDate > $today) {
            http_response_code(401);
            echo $genericError;
            exit;
        }
        // else: suspension date passed — fall through to login success
    }
    // else: suspended_until is null — treat as expired (defensive; shouldn't happen via UI)
}

// Login success — regenerate session ID before writing session data (prevents session fixation)
session_regenerate_id(true);

$_SESSION['logged_in'] = true;
$_SESSION['user']      = $user['email'];

echo json_encode(['ok' => true]);
```

### Pattern 2: index.html Login Form Change

**What:** Replace the `username` field with an `email` field. Change label text from "שם משתמש" to "אימייל". Change `type="text"` to `type="email"`. Change `id="username"` to `id="email"`. Update the JS reference from `document.getElementById('username').value` to `document.getElementById('email').value`. Update the `fetch` body to send `{ email, password }` instead of `{ username, password }`.

**That is the complete change to index.html.** No structural change. Same form layout. Same fetch pattern. Same error display.

### Pattern 3: Remove Hardcoded Credentials from config.php (AUTH-05)

**What:** Delete the two constants from `api/config.php`:

```php
// REMOVE THESE TWO LINES:
define('ADMIN_USER', 'Sharonb');
define('ADMIN_PASS', '1532');
```

After removal, no PHP file references `ADMIN_USER` or `ADMIN_PASS` — the old `api/login.php` was the only consumer. Verify with a grep before committing.

### Pattern 4: Supabase Query — Selecting password_hash at Login Only

**What:** The list endpoint (`api/users/list.php`) excludes `password_hash` at the query level. The login endpoint must include it. This is intentional — login is the only place that legitimately needs the hash.

```
GET /users?email=eq.<email>&select=id,email,status,suspended_until,password_hash
```

`rawurlencode($email)` is applied to the email before URL concatenation — prevents the `@` symbol from being misinterpreted (though PostgREST handles it, encoding is best practice for special characters in URL query values).

### Pattern 5: Suspended User — Date Comparison Logic

**What:** Supabase returns `suspended_until` as an ISO 8601 TIMESTAMPTZ string, e.g. `"2026-03-15T00:00:00+00:00"`. PHP's `new DateTime($string)` parses this correctly. The comparison is against `new DateTime('today', new DateTimeZone('UTC'))` — midnight UTC today — consistent with how the suspend date is stored (sent as bare `YYYY-MM-DD` which PostgreSQL interprets as midnight UTC).

**Logic table:**

| `status` | `suspended_until` | Result |
|----------|-------------------|--------|
| `active` | any | Login allowed |
| `blocked` | any | Refused (401) |
| `suspended` | future datetime | Refused (401) |
| `suspended` | past datetime | Allowed (suspension expired) |
| `suspended` | `null` | Allowed (defensive: treat null as expired) |

### Anti-Patterns to Avoid

- **Distinct error messages per failure mode:** "אימייל לא קיים" vs "סיסמא שגויה" vs "חשבון חסום" leaks user enumeration and status. Always use one generic message for all auth failures.
- **Comparing hashes with `===` or `==`:** Not timing-safe. Always use `password_verify()`.
- **Setting `$_SESSION` before `session_regenerate_id(true)`:** Opens session fixation window. Regenerate first, then write.
- **Fetching `password_hash` in list.php:** The list endpoint deliberately excludes it at the SQL query level. Do not change this.
- **Not calling `rawurlencode()` on the email in the URL:** The `@` character in emails is valid in query strings but encoding is best practice when building URLs by concatenation.
- **Leaving `ADMIN_USER` / `ADMIN_PASS` constants in config.php after Phase 6:** They would be dead code and a misleading security relic. Remove both.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Password comparison | `$hash === hash('sha256', $password)` | `password_verify($password, $hash)` | `password_verify` is timing-safe; SHA-256 comparison is not; bcrypt comparison is non-trivial |
| Session ID regeneration | Manual cookie manipulation | `session_regenerate_id(true)` | PHP handles session file creation/deletion; `true` deletes old session to prevent fixation |
| Supabase HTTP call | New cURL function | `supabase_request()` from `api/supabase.php` | Dual-header auth already production-verified |
| Date parsing for suspended_until | Regex on TIMESTAMPTZ string | `new DateTime($timestamptzString)` | PHP's `DateTime` constructor correctly parses ISO 8601 with timezone offsets |

**Key insight:** Every piece of this phase is already built. Phase 6 is assembly, not construction. The only new logic is the three-way `if ($status === ...)` block.

---

## Common Pitfalls

### Pitfall 1: PostgREST Returns 200 with Empty Array for No-Match, Not 404

**What goes wrong:** Developer writes `if ($result['http_code'] === 404)` to detect "user not found" — this never triggers. A non-existent email returns `HTTP 200` with `data = []`.

**Why it happens:** PostgREST treats "no rows matched your filter" as a successful query that returned an empty result set, not as a 404.

**How to avoid:** Check `empty($result['data'])` after confirming `http_code === 200`.

**Warning signs:** Login with an unknown email returns no error and falls through to `password_verify()` with `null`, which returns `false` — so it accidentally works correctly but for the wrong reason. Then if null is passed to `password_verify`, it may trigger a TypeError in PHP 8.0+.

### Pitfall 2: password_verify() with null Hash (TypeError in PHP 8.0+)

**What goes wrong:** If the email lookup succeeds but `password_hash` is null in the row (it shouldn't be — column is NOT NULL — but defensive), `password_verify($password, null)` throws a `TypeError` in PHP 8.0+ (expects string, gets null).

**Why it happens:** PHP 8.0 enforced strict types on built-in functions. PHP 7.4 would silently coerce null to empty string.

**How to avoid:** Check `empty($result['data'])` before accessing `$result['data'][0]['password_hash']`. The NOT NULL constraint on the column ensures this can only happen due to a data integrity bug, but the guard is cheap.

**Warning signs:** `Fatal error: Uncaught TypeError: password_verify(): Argument #2 ($hash) must be of type string, null given`

### Pitfall 3: Suspended_Until Timezone Mismatch

**What goes wrong:** `suspended_until` stored as `"2026-03-15T00:00:00+00:00"` (UTC midnight) is compared against PHP server's local time. If the server is in UTC+2, `new DateTime('today')` without explicit timezone is midnight local time, which is 2 hours earlier than the UTC-stored date — causing a suspension to be 2 hours longer than expected.

**Why it happens:** Supabase TIMESTAMPTZ is always stored in UTC. PHP's `new DateTime('today')` uses the server's configured timezone from `php.ini` (date.timezone).

**How to avoid:** Always pass `new DateTimeZone('UTC')` to `new DateTime('today')` when comparing against Supabase TIMESTAMPTZ values. The example in Pattern 5 includes this.

**Warning signs:** A user whose suspension ends "today" can log in a few hours earlier or later than expected depending on server timezone.

### Pitfall 4: Session Set Before session_regenerate_id (Session Fixation Window)

**What goes wrong:** Developer moves `session_regenerate_id(true)` to after `$_SESSION['logged_in'] = true`. A session fixation attacker who plants a session ID sees the session become authenticated.

**Why it happens:** Order confusion — "why regenerate before setting data?"

**How to avoid:** Always: `session_regenerate_id(true)` first, then `$_SESSION[...] = ...`. This is the existing pattern in Phase 1's `api/login.php` — preserve it in the new version.

**Warning signs:** No error — this is a security defect, not a crash.

### Pitfall 5: Hardcoded Credentials Left in config.php

**What goes wrong:** `ADMIN_USER` and `ADMIN_PASS` constants remain in `api/config.php` after the new `login.php` is deployed. The old login path is disabled but the constants linger as dead code. Any future developer might re-introduce them by accident.

**Why it happens:** Incomplete cleanup — new endpoint written but old constants not removed.

**How to avoid:** The plan must include an explicit task to delete the two `define()` lines from `config.php` and verify with grep that no other file references `ADMIN_USER` or `ADMIN_PASS`.

**Warning signs:** `grep -r 'ADMIN_USER' api/` still returns results after Phase 6.

### Pitfall 6: rawurlencode Missing from Email in URL

**What goes wrong:** Email `user+tag@example.com` (containing `+`) is not encoded. PostgREST receives `?email=eq.user+tag@example.com` where `+` is decoded as a space, causing the lookup to fail.

**Why it happens:** `+` is a valid email character and a valid URL query character that means "space" in `application/x-www-form-urlencoded` context. PostgREST may or may not interpret it correctly.

**How to avoid:** Apply `rawurlencode($email)` when building the PostgREST query string.

**Warning signs:** Login fails for emails containing `+`, `.`, or other special characters even though the email exists in the database.

---

## Code Examples

Verified patterns from official sources and existing codebase:

### PostgREST Lookup by Email (GET, returns [] if not found)

```php
// Source: api/supabase.php (Phase 3) + PostgREST docs eq. filter syntax
// Empty result ($result['data'] === []) = 200 OK — NOT 404
$result = supabase_request(
    'GET',
    '/users?email=eq.' . rawurlencode($email)
    . '&select=id,email,status,suspended_until,password_hash'
);

// http_code === 200 AND data === [] means user not found
// http_code === 200 AND data !== [] means user found
```

### password_verify — Timing-Safe Bcrypt Check

```php
// Source: https://www.php.net/manual/en/function.password-verify.php
// Returns bool — false for wrong password AND for invalid hash (no distinction)
// timing-safe: does not short-circuit on first non-matching byte
if (!password_verify($plaintextPassword, $storedHash)) {
    // Wrong password — return generic error, never "wrong password"
    http_response_code(401);
    echo $genericError;
    exit;
}
```

### Suspended_Until Comparison (UTC-aware)

```php
// Source: PHP DateTime docs — https://www.php.net/manual/en/class.datetime.php
// Supabase returns TIMESTAMPTZ as ISO 8601 string: "2026-03-15T00:00:00+00:00"
// DateTime constructor parses timezone offset from the string correctly
$suspendDate = new DateTime($user['suspended_until']);
$today       = new DateTime('today', new DateTimeZone('UTC'));  // midnight UTC today

if ($suspendDate > $today) {
    // Still suspended
    http_response_code(401);
    echo $genericError;
    exit;
}
// else: suspension has expired — fall through to login success
```

### Session Regeneration (existing pattern — unchanged)

```php
// Source: PHP Manual + existing api/login.php (Phase 1)
// ALWAYS call session_regenerate_id(true) BEFORE setting session variables
// true = delete old session file (prevents session fixation via orphaned file)
session_regenerate_id(true);

$_SESSION['logged_in'] = true;
$_SESSION['user']      = $user['email'];  // Store email (not username) as user identifier
```

### index.html — Login Form Field Change

```html
<!-- BEFORE (Phase 1 — username field) -->
<label for="username">שם משתמש</label>
<input type="text" id="username" name="username"
       placeholder="שם משתמש" autocomplete="username" required>

<!-- AFTER (Phase 6 — email field) -->
<label for="email">אימייל</label>
<input type="email" id="email" name="email"
       placeholder="אימייל" autocomplete="email" required>
```

```javascript
// BEFORE (Phase 1):
var username = document.getElementById('username').value;
body: JSON.stringify({ username: username, password: password })

// AFTER (Phase 6):
var email = document.getElementById('email').value;
body: JSON.stringify({ email: email, password: password })
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hardcoded credentials in config.php | Supabase table lookup + bcrypt | Phase 6 | Security: brute-force possible on hardcoded 4-char password |
| `$username === ADMIN_USER && $password === ADMIN_PASS` | `password_verify($pw, $hash)` | PHP 5.5 introduced password_hash (2013) | Timing-safe bcrypt vs plain string comparison |
| Generic "admin" account | Per-user email-based login | Phase 6 | Each login maps to a Supabase user row with status |

**Deprecated/outdated in this codebase after Phase 6:**
- `ADMIN_USER` constant: Remove from config.php — no longer consumed
- `ADMIN_PASS` constant: Remove from config.php — no longer consumed
- `$body['username']` in login.php: Replace with `$body['email']`
- `$_SESSION['user'] = $username`: Replace with `$_SESSION['user'] = $user['email']`

---

## Open Questions

1. **Does the test user from Phase 4 have a known plaintext password available for testing?**
   - What we know: Phase 4 created users via the admin UI with bcrypt-hashed passwords. The plaintext was entered in the UI at creation time.
   - What's unclear: Whether Sharon remembers the plaintext password used when creating the test user, or whether a new test user must be created before Phase 6 testing.
   - Recommendation: Plan 06-01 should include a pre-flight step: "Confirm you have the email and plaintext password of at least one active user in Supabase." If not, create one via admin.php before starting the login change. The phase description says "Depends on Phase 4 (a real user with a known bcrypt password exists)."

2. **What Hebrew error message should be shown for all login failures?**
   - What we know: Phase 1 used `"שם כניסה או סיסמא לא תקינים"` (username or password incorrect). Phase 6 switches to email. A slightly updated message makes sense.
   - What's unclear: Sharon's preferred wording.
   - Recommendation: Default to `"פרטי הכניסה שגויים"` (credentials incorrect). This is generic, applies to all failure modes, and does not mention username vs email. Planner can note this is a low-stakes decision Sharon can adjust during execution.

3. **Should a server-side error (Supabase unreachable) show a different Hebrew message vs. wrong credentials?**
   - What we know: If `supabase_request()` returns `error !== null`, it is a transport/timeout problem — not a credential problem. Returning 401 with "פרטי הכניסה שגויים" would be misleading.
   - What's unclear: Whether Sharon wants a separate server error message.
   - Recommendation: Yes — return HTTP 500 with `"שגיאת שרת — נסה שוב"` for Supabase transport errors, and HTTP 401 with the generic credential error for all auth failures. The code example in Pattern 1 implements this split.

---

## Sources

### Primary (HIGH confidence)

- PHP Manual: `password_verify()` — https://www.php.net/manual/en/function.password-verify.php — confirmed timing-safe, returns bool, false for wrong password AND invalid hash, compatible with `password_hash()` output
- PHP Manual: `session_regenerate_id()` — https://www.php.net/manual/en/function.session-regenerate-id.php — confirmed `true` parameter deletes old session file; must be called before setting session variables
- PostgREST v12 Docs — https://docs.postgrest.org/en/v12/references/api/tables_views.html — confirmed `?email=eq.X` filter syntax; GET returns HTTP 200 with `[]` when no rows match (not 404)
- PostgREST Resource Representation — https://docs.postgrest.org/en/v12/references/api/resource_representation.html — confirmed 200 + empty array for zero-row GET results
- `api/supabase.php` (Phase 3, production) — `supabase_request()` pattern with dual-header auth; same GET filter syntax used in Phase 5 `update.php`
- `api/login.php` (Phase 1, production) — session pattern, `session_regenerate_id(true)`, JSON body read from `php://input`, Hebrew error messages
- `api/users/update.php` (Phase 5, production) — `DateTime::createFromFormat()` and date comparison pattern for `suspended_until`

### Secondary (MEDIUM confidence)

- OWASP Session Management Cheat Sheet — https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html — confirmed session_regenerate_id at privilege elevation (login) is the correct mitigation for session fixation
- PostgREST GitHub issue #1655 — https://github.com/PostgREST/postgrest/issues/1655 — confirmed 200 + empty array is the standard GET behavior for zero-row results; 404 only on singular-format requests

### Tertiary (LOW confidence)

- None. All claims verified against official PHP docs or existing production codebase.

---

## Metadata

**Confidence breakdown:**

| Area | Level | Reason |
|------|-------|--------|
| `password_verify()` behavior | HIGH | Official PHP manual; confirmed timing-safe, bool return |
| PostgREST GET empty result = 200 + `[]` | HIGH | Official PostgREST docs + GitHub issue confirmation |
| Session fixation: `session_regenerate_id(true)` before session write | HIGH | PHP manual + OWASP + existing production code pattern |
| `suspended_until` DateTime comparison | HIGH | PHP DateTime docs; DateTime parses ISO 8601 with timezone offset correctly |
| Status enforcement logic (active/blocked/suspended) | HIGH | Directly derived from `public.users` schema (Phase 3) and update.php (Phase 5); no new behavior |
| Generic error message content | MEDIUM | Planner/Sharon discretion — default suggested, easily changed |
| rawurlencode on email in URL | MEDIUM | Best practice; not verified against PostgREST's exact URL parsing of `@` and `+` characters |

**Research date:** 2026-02-28
**Valid until:** 2026-08-28 (PHP built-ins and PostgREST stable; re-verify if Supabase changes TIMESTAMPTZ serialization format)
