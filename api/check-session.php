<?php
/**
 * api/check-session.php — Session status check endpoint
 *
 * Returns JSON indicating whether the current request has a valid authenticated session.
 * Used by index.html on page load to auto-redirect already-logged-in users to dashboard.html.
 * Used by dashboard.html to guard against unauthenticated direct URL access.
 *
 * Response:
 *   {"ok": true}  — session exists and user is logged in
 *   {"ok": false} — no valid session
 */

session_start();

header('Content-Type: application/json; charset=utf-8');

echo json_encode(['ok' => isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true]);
