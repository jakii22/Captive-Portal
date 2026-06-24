<?php
/**
 * Free Login - 1 Hour Guest Access
 * Generates guest credentials with time limit enforced both
 * on application side and via MikroTik User Profile
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();

// Get MAC address from session (captured from MikroTik redirect)
$mac = $_SESSION['portal_mac'] ?? '';
$ip  = $_SESSION['portal_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

try {
    // ── Application-side check: Has this MAC already used free session? ──
    if (!empty($mac) && isFreeSessionExpired($mac)) {
        // Already used free session within the limit period
        $_SESSION['portal_error'] = 'Anda sudah menggunakan akses gratis 1 jam. Silakan login dengan Google atau Facebook untuk melanjutkan.';
        header('Location: ' . PORTAL_URL . '/index.php?error=1');
        exit;
    }

    $db = Database::getInstance();

    // Generate guest username (based on MAC or random)
    if (!empty($mac)) {
        // Normalize MAC address (remove colons/dashes)
        $macClean = strtolower(preg_replace('/[^a-f0-9]/i', '', $mac));
        $usernameIdentity = 'guest_' . $macClean;
    } else {
        $usernameIdentity = 'guest_' . bin2hex(random_bytes(6));
    }

    // ── STEP A: Save to `users` table ──
    $userSaved = upsertUser(
        $usernameIdentity,
        'Tamu Gratis',
        'free',
        null,
        null
    );

    if (!$userSaved) {
        throw new RuntimeException('Gagal menyimpan data pengguna.');
    }

    // ── STEP B: Generate dummy password & save to `radcheck` ──
    $dummyPassword = generateDummyPassword();

    $radcheckSaved = upsertRadcheck($usernameIdentity, $dummyPassword);

    if (!$radcheckSaved) {
        throw new RuntimeException('Gagal menyimpan kredensial RADIUS.');
    }

    // ── STEP C: Log the free session (application-side limit) ──
    if (!empty($mac)) {
        logFreeSession($mac, $ip, $usernameIdentity);
    }

    // Store session info
    $_SESSION['login_success']  = true;
    $_SESSION['login_username'] = $usernameIdentity;
    $_SESSION['login_name']     = 'Tamu Gratis';
    $_SESSION['login_method']   = 'free';
    $_SESSION['login_time']     = time();
    $_SESSION['session_limit']  = (int) getSetting('free_session_limit_seconds', (string) FREE_SESSION_LIMIT);

    // ── STEP D: Redirect to MikroTik hotspot login ──
    // MikroTik User Profile should also enforce 1-hour limit
    redirectToMikrotik($usernameIdentity, $dummyPassword);

} catch (PDOException $e) {
    error_log('Free Login DB Error: ' . $e->getMessage());
    $_SESSION['portal_error'] = 'Terjadi kesalahan database. Silakan coba lagi.';
    header('Location: ' . PORTAL_URL . '/index.php?error=1');
    exit;
} catch (Exception $e) {
    error_log('Free Login Error: ' . $e->getMessage());
    $_SESSION['portal_error'] = $e->getMessage();
    header('Location: ' . PORTAL_URL . '/index.php?error=1');
    exit;
}
