<?php
/**
 * api/login.php — Session-based login endpoint
 *
 * Accepts POST with JSON body: {"username": "...", "password": "..."}
 * Compares against ADMIN_USER and ADMIN_PASS from config.php.
 * On success: regenerates session ID (prevents session fixation) and returns {"ok": true}
 * On failure: returns HTTP 401 with {"ok": false, "message": "שם כניסה או סיסמא לא תקינים"}
 *
 * Usage from index.html:
 *   fetch('api/login.php', { method: 'POST',
 *     headers: { 'Content-Type': 'application/json' },
 *     body: JSON.stringify({ username, password }) })
 */

session_start();

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Read JSON body — must use php://input, NOT $_POST (Pitfall 4: Content-Type is application/json, not form-encoded)
$body = json_decode(file_get_contents('php://input'), true);

$username = $body['username'] ?? '';
$password = $body['password'] ?? '';

// Validate credentials using strict equality against config constants
if ($username === ADMIN_USER && $password === ADMIN_PASS) {
    // Regenerate session ID BEFORE writing session data to prevent session fixation attacks
    session_regenerate_id(true);

    $_SESSION['logged_in'] = true;
    $_SESSION['user'] = $username;

    echo json_encode(['ok' => true]);
} else {
    // AUTH-02: Hebrew error message for wrong credentials
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'שם כניסה או סיסמא לא תקינים']);
}
