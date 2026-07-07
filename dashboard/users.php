<?php
/**
 * Dashboard - Users Management
 * Data from `users` JOIN `radacct`
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$admin = getCurrentAdmin();

$db = Database::getInstance();
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

    header('Location: users.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : ''));
    exit;
}

// Filters
$filterMethod = $_GET['method'] ?? '';
$search       = trim($_GET['search'] ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 20;

try {
    // Build query
    $where = [];
    $params = [];

    if (!empty($filterMethod)) {
        $where[] = 'u.login_method = :method';
        $params[':method'] = $filterMethod;
    }

    if (!empty($search)) {
        $where[] = '(u.username_identity ILIKE :search OR u.name ILIKE :search2)';
        $params[':search'] = "%{$search}%";
        $params[':search2'] = "%{$search}%";
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count total
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM users u {$whereClause}");
    $countStmt->execute($params);
    $totalItems = (int) $countStmt->fetch()['total'];

    $pagination = getPagination($totalItems, $perPage, $page);

    // Fetch users with aggregated radacct data
    $sql = "
        SELECT u.*,
               COUNT(ra.radacctid) as total_sessions,
               COALESCE(SUM(ra.acctinputoctets), 0) as total_upload,
               COALESCE(SUM(ra.acctoutputoctets), 0) as total_download,
               COALESCE(SUM(ra.acctsessiontime), 0) as total_session_time,
               MAX(ra.acctstarttime) as last_login
        FROM users u
        LEFT JOIN radacct ra ON ra.username = u.username_identity
        {$whereClause}
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Users Error: ' . $e->getMessage());
    $users = [];
    $pagination = getPagination(0);
}

$currentPage = 'users';
$pageTitle = 'Users';
$flash = getFlash();
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
                <a href="export-users.php" class="btn btn-success">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export Laporan
                </a>
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
                    <h3 class="card-title">Pengguna Portal (<?= number_format($totalItems) ?>)</h3>
                    <!-- Filters -->
                    <form method="GET" class="flex gap-2 items-center flex-wrap">
                        <input type="text" name="search" class="form-input" style="width:200px;padding:7px 12px;font-size:0.8rem;" placeholder="Cari nama / email..." value="<?= sanitizeInput($search) ?>">
                        <select name="method" class="form-select" style="width:140px;padding:7px 12px;font-size:0.8rem;">
                            <option value="">Semua Metode</option>
                            <option value="google" <?= $filterMethod === 'google' ? 'selected' : '' ?>>Google</option>
                            <option value="facebook" <?= $filterMethod === 'facebook' ? 'selected' : '' ?>>Facebook</option>
                            <option value="free" <?= $filterMethod === 'free' ? 'selected' : '' ?>>Gratis</option>
                        </select>
                        <button type="submit" class="btn btn-sm">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            Filter
                        </button>
                        <?php if (!empty($search) || !empty($filterMethod)): ?>
                        <a href="users.php" class="btn btn-sm">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Pengguna</th>
                                <th>Metode</th>
                                <th>Sesi</th>
                                <th>Upload</th>
                                <th>Download</th>
                                <th>Durasi Total</th>
                                <th>Login Terakhir</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                            <tr><td colspan="8" class="text-center text-muted" style="padding:32px;">Tidak ada pengguna ditemukan</td></tr>
                            <?php else: ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <?php if ($user['photo_url']): ?>
                                        <img src="<?= sanitizeInput($user['photo_url']) ?>" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
                                        <?php else: ?>
                                        <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#06b6d4);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;color:white;flex-shrink:0;">
                                            <?= strtoupper(substr($user['name'] ?? $user['username_identity'], 0, 1)) ?>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= sanitizeInput($user['name'] ?? '-') ?></strong>
                                            <br><span class="text-muted" style="font-size:0.7rem;"><?= sanitizeInput($user['username_identity']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $mb = match($user['login_method']) {
                                        'google'   => 'badge-cyan',
                                        'facebook' => 'badge-violet',
                                        'free'     => 'badge-green',
                                        default    => 'badge-amber',
                                    };
                                    ?>
                                    <span class="badge <?= $mb ?>"><?= ucfirst($user['login_method']) ?></span>
                                </td>
                                <td><?= number_format((int) $user['total_sessions']) ?></td>
                                <td class="text-nowrap"><?= formatBytes((int) $user['total_upload']) ?></td>
                                <td class="text-nowrap"><?= formatBytes((int) $user['total_download']) ?></td>
                                <td class="text-nowrap"><?= formatDuration((int) $user['total_session_time']) ?></td>
                                <td class="text-muted text-nowrap">
                                    <?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '-' ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="user-detail.php?id=<?= $user['id'] ?>" class="btn btn-sm">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                            Detail
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteUser(<?= $user['id'] ?>, '<?= sanitizeInput(addslashes($user['name'] ?? $user['username_identity'])) ?>')">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </div>
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
