# Phase 3: Database Foundation — Research

**Researched:** 2026-02-28
**Domain:** Supabase PostgreSQL REST API, PHP cURL integration, credential management for cPanel shared hosting
**Confidence:** HIGH

---

## Summary

Phase 3 establishes the database layer that every subsequent phase depends on. Three technical domains are involved: creating a PostgreSQL table in Supabase via the SQL Editor, writing a reusable PHP cURL helper that calls the Supabase REST API, and keeping credentials out of git.

The project's stack decision is PHP cURL on cPanel shared hosting calling the Supabase REST API — the same pattern already proven in Phase 1 for Micropay SMS. The Supabase REST API is a PostgREST auto-generated interface exposed at `https://<project_ref>.supabase.co/rest/v1/`. Every request requires two headers: `apikey: <key>` and `Authorization: Bearer <key>`. When using the `service_role` key, the `Authorization` header is what bypasses Row Level Security — if only `apikey` is set without a matching `Authorization`, RLS is NOT bypassed and requests may be blocked. This is the most critical pitfall for this phase.

The `password_hash()` / `password_verify()` functions are available from PHP 5.5 onward and are fully compatible with PHP 7.4 and PHP 8.x. The `password_hash` column must be `TEXT` (not `VARCHAR(60)`) because `PASSWORD_DEFAULT` may produce longer hashes in future PHP versions. The existing `config.php` pattern used in Phase 1 (a file defining constants, added to `.gitignore`) is the correct mechanism for storing Supabase credentials — no new pattern needed.

**Primary recommendation:** Build in order: (1) create the table in Supabase SQL Editor, (2) add credentials to `config.php` and verify `.gitignore` entry, (3) write `api/supabase.php` with the dual-header cURL helper function, (4) verify with a live GET request that returns `[]`. No npm, no Composer, no Supabase SDK — raw cURL against the REST API is sufficient and consistent with the Micropay pattern.

---

## Standard Stack

### Core

| Technology | Version | Purpose | Why Standard |
|------------|---------|---------|--------------|
| Supabase PostgreSQL | Current cloud (Postgres 15.x) | Hosted relational database | Project decision — managed hosting, no local Postgres setup |
| Supabase REST API (PostgREST) | Auto-generated | HTTP interface to Postgres | No SDK needed; same cURL pattern as Micropay |
| PHP cURL | Built-in (PHP 7.4+) | HTTP requests to Supabase REST API | Already used for Micropay; `allow_url_fopen` workaround |
| PHP `password_hash()` / `password_verify()` | Built-in (PHP 5.5+) | Bcrypt password hashing and verification | PHP native; no library dependency |

### Supporting

| Technology | Version | Purpose | When to Use |
|------------|---------|---------|-------------|
| `.gitignore` | Git standard | Prevent `api/config.php` from being committed | Mandatory before first credential is written to config.php |
| Supabase Dashboard SQL Editor | Web UI | Create and run DDL (CREATE TABLE) | First-time setup; Sharon does this manually in browser |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Raw REST API (recommended) | Supabase PHP SDK (unofficial) | No official PHP SDK exists; third-party packages add Composer dependency with no advantage for this stack |
| `TEXT` for password_hash (recommended) | `VARCHAR(60)` | `PASSWORD_DEFAULT` may produce hashes longer than 60 chars in future PHP versions; `TEXT` is safe forever |
| `TEXT` with `CHECK` constraint for gender (recommended) | PostgreSQL native `ENUM` type | CHECK constraints can be modified without schema ALTER; ENUMs require schema changes to add/remove values |
| `config.php` in `.gitignore` (recommended) | Environment variables via `.env` | `.env` approach requires a library (`vlucas/phpdotenv` via Composer) on cPanel; `config.php` already exists and works |

**No installation required.** All PHP functions are built-in. No Composer. No npm.

---

## Architecture Patterns

### Recommended Project Structure (additions for Phase 3)

```
api/
├── config.php          ← ADD: SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY constants
│                           (already has MICROPAY_TOKEN — same pattern)
├── supabase.php        ← NEW: Shared cURL helper function for all Supabase calls
├── login.php           ← Unchanged (Phase 6 will modify this)
├── check-session.php   ← Unchanged
└── send-sms.php        ← Unchanged

.gitignore              ← NEW: Created here (project had none before Phase 3)
                            Must contain: api/config.php
```

### Pattern 1: Supabase Credentials in config.php

**What:** Add two new constants to the existing `api/config.php` — the Supabase Project URL and the `service_role` key.

**Why this pattern:** Matches Phase 1's Micropay token pattern exactly. One file, one source of truth, never echoed in responses.

```php
<?php
// api/config.php — additions for Phase 3
// (Existing constants remain unchanged)

// Supabase project URL — found in: Dashboard > Settings > API > Project URL
// Format: https://<project_ref>.supabase.co
define('SUPABASE_URL', 'https://your-project-ref.supabase.co');

// Supabase service_role key — found in: Dashboard > Settings > API Keys > service_role
// NEVER expose this key client-side. It bypasses Row Level Security.
define('SUPABASE_SERVICE_ROLE_KEY', 'eyJ...');
```

**Where to find credentials in Supabase Dashboard:**
1. Go to `https://supabase.com/dashboard/project/<your-project>`
2. Left sidebar → Settings → API
3. **Project URL**: shown at the top of the API settings page
4. **service_role key**: in the "API Keys" section (legacy tab: "service_role"; new tab: secret key starting with `sb_secret_`)

### Pattern 2: PHP cURL Helper for Supabase REST API (Dual-Header Pattern)

**What:** A single reusable function `supabase_request()` in `api/supabase.php` that all future endpoints will call. Accepts HTTP method, table path, optional body, and optional query parameters.

**The dual-header requirement:** BOTH headers must be set with the `service_role` key. The `Authorization: Bearer` header is what actually bypasses RLS. Setting only `apikey` does not bypass RLS.

**Source:** Supabase official docs (https://supabase.com/docs/guides/api/quickstart) + RLS troubleshooting guide (https://supabase.com/docs/guides/troubleshooting/why-is-my-service-role-key-client-getting-rls-errors-or-not-returning-data-7_1K9z)

```php
<?php
/**
 * api/supabase.php — Shared cURL helper for all Supabase REST API calls
 *
 * Usage: require_once __DIR__ . '/supabase.php';
 * Then: $result = supabase_request('GET', '/users');
 *       $result = supabase_request('POST', '/users', ['email' => '...', ...]);
 *       $result = supabase_request('PATCH', '/users?id=eq.5', ['status' => 'blocked']);
 *       $result = supabase_request('DELETE', '/users?id=eq.5');
 *
 * Returns: ['data' => mixed, 'http_code' => int, 'error' => string|null]
 */

require_once __DIR__ . '/config.php';

/**
 * Make an authenticated request to the Supabase REST API.
 *
 * @param string      $method     HTTP method: GET, POST, PATCH, DELETE
 * @param string      $path       Table path with optional query params e.g. '/users' or '/users?email=eq.x'
 * @param array|null  $body       Data for POST/PATCH requests (will be JSON-encoded)
 * @param bool        $prefer_rep Whether to request the created/updated row back (Prefer: return=representation)
 * @return array      ['data' => decoded JSON or null, 'http_code' => int, 'error' => string|null]
 */
function supabase_request(string $method, string $path, ?array $body = null, bool $prefer_rep = false): array {
    $url = SUPABASE_URL . '/rest/v1' . $path;

    // DUAL-HEADER REQUIREMENT: Both headers must use service_role key.
    // Authorization: Bearer is what bypasses RLS — apikey alone does NOT bypass RLS.
    $headers = [
        'apikey: '        . SUPABASE_SERVICE_ROLE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
        'Content-Type: application/json',
    ];

    // Add Prefer header when we need the full row returned (e.g. after INSERT)
    if ($prefer_rep) {
        $headers[] = 'Prefer: return=representation';
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);  // Always verify SSL for Supabase HTTPS

    // Attach JSON body for write operations
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw      = curl_exec($ch);
    $error    = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error !== '') {
        return ['data' => null, 'http_code' => 0, 'error' => $error];
    }

    // Supabase returns JSON arrays or objects; decode to PHP array
    $decoded = json_decode($raw, true);
    return ['data' => $decoded, 'http_code' => $httpCode, 'error' => null];
}
```

### Pattern 3: .gitignore — Keep Credentials Out of Git

**What:** Create `.gitignore` in the project root. Phase 1 did not create one (no secrets were yet in tracked files — `config.php` was committed with only Micropay token; this must now change).

**Important note about existing config.php:** `api/config.php` is already tracked by git (it was committed in Phase 1). Adding it to `.gitignore` does NOT automatically untrack it. The file must be explicitly removed from git tracking with `git rm --cached api/config.php` before the `.gitignore` entry will take effect. The physical file stays on disk.

```gitignore
# Credentials — never commit
api/config.php
```

**Full recommended .gitignore for this project:**
```gitignore
# Server credentials — never commit
api/config.php

# OS / Editor noise
.DS_Store
Thumbs.db
.idea/
.vscode/settings.json
*.swp
```

### Pattern 4: CREATE TABLE SQL for public.users

**What:** SQL to run in the Supabase Dashboard SQL Editor to create the `users` table.

**Design decisions:**
- `id` is `bigint generated always as identity` — simpler than UUID for this internal app; auto-increments, no extension needed
- `password_hash TEXT` — not VARCHAR(60); future PHP versions may produce longer hashes
- `status TEXT` with CHECK constraint — not ENUM; easier to modify later
- `gender TEXT` with CHECK constraint — same reasoning
- `foreign_worker BOOLEAN` — clean boolean, not text 'yes'/'no'
- `suspended_until TIMESTAMPTZ NULL` — NULL means not suspended; a future timestamp means suspended
- All timestamps use `TIMESTAMPTZ` (with timezone) — Supabase/PostgreSQL best practice
- `updated_at` triggers require a trigger function — included in SQL below

```sql
-- Run in Supabase Dashboard > SQL Editor
-- Creates the public.users table for the FirstApp User Management system

CREATE TABLE IF NOT EXISTS public.users (
    id              BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    first_name      TEXT        NOT NULL,
    last_name       TEXT        NOT NULL,
    id_number       TEXT        NOT NULL UNIQUE,   -- Israeli ID / passport
    phone           TEXT        NOT NULL,
    gender          TEXT        NOT NULL CHECK (gender IN ('male', 'female')),
    foreign_worker  BOOLEAN     NOT NULL DEFAULT false,
    email           TEXT        NOT NULL UNIQUE,
    password_hash   TEXT        NOT NULL,          -- bcrypt via PHP password_hash()
    status          TEXT        NOT NULL DEFAULT 'active'
                                CHECK (status IN ('active', 'blocked', 'suspended')),
    suspended_until TIMESTAMPTZ NULL,              -- NULL = not suspended; future date = suspended until
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Trigger to auto-update updated_at on every UPDATE
CREATE OR REPLACE FUNCTION public.set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER users_set_updated_at
    BEFORE UPDATE ON public.users
    FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

-- Comment for future maintainers
COMMENT ON TABLE public.users IS
    'Application users for FirstApp. Auth is PHP session-based with bcrypt passwords. No Supabase GoTrue.';
```

### Pattern 5: Verifying the Connection (Test GET Request)

**What:** After `api/supabase.php` is deployed, verify it works by calling it in a test script that queries the empty `users` table. Expected response: `[]` (empty JSON array).

```php
<?php
// api/test-supabase.php — TEMPORARY test file, DELETE after verification
// Access in browser: https://ch-ah.info/FirstApp/api/test-supabase.php

require_once __DIR__ . '/supabase.php';

$result = supabase_request('GET', '/users?select=*');

header('Content-Type: application/json');
echo json_encode($result);
// Expected: {"data":[],"http_code":200,"error":null}
```

**DELETE this file after verification.** It exposes the Supabase connection to anyone with the URL.

### Anti-Patterns to Avoid

- **Setting only `apikey` header without `Authorization`:** RLS is enforced by the `Authorization` header, not `apikey`. With RLS enabled (Supabase default), requests with only `apikey` may return empty arrays or 403 errors silently.
- **Using `VARCHAR(60)` for password_hash:** PHP's `PASSWORD_DEFAULT` can produce hashes longer than 60 chars in future versions. Use `TEXT`.
- **Committing `api/config.php` with Supabase credentials:** Phase 1 committed `config.php` — it is currently tracked by git. Must run `git rm --cached api/config.php` before pushing Phase 3 credentials.
- **Using the `anon` key server-side for write operations:** The `anon` key respects RLS policies. With no RLS policies defined on the `users` table, anon key reads/writes may fail or behave unexpectedly. Use `service_role` for all server-side PHP calls.
- **Leaving the test file deployed:** `api/test-supabase.php` must be deleted after the connection is verified. It reveals whether the Supabase connection is working to any browser visitor.
- **CURLOPT_SSL_VERIFYPEER = false:** Supabase is served over HTTPS with a valid certificate. Never disable SSL verification for Supabase calls in production.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Password hashing | Custom MD5/SHA1 hash | `password_hash($pw, PASSWORD_DEFAULT)` | PHP built-in bcrypt; correct salt generation; timing-safe with `password_verify()` |
| JSON HTTP client | Custom string-building for REST calls | The `supabase_request()` helper (Pattern 2) | All future endpoints share the same cURL setup, headers, and error handling |
| updated_at maintenance | Setting `updated_at` manually in PHP | PostgreSQL trigger (Pattern 4) | PHP clocks can drift or be wrong; DB trigger is authoritative |
| SQL injection protection | String concatenation for filter values | PostgREST URL filter syntax (`?email=eq.x`) | PostgREST handles parameterization; PHP never builds raw SQL strings for REST calls |

**Key insight:** PHP never touches SQL directly in this architecture. All data operations go through the Supabase REST API (PostgREST), which handles parameterization. SQL injection is not a concern for the PHP layer.

---

## Common Pitfalls

### Pitfall 1: Missing Authorization Header — RLS Blocks All Requests

**What goes wrong:** GET request to `/rest/v1/users` returns `[]` even after inserting rows, or returns HTTP 403. POST returns 403 or 401.

**Why it happens:** Supabase enables RLS on all tables by default. RLS is enforced by the `Authorization` header, not the `apikey` header. If `Authorization: Bearer <service_role_key>` is missing or set to a user JWT instead of the service_role key, RLS applies and blocks the request.

**How to avoid:** In `supabase_request()`, always set BOTH:
```
apikey: <service_role_key>
Authorization: Bearer <service_role_key>
```

**Warning signs:** Table has rows visible in Supabase dashboard but GET returns `[]`. No PHP error — just empty data.

### Pitfall 2: config.php Already Tracked by Git

**What goes wrong:** Developer adds `api/config.php` to `.gitignore`, adds Supabase credentials, does `git add .` and `git commit` — and the credentials are committed because `.gitignore` only prevents NEW untracked files from being added, not files already in git's index.

**Why it happens:** Phase 1 committed `api/config.php` (it contained only the Micropay token at that time). Git still tracks it.

**How to avoid:** Before adding Supabase credentials to `config.php`:
1. Create `.gitignore` with `api/config.php`
2. Run: `git rm --cached api/config.php`
3. Commit the removal: `git commit -m "stop tracking config.php"`
4. THEN add Supabase credentials to `config.php`

**Verification:** After the above steps, `git status` must NOT show `api/config.php` under any section.

### Pitfall 3: PHP Version Below 7.4 — password_hash Constant Change

**What goes wrong:** `password_hash()` returns `false` or throws an error; or `PASSWORD_DEFAULT` / `PASSWORD_BCRYPT` constants behave differently.

**Why it happens:** PHP 8.0 changed `PASSWORD_DEFAULT` from `int 1` to `null` and `PASSWORD_BCRYPT` from `int 1` to `string '2y'`. PHP 7.4 still accepts integers but shows deprecation warnings. Below PHP 7.4, behavior may differ.

**How to avoid:** Verify the PHP version on ch-ah.info before Phase 3 execution. Phase 3 plan will include a one-time phpinfo check. Required minimum: PHP 7.4. Recommended: PHP 8.2 (current cPanel default per Phase 1 research).

**Warning signs:** `password_hash()` returns `false`. Login validation always fails even with correct password.

### Pitfall 4: Supabase Free Plan — Project Pauses After Inactivity

**What goes wrong:** Supabase free tier pauses projects after 7 days of inactivity. A paused project returns 503 or connection refused errors. The first request after reactivation is slow (~30 seconds).

**Why it happens:** Supabase free tier hibernation policy.

**How to avoid:** For a learning project, this is acceptable. For the plan: document that if the project stops responding, Sharon should log into the Supabase Dashboard and resume the project. A paid plan or a monthly visit to the dashboard avoids this.

**Warning signs:** All Supabase calls return 503. Dashboard shows "Project paused."

### Pitfall 5: Supabase Dashboard Key Tab (Legacy vs New API Keys)

**What goes wrong:** Sharon navigates to API settings and sees unfamiliar "Publishable Key" / "Secret Key" (`sb_publishable_...` / `sb_secret_...`) instead of `anon` / `service_role`.

**Why it happens:** Supabase is migrating to a new key format. New projects may show new-format keys. Both old and new formats work; the `service_role` equivalent is the "secret key."

**How to avoid:** In the plan, instruct Sharon to use the **service_role** key (legacy tab) or the **secret key** (new tab). Both work identically for server-side PHP cURL calls. The plan will include a screenshot-guided step.

**Warning signs:** Authentication fails with "No API key found in request" if the wrong key or format is pasted.

### Pitfall 6: cURL SSL Certificate Verification Failure

**What goes wrong:** cURL returns error `SSL certificate problem: unable to get local issuer certificate` on some Windows-based servers or older cPanel configurations.

**Why it happens:** PHP's cURL on some servers does not have an up-to-date CA bundle configured. Supabase uses a valid Let's Encrypt certificate but the server's `curl.cainfo` may point to an outdated bundle.

**How to avoid:** If SSL verification fails, the cPanel PHP configuration needs an updated `cacert.pem`. Do NOT disable SSL verification (`CURLOPT_SSL_VERIFYPEER = false`) in production. The plan will include a fallback diagnostic step for this case.

**Warning signs:** cURL `$error` contains "SSL certificate problem."

---

## Code Examples

### Verify the Connection (full test)

```php
<?php
// Source: Supabase REST API docs + Pattern 2 above
require_once __DIR__ . '/supabase.php';

$result = supabase_request('GET', '/users?select=*');

// After table creation with no rows: $result['data'] === [] and $result['http_code'] === 200
// If $result['http_code'] === 401 or 403: Authorization header problem (Pitfall 1)
// If $result['error'] !== null: cURL network/SSL problem
header('Content-Type: application/json');
echo json_encode($result);
```

### Check PHP Version Programmatically (pre-flight)

```php
<?php
// api/check-env.php — TEMPORARY, delete after use
// Verifies PHP version and cURL availability before Phase 3 build
echo 'PHP version: ' . PHP_VERSION . "\n";
echo 'cURL loaded: ' . (extension_loaded('curl') ? 'YES' : 'NO') . "\n";
echo 'password_hash available: ' . (function_exists('password_hash') ? 'YES' : 'NO') . "\n";
// Required: PHP >= 7.4, cURL = YES, password_hash = YES
```

### password_hash / password_verify Pattern (for Phase 4 reference)

```php
<?php
// Source: PHP Manual — https://www.php.net/manual/en/function.password-hash.php
// Used in Phase 4 (create user) and Phase 6 (login verification)

// On user creation (Phase 4):
$hash = password_hash($plaintextPassword, PASSWORD_DEFAULT);
// Store $hash in Supabase users.password_hash column

// On login (Phase 6):
$storedHash = /* fetched from Supabase */;
if (password_verify($plaintextPassword, $storedHash)) {
    // Login succeeds
}
// password_verify is timing-safe — no timing attack possible
```

### PostgREST Filter Syntax Reference

```
GET /rest/v1/users                           → All rows
GET /rest/v1/users?select=*                  → All rows, all columns (explicit)
GET /rest/v1/users?email=eq.user@example.com → WHERE email = 'user@example.com'
GET /rest/v1/users?id=eq.5                   → WHERE id = 5
GET /rest/v1/users?status=eq.active          → WHERE status = 'active'
GET /rest/v1/users?select=id,email,status    → SELECT id, email, status only
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Supabase `anon` key for server-side PHP | `service_role` key for server-side PHP | Always best practice | `anon` key respects RLS; without RLS policies defined, reads/writes may silently fail |
| Legacy API keys (`anon`, `service_role`) | New API keys (`sb_publishable_...`, `sb_secret_...`) | 2025 — Supabase key migration | Both work; use whichever the dashboard shows; concept is the same |
| `VARCHAR(60)` for bcrypt hashes | `TEXT` for password_hash column | PHP 8.4 (cost increase) | Future-proof: `TEXT` accommodates any hash length |
| `ENUM` type for constrained columns | `TEXT` + `CHECK` constraint | PostgreSQL community guidance | CHECK constraints are easier to modify without schema migration |
| Manual `updated_at` in application code | PostgreSQL trigger on `BEFORE UPDATE` | PostgreSQL best practice | DB trigger is atomic and authoritative; PHP clock can drift |

---

## Open Questions

1. **Is `api/config.php` currently tracked by git on the live repo (GitHub ChemoIT/FirstApp)?**
   - What we know: Phase 1 committed config.php with Micropay token. It is tracked.
   - What's unclear: Whether the remote repo already has config.php pushed (likely yes, based on Phase 1 deploy).
   - Recommendation: Phase 3 plan MUST include `git rm --cached api/config.php` as an explicit step before adding Supabase credentials. Also check if config.php is already on GitHub and if so, rotate the Micropay token as a precaution.

2. **Does Sharon's Supabase account already have a project created?**
   - What we know: Phase context says "Sharon has the credentials" — implies project exists.
   - What's unclear: Whether the `users` table already exists or starts fresh.
   - Recommendation: Plan should include a "check if table already exists" step before running CREATE TABLE.

3. **Is RLS enabled on the Supabase project?**
   - What we know: Supabase enables RLS by default on tables created via Dashboard Table Editor; SQL Editor creates tables WITHOUT RLS enabled unless explicitly added.
   - What's unclear: The project's current RLS state.
   - Recommendation: Since we use `service_role` key which bypasses RLS regardless, RLS state does not affect Phase 3 functionality. Document this for Phase 4+.

4. **What is the PHP version on ch-ah.info?**
   - What we know: Phase 1 research says PHP 8.2 is the new cPanel default. Phase 1 was built and deployed successfully.
   - What's unclear: The exact version confirmed on ch-ah.info.
   - Recommendation: Include `phpversion()` check as first step in Phase 3 plan. Minimum required: 7.4. Recommended: 8.2.

---

## Sources

### Primary (HIGH confidence)

- Supabase REST API Quickstart — https://supabase.com/docs/guides/api/quickstart — confirmed dual-header requirement (apikey + Authorization), base URL format `https://<ref>.supabase.co/rest/v1/`
- Supabase API Keys Docs — https://supabase.com/docs/guides/api/api-keys — confirmed service_role bypasses RLS via BYPASSRLS attribute; navigation path to keys in dashboard
- Supabase RLS Troubleshooting — https://supabase.com/docs/guides/troubleshooting/why-is-my-service-role-key-client-getting-rls-errors-or-not-returning-data-7_1K9z — confirmed "RLS is enforced based on Authorization header, not apikey header"
- PHP Manual: password_hash — https://www.php.net/manual/en/function.password-hash.php — confirmed available PHP 5.5+; PASSWORD_DEFAULT behavior; 72-byte truncation; PHP 8.4 cost change
- Phase 1 Research (01-RESEARCH.md) — established cURL pattern, config.php isolation, cPanel PHP 8.2 default

### Secondary (MEDIUM confidence)

- Supabase Tables and Data — https://supabase.com/docs/guides/database/tables — CREATE TABLE syntax, SQL Editor usage, `bigint generated always as identity` pattern
- Crunchydata: Enums vs Check Constraints — https://www.crunchydata.com/blog/enums-vs-check-constraints-in-postgres — CHECK constraints preferred over ENUM for modifiable columns
- Supabase REST API Discussion — https://github.com/orgs/supabase/discussions/34958 — service_role with RLS confirmed community-level; both headers must match service_role key
- Supabase key migration (sb_publishable/sb_secret) — confirmed via API keys docs page; legacy keys still work

### Tertiary (LOW confidence — verify before use)

- Supabase free tier hibernation (7-day inactivity pause) — mentioned in multiple community discussions but specific terms not confirmed in official docs. Verify current policy at https://supabase.com/pricing before planning.

---

## Metadata

**Confidence breakdown:**

| Area | Level | Reason |
|------|-------|--------|
| Supabase REST API dual-header pattern | HIGH | Confirmed in official docs and RLS troubleshooting guide |
| PHP cURL helper structure | HIGH | Direct extension of proven Micropay pattern from Phase 1 |
| PostgreSQL CREATE TABLE SQL | HIGH | Standard PostgreSQL syntax; confirmed via Supabase docs examples |
| password_hash/password_verify behavior | HIGH | Official PHP manual; version behavior confirmed |
| .gitignore / untracking config.php | HIGH | Standard git behavior; well-documented |
| Supabase dashboard navigation (key location) | MEDIUM | UI may have changed with new key format migration; plan must account for both old/new tabs |
| Supabase free tier hibernation policy | LOW | Policy details not confirmed in official docs; treat as informational |

**Research date:** 2026-02-28
**Valid until:** 2026-08-28 (Supabase REST API is stable; re-verify dashboard navigation if Supabase UI changes)
