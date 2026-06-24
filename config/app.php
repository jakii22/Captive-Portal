<?php
/**
 * Application Configuration
 */

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Session configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Application settings
define('APP_NAME', 'Captive Portal Hotspot');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('PORTAL_URL', BASE_URL . '/portal');
define('DASHBOARD_URL', BASE_URL . '/dashboard');

// File upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('ADS_UPLOAD_DIR', UPLOAD_DIR . 'ads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// MikroTik Hotspot default
define('DEFAULT_HOTSPOT_LOGIN_URL', 'http://hotspot.local/login');

// Free session limit (seconds)
define('FREE_SESSION_LIMIT', 3600); // 1 hour

// OAuth redirect URIs
define('GOOGLE_REDIRECT_URI', PORTAL_URL . '/social-verification.php?provider=google');
define('FACEBOOK_REDIRECT_URI', PORTAL_URL . '/social-verification.php?provider=facebook');

// Ensure upload directories exist
if (!is_dir(ADS_UPLOAD_DIR)) {
    mkdir(ADS_UPLOAD_DIR, 0755, true);
}

if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}
