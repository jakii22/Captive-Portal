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

$csrfToken = generateCsrfToken();

// Handle POST actions (delete user)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Token keamanan tidak valid.');
        }

        if ($action === 'delete') {
            $deleteId = (int) ($_POST['id'] ?? 0);
            if ($deleteId <= 0) {
                throw new RuntimeException('ID pengguna tidak valid.');
            }

            if (deleteUser($deleteId)) {
                setFlash('success', 'Pengguna berhasil dihapus beserta semua data terkait.');
            } else {
                setFlash('error', 'Gagal menghapus pengguna. Pengguna tidak ditemukan.');
            }
        }
    } catch (RuntimeException $e) {
        setFlash('error', $e->getMessage());
    }

    header('Location: active-sessions.php');
    exit;
}

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
               u.id AS user_id,
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
                                <th>Aksi</th>
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
                                <td>
                                    <?php if ($s['user_id']): ?>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteUser(<?= $s['user_id'] ?>, '<?= sanitizeInput(addslashes($s['user_name'] ?? $s['username'])) ?>')" title="Hapus Pengguna">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
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

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Hapus Pengguna</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body">
            <div style="text-align:center;margin-bottom:16px;">
                <div style="width:56px;height:56px;border-radius:50%;background:rgba(239,68,68,0.1);display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                </div>
                <p style="font-weight:600;font-size:1rem;margin-bottom:4px;">Yakin ingin menghapus pengguna ini?</p>
                <p class="text-muted" style="font-size:0.85rem;">User: <strong id="deleteUserName"></strong></p>
                <p class="text-muted" style="font-size:0.8rem;margin-top:8px;color:#ef4444;">Semua data terkait (sesi, RADIUS, log) akan ikut dihapus secara permanen.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn" data-modal-close>Batal</button>
            <form method="POST" id="deleteUserForm" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteUserId">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <button type="submit" class="btn btn-danger">Hapus Pengguna</button>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/dashboard.js?v=1.2"></script>
<script>
function confirmDeleteUser(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    openModal('deleteUserModal');
}
</script>
</body>
</html>
