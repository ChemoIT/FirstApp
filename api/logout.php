<?php
/**
 * api/logout.php — Destroy session and return success
 *
 * Called by dashboard.html logout link. Destroys the PHP session
 * so check-session.php will return {ok: false} on next visit.
 */

session_start();

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true]);
