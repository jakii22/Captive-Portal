<?php
/**
 * Success Page - Displayed after successful hotspot login
 * Shows advertisements and session information
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();

$loginSuccess  = $_SESSION['login_success'] ?? false;
$loginUsername  = $_SESSION['login_username'] ?? '';
$loginName     = $_SESSION['login_name'] ?? 'User';
$loginMethod   = $_SESSION['login_method'] ?? '';
$loginTime     = $_SESSION['login_time'] ?? time();
$sessionLimit  = $_SESSION['session_limit'] ?? 0;

// Get active advertisements
$ads = getActiveAds();
$siteName = getSetting('site_name', APP_NAME);

// Calculate remaining time for free sessions
$remainingSeconds = 0;
if ($loginMethod === 'free' && $sessionLimit > 0) {
    $elapsed = time() - $loginTime;
    $remainingSeconds = max(0, $sessionLimit - $elapsed);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="description" content="Berhasil terhubung ke <?= sanitizeInput($siteName) ?>">
    <meta name="robots" content="noindex, nofollow">
    <title>Berhasil Terhubung - <?= sanitizeInput($siteName) ?></title>
    <link rel="stylesheet" href="assets/css/portal.css?v=1.1">
</head>
<body>
    <!-- Theme Toggle -->
    <button id="themeToggle" class="portal-theme-toggle" aria-label="Toggle Theme">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
    </button>
    <!-- Animated Background -->
    <div class="portal-bg"></div>
    <div class="particle-grid"></div>

    <div class="portal-container">
        <div class="login-card success-container" style="max-width: 480px;">
            <!-- Success Icon -->
            <div class="success-icon">
                <svg viewBox="0 0 24 24">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </div>

            <h1 class="success-title">Terhubung!</h1>
            <p class="success-subtitle">
                Selamat datang, <strong><?= sanitizeInput($loginName) ?></strong>.<br>
                Anda sekarang terhubung ke internet.
            </p>

            <?php if ($loginMethod === 'free' && $remainingSeconds > 0): ?>
            <!-- Session Timer for Free Users -->
            <div class="session-timer" id="sessionTimer" data-remaining="<?= $remainingSeconds ?>">
                <div class="session-timer-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12,6 12,12 16,14"/>
                    </svg>
                </div>
                <div>
                    <div class="session-timer-text">Sisa waktu gratis</div>
                    <div class="session-timer-value">01:00:00</div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($ads)): ?>
            <!-- Advertisement Carousel -->
            <div class="ad-carousel">
                <div class="ad-carousel-inner">
                    <?php foreach ($ads as $ad): ?>
                    <div class="ad-slide">
                        <?php if (!empty($ad['link_url'])): ?>
                        <a href="<?= sanitizeInput($ad['link_url']) ?>" target="_blank" rel="noopener" class="ad-slide-link">
                            <img src="<?= BASE_URL ?>/uploads/ads/<?= sanitizeInput($ad['image_path']) ?>"
                                 alt="<?= sanitizeInput($ad['title']) ?>"
                                 loading="lazy">
                        </a>
                        <?php else: ?>
                        <img src="<?= BASE_URL ?>/uploads/ads/<?= sanitizeInput($ad['image_path']) ?>"
                             alt="<?= sanitizeInput($ad['title']) ?>"
                             loading="lazy">
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($ads) > 1): ?>
                <div class="ad-dots"></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Connection Info -->
            <div style="margin-top: 16px; font-size: 0.8125rem; color: var(--text-muted);">
                <p>Login method: <?= ucfirst(sanitizeInput($loginMethod)) ?></p>
                <p style="margin-top: 4px;">Nikmati akses internet Anda!</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="portal-footer">
            <p>© <?= date('Y') ?> <?= sanitizeInput($siteName) ?></p>
        </div>
    </div>

    <script src="assets/js/portal.js?v=1.1"></script>
</body>
</html>

