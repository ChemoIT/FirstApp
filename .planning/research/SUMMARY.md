# Project Research Summary

**Project:** FirstApp v2.0 — Supabase User Management
**Domain:** PHP cPanel app + Supabase REST API integration (subsequent milestone)
**Researched:** 2026-02-28
**Confidence:** HIGH

---

## Executive Summary

FirstApp v2.0 adds database-backed user management to an existing PHP signature dispatch app. The research is unanimous on approach: extend the existing PHP cURL pattern already used for the Micropay SMS gateway, applying it to Supabase's PostgREST REST API. No new libraries, no Composer, no framework — just the same `curl_init` / `curl_exec` pattern pointed at a different HTTPS endpoint. This is both the simplest and most robust path for a cPanel shared hosting environment where dependency management is restricted.

The architecture decision with the most downstream impact is to use a custom `public.users` table in Supabase's PostgreSQL, storing bcrypt-hashed passwords via PHP's built-in `password_hash()`, rather than using Supabase GoTrue Auth. This choice keeps all auth logic in PHP sessions (identical to v1.0 behaviour), gives full SQL control over custom fields (id_number, foreign_worker, suspended_until), and eliminates the complexity of managing JWTs. The trade-off is that PHP owns password security — `password_hash()` with `PASSWORD_BCRYPT` is the required implementation, not optional polish.

The two highest risks in this milestone are both setup-phase issues that must be resolved before any other code is written: (1) the dual-header requirement for all Supabase calls — both `apikey` and `Authorization: Bearer` must be sent simultaneously, which differs from standard REST API conventions and causes a hard 401 if missed; and (2) the service_role key must never appear in any git-tracked file. Both risks are fully mitigated by building a single shared `api/supabase.php` cURL helper and adding `api/config.php` to `.gitignore` before the first commit. Once those two foundations are solid, all remaining work follows standard CRUD patterns with well-documented paths.

---

## Key Findings

### Recommended Stack

The v2.0 stack adds no new languages, frameworks, or build tools. All new capabilities are delivered via PHP cURL calls to Supabase's PostgREST REST API (`/rest/v1/`) for CRUD on the custom `public.users` table. The architecture research recommends skipping Supabase GoTrue Auth entirely and handling authentication through the custom table, reducing the number of API surfaces to one.

**Core technologies:**

- **PHP cURL (existing):** HTTP client for all Supabase calls — same pattern as the Micropay SMS integration, zero new dependencies.
- **Supabase PostgREST v12 (hosted):** CRUD endpoint for the `public.users` table — GET, POST, PATCH, DELETE via URL query parameters and JSON body. Supabase manages upgrades automatically.
- **PHP `password_hash()` / `password_verify()` (built-in):** bcrypt password hashing and login verification — PHP 5.5+ built-in, no library needed, constant-time safe by default.
- **PHP `$_SESSION` (existing):** Session management for authenticated users — identical pattern to v1.0, no JWT handling required.
- **Vanilla JS `fetch()` (existing):** AJAX calls from admin.php to PHP endpoints — same pattern as v1.0 dispatch UI.

**Version requirements:** PHP 7.4+ (available on all cPanel hosts). Supabase free tier is sufficient (< 1000 users, < 500MB storage).

**Explicitly rejected (with rationale):**

- `phpsupabase` Composer library — unofficial, 2021 vintage, Composer uncertain on cPanel shared hosting; raw cURL is safer and more debuggable.
- Supabase JS SDK — would expose service_role key to the browser; PHP-only server-side calls are required.
- Supabase GoTrue Auth — adds JWT management, two separate API surfaces, and makes admin password-reset awkward; custom table approach is simpler for this use case.
- Any CSS or JS framework — FTP deploy pattern requires no build step; vanilla JS and plain CSS handle the admin UI.
- Argon2id password hashing — memory-intensive on shared hosting; bcrypt at cost 10-12 is the correct choice.

### Expected Features

The feature set is fully defined. The primary dependency that gates everything else: the Supabase schema must be created and verified in the Supabase dashboard before any PHP code is written, because column names dictate payload structure throughout all endpoints.

**Must have (table stakes — v2.0 core, all blocking):**

- Supabase `public.users` table — schema with all fields including `status` enum and `suspended_until`
- User list table — HTML table rendered via JS fetch to `api/users/list.php`, columns: name, ID, phone, status, actions
- Create user form — all 8 fields, email validation (JS + PHP `filter_var`), password minimum 8 chars
- Edit user — pre-populated modal, saves changes to Supabase via PATCH
- Delete user — with `confirm()` prompt before destructive action
- Block user — sets `status = 'blocked'`, permanent, no expiry logic needed
- Suspend user with end date — sets `status = 'suspended'` plus `suspended_until` timestamp
- Client-side search on user table — JS filter on rendered rows, no server round-trip
- Login with Supabase credentials — PHP fetches user by email, calls `password_verify()`, checks status before setting session
- Hebrew labels and RTL layout throughout

**Three-state user status model (confirmed canonical pattern, used by Google Workspace and Atlassian):**

| Status | Login allowed | Clears automatically |
|--------|---------------|----------------------|
| active | Yes | No |
| blocked | No | No — admin must manually unblock |
| suspended | No (while `today < suspended_until`) | Not auto — login check reads the date passively |

**Should have (v2.x polish, add after core works):**

- Show/hide password toggle — 2 lines of JS, eliminates real pain point during user creation
- Password generator button — JS string manipulation, directly useful for admin workflow
- Toast/snackbar feedback instead of `alert()` — better UX, teaches JS event timing
- Filter table by status — useful for fleet manager reviewing suspended workers

**Defer to v3.0+:**

- Admin page authentication — wrap admin.php in session check, same pattern as dispatch.php
- Sort table columns by clicking header
- Pagination — only needed when user list exceeds 50+ rows

**Explicit anti-features (do not build in v2.0):** RBAC, CSV bulk import, email notifications, password reset flow, audit log, real-time updates, soft delete with restore, forgot-password flow. Each is excluded with clear rationale — all add a sub-system worth of complexity for no learning benefit at this scale.

### Architecture Approach

The architecture is a thin extension of the v1.0 pattern. Browser calls PHP via `fetch()`. PHP calls Supabase via cURL. Supabase returns JSON. PHP validates, transforms, and returns JSON to the browser. No new layers, no new abstractions. All Supabase API calls are centralized through a single `api/supabase.php` helper that encodes the dual-header requirement exactly once — every endpoint file includes this helper rather than constructing headers independently.

**Major components (6 new files, 3 modified):**

1. **`api/supabase.php` (NEW):** Reusable cURL helper — `supabase_request(method, path, body, extra_headers)`. Single source of truth for authentication headers, timeout, error handling, and JSON decode. All other PHP endpoint files call this function; none construct raw cURL directly.
2. **`api/users/*.php` (NEW — 4 files):** Endpoint files for list, create, update, delete. Each validates input, hashes passwords where needed, calls `supabase_request()`, and returns JSON to the browser.
3. **`admin.php` (NEW):** PHP page serving the user management UI. HTML table populated via JS `fetch()` calls to `api/users/` endpoints. No server-side rendering of the table — data-driven via JS for simpler CRUD interactions.
4. **`api/login.php` (MODIFIED):** Replaces 2-line hardcoded credential check with: GET user from Supabase by email → status check → `password_verify()` → session set. This is the highest-risk change and is built last.
5. **`api/config.php` (MODIFIED):** Adds `SUPABASE_URL` and `SUPABASE_SERVICE_KEY` constants. Removes `ADMIN_USER` and `ADMIN_PASS` constants. Must be in `.gitignore` before the first commit.
6. **`Supabase Cloud` (EXTERNAL):** PostgreSQL via PostgREST. Single `public.users` table. RLS disabled (service_role key bypasses it regardless; disabling makes intent explicit and avoids Pitfall S4).

**Unchanged files:** `dashboard.html`, `sign.html`, `api/send-sms.php`, `api/save-signature.php`, `api/check-session.php`. The v1.0 core flow is untouched.

**Three architectural patterns to follow without exception:**

- All Supabase calls go through PHP — never from browser JS (service_role key security)
- One cURL helper, many callers (DRY — single point for header and credential changes)
- PHP owns password lifecycle — `password_hash()` on write, `password_verify()` on login, never store plaintext

### Critical Pitfalls

**v2.0 specific — Supabase + PHP integration:**

1. **Missing `apikey` header (Pitfall S1)** — Supabase PostgREST requires both `apikey: KEY` and `Authorization: Bearer KEY` simultaneously. Standard REST convention only uses Authorization. Missing `apikey` returns HTTP 401 with "No API key found in request." Prevention: centralize both headers in `api/supabase.php` so they cannot be omitted from any call.

2. **PATCH/DELETE without row filter = table-wide operation (Pitfall S3)** — Sending `PATCH /rest/v1/users` without `?id=eq.{uuid}` updates every row in the table. PostgREST applies bulk operations by design with no warning. Prevention: validate `$id` is non-empty before constructing every PATCH or DELETE URL; add an assertion `if (empty($id)) { die('User ID required'); }` at the top of update.php and delete.php.

3. **service_role key committed to GitHub (Pitfall S6)** — The service_role key bypasses all Row Level Security. If committed to the public `ChemoIT/FirstApp` repo, any internet user has unrestricted database access. Prevention: add `api/config.php` to `.gitignore` before the first commit and verify with `git status`. If already committed, rotate the key immediately in Supabase Dashboard → Settings → API.

4. **Plaintext passwords stored in Supabase (Pitfall S5)** — The custom table approach means PHP is responsible for hashing. Storing `$_POST['password']` directly inserts the real password in plaintext. Prevention: always `password_hash($password, PASSWORD_BCRYPT)` before any Supabase write; name the column `password_hash` not `password` as a built-in reminder.

5. **PATCH returns HTTP 200 but no rows updated (Pitfall S8)** — Caused by double-encoded JSON body (`json_encode` called on a string instead of an array) or missing `Content-Type: application/json` header. Prevention: always `json_encode(array)`, always include Content-Type, use `Prefer: return=representation` to verify the updated row appears in the response.

**Carry-forward from v1.0 (still active in v2.0 work):**

6. **PHP error display off on shared hosting (Pitfall 7)** — cPanel has `display_errors = Off` by default. Add `error_reporting(E_ALL); ini_set('display_errors', 1);` at the top of all new PHP files during development. Remove before deploy.

7. **SSL verification disabled (Pitfall S7)** — Never set `CURLOPT_SSL_VERIFYPEER = false`. If cPanel's CA bundle is outdated, download `cacert.pem` from curl.se and reference it explicitly via `CURLOPT_CAINFO`. The false flag is widely found in Stack Overflow answers and creates a man-in-the-middle vulnerability.

---

## Implications for Roadmap

V2.0 implementation has a deterministic dependency order driven by hard technical constraints. The build sequence below is not a stylistic preference — it reflects what must exist before the next thing can be built or tested.

### Phase 1: Foundation — Schema and Connection

**Rationale:** Nothing else can be built until the database table exists and PHP can read from it with verified credentials. This phase has zero risk to existing v1.0 functionality because no existing files are modified. The cURL helper built here becomes the dependency for all subsequent phases.

**Delivers:** Supabase project configured with `public.users` table (verified in dashboard), `api/config.php` updated with credentials (and confirmed in `.gitignore`), `api/supabase.php` cURL helper implemented and tested with a GET call returning an empty array `[]`.

**Addresses:** All "schema must exist first" dependencies from FEATURES.md; column name and type decisions (status enum, `suspended_until`, `password_hash` column name established from the start).

**Avoids:** Pitfall S1 (dual headers centralized in helper), Pitfall S5 (column named `password_hash` from day one), Pitfall S6 (config.php in .gitignore before first commit), Pitfall S4 (RLS explicitly disabled so service_role key behaves predictably).

**Research flag:** Standard patterns — no additional research needed. PostgREST setup and required headers are fully documented in official Supabase docs. Confidence HIGH.

---

### Phase 2: Admin CRUD — Read and Create

**Rationale:** Build the two safest endpoints first — `list.php` (read-only, no side effects) then `create.php` (additive only, no deletion risk). Scaffold the admin UI only after both API endpoints are verified. This validates the entire data flow before any destructive operations are introduced.

**Delivers:** `api/users/list.php`, `api/users/create.php`, and `admin.php` HTML page with working user table and create form. Admin can add the first real user and see it in the table. First user verified in Supabase dashboard. Password is confirmed to be stored as a bcrypt hash (not plaintext).

**Uses:** `api/supabase.php` helper from Phase 1; `password_hash()` on create; `Prefer: return=representation` header on POST to get the created row back including auto-generated UUID.

**Addresses:** User list table, create user form, email validation, password minimum 8 chars, Hebrew RTL layout, password generator button.

**Avoids:** Pitfall S5 (bcrypt established on first create), Pitfall S8 (correct `json_encode(array)` pattern validated here), Pitfall S7 (SSL verification verified before any other calls).

**Research flag:** Standard patterns — PHP POST to REST API with form validation is well-documented. No additional research needed.

---

### Phase 3: Admin CRUD — Update, Delete, Block, Suspend

**Rationale:** Mutating and destructive operations are built after read/create is working and at least one real user exists in the table to test against. Block and suspend are PATCH operations on the `status` field — same endpoint as update, different payload. Grouping them here keeps all write operations in one verifiable phase.

**Delivers:** `api/users/update.php`, `api/users/delete.php`, full admin UI with edit modal, delete confirmation prompt, block button, and suspend-with-date-picker. All status transitions tested end-to-end including the unblock action (PATCH status back to 'active').

**Implements:** Three-state status model in its full form. Both setting and clearing `suspended_until`. Block vs. suspend distinction verified at the database level.

**Avoids:** Pitfall S3 (PATCH/DELETE require non-empty id filter — enforced with assertions at the top of both endpoint files), Pitfall S8 (PATCH body encoding and Content-Type header verified by checking response body contains updated row).

**Research flag:** Standard patterns — PostgREST PATCH/DELETE are fully documented. The only subtlety (filter required) is covered in PITFALLS.md. No additional research needed.

---

### Phase 4: Login Replacement

**Rationale:** This is the highest-risk change — it modifies the authentication path that all existing v1.0 functionality depends on. It is built last, after at least one user with a known password exists in Supabase (created in Phase 2). A rollback path exists: temporarily re-add `ADMIN_USER`/`ADMIN_PASS` constants to `config.php` and revert `login.php` to the v1.0 credential check.

**Delivers:** `api/login.php` modified to query Supabase by email, check status (blocked/suspended/active), call `password_verify()`, and set the PHP session. `index.html` minor update changing "שם משתמש" label to "אימייל" and updating the field name in the fetch body. Full end-to-end test: create user in admin → login with that user → send SMS → sign → receive confirmation SMS.

**Implements:** Status enforcement on login — blocked users denied always; suspended users denied while `today < suspended_until`; active users proceed to session set. Generic "Invalid credentials" error message for both wrong email and wrong password (prevents user enumeration).

**Avoids:** Pitfall S4 (service_role key confirmed working from Phase 1, no RLS surprises), all v1.0 pitfalls preserved (Micropay token isolation, session security, Hebrew SMS encoding — unchanged files).

**Research flag:** Low complexity — the login flow is completely specified in ARCHITECTURE.md with exact code including Hebrew error messages. No additional research needed.

---

### Phase Ordering Rationale

- **Schema-first is non-negotiable:** PHP payloads mirror column names and types. Changing a column name after endpoints are written requires updating every endpoint file. Design the schema completely in Phase 1 before any PHP is written.
- **The cURL helper must precede all endpoint files:** It is the single source of truth for the dual-header requirement (Pitfall S1). Every endpoint built without the helper risks silently omitting `apikey`.
- **Login replacement is last for two reasons:** (1) It modifies the live authentication path that all users depend on; (2) It requires a real Supabase user with a known password to test against — that user only exists after Phase 2 completes.
- **Admin CRUD is split across Phases 2 and 3:** Read/create (additive, low risk) before update/delete/block/suspend (mutating/destructive). This allows each group to be verified in isolation before introducing operations that can destroy data.

### Research Flags

All four phases have HIGH-confidence documentation. No phases require `/gsd:research-phase`.

Phases with standard, well-documented patterns (skip additional research):
- **Phase 1:** Supabase project setup, PostgREST connection, dual-header requirement — verified in official Supabase docs.
- **Phase 2:** Admin read and create CRUD — standard REST POST pattern; PHP validation and `password_hash` are PHP manual standards.
- **Phase 3:** Admin update, delete, and status management — PostgREST PATCH/DELETE with filter parameters fully documented.
- **Phase 4:** Login flow replacement — completely specified in ARCHITECTURE.md with exact PHP code; no unknowns.

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | PostgREST endpoints, required headers, and PHP cURL integration verified via official Supabase docs and PHP manual. The decision to reject phpsupabase and GoTrue Auth is MEDIUM — based on assessed risk rather than direct testing of the rejected alternatives. |
| Features | HIGH | Admin CRUD is a 20-year-old solved problem. Three-state status model verified against Google Workspace and Atlassian patterns. Feature ordering is deterministically driven by schema dependencies with no ambiguity. |
| Architecture | HIGH | Component boundaries, data flows, file structure, and build order are fully specified with exact code in ARCHITECTURE.md. Custom-table-over-GoTrue decision has explicit trade-off analysis. RLS behaviour with service_role confirmed via community discussion in addition to official docs. |
| Pitfalls | MEDIUM-HIGH | v1.0 pitfalls (PHP/cPanel) are HIGH — established patterns. v2.0 Supabase pitfalls are MEDIUM-HIGH — core issues (dual header, key exposure, filter required) verified via official docs and confirmed community discussions. The PATCH silent-failure mechanism verified via a widely-referenced Supabase issue thread. |

**Overall confidence:** HIGH

### Gaps to Address

- **bcrypt cost factor discrepancy:** STACK.md recommends `PASSWORD_DEFAULT` (bcrypt cost 10); ARCHITECTURE.md uses `PASSWORD_BCRYPT` (cost 12, the PHP 8.4 new default). During Phase 1 setup, confirm the cPanel PHP version. Use `PASSWORD_DEFAULT` for maximum forward compatibility — PHP manages the cost factor automatically on future upgrades.

- **cPanel PHP version:** Research assumes PHP 7.4+. Verify the actual PHP version via cPanel → Select PHP Version before writing any code. Both 7.4 and 8.x are fully supported; the difference only affects the default bcrypt cost.

- **`ban_duration` GoTrue mechanism:** Researched but not used in the final architecture (custom table chosen instead of GoTrue Auth). No action needed — this gap is moot for v2.0.

- **Supabase free tier limits:** Free tier provides 500MB storage and unlimited API calls. Sufficient for < 1000 users. No action required unless the project grows beyond expected scale.

---

## Sources

### Primary (HIGH confidence)

- Supabase REST API docs: https://supabase.com/docs/guides/api — base URLs, CRUD methods, filter syntax
- Supabase API Keys docs: https://supabase.com/docs/guides/api/api-keys — dual header requirement (apikey + Authorization Bearer)
- Supabase GoTrue self-hosting auth API: https://supabase.com/docs/reference/self-hosting-auth/introduction — admin endpoint patterns (assessed and rejected for this project)
- Supabase auth architecture: https://supabase.com/docs/guides/auth/architecture — GoTrue vs PostgREST separation (informed custom table decision)
- PostgREST v12 Prefer header: https://docs.postgrest.org/en/v12/references/api/preferences.html — `return=representation`
- PHP `password_hash` docs: https://www.php.net/manual/en/function.password-hash.php — bcrypt, PASSWORD_DEFAULT
- PROJECT.md — project scope, constraints, field list, and explicit decisions (no admin auth, custom table preferred)

### Secondary (MEDIUM confidence)

- PHP Watch bcrypt cost: https://php.watch/versions/8.4/password_hash-bcrypt-cost-increase — PHP 8.4 default cost change to 12
- PostgREST dual-header confirmation: https://github.com/supabase-community/postgrest-go/issues/29 — community thread confirming both headers required (matches official docs)
- Google Workspace user status model: https://knowledge.workspace.google.com/admin/users/view-the-status-of-a-user-account — active/suspended pattern validation
- Supabase admin API (JS reference, used to infer HTTP layer): https://supabase.com/docs/reference/javascript/admin-api

### Tertiary (feature UX conventions)

- Refine admin panel guide: https://refine.dev/blog/what-is-an-admin-panel/ — admin panel feature taxonomy
- Authgear login UX guide: https://www.authgear.com/post/login-signup-ux-guide — validation and error messaging conventions
- phpsupabase GitHub: https://github.com/rafaelwendel/phpsupabase — assessed and rejected (Composer-only, unofficial, 2021 vintage)

---

*Research completed: 2026-02-28*
*Ready for roadmap: yes*
