<?php
/**
 * Sidebar Partial - Included in all dashboard pages
 * Expects: $currentPage, $admin
 */
$currentPage = $currentPage ?? '';
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

        <?php if (isFullAdmin()): ?>
        <div class="sidebar-nav-label" style="margin-top:8px;">Konfigurasi</div>

        <a href="google-api.php" class="sidebar-link <?= $currentPage === 'google-api' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg>
            Google & FB API
        </a>

        <a href="mikrotik.php" class="sidebar-link <?= $currentPage === 'mikrotik' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
            MikroTik API
        </a>

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
