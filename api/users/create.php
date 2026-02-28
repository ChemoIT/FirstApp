<?php
/**
 * api/users/create.php — Creates a new user in public.users
 *
 * POST request only. Expects JSON body:
 *   {
 *     "first_name": "string",
 *     "last_name": "string",
 *     "id_number": "string",
 *     "phone": "string (digits only)",
 *     "gender": "male|female",
 *     "foreign_worker": bool,
 *     "email": "valid email address",
 *     "password": "string (min 8 chars)"
 *   }
 *
 * Returns on success (HTTP 201):
 *   {"ok": true, "user": {id, first_name, last_name, ...}} — password_hash excluded
 *
 * Returns on validation failure (HTTP 422):
 *   {"ok": false, "message": "<Hebrew error message>"}
 *
 * Returns on duplicate email/id_number (HTTP 409):
 *   {"ok": false, "message": "אימייל או מספר זהות כבר קיים במערכת"}
 *
 * Returns on Supabase error (HTTP 500):
 *   {"ok": false, "message": "שגיאה ביצירת המשתמש"}
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
$body = json_decode(file_get_contents('php://input'), true);

// Extract and trim all fields — default to empty string so required-field check catches missing keys
$firstName     = trim($body['first_name']     ?? '');
$lastName      = trim($body['last_name']      ?? '');
$idNumber      = trim($body['id_number']      ?? '');
$phone         = trim($body['phone']          ?? '');
$email         = trim($body['email']          ?? '');
$password      = $body['password']            ?? '';  // Do NOT trim password — spaces may be intentional
$gender        = $body['gender']              ?? '';
$foreignWorker = (bool)($body['foreign_worker'] ?? false);

// --- Server-side validation ---
// Step 1: All required fields must be non-empty
// Note: $password is checked with strlen to preserve trimmed behavior
if (!$firstName || !$lastName || !$idNumber || !$phone || !$email || !$password || !$gender) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'כל השדות חובה'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Step 2 (ADMIN-03): Email format validation — server-side, regardless of client validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'כתובת אימייל לא תקינה'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Step 3 (ADMIN-04): Password minimum length — server-side enforcement
if (strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'הסיסמא חייבת לכלול לפחות 8 תווים'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Step 4: Gender whitelist — must match database CHECK constraint values exactly
if (!in_array($gender, ['male', 'female'], true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'ערך מגדר לא תקין'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Step 5: Phone digits-only validation — type="tel" on frontend does NOT enforce this on desktop
if (!preg_match('/^\d+$/', $phone)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'מספר טלפון חייב לכלול ספרות בלבד'], JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Password hashing ---
// PHP owns the full password lifecycle (Phase 3 decision).
// PASSWORD_DEFAULT = bcrypt. PHP auto-generates a cryptographic salt.
// Never pass a manual salt — the 'salt' option was deprecated in PHP 7.0 and removed in PHP 8.0.
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// --- Insert into Supabase ---
// Fourth arg true → adds "Prefer: return=representation" header so the inserted row is returned.
// Without this header, Supabase returns HTTP 201 with an empty body (return=minimal default).
$result = supabase_request(
    'POST',
    '/users',
    [
        'first_name'     => $firstName,
        'last_name'      => $lastName,
        'id_number'      => $idNumber,
        'phone'          => $phone,
        'gender'         => $gender,
        'foreign_worker' => $foreignWorker,
        'email'          => $email,
        'password_hash'  => $passwordHash,
        'status'         => 'active',
    ],
    true  // $prefer_rep = true — request the full inserted row back (HTTP 201 with body)
);

// --- Handle Supabase response ---
if ($result['error'] !== null || $result['http_code'] !== 201) {
    // Check for PostgreSQL unique constraint violation (duplicate email or id_number).
    // Supabase returns the error in data['message'] with text "duplicate key value violates..."
    $errMsg = $result['data']['message'] ?? '';
    if (strpos($errMsg, 'duplicate key') !== false || $result['http_code'] === 409) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => 'אימייל או מספר זהות כבר קיים במערכת'], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'שגיאה ביצירת המשתמש'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// $result['data'] is an array of inserted rows — take the first element
$created = is_array($result['data']) ? $result['data'][0] : $result['data'];

// SECURITY: Remove password_hash before returning — never expose bcrypt hash to the client
unset($created['password_hash']);

// Return the created user (with auto-generated id and created_at from Supabase)
http_response_code(201);
echo json_encode(['ok' => true, 'user' => $created], JSON_UNESCAPED_UNICODE);
