# Technology Stack

**Project:** FirstApp v2.0 — Supabase User Management Milestone
**Researched:** 2026-02-28
**Confidence:** HIGH (verified via WebSearch + official Supabase docs)

---

## Context: What This Milestone Adds

This is a SUBSEQUENT MILESTONE file. The v1.0 stack (PHP, cURL, vanilla JS, cPanel, signature_pad) is validated and unchanged. This document covers ONLY the new additions required for Supabase-backed user management.

**New capabilities needed:**
1. PHP → Supabase PostgREST REST API (CRUD on a `users` table)
2. PHP → Supabase GoTrue Auth Admin API (create/delete auth users)
3. Admin UI: create, edit, delete, block, suspend users
4. Login: email + password authentication via Supabase GoTrue

---

## Recommended Stack Additions

### New API Surfaces (No New Libraries — Pure cURL)

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| Supabase PostgREST | v12.x (hosted, auto-updated) | CRUD on `public.users` table via REST | Already proven PHP cURL pattern. No new library. JSON over HTTP. |
| Supabase GoTrue Auth API | v2.x (hosted, auto-updated) | Create/delete/update users in `auth.users` | Admin operations (create user with email+password, delete, ban) require GoTrue, not PostgREST. Service role key required. |
| PHP cURL (existing) | Built into PHP 7.4/8.x | HTTP client for all Supabase calls | Already used for Micropay SMS. Same pattern extended to Supabase. No new dependency. |

**Decision: No PHP Supabase SDK.** The community library `phpsupabase` (rafaelwendel) requires Composer, is not officially supported by Supabase, and was created in 2021 with uncertain maintenance status. Raw cURL calls to documented REST endpoints are more predictable, easier to debug, and have zero dependency risk on cPanel shared hosting where Composer may not be available.

### PHP Password Hashing (Built-in, No Library)

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| `password_hash()` + `password_verify()` | PHP 5.5+ built-in | Hash passwords stored in `public.users` | Native PHP function; bcrypt by default; handles salt automatically; works on any PHP 7.x/8.x cPanel host. Zero dependencies. |

**Algorithm choice:** Use `PASSWORD_DEFAULT` (bcrypt, cost 10). On cPanel shared hosting with limited CPU, Argon2id is overkill and may slow login. Bcrypt at cost 10 is industry-standard for this context.

### Admin UI (No New Frontend Libraries)

| Technology | Purpose | Why |
|------------|---------|-----|
| Existing vanilla JS + HTML/CSS | Admin page UI (table, forms, modal) | No framework needed for a data table + form. Bootstrap or React would add complexity for what is a CRUD form. Same FTP-deployable pattern as v1.0. |
| `fetch()` API (browser built-in) | AJAX calls from admin.php to PHP endpoints | Already used in v1.0. No library needed. |

---

## Supabase API Integration Details

### Two Separate API Surfaces

**PostgREST** — for your own `public.users` table (custom fields: name, phone, gender, etc.)

```
Base URL: https://<project_ref>.supabase.co/rest/v1
```

**GoTrue Auth API** — for Supabase's built-in auth system (email + password login)

```
Base URL: https://<project_ref>.supabase.co/auth/v1
```

These are different services. You will call BOTH from PHP.

### Required Headers for All Supabase Calls

```php
$headers = [
    'Content-Type: application/json',
    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
];
```

Both `apikey` and `Authorization: Bearer` must be sent together. `apikey` is consumed by the Supabase API gateway; `Authorization` is consumed by PostgREST/GoTrue for RLS/admin privilege checks.

**Service role key** — bypasses Row Level Security, required for admin operations. Must stay server-side in PHP only. Never in JS, never echoed to page.

### PostgREST CRUD Endpoints

| Operation | Method | URL | Extra Headers |
|-----------|--------|-----|---------------|
| List all users | GET | `/rest/v1/users` | — |
| Get one user | GET | `/rest/v1/users?id=eq.{id}` | — |
| Create user | POST | `/rest/v1/users` | `Prefer: return=representation` |
| Update user | PATCH | `/rest/v1/users?id=eq.{id}` | `Prefer: return=representation` |
| Delete user | DELETE | `/rest/v1/users?id=eq.{id}` | — |

`Prefer: return=representation` makes Supabase return the created/updated row. Without it, you get an empty 204 response — fine for delete, needed for create/update to get back the `id`.

### GoTrue Auth Admin Endpoints (for auth.users)

| Operation | Method | URL | Notes |
|-----------|--------|-----|-------|
| Sign in (login) | POST | `/auth/v1/token?grant_type=password` | Body: `{"email":"...","password":"..."}` — returns JWT access_token |
| Create auth user | POST | `/auth/v1/admin/users` | Body: `{"email":"...","password":"...","email_confirm":true}` |
| List auth users | GET | `/auth/v1/admin/users` | Returns paginated list |
| Update auth user | PUT | `/auth/v1/admin/user/{user_id}` | Body: `{"email":"...","password":"...","ban_duration":"876600h"}` |
| Delete auth user | DELETE | `/auth/v1/admin/user/{user_id}` | Permanently removes from auth.users |

**Note on `ban_duration`:** Supabase GoTrue supports banning users by setting `ban_duration` to a duration string (e.g. `"876600h"` = 100 years = effectively permanent). This is how you implement "blocked" status via the auth system.

### PHP cURL Helper Pattern

This reusable function covers all Supabase calls:

```php
/**
 * Make an authenticated HTTP call to Supabase REST or Auth API.
 *
 * @param string $method  GET | POST | PATCH | PUT | DELETE
 * @param string $url     Full Supabase endpoint URL
 * @param array  $body    Request body (will be JSON-encoded). Null for GET/DELETE.
 * @param array  $extra_headers  Additional headers (e.g. Prefer: return=representation)
 * @return array  ['status' => int, 'body' => mixed]
 */
function supabase_request(string $method, string $url, ?array $body = null, array $extra_headers = []): array {
    $ch = curl_init($url);

    $headers = array_merge([
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
    ], $extra_headers);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Supabase cURL error [{$method} {$url}]: {$error}");
        return ['status' => 0, 'body' => null, 'error' => $error];
    }

    return ['status' => $status, 'body' => json_decode($response, true)];
}
```

### Configuration File Pattern

Store Supabase credentials in a PHP config file, excluded from git:

```php
<?php
// config.php — NOT committed to GitHub (add to .gitignore)
define('SUPABASE_URL',              'https://xxxxxxxxxxxx.supabase.co');
define('SUPABASE_SERVICE_ROLE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...');
define('SUPABASE_REST_URL',         SUPABASE_URL . '/rest/v1');
define('SUPABASE_AUTH_URL',         SUPABASE_URL . '/auth/v1');
```

---

## Architecture Decision: Where Passwords Live

**Decision: Dual storage — GoTrue auth + public.users profile table.**

- `auth.users` (Supabase GoTrue) — stores email + hashed password. Handles login token issuance.
- `public.users` (your own table) — stores profile fields: first_name, last_name, id_number, phone, gender, foreign_worker, status (active/blocked/suspended).

**Why dual, not single table:**

| Option | Pro | Con |
|--------|-----|-----|
| GoTrue only (`auth.users`) | Single source of truth for auth | Cannot store custom fields (gender, foreign_worker, id_number) without workarounds in user_metadata JSON blob — messy to query/filter |
| Custom table only (`public.users` with password_hash) | Full control, simple SQL | Must implement your own session token system; skips Supabase's built-in auth features |
| **Dual (GoTrue + public.users)** | Clean separation: GoTrue handles login tokens; public.users handles business fields | Two API calls on create/delete — manageable with the cURL helper |

**The link:** `public.users.auth_id` = UUID from `auth.users.id`. Created together in one PHP transaction on the admin "create user" action.

**Login flow:** POST to GoTrue `/auth/v1/token?grant_type=password` → validates credentials → returns JWT → PHP stores `access_token` in PHP `$_SESSION` → subsequent requests validate session. No client-side token storage.

---

## What NOT to Add

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| `phpsupabase` Composer library | Requires Composer (uncertain on cPanel), unofficial, 2021 vintage, uncertain maintenance | Raw PHP cURL to documented REST endpoints |
| Supabase JS Client SDK | Service role key would be exposed in browser JS | Server-side PHP cURL only |
| Any CSS framework (Bootstrap, Tailwind) | Admin page is a simple CRUD table + form; 80 lines of plain CSS handles it | Plain CSS (same pattern as v1.0) |
| React/Vue for admin UI | Requires Node.js build step, incompatible with FTP deploy | Vanilla JS + `fetch()` for AJAX |
| Argon2id for password hashing | Memory-intensive; may cause issues on shared hosting CPU limits | `password_hash($pwd, PASSWORD_DEFAULT)` = bcrypt cost 10 |
| Supabase Realtime websockets | Not needed for admin CRUD | Plain HTTP REST calls |
| JWT library in PHP | You don't need to decode JWTs server-side for this app; PHP session stores the token opaquely | PHP `$_SESSION` |

---

## Database Schema Addition

New table to create in Supabase Dashboard or migration:

```sql
CREATE TABLE public.users (
    id            UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    auth_id       UUID REFERENCES auth.users(id) ON DELETE CASCADE,
    first_name    TEXT NOT NULL,
    last_name     TEXT NOT NULL,
    id_number     TEXT,
    phone         TEXT,
    gender        TEXT CHECK (gender IN ('male', 'female', 'other')),
    foreign_worker BOOLEAN DEFAULT false,
    email         TEXT NOT NULL UNIQUE,
    status        TEXT DEFAULT 'active' CHECK (status IN ('active', 'blocked', 'suspended')),
    created_at    TIMESTAMPTZ DEFAULT now(),
    updated_at    TIMESTAMPTZ DEFAULT now()
);

-- Enable RLS but service role bypasses it — safe for PHP backend
ALTER TABLE public.users ENABLE ROW LEVEL SECURITY;
```

`status` field in `public.users` is the source of truth for UI display. `ban_duration` in GoTrue enforces the auth-level block (prevents login token issuance even if user knows the password).

---

## Alternatives Considered

| Category | Recommended | Alternative | Why Not |
|----------|-------------|-------------|---------|
| User data store | Supabase PostgreSQL via REST | MySQL on cPanel | Supabase is already the chosen constraint for this milestone; MySQL would require adding DB credentials management, mysqli/PDO library, and has no admin API for auth |
| Auth system | Supabase GoTrue (via HTTP) | PHP sessions with bcrypt only (no GoTrue) | GoTrue provides the login endpoint and handles password hashing on Supabase's side — less PHP code to maintain |
| PHP→Supabase client | Raw cURL | phpsupabase library | Library requires Composer, unofficial, uncertain maintenance |
| Admin UI | PHP-rendered HTML + vanilla JS fetch | Single-page React app | SPA adds build step and FTP-deploy incompatibility |

---

## Installation / Setup Steps

No npm installs. No Composer installs. Setup is:

1. Create Supabase project at supabase.com (free tier sufficient)
2. Run the SQL schema above in Supabase Dashboard → SQL Editor
3. Copy Project URL + service role key from Dashboard → Settings → API
4. Create `config.php` with those values (add to `.gitignore`)
5. Enable Email provider in Supabase Dashboard → Authentication → Providers → Email (disable email confirmation for internal app)
6. Write PHP endpoints: `admin.php`, `api/users.php` (CRUD), `api/auth.php` (login)

---

## Version Compatibility

| Component | Version | Compatibility Notes |
|-----------|---------|---------------------|
| PHP cURL | Built into PHP 7.4+ | Available on all cPanel hosts; no version conflict |
| Supabase PostgREST | v12.x (hosted) | Supabase manages upgrades; no action needed |
| Supabase GoTrue | v2.x (hosted) | Supabase manages upgrades; no action needed |
| `password_hash()` | PHP 5.5+ | Full support on PHP 7.4 and PHP 8.x |
| PHP `$_SESSION` | All PHP versions | No compatibility concern |

---

## Security Checklist for This Milestone

| Risk | Prevention |
|------|------------|
| Service role key in JS | Lives only in `config.php` on server. Never echoed to page output. |
| Service role key in git | `config.php` in `.gitignore`. GitHub Actions uses repository secrets. |
| SQL injection via PostgREST | Not applicable — PostgREST uses URL query params, not raw SQL. Supabase handles parameterization. |
| Admin page accessible to non-admins | `admin.php` requires `$_SESSION['user']['role'] === 'admin'` check at top of file |
| Brute force on login | Supabase GoTrue has built-in rate limiting on `/auth/v1/token` endpoint |
| User enumeration via login error | Return generic "Invalid credentials" for both wrong email and wrong password |

---

## Confidence Assessment

| Area | Confidence | Reason |
|------|------------|--------|
| PostgREST endpoint patterns | HIGH | Verified via official Supabase docs + WebSearch |
| GoTrue admin API endpoints | HIGH | Confirmed via official self-hosting auth docs: `/auth/v1/admin/users`, `/auth/v1/token` |
| Required headers (`apikey` + `Authorization`) | HIGH | Verified via Supabase API keys docs |
| `Prefer: return=representation` header | HIGH | Verified via PostgREST v12 docs |
| `ban_duration` for blocking users | MEDIUM | Confirmed in search results as the GoTrue mechanism; verify exact string format ("876600h") in Supabase dashboard test |
| `password_hash()` bcrypt on cPanel | HIGH | Built-in PHP function since 5.5; no compatibility risk |
| Dual table architecture (GoTrue + public.users) | HIGH | Standard Supabase pattern for apps with custom user fields |
| phpsupabase library decision to avoid | MEDIUM | Assessment based on GitHub age (2021) and Composer requirement; library may have improved but raw cURL is safer bet |

---

## Sources

- Supabase REST API docs: https://supabase.com/docs/guides/api — base URL, CRUD methods
- Supabase API Keys docs: https://supabase.com/docs/guides/api/api-keys — `apikey` vs service role key, header requirements
- Supabase GoTrue self-hosting auth API: https://supabase.com/docs/reference/self-hosting-auth/introduction — admin endpoint paths
- PostgREST v12 Prefer header: https://docs.postgrest.org/en/v12/references/api/preferences.html — `return=representation`
- PHP password_hash docs: https://www.php.net/manual/en/function.password-hash.php — bcrypt, PASSWORD_DEFAULT
- phpsupabase GitHub: https://github.com/rafaelwendel/phpsupabase — assessed and rejected (Composer-only, unofficial)
- Supabase admin API (JS reference, used to infer HTTP layer): https://supabase.com/docs/reference/javascript/admin-api

---

*Stack research for: Supabase user management — PHP cURL integration*
*Researched: 2026-02-28*
*Supersedes: v1.0 STACK.md (which remains valid for base app capabilities)*
