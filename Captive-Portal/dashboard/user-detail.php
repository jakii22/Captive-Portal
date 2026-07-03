<?php
/**
 * Dashboard - User Detail
 * Full profile and session history from radacct
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$admin = getCurrentAdmin();

$userId = (int) ($_GET['id'] ?? 0);
if ($userId <= 0) {
    header('Location: users.php');
    exit;
}

try {
    $db = Database::getInstance();

    // Fetch user
    $stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        setFlash('error', 'Pengguna tidak ditemukan.');
        header('Location: users.php');
        exit;
    }

    // Fetch session history from radacct
    $stmt = $db->prepare("
        SELECT radacctid, acctsessionid, acctstarttime, acctstoptime,
               acctsessiontime, acctinputoctets, acctoutputoctets,
               framedipaddress, callingstationid, nasipaddress,
               acctterminatecause
        FROM radacct
        WHERE username = :username
        ORDER BY acctstarttime DESC
        LIMIT 50
    ");
    $stmt->execute([':username' => $user['username_identity']]);
    $sessions = $stmt->fetchAll();

    // Aggregate stats
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_sessions,
               COALESCE(SUM(acctinputoctets), 0) as total_upload,
               COALESCE(SUM(acctoutputoctets), 0) as total_download,
               COALESCE(SUM(acctsessiontime), 0) as total_time
        FROM radacct WHERE username = :username
    ");
    $stmt->execute([':username' => $user['username_identity']]);
    $stats = $stmt->fetch();

} catch (PDOException $e) {
    error_log('User Detail Error: ' . $e->getMessage());
    setFlash('error', 'Gagal memuat data pengguna.');
    header('Location: users.php');
    exit;
}

$currentPage = 'users';
$pageTitle = 'Detail Pengguna';
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
                <a href="users.php" class="btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    Kembali
                </a>
            </div>
        </header>

        <main class="main-body">
            <!-- User Profile Card -->
            <div class="card mb-6">
                <div class="card-body">
                    <div class="flex items-center gap-4" style="flex-wrap:wrap;">
                        <?php if ($user['photo_url']): ?>
                        <img src="<?= sanitizeInput($user['photo_url']) ?>" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid var(--glass-border-strong);">
                        <?php else: ?>
                        <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#06b6d4);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;color:white;flex-shrink:0;">
                            <?= strtoupper(substr($user['name'] ?? $user['username_identity'], 0, 1)) ?>
                        </div>
                        <?php endif; ?>
                        <div style="flex:1;">
                            <h2 style="font-size:1.25rem;font-weight:700;margin-bottom:2px;">
                                <?= sanitizeInput($user['name'] ?? '-') ?>
                            </h2>
                            <p class="text-muted"><?= sanitizeInput($user['username_identity']) ?></p>
                            <div class="flex gap-2 items-center" style="margin-top:8px;">
                                <?php
                                $mb = match($user['login_method']) {
                                    'google'   => 'badge-cyan',
                                    'facebook' => 'badge-violet',
                                    'free'     => 'badge-green',
                                    default    => 'badge-amber',
                                };
                                ?>
                                <span class="badge <?= $mb ?>"><?= ucfirst($user['login_method']) ?></span>
                                <span class="text-muted" style="font-size:0.75rem;">
                                    Bergabung <?= date('d M Y, H:i', strtotime($user['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-label">Total Sesi</span>
                        <div class="stat-card-icon stat-card-icon--violet">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                    </div>
                    <div class="stat-card-value"><?= number_format((int) $stats['total_sessions']) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-label">Total Upload</span>
                        <div class="stat-card-icon stat-card-icon--cyan">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/></svg>
                        </div>
                    </div>
                    <div class="stat-card-value"><?= formatBytes((int) $stats['total_upload']) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-label">Total Download</span>
                        <div class="stat-card-icon stat-card-icon--amber">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="7 15 3 19 7 23"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                        </div>
                    </div>
                    <div class="stat-card-value"><?= formatBytes((int) $stats['total_download']) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-label">Total Durasi</span>
                        <div class="stat-card-icon stat-card-icon--green">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                    </div>
                    <div class="stat-card-value" style="font-size:1.25rem;"><?= formatDuration((int) $stats['total_time']) ?></div>
                </div>
            </div>

            <!-- Session History -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Riwayat Sesi (<?= count($sessions) ?>)</h3>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Mulai</th>
                                <th>Selesai</th>
                                <th>Durasi</th>
                                <th>Upload</th>
                                <th>Download</th>
                                <th>IP</th>
                                <th>MAC</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sessions)): ?>
                            <tr><td colspan="8" class="text-center text-muted" style="padding:32px;">Belum ada riwayat sesi</td></tr>
                            <?php else: ?>
                            <?php foreach ($sessions as $sess): ?>
                            <tr>
                                <td class="text-nowrap"><?= $sess['acctstarttime'] ? date('d/m/Y H:i', strtotime($sess['acctstarttime'])) : '-' ?></td>
                                <td class="text-nowrap"><?= $sess['acctstoptime'] ? date('d/m/Y H:i', strtotime($sess['acctstoptime'])) : '<span class="badge badge-green">Aktif</span>' ?></td>
                                <td class="text-nowrap"><?= formatDuration((int) ($sess['acctsessiontime'] ?? 0)) ?></td>
                                <td class="text-nowrap"><?= formatBytes((int) ($sess['acctinputoctets'] ?? 0)) ?></td>
                                <td class="text-nowrap"><?= formatBytes((int) ($sess['acctoutputoctets'] ?? 0)) ?></td>
                                <td class="text-muted"><?= sanitizeInput($sess['framedipaddress'] ?? '-') ?></td>
                                <td class="text-muted" style="font-size:0.75rem;"><?= sanitizeInput($sess['callingstationid'] ?? '-') ?></td>
                                <td>
                                    <?php if (empty($sess['acctstoptime'])): ?>
                                        <span class="badge badge-green">Online</span>
                                    <?php else: ?>
                                        <span class="badge badge-amber"><?= sanitizeInput($sess['acctterminatecause'] ?? 'Unknown') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="assets/js/dashboard.js?v=1.1"></script>
</body>
</html>
