<?php
/**
 * Dashboard - Traffic Monitor
 * Shows upload/download statistics and charts
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$admin = getCurrentAdmin();
$flash = getFlash();

try {
    $db = Database::getInstance();

    // Today's total upload & download
    $stmt = $db->query("
        SELECT COALESCE(SUM(acctinputoctets), 0) as upload,
               COALESCE(SUM(acctoutputoctets), 0) as download
        FROM radacct
        WHERE acctstarttime >= CURRENT_DATE
    ");
    $today = $stmt->fetch();
    $todayUpload = (int)($today['upload'] ?? 0);
    $todayDownload = (int)($today['download'] ?? 0);
    $todayTotal = $todayUpload + $todayDownload;

    // Traffic per day (last 7 days)
    $stmt = $db->query("
        SELECT DATE(acctstarttime) as date,
               COALESCE(SUM(acctinputoctets), 0) as upload,
               COALESCE(SUM(acctoutputoctets), 0) as download
        FROM radacct
        WHERE acctstarttime >= CURRENT_DATE - INTERVAL '6 days'
        GROUP BY DATE(acctstarttime)
        ORDER BY date
    ");
    $trafficPerDay = $stmt->fetchAll();

    // Top 10 users by data usage today
    $stmt = $db->query("
        SELECT ra.username,
               u.name,
               u.login_method,
               COALESCE(SUM(ra.acctinputoctets), 0) as upload,
               COALESCE(SUM(ra.acctoutputoctets), 0) as download,
               COALESCE(SUM(ra.acctinputoctets + ra.acctoutputoctets), 0) as total
        FROM radacct ra
        LEFT JOIN users u ON u.username_identity = ra.username
        WHERE ra.acctstarttime >= CURRENT_DATE
        GROUP BY ra.username, u.name, u.login_method
        ORDER BY total DESC
        LIMIT 10
    ");
    $topUsersToday = $stmt->fetchAll();

    // All-time totals
    $stmt = $db->query("
        SELECT COALESCE(SUM(acctinputoctets), 0) as upload,
               COALESCE(SUM(acctoutputoctets), 0) as download
        FROM radacct
    ");
    $allTime = $stmt->fetch();
    $allTimeUpload = (int)($allTime['upload'] ?? 0);
    $allTimeDownload = (int)($allTime['download'] ?? 0);

} catch (PDOException $e) {
    error_log('Traffic Monitor Error: ' . $e->getMessage());
    $todayUpload = $todayDownload = $todayTotal = 0;
    $allTimeUpload = $allTimeDownload = 0;
    $trafficPerDay = [];
    $topUsersToday = [];
}

// Prepare chart data
$chartLabels = [];
$chartUpload = [];
$chartDownload = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $chartLabels[] = date('d M', strtotime($date));
    $found = false;
    foreach ($trafficPerDay as $row) {
        if ($row['date'] === $date) {
            // Convert to MB for readability
            $chartUpload[] = round((int)$row['upload'] / (1024 * 1024), 2);
            $chartDownload[] = round((int)$row['download'] / (1024 * 1024), 2);
            $found = true;
            break;
        }
    }
    if (!$found) {
        $chartUpload[] = 0;
        $chartDownload[] = 0;
    }
}

$currentPage = 'traffic';
$pageTitle = 'Traffic Monitor';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $pageTitle ?> - Admin Panel</title>
    <link rel="stylesheet" href="assets/css/dashboard.css?v=1.2">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
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
                <span class="text-muted" style="font-size:0.8rem;">
                    <?= date('l, d F Y') ?>
                </span>
            </div>
        </header>

        <main class="main-body">
            <!-- Summary Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-label">Upload Hari Ini</span>
                        <div class="stat-card-icon stat-card-icon--cyan">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/></svg>
                        </div>
                    </div>
                    <div class="stat-card-value"><?= formatBytes($todayUpload) ?></div>
                    <div class="stat-card-change">Total upload hari ini</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-label">Download Hari Ini</span>
                        <div class="stat-card-icon stat-card-icon--amber">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="7 15 3 19 7 23"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                        </div>
                    </div>
                    <div class="stat-card-value"><?= formatBytes($todayDownload) ?></div>
                    <div class="stat-card-change">Total download hari ini</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-label">Total Transfer Hari Ini</span>
                        <div class="stat-card-icon stat-card-icon--green">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </div>
                    </div>
                    <div class="stat-card-value"><?= formatBytes($todayTotal) ?></div>
                    <div class="stat-card-change">Upload + Download</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-label">Total Semua Waktu</span>
                        <div class="stat-card-icon stat-card-icon--violet">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
                        </div>
                    </div>
                    <div class="stat-card-value"><?= formatBytes($allTimeUpload + $allTimeDownload) ?></div>
                    <div class="stat-card-change">↑ <?= formatBytes($allTimeUpload) ?> · ↓ <?= formatBytes($allTimeDownload) ?></div>
                </div>
            </div>

            <!-- Traffic Chart -->
            <div class="card mb-6">
                <div class="card-header">
                    <h3 class="card-title">Trafik 7 Hari Terakhir (MB)</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="trafficChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Users Today -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Top 10 Pengguna Hari Ini (Penggunaan Data)</h3>
                </div>
                <?php if (empty($topUsersToday)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                    <p>Belum ada data penggunaan hari ini.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Username</th>
                                <th>Nama</th>
                                <th>Metode</th>
                                <th>Upload</th>
                                <th>Download</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topUsersToday as $i => $user): ?>
                            <tr>
                                <td><strong><?= $i + 1 ?></strong></td>
                                <td><strong><?= sanitizeInput($user['username']) ?></strong></td>
                                <td><?= sanitizeInput($user['name'] ?? '-') ?></td>
                                <td>
                                    <?php
                                    $method = $user['login_method'] ?? 'unknown';
                                    $badgeClass = match($method) {
                                        'google' => 'badge-violet',
                                        'facebook' => 'badge-cyan',
                                        'free' => 'badge-green',
                                        default => 'badge-amber',
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= ucfirst($method) ?></span>
                                </td>
                                <td class="text-nowrap"><?= formatBytes((int)$user['upload']) ?></td>
                                <td class="text-nowrap"><?= formatBytes((int)$user['download']) ?></td>
                                <td class="text-nowrap"><strong><?= formatBytes((int)$user['total']) ?></strong></td>
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
<script>
// Traffic Chart
const trafficCtx = document.getElementById('trafficChart');
if (trafficCtx) {
    new Chart(trafficCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'Upload (MB)',
                    data: <?= json_encode($chartUpload) ?>,
                    backgroundColor: 'rgba(14, 165, 233, 0.7)',
                    borderColor: 'rgba(14, 165, 233, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                },
                {
                    label: 'Download (MB)',
                    data: <?= json_encode($chartDownload) ?>,
                    backgroundColor: 'rgba(245, 158, 11, 0.7)',
                    borderColor: 'rgba(245, 158, 11, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() || '#475569',
                        font: { family: "'Inter', sans-serif", size: 12 },
                        usePointStyle: true,
                        pointStyle: 'rect',
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim() || '#64748b',
                        font: { family: "'Inter', sans-serif", size: 11 }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.06)' },
                    ticks: {
                        color: getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim() || '#64748b',
                        font: { family: "'Inter', sans-serif", size: 11 },
                        callback: function(value) { return value + ' MB'; }
                    }
                }
            }
        }
    });
}
</script>
</body>
</html>
