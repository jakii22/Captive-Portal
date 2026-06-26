<?php
/**
 * Dashboard - Accounts Management (CRUD)
 * Only 'full' role can create/edit/delete
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('full'); // Restrict to full admins only
$admin = getCurrentAdmin();
$flash = getFlash();

$db = Database::getInstance();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isFullAdmin()) {
    $action = $_POST['action'] ?? '';

    try {
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Token keamanan tidak valid.');
        }

        switch ($action) {
            case 'create':
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $role     = $_POST['role'] ?? 'read';

                if (empty($username) || empty($password)) {
                    throw new RuntimeException('Username dan password wajib diisi.');
                }
                if (!in_array($role, ['full', 'read'])) {
                    throw new RuntimeException('Role tidak valid.');
                }

                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare('INSERT INTO accounts (username, password, role) VALUES (:username, :password, :role)');
                $stmt->execute([':username' => $username, ':password' => $hashedPassword, ':role' => $role]);
                setFlash('success', "Akun '{$username}' berhasil dibuat.");
                break;

            case 'update':
                $id       = (int) ($_POST['id'] ?? 0);
                $username = trim($_POST['username'] ?? '');
                $role     = $_POST['role'] ?? 'read';
                $password = $_POST['password'] ?? '';

                if (empty($username)) {
                    throw new RuntimeException('Username wajib diisi.');
                }

                if (!empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $db->prepare('UPDATE accounts SET username = :username, password = :password, role = :role WHERE id = :id');
                    $stmt->execute([':username' => $username, ':password' => $hashedPassword, ':role' => $role, ':id' => $id]);
                } else {
                    $stmt = $db->prepare('UPDATE accounts SET username = :username, role = :role WHERE id = :id');
                    $stmt->execute([':username' => $username, ':role' => $role, ':id' => $id]);
                }
                setFlash('success', "Akun '{$username}' berhasil diperbarui.");
                break;

            case 'delete':
                $id = (int) ($_POST['id'] ?? 0);
                if ($id === $admin['id']) {
                    throw new RuntimeException('Tidak bisa menghapus akun sendiri.');
                }
                $stmt = $db->prepare('DELETE FROM accounts WHERE id = :id');
                $stmt->execute([':id' => $id]);
                setFlash('success', 'Akun berhasil dihapus.');
                break;
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'unique') !== false || strpos($e->getMessage(), 'duplicate') !== false) {
            setFlash('error', 'Username sudah digunakan.');
        } else {
            error_log('Accounts Error: ' . $e->getMessage());
            setFlash('error', 'Terjadi kesalahan database.');
        }
    } catch (RuntimeException $e) {
        setFlash('error', $e->getMessage());
    }

    header('Location: accounts.php');
    exit;
}

// Fetch all accounts
$flash = getFlash();
$stmt = $db->query('SELECT id, username, role, created_at FROM accounts ORDER BY created_at DESC');
$accounts = $stmt->fetchAll();

$csrfToken = generateCsrfToken();
$currentPage = 'accounts';
$pageTitle = 'Accounts';
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
            <?php if (isFullAdmin()): ?>
            <div class="main-header-actions">
                <button class="btn btn-primary" data-modal-target="createModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Tambah Akun
                </button>
            </div>
            <?php endif; ?>
        </header>

        <main class="main-body">
            <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= sanitizeInput($flash['message']) ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Daftar Admin (<?= count($accounts) ?>)</h3>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Dibuat</th>
                                <?php if (isFullAdmin()): ?>
                                <th style="width:120px;">Aksi</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accounts as $acc): ?>
                            <tr>
                                <td>
                                    <strong><?= sanitizeInput($acc['username']) ?></strong>
                                    <?php if ($acc['id'] == $admin['id']): ?>
                                        <span class="badge badge-cyan" style="margin-left:6px;">You</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $acc['role'] === 'full' ? 'badge-violet' : 'badge-amber' ?>">
                                        <?= ucfirst($acc['role']) ?>
                                    </span>
                                </td>
                                <td class="text-muted text-nowrap"><?= date('d M Y, H:i', strtotime($acc['created_at'])) ?></td>
                                <?php if (isFullAdmin()): ?>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm" onclick="editAccount(<?= htmlspecialchars(json_encode($acc)) ?>)">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                        <?php if ($acc['id'] != $admin['id']): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $acc['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" data-confirm="Hapus akun '<?= sanitizeInput($acc['username']) ?>'?">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Create Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Tambah Akun Baru</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-input" required placeholder="Masukkan username">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" required placeholder="Masukkan password" minlength="6">
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="read">Read Only</option>
                        <option value="full">Full Access</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-modal-close>Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Edit Akun</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" id="editUsername" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password Baru <span class="text-muted">(kosongkan jika tidak ingin mengubah)</span></label>
                    <input type="password" name="password" class="form-input" placeholder="Password baru (opsional)">
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" id="editRole" class="form-select">
                        <option value="read">Read Only</option>
                        <option value="full">Full Access</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-modal-close>Batal</button>
                <button type="submit" class="btn btn-primary">Perbarui</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/dashboard.js"></script>
<script>
function editAccount(account) {
    document.getElementById('editId').value = account.id;
    document.getElementById('editUsername').value = account.username;
    document.getElementById('editRole').value = account.role;
    openModal('editModal');
}
</script>
</body>
</html>
