<?php
/**
 * api/users/list.php — Returns all users as a JSON array
 *
 * GET request only. No request body expected.
 * Returns: {"ok": true, "users": [...]} on success
 *          {"ok": false, "message": "שגיאה בטעינת המשתמשים"} on Supabase error
 *
 * Fields returned: id, first_name, last_name, id_number, email, phone, gender,
 *                  foreign_worker, status, created_at  (password_hash excluded at query level)
 *
 * Path note: This file lives at api/users/list.php.
 *   __DIR__ = <project>/api/users
 *   One level up (/../) = <project>/api  — where config.php and supabase.php live.
 *   DO NOT use ../../api/ — that would traverse two levels, reaching the project root.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../supabase.php';

header('Content-Type: application/json; charset=utf-8');

// Only GET requests are accepted — reject everything else with 405
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fetch all users — exclude password_hash at the API level (never returned to client)
// order=created_at.desc: newest users first
$result = supabase_request(
    'GET',
    '/users?select=id,first_name,last_name,id_number,email,phone,gender,foreign_worker,status,created_at&order=created_at.desc'
);

// On cURL transport error or unexpected HTTP response, return 500
if ($result['error'] !== null || $result['http_code'] !== 200) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'שגיאה בטעינת המשתמשים'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Success — $result['data'] is an array of user objects (empty array [] when table is empty)
echo json_encode(['ok' => true, 'users' => $result['data']], JSON_UNESCAPED_UNICODE);
