# Domain Pitfalls

**Domain:** PHP signature dispatch web app on cPanel shared hosting
**Project:** FirstApp — Signature Dispatch System
**Researched:** 2026-02-27
**Confidence:** HIGH (well-established PHP/browser patterns) | Note: WebSearch unavailable; based on expert domain knowledge for these specific technologies

---

## Critical Pitfalls

Mistakes that cause silent failures, security breaches, or a broken user experience with no obvious error.

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

## Moderate Pitfalls

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

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|----------------|------------|
| PHP Login (Phase 1) | `session_start()` missing or after output | Add to line 1 of every session-using file |
| SMS Dispatch (Phase 1) | API token in client JavaScript | All Micropay calls in PHP only, verify with View Source |
| SMS Hebrew (Phase 1) | UTF-8 string sent without iconv conversion | `iconv('UTF-8', 'ISO-8859-8', $msg)` before `urlencode` |
| HTTPS setup (Phase 1) | HTTP not redirected to HTTPS | `.htaccess` redirect + Let's Encrypt cert in cPanel |
| Canvas touch (Phase 2) | Works on desktop, broken on iOS | Test on real phone, add `touchstart`/`touchmove`/`touchend` listeners |
| Canvas high-DPI (Phase 2) | Blurry signature on Retina screens | Apply `devicePixelRatio` scaling before drawing |
| Canvas submit (Phase 2) | Hidden input missing `name` attribute | Every submitted field needs `name=`, not just `id=` |
| Canvas blank PNG (Phase 2) | Transparent canvas or undecoded base64 | Fill white background; strip data URL prefix in PHP |
| File storage (Phase 2) | `signatures/` dir web-accessible | Add `.htaccess` `Deny from all` in that directory |
| File storage (Phase 2) | Directory permissions too restrictive | Set to `755`, not `644` or `777` |
| Error handling (all) | Blank page with no debug info | `display_errors = 1` during dev; check cPanel error log |

---

## Confidence Assessment

| Pitfall Area | Confidence | Basis |
|--------------|------------|-------|
| PHP session pitfalls | HIGH | Core PHP behavior, extensively documented |
| Micropay token exposure | HIGH | Standard client/server security principle |
| Canvas touch events | HIGH | Browser API specification, well-known mobile issue |
| Canvas blank PNG | HIGH | Known canvas/PHP base64 pattern |
| Hebrew SMS encoding | HIGH | Micropay API constraint stated in project requirements; iso-8859-8 is a defined standard |
| Directory permissions | HIGH | cPanel/Linux file permission fundamentals |
| High-DPI canvas | HIGH | `devicePixelRatio` is a well-established browser property |
| HTTP→HTTPS redirect | HIGH | Standard `.htaccess` pattern for cPanel |

---

## Sources

- PHP session documentation: https://www.php.net/manual/en/book.session.php (HIGH confidence)
- HTML Canvas API: https://developer.mozilla.org/en-US/docs/Web/API/Canvas_API (HIGH confidence)
- Touch Events API: https://developer.mozilla.org/en-US/docs/Web/API/Touch_events (HIGH confidence)
- `devicePixelRatio`: https://developer.mozilla.org/en-US/docs/Web/API/Window/devicePixelRatio (HIGH confidence)
- PHP `iconv`: https://www.php.net/manual/en/function.iconv.php (HIGH confidence)
- PHP `base64_decode`: https://www.php.net/manual/en/function.base64-decode.php (HIGH confidence)
- cPanel `.htaccess` access control: standard Apache `mod_rewrite` and `mod_authz_host` (HIGH confidence)
- Note: WebSearch was unavailable in this session. All pitfalls are based on well-established, stable PHP/browser/hosting fundamentals directly applicable to the stated project constraints.
