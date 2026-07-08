<?php
/**
 * Dashboard - Settings
 * Combined configurations for General, Google/FB API, and MikroTik
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/RouterOS/RouterosAPI.php';

requireLogin();
requireRole('full');
$admin = getCurrentAdmin();
$currentTab = $_GET['tab'] ?? 'general';

// Handle database backup download
if (isset($_GET['action']) && $_GET['action'] === 'backup') {
    if (!validateCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan tidak valid.');
        header('Location: settings.php?tab=database');
        exit;
    }

    try {
        $creds = Database::getCredentials();
        
        // Clean outputs buffer to ensure no PHP whitespace/warning is pre-pended
        if (ob_get_level()) ob_end_clean();
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="backup_captive_portal_' . date('Y-m-d_H-i-s') . '.sql"');
        
        putenv("PGPASSWORD=" . $creds['pass']);
        $cmd = sprintf(
            'pg_dump -h %s -p %s -U %s -d %s',
            escapeshellarg($creds['host']),
            escapeshellarg($creds['port']),
            escapeshellarg($creds['user']),
            escapeshellarg($creds['name'])
        );
        passthru($cmd);
        exit;
    } catch (Exception $e) {
        setFlash('error', 'Gagal memproses backup: ' . $e->getMessage());
        header('Location: settings.php?tab=database');
        exit;
    }
}

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
                setSetting('dev_mode', isset($_POST['dev_mode']) ? '1' : '0');
                setFlash('success', 'Konfigurasi umum berhasil disimpan.');
            } elseif ($section === 'mikrotik') {
                setSetting('mikrotik_ip', trim($_POST['mikrotik_ip'] ?? ''));
                setSetting('mikrotik_username', trim($_POST['mikrotik_username'] ?? ''));
                setSetting('mikrotik_password', trim($_POST['mikrotik_password'] ?? ''));
                setSetting('mikrotik_port', trim($_POST['mikrotik_port'] ?? '8728'));
                setFlash('success', 'Konfigurasi MikroTik berhasil disimpan.');
            } elseif ($section === 'portal_appearance') {
                setSetting('portal_subtitle', trim($_POST['portal_subtitle'] ?? 'Terhubung ke internet dengan mudah'));
                setSetting('portal_accent_color', trim($_POST['portal_accent_color'] ?? '#7c3aed'));
                setSetting('portal_accent_secondary', trim($_POST['portal_accent_secondary'] ?? '#06b6d4'));
                setSetting('portal_bg_color', trim($_POST['portal_bg_color'] ?? '#f0f2f5'));
                setSetting('portal_footer_text', trim($_POST['portal_footer_text'] ?? 'Okenet Hotspot'));

                // Handle logo upload
                if (!empty($_FILES['portal_logo']['name']) && $_FILES['portal_logo']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['portal_logo'];
                    $allowedTypes = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'];
                    if (in_array($file['type'], $allowedTypes) && $file['size'] <= MAX_UPLOAD_SIZE) {
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $filename = 'portal_logo_' . time() . '.' . $ext;
                        $destDir = __DIR__ . '/../uploads/portal/';
                        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                        if (move_uploaded_file($file['tmp_name'], $destDir . $filename)) {
                            // Delete old logo
                            $oldLogo = getSetting('portal_custom_logo_url');
                            if ($oldLogo && file_exists(__DIR__ . '/../' . $oldLogo)) {
                                @unlink(__DIR__ . '/../' . $oldLogo);
                            }
                            setSetting('portal_custom_logo_url', 'uploads/portal/' . $filename);
                        }
                    } else {
                        setFlash('error', 'Logo harus berformat PNG/JPG/SVG/WebP dan maksimal 5MB.');
                        header('Location: settings.php');
                        exit;
                    }
                }

                // Handle reset logo
                if (isset($_POST['reset_logo']) && $_POST['reset_logo'] === '1') {
                    $oldLogo = getSetting('portal_custom_logo_url');
                    if ($oldLogo && file_exists(__DIR__ . '/../' . $oldLogo)) {
                        @unlink(__DIR__ . '/../' . $oldLogo);
                    }
                    setSetting('portal_custom_logo_url', '');
                }

            } elseif ($section === 'database_restore') {
                if (empty($_FILES['backup_file']['name']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('File backup tidak valid atau tidak dipilih.');
                }

                $file = $_FILES['backup_file'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if ($ext !== 'sql') {
                    throw new RuntimeException('Format file harus berupa .sql');
                }

                $creds = Database::getCredentials();
                putenv("PGPASSWORD=" . $creds['pass']);

                // Run psql command to restore database
                $cmd = sprintf(
                    'psql -h %s -p %s -U %s -d %s -f %s 2>&1',
                    escapeshellarg($creds['host']),
                    escapeshellarg($creds['port']),
                    escapeshellarg($creds['user']),
                    escapeshellarg($creds['name']),
                    escapeshellarg($file['tmp_name'])
                );

                exec($cmd, $output, $returnVar);

                if ($returnVar !== 0) {
                    $errorMsg = implode("\n", $output);
                    error_log("Database Restore Error: " . $errorMsg);
                    throw new RuntimeException('Restore gagal: ' . substr($errorMsg, 0, 200));
                }

                setFlash('success', 'Database berhasil dipulihkan!');
                $section = 'database';
            }
        } catch (Exception $e) {
            error_log('Config Error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan konfigurasi.');
        }
    }

    header('Location: settings.php?tab=' . urlencode($section ?? 'general'));
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
$devMode            = getSetting('dev_mode', '0');

$mtIp       = getSetting('mikrotik_ip', '192.168.88.1');
$mtUsername  = getSetting('mikrotik_username', 'admin');
$mtPassword = getSetting('mikrotik_password', '');
$mtPort     = getSetting('mikrotik_port', '8728');

// Portal appearance settings
$portalSubtitle      = getSetting('portal_subtitle', 'Terhubung ke internet dengan mudah');
$portalAccentColor   = getSetting('portal_accent_color', '#7c3aed');
$portalAccentSecondary = getSetting('portal_accent_secondary', '#06b6d4');
$portalBgColor       = getSetting('portal_bg_color', '#f0f2f5');
$portalFooterText    = getSetting('portal_footer_text', 'Okenet Hotspot');
$portalCustomLogo    = getSetting('portal_custom_logo_url', '');

// Try to connect and fetch MikroTik status
$connected = false;
$routerInfo = [];
$hotspotUsers = [];
$mtError = '';

if (!empty($mtIp) && !empty($mtUsername)) {
    try {
        $api = new RouterosAPI();
        $api->setTimeout(3);
        $api->setPort((int) $mtPort);
        $api->setAttempts(1);

        if ($api->connect($mtIp, $mtUsername, $mtPassword)) {
            $connected = true;

            // Get system resource
            $resource = $api->comm('/system/resource/print');
            if (!empty($resource[0])) {
                $routerInfo = $resource[0];
            }

            // Get system identity
            $identity = $api->comm('/system/identity/print');
            if (!empty($identity[0])) {
                $routerInfo['identity'] = $identity[0]['name'] ?? 'Unknown';
            }

            // Get active hotspot users
            $hotspotUsers = $api->comm('/ip/hotspot/active/print');

            $api->disconnect();
        } else {
            $mtError = $api->getError();
        }
    } catch (Exception $e) {
        $mtError = $e->getMessage();
        error_log('MikroTik Connection Error: ' . $mtError);
    }
}

$csrfToken = generateCsrfToken();
$flash = getFlash();
$currentPage = 'settings';
$pageTitle = 'Settings';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $pageTitle ?> - Admin Panel</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
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
            <div class="main-header-actions">
                <span class="<?= $connected ? 'text-green' : 'text-rose' ?>" style="font-size:0.85rem;">
                    <span class="status-dot <?= $connected ? 'status-dot--online' : 'status-dot--offline' ?>"></span>
                    MikroTik: <?= $connected ? 'Terhubung' : 'Tidak Terhubung' ?>
                </span>
            </div>
        </header>

        <main class="main-body">
            <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?> mb-6">
                <?= sanitizeInput($flash['message']) ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($mtError)): ?>
            <div class="alert alert-error mb-6">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                Gagal terhubung ke MikroTik: <?= sanitizeInput($mtError) ?>
            </div>
            <?php endif; ?>

            <?php if ($currentTab === 'general'): ?>
                    <!-- General Settings -->
                    <div class="card mb-6" id="general">
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
                                <div class="form-group">
                                    <label class="form-label">Nama Hotspot</label>
                                    <input type="text" name="site_name" class="form-input" value="<?= sanitizeInput($siteName) ?>" placeholder="Nama Wi-Fi Anda">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">MikroTik Hotspot Login URL</label>
                                    <input type="url" name="hotspot_login_url" class="form-input" value="<?= sanitizeInput($hotspotLoginUrl) ?>" placeholder="http://hotspot.local/login">
                                    <p class="form-help">URL login page MikroTik hotspot</p>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Batas Sesi Gratis (detik)</label>
                                    <input type="number" name="free_session_limit" class="form-input" value="<?= sanitizeInput($freeSessionLimit) ?>" min="300" max="86400">
                                    <p class="form-help">Default: 3600 detik (1 jam)</p>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                        <input type="checkbox" name="dev_mode" value="1" <?= $devMode === '1' ? 'checked' : '' ?>>
                                        Developer Mode (Bypass Hotspot Guard)
                                    </label>
                                    <p class="form-help">Aktifkan untuk dapat mengakses halaman portal di luar jaringan MikroTik. <strong>Jangan lupa nonaktifkan saat production!</strong></p>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                    Simpan Pengaturan Umum
                                </button>
                            </form>
                        </div>
                    </div>
            <?php endif; ?>

            <?php if ($currentTab === 'mikrotik'): ?>
                    <!-- MikroTik API Config -->
                    <div class="card" id="mikrotik">
                        <div class="card-header">
                            <h3 class="card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px;"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                                Konfigurasi MikroTik
                            </h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="section" value="mikrotik">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <div class="form-group">
                                    <label class="form-label">IP Address Router</label>
                                    <input type="text" name="mikrotik_ip" class="form-input" value="<?= sanitizeInput($mtIp) ?>" placeholder="192.168.88.1">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="mikrotik_username" class="form-input" value="<?= sanitizeInput($mtUsername) ?>" placeholder="admin">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="mikrotik_password" class="form-input" value="<?= sanitizeInput($mtPassword) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">API Port</label>
                                    <input type="number" name="mikrotik_port" class="form-input" value="<?= sanitizeInput($mtPort) ?>" placeholder="8728">
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                                    Simpan & Hubungkan MikroTik
                                </button>
                            </form>
                        </div>
                    </div>
            <?php endif; ?>

            <?php if ($currentTab === 'google'): ?>
                    <!-- Google API -->
                    <div class="card mb-6" id="google">
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
            <?php endif; ?>

            <?php if ($currentTab === 'facebook'): ?>
                    <!-- Facebook API -->
                    <div class="card" id="facebook">
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
            <?php endif; ?>

            <?php if ($currentTab === 'appearance'): ?>
            <!-- Portal Appearance (Full Width) -->
            <div class="card mt-6" id="appearance">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px;"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        Tampilan Portal (Landing Page Builder)
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="section" value="portal_appearance">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                            <!-- Left: Form Fields -->
                            <div>
                                <div class="form-group">
                                    <label class="form-label">Subtitle Portal</label>
                                    <input type="text" name="portal_subtitle" class="form-input" value="<?= sanitizeInput($portalSubtitle) ?>" placeholder="Terhubung ke internet dengan mudah">
                                    <p class="form-help">Teks di bawah nama hotspot</p>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Teks Footer</label>
                                    <input type="text" name="portal_footer_text" class="form-input" value="<?= sanitizeInput($portalFooterText) ?>" placeholder="Okenet Hotspot">
                                    <p class="form-help">Teks copyright di bagian bawah portal</p>
                                </div>

                                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
                                    <div class="form-group">
                                        <label class="form-label">Warna Aksen</label>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <input type="color" name="portal_accent_color" value="<?= sanitizeInput($portalAccentColor) ?>" style="width:40px;height:36px;border:1px solid var(--glass-border);border-radius:8px;background:none;cursor:pointer;padding:2px;">
                                            <input type="text" class="form-input" value="<?= sanitizeInput($portalAccentColor) ?>" readonly style="font-size:0.8rem;">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Warna Sekunder</label>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <input type="color" name="portal_accent_secondary" value="<?= sanitizeInput($portalAccentSecondary) ?>" style="width:40px;height:36px;border:1px solid var(--glass-border);border-radius:8px;background:none;cursor:pointer;padding:2px;">
                                            <input type="text" class="form-input" value="<?= sanitizeInput($portalAccentSecondary) ?>" readonly style="font-size:0.8rem;">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Background</label>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <input type="color" name="portal_bg_color" value="<?= sanitizeInput($portalBgColor) ?>" style="width:40px;height:36px;border:1px solid var(--glass-border);border-radius:8px;background:none;cursor:pointer;padding:2px;">
                                            <input type="text" class="form-input" value="<?= sanitizeInput($portalBgColor) ?>" readonly style="font-size:0.8rem;">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Upload Logo Kustom</label>
                                    <input type="file" name="portal_logo" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="form-input" style="padding:8px;">
                                    <p class="form-help">PNG/JPG/SVG/WebP, maks 5MB. Kosongkan jika tidak ingin mengubah.</p>
                                    <?php if (!empty($portalCustomLogo)): ?>
                                    <div style="margin-top:8px;display:flex;align-items:center;gap:12px;">
                                        <img src="../<?= sanitizeInput($portalCustomLogo) ?>" alt="Logo" style="height:36px;width:auto;border-radius:6px;background:rgba(255,255,255,0.1);padding:4px;">
                                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:0.8rem;color:#f43f5e;">
                                            <input type="checkbox" name="reset_logo" value="1"> Hapus logo kustom
                                        </label>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                    Simpan Tampilan Portal
                                </button>
                            </div>

                            <!-- Right: Live Preview -->
                            <div>
                                <label class="form-label" style="margin-bottom:12px;">Live Preview</label>
                                <div id="portalPreview" style="background:<?= sanitizeInput($portalBgColor) ?>;border-radius:16px;padding:32px 20px;text-align:center;border:1px solid var(--glass-border);min-height:320px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;position:relative;overflow:hidden;">
                                    <div style="position:absolute;inset:0;background:radial-gradient(circle at 20% 20%, <?= sanitizeInput($portalAccentColor) ?>22 0%, transparent 50%),radial-gradient(circle at 80% 80%, <?= sanitizeInput($portalAccentSecondary) ?>22 0%, transparent 50%);pointer-events:none;"></div>
                                    <div style="position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;gap:8px;">
                                        <?php if (!empty($portalCustomLogo)): ?>
                                        <img src="../<?= sanitizeInput($portalCustomLogo) ?>" alt="Logo" style="height:40px;width:auto;margin-bottom:8px;">
                                        <?php else: ?>
                                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                                            <img src="../portal/assets/img/okenet.png" alt="Okenet" style="height:28px;width:auto;">
                                            <span style="opacity:0.4;font-size:0.9rem;">×</span>
                                            <img src="../portal/assets/img/komdigi.svg" alt="Komdigi" style="height:32px;width:auto;">
                                        </div>
                                        <?php endif; ?>
                                        <div style="font-size:1.1rem;font-weight:700;font-style:italic;color:<?= sanitizeInput($portalAccentSecondary) ?>;"><?= sanitizeInput(getSetting('site_name', APP_NAME)) ?></div>
                                        <div style="font-size:0.75rem;color:#666;" id="previewSubtitle"><?= sanitizeInput($portalSubtitle) ?></div>
                                    </div>
                                    <div style="position:relative;z-index:1;background:rgba(255,255,255,0.9);border-radius:12px;padding:16px;width:100%;max-width:250px;box-shadow:0 4px 16px rgba(0,0,0,0.06);">
                                        <div style="font-size:0.8rem;font-weight:600;color:#333;margin-bottom:8px;">Pilih Metode Login</div>
                                        <div style="background:#f5f5f5;border-radius:8px;padding:8px 12px;display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/></svg>
                                            <span style="font-size:0.7rem;color:#333;">Google</span>
                                        </div>
                                        <div style="background:#f5f5f5;border-radius:8px;padding:8px 12px;display:flex;align-items:center;gap:8px;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="#10b981"><circle cx="12" cy="12" r="10" fill="none" stroke="#10b981" stroke-width="2"/></svg>
                                            <span style="font-size:0.7rem;color:#333;">Gratis 1 Jam</span>
                                        </div>
                                    </div>
                                    <div style="position:relative;z-index:1;font-size:0.6rem;color:#999;margin-top:4px;" id="previewFooter">© 2026 <?= sanitizeInput($portalFooterText) ?></div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($currentTab === 'mikrotik'): ?>
            <!-- Router Status (Full Width) -->
            <div class="card mt-6">
                <div class="card-header">
                    <h3 class="card-title">Status Router MikroTik</h3>
                    <?php if ($connected): ?>
                    <a href="settings.php" class="btn btn-sm">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        Refresh
                    </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($connected && !empty($routerInfo)): ?>
                    <dl class="mikrotik-info" style="display: grid; grid-template-columns: max-content 1fr max-content 1fr; gap: 16px 32px;">
                        <dt>Identity</dt>
                        <dd><?= sanitizeInput($routerInfo['identity'] ?? '-') ?></dd>

                        <dt>CPU Load</dt>
                        <dd>
                            <?php $cpuLoad = (int) ($routerInfo['cpu-load'] ?? 0); ?>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;height:6px;background:rgba(255,255,255,0.06);border-radius:3px;overflow:hidden;">
                                    <div style="height:100%;width:<?= $cpuLoad ?>%;background:<?= $cpuLoad > 80 ? '#f43f5e' : ($cpuLoad > 50 ? '#f59e0b' : '#10b981') ?>;border-radius:3px;transition:width 0.3s;"></div>
                                </div>
                                <span style="font-size:0.8rem;color:var(--text-secondary);"><?= $cpuLoad ?>%</span>
                            </div>
                        </dd>

                        <dt>Board</dt>
                        <dd><?= sanitizeInput($routerInfo['board-name'] ?? '-') ?></dd>

                        <dt>Memory</dt>
                        <dd>
                            <?php
                            $totalMem = (int) ($routerInfo['total-memory'] ?? 0);
                            $freeMem = (int) ($routerInfo['free-memory'] ?? 0);
                            $usedMem = $totalMem - $freeMem;
                            $memPct = $totalMem > 0 ? round(($usedMem / $totalMem) * 100) : 0;
                            ?>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;height:6px;background:rgba(255,255,255,0.06);border-radius:3px;overflow:hidden;">
                                    <div style="height:100%;width:<?= $memPct ?>%;background:<?= $memPct > 80 ? '#f43f5e' : '#06b6d4' ?>;border-radius:3px;"></div>
                                </div>
                                <span style="font-size:0.8rem;color:var(--text-secondary);"><?= formatBytes($usedMem) ?> / <?= formatBytes($totalMem) ?></span>
                            </div>
                        </dd>

                        <dt>Uptime</dt>
                        <dd class="text-green"><?= sanitizeInput($routerInfo['uptime'] ?? '-') ?></dd>

                        <dt>HDD</dt>
                        <dd>
                            <?php
                            $totalHdd = (int) ($routerInfo['total-hdd-space'] ?? 0);
                            $freeHdd = (int) ($routerInfo['free-hdd-space'] ?? 0);
                            ?>
                            <?= formatBytes($totalHdd - $freeHdd) ?> / <?= formatBytes($totalHdd) ?>
                        </dd>
                    </dl>
                    <?php else: ?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                        <p>Belum terhubung ke router MikroTik</p>
                        <p style="font-size:0.8rem;">Masukkan kredensial di pengaturan MikroTik untuk terhubung.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Active Hotspot Users -->
            <div class="card mt-6">
                <div class="card-header">
                    <h3 class="card-title">Active Hotspot Users (<?= count($hotspotUsers) ?>)</h3>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>IP Address</th>
                                <th>MAC Address</th>
                                <th>Uptime</th>
                                <th>Server</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($hotspotUsers)): ?>
                            <tr><td colspan="5" class="text-center text-muted" style="padding:32px;">
                                <?= $connected ? 'Tidak ada user hotspot aktif' : 'Tidak terhubung ke router' ?>
                            </td></tr>
                            <?php else: ?>
                            <?php foreach ($hotspotUsers as $hUser): ?>
                            <tr>
                                <td><strong><?= sanitizeInput($hUser['user'] ?? '-') ?></strong></td>
                                <td class="text-muted"><?= sanitizeInput($hUser['address'] ?? '-') ?></td>
                                <td class="text-muted" style="font-size:0.8rem;"><?= sanitizeInput($hUser['mac-address'] ?? '-') ?></td>
                                <td><?= sanitizeInput($hUser['uptime'] ?? '-') ?></td>
                                <td class="text-muted"><?= sanitizeInput($hUser['server'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($currentTab === 'database'): ?>
            <!-- Database Backup & Restore -->
            <div class="card mb-6" id="database">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px;"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"/></svg>
                        Backup & Restore Database
                    </h3>
                </div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                        <!-- Left: Backup -->
                        <div>
                            <h4 style="font-size:1.1rem;font-weight:600;margin-bottom:12px;">Cadangkan Database (Backup)</h4>
                            <p style="font-size:0.875rem;color:var(--text-muted);line-height:1.6;margin-bottom:20px;">Unduh seluruh data database saat ini (akun admin, data pengguna, log hotspot, dan iklan) ke komputer Anda.</p>
                            <a href="settings.php?action=backup&csrf_token=<?= $csrfToken ?>" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:8px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Unduh Backup Database (.sql)
                            </a>
                        </div>
                        
                        <!-- Right: Restore -->
                        <div style="border-left:1px solid var(--glass-border);padding-left:24px;">
                            <h4 style="font-size:1.1rem;font-weight:600;margin-bottom:12px;">Pulihkan Database (Restore)</h4>
                            <p style="font-size:0.875rem;color:var(--text-muted);line-height:1.6;margin-bottom:20px;">Ganti data saat ini dengan mengunggah file hasil cadangan database (.sql) sebelumnya.</p>
                            <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('Peringatan: Pemulihan database akan menimpa seluruh data yang ada saat ini. Apakah Anda yakin?')">
                                <input type="hidden" name="section" value="database_restore">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <div class="form-group" style="margin-bottom:16px;">
                                    <input type="file" name="backup_file" accept=".sql" class="form-input" required style="padding:8px;">
                                </div>
                                <button type="submit" class="btn btn-primary" style="background:#f43f5e;border-color:#f43f5e;display:inline-flex;align-items:center;gap:8px;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    Mulai Restore Database
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<script src="assets/js/dashboard.js?v=1.2"></script>
</body>
</html>
