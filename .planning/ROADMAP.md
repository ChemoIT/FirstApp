# Roadmap: FirstApp — Signature Dispatch System

## Milestones

- SHIPPED **v1.0 Signature Dispatch** — Phases 1-2 (shipped 2026-02-28)
- IN PROGRESS **v2.0 User Management** — Phases 3-6

## Phases

<details>
<summary>v1.0 Signature Dispatch (Phases 1-2) — SHIPPED 2026-02-28</summary>

- [x] Phase 1: Foundation, Auth, and Dispatch (3/3 plans) — completed 2026-02-28
- [x] Phase 2: Signature Capture and Confirmation (2/2 plans) — completed 2026-02-28

</details>

### v2.0 User Management (In Progress)

**Milestone Goal:** Replace hardcoded credentials with a Supabase-backed user database. Admin can create, view, edit, delete, block, and suspend users. Login validates against the database.

- [x] **Phase 3: Database Foundation** — Schema and PHP connection layer in place before any other code — completed 2026-02-28
- [x] **Phase 4: Admin Read and Create** — Admin can view the user list and add new users end to end — completed 2026-02-28
- [ ] **Phase 5: Admin Update, Delete, Block, Suspend** — Admin can mutate and remove users with full status control
- [ ] **Phase 6: Login Replacement** — Email+password login from Supabase replaces hardcoded credentials

---

### Phase 3: Database Foundation

**Goal:** Supabase is connected and PHP can talk to it — the foundation every subsequent phase depends on.

**Depends on:** Phases 1-2 (v1.0 complete)

**Requirements:** DB-01, DB-02, DB-03

**Success Criteria** (what must be TRUE):
1. The `public.users` table exists in Supabase with all required columns (first_name, last_name, id_number, phone, gender, foreign_worker, email, password_hash, status, suspended_until, created_at, updated_at) — verified in Supabase dashboard
2. `api/config.php` holds the Project URL and service_role key and does NOT appear in `git status` (confirmed by .gitignore)
3. A test GET request via `api/supabase.php` returns an empty JSON array `[]` — proving the dual-header connection works

**Plans:** 1 plan

Plans:
- [x] 03-01-PLAN.md — Create Supabase schema, credentials, and PHP cURL helper — completed 2026-02-28

---

### Phase 4: Admin Read and Create

**Goal:** Admin can open the admin page, see the user list, and add new users — the full read-and-write data flow verified end to end.

**Depends on:** Phase 3 (Supabase connection working, users table exists)

**Requirements:** ADMIN-01, ADMIN-02, ADMIN-03, ADMIN-04, ADMIN-05, ADMIN-06, ADMIN-07, ADMIN-12

**Success Criteria** (what must be TRUE):
1. Admin can navigate to `/FirstApp/admin.php` without logging in and see the user management page with Hebrew labels and RTL layout
2. Admin can fill in the create-user form and submit — the new user appears in the table and the password is stored as a bcrypt hash in Supabase (verified in dashboard)
3. Admin sees an error when email format is invalid (frontend and backend both block the submission)
4. Admin sees an error when password is fewer than 8 characters (frontend and backend both block the submission)
5. Admin can click the password generator button and a random secure password is inserted into the password field
6. Admin can type in the search box and the user table filters live by name, email, or ID without a page reload

**Plans:** 2 plans

Plans:
- [x] 04-01-PLAN.md — Build list and create API endpoints (`api/users/list.php`, `api/users/create.php`) — completed 2026-02-28
- [x] 04-02-PLAN.md — Build `admin.php` UI with user table, search, and create form — completed 2026-02-28

---

### Phase 5: Admin Update, Delete, Block, Suspend

**Goal:** Admin has full control over existing users — editing details, removing users, and setting status — with all destructive operations protected against accidents.

**Depends on:** Phase 4 (at least one real user exists in Supabase to test against)

**Requirements:** ADMIN-08, ADMIN-09, ADMIN-10, ADMIN-11

**Success Criteria** (what must be TRUE):
1. Admin can click Edit on a user, change a field in the modal, save, and see the updated value in the table immediately
2. Admin can click Delete, confirm the prompt, and the user disappears from the table and from the Supabase dashboard
3. Admin can click Block on a user and the user's status shows "blocked" in the table — permanently, with no expiry
4. Admin can click Suspend, enter a future date, and the user's status shows "suspended" with the correct end date stored in Supabase

**Plans:** TBD

Plans:
- [ ] 05-01: Build update and delete API endpoints (`api/users/update.php`, `api/users/delete.php`)
- [ ] 05-02: Add edit modal, delete confirmation, block button, and suspend-with-date-picker to `admin.php`

---

### Phase 6: Login Replacement

**Goal:** The app no longer uses hardcoded credentials — every login is validated against the Supabase users table with status enforcement.

**Depends on:** Phase 4 (a real user with a known bcrypt password exists in Supabase to log in with)

**Requirements:** AUTH-01, AUTH-02, AUTH-03, AUTH-04, AUTH-05

**Success Criteria** (what must be TRUE):
1. The login page shows an email field instead of a username field, with Hebrew labels
2. A user with status "active" can log in with their Supabase email and password and reach the dispatch page — the PHP session persists across a page refresh
3. A user with status "blocked" is refused login with a generic Hebrew error message
4. A user with status "suspended" and a future `suspended_until` date is refused login; once that date passes, the same credentials succeed
5. The hardcoded sharonb/1532 credentials no longer work — they are removed from the codebase

**Plans:** TBD

Plans:
- [ ] 06-01: Modify `api/login.php` and update `index.html` login form

---

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Foundation, Auth, and Dispatch | v1.0 | 3/3 | Complete | 2026-02-28 |
| 2. Signature Capture and Confirmation | v1.0 | 2/2 | Complete | 2026-02-28 |
| 3. Database Foundation | v2.0 | 1/1 | Complete | 2026-02-28 |
| 4. Admin Read and Create | v2.0 | 2/2 | Complete | 2026-02-28 |
| 5. Admin Update, Delete, Block, Suspend | v2.0 | 0/2 | Not started | - |
| 6. Login Replacement | v2.0 | 0/1 | Not started | - |
