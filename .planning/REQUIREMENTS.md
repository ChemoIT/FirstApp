# Requirements: FirstApp v2.0

**Defined:** 2026-02-28
**Core Value:** A user can dispatch a signature request via SMS and receive a signed document back — the full send-sign-confirm loop must work end to end.

## v2.0 Requirements

Requirements for v2.0 milestone. Each maps to roadmap phases.

### Database Foundation (DB)

- [ ] **DB-01**: Supabase PostgreSQL `users` table created with all required columns (first_name, last_name, id_number, phone, gender, foreign_worker, email, password_hash, status, suspended_until, created_at, updated_at)
- [ ] **DB-02**: PHP cURL helper function for Supabase REST API calls (shared across all endpoints, dual-header pattern)
- [ ] **DB-03**: config.php with Supabase credentials (Project URL, service_role key) added to .gitignore

### Admin Page (ADMIN)

- [ ] **ADMIN-01**: Admin page accessible at `/FirstApp/admin.php` without login
- [ ] **ADMIN-02**: User creation form with fields: first name, last name, ID number, phone (digits only), gender (combo box male/female), foreign worker (checkbox), email, password
- [ ] **ADMIN-03**: Email format validation (frontend + backend)
- [ ] **ADMIN-04**: Password minimum 8 characters validation (frontend + backend)
- [ ] **ADMIN-05**: Password generator button that creates a random secure password
- [ ] **ADMIN-06**: User table view displaying all users with key columns
- [ ] **ADMIN-07**: Search/filter users by name, email, or ID
- [ ] **ADMIN-08**: Edit user details (modal or inline)
- [ ] **ADMIN-09**: Delete user with confirmation prompt
- [ ] **ADMIN-10**: Block user (permanent — status set to 'blocked')
- [ ] **ADMIN-11**: Suspend user until specific date (status 'suspended' + suspended_until date)
- [ ] **ADMIN-12**: Hebrew labels and RTL layout throughout admin page

### Authentication (AUTH)

- [ ] **AUTH-01**: Login page with email + password fields (replaces hardcoded sharonb)
- [ ] **AUTH-02**: Authentication against Supabase users table using bcrypt (password_verify)
- [ ] **AUTH-03**: Blocked/suspended users cannot login (status check on login)
- [ ] **AUTH-04**: Session persistence after login using existing PHP sessions
- [ ] **AUTH-05**: Remove hardcoded sharonb credentials from codebase

## v3.0 Requirements (Future)

Deferred to future release. Tracked but not in current roadmap.

### Admin Security

- **ASEC-01**: Admin page login/authentication (e.g., .htaccess basic auth)
- **ASEC-02**: Audit log of admin actions (who created/edited/deleted which user)

### User Self-Service

- **SELF-01**: User can change their own password
- **SELF-02**: Password reset via email link

### Enhanced Features

- **ENH-01**: User roles (admin, regular user)
- **ENH-02**: Profile photo upload
- **ENH-03**: Pagination for large user tables

## Out of Scope

| Feature | Reason |
|---------|--------|
| Supabase Auth (GoTrue) | Custom table gives full SQL control and matches PHP session pattern — simpler for learning |
| Supabase JS SDK | All API calls go through PHP server-side to protect service_role key |
| RLS (Row Level Security) | Admin page has no auth; service_role bypasses RLS anyway |
| User roles/permissions | Single admin level sufficient for learning project |
| Email verification on signup | Admin creates users — no self-registration |
| Two-factor authentication | Overkill for learning project |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| DB-01 | — | Pending |
| DB-02 | — | Pending |
| DB-03 | — | Pending |
| ADMIN-01 | — | Pending |
| ADMIN-02 | — | Pending |
| ADMIN-03 | — | Pending |
| ADMIN-04 | — | Pending |
| ADMIN-05 | — | Pending |
| ADMIN-06 | — | Pending |
| ADMIN-07 | — | Pending |
| ADMIN-08 | — | Pending |
| ADMIN-09 | — | Pending |
| ADMIN-10 | — | Pending |
| ADMIN-11 | — | Pending |
| ADMIN-12 | — | Pending |
| AUTH-01 | — | Pending |
| AUTH-02 | — | Pending |
| AUTH-03 | — | Pending |
| AUTH-04 | — | Pending |
| AUTH-05 | — | Pending |

**Coverage:**
- v2.0 requirements: 20 total
- Mapped to phases: 0
- Unmapped: 20 ⚠️

---
*Requirements defined: 2026-02-28*
*Last updated: 2026-02-28 after initial definition*
