<?php
/**
 * api/save-signature.php — Signature save endpoint
 *
 * Receives a base64 PNG from the signing page, validates it with PHP GD,
 * saves it to the signatures/ folder, and sends a confirmation SMS to ADMIN_PHONE.
 *
 * Request:  POST JSON body: { "signature": "data:image/png;base64,..." }
 * Response: {"ok": true,  "file": "sig_xxx.png", "sms_result": "<micropay response>"}
 *           {"ok": false, "message": "error reason"}  — with HTTP 400 or 500
 *
 * NOTE: No session auth — the signing URL is itself the authorization token.
 *       This endpoint is intentionally public: anyone with the sign.html link can submit.
 *       Single-use token (nonce) is a future enhancement — not in Phase 2 scope.
 */

// No session_start() — signing page and this endpoint are intentionally public
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// --- 1. Parse JSON body ---
// Use php://input (not $_POST) — fetch() with JSON body does NOT populate $_POST.
// Consistent with Phase 1 login.php and send-sms.php patterns.
$body    = json_decode(file_get_contents('php://input'), true);
$dataUrl = $body['signature'] ?? '';

// --- 2. Validate data URL prefix ---
// Must start with the exact PNG data URL prefix produced by canvas.toDataURL('image/png')
if (strpos($dataUrl, 'data:image/png;base64,') !== 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid image data']);
    exit;
}

// --- 3. Strip prefix and base64-decode ---
// strict mode (true) returns false on invalid base64 characters — catches corrupt payloads
$base64Data = substr($dataUrl, strlen('data:image/png;base64,'));
$imageData  = base64_decode($base64Data, true);

if ($imageData === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Base64 decode failed']);
    exit;
}

// --- 4. GD validation ---
// imagecreatefromstring() validates that the bytes are an actual image.
// Returns false for random binary data, truncated files, or non-image payloads.
// Preferred over file_put_contents(base64_decode()) alone — adds structural validation.
$gdImage = imagecreatefromstring($imageData);

if ($gdImage === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid image content']);
    exit;
}

// --- 5. Generate unique filename and save PNG ---
// uniqid('sig_', true) embeds microsecond timestamp in hex + entropy suffix.
// Example output: sig_679d3f8a4e0a1.png
// Avoids filename collision even under concurrent submissions.
$filename = uniqid('sig_', true) . '.png';
$savePath = __DIR__ . '/../signatures/' . $filename;

$saved = imagepng($gdImage, $savePath);
imagedestroy($gdImage);   // Free GD memory — always call after imagepng

if (!$saved) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Failed to save signature file']);
    exit;
}

// --- 6. Send confirmation SMS "המסמך נחתם" to ADMIN_PHONE (NOTF-01) ---
// Reuses Phase 1 Micropay/cURL pattern verbatim from send-sms.php.
// iconv UTF-8 → ISO-8859-8 required for Hebrew characters via Micropay API.
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

// cURL (not file_get_contents) — provides timeout control and curl_error() detection
$ch = curl_init($smsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$smsResult = curl_exec($ch);
$smsError  = curl_error($ch);
curl_close($ch);

// --- 7. Return success ---
// CRITICAL design decision: SMS failure does NOT cause ok:false.
// The signature PNG is already saved — that is the record of truth.
// SMS is a notification only. Include sms_result for debugging.
echo json_encode([
    'ok'         => true,
    'file'       => $filename,
    'sms_result' => $smsError !== '' ? 'error: ' . $smsError : $smsResult,
]);
