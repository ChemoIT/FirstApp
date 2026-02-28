<?php
/**
 * api/users/delete.php — Deletes a user by ID from public.users
 *
 * POST request only. Expects JSON body:
 *   { "id": 5 }
 *
 * Returns HTTP 200 with {"ok": true} on success
 * Returns HTTP 405 with {"ok": false, "message": "..."} on non-POST
 * Returns HTTP 422 with {"ok": false, "message": "..."} on invalid ID
 * Returns HTTP 500 with {"ok": false, "message": "..."} on Supabase error
 *
 * IMPORTANT — DELETE vs PATCH HTTP status codes:
 *   PATCH success = 200 OK (checked in update.php)
 *   DELETE success = 204 No Content (checked here)
 *   Checking for 200 on DELETE will always produce false errors — the row is deleted
 *   but PHP returns 500 because the expected code was 200 not 204.
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

// intval() prevents PostgREST operator injection — same pattern as update.php and create.php
$userId = intval($body['id'] ?? 0);

// Validate user ID before building any URL
if ($userId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'מזהה משתמש לא תקין'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Send DELETE to Supabase — filter by primary key, no body needed
// intval() applied above; safe to concatenate directly
$result = supabase_request('DELETE', '/users?id=eq.' . $userId);
// Note: third arg (body) omitted — DELETE requires no request body
// Note: fourth arg (prefer_rep) omitted — we don't need the deleted row back

// CRITICAL: DELETE success = HTTP 204 No Content (not 200)
// $result['data'] will be null on 204 — this is correct and expected
if ($result['error'] !== null || $result['http_code'] !== 204) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'שגיאה במחיקת המשתמש'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Return 200 with ok:true — the caller (admin.php) gets a standard success response
http_response_code(200);
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
