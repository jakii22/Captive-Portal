<?php
/**
 * Dashboard - Active Sessions
 * Shows currently connected users from radacct (acctstoptime IS NULL)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$admin = getCurrentAdmin();
$flash = getFlash();

try {
    $db = Database::getInstance();

    // Fetch active sessions (no stop time = still connected)
    $stmt = $db->query("
        SELECT ra.radacctid,
               ra.username,
               ra.nasipaddress,
               ra.framedipaddress,
               ra.callingstationid AS mac_address,
               ra.acctstarttime,
               EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - ra.acctstarttime))::int AS duration_seconds,
               ra.acctinputoctets AS upload_bytes,
               ra.acctoutputoctets AS download_bytes,
               u.name AS user_name,
               u.login_method
        FROM radacct ra
        LEFT JOIN users u ON u.username_identity = ra.username
        WHERE ra.acctstoptime IS NULL
        ORDER BY ra.acctstarttime DESC
    ");
    $sessions = $stmt->fetchAll();

    $totalSessions = count($sessions);

} catch (PDOException $e) {
    error_log('Active Sessions Error: ' . $e->getMessage());
    $sessions = [];
    $totalSessions = 0;
}

$currentPage = 'active-sessions';
$pageTitle = 'Pengguna Aktif';
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
            <div class="main-header-actions">
                <span class="badge badge-green" style="font-size: 0.8rem; padding: 6px 14px;">
                    <?= $totalSessions ?> pengguna aktif
                </span>
            </div>
        </header>

        <main class="main-body">
            <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= sanitizeInput($flash['message']) ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Sesi Aktif Saat Ini</h3>
                </div>
                <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>
                    </svg>
                    <p>Tidak ada pengguna yang sedang terhubung saat ini.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Nama</th>
                                <th>Metode</th>
                                <th>IP Address</th>
                                <th>MAC Address</th>
                                <th>Durasi</th>
                                <th>Upload</th>
                                <th>Download</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $s): ?>
                            <tr>
                                <td>
                                    <strong><?= sanitizeInput($s['username']) ?></strong>
                                </td>
                                <td><?= sanitizeInput($s['user_name'] ?? '-') ?></td>
                                <td>
                                    <?php
                                    $method = $s['login_method'] ?? 'unknown';
                                    $badgeClass = match($method) {
                                        'google' => 'badge-violet',
                                        'facebook' => 'badge-cyan',
                                        'free' => 'badge-green',
                                        default => 'badge-amber',
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= ucfirst($method) ?></span>
                                </td>
                                <td class="text-nowrap"><?= sanitizeInput($s['framedipaddress'] ?? '-') ?></td>
                                <td class="text-nowrap" style="font-family: monospace; font-size: 0.8rem;">
                                    <?= sanitizeInput($s['mac_address'] ?? '-') ?>
                                </td>
                                <td class="text-nowrap">
                                    <span class="badge badge-green"><?= formatDuration((int)$s['duration_seconds']) ?></span>
                                </td>
                                <td class="text-nowrap"><?= formatBytes((int)$s['upload_bytes']) ?></td>
                                <td class="text-nowrap"><?= formatBytes((int)$s['download_bytes']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script src="assets/js/dashboard.js?v=1.2"></script>
</body>
</html>
