<?php
/**
 * api/login.php — Supabase-backed session login
 *
 * Accepts POST with JSON body: {"email": "...", "password": "..."}
 * Looks up user by email in public.users, verifies bcrypt password,
 * checks status (active/blocked/suspended), sets PHP session on success.
 *
 * On success:  {"ok": true}
 * On failure:  HTTP 401 + {"ok": false, "message": "פרטי הכניסה שגויים"}
 * On transport error: HTTP 500 + {"ok": false, "message": "שגיאת שרת — נסה שוב"}
 *
 * Usage from index.html:
 *   fetch('api/login.php', { method: 'POST',
 *     headers: { 'Content-Type': 'application/json' },
 *     body: JSON.stringify({ email, password }) })
 */

session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';

header('Content-Type: application/json; charset=utf-8');

// Read JSON body — must use php://input, NOT $_POST (Content-Type is application/json, not form-encoded)
$body     = json_decode(file_get_contents('php://input'), true);
$email    = trim($body['email']    ?? '');
$password = $body['password']      ?? '';

// Generic error — never reveal whether email exists, password was wrong, or user is blocked
// Using a single message for all auth failures prevents user enumeration
$genericError = json_encode(['ok' => false, 'message' => 'פרטי הכניסה שגויים'], JSON_UNESCAPED_UNICODE);

// Guard: reject empty input immediately (no Supabase call needed)
if ($email === '' || $password === '') {
    http_response_code(401);
    echo $genericError;
    exit;
}

// Guard: basic email format check (avoids PostgREST call on obviously malformed input)
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(401);
    echo $genericError;
    exit;
}

// Fetch user by email — include password_hash for verify, status + suspended_until for status check
// SECURITY: password_hash is fetched here (login only) — excluded from list.php at query level
$result = supabase_request(
    'GET',
    '/users?email=eq.' . rawurlencode($email)
    . '&select=id,email,status,suspended_until,password_hash'
);

// Supabase transport error (cURL failure or unexpected HTTP code)
if ($result['error'] !== null || $result['http_code'] !== 200) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'שגיאת שרת — נסה שוב'], JSON_UNESCAPED_UNICODE);
    exit;
}

// No user found — PostgREST returns 200 + [] for no-match (NOT 404)
if (empty($result['data'])) {
    http_response_code(401);
    echo $genericError;
    exit;
}

$user = $result['data'][0];

// Bcrypt verification — timing-safe by spec (password_verify does not short-circuit)
if (!password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo $genericError;
    exit;
}

// Status enforcement — checked AFTER password verification to avoid leaking user existence
$status = $user['status'] ?? 'active';

if ($status === 'blocked') {
    http_response_code(401);
    echo $genericError;
    exit;
}

if ($status === 'suspended') {
    $suspendedUntil = $user['suspended_until'] ?? null;

    // If suspended_until is set and in the future: refuse login
    // If null or past: suspension expired — allow login (fall through)
    if ($suspendedUntil !== null) {
        // Supabase returns TIMESTAMPTZ as ISO 8601 string e.g. "2026-03-15T00:00:00+00:00"
        // Always compare against UTC midnight to match how PostgreSQL stores the date
        $suspendDate = new DateTime($suspendedUntil);
        $today       = new DateTime('today', new DateTimeZone('UTC'));

        if ($suspendDate > $today) {
            http_response_code(401);
            echo $genericError;
            exit;
        }
        // else: suspension date passed — fall through to login success
    }
    // else: suspended_until is null — treat as expired (defensive; shouldn't happen via normal UI)
}

// Login success — regenerate session ID BEFORE writing session data to prevent session fixation
session_regenerate_id(true);

$_SESSION['logged_in'] = true;
$_SESSION['user']      = $user['email'];

echo json_encode(['ok' => true]);
