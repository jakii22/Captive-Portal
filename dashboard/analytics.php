<?php
/**
 * Dashboard - Advanced Analytics & Demografi
 * Heatmap penggunaan Wi-Fi, demografi pengguna, peak hours, dan trends
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$admin = getCurrentAdmin();
$flash = getFlash();

try {
    $db = Database::getInstance();

    // ── Heatmap Data (7 hari × 24 jam) ──
    // Menghitung jumlah session per hari-per-jam selama 4 minggu terakhir
    $stmt = $db->query("
        SELECT EXTRACT(DOW FROM acctstarttime)::int AS day_of_week,
               EXTRACT(HOUR FROM acctstarttime)::int AS hour_of_day,
               COUNT(*) AS session_count
        FROM radacct
        WHERE acctstarttime >= CURRENT_DATE - INTERVAL '28 days'
        GROUP BY day_of_week, hour_of_day
        ORDER BY day_of_week, hour_of_day
    ");
    $heatmapRaw = $stmt->fetchAll();

    // Build 7×24 grid
    $heatmapData = [];
    $maxSessions = 1;
    for ($d = 0; $d < 7; $d++) {
        for ($h = 0; $h < 24; $h++) {
            $heatmapData[$d][$h] = 0;
        }
    }
    foreach ($heatmapRaw as $row) {
        $d = (int)$row['day_of_week'];
        $h = (int)$row['hour_of_day'];
        $count = (int)$row['session_count'];
        $heatmapData[$d][$h] = $count;
        if ($count > $maxSessions) $maxSessions = $count;
    }

    // Find peak hour and peak day
    $peakHour = 0; $peakHourCount = 0;
    $peakDay = 0; $peakDayCount = 0;
    $hourTotals = array_fill(0, 24, 0);
    $dayTotals = array_fill(0, 7, 0);
    
    for ($d = 0; $d < 7; $d++) {
        for ($h = 0; $h < 24; $h++) {
            $hourTotals[$h] += $heatmapData[$d][$h];
            $dayTotals[$d] += $heatmapData[$d][$h];
        }
    }
    foreach ($hourTotals as $h => $total) {
        if ($total > $peakHourCount) { $peakHour = $h; $peakHourCount = $total; }
    }
    foreach ($dayTotals as $d => $total) {
        if ($total > $peakDayCount) { $peakDay = $d; $peakDayCount = $total; }
    }

    $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $dayNamesShort = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    // ── Demografi Gender ──
    $stmt = $db->query("
        SELECT COALESCE(NULLIF(gender, ''), 'Tidak Diketahui') AS gender_label,
               COUNT(*) AS count
        FROM users
        GROUP BY gender_label
        ORDER BY count DESC
    ");
    $genderData = $stmt->fetchAll();

    // ── Demografi Login Method ──
    $stmt = $db->query("
        SELECT login_method, COUNT(*) AS count
        FROM users
        GROUP BY login_method
        ORDER BY count DESC
    ");
    $methodData = $stmt->fetchAll();

    // ── Tren Pengguna Baru (30 hari) ──
    $stmt = $db->query("
        SELECT DATE(created_at) AS date, COUNT(*) AS count
        FROM users
        WHERE created_at >= CURRENT_DATE - INTERVAL '29 days'
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $trendRaw = $stmt->fetchAll();

    // Fill in missing dates
    $trendLabels = [];
    $trendValues = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $trendLabels[] = date('d M', strtotime($date));
        $found = false;
        foreach ($trendRaw as $row) {
            if ($row['date'] === $date) {
                $trendValues[] = (int)$row['count'];
                $found = true;
                break;
            }
        }
        if (!$found) $trendValues[] = 0;
    }

    // ── Summary Stats ──
    $stmt = $db->query("SELECT COUNT(*) AS total FROM users");
    $totalUsers = (int)($stmt->fetch()['total'] ?? 0);

    $stmt = $db->query("SELECT COUNT(*) AS total FROM users WHERE created_at >= CURRENT_DATE - INTERVAL '7 days'");
    $usersThisWeek = (int)($stmt->fetch()['total'] ?? 0);

    $stmt = $db->query("
        SELECT COUNT(*) AS total FROM radacct
        WHERE acctstarttime >= CURRENT_DATE - INTERVAL '28 days'
    ");
    $totalSessions28d = (int)($stmt->fetch()['total'] ?? 0);

} catch (PDOException $e) {
    error_log('Analytics Error: ' . $e->getMessage());
    $heatmapData = [];
    $maxSessions = 1;
    $genderData = $methodData = [];
    $trendLabels = $trendValues = [];
    $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $dayNamesShort = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    $peakHour = $peakDay = $peakHourCount = $peakDayCount = 0;
    $hourTotals = array_fill(0, 24, 0);
    $dayTotals = array_fill(0, 7, 0);
    $totalUsers = $usersThisWeek = $totalSessions28d = 0;
}

$currentPage = 'analytics';
$pageTitle = 'Analytics & Demografi';
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <style>
        .heatmap-container {
            overflow-x: auto;
            padding-bottom: 8px;
        }
        .heatmap-grid {
            display: grid;
            grid-template-columns: 56px repeat(24, 1fr);
            gap: 2px;
            min-width: 500px;
        }
        .heatmap-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 8px;
            font-weight: 500;
        }
        .heatmap-label-top {
            font-size: 0.65rem;
            color: var(--text-muted);
            text-align: center;
            padding-bottom: 4px;
            font-weight: 500;
        }
        .heatmap-cell {
            aspect-ratio: 1;
            border-radius: 4px;
            min-width: 12px;
            min-height: 12px;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            position: relative;
        }
        .heatmap-cell:hover {
            transform: scale(1.3);
            z-index: 10;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
        .heatmap-cell[data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.85);
            color: #fff;
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 6px;
            white-space: nowrap;
            z-index: 20;
            pointer-events: none;
        }
        .heatmap-legend {
            display: flex;
            align-items: center;
            gap: 6px;
            justify-content: flex-end;
            margin-top: 12px;
            font-size: 0.7rem;
            color: var(--text-muted);
        }
        .heatmap-legend-cell {
            width: 14px;
            height: 14px;
            border-radius: 3px;
        }

        .stat-mini {
            background: var(--bg-card);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 20px 24px;
            text-align: center;
        }
        .stat-mini-value {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #7c3aed, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }
        .stat-mini-label {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .stat-mini-sub {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 2px;
            opacity: 0.7;
        }

        .chart-container {
            position: relative;
            height: 260px;
        }
        .chart-container-sm {
            position: relative;
            height: 220px;
        }
    </style>
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
                <span class="text-muted" style="font-size:0.85rem;">Data 28 hari terakhir</span>
            </div>
        </header>

        <main class="main-body">
            <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?> mb-6">
                <?= sanitizeInput($flash['message']) ?>
            </div>
            <?php endif; ?>

            <!-- Summary Stats -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;" class="mb-6">
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= number_format($totalUsers) ?></div>
                    <div class="stat-mini-label">Total Pengguna</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= number_format($usersThisWeek) ?></div>
                    <div class="stat-mini-label">Pengguna Baru</div>
                    <div class="stat-mini-sub">7 hari terakhir</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= str_pad($peakHour, 2, '0', STR_PAD_LEFT) ?>:00</div>
                    <div class="stat-mini-label">Jam Tersibuk</div>
                    <div class="stat-mini-sub"><?= number_format($peakHourCount) ?> sesi</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-value"><?= $dayNamesShort[$peakDay] ?? '-' ?></div>
                    <div class="stat-mini-label">Hari Tersibuk</div>
                    <div class="stat-mini-sub"><?= $dayNames[$peakDay] ?? '-' ?> (<?= number_format($peakDayCount) ?> sesi)</div>
                </div>
            </div>

            <!-- Heatmap -->
            <div class="card mb-6">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px;"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
                        Heatmap Penggunaan Wi-Fi
                    </h3>
                    <span class="badge badge-violet"><?= number_format($totalSessions28d) ?> sesi total</span>
                </div>
                <div class="card-body">
                    <div class="heatmap-container">
                        <div class="heatmap-grid">
                            <!-- Header row (hours) -->
                            <div></div>
                            <?php for ($h = 0; $h < 24; $h++): ?>
                            <div class="heatmap-label-top"><?= str_pad($h, 2, '0', STR_PAD_LEFT) ?></div>
                            <?php endfor; ?>

                            <!-- Data rows -->
                            <?php for ($d = 0; $d < 7; $d++): ?>
                            <div class="heatmap-label"><?= $dayNamesShort[$d] ?></div>
                            <?php for ($h = 0; $h < 24; $h++):
                                $count = $heatmapData[$d][$h] ?? 0;
                                $intensity = $maxSessions > 0 ? $count / $maxSessions : 0;
                                
                                if ($count === 0) {
                                    $color = 'rgba(124, 58, 237, 0.06)';
                                } elseif ($intensity <= 0.25) {
                                    $color = 'rgba(124, 58, 237, 0.2)';
                                } elseif ($intensity <= 0.5) {
                                    $color = 'rgba(124, 58, 237, 0.4)';
                                } elseif ($intensity <= 0.75) {
                                    $color = 'rgba(124, 58, 237, 0.65)';
                                } else {
                                    $color = 'rgba(124, 58, 237, 0.9)';
                                }
                                
                                $tooltip = $dayNames[$d] . ' ' . str_pad($h, 2, '0', STR_PAD_LEFT) . ':00 — ' . $count . ' sesi';
                            ?>
                            <div class="heatmap-cell" style="background:<?= $color ?>" data-tooltip="<?= $tooltip ?>"></div>
                            <?php endfor; ?>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="heatmap-legend">
                        <span>Sepi</span>
                        <div class="heatmap-legend-cell" style="background:rgba(124,58,237,0.06)"></div>
                        <div class="heatmap-legend-cell" style="background:rgba(124,58,237,0.2)"></div>
                        <div class="heatmap-legend-cell" style="background:rgba(124,58,237,0.4)"></div>
                        <div class="heatmap-legend-cell" style="background:rgba(124,58,237,0.65)"></div>
                        <div class="heatmap-legend-cell" style="background:rgba(124,58,237,0.9)"></div>
                        <span>Ramai</span>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;" class="mb-6">
                <!-- Gender Chart -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            Demografi Gender
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($genderData)): ?>
                        <div class="chart-container-sm">
                            <canvas id="genderChart"></canvas>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <p>Belum ada data gender pengguna</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Login Method Chart -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px;"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                            Metode Login
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($methodData)): ?>
                        <div class="chart-container-sm">
                            <canvas id="methodChart"></canvas>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <p>Belum ada data login pengguna</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- User Trend (30 days) -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                        Tren Pengguna Baru (30 Hari)
                    </h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="assets/js/dashboard.js?v=1.2"></script>
<script>
// Chart.js defaults
Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text-secondary').trim() || '#a0a0b8';
Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';
Chart.defaults.font.family = "'Inter', -apple-system, sans-serif";

// Gender Chart
<?php if (!empty($genderData)): ?>
new Chart(document.getElementById('genderChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($genderData, 'gender_label')) ?>,
        datasets: [{
            data: <?= json_encode(array_map('intval', array_column($genderData, 'count'))) ?>,
            backgroundColor: ['#7c3aed', '#06b6d4', '#f59e0b', '#10b981', '#f43f5e', '#6366f1'],
            borderWidth: 0,
            hoverOffset: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 16, usePointStyle: true, pointStyleWidth: 10, font: { size: 12 } }
            }
        }
    }
});
<?php endif; ?>

// Login Method Chart
<?php if (!empty($methodData)): ?>
new Chart(document.getElementById('methodChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(function($r) {
            $map = ['google' => 'Google', 'facebook' => 'Facebook', 'free' => 'Akses Gratis'];
            return $map[$r['login_method']] ?? ucfirst($r['login_method']);
        }, $methodData)) ?>,
        datasets: [{
            data: <?= json_encode(array_map('intval', array_column($methodData, 'count'))) ?>,
            backgroundColor: ['#4285f4', '#1877f2', '#10b981', '#f59e0b', '#f43f5e'],
            borderWidth: 0,
            hoverOffset: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 16, usePointStyle: true, pointStyleWidth: 10, font: { size: 12 } }
            }
        }
    }
});
<?php endif; ?>

// Trend Chart (30 days)
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($trendLabels) ?>,
        datasets: [{
            label: 'Pengguna Baru',
            data: <?= json_encode($trendValues) ?>,
            borderColor: '#7c3aed',
            backgroundColor: 'rgba(124, 58, 237, 0.1)',
            borderWidth: 2.5,
            fill: true,
            tension: 0.4,
            pointRadius: 0,
            pointHoverRadius: 6,
            pointHoverBackgroundColor: '#7c3aed',
            pointHoverBorderColor: '#fff',
            pointHoverBorderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
            x: {
                grid: { display: false },
                ticks: { maxRotation: 0, font: { size: 10 }, maxTicksLimit: 10 }
            },
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1, font: { size: 11 } },
                grid: { color: 'rgba(255,255,255,0.04)' }
            }
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                titleFont: { size: 12 },
                bodyFont: { size: 12 },
                padding: 10,
                cornerRadius: 8,
                callbacks: {
                    label: ctx => ctx.parsed.y + ' pengguna baru'
                }
            }
        }
    }
});
</script>
</body>
</html>
