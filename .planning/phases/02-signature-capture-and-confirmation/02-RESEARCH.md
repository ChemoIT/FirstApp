# Phase 2: Signature Capture and Confirmation — Research

**Researched:** 2026-02-28
**Domain:** HTML5 Canvas signature capture (signature_pad), PHP PNG file save, .htaccess folder protection, Micropay confirmation SMS
**Confidence:** HIGH

---

## Summary

Phase 2 builds the recipient-facing half of the signing loop: a mobile-optimized Hebrew signing page, a PHP endpoint that saves the signature as PNG, and a confirmation SMS back to Sharon. The technology choices are tightly constrained by Phase 1 decisions — PHP on cPanel, cURL for Micropay, iconv for Hebrew encoding — and those locked patterns are re-used verbatim here.

The single most important discovery in this research is a **version shift**: Phase 1 documents reference `signature_pad 4.x`, but the library is now at **v5.1.3** (December 2025). The v5.x API is compatible with the core methods (`clear()`, `isEmpty()`, `toDataURL()`) but the event system changed — `onBegin`/`onEnd` callback options became `addEventListener("beginStroke", ...)` / `addEventListener("endStroke", ...)`. The correct CDN URL to pin is `https://cdn.jsdelivr.net/npm/signature_pad@5.1.3/dist/signature_pad.umd.min.js`. Plans must use v5.1.3 and its event API, not the v4 callback-style API.

The signature save pipeline is a two-step operation: (1) JavaScript calls `signaturePad.toDataURL("image/png")` on the canvas to get a base64 data URL, sends it to PHP via `fetch()` POST with JSON body; (2) PHP strips the `data:image/png;base64,` prefix, base64-decodes, validates with `imagecreatefromstring()` (GD), then writes with `imagepng()` to `signatures/`. Saving via `file_put_contents()` alone is acceptable for a canvas-generated image because the source is the browser canvas, not a user-uploaded file — but using GD's `imagepng()` is the safer approach that also validates the image is actually a valid PNG.

**Primary recommendation:** Pin signature_pad at v5.1.3 via jsdelivr CDN. Use `toDataURL("image/png")` → POST JSON to `api/save-signature.php` → `imagecreatefromstring()` + `imagepng()` save in PHP. Apply `touch-action: none` CSS to the canvas element. Reuse all Phase 1 Micropay/cURL patterns verbatim for the confirmation SMS.

---

## Prior Decisions Inherited from Phase 1

These are locked — research validates them, does not reconsider them.

| Decision | Implementation | Confirmed |
|----------|---------------|-----------|
| PHP backend only | All server logic in `api/*.php` | YES — cPanel constraint |
| `api/` separation | Secrets never in browser-accessible files | YES — Pattern 1 from Phase 1 |
| iconv UTF-8 → ISO-8859-8 for Hebrew SMS | Required for Micropay | YES — working in `send-sms.php` |
| cURL for Micropay (not file_get_contents) | CURLOPT_RETURNTRANSFER, 10s timeout | YES — working in `send-sms.php` |
| `signatures/.htaccess` protection | `Require all denied` dual-syntax | YES — already exists |
| `config.php` single source of truth | MICROPAY_TOKEN, ADMIN_PHONE, BASE_URL | YES — already defined |

**No CONTEXT.md exists for Phase 2.** The above are carried forward from Phase 1 deliverables and the phase requirements listed in the objective.

---

## Standard Stack

### Core

| Library/Technology | Version | Purpose | Why Standard |
|-------------------|---------|---------|--------------|
| signature_pad | 5.1.3 | Touch + mouse signature drawing on HTML5 canvas | Industry standard for HTML5 canvas signatures; handles Bezier smoothing, pointer events, retina DPI, touch/mouse unification |
| PHP GD (imagecreatefromstring + imagepng) | Built-in (PHP 8.2) | Validate and save base64 PNG server-side | GD is always available on cPanel; validates the PNG is a real image before saving; strips potential embedded metadata |
| Vanilla JS Fetch API | ES6+ | POST signature data URL to PHP endpoint | No new dependencies; consistent with Phase 1 dispatch pattern |
| PHP cURL | Built-in | Confirmation SMS via Micropay | Already working in send-sms.php — use identical pattern |

### Supporting

| Technology | Version | Purpose | When to Use |
|------------|---------|---------|-------------|
| CSS `touch-action: none` | CSS3 Living Standard | Prevent page scroll while finger-drawing | Applied to canvas element — prevents browser from intercepting touch events during signature drawing |
| `window.devicePixelRatio` | Browser API | Scale canvas for retina/high-DPI screens | Applied in canvas resize function to prevent blurry signatures on iPhone/Android |
| PHP `uniqid('sig_', true)` | Built-in | Generate unique filename for each PNG | Microsecond prefix prevents filename collision; `true` adds entropy |

### CDN URL (Pinned)

```html
<!-- Source: jsdelivr.com/package/npm/signature_pad — verified 2026-02-28 -->
<script src="https://cdn.jsdelivr.net/npm/signature_pad@5.1.3/dist/signature_pad.umd.min.js"></script>
```

Alternative (cdnjs — verified at v5.1.1, slightly behind):
```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/signature_pad/5.1.1/signature_pad.umd.min.js"></script>
```

**Use jsdelivr at 5.1.3.** It is the newer confirmed version.

### Alternatives Considered

| Instead of | Could Use | Why We Don't |
|------------|-----------|-------------|
| signature_pad 5.1.3 | Raw canvas pointer events | signature_pad handles Bezier smoothing, velocity-based width, pointer event normalization, devicePixelRatio, and cross-browser touch/mouse — hand-rolling this is a multi-day task with known edge case bugs |
| `imagecreatefromstring()` + `imagepng()` | `file_put_contents(base64_decode(...))` | GD approach validates the PNG is a real image; for canvas output it is slightly overkill but safer; costs one extra line of code |
| `uniqid()` for filename | `time()` or manual counter | `uniqid('sig_', true)` adds microsecond + entropy; `time()` alone can collide within same second |

**No npm install.** signature_pad is loaded via CDN. PHP dependencies are all built-in.

---

## Architecture Patterns

### Phase 2 File Structure (Additions to Phase 1)

```
ch-ah.info/FirstApp/
│
├── sign.html                   ← NEW: Public signing page (no auth required — URL is the token)
│
├── api/
│   ├── config.php              ← EXISTING — no changes needed
│   ├── send-sms.php            ← EXISTING — no changes needed
│   ├── save-signature.php      ← NEW: Receives base64 PNG, validates, saves, sends confirmation SMS
│   └── check-session.php       ← EXISTING — no changes needed
│
├── signatures/                 ← EXISTING folder
│   ├── .htaccess               ← EXISTING — already denies all access
│   └── sig_[uniqid].png        ← NEW: Saved per signing event
│
└── css/
    └── style.css               ← EXISTING — may need canvas-specific additions
```

### Pattern 1: signature_pad v5 Initialization (DPI-Aware)

**What:** Initialize SignaturePad with correct canvas dimensions adjusted for devicePixelRatio so signatures are crisp on retina/high-DPI mobile screens.

**Why:** If canvas CSS size and internal pixel size mismatch, signatures appear blurry. This resize function must run on load and whenever the window resizes.

**Source:** signature_pad GitHub README + jsDocs.io API — verified 2026-02-28

```javascript
// sign.html <script> — run after DOM loaded
var canvas = document.getElementById('signature-canvas');
var signaturePad = new SignaturePad(canvas, {
    backgroundColor: 'rgb(255, 255, 255)'   // White background so PNG saves correctly
});

function resizeCanvas() {
    var ratio = Math.max(window.devicePixelRatio || 1, 1);
    canvas.width  = canvas.offsetWidth  * ratio;
    canvas.height = canvas.offsetHeight * ratio;
    canvas.getContext('2d').scale(ratio, ratio);
    signaturePad.clear();   // REQUIRED: clear after resize, else isEmpty() returns wrong value
}

window.addEventListener('resize', resizeCanvas);
resizeCanvas();   // Call once on load
```

**Critical:** `signaturePad.clear()` MUST be called in resizeCanvas after setting canvas dimensions. If omitted, `isEmpty()` returns `false` even on an empty canvas because the internal state doesn't sync with the browser's automatic canvas clearing on resize.

### Pattern 2: Submit Handler — isEmpty Check → toDataURL → fetch POST

**What:** On "שלח" button click: block empty submission, extract PNG data URL, POST JSON to `api/save-signature.php`, handle response.

**Source:** signature_pad v5 API (jsDocs.io) + MDN Fetch API

```javascript
// sign.html <script>
var submitBtn = document.getElementById('submit-btn');
var statusMsg = document.getElementById('status-msg');

submitBtn.addEventListener('click', function () {
    if (signaturePad.isEmpty()) {
        statusMsg.textContent = 'אנא חתום לפני השליחה';
        statusMsg.className = 'error-msg visible';
        return;
    }

    var dataUrl = signaturePad.toDataURL('image/png');

    submitBtn.disabled = true;
    submitBtn.textContent = 'שולח...';

    fetch('api/save-signature.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ signature: dataUrl })
    })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.ok) {
                // Hide canvas, show confirmation — no ability to re-sign after success
                document.getElementById('signing-area').style.display = 'none';
                statusMsg.textContent = 'החתימה נשמרה. תודה!';
                statusMsg.className = 'success-msg visible';
            } else {
                statusMsg.textContent = data.message || 'שגיאה בשמירת החתימה';
                statusMsg.className = 'error-msg visible';
                submitBtn.disabled = false;
                submitBtn.textContent = 'שלח';
            }
        })
        .catch(function () {
            statusMsg.textContent = 'שגיאת תקשורת — נסה שוב';
            statusMsg.className = 'error-msg visible';
            submitBtn.disabled = false;
            submitBtn.textContent = 'שלח';
        });
});
```

### Pattern 3: Clear Button Handler

**What:** "נקה" button resets the pad. Uses `signaturePad.clear()` — the only correct method.

```javascript
// sign.html <script>
document.getElementById('clear-btn').addEventListener('click', function () {
    signaturePad.clear();
    statusMsg.style.display = 'none';
    statusMsg.className = '';
});
```

### Pattern 4: PHP save-signature.php — Receive, Validate, Save, Notify

**What:** Receives JSON POST with `{"signature": "data:image/png;base64,..."}`, strips prefix, base64-decodes, validates with GD, saves PNG to `signatures/`, sends confirmation SMS via Micropay.

**Source:** PHP Manual (imagecreatefromstring, imagepng, file_put_contents) + Phase 1 Micropay pattern

```php
<?php
/**
 * api/save-signature.php — Signature save endpoint
 *
 * Receives a base64 PNG from the signing page, validates it, saves it to
 * signatures/ folder, and sends confirmation SMS to ADMIN_PHONE.
 *
 * Request:  POST JSON body: { "signature": "data:image/png;base64,..." }
 * Response: {"ok": true,  "file": "sig_xxx.png"}
 *           {"ok": false, "message": "error reason"}
 *
 * NOTE: No session auth — the signing URL is itself the authorization token.
 *       Anyone with the link can sign. This is correct for this phase.
 */

// No session_start() — signing page is intentionally public
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Parse JSON body (consistent with Phase 1 pattern — fetch() sends JSON)
$body      = json_decode(file_get_contents('php://input'), true);
$dataUrl   = $body['signature'] ?? '';

// 2. Validate format — must start with the PNG data URL prefix
if (strpos($dataUrl, 'data:image/png;base64,') !== 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid image data']);
    exit;
}

// 3. Strip prefix and decode base64
$base64Data  = substr($dataUrl, strlen('data:image/png;base64,'));
$imageData   = base64_decode($base64Data, true);   // strict mode: returns false on invalid chars

if ($imageData === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Base64 decode failed']);
    exit;
}

// 4. Validate with GD — imagecreatefromstring() returns false for non-image data
$gdImage = imagecreatefromstring($imageData);
if ($gdImage === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid image content']);
    exit;
}

// 5. Generate unique filename and save via GD's imagepng()
$filename  = uniqid('sig_', true) . '.png';
$savePath  = __DIR__ . '/../signatures/' . $filename;

$saved = imagepng($gdImage, $savePath);
imagedestroy($gdImage);

if (!$saved) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Failed to save signature file']);
    exit;
}

// 6. Send confirmation SMS "המסמך נחתם" to ADMIN_PHONE (NOTF-01)
$messageUtf8    = 'המסמך נחתם';
$messageEncoded = iconv('UTF-8', 'ISO-8859-8', $messageUtf8);

$params = http_build_query([
    'get'     => '1',
    'token'   => MICROPAY_TOKEN,
    'msg'     => $messageEncoded,
    'list'    => ADMIN_PHONE,
    'charset' => 'iso-8859-8',
    'from'    => 'Chemo IT',
]);

$smsUrl = 'http://www.micropay.co.il/ExtApi/ScheduleSms.php?' . $params;

$ch = curl_init($smsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$smsResult = curl_exec($ch);
$smsError  = curl_error($ch);
curl_close($ch);

// 7. Return success regardless of SMS result (signature is already saved)
//    Include SMS result for debugging
echo json_encode([
    'ok'         => true,
    'file'       => $filename,
    'sms_result' => $smsError !== '' ? 'error: ' . $smsError : $smsResult
]);
```

**Key decision:** SMS failure does NOT cause the endpoint to return `ok: false`. The signature is already saved — SMS is a notification, not the save mechanism. Log `sms_result` in the response for debugging.

### Pattern 5: sign.html Page Structure (Hebrew RTL)

**What:** Public page — no session check on load. Uses same `css/style.css` as Phase 1. Canvas is styled to fill the container width.

```html
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>חתום פה</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Canvas-specific styles — not in shared style.css */
        #signature-canvas {
            border: 2px solid #ccc;
            border-radius: 4px;
            width: 100%;        /* CSS width = container width */
            height: 200px;      /* Fixed CSS height — internal pixel size set via JS */
            display: block;
            cursor: crosshair;
            touch-action: none; /* CRITICAL: prevents scroll while finger-drawing */
        }
        .btn-row {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }
        #clear-btn {
            background-color: #757575;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>חתום פה</h1>

        <div id="signing-area">
            <canvas id="signature-canvas"></canvas>

            <div class="btn-row">
                <button id="submit-btn" class="btn btn-primary">שלח</button>
                <button id="clear-btn" class="btn">נקה</button>
            </div>
        </div>

        <div id="status-msg" class="error-msg"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/signature_pad@5.1.3/dist/signature_pad.umd.min.js"></script>
    <script>
        /* ... Pattern 1 + 2 + 3 JavaScript here ... */
    </script>
</body>
</html>
```

**Note on `user-scalable=no`:** Prevents iOS Safari from zooming the page when the user taps the canvas, which would interfere with signature drawing. Combined with `touch-action: none` on the canvas, this covers pinch-zoom suppression.

### Anti-Patterns to Avoid

- **`touch-action` missing on canvas:** Without `touch-action: none` on the `<canvas>` element, mobile browsers scroll the page when the user tries to draw. The signature pad library cannot override this at the JS level alone on modern iOS/Android — CSS `touch-action: none` must be set on the element.
- **Missing `resizeCanvas()` on load:** Calling `new SignaturePad(canvas)` without setting `canvas.width`/`canvas.height` relative to `devicePixelRatio` produces blurry signatures on retina screens. The resize function must run immediately on page load, not just on `resize` events.
- **`signaturePad.clear()` not called in resizeCanvas:** After changing `canvas.width` or `canvas.height`, the browser clears the canvas but `signaturePad`'s internal state still thinks strokes exist. `isEmpty()` will return `false` on an empty canvas. Always call `signaturePad.clear()` after resizing.
- **White background not set:** Default `backgroundColor` is transparent. `signaturePad.toDataURL("image/png")` with a transparent background produces a PNG with a black background when the canvas background is transparent (the browser composite shows white, but the exported data URI encodes transparency as black in some renderers). Set `backgroundColor: 'rgb(255, 255, 255)'` in the SignaturePad options.
- **`save-signature.php` with session auth guard:** The signing page is accessed by recipients without a Sharon login. Do NOT add the Phase 1 auth guard to `save-signature.php`. The signing URL is the access control.
- **Saving `$_POST['signature']`:** JS `fetch()` with JSON body does NOT populate `$_POST`. Read from `file_get_contents('php://input')` (identical to Phase 1 login pattern).
- **`v4.x` onBegin/onEnd callback options in v5.x:** These are not supported in v5. Use `signaturePad.addEventListener("beginStroke", fn)` and `signaturePad.addEventListener("endStroke", fn)` if event hooks are needed.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Smooth signature drawing | Custom canvas pointer/touch handlers | signature_pad 5.1.3 | Bezier smoothing, velocity-based width variation, pointer event normalization, devicePixelRatio — each individually hard; together they are months of work |
| Cross-browser touch + mouse | Separate touchstart/mousemove handlers | signature_pad's unified pointer event handling | Pointer Events API unifies mouse and touch in modern browsers; signature_pad already uses it correctly |
| PNG validation on save | Custom MIME type header inspection | `imagecreatefromstring()` | GD validates that the bytes are actually a valid PNG image; MIME inspection on a user-supplied string can be spoofed |
| Unique filenames | Timestamp only (`time()`) | `uniqid('sig_', true)` | `time()` alone collides within same second; `uniqid` adds microsecond + LCSG entropy suffix |
| Confirmation SMS encoding | Custom char-by-char encode | `iconv('UTF-8', 'ISO-8859-8', $msg)` | Already validated working in send-sms.php; identical pattern |

**Key insight:** The "don't hand-roll" list for Phase 2 is dominated by signature_pad. The canvas touch/pointer event handling alone has 50+ known edge cases across browsers (pointer capture, preventDefault timing, coordinate mapping on scroll, devicePixelRatio, etc). Use the library.

---

## Common Pitfalls

### Pitfall 1: Canvas Appears Blurry on Mobile (Missing devicePixelRatio Scaling)

**What goes wrong:** Signature looks fine on desktop but blurry on iPhone/Android retina screens.

**Why it happens:** A `<canvas>` element's CSS size (what the user sees) is separate from its internal pixel buffer. On a 3x retina screen, a canvas with `width: 300px` in CSS has 900 physical pixels. Without scaling the buffer, the browser upscales 300 pixels to fill 900, causing blur.

**How to avoid:** Use the `resizeCanvas()` function in Pattern 1. Set `canvas.width = canvas.offsetWidth * devicePixelRatio` and `canvas.height = canvas.offsetHeight * devicePixelRatio`, then call `canvas.getContext('2d').scale(ratio, ratio)`.

**Warning signs:** Signature looks crisp while drawing but blurry in the saved PNG; or signature looks blurry immediately on mobile devices.

### Pitfall 2: Page Scrolls While Drawing Signature on Mobile

**What goes wrong:** When the user tries to draw a signature on mobile, the page scrolls instead. The signature cannot be drawn.

**Why it happens:** Mobile browsers interpret single-finger touch as a scroll gesture by default. This takes priority over canvas drawing events.

**How to avoid:** Add `touch-action: none` CSS to the `<canvas>` element. This tells the browser not to handle touch events on this element, allowing JavaScript (and signature_pad) to receive them exclusively.

**Warning signs:** Testing on desktop works; testing on a real phone fails. The page scrolls when trying to draw.

### Pitfall 3: isEmpty() Returns false on an Empty Canvas After Resize

**What goes wrong:** User can submit without drawing anything. `signaturePad.isEmpty()` returns `false` on a visually blank canvas.

**Why it happens:** When `canvas.width` or `canvas.height` is changed, the browser automatically clears the canvas pixel buffer. But `signaturePad` doesn't know — its internal stroke data still contains the previous strokes. The mismatch means `isEmpty()` reports non-empty.

**How to avoid:** Always call `signaturePad.clear()` immediately after setting `canvas.width` and `canvas.height` in the resize function. This resets both the canvas buffer and signaturePad's internal state.

**Warning signs:** After rotating a phone (orientation change), the canvas is blank but the submit button succeeds with an empty/all-white PNG.

### Pitfall 4: PNG Saved as All Black (Transparent Background Not Set)

**What goes wrong:** The saved PNG file is all black, or the signature strokes are invisible against a black background.

**Why it happens:** HTML5 canvas default is transparent. `toDataURL("image/png")` exports the actual pixel data including transparency. When PHP saves this via GD, GD may fill transparent areas with black. Some renderers (imagepng) black-fill alpha channels when saving to standard PNG without explicit alpha handling.

**How to avoid:** Initialize SignaturePad with `{ backgroundColor: 'rgb(255, 255, 255)' }`. This fills the canvas white before any drawing, ensuring the background is opaque white in the exported PNG.

**Warning signs:** PNG file opens with black background; signature strokes may or may not be visible.

### Pitfall 5: Submission Allowed After Successful Save

**What goes wrong:** User submits signature, receives success, then hits "שלח" again and submits a second signature.

**Why it happens:** The submit button is re-enabled in the success path (unlike the error path where re-enabling is correct).

**How to avoid:** On success, hide the entire `#signing-area` div (canvas + buttons) and show the success message. The submit button should never be re-enabled after a successful save (see Pattern 2).

**Warning signs:** Two PNG files appear in `signatures/` folder for the same signing event.

### Pitfall 6: SMS Confirmation Failure Blocks Success Response

**What goes wrong:** Signature is saved successfully to disk, but the Micropay SMS call fails (network timeout, etc.). PHP returns `ok: false` and the user sees an error message even though their signature was saved.

**Why it happens:** If the save-signature.php treats SMS failure as a full endpoint failure, the user is told "error" even though the signature was recorded.

**How to avoid:** Return `ok: true` after the file is successfully saved regardless of SMS result. Include `sms_result` in the response for debugging. SMS is a notification, not the record of truth (see Pattern 4).

**Warning signs:** Signature PNG exists in `signatures/` folder but the signing page shows an error to the user.

### Pitfall 7: v4 API Syntax Used with v5 Library

**What goes wrong:** Code uses `var pad = new SignaturePad(canvas, { onBegin: fn, onEnd: fn })` which was valid in v4 but silently does nothing in v5.

**Why it happens:** Phase 1 research references "signature_pad 4.x" — if a developer copies v4 examples, the event callbacks will not fire.

**How to avoid:** Use v5 event API: `signaturePad.addEventListener("beginStroke", fn)`. For this phase's requirements, no stroke events are needed — `isEmpty()` and `toDataURL()` are sufficient and unchanged between v4 and v5.

**Warning signs:** `onBegin`/`onEnd` callbacks set in options never fire. No error thrown (they are silently ignored as unknown options).

---

## Code Examples

Verified patterns from official sources:

### signature_pad v5.1.3 — Full API Methods Used in Phase 2

```javascript
// Source: jsDocs.io signature_pad 5.1.2 API — verified 2026-02-28

// Initialize
var pad = new SignaturePad(canvas, {
    backgroundColor: 'rgb(255, 255, 255)',   // white background for clean PNG export
    penColor: 'rgb(0, 0, 0)',                // black ink
    minWidth: 0.5,
    maxWidth: 2.5
});

// Check if empty (use after resizeCanvas calls pad.clear())
pad.isEmpty();          // returns boolean

// Get data URL
pad.toDataURL();                    // default: 'image/png'
pad.toDataURL('image/png');         // explicit PNG

// Clear canvas
pad.clear();

// Enable/disable drawing
pad.off();              // disables drawing (call after successful save)
pad.on();               // re-enables drawing

// Event listeners (v5 API — replaces v4 onBegin/onEnd options)
pad.addEventListener("beginStroke", function(event) { /* ... */ });
pad.addEventListener("endStroke",   function(event) { /* ... */ });
```

### PHP: Base64 PNG Save with GD

```php
<?php
// Source: PHP Manual — imagecreatefromstring, imagepng, base64_decode
// Pattern for save-signature.php

// Input: $dataUrl = "data:image/png;base64,iVBORw0KGgo..."
$prefix    = 'data:image/png;base64,';
$base64    = substr($dataUrl, strlen($prefix));
$imageData = base64_decode($base64, true);       // strict mode

if ($imageData === false) {
    // Invalid base64 — reject
}

$gd = imagecreatefromstring($imageData);
if ($gd === false) {
    // Not a valid image — reject
}

$filename = uniqid('sig_', true) . '.png';
$path     = __DIR__ . '/../signatures/' . $filename;

imagepng($gd, $path);
imagedestroy($gd);
```

### Confirmation SMS — "המסמך נחתם"

```php
<?php
// Source: Phase 1 send-sms.php pattern — identical except different message text
// NOTF-01: message is "המסמך נחתם"

$messageUtf8    = 'המסמך נחתם';
$messageEncoded = iconv('UTF-8', 'ISO-8859-8', $messageUtf8);

// Hebrew char count check: "המסמך נחתם" = 10 chars — well within 70-char SMS limit

$params = http_build_query([
    'get'     => '1',
    'token'   => MICROPAY_TOKEN,
    'msg'     => $messageEncoded,
    'list'    => ADMIN_PHONE,
    'charset' => 'iso-8859-8',
    'from'    => 'Chemo IT',
]);

$url = 'http://www.micropay.co.il/ExtApi/ScheduleSms.php?' . $params;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$result = curl_exec($ch);
$error  = curl_error($ch);
curl_close($ch);
```

### CSS: Canvas Touch and Sizing

```css
/* style.css additions or inline in sign.html */

#signature-canvas {
    border: 2px solid #ccc;
    border-radius: 4px;
    width: 100%;
    height: 200px;
    display: block;
    cursor: crosshair;
    touch-action: none;   /* Prevents mobile scroll during drawing */
    background: #fff;     /* Visible hint of white background */
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| signature_pad 4.x (`onBegin`/`onEnd` options) | signature_pad 5.x (addEventListener API) | v5.0.0, May 2024 | Phase 1 docs reference 4.x — must use 5.x event API |
| signature_pad 4.1.x (last 4.x) | 5.1.3 (latest, Dec 2025) | Dec 2025 | Pin CDN to 5.1.3 specifically |
| `touch-action` with JavaScript preventDefault | `touch-action: none` CSS (passive event listeners) | Chrome 56+ (2017) | CSS approach is now the correct method; JS preventDefault on passive listeners throws error in some browsers |
| `file_get_contents(base64_decode(...))` | `imagecreatefromstring()` + `imagepng()` | Best practice update 2020+ | GD approach validates the PNG structure; preferred for any server-side image save |

**Not deprecated in this stack:**
- signature_pad `toDataURL()` — unchanged between v4 and v5, returns `data:image/png;base64,...`
- signature_pad `isEmpty()` — unchanged between v4 and v5
- signature_pad `clear()` — unchanged between v4 and v5
- PHP GD `imagecreatefromstring()` / `imagepng()` — stable built-in since PHP 4

---

## Open Questions

1. **Does ch-ah.info's PHP have GD extension enabled?**
   - What we know: GD is included by default in PHP 8.x cPanel installs (bundled with PHP since PHP 5). The `--with-gd` flag was the default compile option.
   - What's unclear: Whether this specific cPanel server has GD enabled in the active PHP profile.
   - Recommendation: First task in Phase 2 execution should be a quick PHP probe: `<?php echo extension_loaded('gd') ? 'GD OK' : 'GD missing'; ?>`. If missing, fall back to `file_put_contents(base64_decode(...))` — this is safe because the source is always a canvas data URL, not a user-uploaded file.
   - Confidence: MEDIUM (GD is almost universally available on cPanel PHP 8.x, but worth confirming)

2. **Should sign.html be accessible without authentication?**
   - What we know: Requirements say "SIGN-01: Signing page opens from SMS link on mobile browser" — the link is sent via SMS to a recipient who is not Sharon and has no login. No auth is required by design.
   - What's unclear: Whether there is a token/nonce in the URL to make it single-use (not in Phase 2 requirements).
   - Recommendation: Phase 2 makes sign.html fully public — anyone with the URL can sign. Single-use token is a future enhancement. This is correct per the Phase 2 success criteria.

3. **Signature file naming — should it include timestamp?**
   - What we know: Requirements say "saved as PNG file in signatures/ folder" — no specific naming format specified.
   - What's unclear: Whether Sharon needs to know when each signature was created from the filename alone.
   - Recommendation: Use `uniqid('sig_', true)` which embeds a microsecond timestamp in hex (e.g., `sig_679d3f8a4e0a1.png`). Human-readable date can be added as prefix (`sig_20260228_` + uniqid) if preferred, but this is planner discretion.

---

## Sources

### Primary (HIGH confidence)

- jsDocs.io — signature_pad 5.1.2 API — https://www.jsdocs.io/package/signature_pad — full method list, constructor options
- GitHub szimek/signature_pad Releases — https://github.com/szimek/signature_pad/releases — v5.1.3 confirmed as latest (Dec 3, 2025)
- cdnjs.com — signature_pad — https://cdnjs.com/libraries/signature_pad — v5.1.1 CDN URLs confirmed
- MDN — touch-action CSS — https://developer.mozilla.org/en-US/docs/Web/CSS/touch-action — touch-action: none behavior
- MDN — HTMLCanvasElement.toDataURL — https://developer.mozilla.org/en-US/docs/Web/API/HTMLCanvasElement/toDataURL — returns data URL format
- Phase 1 RESEARCH.md — existing decisions, patterns, Micropay API verified
- Phase 1 send-sms.php — verified working cURL + iconv + Micropay pattern (reused verbatim)
- Phase 1 config.php — MICROPAY_TOKEN, ADMIN_PHONE, BASE_URL constants confirmed

### Secondary (MEDIUM confidence)

- GitHub signature_pad Issue #94 — isEmpty() false positive after resize — https://github.com/szimek/signature_pad/issues/94 — confirmed, resolution is calling clear() in resize handler
- GitHub signature_pad v5.0.0 Release Notes — breaking changes from v4 to v5 (event system change)
- PHP Manual — imagecreatefromstring — https://www.php.net/manual/en/function.imagecreatefromstring.php
- PHP Manual — imagepng — https://www.php.net/manual/en/function.imagepng.php
- PHP Manual — uniqid — https://www.php.net/manual/en/function.uniqid.php

### Tertiary (LOW confidence — verify before use)

- GD extension availability on ch-ah.info cPanel: assumed standard but not server-confirmed. Add a verification step to Phase 2 execution.
- signature_pad v5 `touch-action: none` reliance: verified via MDN CSS spec; Safari historically had partial support. Current Safari (2025) supports `touch-action: none` fully. Source: multiple webdev community articles, not single official changelog.

---

## Metadata

**Confidence breakdown:**

| Area | Level | Reason |
|------|-------|--------|
| signature_pad 5.1.3 version/CDN | HIGH | Verified via GitHub releases page and cdnjs |
| signature_pad v5 API (toDataURL, isEmpty, clear) | HIGH | Verified via jsDocs.io API documentation |
| v4 → v5 event API change | HIGH | Verified via GitHub release notes and multiple secondary sources |
| PHP GD save pattern | HIGH | PHP Manual verified; pattern is well-established |
| touch-action: none for canvas | HIGH | MDN spec verified; Safari current support confirmed |
| devicePixelRatio canvas scaling | HIGH | Multiple verified sources; signature_pad README pattern |
| GD extension on ch-ah.info | MEDIUM | Standard on cPanel PHP 8.x but not server-confirmed |
| Single-use URL token (not in scope) | N/A | Deferred per Phase 2 requirements |

**Research date:** 2026-02-28
**Valid until:** 2026-08-28 (signature_pad is actively maintained; re-check release if building after 6 months; PHP GD is stable)
