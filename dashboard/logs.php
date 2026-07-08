<?php
/**
 * Dashboard - Connection Logs
 * Data from `radacct` JOIN `users`
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$admin = getCurrentAdmin();

$db = Database::getInstance();

// Filters
$search       = trim($_GET['search'] ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 20;

try {
    // Build query
    $where = [];
    $params = [];

    if (!empty($search)) {
        $where[] = '(ra.username ILIKE :search OR ra.callingstationid ILIKE :search2 OR u.name ILIKE :search3)';
        $params[':search'] = "%{$search}%";
        $params[':search2'] = "%{$search}%";
        $params[':search3'] = "%{$search}%";
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count total
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM radacct ra LEFT JOIN users u ON u.username_identity = ra.username {$whereClause}");
    $countStmt->execute($params);
    $totalItems = (int) $countStmt->fetch()['total'];

    $pagination = getPagination($totalItems, $perPage, $page);

    // Fetch logs
    $sql = "
        SELECT ra.*,
               u.name,
               u.login_method,
               u.photo_url
        FROM radacct ra
        LEFT JOIN users u ON u.username_identity = ra.username
        {$whereClause}
        ORDER BY ra.radacctid DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Logs Error: ' . $e->getMessage());
    $logs = [];
    $pagination = getPagination(0);
}

// Format duration to compact format (e.g. 1j 15m 10det)
function formatDurationCompact(int $seconds): string
{
    if ($seconds < 60) {
        return $seconds . ' det';
    }

    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    $parts = [];
    if ($hours > 0) $parts[] = $hours . 'j';
    if ($minutes > 0) $parts[] = $minutes . 'm';
    if ($secs > 0 && $hours == 0) $parts[] = $secs . ' det';

    return implode(' ', $parts);
}

$currentPage = 'logs';
$pageTitle = 'Riwayat Koneksi';
$flash = getFlash();
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
    <style>
        /* Desain tabel ringkas agar pas di layar tanpa geser */
        .data-table th, 
        .data-table td {
            padding: 10px 8px !important;
            font-size: 0.8rem !important;
        }
        .user-email-text {
            display: inline-block;
            max-width: 140px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: bottom;
            opacity: 0.7;
        }
        .status-cell {
            font-size: 0.78rem !important;
            line-height: 1.3;
            max-width: 140px;
            word-wrap: break-word;
            white-space: normal !important;
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
                    <h3 class="card-title">Riwayat Sesi Hotspot (<?= number_format($totalItems) ?>)</h3>
                    <!-- Filters -->
                    <form method="GET" class="flex gap-2 items-center flex-wrap">
                        <input type="text" name="search" class="form-input" style="width:250px;padding:7px 12px;font-size:0.8rem;" placeholder="Cari username / nama / MAC..." value="<?= sanitizeInput($search) ?>">
                        <button type="submit" class="btn btn-sm">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            Cari
                        </button>
                        <?php if (!empty($search)): ?>
                        <a href="logs.php" class="btn btn-sm">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Pengguna</th>
                                <th>MAC Address</th>
                                <th>IP Address</th>
                                <th>Waktu Mulai</th>
                                <th>Durasi</th>
                                <th>Upload</th>
                                <th>Download</th>
                                <th>Status / Info</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                            <tr><td colspan="8" class="text-center text-muted" style="padding:32px;">Tidak ada riwayat sesi ditemukan</td></tr>
                            <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <?php if (!empty($log['photo_url'])): ?>
                                        <img src="<?= sanitizeInput($log['photo_url']) ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                                        <?php else: ?>
                                        <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#06b6d4);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;color:white;flex-shrink:0;">
                                            <?= strtoupper(substr($log['name'] ?? $log['username'] ?? '?', 0, 1)) ?>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= sanitizeInput($log['name'] ?? '-') ?></strong>
                                            <br><span class="text-muted user-email-text" title="<?= sanitizeInput($log['username']) ?>"><?= sanitizeInput($log['username']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-nowrap" style="font-family: monospace; font-size: 0.85rem;"><?= sanitizeInput($log['callingstationid']) ?></td>
                                <td class="text-nowrap" style="font-family: monospace; font-size: 0.85rem;"><?= sanitizeInput(str_replace('/32', '', $log['framedipaddress'])) ?></td>
                                <td class="text-nowrap text-muted" style="font-size: 0.85rem;">
                                    <?= date('d/m/Y H:i:s', strtotime($log['acctstarttime'])) ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php
                                    if ($log['acctstoptime']) {
                                        echo formatDurationCompact((int) $log['acctsessiontime']);
                                    } else {
                                        $duration = time() - strtotime($log['acctstarttime']);
                                        echo '<span style="color:var(--success-color)">Aktif (' . formatDurationCompact($duration) . ')</span>';
                                    }
                                    ?>
                                </td>
                                <td class="text-nowrap text-muted" style="font-size: 0.85rem;"><?= formatBytes((int) $log['acctinputoctets']) ?></td>
                                <td class="text-nowrap text-muted" style="font-size: 0.85rem;"><?= formatBytes((int) $log['acctoutputoctets']) ?></td>
                                <td class="status-cell">
                                    <?php if ($log['acctstoptime']): ?>
                                        <span class="text-muted">Selesai (<?= sanitizeInput($log['acctterminatecause'] ?: 'Unknown') ?>)</span>
                                    <?php else: ?>
                                        <span class="badge badge-green">Online</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pagination['total_pages'] > 1): ?>
                <div class="card-footer">
                    <span class="text-muted" style="font-size:0.8rem;">
                        Menampilkan <?= $pagination['offset'] + 1 ?>-<?= min($pagination['offset'] + $perPage, $totalItems) ?> dari <?= number_format($totalItems) ?>
                    </span>
                    <div class="pagination">
                        <?php
                        $queryParams = $_GET;
                        for ($p = max(1, $pagination['current_page'] - 2); $p <= min($pagination['total_pages'], $pagination['current_page'] + 2); $p++):
                            $queryParams['page'] = $p;
                        ?>
                        <a href="?<?= http_build_query($queryParams) ?>" class="<?= $p === $pagination['current_page'] ? 'active' : '' ?>">
                            <?= $p ?>
                        </a>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script src="assets/js/dashboard.js?v=1.2"></script>
</body>
</html>
