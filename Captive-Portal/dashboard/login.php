<?php
/**
 * Dashboard - Admin Login
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

ensureSession();

// Already logged in?
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } elseif (loginAdmin($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Login - Dashboard Admin</title>
    <link rel="stylesheet" href="assets/css/dashboard.css?v=1.1">
</head>
<body class="login-page">
    <div class="login-box">
        <div style="text-align:center; margin-bottom:24px;">
            <div style="width:48px;height:48px;background:linear-gradient(135deg,#7c3aed,#06b6d4);border-radius:10px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
        </div>

        <h1 class="login-box-title">Dashboard Admin</h1>
        <p class="login-box-subtitle">Masuk untuk mengelola captive portal</p>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <?= sanitizeInput($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input type="text" id="username" name="username" class="form-input"
                       placeholder="Masukkan username" autocomplete="username" required
                       value="<?= sanitizeInput($_POST['username'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input"
                       placeholder="Masukkan password" autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn btn-primary w-full" style="justify-content:center; padding: 12px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                Masuk
            </button>
        </form>
    </div>
</body>
</html>
