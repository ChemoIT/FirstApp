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
 *   block:   no extra fields needed (also clears suspended_until)
 *   suspend: "suspended_until" (ISO 8601 date string YYYY-MM-DD, must be future date)
 *
 * Returns HTTP 200 with {"ok": true} on success
 * Returns HTTP 405 with {"ok": false, "message": "..."} on non-POST
 * Returns HTTP 422 with {"ok": false, "message": "..."} on validation failure
 * Returns HTTP 500 with {"ok": false, "message": "..."} on Supabase error
 *
 * Path note: __DIR__ = <project>/api/users — one level up (/../) reaches <project>/api
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../supabase.php';

header('Content-Type: application/json; charset=utf-8');

// Only POST requests are accepted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Read and decode JSON request body (must use php://input — not $_POST — because Content-Type is application/json)
$body   = json_decode(file_get_contents('php://input'), true);

// intval() prevents PostgREST operator injection (e.g. id=eq.0 OR 1=1) — same pattern as create.php
$userId = intval($body['id'] ?? 0);
$action = $body['action'] ?? '';

// Validate user ID before building any URL
if ($userId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'מזהה משתמש לא תקין'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Build the PATCH payload based on action
$patch = [];

if ($action === 'edit') {
    // Extract and trim all editable fields — id_number intentionally excluded (identity doc, immutable after creation)
    $firstName     = trim($body['first_name']       ?? '');
    $lastName      = trim($body['last_name']        ?? '');
    $phone         = trim($body['phone']            ?? '');
    $email         = trim($body['email']            ?? '');
    $gender        = $body['gender']                ?? '';
    $foreignWorker = (bool)($body['foreign_worker'] ?? false);

    // Step 1: All required fields must be non-empty
    if (!$firstName || !$lastName || !$phone || !$email || !$gender) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'כל השדות חובה'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Step 2: Email format validation — server-side, regardless of client validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'כתובת אימייל לא תקינה'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Step 3: Phone digits-only validation — type="tel" on frontend does NOT enforce this on desktop
    if (!preg_match('/^\d+$/', $phone)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'מספר טלפון חייב לכלול ספרות בלבד'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Step 4: Gender whitelist — must match database CHECK constraint values exactly
    if (!in_array($gender, ['male', 'female'], true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'ערך מגדר לא תקין'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $patch = [
        'first_name'     => $firstName,
        'last_name'      => $lastName,
        'phone'          => $phone,
        'email'          => $email,
        'gender'         => $gender,
        'foreign_worker' => $foreignWorker,
    ];

} elseif ($action === 'block') {
    // CRITICAL: Must set suspended_until to null to clear any previous suspend date.
    // PHP json_encode maps null → JSON null → PostgreSQL NULL on the TIMESTAMPTZ column.
    // Without this, a previously-suspended user retains a stale suspended_until date after blocking.
    $patch = ['status' => 'blocked', 'suspended_until' => null];

} elseif ($action === 'suspend') {
    $suspendedUntil = trim($body['suspended_until'] ?? '');

    // Validate date format YYYY-MM-DD using DateTime — also validates impossible dates (e.g. Feb 30)
    $date = DateTime::createFromFormat('Y-m-d', $suspendedUntil);
    if (!$date || $date->format('Y-m-d') !== $suspendedUntil) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'תאריך לא תקין'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Must be a future date — today or past is not a valid suspension date
    if ($date <= new DateTime('today')) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'תאריך ההשעיה חייב להיות בעתיד'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Sending bare YYYY-MM-DD is accepted by PostgreSQL — interpreted as midnight UTC (correct for TIMESTAMPTZ)
    $patch = ['status' => 'suspended', 'suspended_until' => $suspendedUntil];

} else {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'פעולה לא תקינה'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Send PATCH to Supabase — filter by primary key to prevent mass-update (Pitfall 7)
// intval() applied above; safe to concatenate directly
$result = supabase_request('PATCH', '/users?id=eq.' . $userId, $patch);

// PATCH success = HTTP 200 (not 204 — DELETE returns 204, PATCH returns 200)
if ($result['error'] !== null || $result['http_code'] !== 200) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'שגיאה בעדכון המשתמש'], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(200);
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
