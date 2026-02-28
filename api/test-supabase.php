<?php
// api/test-supabase.php — TEMPORARY test file, DELETE after verification
// Access in browser: https://ch-ah.info/FirstApp/api/test-supabase.php

require_once __DIR__ . '/supabase.php';

$result = supabase_request('GET', '/users?select=*');

header('Content-Type: application/json');
echo json_encode($result);
// Expected: {"data":[],"http_code":200,"error":null}
