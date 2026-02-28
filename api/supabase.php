<?php
/**
 * api/supabase.php — Shared cURL helper for all Supabase REST API calls
 *
 * Usage: require_once __DIR__ . '/supabase.php';
 * Then: $result = supabase_request('GET', '/users');
 *       $result = supabase_request('POST', '/users', ['email' => '...', ...]);
 *       $result = supabase_request('PATCH', '/users?id=eq.5', ['status' => 'blocked']);
 *       $result = supabase_request('DELETE', '/users?id=eq.5');
 *
 * Returns: ['data' => mixed, 'http_code' => int, 'error' => string|null]
 *
 * DUAL-HEADER REQUIREMENT: Both 'apikey' and 'Authorization: Bearer' headers must
 * use the service_role key. The Authorization header is what bypasses Row Level
 * Security (RLS) — apikey alone does NOT bypass RLS.
 */

require_once __DIR__ . '/config.php';

/**
 * Make an authenticated request to the Supabase REST API.
 *
 * @param string      $method     HTTP method: GET, POST, PATCH, DELETE
 * @param string      $path       Table path with optional query params e.g. '/users' or '/users?email=eq.x'
 * @param array|null  $body       Data for POST/PATCH requests (will be JSON-encoded)
 * @param bool        $prefer_rep Whether to request the created/updated row back (Prefer: return=representation)
 * @return array      ['data' => decoded JSON or null, 'http_code' => int, 'error' => string|null]
 */
function supabase_request(string $method, string $path, ?array $body = null, bool $prefer_rep = false): array {
    $url = SUPABASE_URL . '/rest/v1' . $path;

    // DUAL-HEADER REQUIREMENT: Both headers must use service_role key.
    // Authorization: Bearer is what bypasses RLS — apikey alone does NOT bypass RLS.
    $headers = [
        'apikey: '               . SUPABASE_SERVICE_ROLE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
        'Content-Type: application/json',
    ];

    // Add Prefer header when we need the full row returned (e.g. after INSERT)
    if ($prefer_rep) {
        $headers[] = 'Prefer: return=representation';
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);  // Always verify SSL for Supabase HTTPS

    // Attach JSON body for write operations
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw      = curl_exec($ch);
    $error    = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($error !== '') {
        return ['data' => null, 'http_code' => 0, 'error' => $error];
    }

    // Supabase returns JSON arrays or objects; decode to PHP array
    $decoded = json_decode($raw, true);
    return ['data' => $decoded, 'http_code' => $httpCode, 'error' => null];
}
