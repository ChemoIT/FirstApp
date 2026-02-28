# Feature Landscape

**Domain:** User Management Admin Panel — CRUD interface for a signature dispatch system
**Project:** FirstApp v2.0 (Learning Project — subsequent milestone)
**Researched:** 2026-02-28
**Overall Confidence:** HIGH — Admin panel CRUD is a mature, well-understood domain with stable conventions.

---

## Framing: Milestone Scope

This document covers ONLY the new features added in v2.0. The v1.0 loop (login, SMS dispatch, canvas, PNG save, confirmation SMS) is already shipped and working. Do not rebuild or re-document what exists.

v2.0 adds exactly three concerns:
1. **User table in Supabase** — stored users with status management
2. **Admin page** — CRUD interface for that table (no auth required — learning project)
3. **Login replacement** — email + password checked against Supabase instead of hardcoded credentials

---

## Table Stakes

Features that must exist for the admin panel to function. Missing any of these = the feature set is broken or incomplete.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| User list table | The admin page is the user table — without it there is nothing | LOW | HTML `<table>` rendered from PHP Supabase query. Columns: name, ID number, phone, gender, status, actions. |
| Create user (modal or inline form) | CRUD without Create is just Read-Update-Delete | LOW | Modal or dedicated form row. All 8 fields required. |
| Edit user | User data changes over time (phone, status, password) | LOW | Modal pre-populated from row data. Same 8-field form. |
| Delete user | Remove users who are no longer needed | LOW | Confirmation prompt required — accidental deletes are the #1 user complaint. |
| User status: Active / Blocked / Suspended | The point of user management is controlling access | MEDIUM | Three states, not two. See status section below. |
| Block user | Permanent access revocation — not deleted but cannot log in | LOW | Status column set to 'blocked'. No expiry logic needed. |
| Suspend user with end date | Temporary revocation — time-bounded block | MEDIUM | Status = 'suspended', suspended_until = date. Login check: if suspended AND today < suspended_until → deny. |
| Login with Supabase credentials | Replaces hardcoded sharonb/1532 — the trigger for this entire milestone | MEDIUM | PHP: POST email+password → Supabase REST → match row → PHP session. Password must be hashed (bcrypt). |
| Password minimum 8 characters | Baseline security — universally expected | LOW | Frontend validation + PHP backend validation. Both layers required. |
| Email format validation | Email is the username — invalid format breaks login | LOW | JS regex or `type="email"` HTML attribute + PHP filter_var() on backend. |
| Search / filter on user table | Without search, a table with 20+ users is unusable | LOW | Client-side JS filter on table rows (no server round-trip needed at this scale). Filter on name, ID, phone. |
| Hebrew labels and RTL layout | All user-facing UI is Hebrew — established in v1.0 | LOW | Carry forward RTL conventions from v1.0 login and dispatch pages. |
| Confirmation before destructive actions | Delete and block must prompt before executing | LOW | `confirm()` dialog or modal. Prevents fat-finger damage. |

---

## User Status States — Detail

This is the most important decision in the schema design. Three states are needed:

| Status | Meaning | Login Allowed? | How Admin Sets It | Auto-Clears? |
|--------|---------|----------------|------------------|--------------|
| `active` | Normal account | Yes | Explicitly or by default on create | No |
| `blocked` | Permanently revoked | No | Block button in actions column | No — admin must manually unblock |
| `suspended` | Temporarily revoked | No (until expiry date) | Suspend button + date picker | Not auto — login check reads the date |

**Login check logic (PHP):**
```
status == 'blocked' → deny always
status == 'suspended' AND today < suspended_until → deny
status == 'suspended' AND today >= suspended_until → allow (treat as active)
status == 'active' → allow
```

The "suspended_until" date comparison happens server-side in PHP on every login attempt. The DB is not auto-updated — login handles the expired suspension passively. This is simpler and correct for this project scale.

---

## Differentiators

Features that improve usability or teach important concepts but are not required for the admin panel to function. Reasonable as stretch goals.

| Feature | Value Proposition | Complexity | What It Teaches |
|---------|-------------------|------------|-----------------|
| Password generator button | Saves admin from inventing passwords; ensures quality | LOW | JS Math.random(), character pools, clipboard API |
| Show/hide password toggle | Admin can verify what they typed; avoids typos | LOW | JS input type toggle between `password` and `text` |
| Password strength indicator | Visual feedback during password entry | LOW | JS string analysis, CSS dynamic classes |
| Filter by status (Active / Blocked / Suspended) | Useful when reviewing all suspended users | LOW | JS filter on data-attribute of table rows |
| Sort table by column (name, ID, etc.) | Standard table UX expectation | MEDIUM | JS sort on array of row data, DOM rebuild |
| Inline status toggle (click cell to change) | Faster than open-edit-save for status changes | MEDIUM | JS click handler → PHP PATCH to Supabase; risk: accidental clicks |
| User count summary | "12 active, 2 suspended, 1 blocked" header line | LOW | PHP count() per status group from Supabase query |
| Toast / snackbar feedback on save | Better than alert() for CRUD confirmations | LOW | JS div with fade-out animation; replaces browser alert() |
| Pagination | Needed when user list exceeds ~50 rows | LOW | Client-side JS slice() on table array; no DB pagination needed at this scale |
| "Copy to clipboard" on generated password | Convenience — admin pastes password to user over phone | LOW | navigator.clipboard.writeText() |

**Recommended for this milestone (in order):**
1. Password generator button — directly required by spec, teaches JS string manipulation
2. Show/hide password toggle — 2 lines of JS, eliminates a real pain point
3. Toast feedback instead of alert() — teaches JS event timing, much better UX
4. Filter by status — directly useful for a fleet manager managing many workers

---

## Anti-Features

Features to explicitly NOT build in v2.0. Each one is either out of scope, creates disproportionate complexity, or conflicts with the learning-project constraints.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| Admin page authentication | Spec explicitly says no auth on admin page — acceptable for learning project | Leave open. If needed in v3.0, wrap in PHP session like dispatch page. |
| Role-based access control (RBAC) | Multiple permission levels require a roles table, join queries, middleware — a full sub-system | Single admin, single role. Not needed. |
| Bulk user import (CSV) | File parsing, validation per row, partial failure handling — high complexity | Add one user at a time via the form. |
| Email sending for account notifications | SMTP config, email templates, deliverability — a separate system | Admin tells users their credentials by phone/WhatsApp (Sharon's context). |
| Password reset flow | Requires token generation, email/SMS delivery, expiry — a multi-step flow | Admin resets password via Edit form and communicates it manually. |
| Audit log / history of status changes | Requires separate log table, UI to view it — adds schema complexity | Not needed for this scale. |
| Pagination with server-side Supabase queries | Range queries add URL params, server round-trips — overkill for < 200 users | Client-side JS filter/sort. |
| User profile photos / avatars | File upload, storage, display — unrelated to dispatch system | Not needed. |
| Two-factor authentication | TOTP setup, backup codes, recovery — enterprise-grade feature | Out of scope for learning project. |
| Real-time updates (auto-refresh table) | WebSockets or polling — complex async, no value for single-admin panel | Manual page refresh or reload after each action. |
| Soft delete with restore | Deleted users go to trash, can be restored — adds status complexity | Hard delete. Data is not precious at this scale. |
| "Forgot password" for the login page | SMS or email delivery of reset token required | Admin manually resets via admin page. |

---

## Feature Dependencies

```
# Schema must exist first
Supabase users table (schema: id, first_name, last_name, id_number,
                      phone, gender, foreign_worker, email,
                      password_hash, status, suspended_until, created_at)
    └── User list table (READ from Supabase via PHP cURL)
          └── Create user (INSERT via PHP cURL)
          └── Edit user (UPDATE via PHP cURL)
          └── Delete user (DELETE via PHP cURL)
          └── Block user (UPDATE status='blocked' via PHP cURL)
          └── Suspend user (UPDATE status='suspended' + suspended_until date)

# Login depends on schema AND password hashing convention
Supabase users table
    └── Login with Supabase credentials
          └── PHP password_verify() -- requires passwords stored as bcrypt hash
          └── Status check (active / blocked / suspended-until)

# These are independent of each other but depend on the form existing
Create user form
    └── Email format validation (JS + PHP backend)
    └── Password min 8 chars (JS + PHP backend)
    └── Password generator button (JS only)
    └── Show/hide password toggle (JS only)

# Search depends on the table rendering first
User list table
    └── Client-side search/filter (JS on rendered rows)
    └── Filter by status (JS on data-status attribute)
```

### Dependency Notes

- **Supabase schema must be created before any PHP code is written.** The column names and types in the DB dictate the PHP cURL payloads and the HTML form field names. Design schema first.
- **Password hashing convention must be decided at Create time.** If passwords are stored as bcrypt via PHP `password_hash()`, then login uses PHP `password_verify()`. If stored plaintext (bad), login is a simple string compare. Choose bcrypt — it teaches the right lesson and is the correct practice.
- **Status column design affects login logic.** The three-state enum (`active`, `blocked`, `suspended`) plus the `suspended_until` date column must be in the schema from the start. Adding it later requires an ALTER TABLE and a PHP login rewrite.
- **Login replacement depends on the users table having at least one row.** Create at least one user via admin page before switching login logic. Do not cut over the login until the admin page works.

---

## MVP Definition

### Launch With (v2.0 core)

Minimum to make the milestone complete. Every item below is blocking.

- [ ] Supabase users table created (schema with all fields including status + suspended_until)
- [ ] Admin page renders user list from Supabase via PHP cURL
- [ ] Create user form (all 8 fields, email validation, password min 8 chars, password generator)
- [ ] Edit user (pre-populated modal, saves changes to Supabase)
- [ ] Delete user (with confirm() prompt)
- [ ] Block user (sets status to 'blocked')
- [ ] Suspend user with end date (sets status + suspended_until)
- [ ] Client-side search on user table (filter by name, ID, phone)
- [ ] Login using email + password from Supabase (with status check blocking blocked/suspended users)
- [ ] Hebrew labels and RTL layout throughout

### Add After Core Works (v2.x polish)

- [ ] Show/hide password toggle — 5 minutes of work, eliminates real pain point
- [ ] Toast/snackbar instead of alert() for feedback — teaches JS animation
- [ ] Filter table by status — directly useful for a real fleet manager

### Future Consideration (v3.0+)

- [ ] Admin authentication — wrap admin.php in session check, same pattern as dispatch.php
- [ ] Sort table columns — click column header to sort ascending/descending
- [ ] Pagination — only needed when user list exceeds 50+ rows

---

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Supabase users table (schema) | HIGH | LOW | P1 |
| User list table (read) | HIGH | LOW | P1 |
| Create user | HIGH | LOW | P1 |
| Edit user | HIGH | LOW | P1 |
| Delete user | HIGH | LOW | P1 |
| Block user | HIGH | LOW | P1 |
| Suspend until date | HIGH | MEDIUM | P1 |
| Login with Supabase | HIGH | MEDIUM | P1 |
| Email validation | HIGH | LOW | P1 |
| Password min 8 chars | HIGH | LOW | P1 |
| Client-side search | HIGH | LOW | P1 |
| Password generator button | MEDIUM | LOW | P2 |
| Show/hide password | MEDIUM | LOW | P2 |
| Toast feedback | MEDIUM | LOW | P2 |
| Filter by status | MEDIUM | LOW | P2 |
| Sort table columns | LOW | MEDIUM | P3 |
| Admin page auth | LOW | LOW | P3 |

**Priority key:**
- P1: Required for v2.0 milestone completion
- P2: Should have — add before calling milestone done if time allows
- P3: Nice to have — future consideration

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Table stakes | HIGH | Admin CRUD is a 20-year-old solved problem; no ambiguity |
| Status states (3-state model) | HIGH | Active/blocked/suspended is the canonical pattern (Google Workspace, Atlassian, AD all use it) |
| Feature ordering | HIGH | Schema-first dependency is deterministic |
| Anti-features | HIGH | Each exclusion has a clear rationale tied to project constraints |
| PHP + Supabase REST pattern | MEDIUM | Supabase REST API is well-documented; PHP cURL for REST is standard. No direct verification via Context7 for this combination. |
| Password hashing (bcrypt) | HIGH | PHP `password_hash()` / `password_verify()` is the PHP standard since PHP 5.5; MDN and PHP docs confirm |

---

## Sources

- PROJECT.md (primary source of truth for scope, fields, and constraints)
- [What is an Admin Panel? The Complete Guide for 2026 | Refine](https://refine.dev/blog/what-is-an-admin-panel/) — admin panel feature taxonomy
- [CRUD Beyond Grids: Modern UI Patterns | CopyProgramming](https://copyprogramming.com/howto/what-is-the-best-ux-to-let-user-perform-crud-operations) — CRUD UX conventions
- [View the status of a user account | Google Workspace Help](https://knowledge.workspace.google.com/admin/users/view-the-status-of-a-user-account) — Active/Suspended/Invited status pattern
- [3 Ways to Suspend Users in Google Workspace | Torii](https://www.toriihq.com/articles/how-to-suspend-user-google-workspace) — suspension semantics (access removed, data preserved)
- [Password Pattern | UX Patterns for Devs](https://uxpatterns.dev/en/patterns/forms/password) — password field UX best practices
- [Login & Signup UX: The 2025 Guide | Authgear](https://www.authgear.com/post/login-signup-ux-guide) — validation and error messaging conventions
- [How To Create Random Strong Password Generator | Medium/sharathchandark](https://medium.com/@sharathchandark/how-to-create-random-strong-password-generator-using-html-css-javascript-268ac231749d) — JS password generator pattern
- PHP documentation: `password_hash()`, `password_verify()`, `filter_var(FILTER_VALIDATE_EMAIL)` — standard PHP patterns
- Training data confidence: Admin CRUD UX conventions are stable since 2010+; status management patterns verified against Google Workspace and Atlassian documentation

---

*Feature research for: User Management Admin Panel (FirstApp v2.0)*
*Researched: 2026-02-28*
