<?php
/**
 * Dashboard - Google & Facebook API Configuration
 * Save OAuth credentials to database settings
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('full');
$admin = getCurrentAdmin();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan tidak valid.');
    } else {
        $section = $_POST['section'] ?? '';

        try {
            if ($section === 'google') {
                setSetting('google_client_id', trim($_POST['google_client_id'] ?? ''));
                setSetting('google_client_secret', trim($_POST['google_client_secret'] ?? ''));
                setSetting('google_redirect_uri', trim($_POST['google_redirect_uri'] ?? ''));
                setFlash('success', 'Konfigurasi Google API berhasil disimpan.');
            } elseif ($section === 'facebook') {
                setSetting('facebook_app_id', trim($_POST['facebook_app_id'] ?? ''));
                setSetting('facebook_app_secret', trim($_POST['facebook_app_secret'] ?? ''));
                setSetting('facebook_redirect_uri', trim($_POST['facebook_redirect_uri'] ?? ''));
                setFlash('success', 'Konfigurasi Facebook API berhasil disimpan.');
            } elseif ($section === 'general') {
                setSetting('site_name', trim($_POST['site_name'] ?? ''));
                setSetting('hotspot_login_url', trim($_POST['hotspot_login_url'] ?? ''));
                setSetting('free_session_limit_seconds', trim($_POST['free_session_limit'] ?? '3600'));
                setFlash('success', 'Konfigurasi umum berhasil disimpan.');
            }
        } catch (Exception $e) {
            error_log('Google API Config Error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan konfigurasi.');
        }
    }

    header('Location: google-api.php');
    exit;
}

// Load current settings
$googleClientId     = getSetting('google_client_id');
$googleClientSecret = getSetting('google_client_secret');
$googleRedirectUri  = getSetting('google_redirect_uri', GOOGLE_REDIRECT_URI);
$facebookAppId      = getSetting('facebook_app_id');
$facebookAppSecret  = getSetting('facebook_app_secret');
$facebookRedirectUri = getSetting('facebook_redirect_uri', FACEBOOK_REDIRECT_URI);
$siteName           = getSetting('site_name', APP_NAME);
$hotspotLoginUrl    = getSetting('hotspot_login_url', DEFAULT_HOTSPOT_LOGIN_URL);
$freeSessionLimit   = getSetting('free_session_limit_seconds', (string) FREE_SESSION_LIMIT);

$csrfToken = generateCsrfToken();
$flash = getFlash();
$currentPage = 'google-api';
$pageTitle = 'API Configuration';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $pageTitle ?> - Admin Panel</title>
    <link rel="stylesheet" href="assets/css/dashboard.css?v=1.2">
</head>
<body>
<div class="dashboard-layout">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <div class="main-content">
        <header class="main-header">
            <div class="flex items-center gap-4">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <h2 class="main-header-title"><?= $pageTitle ?></h2>
            </div>
        </header>

        <main class="main-body">
            <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= sanitizeInput($flash['message']) ?>
            </div>
            <?php endif; ?>

            <!-- General Settings -->
            <div class="card mb-6">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        Pengaturan Umum
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="section" value="general">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Nama Hotspot</label>
                                <input type="text" name="site_name" class="form-input" value="<?= sanitizeInput($siteName) ?>" placeholder="Nama Wi-Fi Anda">
                            </div>
                            <div class="form-group">
                                <label class="form-label">MikroTik Hotspot Login URL</label>
                                <input type="url" name="hotspot_login_url" class="form-input" value="<?= sanitizeInput($hotspotLoginUrl) ?>" placeholder="http://hotspot.local/login">
                                <p class="form-help">URL login page MikroTik hotspot</p>
                            </div>
                        </div>
                        <div class="form-group" style="max-width:300px;">
                            <label class="form-label">Batas Sesi Gratis (detik)</label>
                            <input type="number" name="free_session_limit" class="form-input" value="<?= sanitizeInput($freeSessionLimit) ?>" min="300" max="86400">
                            <p class="form-help">Default: 3600 detik (1 jam)</p>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Simpan Pengaturan Umum
                        </button>
                    </form>
                </div>
            </div>

            <!-- Google API -->
            <div class="card mb-6">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" style="vertical-align:-3px;margin-right:6px;">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                        Google OAuth API
                    </h3>
                    <span class="badge <?= !empty($googleClientId) ? 'badge-green' : 'badge-rose' ?>">
                        <?= !empty($googleClientId) ? 'Dikonfigurasi' : 'Belum dikonfigurasi' ?>
                    </span>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="section" value="google">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <div class="form-group">
                            <label class="form-label">Client ID</label>
                            <input type="text" name="google_client_id" class="form-input" value="<?= sanitizeInput($googleClientId) ?>" placeholder="xxxxxxx.apps.googleusercontent.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Client Secret</label>
                            <input type="password" name="google_client_secret" class="form-input" value="<?= sanitizeInput($googleClientSecret) ?>" placeholder="GOCSPX-xxxxxxxxx">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Redirect URI</label>
                            <input type="url" name="google_redirect_uri" class="form-input" value="<?= sanitizeInput($googleRedirectUri) ?>" placeholder="<?= GOOGLE_REDIRECT_URI ?>">
                            <p class="form-help">Default: <?= GOOGLE_REDIRECT_URI ?></p>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Simpan Google API
                        </button>
                    </form>
                </div>
            </div>

            <!-- Facebook API -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#1877F2" style="vertical-align:-3px;margin-right:6px;">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                        Facebook OAuth API
                    </h3>
                    <span class="badge <?= !empty($facebookAppId) ? 'badge-green' : 'badge-rose' ?>">
                        <?= !empty($facebookAppId) ? 'Dikonfigurasi' : 'Belum dikonfigurasi' ?>
                    </span>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="section" value="facebook">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <div class="form-group">
                            <label class="form-label">App ID</label>
                            <input type="text" name="facebook_app_id" class="form-input" value="<?= sanitizeInput($facebookAppId) ?>" placeholder="123456789012345">
                        </div>
                        <div class="form-group">
                            <label class="form-label">App Secret</label>
                            <input type="password" name="facebook_app_secret" class="form-input" value="<?= sanitizeInput($facebookAppSecret) ?>" placeholder="abcdef1234567890">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Redirect URI</label>
                            <input type="url" name="facebook_redirect_uri" class="form-input" value="<?= sanitizeInput($facebookRedirectUri) ?>" placeholder="<?= FACEBOOK_REDIRECT_URI ?>">
                            <p class="form-help">Default: <?= FACEBOOK_REDIRECT_URI ?></p>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Simpan Facebook API
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="assets/js/dashboard.js?v=1.2"></script>
</body>
</html>
