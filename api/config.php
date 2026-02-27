<?php
/**
 * api/config.php — Centralized credentials and constants
 *
 * This file is the single source of truth for all application secrets and settings.
 * It is included by other PHP files via:
 *     require_once __DIR__ . '/config.php';
 *
 * IMPORTANT: This file must NEVER be served directly to browsers or echoed in responses.
 * It produces no output — it only defines constants for use by other PHP files.
 * Keep all secrets here and nowhere else.
 */

// Micropay SMS API token — server-side only, never in JavaScript
define('MICROPAY_TOKEN', '16nI8fd3c366a8a010e76c7a03c7709178af');

// Admin phone number for SMS dispatch
define('ADMIN_PHONE', '0526804680');

// Base URL of the application (used to build signing links in SMS)
define('BASE_URL', 'https://ch-ah.info/FirstApp');

// Admin login credentials — single hardcoded user for this learning project
define('ADMIN_USER', 'Sharonb');
define('ADMIN_PASS', '1532');
