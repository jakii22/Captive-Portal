<?php
/**
 * Dashboard - MikroTik API Status & Configuration
 * Uses RouterOS API PHP to connect to MikroTik router
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/RouterOS/RouterosAPI.php';

requireLogin();
requireRole('full'); // Restrict to full admins only
$admin = getCurrentAdmin();

// Handle form submission (save MikroTik credentials)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan tidak valid.');
    } else {
        try {
            setSetting('mikrotik_ip', trim($_POST['mikrotik_ip'] ?? ''));
            setSetting('mikrotik_username', trim($_POST['mikrotik_username'] ?? ''));
            setSetting('mikrotik_password', trim($_POST['mikrotik_password'] ?? ''));
            setSetting('mikrotik_port', trim($_POST['mikrotik_port'] ?? '8728'));
            setFlash('success', 'Konfigurasi MikroTik berhasil disimpan.');
        } catch (Exception $e) {
            error_log('MikroTik Config Error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan konfigurasi.');
        }
    }

    header('Location: mikrotik.php');
    exit;
}

// Load current settings
$mtIp       = getSetting('mikrotik_ip', '192.168.88.1');
$mtUsername  = getSetting('mikrotik_username', 'admin');
$mtPassword = getSetting('mikrotik_password', '');
$mtPort     = getSetting('mikrotik_port', '8728');

// Try to connect and fetch status
$connected = false;
$routerInfo = [];
$hotspotUsers = [];
$error = '';

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
            $error = $api->getError();
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log('MikroTik Connection Error: ' . $error);
    }
}

$csrfToken = generateCsrfToken();
$flash = getFlash();
$currentPage = 'mikrotik';
$pageTitle = 'MikroTik API';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $pageTitle ?> - Admin Panel</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
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
                    <?= $connected ? 'Terhubung' : 'Tidak Terhubung' ?>
                </span>
            </div>
        </header>

        <main class="main-body">
            <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= sanitizeInput($flash['message']) ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                Gagal terhubung ke MikroTik: <?= sanitizeInput($error) ?>
            </div>
            <?php endif; ?>

            <div class="mikrotik-grid mb-6">
                <!-- Connection Config -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Konfigurasi Koneksi</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST">
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
                                Simpan & Hubungkan
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Router Status -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Status Router</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($connected && !empty($routerInfo)): ?>
                        <dl class="mikrotik-info">
                            <dt>Identity</dt>
                            <dd><?= sanitizeInput($routerInfo['identity'] ?? '-') ?></dd>

                            <dt>Board</dt>
                            <dd><?= sanitizeInput($routerInfo['board-name'] ?? '-') ?></dd>

                            <dt>RouterOS</dt>
                            <dd><?= sanitizeInput($routerInfo['version'] ?? '-') ?></dd>

                            <dt>Architecture</dt>
                            <dd><?= sanitizeInput($routerInfo['architecture-name'] ?? '-') ?></dd>

                            <dt>Uptime</dt>
                            <dd class="text-green"><?= sanitizeInput($routerInfo['uptime'] ?? '-') ?></dd>

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
                            <p style="font-size:0.8rem;">Masukkan kredensial di form sebelah kiri untuk terhubung.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Active Hotspot Users -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Active Hotspot Users (<?= count($hotspotUsers) ?>)</h3>
                    <?php if ($connected): ?>
                    <a href="mikrotik.php" class="btn btn-sm">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        Refresh
                    </a>
                    <?php endif; ?>
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
        </main>
    </div>
</div>

<script src="assets/js/dashboard.js"></script>
</body>
</html>
