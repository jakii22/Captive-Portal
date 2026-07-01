<?php
/**
 * Captive Portal - Landing Page
 * Users choose their login method here
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

// Capture MikroTik parameters (passed by hotspot)
$mac      = $_GET['mac'] ?? '';
$ip       = $_GET['ip'] ?? '';
$linkOrig = $_GET['link-orig'] ?? $_GET['link_orig'] ?? '';

// Store in session for later use
session_start();
if ($mac)      $_SESSION['portal_mac'] = $mac;
if ($ip)       $_SESSION['portal_ip'] = $ip;
if ($linkOrig) $_SESSION['portal_link_orig'] = $linkOrig;

// Build OAuth URLs
$googleClientId  = getSetting('google_client_id');
$facebookAppId   = getSetting('facebook_app_id');

$googleAuthUrl = '';
if (!empty($googleClientId)) {
    $googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'     => $googleClientId,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
        'prompt'        => 'select_account',
        'state'         => bin2hex(random_bytes(16)),
    ]);
    $_SESSION['google_oauth_state'] = $googleAuthUrl;
}

$facebookAuthUrl = '';
if (!empty($facebookAppId)) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['facebook_oauth_state'] = $state;
    $facebookAuthUrl = 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query([
        'client_id'    => $facebookAppId,
        'redirect_uri' => FACEBOOK_REDIRECT_URI,
        'scope'        => 'email,public_profile',
        'state'        => $state,
    ]);
}

$siteName = getSetting('site_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="description" content="<?= sanitizeInput($siteName) ?> - Pilih metode login untuk terhubung ke internet">
    <meta name="robots" content="noindex, nofollow">
    <title><?= sanitizeInput($siteName) ?> - Login</title>
    <link rel="stylesheet" href="assets/css/portal.css?v=1.1">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><circle cx='12' cy='12' r='10' fill='%237c3aed'/></svg>">
</head>
<body>
    <!-- Theme Toggle -->
    <button id="themeToggle" class="portal-theme-toggle" aria-label="Toggle Theme">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
    </button>
    <!-- Animated Background -->
    <div class="portal-bg"></div>
    <div class="particle-grid"></div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <p class="loading-text">Menghubungkan akun Anda...</p>
    </div>

    <!-- Main Container -->
    <div class="portal-container">
        <!-- Brand -->
        <div class="portal-brand">
            <div class="portal-logo">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" stroke-opacity="0.3"/>
                    <path d="M8.5 8.5a5 5 0 0 1 7 0" stroke-linecap="round"/>
                    <path d="M6 6a9 9 0 0 1 12 0" stroke-linecap="round" stroke-opacity="0.5"/>
                    <circle cx="12" cy="14" r="2" fill="currentColor" stroke="none"/>
                </svg>
            </div>
            <h1><?= sanitizeInput($siteName) ?></h1>
            <p>Terhubung ke internet dengan mudah</p>
        </div>

        <!-- Login Card -->
        <div class="login-card">
            <h2 class="login-card-title">Pilih Metode Login</h2>
            <p class="login-card-subtitle">Pilih salah satu cara untuk terhubung ke jaringan Wi-Fi</p>

            <div class="login-methods">
                <!-- Google Login -->
                <?php if (!empty($googleAuthUrl)): ?>
                <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="login-btn login-btn--google" id="btn-google">
                    <div class="login-btn-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                    </div>
                    <div class="login-btn-text">
                        <span>Lanjutkan dengan Google</span>
                        <span>Login menggunakan akun Google</span>
                    </div>
                </a>
                <?php endif; ?>

                <!-- Facebook Login -->
                <?php if (!empty($facebookAuthUrl)): ?>
                <a href="<?= htmlspecialchars($facebookAuthUrl) ?>" class="login-btn login-btn--facebook" id="btn-facebook">
                    <div class="login-btn-icon">
                        <svg viewBox="0 0 24 24" fill="#1877F2">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </div>
                    <div class="login-btn-text">
                        <span>Lanjutkan dengan Facebook</span>
                        <span>Login menggunakan akun Facebook</span>
                    </div>
                </a>
                <?php endif; ?>

                <!-- Divider -->
                <div class="login-divider">atau</div>

                <!-- Free 1 Hour -->
                <a href="free-login.php" class="login-btn login-btn--free" id="btn-free">
                    <div class="login-btn-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12,6 12,12 16,14"/>
                        </svg>
                    </div>
                    <div class="login-btn-text">
                        <span>Akses Gratis 1 Jam</span>
                        <span>Tanpa login, langsung terhubung</span>
                    </div>
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="portal-footer">
            <p>Dengan melanjutkan, Anda menyetujui <a href="#">Syarat & Ketentuan</a> kami.</p>
            <p style="margin-top: 8px; opacity: 0.5;">© <?= date('Y') ?> <?= sanitizeInput($siteName) ?></p>
        </div>
    </div>

    <script src="assets/js/portal.js?v=1.1"></script>
</body>
</html>

