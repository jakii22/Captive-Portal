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
 * Save or update radreply entry (upsert)
 */
function upsertRadreply(string $username, string $attribute, string $value): bool
{
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM radreply WHERE username = :username AND attribute = :attribute LIMIT 1');
        $stmt->execute([':username' => $username, ':attribute' => $attribute]);
        $row = $stmt->fetch();

        if ($row) {
            $stmtUpdate = $db->prepare('UPDATE radreply SET value = :value WHERE id = :id');
            return $stmtUpdate->execute([':value' => $value, ':id' => $row['id']]);
        } else {
            $stmtInsert = $db->prepare('INSERT INTO radreply (username, attribute, op, value) VALUES (:username, :attribute, \'=\', :value)');
            return $stmtInsert->execute([':username' => $username, ':attribute' => $attribute, ':value' => $value]);
        }
    } catch (PDOException $e) {
        error_log('upsertRadreply error: ' . $e->getMessage());
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

function redirectToMikrotik(string $username, string $password): void
{
    $hotspotUrl = getSetting('hotspot_login_url', 'http://hotspot.local/login');
    $successUrl = PORTAL_URL . '/success.php';
    
    // Build MikroTik login URL with credentials and destination
    // dst parameter tells MikroTik where to redirect after successful login
    $loginUrl = $hotspotUrl . '?' . http_build_query([
        'username' => $username,
        'password' => $password,
        'dst'      => $successUrl,
    ]);
    
    // Direct redirect to MikroTik login URL.
    // IMPORTANT: We MUST navigate the main window to MikroTik's login URL.
    // Hidden iframe/fetch approaches fail due to mixed content (HTTPS portal → HTTP hotspot).
    // MikroTik needs the main browser window to process authentication cookies.
    echo '<!DOCTYPE html>
<html>
<head>
    <title>Connecting...</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: sans-serif; text-align: center; margin-top: 50px; background: #0f0f1a; color: #fff; }
        .spinner { width: 40px; height: 40px; border: 4px solid rgba(124,58,237,0.3); border-top-color: #7c3aed; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 20px auto; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="spinner"></div>
    <h3>Menghubungkan ke Internet...</h3>
    <p>Silakan tunggu beberapa saat.</p>
    <script>
        window.location.replace("' . $loginUrl . '");
    </script>
</body>
</html>';
    exit;
}

/**
 * Get active free session for a MAC address (still within time limit)
 * Returns session data if found, null if no active session
 */
function getActiveFreeSession(string $macAddress): ?array
{
    try {
        $db = Database::getInstance();
        $limitSeconds = (int) getSetting('free_session_limit_seconds', (string) FREE_SESSION_LIMIT);

        $stmt = $db->prepare(
            'SELECT * FROM free_session_log
             WHERE mac_address = :mac
             AND session_start > (CURRENT_TIMESTAMP - INTERVAL \'1 second\' * :limit)
             AND is_expired = FALSE
             ORDER BY session_start DESC
             LIMIT 1'
        );
        $stmt->execute([
            ':mac'   => $macAddress,
            ':limit' => $limitSeconds,
        ]);
        $result = $stmt->fetch();

        return $result ?: null;
    } catch (PDOException $e) {
        error_log('getActiveFreeSession error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Check if a MAC has used up its free session (session expired / past the time limit)
 * Returns true only if there's a PAST session that has run out
 */
function hasFreeSessionUsedUp(string $macAddress): bool
{
    try {
        $db = Database::getInstance();
        $limitSeconds = (int) getSetting('free_session_limit_seconds', (string) FREE_SESSION_LIMIT);

        // Check for sessions that started within the cooldown period but are marked expired
        $stmt = $db->prepare(
            'SELECT COUNT(*) as count FROM free_session_log
             WHERE mac_address = :mac
             AND session_start > (CURRENT_TIMESTAMP - INTERVAL \'1 second\' * :limit)
             AND is_expired = TRUE'
        );
        $stmt->execute([
            ':mac'   => $macAddress,
            ':limit' => $limitSeconds,
        ]);
        $result = $stmt->fetch();

        return ($result['count'] ?? 0) > 0;
    } catch (PDOException $e) {
        error_log('hasFreeSessionUsedUp error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get existing password from radcheck for a username
 */
function getRadcheckPassword(string $username): ?string
{
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT value FROM radcheck WHERE username = :username AND attribute = 'Cleartext-Password' LIMIT 1");
        $stmt->execute([':username' => $username]);
        $result = $stmt->fetch();
        return $result ? $result['value'] : null;
    } catch (PDOException $e) {
        error_log('getRadcheckPassword error: ' . $e->getMessage());
        return null;
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
 * Delete a portal user and all related RADIUS data
 * Removes: users, radcheck, radreply, radacct, radusergroup, radpostauth, free_session_log
 */
function deleteUser(int $userId): bool
{
    try {
        $db = Database::getInstance();

        // First, get the username_identity for this user
        $stmt = $db->prepare('SELECT username_identity FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        $username = $user['username_identity'];

        // Disconnect user from MikroTik first (if configured)
        disconnectMikrotikUser($username);

        $db->beginTransaction();

        // Delete from RADIUS tables
        $tables = ['radcheck', 'radreply', 'radusergroup', 'radpostauth'];
        foreach ($tables as $table) {
            $stmt = $db->prepare("DELETE FROM {$table} WHERE username = :username");
            $stmt->execute([':username' => $username]);
        }

        // Delete accounting records
        $stmt = $db->prepare('DELETE FROM radacct WHERE username = :username');
        $stmt->execute([':username' => $username]);

        // Delete free session logs
        $stmt = $db->prepare('DELETE FROM free_session_log WHERE username_identity = :username');
        $stmt->execute([':username' => $username]);

        // Finally delete the user record
        $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);

        $db->commit();
        return true;

    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('deleteUser error: ' . $e->getMessage());
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

/**
 * Disconnect a user from MikroTik active hotspot sessions
 * @throws RuntimeException if fails
 */
function disconnectMikrotikUser(string $username): bool
{
    $mtIp       = getSetting('mikrotik_ip');
    $mtUsername = getSetting('mikrotik_username');
    $mtPassword = getSetting('mikrotik_password');
    $mtPort     = getSetting('mikrotik_port', '8728');

    if (empty($mtIp) || empty($mtUsername)) {
        throw new RuntimeException("Konfigurasi API MikroTik belum diisi.");
    }

    require_once __DIR__ . '/RouterOS/RouterosAPI.php';
    try {
        $api = new RouterosAPI();
        $api->setTimeout(3);
        $api->setPort((int) $mtPort);
        $api->setAttempts(1);

        if (!$api->connect($mtIp, $mtUsername, $mtPassword)) {
            $error = $api->getError() ?: 'Timeout/Connection Refused';
            throw new RuntimeException("Koneksi API gagal: " . $error);
        }

        // Fetch ALL active sessions and filter in PHP (more robust than API query)
        $activeSessions = $api->comm('/ip/hotspot/active/print');
        $cookies = $api->comm('/ip/hotspot/cookie/print');

        $success = false;
        $found = false;

        if (is_array($activeSessions)) {
            foreach ($activeSessions as $session) {
                if (isset($session['user']) && $session['user'] === $username && isset($session['.id'])) {
                    $found = true;
                    $api->comm('/ip/hotspot/active/remove', [
                        '.id' => $session['.id']
                    ]);
                    $success = true;
                }
            }
        }

        if (is_array($cookies)) {
            foreach ($cookies as $cookie) {
                if (isset($cookie['user']) && $cookie['user'] === $username && isset($cookie['.id'])) {
                    $found = true;
                    $api->comm('/ip/hotspot/cookie/remove', [
                        '.id' => $cookie['.id']
                    ]);
                    $success = true;
                }
            }
        }

        $api->disconnect();

        if (!$found) {
            // FreeRADIUS radacct sometimes has stale sessions. 
            // If it's not in MikroTik (active or cookie), we consider it already disconnected.
            return false;
        }
        
        return $success;

    } catch (Exception $e) {
        throw new RuntimeException("Error RouterOS API: " . $e->getMessage());
    }
}
