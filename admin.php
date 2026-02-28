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
                    </tr>
                </thead>
                <tbody id="users-tbody">
                    <!-- Populated by loadUsers() on DOMContentLoaded -->
                    <tr>
                        <td colspan="7" class="text-center text-muted">טוען משתמשים...</td>
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

    <!-- Bootstrap JS bundle (includes Popper) — loaded before inline script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // ===== Utility: translate status code to Hebrew badge HTML =====
        function statusBadge(status) {
            if (status === 'active')    return '<span class="badge bg-success">פעיל</span>';
            if (status === 'blocked')   return '<span class="badge bg-danger">חסום</span>';
            if (status === 'suspended') return '<span class="badge bg-warning text-dark">מושעה</span>';
            return '<span class="badge bg-secondary">' + status + '</span>';
        }

        // ===== 1. loadUsers — fetch user list from API and populate table (ADMIN-01, ADMIN-06) =====
        function loadUsers() {
            var tbody = document.getElementById('users-tbody');

            fetch('api/users/list.php')
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    tbody.innerHTML = '';

                    if (!data.ok) {
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">שגיאה בטעינת המשתמשים</td></tr>';
                        return;
                    }

                    if (!data.users || data.users.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">אין משתמשים במערכת</td></tr>';
                        return;
                    }

                    // Build a row for each user
                    data.users.forEach(function(user) {
                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>' + (user.id          || '') + '</td>' +
                            '<td>' + (user.first_name  || '') + '</td>' +
                            '<td>' + (user.last_name   || '') + '</td>' +
                            '<td>' + (user.id_number   || '') + '</td>' +
                            '<td>' + (user.email       || '') + '</td>' +
                            '<td>' + (user.phone       || '') + '</td>' +
                            '<td>' + statusBadge(user.status) + '</td>';
                        tbody.appendChild(tr);
                    });
                })
                .catch(function() {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">שגיאת תקשורת — לא ניתן לטעון משתמשים</td></tr>';
                });
        }

        // Load users immediately when the DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            loadUsers();
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
