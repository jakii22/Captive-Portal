<?php
/**
 * Sidebar Partial - Included in all dashboard pages
 * Expects: $currentPage, $admin
 */
$currentPage = $currentPage ?? '';
$currentTab = $currentTab ?? '';
$admin = $admin ?? getCurrentAdmin();
?>
<aside class="sidebar" id="sidebar">
    <a href="index.php" class="sidebar-brand" style="text-decoration:none; color:inherit;">
        <div class="sidebar-brand-logo">
            <svg viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <clipPath id="circleClip">
                        <circle cx="250" cy="250" r="250" />
                    </clipPath>
                </defs>
                <g clip-path="url(#circleClip)">
                    <rect width="500" height="500" fill="#1a8dff" />
                    <polygon points="140,300 -100,400 -100,600 300,600" fill="#94f394" />
                    <line x1="-100" y1="403" x2="600" y2="103" stroke="#ffffff" stroke-width="45" />
                    <line x1="15" y1="-100" x2="233" y2="600" stroke="#ffffff" stroke-width="45" />
                </g>
            </svg>
        </div>
        <div>
            <div class="sidebar-brand-text">Okenet Hotspot</div>
            <div class="sidebar-brand-sub">Captive Portal</div>
        </div>
    </a>

    <nav class="sidebar-nav">
        <div class="sidebar-nav-label">Menu Utama</div>

        <a href="index.php" class="sidebar-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>

        <a href="accounts.php" class="sidebar-link <?= $currentPage === 'accounts' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Accounts
        </a>

        <a href="users.php" class="sidebar-link <?= $currentPage === 'users' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Users
        </a>

        <a href="logs.php" class="sidebar-link <?= $currentPage === 'logs' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            Logs
        </a>

        <a href="active-sessions.php" class="sidebar-link <?= $currentPage === 'active-sessions' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
            Pengguna Aktif
        </a>

        <a href="traffic.php" class="sidebar-link <?= $currentPage === 'traffic' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Traffic Monitor
        </a>

        <a href="analytics.php" class="sidebar-link <?= $currentPage === 'analytics' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
            Analytics
        </a>

        <?php if (isFullAdmin()): ?>
        <div class="sidebar-nav-label" style="margin-top:8px;">Konfigurasi</div>

        <div class="sidebar-dropdown <?= in_array($currentPage, ['settings', 'settings-general', 'settings-mikrotik', 'settings-google', 'settings-facebook', 'settings-appearance']) ? 'open' : '' ?>">
            <button class="sidebar-link sidebar-dropdown-toggle" type="button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Settings
                <svg class="sidebar-dropdown-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="sidebar-dropdown-menu">
                <a href="settings.php?tab=general" class="sidebar-sublink <?= ($currentPage === 'settings' && $currentTab === 'general') ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4"/></svg>
                    Pengaturan Umum
                </a>
                <a href="settings.php?tab=mikrotik" class="sidebar-sublink <?= ($currentPage === 'settings' && $currentTab === 'mikrotik') ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                    MikroTik API
                </a>
                <a href="settings.php?tab=google" class="sidebar-sublink <?= ($currentPage === 'settings' && $currentTab === 'google') ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" style="stroke:none;"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="currentColor" opacity="0.7"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="currentColor" opacity="0.5"/></svg>
                    Google OAuth
                </a>
                <a href="settings.php?tab=facebook" class="sidebar-sublink <?= ($currentPage === 'settings' && $currentTab === 'facebook') ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="currentColor" opacity="0.7" style="stroke:none;"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    Facebook OAuth
                </a>
                <a href="settings.php?tab=appearance" class="sidebar-sublink <?= ($currentPage === 'settings' && $currentTab === 'appearance') ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    Tampilan Portal
                </a>
            </div>
        </div>

        <a href="advertisements.php" class="sidebar-link <?= $currentPage === 'advertisements' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            Advertisements
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar">
                <?= strtoupper(substr($admin['username'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= sanitizeInput($admin['username'] ?? 'Admin') ?></div>
                <div class="sidebar-user-role"><?= sanitizeInput($admin['role'] ?? 'read') ?></div>
            </div>
            <button id="themeToggle" title="Toggle Theme" style="background:none;border:none;cursor:pointer;color:var(--text-muted);padding:4px;display:flex;align-items:center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </button>
            <a href="logout.php" title="Logout" style="color:var(--text-muted);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </a>
        </div>
    </div>
</aside>
