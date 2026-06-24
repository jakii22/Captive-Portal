<?php
/**
 * Helper Functions
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Generate a random dummy password for RADIUS
 */
function generateDummyPassword(int $length = 16): string
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Format bytes to human-readable format
 */
function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Format seconds to human-readable duration
 */
function formatDuration(int $seconds): string
{
    if ($seconds < 60) {
        return $seconds . ' detik';
    }

    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    $parts = [];
    if ($hours > 0) $parts[] = $hours . ' jam';
    if ($minutes > 0) $parts[] = $minutes . ' menit';
    if ($secs > 0 && $hours == 0) $parts[] = $secs . ' detik';

    return implode(' ', $parts);
}

/**
 * Sanitize input string
 */
function sanitizeInput(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Get a setting value from database
 */
function getSetting(string $key, string $default = ''): string
{
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = :key');
        $stmt->execute([':key' => $key]);
        $result = $stmt->fetch();
        return $result ? ($result['setting_value'] ?? $default) : $default;
    } catch (PDOException $e) {
        error_log('getSetting error: ' . $e->getMessage());
        return $default;
    }
}

/**
 * Set a setting value in database
 */
function setSetting(string $key, string $value): bool
{
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO settings (setting_key, setting_value, updated_at)
             VALUES (:key, :value, CURRENT_TIMESTAMP)
             ON CONFLICT (setting_key) DO UPDATE SET
                setting_value = :value2,
                updated_at = CURRENT_TIMESTAMP'
        );
        return $stmt->execute([
            ':key'    => $key,
            ':value'  => $value,
            ':value2' => $value,
        ]);
    } catch (PDOException $e) {
        error_log('setSetting error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get all active advertisements
 */
function getActiveAds(): array
{
    try {
        $db = Database::getInstance();
        $stmt = $db->query(
            'SELECT * FROM advertisements WHERE is_active = TRUE ORDER BY display_order ASC, created_at DESC'
        );
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('getActiveAds error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Save or update user in the users table (upsert)
 */
function upsertUser(string $usernameIdentity, string $name, string $loginMethod, ?string $photoUrl = null, ?string $gender = null): bool
{
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO users (username_identity, name, login_method, photo_url, gender)
             VALUES (:username, :name, :method, :photo, :gender)
             ON CONFLICT (username_identity) DO UPDATE SET
                name = COALESCE(EXCLUDED.name, users.name),
                photo_url = COALESCE(EXCLUDED.photo_url, users.photo_url),
                login_method = EXCLUDED.login_method'
        );
        return $stmt->execute([
            ':username' => $usernameIdentity,
            ':name'     => $name,
            ':method'   => $loginMethod,
            ':photo'    => $photoUrl,
            ':gender'   => $gender,
        ]);
    } catch (PDOException $e) {
        error_log('upsertUser error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Save or update radcheck entry (upsert)
 */
function upsertRadcheck(string $username, string $password): bool
{
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO radcheck (username, attribute, op, value)
             VALUES (:username, \'Cleartext-Password\', \':=\', :password)
             ON CONFLICT (username) DO UPDATE SET
                value = EXCLUDED.value'
        );
        return $stmt->execute([
            ':username' => $username,
            ':password' => $password,
        ]);
    } catch (PDOException $e) {
        error_log('upsertRadcheck error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Build MikroTik hotspot login redirect URL
 */
function buildMikrotikLoginUrl(string $username, string $password): string
{
    $hotspotUrl = getSetting('hotspot_login_url', DEFAULT_HOTSPOT_LOGIN_URL);
    return $hotspotUrl . '?' . http_build_query([
        'username' => $username,
        'password' => $password,
    ]);
}

/**
 * Redirect to MikroTik hotspot login
 */
function redirectToMikrotik(string $username, string $password): void
{
    $url = buildMikrotikLoginUrl($username, $password);
    header('Location: ' . $url);
    exit;
}

/**
 * Check if a free session MAC address has exceeded the limit
 */
function isFreeSessionExpired(string $macAddress): bool
{
    try {
        $db = Database::getInstance();
        $limitSeconds = (int) getSetting('free_session_limit_seconds', (string) FREE_SESSION_LIMIT);

        // Check if there's an active session within the limit period
        $stmt = $db->prepare(
            'SELECT COUNT(*) as count FROM free_session_log
             WHERE mac_address = :mac
             AND session_start > (CURRENT_TIMESTAMP - INTERVAL \'1 second\' * :limit)
             AND is_expired = FALSE'
        );
        $stmt->execute([
            ':mac'   => $macAddress,
            ':limit' => $limitSeconds,
        ]);
        $result = $stmt->fetch();

        return ($result['count'] ?? 0) > 0;
    } catch (PDOException $e) {
        error_log('isFreeSessionExpired error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Log a free session start
 */
function logFreeSession(string $macAddress, string $ipAddress, string $usernameIdentity): bool
{
    try {
        $db = Database::getInstance();
        $limitSeconds = (int) getSetting('free_session_limit_seconds', (string) FREE_SESSION_LIMIT);

        $stmt = $db->prepare(
            'INSERT INTO free_session_log (mac_address, ip_address, username_identity, session_limit_seconds)
             VALUES (:mac, :ip, :username, :limit)'
        );
        return $stmt->execute([
            ':mac'      => $macAddress,
            ':ip'       => $ipAddress,
            ':username' => $usernameIdentity,
            ':limit'    => $limitSeconds,
        ]);
    } catch (PDOException $e) {
        error_log('logFreeSession error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Generate CSRF token
 */
function generateCsrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCsrfToken(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash message helper
 */
function setFlash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 */
function getFlash(): ?array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * JSON response helper
 */
function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Get pagination parameters
 */
function getPagination(int $totalItems, int $perPage = 20, int $currentPage = 1): array
{
    $totalPages = max(1, (int) ceil($totalItems / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total_items'  => $totalItems,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => $offset,
        'has_prev'     => $currentPage > 1,
        'has_next'     => $currentPage < $totalPages,
    ];
}
