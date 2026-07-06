<?php
/**
 * Dashboard - Advertisements Management
 * Upload, manage, toggle, and order ad images for success.php
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
requireRole('full');
$admin = getCurrentAdmin();

$db = Database::getInstance();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token keamanan tidak valid.');
        header('Location: advertisements.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'upload':
                $title   = trim($_POST['title'] ?? '');
                $linkUrl = trim($_POST['link_url'] ?? '');
                $order   = (int) ($_POST['display_order'] ?? 0);

                if (empty($title)) {
                    throw new RuntimeException('Judul iklan wajib diisi.');
                }

                // Handle file upload
                if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('File gambar wajib diunggah.');
                }

                $file = $_FILES['image'];

                // Validate file type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
                    throw new RuntimeException('Tipe file tidak diizinkan. Gunakan JPG, PNG, GIF, atau WebP.');
                }

                // Validate file size
                if ($file['size'] > MAX_UPLOAD_SIZE) {
                    throw new RuntimeException('Ukuran file maksimal ' . formatBytes(MAX_UPLOAD_SIZE) . '.');
                }

                // Generate unique filename
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
                $filename = 'ad_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destination = ADS_UPLOAD_DIR . $filename;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    throw new RuntimeException('Gagal menyimpan file. Periksa permission folder uploads.');
                }

                // Save to database
                $stmt = $db->prepare(
                    'INSERT INTO advertisements (title, image_path, link_url, display_order) VALUES (:title, :image, :link, :order)'
                );
                $stmt->execute([
                    ':title' => $title,
                    ':image' => $filename,
                    ':link'  => $linkUrl,
                    ':order' => $order,
                ]);

                setFlash('success', "Iklan '{$title}' berhasil diunggah.");
                break;

            case 'toggle':
                $id = (int) ($_POST['id'] ?? 0);
                $stmt = $db->prepare('UPDATE advertisements SET is_active = NOT is_active WHERE id = :id');
                $stmt->execute([':id' => $id]);
                setFlash('success', 'Status iklan berhasil diperbarui.');
                break;

            case 'update_order':
                $id    = (int) ($_POST['id'] ?? 0);
                $order = (int) ($_POST['display_order'] ?? 0);
                $stmt = $db->prepare('UPDATE advertisements SET display_order = :order WHERE id = :id');
                $stmt->execute([':order' => $order, ':id' => $id]);
                setFlash('success', 'Urutan iklan berhasil diperbarui.');
                break;

            case 'delete':
                $id = (int) ($_POST['id'] ?? 0);

                // Get image path before delete
                $stmt = $db->prepare('SELECT image_path FROM advertisements WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $ad = $stmt->fetch();

                if ($ad) {
                    // Delete file
                    $filePath = ADS_UPLOAD_DIR . $ad['image_path'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }

                    // Delete from database
                    $stmt = $db->prepare('DELETE FROM advertisements WHERE id = :id');
                    $stmt->execute([':id' => $id]);
                    setFlash('success', 'Iklan berhasil dihapus.');
                }
                break;
        }
    } catch (PDOException $e) {
        error_log('Advertisements Error: ' . $e->getMessage());
        setFlash('error', 'Terjadi kesalahan database.');
    } catch (RuntimeException $e) {
        setFlash('error', $e->getMessage());
    }

    header('Location: advertisements.php');
    exit;
}

// Fetch all advertisements
$flash = getFlash();
$stmt = $db->query('SELECT * FROM advertisements ORDER BY display_order ASC, created_at DESC');
$ads = $stmt->fetchAll();

$csrfToken = generateCsrfToken();
$currentPage = 'advertisements';
$pageTitle = 'Advertisements';
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
                <button class="btn btn-primary" data-modal-target="uploadModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Upload Iklan
                </button>
            </div>
        </header>

        <main class="main-body">
            <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= sanitizeInput($flash['message']) ?>
            </div>
            <?php endif; ?>

            <!-- Ad List -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Daftar Iklan (<?= count($ads) ?>)</h3>
                </div>

                <?php if (empty($ads)): ?>
                <div class="card-body">
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <p>Belum ada iklan. Klik "Upload Iklan" untuk menambahkan.</p>
                    </div>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width:80px;">Preview</th>
                                <th>Judul</th>
                                <th>Link</th>
                                <th>Urutan</th>
                                <th>Status</th>
                                <th>Dibuat</th>
                                <th style="width:160px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ads as $ad): ?>
                            <tr>
                                <td>
                                    <img src="<?= BASE_URL ?>/uploads/ads/<?= sanitizeInput($ad['image_path']) ?>"
                                         alt="<?= sanitizeInput($ad['title']) ?>"
                                         style="width:60px;height:40px;object-fit:cover;border-radius:4px;border:1px solid var(--glass-border);">
                                </td>
                                <td><strong><?= sanitizeInput($ad['title']) ?></strong></td>
                                <td class="text-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= $ad['link_url'] ? sanitizeInput($ad['link_url']) : '-' ?>
                                </td>
                                <td>
                                    <form method="POST" class="flex gap-2 items-center" style="display:inline-flex;">
                                        <input type="hidden" name="action" value="update_order">
                                        <input type="hidden" name="id" value="<?= $ad['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="number" name="display_order" class="form-input" value="<?= $ad['display_order'] ?>" style="width:60px;padding:4px 8px;font-size:0.8rem;text-align:center;" min="0">
                                        <button type="submit" class="btn btn-sm" title="Update urutan">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= $ad['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <button type="submit" class="btn btn-sm <?= $ad['is_active'] ? 'btn-success' : '' ?>"
                                                title="<?= $ad['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <?= $ad['is_active'] ? '● Aktif' : '○ Nonaktif' ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-muted text-nowrap"><?= date('d M Y', strtotime($ad['created_at'])) ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $ad['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="Hapus iklan '<?= sanitizeInput($ad['title']) ?>'?">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            Hapus
                                        </button>
                                    </form>
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

<!-- Upload Modal -->
<div class="modal-overlay" id="uploadModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Upload Iklan Baru</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Judul Iklan</label>
                    <input type="text" name="title" class="form-input" required placeholder="Judul iklan yang ditampilkan">
                </div>
                <div class="form-group">
                    <label class="form-label">Gambar Iklan</label>
                    <div class="upload-zone">
                        <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" required style="display:none;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                        <p>Klik atau drag gambar ke sini</p>
                        <p class="upload-hint">JPG, PNG, GIF, WebP — Maks. 5MB</p>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Link URL <span class="text-muted">(opsional)</span></label>
                    <input type="url" name="link_url" class="form-input" placeholder="https://example.com">
                    <p class="form-help">URL tujuan saat iklan diklik</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Urutan Tampil</label>
                    <input type="number" name="display_order" class="form-input" value="0" min="0" style="width:120px;">
                    <p class="form-help">Angka lebih kecil ditampilkan lebih dulu</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-modal-close>Batal</button>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/dashboard.js?v=1.2"></script>
</body>
</html>
