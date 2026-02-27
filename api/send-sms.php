<?php
/**
 * api/send-sms.php — SMS dispatch endpoint
 *
 * Sends an SMS to ADMIN_PHONE via the Micropay API with a Hebrew signing link message.
 * Only accessible by an authenticated session — returns HTTP 401 JSON otherwise.
 *
 * Request:  POST (no body required — phone and message are server-side constants)
 * Response: {"ok": true,  "result": "<micropay raw response>"}
 *           {"ok": false, "message": "שגיאה בשליחת SMS: <curl error>"}
 *           {"ok": false, "message": "Unauthorized"}  — 401 if not logged in
 */

session_start();

// Auth guard — must have an active authenticated session (research Pattern 2)
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Build the SMS message — Hebrew text + signing link (DISP-03)
$phone        = ADMIN_PHONE;                           // '0526804680' from config
$signUrl      = BASE_URL . '/sign.html';               // e.g. https://ch-ah.info/FirstApp/sign.html
$messageUtf8  = 'היכנס לקישור הבא: ' . $signUrl;       // Exact Hebrew message per spec

// Micropay requires ISO-8859-8 encoding for Hebrew messages
$messageEncoded = iconv('UTF-8', 'ISO-8859-8', $messageUtf8);

// Build the Micropay API query string with all 6 required parameters
$params = http_build_query([
    'get'     => '1',
    'token'   => MICROPAY_TOKEN,
    'msg'     => $messageEncoded,
    'list'    => $phone,
    'charset' => 'iso-8859-8',
    'from'    => 'Chemo IT',
]);

$url = 'http://www.micropay.co.il/ExtApi/ScheduleSms.php?' . $params;

// Send via cURL — NOT file_get_contents (research: cURL is required for reliability and timeout control)
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$result = curl_exec($ch);
$error  = curl_error($ch);
curl_close($ch);

// Return JSON — include raw Micropay response for debugging (response format not fully documented)
if ($error !== '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'שגיאה בשליחת SMS: ' . $error]);
} else {
    echo json_encode(['ok' => true, 'result' => $result]);
}
