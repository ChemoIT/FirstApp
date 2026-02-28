# Phase 5: Admin Update, Delete, Block, Suspend — Research

**Researched:** 2026-02-28
**Domain:** PostgREST PATCH/DELETE via PHP cURL, Bootstrap 5.3 Modal (RTL), vanilla JS ES5 modal orchestration, date input with PHP future-date validation
**Confidence:** HIGH

---

## Summary

Phase 5 extends the existing `admin.php` + `api/users/` architecture with four admin actions: edit (PATCH), delete (DELETE), block (PATCH status='blocked'), and suspend (PATCH status='suspended' + suspended_until date). The stack is identical to Phase 4 — PHP cURL calling Supabase REST API, Bootstrap 5.3 RTL, vanilla JS ES5 `.then()` chains. No new dependencies are introduced.

The two API endpoints are `api/users/update.php` (PATCH) and `api/users/delete.php` (DELETE). Both use `supabase_request()` from Phase 3, following the exact same pattern already established. PATCH requires the `?id=eq.{id}` PostgREST filter and the body containing only the fields to change. DELETE requires `?id=eq.{id}` only. Supabase returns HTTP **200** for a successful PATCH (with or without `Prefer: return=representation`) and HTTP **204 No Content** for a successful DELETE.

The UI additions to `admin.php` are: an Edit modal (Bootstrap 5.3 `modal` component already loaded via Bootstrap JS bundle in Phase 4), action buttons per table row (Edit, Delete, Block, Suspend), a delete confirmation using `window.confirm()`, and a Suspend modal with an `<input type="date">` for picking the end date. All modals are opened programmatically with `new bootstrap.Modal()` or `bootstrap.Modal.getOrCreateInstance()`. The table row must carry a `data-user-id` attribute so event delegation can identify which user to act on.

**Primary recommendation:** Build API endpoints first (05-01), then add UI actions to `admin.php` (05-02). The PATCH endpoint handles all three write operations (edit fields, block, suspend) by accepting whatever fields are sent and applying them. A single endpoint keeps the surface area small and consistent with the existing `create.php` pattern.

---

## Standard Stack

### Core

| Technology | Version | Purpose | Why Standard |
|------------|---------|---------|--------------|
| PHP cURL + `supabase_request()` | Phase 3 (existing) | PATCH and DELETE to Supabase REST API | Already proven; no new library needed |
| PostgREST PATCH via `?id=eq.{id}` | PostgREST 12.x | Update a single row by primary key | Standard PostgREST horizontal filter pattern |
| PostgREST DELETE via `?id=eq.{id}` | PostgREST 12.x | Delete a single row by primary key | Same filter syntax as PATCH |
| Bootstrap 5.3 Modal (JS bundle) | 5.3.8 (loaded in Phase 4) | Edit modal, Suspend modal dialogs | Bootstrap JS bundle already in admin.php; no re-load needed |
| `window.confirm()` | Browser built-in | Delete confirmation prompt | Zero code, zero library; sufficient for an internal admin page |
| `<input type="date">` | HTML5 | Suspend end-date picker | Browser-native; always submits ISO 8601 YYYY-MM-DD value |
| Vanilla JS ES5 `.then()` chains | ES5 | All fetch calls in admin.php | Consistent with every existing JS in the project |

### Supporting

| Technology | Version | Purpose | When to Use |
|------------|---------|---------|-------------|
| `data-user-id` HTML attributes on `<tr>` | HTML5 | Carry user ID per row for event delegation | Set on every `<tr>` in `loadUsers()` so button handlers can read which row was clicked |
| `data-*` attributes on Edit/Suspend buttons | HTML5 | Pre-populate modals with user's current values | Cleaner than scanning sibling DOM nodes; avoids a second API call |
| `input[type="date"] min` attribute | HTML5 | Prevent picking today or a past date in Suspend picker | Client-side guard; PHP validates server-side too |
| `bootstrap.Modal.getOrCreateInstance()` | Bootstrap 5.3 | Open a modal from JS without creating duplicate instances | Safer than `new bootstrap.Modal()` if the modal was already instantiated |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `window.confirm()` for delete (recommended) | Custom confirmation modal | Custom modal is 30+ lines of HTML + JS; `window.confirm()` is two characters. For an internal admin tool, the native dialog is sufficient. |
| Single `update.php` for all three write ops (recommended) | Separate `block.php` / `suspend.php` | Separate files multiply the surface area with no benefit; one PATCH endpoint handles all writes by accepting whatever fields are in the body |
| Edit modal for field editing (recommended) | Inline table editing | Inline editing requires contenteditable or input injection into table cells — complex and fragile. A modal is Bootstrap-native, cleaner. |
| `<input type="date">` (recommended) | Third-party date picker library | No new CDN dependency; the native date picker is RTL-compatible and accurate |

**No new npm. No new Composer. No new CDN.** Bootstrap JS bundle (loaded in Phase 4) already covers modals.

---

## Architecture Patterns

### Recommended Project Structure (Phase 5 additions)

```
FirstApp/
├── admin.php                        <- MODIFIED: add action column, modals, button handlers
├── api/
│   └── users/
│       ├── list.php                 <- Unchanged
│       ├── create.php               <- Unchanged
│       ├── update.php               <- NEW: PATCH endpoint (edit fields, block, suspend)
│       └── delete.php               <- NEW: DELETE endpoint
```

### Pattern 1: PostgREST PATCH — Update by ID

**What:** Send a PATCH request to `/users?id=eq.{id}` with only the fields to change as the JSON body. PostgREST applies a partial update (like SQL `UPDATE ... SET col=val WHERE id = X`).

**HTTP status codes (MEDIUM confidence — verified via Supabase community discussions):**
- Default (no Prefer header): **200 OK** with an empty array `[]` as body
- With `Prefer: return=representation`: **200 OK** with updated row array as body

**When to use:** ADMIN-08 (edit fields), ADMIN-10 (block — set `status='blocked'`), ADMIN-11 (suspend — set `status='suspended'` + `suspended_until='...'`).

```php
// Source: PostgREST v12 docs — https://docs.postgrest.org/en/v12/references/api/tables_views.html
// + supabase.php pattern (Phase 3)

// Edit: update name, phone, email, gender, foreign_worker
$result = supabase_request(
    'PATCH',
    '/users?id=eq.' . intval($userId),
    [
        'first_name' => $firstName,
        'last_name'  => $lastName,
        'phone'      => $phone,
        'email'      => $email,
        'gender'     => $gender,
        'foreign_worker' => $foreignWorker,
    ]
);
// Success: $result['http_code'] === 200

// Block: set status only
$result = supabase_request(
    'PATCH',
    '/users?id=eq.' . intval($userId),
    ['status' => 'blocked']
);

// Suspend: set status + suspended_until
$result = supabase_request(
    'PATCH',
    '/users?id=eq.' . intval($userId),
    ['status' => 'suspended', 'suspended_until' => $suspendedUntil]
    // $suspendedUntil is ISO 8601 string: '2026-06-30' or '2026-06-30T00:00:00+00:00'
);
```

### Pattern 2: PostgREST DELETE — Delete by ID

**What:** Send a DELETE request to `/users?id=eq.{id}`. No body. PostgREST deletes the matching row.

**HTTP status codes (HIGH confidence — multiple sources agree):**
- Default: **204 No Content** — success, no body
- With `Prefer: return=representation`: **200 OK** with deleted row as body (rarely needed)

**When to use:** ADMIN-09 (delete user).

```php
// Source: PostgREST v12 docs — https://docs.postgrest.org/en/v12/references/api/tables_views.html
// supabase_request() signature: method, path, body=null, prefer_rep=false

$result = supabase_request(
    'DELETE',
    '/users?id=eq.' . intval($userId)
    // No body (third arg omitted = null)
    // No Prefer: return=representation needed — we don't need the deleted row back
);
// Success: $result['http_code'] === 204
// $result['data'] will be null (204 = No Content)
```

### Pattern 3: PHP update.php Endpoint Structure

**What:** POST-based wrapper endpoint (admin.php sends POST, not PATCH, because browsers and fetch can do PATCH but this stays consistent with the existing project pattern where the client always sends JSON POST and the PHP decides the Supabase verb).

**Actually:** Use the HTTP method that matches the operation semantics. Since the existing `supabase_request()` already supports PATCH and DELETE, the PHP endpoint can accept POST from the browser and translate it to the correct Supabase verb internally. This avoids exposing PATCH/DELETE to the browser (some proxy/hosting configs block non-standard verbs).

**Alternative verified approach (confirmed working on cPanel):** The existing project already uses `fetch()` with `method: 'POST'` for all admin API calls. Keep that pattern — `update.php` accepts POST, calls `supabase_request('PATCH', ...)` internally.

```php
<?php
/**
 * api/users/update.php — Updates a user's fields, or sets status (block/suspend)
 *
 * POST request only. Expects JSON body:
 *   { "id": 5, "action": "edit|block|suspend",
 *     ... action-specific fields ... }
 *
 * Actions:
 *   edit:    first_name, last_name, phone, email, gender, foreign_worker
 *   block:   no extra fields needed
 *   suspend: "suspended_until" (ISO 8601 date string, future date)
 *
 * Returns HTTP 200 with {"ok": true} on success
 * Returns HTTP 422 with {"ok": false, "message": "..."} on validation failure
 * Returns HTTP 500 with {"ok": false, "message": "..."} on Supabase error
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../supabase.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$userId = intval($body['id'] ?? 0);
$action = $body['action'] ?? '';

if ($userId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'מזהה משתמש לא תקין'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Build the PATCH payload based on action
$patch = [];

if ($action === 'edit') {
    $firstName     = trim($body['first_name']    ?? '');
    $lastName      = trim($body['last_name']     ?? '');
    $phone         = trim($body['phone']         ?? '');
    $email         = trim($body['email']         ?? '');
    $gender        = $body['gender']             ?? '';
    $foreignWorker = (bool)($body['foreign_worker'] ?? false);

    if (!$firstName || !$lastName || !$phone || !$email || !$gender) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'כל השדות חובה'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'כתובת אימייל לא תקינה'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!preg_match('/^\d+$/', $phone)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'מספר טלפון חייב לכלול ספרות בלבד'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!in_array($gender, ['male', 'female'], true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'ערך מגדר לא תקין'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $patch = ['first_name' => $firstName, 'last_name' => $lastName,
              'phone' => $phone, 'email' => $email,
              'gender' => $gender, 'foreign_worker' => $foreignWorker];

} elseif ($action === 'block') {
    $patch = ['status' => 'blocked', 'suspended_until' => null];

} elseif ($action === 'suspend') {
    $suspendedUntil = trim($body['suspended_until'] ?? '');
    // Validate date format YYYY-MM-DD using DateTime
    $date = DateTime::createFromFormat('Y-m-d', $suspendedUntil);
    if (!$date || $date->format('Y-m-d') !== $suspendedUntil) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'תאריך לא תקין'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Must be a future date
    if ($date <= new DateTime('today')) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'תאריך ההשעיה חייב להיות בעתיד'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $patch = ['status' => 'suspended', 'suspended_until' => $suspendedUntil];

} else {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'פעולה לא תקינה'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = supabase_request('PATCH', '/users?id=eq.' . $userId, $patch);

if ($result['error'] !== null || $result['http_code'] !== 200) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'שגיאה בעדכון המשתמש'], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(200);
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
```

### Pattern 4: PHP delete.php Endpoint Structure

```php
<?php
/**
 * api/users/delete.php — Deletes a user by ID
 *
 * POST request only. Expects JSON body: { "id": 5 }
 *
 * Returns HTTP 200 with {"ok": true} on success
 * Returns HTTP 422 with {"ok": false, "message": "..."} on invalid ID
 * Returns HTTP 500 with {"ok": false, "message": "..."} on Supabase error
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../supabase.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$userId = intval($body['id'] ?? 0);

if ($userId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'מזהה משתמש לא תקין'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = supabase_request('DELETE', '/users?id=eq.' . $userId);

// Supabase DELETE returns 204 No Content on success (no body)
if ($result['error'] !== null || $result['http_code'] !== 204) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'שגיאה במחיקת המשתמש'], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(200);
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
```

### Pattern 5: Table Row with Action Buttons (Admin.php)

**What:** The `loadUsers()` function must add an "actions" column to each `<tr>`. Each button carries the user's data as `data-*` attributes so the JS handlers can read them without a DOM traversal.

**Table header change:** Add an 8th `<th>פעולות</th>` to the thead. Update `colspan` in empty-state rows from 7 to 8.

```javascript
// Source: Phase 4 loadUsers() pattern, extended for Phase 5
// Inside loadUsers(), inside the data.users.forEach loop:

data.users.forEach(function(user) {
    var tr = document.createElement('tr');
    tr.setAttribute('data-user-id', user.id);  // ID for delete/block/suspend by delegation
    tr.innerHTML =
        '<td>' + (user.id          || '') + '</td>' +
        '<td>' + (user.first_name  || '') + '</td>' +
        '<td>' + (user.last_name   || '') + '</td>' +
        '<td>' + (user.id_number   || '') + '</td>' +
        '<td>' + (user.email       || '') + '</td>' +
        '<td>' + (user.phone       || '') + '</td>' +
        '<td>' + statusBadge(user.status) + '</td>' +
        '<td>' +
            '<button class="btn btn-sm btn-outline-primary ms-1 btn-edit"' +
                ' data-id="' + user.id + '"' +
                ' data-first-name="' + escHtml(user.first_name) + '"' +
                ' data-last-name="' + escHtml(user.last_name) + '"' +
                ' data-phone="' + escHtml(user.phone) + '"' +
                ' data-email="' + escHtml(user.email) + '"' +
                ' data-gender="' + escHtml(user.gender) + '"' +
                ' data-foreign-worker="' + (user.foreign_worker ? '1' : '0') + '"' +
            '>עריכה</button>' +
            '<button class="btn btn-sm btn-outline-danger ms-1 btn-delete"' +
                ' data-id="' + user.id + '"' +
                ' data-name="' + escHtml(user.first_name + ' ' + user.last_name) + '"' +
            '>מחיקה</button>' +
            '<button class="btn btn-sm btn-outline-warning ms-1 btn-block"' +
                ' data-id="' + user.id + '"' +
            '>חסימה</button>' +
            '<button class="btn btn-sm btn-outline-secondary ms-1 btn-suspend"' +
                ' data-id="' + user.id + '"' +
            '>השעיה</button>' +
        '</td>';
    tbody.appendChild(tr);
});
```

**`escHtml()` helper (mandatory — prevent XSS in data attributes):**

```javascript
// Simple HTML-escape for inserting user data into innerHTML/data-* attributes
function escHtml(str) {
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}
```

### Pattern 6: Bootstrap 5.3 Edit Modal Structure (RTL-safe)

**What:** A standard Bootstrap modal whose form is pre-populated from button `data-*` attributes when the Edit button is clicked. No extra RTL configuration needed — Bootstrap RTL CSS handles all mirroring automatically.

```html
<!-- Source: Bootstrap 5.3 Modal docs — https://getbootstrap.com/docs/5.3/components/modal/ -->
<!-- Place this BEFORE the closing </div><!-- /container-lg --> -->

<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">עריכת משתמש</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="סגור"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-user-id">
                <div class="mb-3">
                    <label class="form-label">שם פרטי</label>
                    <input type="text" id="edit-first-name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">שם משפחה</label>
                    <input type="text" id="edit-last-name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">טלפון</label>
                    <input type="tel" id="edit-phone" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">אימייל</label>
                    <input type="email" id="edit-email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">מגדר</label>
                    <select id="edit-gender" class="form-select">
                        <option value="male">זכר</option>
                        <option value="female">נקבה</option>
                    </select>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" id="edit-foreign-worker" class="form-check-input">
                    <label class="form-check-label" for="edit-foreign-worker">עובד זר</label>
                </div>
                <div id="edit-modal-error" class="alert alert-danger" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ביטול</button>
                <button type="button" id="edit-save-btn" class="btn btn-primary">שמור</button>
            </div>
        </div>
    </div>
</div>

<!-- Suspend modal -->
<div class="modal fade" id="suspendModal" tabindex="-1" aria-labelledby="suspendModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="suspendModalLabel">השעיית משתמש</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="סגור"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="suspend-user-id">
                <div class="mb-3">
                    <label class="form-label">השעה עד תאריך</label>
                    <!-- min set by JS to tomorrow's date -->
                    <input type="date" id="suspend-until" class="form-control" required>
                </div>
                <div id="suspend-modal-error" class="alert alert-danger" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ביטול</button>
                <button type="button" id="suspend-save-btn" class="btn btn-warning">השעה</button>
            </div>
        </div>
    </div>
</div>
```

### Pattern 7: JS Event Delegation for Action Buttons

**What:** Register one click handler on `#users-tbody` (not on each button) and dispatch based on `event.target.classList`. This works correctly even after `loadUsers()` re-renders the table, because the handler is on the stable parent, not the regenerated buttons.

```javascript
// Source: Standard JS event delegation — no library required
// All button events delegated from tbody to avoid re-wiring after loadUsers()

document.getElementById('users-tbody').addEventListener('click', function(e) {
    var btn = e.target.closest('button');
    if (!btn) return;

    var userId = btn.getAttribute('data-id');

    if (btn.classList.contains('btn-edit')) {
        // Pre-populate edit modal from data-* attributes
        document.getElementById('edit-user-id').value      = userId;
        document.getElementById('edit-first-name').value   = btn.getAttribute('data-first-name');
        document.getElementById('edit-last-name').value    = btn.getAttribute('data-last-name');
        document.getElementById('edit-phone').value        = btn.getAttribute('data-phone');
        document.getElementById('edit-email').value        = btn.getAttribute('data-email');
        document.getElementById('edit-gender').value       = btn.getAttribute('data-gender');
        document.getElementById('edit-foreign-worker').checked =
            btn.getAttribute('data-foreign-worker') === '1';
        document.getElementById('edit-modal-error').style.display = 'none';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal')).show();

    } else if (btn.classList.contains('btn-delete')) {
        var name = btn.getAttribute('data-name');
        if (window.confirm('האם למחוק את המשתמש ' + name + '?')) {
            deleteUser(userId);
        }

    } else if (btn.classList.contains('btn-block')) {
        if (window.confirm('האם לחסום את המשתמש?')) {
            updateUser({ id: userId, action: 'block' });
        }

    } else if (btn.classList.contains('btn-suspend')) {
        document.getElementById('suspend-user-id').value = userId;
        // Set min date to tomorrow to enforce future-only dates
        var tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('suspend-until').min = tomorrow.toISOString().split('T')[0];
        document.getElementById('suspend-until').value = '';
        document.getElementById('suspend-modal-error').style.display = 'none';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('suspendModal')).show();
    }
});
```

### Pattern 8: JS Fetch Helpers for Update and Delete

```javascript
// All fetches use ES5 .then() chains — consistent with existing admin.php pattern

function updateUser(payload) {
    fetch('api/users/update.php', {
        method:  'POST',
        headers: {'Content-Type': 'application/json'},
        body:    JSON.stringify(payload)
    })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.ok) {
                loadUsers(); // Refresh table — same pattern as after create
            } else {
                alert(data.message || 'שגיאה בעדכון המשתמש');
            }
        })
        .catch(function() {
            alert('שגיאת תקשורת — נסה שוב');
        });
}

function deleteUser(userId) {
    fetch('api/users/delete.php', {
        method:  'POST',
        headers: {'Content-Type': 'application/json'},
        body:    JSON.stringify({id: userId})
    })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.ok) {
                loadUsers();
            } else {
                alert(data.message || 'שגיאה במחיקת המשתמש');
            }
        })
        .catch(function() {
            alert('שגיאת תקשורת — נסה שוב');
        });
}
```

### Pattern 9: Edit Modal Save Handler

```javascript
// Wired once on DOMContentLoaded — modal is stable DOM (not re-rendered by loadUsers)
document.getElementById('edit-save-btn').addEventListener('click', function() {
    var errorDiv = document.getElementById('edit-modal-error');
    var payload = {
        id:             parseInt(document.getElementById('edit-user-id').value, 10),
        action:         'edit',
        first_name:     document.getElementById('edit-first-name').value.trim(),
        last_name:      document.getElementById('edit-last-name').value.trim(),
        phone:          document.getElementById('edit-phone').value.trim(),
        email:          document.getElementById('edit-email').value.trim(),
        gender:         document.getElementById('edit-gender').value,
        foreign_worker: document.getElementById('edit-foreign-worker').checked
    };

    // Client-side guard
    if (!payload.first_name || !payload.last_name || !payload.phone || !payload.email) {
        errorDiv.textContent = 'כל השדות חובה';
        errorDiv.style.display = 'block';
        return;
    }

    errorDiv.style.display = 'none';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal')).hide();
    updateUser(payload);
});
```

### Anti-Patterns to Avoid

- **Registering click listeners inside `loadUsers()`:** Every call to `loadUsers()` (after create, update, delete) re-renders the tbody. If listeners are attached inside `loadUsers()`, they accumulate — 5 reloads = 5 listeners on every button. Use event delegation on the stable parent `#users-tbody`.
- **Using `new bootstrap.Modal()` every time a button is clicked:** Creates duplicate Modal instances on the same element, causing double-show behavior or memory leaks. Use `bootstrap.Modal.getOrCreateInstance()`.
- **Filtering by `id_number` instead of `id` for PATCH/DELETE:** The `id_number` is user-visible but the primary key `id` (BIGINT) is what PostgREST uses for row identity. Always use `?id=eq.{id}`.
- **Sending `suspended_until` without timezone:** `<input type="date">` returns `YYYY-MM-DD`. Supabase stores `TIMESTAMPTZ`. Sending a bare date string (`2026-06-30`) is accepted by PostgreSQL and interpreted as midnight UTC. This is correct and safe — do NOT convert to a full ISO timestamp in PHP unless timezone accuracy matters.
- **Checking `http_code !== 200` for DELETE:** DELETE returns **204**, not 200. Checking for 200 will always flag a success as an error. Check for 204.
- **Exposing `id_number` in data-* attributes in the HTML:** The `id_number` (Israeli ID) is PII. Only expose the minimum data needed in data-* attributes. The edit modal re-uses values already in the row cells if needed. Do NOT expose password-related data in data-* attributes (there is none returned by list.php).
- **No XSS escape on data-* attribute values:** User data (names, phone) can contain quotes or angle brackets. Always run values through `escHtml()` before inserting into innerHTML. Failure to escape allows stored XSS in the admin panel.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Modal dialogs | Custom overlay + CSS | Bootstrap 5.3 `modal` component | Already loaded; handles focus trap, keyboard close (Escape), backdrop click |
| Delete confirmation | Custom modal | `window.confirm()` | Internal admin tool — native browser confirm is instant and zero-code |
| Date picker | Third-party calendar widget | `<input type="date">` | Browser-native; RTL-compatible; always returns YYYY-MM-DD; no CDN dependency |
| Row event wiring after table re-render | Re-attach listeners after each `loadUsers()` | Event delegation on `#users-tbody` | Delegation survives table re-render without reattachment |
| XSS sanitization | Strip-tags or regex | `escHtml()` — 5-line function | Four targeted replacements are sufficient for HTML attribute context |

**Key insight:** Bootstrap's Modal is already loaded (the JS bundle was added in Phase 4). Zero new dependencies are needed for Phase 5. The entire feature set — edit modal, suspend modal, delete confirm, block action — is built from components already in the page.

---

## Common Pitfalls

### Pitfall 1: PATCH Returns 200, DELETE Returns 204 — Must Check Correctly

**What goes wrong:** PHP checks `$result['http_code'] !== 200` for both PATCH and DELETE. Delete always looks like a failure because DELETE returns 204.

**Why it happens:** Developers assume both write operations use the same success code. They do not.

**How to avoid:**
- `update.php`: `if ($result['http_code'] !== 200)` — PATCH success = 200
- `delete.php`: `if ($result['http_code'] !== 204)` — DELETE success = 204

**Warning signs:** Delete always returns HTTP 500 `שגיאה במחיקת המשתמש` even though the row disappears from Supabase.

**Confidence:** MEDIUM — confirmed via multiple Supabase community discussions and PostgREST issue tracker; not confirmed via official doc with explicit statement. If this fails in testing, check for 200 as well (some PostgREST versions may differ).

### Pitfall 2: Event Listener Accumulation After Table Reload

**What goes wrong:** Click listeners attached to `<button>` elements inside `loadUsers()` accumulate each time the table is reloaded. After creating a user and reloading the table, every button fires N times (N = number of reloads).

**Why it happens:** `loadUsers()` clears `tbody.innerHTML` and rebuilds it, creating fresh DOM nodes. But if listeners were attached to the old nodes, they are garbage-collected — however, if they were attached to parent containers (or body), they stack up.

**How to avoid:** Register ALL button handlers once using event delegation on `#users-tbody` (which is a stable element, never replaced by `loadUsers()`). Delegation using `event.target.closest('button')` is the correct pattern.

**Warning signs:** First row click works correctly. Second action on the same row triggers the action twice. Third triggers it three times.

### Pitfall 3: Bootstrap Modal Instance Duplication

**What goes wrong:** Calling `new bootstrap.Modal(element)` multiple times on the same DOM element creates multiple Modal instances. The modal may open correctly the first time but show/hide issues appear after subsequent opens.

**Why it happens:** Bootstrap does not prevent instantiation on an already-bootstrapped element.

**How to avoid:** Use `bootstrap.Modal.getOrCreateInstance(element)` — it returns the existing instance if one exists, or creates a new one. This is the safe, idempotent approach.

**Warning signs:** Modal opens twice. Or `.hide()` on one instance does not close the modal because a different instance controls it.

### Pitfall 4: Missing `escHtml()` Leads to XSS in Admin Panel

**What goes wrong:** A user's first name contains `"` or `<script>`. When `loadUsers()` inserts the name into `data-first-name="..."` or directly into `innerHTML`, the script executes in the admin browser.

**Why it happens:** The admin table is built with string concatenation into `innerHTML`. User-supplied data must be escaped before insertion.

**How to avoid:** Apply `escHtml()` to ALL user data before inserting into innerHTML or data-* attribute values. Specifically: `first_name`, `last_name`, `email`, `phone`.

**Warning signs:** An admin user with name `"><img src=x onerror=alert(1)>` causes a JavaScript alert when the table loads.

### Pitfall 5: Suspend Date Not in Future — No Server Validation

**What goes wrong:** Admin selects today's date or a past date in the suspend date picker. The client-side `min` attribute prevents this in supporting browsers, but the PHP server does not re-validate, so a raw API call bypasses the restriction.

**Why it happens:** Client-side only validation is always bypassable.

**How to avoid:** PHP must validate: `$date <= new DateTime('today')` → return 422. Use `DateTime::createFromFormat('Y-m-d', $suspendedUntil)` and compare.

**Warning signs:** User gets status 'suspended' with `suspended_until = '2024-01-01'` (already past). The suspension is semantically meaningless.

### Pitfall 6: `suspended_until` Not Cleared When Blocking

**What goes wrong:** A previously-suspended user has `suspended_until = '2026-12-31'`. Admin clicks Block. The status changes to 'blocked' but `suspended_until` still holds the old date. Future logic that reads `suspended_until` is confused.

**Why it happens:** Block action only sets `status = 'blocked'` without clearing `suspended_until`.

**How to avoid:** Block action must set both: `['status' => 'blocked', 'suspended_until' => null]`. PostgreSQL accepts JSON `null` as SQL NULL on a TIMESTAMPTZ column.

**Warning signs:** Blocked user shows a `suspended_until` date in Supabase dashboard even though they are blocked, not suspended.

### Pitfall 7: PATCH with No Filter Modifies ALL Rows

**What goes wrong:** A bug in PHP sends `supabase_request('PATCH', '/users', $patch)` without the `?id=eq.{userId}` filter. PostgREST updates ALL rows in the `users` table.

**Why it happens:** Missing filter string in the URL path. PostgREST is spec-compliant: PATCH on a collection without a filter = update all.

**How to avoid:** Always validate `$userId > 0` before building the PATCH URL. The URL must be `/users?id=eq.{intval($userId)}`. Use `intval()` to prevent injection of PostgREST operators.

**Warning signs:** After one edit, all users in the table show the same name. Catastrophic data loss.

---

## Code Examples

Verified patterns from official sources and project codebase:

### PostgREST PATCH Filter by ID

```php
// Source: PostgREST v12 docs — https://docs.postgrest.org/en/v12/references/api/tables_views.html
// Filter syntax: ?column=operator.value
// eq = equals operator

$result = supabase_request('PATCH', '/users?id=eq.' . intval($userId), $patch);
// Success: $result['http_code'] === 200
// $result['data'] === [] (empty array, no representation requested)
```

### PostgREST DELETE by ID

```php
// Source: PostgREST v12 docs — https://docs.postgrest.org/en/v12/references/api/tables_views.html
// DELETE with no body — filter in URL

$result = supabase_request('DELETE', '/users?id=eq.' . intval($userId));
// Success: $result['http_code'] === 204
// $result['data'] === null (204 = No Content)
```

### PHP Date Validation (Future Date Only)

```php
// Source: PHP Manual — https://www.php.net/manual/en/class.datetime.php
// DateTime::createFromFormat validates format AND date logic (no Feb 30 etc.)

$date = DateTime::createFromFormat('Y-m-d', $suspendedUntil);
if (!$date || $date->format('Y-m-d') !== $suspendedUntil) {
    // Invalid format or impossible date
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'תאריך לא תקין'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($date <= new DateTime('today')) {
    // Past or today — must be future
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'תאריך ההשעיה חייב להיות בעתיד'], JSON_UNESCAPED_UNICODE);
    exit;
}
```

### Bootstrap 5.3 Modal — Programmatic Open/Close

```javascript
// Source: Bootstrap 5.3 Modal docs — https://getbootstrap.com/docs/5.3/components/modal/

// OPEN — getOrCreateInstance is safe to call multiple times
bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal')).show();

// CLOSE — same getOrCreateInstance pattern
bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal')).hide();

// CLOSE from within a button using data attribute (no JS needed):
// <button data-bs-dismiss="modal">ביטול</button>
```

### Set Tomorrow as Minimum Date for Date Picker

```javascript
// Prevent selecting today or past dates in the suspend date picker
var tomorrow = new Date();
tomorrow.setDate(tomorrow.getDate() + 1);
// toISOString() → "2026-03-01T00:00:00.000Z" → split at T → "2026-03-01"
document.getElementById('suspend-until').min = tomorrow.toISOString().split('T')[0];
```

### Setting suspended_until to null (clear on block)

```php
// Source: PostgREST accepts JSON null as SQL NULL for nullable columns
// TIMESTAMPTZ NULL column: JSON null maps to PostgreSQL NULL

$patch = ['status' => 'blocked', 'suspended_until' => null];
// PHP json_encode(['suspended_until' => null]) → '{"suspended_until":null}'
// PostgREST sends: UPDATE users SET status='blocked', suspended_until=NULL WHERE id=eq.X
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Separate delete.php, block.php, suspend.php | Single update.php with `action` field dispatching | Industry pattern (2020+) | Fewer files, consistent entry point |
| jQuery `.modal('show')` | `bootstrap.Modal.getOrCreateInstance().show()` | Bootstrap 5 (2021) | No jQuery dependency; vanilla JS Bootstrap API |
| Custom date picker (jQuery UI Datepicker) | `<input type="date">` | HTML5 / 2015+ | No library; native browser; RTL-compatible |
| Separate event listeners on each button | Event delegation on stable parent | ES5 pattern (always correct) | Survives DOM re-render without reattachment |

**Deprecated/outdated:**
- `$('#myModal').modal('show')` — jQuery Bootstrap 4 syntax. Bootstrap 5 removed jQuery. Use `bootstrap.Modal.getOrCreateInstance()`.
- `data-toggle="modal"` — Bootstrap 4 attribute. Bootstrap 5 uses `data-bs-toggle="modal"`.
- `data-dismiss="modal"` — Bootstrap 4. Bootstrap 5 uses `data-bs-dismiss="modal"`.

---

## Open Questions

1. **Should update.php also allow changing `id_number`?**
   - What we know: `id_number` has a UNIQUE constraint. Allowing edits risks duplicate key violations. ADMIN-08 says "edit user details" without specifying which fields.
   - What's unclear: Whether Sharon wants `id_number` to be editable.
   - Recommendation: Exclude `id_number` from the edit modal. It is an identity document number — it should not change after creation. If needed later, it can be added to the edit form with duplicate-key handling.

2. **Should the table show `suspended_until` date when status is 'suspended'?**
   - What we know: The current status badge shows only "מושעה" with no date. ADMIN-11 requires the date to be stored, but the success criterion says "the correct end date stored in Supabase" — not necessarily displayed in the table.
   - What's unclear: Whether Sharon wants to see the date in the table or only in Supabase.
   - Recommendation: Display `suspended_until` in the status column when status is 'suspended', e.g.: `מושעה עד 30/06/2026`. Requires `suspended_until` to be included in `list.php`'s SELECT query (currently it is excluded). Add it to the query.

3. **What HTTP status code does Supabase return for PATCH when zero rows match the filter?**
   - What we know: PostgREST v9 returned 404 for unmatched PATCH/DELETE. This was changed. Current behavior when using service_role (no RLS): likely returns 200 with empty body.
   - What's unclear: Exact behavior for missing ID in Supabase's current PostgREST version.
   - Recommendation: Do not rely on 404 for "user not found" detection. After a successful PATCH call, the table will be reloaded by `loadUsers()` — if the update had no effect, the user will see no change. This is acceptable for an admin tool. If strict detection is needed, pass `Prefer: return=representation` and check if `$result['data']` is an empty array.

---

## Sources

### Primary (HIGH confidence)

- PostgREST v12 Tables/Views Docs — https://docs.postgrest.org/en/v12/references/api/tables_views.html — confirmed PATCH filter syntax `?id=eq.{id}`, DELETE syntax, `Prefer: return=representation` for PATCH/DELETE
- Bootstrap 5.3 Modal Docs — https://getbootstrap.com/docs/5.3/components/modal/ — confirmed `bootstrap.Modal.getOrCreateInstance()`, `.show()`, `.hide()`, `data-bs-dismiss="modal"`, modal HTML structure
- Bootstrap 5.3 RTL Docs — https://getbootstrap.com/docs/5.3/getting-started/rtl/ — confirmed RTL works automatically with `bootstrap.rtl.min.css` and `dir="rtl"`; no modal-specific RTL configuration needed
- PHP Manual: DateTime::createFromFormat — https://www.php.net/manual/en/datetime.createfromformat.php — confirmed format validation pattern for date inputs
- Phase 3 `api/supabase.php` (production-verified) — `supabase_request()` supports PATCH and DELETE via `CURLOPT_CUSTOMREQUEST`
- Phase 4 `admin.php` + `api/users/create.php` (production-verified) — established patterns for all: event handling, fetch, `loadUsers()`, Bootstrap RTL, ES5 `.then()` chains

### Secondary (MEDIUM confidence)

- Supabase community discussion #28041 — confirmed PATCH returns 200 (not 204) on success; resolved via service_role RLS bypass confirmation
- PostgREST GitHub Issue #182 — confirmed DELETE returns 204 No Content by default; `Prefer: return=representation` returns body
- Supabase Restack docs — confirmed DELETE 204 as standard success code for Supabase REST API
- Bootstrap 5 `data-bs-*` attribute naming — confirmed Bootstrap 5 changed `data-toggle` to `data-bs-toggle`, `data-dismiss` to `data-bs-dismiss`

### Tertiary (LOW confidence — verify before use)

- PATCH HTTP status code (200 vs 204): Multiple sources suggest 200 for Supabase PATCH, but no single official Supabase doc page explicitly states this. If `update.php` fails with 500 when the Supabase call actually succeeded, check for 204 and adjust the condition.

---

## Metadata

**Confidence breakdown:**

| Area | Level | Reason |
|------|-------|--------|
| PostgREST PATCH/DELETE URL filter syntax | HIGH | Official PostgREST v12 docs |
| Bootstrap 5.3 Modal API | HIGH | Official Bootstrap docs; same modal HTML structure and JS API since Bootstrap 5.0 |
| PHP `supabase_request()` PATCH/DELETE | HIGH | Function already supports both via `CURLOPT_CUSTOMREQUEST` (verified in supabase.php source) |
| PATCH returns 200 | MEDIUM | Multiple community sources; no single official doc statement |
| DELETE returns 204 | HIGH | Multiple sources agree; PostgREST GitHub issue explicitly confirms |
| Event delegation pattern | HIGH | Standard DOM API; project already uses addEventListener throughout |
| `suspended_until = null` for block | HIGH | PHP `json_encode` produces `null` which PostgREST maps to PostgreSQL NULL |
| Date validation via `DateTime::createFromFormat` | HIGH | Official PHP Manual |

**Research date:** 2026-02-28
**Valid until:** 2026-08-28 (PostgREST REST API and Bootstrap 5 stable; re-verify if Bootstrap version is bumped or Supabase changes PostgREST version)
