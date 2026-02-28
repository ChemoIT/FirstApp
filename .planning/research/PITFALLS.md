# Domain Pitfalls

**Domain:** PHP signature dispatch web app on cPanel shared hosting
**Project:** FirstApp — Signature Dispatch System
**Researched:** 2026-02-27 (v1.0) | Updated: 2026-02-28 (v2.0 Supabase milestone)
**Confidence:** HIGH (well-established PHP/browser patterns) | MEDIUM (Supabase+PHP integration — verified via official Supabase docs + community discussions)

---

## v1.0 Critical Pitfalls (PHP + cPanel baseline)

These pitfalls were documented for the original PHP build and remain active constraints throughout all milestones.

---

### Pitfall 1: Micropay API Token Exposed in Client-Side JavaScript

**What goes wrong:** The SMS dispatch button makes a fetch/AJAX call directly to the Micropay API URL from the browser, embedding the token in the JavaScript. Anyone who opens DevTools → Network tab sees the full token.

**Why it happens:** Beginners think "if the JS is in a PHP file it's hidden." It is not — PHP runs server-side, then the rendered HTML (including all JS) is sent to the browser in plain text.

**Consequences:** Token is visible to any user who opens DevTools. Anyone can then send unlimited SMS messages on the account with zero barrier.

**Prevention:**
- ALL calls to the Micropay API must happen inside a PHP file running on the server.
- The browser button submits a form (POST) to a PHP script. The PHP script calls Micropay. No token ever touches the browser.
- Double-check: use View Source in the browser after building. The word "token" or the actual token value must not appear anywhere.

**Detection (warning signs):**
- You wrote `fetch("https://api.micropay.co.il/...?token=XXXX")` in a `.js` file or in `<script>` tags.
- You can see the token by opening DevTools → Network → clicking the SMS request.

**Phase:** Authentication & Dispatch (Phase 1)

---

### Pitfall 2: PHP Session Not Started Before Use

**What goes wrong:** `$_SESSION['logged_in']` is checked or set, but `session_start()` was forgotten at the top of the file. PHP silently ignores the session read, the variable is undefined, and the auth check passes or fails unpredictably.

**Why it happens:** `session_start()` must be the very first thing in any PHP file that uses sessions — before any HTML output, before any echo, before any blank lines. Forgetting it, or placing it after `echo` output, causes a "headers already sent" warning and breaks the session entirely.

**Consequences:** Auth check always evaluates as "not logged in" or always passes depending on how the code is structured. Silent failure — the page may appear to work but the session is not being set.

**Prevention:**
- `<?php session_start(); ?>` is line 1 of every PHP file that reads or writes `$_SESSION`.
- Add `error_reporting(E_ALL); ini_set('display_errors', 1);` during development to surface "headers already sent" warnings.
- After login, immediately `var_dump($_SESSION)` during testing to confirm the session was written.

**Detection (warning signs):**
- Logging in succeeds but the dispatch page immediately redirects back to login.
- PHP error log shows "Cannot modify header information — headers already sent."
- `$_SESSION` is empty even after setting it.

**Phase:** Authentication (Phase 1)

---

### Pitfall 3: Canvas Signature Blank on iOS Safari (Touch vs Mouse Events)

**What goes wrong:** The signature canvas works perfectly in Chrome on desktop (mouse events). On an iPhone with Safari, drawing does nothing — the canvas stays blank, or the page scrolls instead of capturing the stroke.

**Why it happens:** Two separate issues:
1. Mobile browsers fire `touchstart`, `touchmove`, `touchend` events, not `mousedown`/`mousemove`. Code that only listens to mouse events does not work on touch screens.
2. `touchmove` default behavior is page scroll. If `preventDefault()` is not called on the touch event, the browser scrolls the page while the user tries to sign — no stroke is drawn.

**Consequences:** The entire signing flow is broken on mobile, which is the primary use case. This will not be discovered until testing on an actual phone.

**Prevention:**
- Register both mouse AND touch event listeners on the canvas element.
- Call `e.preventDefault()` inside every `touchmove` handler.
- Use `e.touches[0].clientX` / `e.touches[0].clientY` to extract coordinates from touch events (not `e.clientX`).
- Test on a real iPhone and real Android before considering the feature done.

**Detection (warning signs):**
- Tested only on desktop browser.
- Event listeners only reference `mousedown`, `mousemove`, `mouseup`.
- Page scrolls when finger moves on the canvas.

**Phase:** Signature Capture (Phase 2)

---

### Pitfall 4: Signature PNG Saved as Empty/Blank White Image

**What goes wrong:** `canvas.toDataURL('image/png')` returns a valid base64 string, it is posted to PHP, PHP decodes and writes it — but the saved file opens as a completely blank white image.

**Why it happens:** Multiple causes:
1. The canvas background is transparent by default. `toDataURL` with a transparent background produces a valid PNG with no visible content on white backgrounds — it looks blank when opened.
2. The canvas element was re-created or its size changed via CSS (`width`/`height` CSS properties vs. the HTML `width`/`height` attributes). Changing canvas size via CSS scales the display but clears the drawing buffer, erasing any content.
3. `base64_decode()` in PHP silently returns false for malformed data (e.g., the `data:image/png;base64,` prefix was not stripped before decoding).

**Consequences:** The file exists on disk, the confirmation SMS is sent, but the signature file contains nothing. The signed document cannot be recovered.

**Prevention:**
- Before `toDataURL`, fill the canvas background: `ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, canvas.width, canvas.height);` — do this at initialization, before any strokes.
- Set canvas dimensions using HTML attributes (`canvas.width = 600`), not CSS.
- In PHP, strip the data URL prefix before decoding: `$data = base64_decode(str_replace('data:image/png;base64,', '', $_POST['signature']));`
- After saving, verify `$data !== false` and `file_put_contents() !== false` before sending the confirmation SMS.

**Detection (warning signs):**
- PNG file exists on server but appears blank when opened.
- PHP `file_put_contents` returns 0 bytes written.
- `base64_decode` returns `false` (check with `var_dump`).

**Phase:** Signature Capture & Storage (Phase 2)

---

### Pitfall 5: Hebrew SMS Truncated or Garbled (iso-8859-8 Encoding)

**What goes wrong:** The Hebrew message sent via Micropay arrives on the phone as `????` or gets cut off mid-word. The API call returns HTTP 200 (success) but the message content is wrong.

**Why it happens:**
1. The Micropay API expects the message body encoded as `iso-8859-8` (legacy Hebrew encoding), not UTF-8. PHP files are UTF-8 by default. Sending a UTF-8 Hebrew string directly to the API corrupts the characters.
2. Hebrew SMS uses UCS-2 encoding at the carrier level. A single Hebrew character uses 2 bytes. This limits a single SMS to **70 characters** (not 160). A message that looks short in Hebrew can exceed 70 chars and be split or truncated depending on carrier behavior.
3. URL encoding of Hebrew characters (`urlencode`) on a UTF-8 string produces different percent-encoded sequences than encoding an `iso-8859-8` string.

**Consequences:** Messages arrive as garbage text or are silently truncated. The confirmation message "המסמך נחתם" may display as question marks on the recipient's phone.

**Prevention:**
- Convert message string from UTF-8 to iso-8859-8 before URL-encoding: `$msg = iconv('UTF-8', 'ISO-8859-8', 'המסמך נחתם');`
- Then URL-encode the result: `$msg = urlencode($msg);`
- Keep ALL Hebrew SMS messages under 70 characters. Count characters carefully — "המסמך נחתם" is 10 characters, well within limit. The signing link URL adds to the count in the dispatch SMS.
- Test the actual received SMS on a real phone before marking this feature done.

**Detection (warning signs):**
- SMS arrives with `?` characters instead of Hebrew letters.
- Message is cut off unexpectedly.
- API returns success but message is unreadable.

**Phase:** SMS Integration (Phase 1 and Phase 2)

---

### Pitfall 6: signatures/ Directory Publicly Accessible via URL

**What goes wrong:** Signature PNG files saved to `ch-ah.info/FirstApp/signatures/` are directly accessible via browser URL. Anyone who guesses or discovers a filename can view any signed document.

**Why it happens:** On cPanel shared hosting, every file inside `public_html/` is publicly accessible by default unless explicitly protected. There is no automatic access restriction on subdirectories.

**Consequences:** Signed documents (which may contain handwritten signatures) are exposed to the open internet. For a learning project this is low-stakes, but the habit of leaving sensitive files web-accessible is dangerous to carry into production work.

**Prevention:**
- Add a `.htaccess` file inside the `signatures/` directory with `Deny from all`. This blocks direct browser access while PHP (running server-side) can still read/write the files.
- Alternatively, store signatures outside `public_html/` entirely (one directory up). PHP can still write there; browsers cannot access it.
- File names should not be sequential integers (e.g., `1.png`, `2.png`). Use `uniqid()` or a random hash so filenames cannot be enumerated.

**Detection (warning signs):**
- Typing `https://ch-ah.info/FirstApp/signatures/test.png` in a browser shows the image.
- No `.htaccess` file exists in the signatures directory.
- Files are named with predictable patterns.

**Phase:** Signature Storage (Phase 2)

---

### Pitfall 7: PHP Error Display Off on Shared Hosting — Silent Failures

**What goes wrong:** An error in the PHP code produces no visible output. The page goes blank, or the form submits and nothing happens. There is no error message to debug from.

**Why it happens:** cPanel shared hosting typically has `display_errors = Off` in the server's `php.ini` for security reasons. Errors are logged to a server error log, but beginners do not know where to find it or that it exists.

**Consequences:** Debugging becomes extremely difficult. A missing semicolon, a bad `include` path, or a failed `file_put_contents` produces a blank page with zero information.

**Prevention during development:**
- Add these two lines at the top of every PHP file during development: `error_reporting(E_ALL); ini_set('display_errors', 1);`
- Remove or gate behind a constant before deploying to production.
- Check the cPanel error log: cPanel → Logs → Error Log, or the file at `~/logs/error_log`.
- Wrap critical operations (file save, SMS send) in explicit success checks with `die()` or `echo` for immediate feedback during testing.

**Detection (warning signs):**
- PHP form submission results in blank white page.
- Nothing happens when clicking a button and there are no browser console errors.
- No feedback at all after form submit.

**Phase:** All phases — establish this on day one.

---

### Pitfall 8: Form POST Data Lost Due to Missing `name` Attribute on Input

**What goes wrong:** A form is submitted via POST. In PHP, `$_POST['signature']` is empty or undefined even though the canvas data was definitely set in JavaScript.

**Why it happens:** A `<input type="hidden" id="signatureData">` element will NOT appear in `$_POST` because it has no `name` attribute. PHP reads form fields by their `name` attribute, not their `id`. This is one of the most common beginner HTML/PHP mistakes.

**Consequences:** The signature base64 string never reaches PHP. The file is not saved. PHP throws an undefined index notice (or silently fails if error display is off).

**Prevention:**
- Every form field that must be submitted must have a `name` attribute: `<input type="hidden" id="signatureData" name="signature">`.
- Before the canvas toDataURL value is assigned to the hidden input, add `console.log(document.getElementById('signatureData').value)` to verify it is set.
- In PHP, use `isset($_POST['signature'])` before accessing the value.

**Detection (warning signs):**
- `$_POST` is empty or missing the signature key.
- Hidden input has `id` but no `name`.
- PHP undefined index notice for `$_POST['signature']`.

**Phase:** Signature Capture (Phase 2)

---

## v2.0 Critical Pitfalls — Supabase + PHP Integration

These pitfalls are **specific to adding Supabase REST API (PostgREST) to a PHP/cPanel app**. They do not apply to the v1.0 baseline and do not overlap with generic PHP mistakes.

**Confidence:** MEDIUM-HIGH. Sources: Supabase official documentation, verified GitHub discussions, PHP manual. See Sources section.

---

### Pitfall S1: Missing `apikey` Header — "No API key found in request"

**What goes wrong:** PHP cURL sends a request to `https://[project].supabase.co/rest/v1/users` but Supabase returns HTTP 401 with `{"message":"No API key found in request","hint":"No \`apikey\` request header or url param was found."}`. The request never reaches the table.

**Why it happens:** PostgREST requires **two separate headers** for every request — not just the `Authorization` header that most REST APIs use:
1. `apikey: YOUR_SERVICE_ROLE_KEY`
2. `Authorization: Bearer YOUR_SERVICE_ROLE_KEY`

Developers familiar with other APIs send only `Authorization: Bearer` and assume that is sufficient. Supabase PostgREST requires both headers simultaneously, and omitting `apikey` causes a hard 401.

**How to avoid:**
```php
$headers = [
    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json',
    'Prefer: return=representation',
];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
```
Both `apikey` and `Authorization: Bearer` must use the **same key value**. This is not a typo — Supabase PostgREST validates both independently.

**Warning signs:**
- HTTP 401 response from Supabase.
- Response body contains the string "No API key found".
- cURL is returning a non-empty response body but data is empty.

**Phase to address:** v2.0 — Phase 1 (Supabase connection setup). Set up a `supabase_client.php` helper that always includes both headers so they cannot be forgotten.

---

### Pitfall S2: `Authorization: Bearer` Missing the `Bearer` Prefix

**What goes wrong:** The Authorization header is set as `Authorization: YOUR_SERVICE_ROLE_KEY` (no `Bearer` prefix). Supabase returns HTTP 401 or the request is silently rejected. Data is not updated even though no cURL error is thrown.

**Why it happens:** PHP cURL does not validate header syntax. You can set any string as a header value and cURL sends it as-is. The `Bearer` prefix is required by the JWT standard but PHP does not enforce it. This error was the root cause in a widely-referenced Supabase community discussion where PATCH requests appeared to succeed but made no changes.

**How to avoid:**
```php
// WRONG — silently fails
'Authorization: ' . SUPABASE_SERVICE_ROLE_KEY

// CORRECT
'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY
```

**Warning signs:**
- PATCH or POST requests return HTTP 200 but no rows are changed in the database.
- cURL reports no error (`curl_errno` returns 0) but the operation has no effect.
- Supabase dashboard shows no recent writes matching your test.

**Phase to address:** v2.0 — Phase 1 (Supabase connection setup). Verify by checking the Supabase Dashboard → Table Editor after the first test write.

---

### Pitfall S3: PATCH/DELETE Without a Row Filter — Modifies or Deletes ALL Rows

**What goes wrong:** A PATCH or DELETE request is sent to `/rest/v1/users` without a query parameter filter (e.g., `?id=eq.5`). Supabase applies the operation to **every row in the table**, not just the intended one. All users are updated or deleted in a single request.

**Why it happens:** PostgREST is designed for bulk operations. Without a filter, PATCH and DELETE treat the entire table as the target. There is no built-in "require a filter" safeguard at the API level — the behavior is by design. A developer who writes `DELETE FROM users WHERE id = 5` in SQL and then writes the PostgREST version without the equivalent `?id=eq.5` will silently delete every row.

**How to avoid:**
```php
// WRONG — deletes ALL users
curl_setopt($ch, CURLOPT_URL, SUPABASE_URL . '/rest/v1/users');

// CORRECT — deletes only user with id = 5
curl_setopt($ch, CURLOPT_URL, SUPABASE_URL . '/rest/v1/users?id=eq.' . intval($userId));
```

Always validate that `$userId` is a non-empty integer before constructing the URL. If `$userId` is empty, the filter becomes `?id=eq.` which may match nothing OR, in some PostgREST versions, may match all rows.

**Warning signs:**
- Admin delete button wipes the entire user table.
- PATCH updates every user's status instead of one.
- `$userId` variable is never validated for empty/null before the request.

**Recovery steps if this occurs:**
- Supabase Dashboard → Table Editor → immediately check row count.
- Supabase free tier includes Point-In-Time Recovery only on Pro plan. Free tier: use Table Editor → insert rows from the Supabase dashboard manually if you have backups. Prevention is the only reliable recovery path on the free tier.

**Phase to address:** v2.0 — Every phase that builds CRUD operations. Add an assertion before every PATCH/DELETE: `if (empty($userId)) { die('User ID required'); }`.

---

### Pitfall S4: RLS (Row Level Security) Enabled but No Policy — Silent Empty Results

**What goes wrong:** You create the `users` table in Supabase and enable RLS (or it is enabled by default in newer Supabase projects). Every SELECT query returns an empty array `[]` even though the table has rows. No error is thrown — just an empty result.

**Why it happens:** When RLS is enabled on a table and no policy exists, Supabase denies ALL access by default for all roles — including the `service_role` in some configurations. This is not a bug; it is how PostgreSQL RLS works. Developers assume that RLS is either on or off, not that "on with no policy" means "deny everything silently."

**Note for this project:** Using the `service_role` key in PHP (server-side only, as planned) **bypasses RLS entirely**. This means:
- RLS disabled + service_role → full access (current project approach — correct for admin PHP backend)
- RLS enabled + no policy + service_role → still full access (service_role bypasses RLS)
- RLS enabled + no policy + anon key → empty results, no error

**How to avoid for this project:**
1. Use `service_role` key only in PHP (server-side). Never expose it to the browser.
2. If you also add a JS/browser component later, use the `anon` key with explicit RLS policies.
3. Confirm which key you're using by checking the response: if SELECT returns `[]` and the table has data, run this test — change the URL temporarily to include `?limit=1` and check if the table truly has rows via Supabase Dashboard.

**Warning signs:**
- SELECT returns `[]` but Supabase Dashboard → Table Editor shows rows exist.
- No PHP or Supabase error is thrown.
- The problem disappears when you disable RLS in the Supabase Dashboard.

**Phase to address:** v2.0 — Phase 1 (Supabase connection setup). Verify the first SELECT returns real data immediately after connecting.

---

### Pitfall S5: Storing Plaintext Passwords in the Supabase `users` Table

**What goes wrong:** The `password` column in the Supabase `users` table stores the password as the user typed it — plain text. Anyone who queries the table (via SQL, dashboard, or a compromised service_role key) can read every user's password.

**Why it happens:** This project uses a custom `users` table (not Supabase Auth) because it needs custom fields (ID number, foreign worker status, suspend-until-date) that Supabase Auth does not natively support. The developer stores `$_POST['password']` directly into the `password` column without hashing.

**Consequences:** If the Supabase service_role key is ever leaked (e.g., accidentally committed to GitHub), an attacker with that key can read every user's real password. Many users reuse passwords — this becomes a breach that extends far beyond this application.

**How to avoid:**
```php
// WRONG — stores plain text
$data = ['email' => $email, 'password' => $_POST['password']];

// CORRECT — hash before storing
$data = ['email' => $email, 'password_hash' => password_hash($_POST['password'], PASSWORD_BCRYPT)];
```

Login verification:
```php
// Fetch the stored hash from Supabase, then:
if (password_verify($_POST['password'], $storedHash)) {
    // login success
}
```

Name the column `password_hash` not `password` — the name itself communicates "this is a hash, not the real password."

PHP's `password_hash()` with `PASSWORD_BCRYPT` is the correct, built-in, well-maintained solution. It automatically generates a salt and produces a one-way hash. `password_verify()` is constant-time safe — it prevents timing attacks by design (PHP 5.5+, built into all PHP 8.x versions on cPanel).

**Warning signs:**
- The `password` column is type `TEXT` or `VARCHAR` and stores short strings exactly matching what the user typed.
- You can read a known test password directly in the Supabase Dashboard table view.
- Column is named `password` instead of `password_hash`.

**Phase to address:** v2.0 — Phase 1 (schema creation). The column must be `password_hash TEXT` from the start. Retrofitting hashing after plaintext passwords exist requires a password reset for all users.

---

### Pitfall S6: Supabase `service_role` Key Hardcoded in PHP Files Committed to GitHub

**What goes wrong:** The `service_role` key (and Supabase project URL) is hardcoded as a PHP constant directly in `admin.php` or `supabase_client.php`. The file is committed to the public GitHub repo `github.com/ChemoIT/FirstApp`. The key is now public on the internet and can be used by anyone to read, modify, or delete every row in the database.

**Why it happens:** During development, it is easier to hardcode credentials than to set up environment variables. The developer forgets that the GitHub repo is public. Supabase credentials look like long random strings and are easy to overlook in a diff.

**Consequences:** With the `service_role` key, an attacker has unrestricted access to the entire Supabase project — equivalent to root access to the database. Supabase confirmed that this key "bypasses any and all Row Level Security policies." This is a P0 security incident.

**How to avoid:**
```php
// WRONG — key in PHP file committed to git
define('SUPABASE_KEY', 'eyJhbGci...[service_role_key]...');

// CORRECT option A — PHP reads from environment variable set in cPanel
define('SUPABASE_KEY', getenv('SUPABASE_SERVICE_ROLE_KEY'));

// CORRECT option B — PHP reads from a config file excluded from git
require_once __DIR__ . '/config.secret.php'; // in .gitignore
```

`.gitignore` must list the config file **before the first commit**:
```
config.secret.php
.env
```

Add a check in GitHub Actions that scans for the key pattern before deploying. If you discover the key was already committed: immediately rotate it in Supabase Dashboard → Settings → API → Reset Service Role Key.

**Warning signs:**
- `SUPABASE_KEY` constant appears in a file tracked by git (`git status` shows it, `git log` shows it in a past commit).
- The `.gitignore` does not list the file containing credentials.
- `git grep "eyJhbGci"` finds a match in any tracked file.

**Phase to address:** v2.0 — Phase 1 (project setup, before the first commit). This cannot be fixed retroactively without key rotation.

---

### Pitfall S7: PHP cURL Disabling SSL Verification to "Fix" Connection Errors

**What goes wrong:** The first cURL request to Supabase fails with "SSL certificate problem: unable to get local issuer certificate." The developer adds `curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false)` to make the error go away. The error disappears but SSL verification is now disabled for all Supabase API calls.

**Why it happens:** cPanel shared hosting sometimes has an outdated CA bundle (`cacert.pem`), causing SSL verification failures against modern certificates. The "quick fix" of disabling verification is widely found in Stack Overflow answers. PHP does not warn that this creates a man-in-the-middle vulnerability.

**Consequences:** With `CURLOPT_SSL_VERIFYPEER` disabled, any network intermediary (including the cPanel hosting provider's own infrastructure) could intercept and modify Supabase API responses. The Supabase `service_role` key is sent in HTTP headers over an unverified connection.

**How to avoid:**
```php
// WRONG — disables SSL verification entirely
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// CORRECT — provide updated CA bundle path
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem'); // download from curl.se
```

Download the latest `cacert.pem` from https://curl.se/docs/caextract.html and place it in the project directory. If cPanel's own certificate bundle is outdated, this overrides it with a current one. Most cPanel hosts in 2026 have modern CA bundles — try without `CURLOPT_CAINFO` first; only add it if you get the SSL error.

**Warning signs:**
- `CURLOPT_SSL_VERIFYPEER` is set to `false` anywhere in the codebase.
- The cURL request works but you're not sure why you added the false flag.
- `git grep "VERIFYPEER"` finds a match set to `0` or `false`.

**Phase to address:** v2.0 — Phase 1 (first Supabase connection). If the SSL error appears, fix it properly with the CA bundle — never with the false flag.

---

### Pitfall S8: PATCH Returns HTTP 200 but No Rows Are Updated (Wrong JSON Body)

**What goes wrong:** A PHP cURL PATCH request to update a user's status returns HTTP 200 with no error. The Supabase Dashboard shows the row is unchanged. The update silently did nothing.

**Why it happens:** Two sub-causes, both specific to the PostgREST REST API:

**Sub-cause A:** The request body is double-encoded JSON:
```php
// WRONG — json_encode of an already-encoded string
$body = json_encode('{"status": "blocked"}'); // produces "\"{\\"status\\":\\"blocked\\"}\""

// CORRECT
$body = json_encode(['status' => 'blocked']); // produces {"status":"blocked"}
```

**Sub-cause B:** The `Content-Type: application/json` header is missing. PostgREST defaults to form-encoded parsing when `Content-Type` is absent. The JSON body is ignored and the update has no fields to apply.

**How to avoid:**
```php
$data = ['status' => 'blocked', 'suspended_until' => null];
$body = json_encode($data); // always json_encode an array/object, never a string

$ch = curl_init(SUPABASE_URL . '/rest/v1/users?id=eq.' . intval($userId));
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
    'Content-Type: application/json',       // required for PATCH
    'Prefer: return=representation',         // return updated row for verification
]);
```

Use `Prefer: return=representation` to get the updated row back — if the response body is empty, the update did not apply.

**Warning signs:**
- PATCH returns HTTP 200 but the response body is `[]` (empty array).
- Supabase Dashboard row shows the old value after a successful PHP update.
- `json_encode` is called on a string variable, not an array.

**Phase to address:** v2.0 — Phase covering user status updates (block, suspend). Test every PATCH by reading the row back immediately after.

---

### Pitfall S9: Hebrew Text Garbled in Supabase — Wrong PHP File Encoding

**What goes wrong:** Hebrew text entered in the admin form (names, fields) is saved to Supabase with garbled characters (e.g., `×©×¨×•×Ÿ` instead of `שרון`). The data is stored correctly in Supabase (UTF-8 by default) but the PHP file that sends the data is saved in a non-UTF-8 encoding.

**Why it happens:** Supabase PostgreSQL uses UTF-8 natively and stores Hebrew correctly. The problem is upstream: the PHP file that processes the form POST was saved by the code editor in Windows-1255 or ISO-8859-8 encoding (a common issue on Windows systems with Hebrew system locale). The Hebrew string literals in the PHP code itself are then sent as wrong byte sequences, and Supabase stores the garbage.

**This is distinct from the Micropay SMS encoding issue** (Pitfall 5). The Micropay issue requires converting FROM UTF-8 TO iso-8859-8. The Supabase issue requires ensuring the PHP file IS UTF-8 throughout — no conversion needed for Supabase.

**How to avoid:**
- Confirm every PHP file is saved as UTF-8 (no BOM) in your editor. In VS Code: bottom status bar → click the encoding → "Save with Encoding" → UTF-8.
- Add `<meta charset="UTF-8">` to every HTML page.
- Do NOT add `iconv` conversion before sending data to Supabase. Supabase expects raw UTF-8.
- Test by entering a Hebrew name via the admin form, then immediately reading it back via a SELECT and comparing to what was typed.

**Warning signs:**
- Hebrew text stored in Supabase looks like Latin garbage characters in the Dashboard.
- The same Hebrew text works in form inputs but arrives garbled after PHP processes it.
- PHP file encoding is not UTF-8 (VS Code shows a different encoding in the status bar).

**Phase to address:** v2.0 — Phase 1 (schema + first INSERT test). Catch this before any real data is entered.

---

### Pitfall S10: Hardcoded Auth Replaced Too Early — Login Breaks Before DB Auth Works

**What goes wrong:** During v2.0 development, the developer removes the hardcoded `sharonb/1532` credentials from `index.php` and replaces them with the new Supabase-backed auth logic. The Supabase auth code has a bug (wrong header, wrong column name, encoding issue). The app is now deployed with no working login at all. The admin page cannot be accessed.

**Why it happens:** The replacement is done in a single step — old code is deleted, new code is written — rather than running both in parallel during transition. If the new code fails for any reason, there is no fallback.

**Consequences:** The application is locked. Sharon cannot log in to test or use the app until the bug is found and fixed. In a production app, this would be a complete outage.

**How to avoid:**
1. Build and test the new Supabase auth logic as a **separate endpoint** (`login_v2.php`) first.
2. Only cut over `index.php` to use the new logic after `login_v2.php` succeeds end-to-end on live data.
3. Keep a commented-out fallback: `// if (EMERGENCY_BYPASS) { /* hardcoded login */ }` during the transition week.
4. Deploy the changeover as a separate commit so it can be git-reverted instantly.

**Warning signs:**
- index.php is modified to remove hardcoded credentials before `login_v2.php` is tested live.
- No fallback exists if the Supabase SELECT fails.
- The new auth code has never been tested against the live Supabase project.

**Phase to address:** v2.0 — The auth cutover phase (whichever phase replaces hardcoded login). Do not combine "write new auth code" and "remove old auth code" in the same deploy.

---

## v1.0 Moderate Pitfalls

---

### Pitfall 9: High-DPI (Retina) Screen Makes Signature Look Blurry

**What goes wrong:** On an iPhone or high-DPI Android screen, the drawn signature looks sharp on the device but the saved PNG file is blurry or low-resolution.

**Why it happens:** Modern phones have `devicePixelRatio` of 2 or 3. A canvas set to CSS width 300px on a Retina screen is physically 600 or 900 device pixels but the canvas drawing buffer is still 300 pixels. The result is scaled up and appears blurry.

**Prevention:**
```javascript
const dpr = window.devicePixelRatio || 1;
canvas.width = canvas.offsetWidth * dpr;
canvas.height = canvas.offsetHeight * dpr;
ctx.scale(dpr, dpr);
```
Set canvas size AFTER this adjustment, before any drawing.

**Phase:** Signature Capture (Phase 2)

---

### Pitfall 10: Session Fixation — Login Does Not Regenerate Session ID

**What goes wrong:** The session ID is the same before and after login. An attacker who obtains the pre-login session ID (e.g., via network sniffing on HTTP) can use it after the user logs in.

**Why it happens:** `session_start()` reuses an existing session ID. Most beginner tutorials skip `session_regenerate_id()`.

**Prevention:**
- Call `session_regenerate_id(true)` immediately after a successful login, before setting `$_SESSION['logged_in'] = true`.
- For a learning project on HTTPS this risk is low, but building the habit now matters.

**Phase:** Authentication (Phase 1)

---

### Pitfall 11: HTTPS Not Enforced — Credentials and Session Tokens Sent in Clear Text

**What goes wrong:** The cPanel hosting serves the site over both HTTP and HTTPS. The login form posts credentials over HTTP if the user navigates to the HTTP URL. The Micropay token is also exposed in transit.

**Prevention:**
- Add to the root `.htaccess`: `RewriteEngine On` / `RewriteCond %{HTTPS} off` / `RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]`
- cPanel provides a free Let's Encrypt SSL certificate — ensure it is active before going live.

**Phase:** Authentication (Phase 1) — do this before first deploy.

---

### Pitfall 12: Signing Link Is Not Unique — Anyone Can Sign

**What goes wrong:** The SMS sends `https://ch-ah.info/FirstApp/sign.php` (a fixed URL). Anyone with the URL can open the signing page and submit a signature, even if they are not the intended recipient.

**Why it happens:** For a simple learning project the link is hardcoded with no token or identifier. This is acceptable for the learning phase but becomes a real problem if the project evolves.

**Prevention for learning phase:** Document this known limitation explicitly. For the learning project, the single fixed phone number target means this is low risk.

**Prevention if the project grows:** Generate a unique token per dispatch, store it (even in a flat file), validate on the sign page, and mark as used after one submission.

**Phase:** Out of scope for learning phase — flag for future milestone.

---

### Pitfall 13: Canvas `toDataURL` Called Before User Signs Anything

**What goes wrong:** The user clicks "Submit" without drawing a signature. `canvas.toDataURL()` returns the canvas background (a white rectangle). PHP saves it as a valid PNG file. The system confirms "נחתם" (signed) but there is no signature.

**Prevention:**
- Track whether the user has drawn anything using a boolean flag: set it to `true` on the first `mousedown`/`touchstart` event.
- On form submit, check the flag: `if (!hasSigned) { alert('נא לחתום לפני השליחה'); return false; }`

**Phase:** Signature Capture (Phase 2)

---

## Minor Pitfalls

---

### Pitfall 14: RTL Layout Breaking on Input Fields

**What goes wrong:** Hebrew text in `<input>` and `<textarea>` fields aligns left and the cursor starts on the left, making it feel backwards for Hebrew users.

**Prevention:** Add `dir="rtl"` to the `<html>` tag and `lang="he"`. For individual fields: `<input dir="rtl">`. CSS: `body { direction: rtl; text-align: right; }`

**Phase:** Any phase with forms — set in the base HTML template.

---

### Pitfall 15: File Permissions on signatures/ Folder Too Restrictive

**What goes wrong:** PHP tries to write a PNG file to `signatures/` and fails silently because the directory permissions are set to 644 (owner read/write only, no execute for traversal). PHP running as the web server user cannot write to the directory.

**Prevention:** Set the `signatures/` directory permissions to `755` (owner rwx, group r-x, world r-x). Files written inside should be `644`. Set via cPanel File Manager or FTP client. Do NOT set `777` — this is a security risk on shared hosting.

**Detection:** `file_put_contents` returns `false`. PHP warning: "failed to open stream: Permission denied."

**Phase:** Signature Storage (Phase 2)

---

### Pitfall 16: PHP `include`/`require` Path Breaks on Deployment

**What goes wrong:** Code uses relative paths like `include('../config.php')`. Works locally but breaks on the server because the working directory differs depending on how PHP resolves relative paths.

**Prevention:** Use `__DIR__` for reliable path resolution: `include(__DIR__ . '/../config.php');`

**Phase:** Any phase with multiple PHP files.

---

### Pitfall S11: Supabase Free Tier Project Paused After 1 Week of Inactivity

**What goes wrong:** The Supabase free tier project is automatically paused after 1 week of inactivity. The first time the admin page is opened after a pause, all Supabase API calls fail with a connection error. The project must be manually resumed from the Supabase Dashboard.

**Why it happens:** This is a Supabase free tier policy, not a bug. Free projects are paused to conserve resources. The project URL still exists but the database is offline until resumed.

**How to avoid:**
- Resume the project from Supabase Dashboard → Projects → click "Resume" if you see the paused status.
- For the learning project, this is an acceptable constraint — just know it exists and do not diagnose it as a code error.
- Consider upgrading to Pro ($25/mo) if the app needs to be always-on. For this learning project, the free tier is appropriate.

**Warning signs:**
- All Supabase API calls suddenly return connection errors after a period of inactivity.
- Supabase Dashboard shows "Project paused" badge on the project.

**Phase to address:** v2.0 — Mention in project documentation so this is not confused with a code bug.

---

## Technical Debt Patterns

Shortcuts that seem reasonable but create long-term problems.

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Hardcoded `sharonb/1532` credentials | No DB needed for v1.0 | Cannot support multiple users; must be replaced in v2.0 | v1.0 only — explicit v2.0 upgrade planned |
| Admin page with no authentication | Faster to build; learning focus | Anyone who knows the URL has full user CRUD access | Learning project only — document the risk |
| Plaintext password storage | No hashing code to write | Password breach exposes real passwords, user harm extends beyond this app | Never acceptable — use `password_hash()` always |
| `CURLOPT_SSL_VERIFYPEER = false` | Fixes SSL errors quickly | Man-in-the-middle attack on Supabase API key transfer | Never acceptable — fix CA bundle instead |
| Supabase credentials in PHP file tracked by git | Easy to deploy | Key exposed to anyone with repo access, including future contributors | Never acceptable — use environment variables or `.gitignore`d config file |
| No filter on PATCH/DELETE | Slightly shorter URL | One bug deletes/updates entire table | Never acceptable — always add `?id=eq.X` |

---

## Integration Gotchas

Common mistakes when connecting to external services.

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| Supabase PostgREST | Only `Authorization: Bearer` header — missing `apikey` | Always send both `apikey` and `Authorization: Bearer` headers |
| Supabase PostgREST | `Authorization: KEY` without `Bearer` prefix | `Authorization: Bearer KEY` — the prefix is mandatory |
| Supabase PostgREST | `json_encode` on a string, not an array | `json_encode(['status' => 'blocked'])` — always encode a PHP array |
| Supabase PostgREST | PATCH/DELETE with no URL filter | Always append `?id=eq.X` before any mutation |
| Supabase PostgREST | Storing plaintext passwords in custom table | `password_hash($pw, PASSWORD_BCRYPT)` before INSERT; `password_verify()` on login |
| Micropay SMS | UTF-8 Hebrew sent directly | `iconv('UTF-8', 'ISO-8859-8', $msg)` then `urlencode()` |
| Micropay SMS | Token in client-side JS | Token in PHP only, never in browser-visible code |
| PHP cURL (any HTTPS) | `CURLOPT_SSL_VERIFYPEER = false` to fix SSL errors | Provide updated `cacert.pem` via `CURLOPT_CAINFO` |

---

## Security Mistakes

Domain-specific security issues beyond general web security.

| Mistake | Risk | Prevention |
|---------|------|------------|
| `service_role` key in PHP file committed to public GitHub | Full database access for anyone — equivalent to root access | Store in env variable or `.gitignore`d file; run `git grep` before every push |
| `service_role` key echoed to JavaScript or HTML response | Key visible in browser DevTools; full database access for any page visitor | Key only in PHP, never echoed — check View Source after every deploy |
| Plaintext passwords in Supabase `users` table | Any key leak or dashboard access reveals all user passwords | Always `password_hash()` before INSERT; column named `password_hash` |
| No filter on PATCH/DELETE REST calls | One bug updates or deletes entire table | Validate row ID is non-empty integer before every mutation |
| Admin page at known URL with no auth check | Full user CRUD for anyone who knows or guesses the URL | Accepted as learning project trade-off; document explicitly; do not host on public-facing production system |
| `CURLOPT_SSL_VERIFYPEER = false` | Man-in-the-middle attack on Supabase API key | Correct CA bundle — never disable verification |

---

## "Looks Done But Isn't" Checklist

Things that appear complete but are missing critical pieces.

- [ ] **Supabase connection:** Returns data for the first SELECT — verify rows appear, not just HTTP 200
- [ ] **User CREATE:** Password stored as `password_hash` — check Supabase Dashboard column value starts with `$2y$` (bcrypt signature)
- [ ] **User LOGIN:** `password_verify()` called against stored hash — not `== $_POST['password']`
- [ ] **User UPDATE:** PATCH URL includes `?id=eq.X` — verify only the target row changed
- [ ] **User DELETE:** DELETE URL includes `?id=eq.X` — verify row count decreased by exactly 1
- [ ] **Credentials in git:** `git grep "eyJhbGci"` and `git grep "supabase.co"` return only expected files, not config files
- [ ] **SSL verification:** `CURLOPT_SSL_VERIFYPEER` is not set to `false` anywhere
- [ ] **Hebrew data round-trip:** Enter Hebrew name → save → read back → display — text matches exactly
- [ ] **Auth cutover tested:** Login with a test Supabase user succeeds before hardcoded credentials are removed
- [ ] **RLS decision documented:** Consciously decided to use service_role (bypasses RLS) — not using anon key by mistake

---

## Recovery Strategies

When pitfalls occur despite prevention, how to recover.

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| `service_role` key exposed on GitHub | HIGH | 1. Immediately rotate key: Supabase Dashboard → Settings → API → Reset. 2. Update the new key in all server configs. 3. Force-push or create a new commit removing the key. 4. Add `.gitignore` entry for the config file. |
| PATCH/DELETE without filter — table modified | HIGH | 1. Check Supabase Dashboard for damage extent. 2. Free tier has no automatic backups — recover from any manual backups or re-enter data. 3. Fix the bug in code before next test. |
| Plaintext passwords already in database | HIGH | 1. Hash all existing passwords: read each, hash it, update the row. 2. Force all users to reset passwords if the plaintext data was ever accessible. |
| Wrong JSON encoding — PATCH did nothing | LOW | Fix `json_encode()` call to encode a PHP array. Re-run the update. No data lost — the previous value is still in the DB. |
| `Bearer` prefix missing — requests rejected | LOW | Add `Bearer ` prefix to Authorization header string. No data lost. |
| Hebrew garbled in database | MEDIUM | 1. Delete garbled rows. 2. Fix PHP file encoding to UTF-8. 3. Re-enter data via admin form. |
| Auth cutover breaks login | MEDIUM | 1. Git revert the cutover commit. 2. Debug `login_v2.php` separately. 3. Re-deploy cutover only after separate test passes. |
| Supabase project paused | LOW | Resume from Supabase Dashboard. No data lost. Takes ~30 seconds. |

---

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|----------------|------------|
| PHP Login (v1.0 Phase 1) | `session_start()` missing or after output | Add to line 1 of every session-using file |
| SMS Dispatch (v1.0 Phase 1) | API token in client JavaScript | All Micropay calls in PHP only, verify with View Source |
| SMS Hebrew (v1.0 Phase 1) | UTF-8 string sent without iconv conversion | `iconv('UTF-8', 'ISO-8859-8', $msg)` before `urlencode` |
| HTTPS setup (v1.0 Phase 1) | HTTP not redirected to HTTPS | `.htaccess` redirect + Let's Encrypt cert in cPanel |
| Canvas touch (v1.0 Phase 2) | Works on desktop, broken on iOS | Test on real phone, add `touchstart`/`touchmove`/`touchend` listeners |
| Canvas high-DPI (v1.0 Phase 2) | Blurry signature on Retina screens | Apply `devicePixelRatio` scaling before drawing |
| Canvas submit (v1.0 Phase 2) | Hidden input missing `name` attribute | Every submitted field needs `name=`, not just `id=` |
| Canvas blank PNG (v1.0 Phase 2) | Transparent canvas or undecoded base64 | Fill white background; strip data URL prefix in PHP |
| File storage (v1.0 Phase 2) | `signatures/` dir web-accessible | Add `.htaccess` `Deny from all` in that directory |
| Error handling (all v1.0) | Blank page with no debug info | `display_errors = 1` during dev; check cPanel error log |
| Supabase setup (v2.0 Phase 1) | Missing `apikey` header → 401 | Always send both `apikey` and `Authorization: Bearer` headers |
| Supabase setup (v2.0 Phase 1) | `Bearer` prefix missing → silent failure | Set `Authorization: Bearer KEY` with the prefix |
| Supabase setup (v2.0 Phase 1) | SSL verification disabled | Never use `CURLOPT_SSL_VERIFYPEER = false` |
| Supabase setup (v2.0 Phase 1) | Key in git-tracked PHP file | Config file in `.gitignore` before first commit |
| User schema (v2.0 Phase 1) | Plaintext passwords in DB | Column named `password_hash`, use `password_hash()` always |
| User CRUD (v2.0 Phase 2) | PATCH/DELETE without row filter | Validate `$userId` non-empty; always `?id=eq.X` in URL |
| User CRUD (v2.0 Phase 2) | Wrong JSON encoding on PATCH body | `json_encode(['key' => 'value'])` — never json_encode a string |
| Hebrew data (v2.0 Phase 2) | PHP file saved in wrong encoding | All PHP files UTF-8 (no BOM); test Hebrew round-trip |
| Auth cutover (v2.0 Phase 3) | Old auth removed before new auth tested | Build `login_v2.php` separately; test live; then cut over |
| RLS (v2.0 all phases) | Empty results from SELECT | Confirm service_role bypasses RLS; never use anon key for admin backend |

---

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Missing `apikey` header (S1) | v2.0 Phase 1 | First SELECT returns rows, not empty array or 401 |
| Missing `Bearer` prefix (S2) | v2.0 Phase 1 | PATCH test: read row back and confirm value changed |
| PATCH/DELETE without filter (S3) | v2.0 Phase 2 (CRUD) | Count rows before/after delete — only decreases by 1 |
| RLS empty results (S4) | v2.0 Phase 1 | SELECT immediately after INSERT returns the inserted row |
| Plaintext passwords (S5) | v2.0 Phase 1 (schema) | Dashboard: password_hash column starts with `$2y$` |
| Key in git (S6) | v2.0 Phase 1 (before first commit) | `git grep "eyJhbGci"` finds no matches in tracked files |
| SSL verification disabled (S7) | v2.0 Phase 1 | `grep -r "VERIFYPEER" .` finds no `false` values |
| Wrong JSON body (S8) | v2.0 Phase 2 (CRUD) | `Prefer: return=representation` response shows updated values |
| Hebrew encoding (S9) | v2.0 Phase 1 (first test) | Hebrew name round-trip test: enter → save → read → display |
| Auth cutover too early (S10) | v2.0 Phase 3 (auth replacement) | login_v2.php works with test user before index.php is changed |
| Supabase free tier pause (S11) | v2.0 all | Document behavior; check Dashboard if all calls fail suddenly |

---

## Confidence Assessment

| Pitfall Area | Confidence | Basis |
|--------------|------------|-------|
| PHP session pitfalls (v1.0) | HIGH | Core PHP behavior, extensively documented |
| Micropay token exposure (v1.0) | HIGH | Standard client/server security principle |
| Canvas touch events (v1.0) | HIGH | Browser API specification, well-known mobile issue |
| Canvas blank PNG (v1.0) | HIGH | Known canvas/PHP base64 pattern |
| Hebrew SMS encoding (v1.0) | HIGH | Micropay API constraint + iso-8859-8 standard |
| Supabase dual-header requirement (S1, S2) | HIGH | Official Supabase PostgREST docs + GitHub discussion #4824 confirmed |
| Filter required for PATCH/DELETE (S3) | HIGH | Official Supabase JS/Python/REST docs consistently state this |
| RLS empty results behavior (S4) | HIGH | Official Supabase troubleshooting docs + community discussion |
| service_role bypasses RLS (S4 note) | HIGH | Official Supabase API key docs |
| Plaintext password danger (S5) | HIGH | PHP `password_hash` manual; universal security principle |
| service_role key exposure risk (S6) | HIGH | Official Supabase security docs; GitHub discussion #38834 |
| SSL verification disabling (S7) | HIGH | cURL documentation + Sourcery security database |
| Wrong JSON body for PATCH (S8) | HIGH | GitHub discussion #4824 — exact error confirmed in Supabase community |
| Hebrew encoding for Supabase (S9) | MEDIUM | Supabase UTF-8 default confirmed; Windows editor encoding trap is training-data knowledge |
| Auth cutover risk (S10) | MEDIUM | General software deployment risk pattern; not Supabase-specific |
| Free tier pausing (S11) | MEDIUM | Known Supabase free tier policy; verify current pause duration in Dashboard |

---

## Sources

**v1.0 Sources:**
- PHP session documentation: https://www.php.net/manual/en/book.session.php
- HTML Canvas API: https://developer.mozilla.org/en-US/docs/Web/API/Canvas_API
- Touch Events API: https://developer.mozilla.org/en-US/docs/Web/API/Touch_events
- `devicePixelRatio`: https://developer.mozilla.org/en-US/docs/Web/API/Window/devicePixelRatio
- PHP `iconv`: https://www.php.net/manual/en/function.iconv.php
- PHP `base64_decode`: https://www.php.net/manual/en/function.base64-decode.php

**v2.0 Sources:**
- Supabase API key documentation (anon vs service_role): https://supabase.com/docs/guides/api/api-keys
- Supabase dual-header requirement confirmed: https://github.com/supabase-community/postgrest-go/issues/29
- Supabase PATCH not updating — `Bearer` prefix and JSON encoding: https://github.com/orgs/supabase/discussions/4824
- Supabase RLS empty results troubleshooting: https://supabase.com/docs/guides/troubleshooting/why-is-my-select-returning-an-empty-data-array-and-i-have-data-in-the-table-xvOPgx
- Supabase securing your API + RLS: https://supabase.com/docs/guides/api/securing-your-api
- service_role key exposure risk (GitHub discussion): https://github.com/orgs/supabase/discussions/38834
- Supabase best practices (Leanware): https://www.leanware.co/insights/supabase-best-practices
- PHP `password_hash` manual: https://www.php.net/manual/en/function.password-hash.php
- PHP SSL CURLOPT_SSL_VERIFYPEER security vulnerability: https://www.sourcery.ai/vulnerabilities/php-lang-security-curl-ssl-verifypeer-off
- Supabase RLS disabled — 170+ apps exposed CVE-2025-48757: https://byteiota.com/supabase-security-flaw-170-apps-exposed-by-missing-rls/
- Supabase delete requires filters (JS reference, same REST behavior): https://supabase.com/docs/reference/javascript/delete
- Supabase PostgREST error codes: https://supabase.com/docs/guides/api/rest/postgrest-error-codes

---
*Pitfalls research for: PHP + Supabase REST API user management on cPanel shared hosting*
*v1.0 researched: 2026-02-27 | v2.0 Supabase milestone added: 2026-02-28*
