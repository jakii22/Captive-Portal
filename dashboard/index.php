<?php
/**
 * Dashboard - Overview / Home
 * Statistics, charts, and summary data
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$admin = getCurrentAdmin();
$flash = getFlash();

try {
    $db = Database::getInstance();

    // Total Users
    $stmt = $db->query('SELECT COUNT(*) as total FROM users');
    $totalUsers = $stmt->fetch()['total'] ?? 0;

    // Users Today
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE created_at >= CURRENT_DATE");
    $usersToday = $stmt->fetch()['total'] ?? 0;

    // Active Sessions (no stop time yet)
    $stmt = $db->query("SELECT COUNT(*) as total FROM radacct WHERE acctstoptime IS NULL");
    $activeSessions = $stmt->fetch()['total'] ?? 0;

    // Total Data Transfer (today)
    $stmt = $db->query("SELECT COALESCE(SUM(acctinputoctets), 0) as upload, COALESCE(SUM(acctoutputoctets), 0) as download FROM radacct WHERE acctstarttime >= CURRENT_DATE");
    $dataToday = $stmt->fetch();
    $totalUpload = (int) ($dataToday['upload'] ?? 0);
    $totalDownload = (int) ($dataToday['download'] ?? 0);

    // Users per day (last 7 days)
    $stmt = $db->query("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM users
        WHERE created_at >= CURRENT_DATE - INTERVAL '6 days'
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $usersPerDay = $stmt->fetchAll();

    // Users by login method
    $stmt = $db->query("SELECT login_method, COUNT(*) as count FROM users GROUP BY login_method ORDER BY count DESC");
    $usersByMethod = $stmt->fetchAll();

    // Top 5 users by data usage
    $stmt = $db->query("
        SELECT ra.username,
               u.name,
               COALESCE(SUM(ra.acctinputoctets + ra.acctoutputoctets), 0) as total_data,
               COUNT(ra.radacctid) as sessions
        FROM radacct ra
        LEFT JOIN users u ON u.username_identity = ra.username
        GROUP BY ra.username, u.name
        ORDER BY total_data DESC
        LIMIT 5
    ");
    $topUsers = $stmt->fetchAll();

    // Recent logins (last 10)
    $stmt = $db->query("
        SELECT u.username_identity, u.name, u.login_method, u.created_at
        FROM users u
        ORDER BY u.created_at DESC
        LIMIT 10
    ");
    $recentUsers = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Dashboard Error: ' . $e->getMessage());
    $totalUsers = $usersToday = $activeSessions = $totalUpload = $totalDownload = 0;
    $usersPerDay = $usersByMethod = $topUsers = $recentUsers = [];
}

// Prepare chart data
$chartLabels = [];
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = date('d M', strtotime($date));
    $found = false;
    foreach ($usersPerDay as $row) {
        if ($row['date'] === $date) {
            $chartData[] = (int) $row['count'];
            $found = true;
            break;
        }
    }
    if (!$found) $chartData[] = 0;
}

$currentPage = 'dashboard';
$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $pageTitle ?> - Admin Panel</title>
    <link rel="stylesheet" href="assets/css/dashboard.css?v=1.1">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="dashboard-layout">
    <!-- Sidebar Overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="main-header">
            <div class="flex items-center gap-4">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <h2 class="main-header-title">Dashboard Overview</h2>
            </div>
            <div class="main-header-actions">
                <span class="text-muted" style="font-size:0.8rem;">
                    <?= date('l, d F Y') ?>
                </span>
            </div>
        </header>

        <!-- Body -->
        <main class="main-body">
            <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= sanitizeInput($flash['message']) ?>
            </div>
            <?php endif; ?>

            <!-- Stat Cards -->
            <div class="stats-grid">
                <a href="users.php" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-label">Total Pengguna</span>
                        <div class="stat-card-icon stat-card-icon--violet">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                    </div>
                    <div class="stat-card-value"><?= number_format($totalUsers) ?></div>
                    <div class="stat-card-change positive">+<?= $usersToday ?> hari ini</div>
                </div>
                </a>

                <a href="active-sessions.php" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-label">Sesi Aktif</span>
                        <div class="stat-card-icon stat-card-icon--green">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
                        </div>
                    </div>
                    <div class="stat-card-value"><?= number_format($activeSessions) ?></div>
                    <div class="stat-card-change">Sedang terhubung</div>
                </div>
                </a>

                <a href="traffic.php" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-label">Upload Hari Ini</span>
                        <div class="stat-card-icon stat-card-icon--cyan">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/></svg>
                        </div>
                    </div>
                    <div class="stat-card-value"><?= formatBytes($totalUpload) ?></div>
                    <div class="stat-card-change">Total upload</div>
                </div>
                </a>

                <a href="traffic.php" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-label">Download Hari Ini</span>
                        <div class="stat-card-icon stat-card-icon--amber">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="7 15 3 19 7 23"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                        </div>
                    </div>
                    <div class="stat-card-value"><?= formatBytes($totalDownload) ?></div>
                    <div class="stat-card-change">Total download</div>
                </div>
                </a>
            </div>

            <!-- Charts Row -->
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px; margin-bottom:28px;">
                <!-- User Registration Chart -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Pendaftaran 7 Hari Terakhir</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="usersChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Login Method Distribution -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Metode Login</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="methodChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Row -->
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <!-- Top Users -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Top 5 Pengguna (Data Usage)</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Username</th>
                                    <th>Total Data</th>
                                    <th>Sesi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topUsers)): ?>
                                <tr><td colspan="4" class="text-center text-muted" style="padding:24px;">Belum ada data</td></tr>
                                <?php else: ?>
                                <?php foreach ($topUsers as $i => $user): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td>
                                        <strong><?= sanitizeInput($user['name'] ?? $user['username']) ?></strong>
                                        <br><span class="text-muted" style="font-size:0.75rem;"><?= sanitizeInput($user['username']) ?></span>
                                    </td>
                                    <td class="text-nowrap"><?= formatBytes((int) $user['total_data']) ?></td>
                                    <td><?= $user['sessions'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Registrations -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Pendaftaran Terbaru</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Metode</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentUsers)): ?>
                                <tr><td colspan="3" class="text-center text-muted" style="padding:24px;">Belum ada data</td></tr>
                                <?php else: ?>
                                <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?= sanitizeInput($user['name'] ?? '-') ?></strong>
                                        <br><span class="text-muted" style="font-size:0.75rem;"><?= sanitizeInput($user['username_identity']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $methodBadge = match($user['login_method']) {
                                            'google'   => 'badge-cyan',
                                            'facebook' => 'badge-violet',
                                            'free'     => 'badge-green',
                                            default    => 'badge-amber',
                                        };
                                        ?>
                                        <span class="badge <?= $methodBadge ?>"><?= ucfirst($user['login_method']) ?></span>
                                    </td>
                                    <td class="text-nowrap text-muted"><?= date('d/m H:i', strtotime($user['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="assets/js/dashboard.js?v=1.1"></script>
<script>
// Users Chart (Bar)
const usersCtx = document.getElementById('usersChart')?.getContext('2d');
if (usersCtx) {
    new Chart(usersCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Pengguna Baru',
                data: <?= json_encode($chartData) ?>,
                backgroundColor: 'rgba(124, 58, 237, 0.4)',
                borderColor: '#7c3aed',
                borderWidth: 1,
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
            },
            scales: {
                x: {
                    grid: { color: 'rgba(128,128,128,0.15)' },
                    ticks: { color: '#888', font: { size: 11 } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(128,128,128,0.15)' },
                    ticks: { color: '#888', font: { size: 11 }, stepSize: 1 }
                }
            }
        }
    });
}

// Method Distribution Chart (Doughnut)
const methodCtx = document.getElementById('methodChart')?.getContext('2d');
if (methodCtx) {
    const methodLabels = <?= json_encode(array_map(fn($m) => ucfirst($m['login_method']), $usersByMethod)) ?>;
    const methodData = <?= json_encode(array_map(fn($m) => (int) $m['count'], $usersByMethod)) ?>;

    new Chart(methodCtx, {
        type: 'doughnut',
        data: {
            labels: methodLabels.length > 0 ? methodLabels : ['No Data'],
            datasets: [{
                data: methodData.length > 0 ? methodData : [1],
                backgroundColor: methodData.length > 0
                    ? ['#06b6d4', '#7c3aed', '#10b981', '#f59e0b']
                    : ['rgba(128,128,128,0.1)'],
                borderColor: 'transparent',
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#888', padding: 12, font: { size: 11 } }
                }
            }
        }
    });
}
</script>
</body>
</html>
