<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול משתמשים</title>
    <!-- Bootstrap 5.3 RTL CSS — loaded first so its classes take priority over style.css -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.rtl.min.css">
    <!-- Shared site styles (fonts, colors) — loaded after Bootstrap to inherit base RTL -->
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Override style.css .container max-width so container-lg uses Bootstrap's wider breakpoint */
        .container-lg {
            max-width: none;
        }
        /* Reset style.css body padding that would double-indent the Bootstrap container */
        body {
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="container-lg py-4">
        <h1 class="mb-4">ניהול משתמשים</h1>

        <!-- ===== Section 1: Search box (ADMIN-07) ===== -->
        <input
            type="search"
            id="search-box"
            class="form-control mb-3"
            placeholder="חיפוש לפי שם, אימייל או מספר זהות"
        >

        <!-- ===== Section 2: User table (ADMIN-06) ===== -->
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
                        <th>פעולות</th>
                    </tr>
                </thead>
                <tbody id="users-tbody">
                    <!-- Populated by loadUsers() on DOMContentLoaded -->
                    <tr>
                        <td colspan="8" class="text-center text-muted">טוען משתמשים...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ===== Section 3: Create user form (ADMIN-02) ===== -->
        <h2 class="mb-3">הוספת משתמש חדש</h2>

        <!-- novalidate — JS controls all validation so we get Hebrew messages (ADMIN-03, ADMIN-04) -->
        <form id="create-form" novalidate>

            <div class="mb-3">
                <label for="first_name" class="form-label">שם פרטי</label>
                <input type="text" id="first_name" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="last_name" class="form-label">שם משפחה</label>
                <input type="text" id="last_name" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="id_number" class="form-label">מספר זהות</label>
                <input type="text" id="id_number" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="phone" class="form-label">טלפון</label>
                <!-- ADMIN-02: JS strips non-digits on input event -->
                <input type="tel" id="phone" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="gender" class="form-label">מגדר</label>
                <select id="gender" class="form-select" required>
                    <option value="">בחר מגדר</option>
                    <option value="male">זכר</option>
                    <option value="female">נקבה</option>
                </select>
            </div>

            <!-- ADMIN-02: Foreign worker checkbox -->
            <div class="form-check mb-3">
                <input type="checkbox" id="foreign_worker" class="form-check-input">
                <label class="form-check-label" for="foreign_worker">עובד זר</label>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">אימייל</label>
                <!-- ADMIN-03 frontend: email format validated in JS validateForm() -->
                <input type="email" id="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">סיסמא</label>
                <!-- ADMIN-05: input-group wraps the password field + generate button -->
                <div class="input-group">
                    <!-- ADMIN-04 frontend: minlength validated in JS validateForm() -->
                    <input type="password" id="password" class="form-control" minlength="8" required>
                    <button type="button" id="generate-pw-btn" class="btn btn-outline-secondary">צור סיסמא</button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary mt-3">הוסף משתמש</button>

            <!-- Feedback divs — hidden by default, shown/hidden by JS -->
            <div id="form-error" class="alert alert-danger mt-3" style="display:none;"></div>
            <div id="form-success" class="alert alert-success mt-3" style="display:none;"></div>

        </form>
    </div><!-- /container-lg -->

    <!-- ===== Edit Modal (ADMIN-08) — B6 ===== -->
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

    <!-- ===== Suspend Modal (ADMIN-11) — B6 ===== -->
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
                        <!-- min set by JS to tomorrow's date — ADMIN-11 client guard -->
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

    <!-- Bootstrap JS bundle (includes Popper) — loaded before inline script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // ===== B1. escHtml — prevent XSS when inserting user data into innerHTML / data-* attributes =====
        // RESEARCH.md Pattern 5 — must be defined before statusBadge() and loadUsers()
        function escHtml(str) {
            return String(str || '')
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        // ===== B2. statusBadge — translate status code to Hebrew badge HTML =====
        // Accepts optional suspendedUntil to display the end date when status is 'suspended'
        function statusBadge(status, suspendedUntil) {
            if (status === 'active')    return '<span class="badge bg-success">פעיל</span>';
            if (status === 'blocked')   return '<span class="badge bg-danger">חסום</span>';
            if (status === 'suspended') {
                var dateStr = '';
                if (suspendedUntil) {
                    var d = new Date(suspendedUntil);
                    dateStr = ' עד ' + d.toLocaleDateString('he-IL');
                }
                return '<span class="badge bg-warning text-dark">מושעה' + dateStr + '</span>';
            }
            return '<span class="badge bg-secondary">' + escHtml(status) + '</span>';
        }

        // ===== B8. updateUser — POST to api/users/update.php =====
        // ES5 .then() chain — consistent with existing admin.php pattern (RESEARCH.md Pattern 8)
        // Must be defined before the event delegation handler uses it (B7)
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

        // ===== B9. deleteUser — POST to api/users/delete.php =====
        // ES5 .then() chain — consistent with existing admin.php pattern (RESEARCH.md Pattern 8)
        // Must be defined before the event delegation handler uses it (B7)
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

        // ===== 1. loadUsers — fetch user list from API and populate table (ADMIN-01, ADMIN-06) =====
        function loadUsers() {
            var tbody = document.getElementById('users-tbody');

            fetch('api/users/list.php')
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    tbody.innerHTML = '';

                    if (!data.ok) {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">שגיאה בטעינת המשתמשים</td></tr>';
                        return;
                    }

                    if (!data.users || data.users.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">אין משתמשים במערכת</td></tr>';
                        return;
                    }

                    // B5. Build a row for each user — action buttons + escHtml() on all user data
                    // RESEARCH.md Pattern 5
                    data.users.forEach(function(user) {
                        var tr = document.createElement('tr');
                        tr.setAttribute('data-user-id', user.id); // For event delegation
                        tr.innerHTML =
                            '<td>' + (user.id          || '') + '</td>' +
                            '<td>' + escHtml(user.first_name  || '') + '</td>' +
                            '<td>' + escHtml(user.last_name   || '') + '</td>' +
                            '<td>' + escHtml(user.id_number   || '') + '</td>' +
                            '<td>' + escHtml(user.email       || '') + '</td>' +
                            '<td>' + escHtml(user.phone       || '') + '</td>' +
                            '<td>' + statusBadge(user.status, user.suspended_until) + '</td>' +
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
                                    ' data-name="' + escHtml((user.first_name || '') + ' ' + (user.last_name || '')) + '"' +
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
                })
                .catch(function() {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">שגיאת תקשורת — לא ניתן לטעון משתמשים</td></tr>';
                });
        }

        // Load users immediately when the DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            loadUsers();

            // ===== B7. Event delegation on #users-tbody for all action buttons =====
            // Registered ONCE inside DOMContentLoaded — survives table re-renders because
            // the handler is on the stable tbody parent, not on the re-rendered buttons.
            // RESEARCH.md Pattern 7
            document.getElementById('users-tbody').addEventListener('click', function(e) {
                var btn = e.target.closest('button');
                if (!btn) return;

                var userId = btn.getAttribute('data-id');

                if (btn.classList.contains('btn-edit')) {
                    // Pre-populate edit modal from data-* attributes (avoids second API call)
                    document.getElementById('edit-user-id').value      = userId;
                    document.getElementById('edit-first-name').value   = btn.getAttribute('data-first-name');
                    document.getElementById('edit-last-name').value    = btn.getAttribute('data-last-name');
                    document.getElementById('edit-phone').value        = btn.getAttribute('data-phone');
                    document.getElementById('edit-email').value        = btn.getAttribute('data-email');
                    document.getElementById('edit-gender').value       = btn.getAttribute('data-gender');
                    document.getElementById('edit-foreign-worker').checked =
                        btn.getAttribute('data-foreign-worker') === '1';
                    document.getElementById('edit-modal-error').style.display = 'none';
                    // getOrCreateInstance — safe to call multiple times (RESEARCH.md Pattern 3 avoidance)
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
                    // Set min date to tomorrow — client guard for future-only dates (ADMIN-11)
                    var tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    document.getElementById('suspend-until').min = tomorrow.toISOString().split('T')[0];
                    document.getElementById('suspend-until').value = '';
                    document.getElementById('suspend-modal-error').style.display = 'none';
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('suspendModal')).show();
                }
            });

            // ===== B10. Edit modal save handler =====
            // Wired once on DOMContentLoaded — modal is stable DOM, not re-rendered by loadUsers()
            // RESEARCH.md Pattern 9
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

                // Client-side guard — all required fields must be filled
                if (!payload.first_name || !payload.last_name || !payload.phone || !payload.email) {
                    errorDiv.textContent    = 'כל השדות חובה';
                    errorDiv.style.display  = 'block';
                    return;
                }

                errorDiv.style.display = 'none';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal')).hide();
                updateUser(payload);
            });

            // ===== B11. Suspend modal save handler =====
            document.getElementById('suspend-save-btn').addEventListener('click', function() {
                var errorDiv  = document.getElementById('suspend-modal-error');
                var userId    = document.getElementById('suspend-user-id').value;
                var dateValue = document.getElementById('suspend-until').value;

                // Client-side guard — date must be selected
                if (!dateValue) {
                    errorDiv.textContent   = 'יש לבחור תאריך';
                    errorDiv.style.display = 'block';
                    return;
                }

                errorDiv.style.display = 'none';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('suspendModal')).hide();
                updateUser({ id: userId, action: 'suspend', suspended_until: dateValue });
            });
        });

        // ===== 2. Live search — filter table rows by name, email, or ID (ADMIN-07) =====
        document.getElementById('search-box').addEventListener('input', function() {
            var query = this.value.trim().toLowerCase();
            var rows  = document.querySelectorAll('#users-tbody tr');

            rows.forEach(function(row) {
                // Show row if query is empty OR row text contains the query
                row.style.display = (query === '' || row.textContent.toLowerCase().indexOf(query) !== -1) ? '' : 'none';
            });
        });

        // ===== 3. Phone digits-only filter (ADMIN-02) =====
        document.getElementById('phone').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });

        // ===== 4. Password generator (ADMIN-05) =====
        // Uses crypto.getRandomValues for cryptographic randomness.
        // Ambiguous characters (0, O, 1, l, I) removed from charset.
        function generateSecurePassword(length) {
            var charset = 'ABCDEFGHIJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$';
            var bytes   = crypto.getRandomValues(new Uint8Array(length));
            var result  = '';
            for (var i = 0; i < length; i++) {
                result += charset[bytes[i] % charset.length];
            }
            return result;
        }

        document.getElementById('generate-pw-btn').addEventListener('click', function() {
            var passwordInput = document.getElementById('password');
            // Generate 12-char password and display it as text so admin can read/copy it
            passwordInput.value = generateSecurePassword(12);
            passwordInput.type  = 'text';
        });

        // ===== 5. Form validation (ADMIN-03 frontend, ADMIN-04 frontend) =====
        // Returns a Hebrew error string on failure, or null if all valid.
        function validateForm(data) {
            // Required fields check — all must be non-empty strings
            if (!data.first_name || !data.last_name || !data.id_number ||
                !data.phone || !data.gender || !data.email || !data.password) {
                return 'כל השדות חובה';
            }

            // ADMIN-03: Email format validation
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(data.email)) {
                return 'כתובת אימייל לא תקינה';
            }

            // ADMIN-04: Password minimum length
            if (data.password.length < 8) {
                return 'הסיסמא חייבת לכלול לפחות 8 תווים';
            }

            return null; // All valid
        }

        // ===== 6. Create user form submit handler =====
        document.getElementById('create-form').addEventListener('submit', function(e) {
            e.preventDefault();

            var errorDiv   = document.getElementById('form-error');
            var successDiv = document.getElementById('form-success');
            var submitBtn  = this.querySelector('button[type="submit"]');

            // Gather all field values
            var data = {
                first_name:     document.getElementById('first_name').value.trim(),
                last_name:      document.getElementById('last_name').value.trim(),
                id_number:      document.getElementById('id_number').value.trim(),
                phone:          document.getElementById('phone').value.trim(),
                gender:         document.getElementById('gender').value,
                foreign_worker: document.getElementById('foreign_worker').checked,
                email:          document.getElementById('email').value.trim(),
                password:       document.getElementById('password').value
            };

            // Frontend validation — show Hebrew error without hitting the server
            var validationError = validateForm(data);
            if (validationError) {
                errorDiv.textContent  = validationError;
                errorDiv.style.display = 'block';
                successDiv.style.display = 'none';
                return;
            }

            // Clear previous feedback and disable submit button while request is in flight
            errorDiv.style.display   = 'none';
            successDiv.style.display = 'none';
            submitBtn.disabled = true;

            fetch('api/users/create.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(data)
            })
                .then(function(res) { return res.json(); })
                .then(function(result) {
                    if (result.ok) {
                        // Success: show confirmation, clear form, refresh table
                        successDiv.textContent   = 'המשתמש נוצר בהצלחה';
                        successDiv.style.display = 'block';
                        document.getElementById('create-form').reset();
                        loadUsers();
                    } else {
                        // Server returned a Hebrew error (duplicate email, validation failure, etc.)
                        errorDiv.textContent    = result.message;
                        errorDiv.style.display  = 'block';
                    }
                })
                .catch(function() {
                    // Network or JSON parse failure
                    errorDiv.textContent   = 'שגיאת תקשורת — נסה שוב';
                    errorDiv.style.display = 'block';
                })
                .then(function() {
                    // Re-enable submit button whether request succeeded or failed
                    submitBtn.disabled = false;
                });
        });
    </script>
</body>
</html>
