<?php
/**
 * Authentication Middleware for Dashboard
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

/**
 * Start session if not already started
 */
function ensureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Require admin login - redirect to login page if not authenticated
 */
function requireLogin(): void
{
    ensureSession();
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
        header('Location: ' . DASHBOARD_URL . '/login.php');
        exit;
    }
}

/**
 * Require specific role
 */
function requireRole(string $role): void
{
    requireLogin();
    if ($_SESSION['admin_role'] !== $role && $_SESSION['admin_role'] !== 'full') {
        http_response_code(403);
        echo '<h1>403 Forbidden</h1><p>Anda tidak memiliki akses ke halaman ini.</p>';
        exit;
    }
}

/**
 * Get current logged-in admin data
 */
function getCurrentAdmin(): ?array
{
    ensureSession();
    if (!isset($_SESSION['admin_id'])) {
        return null;
    }
    return [
        'id'       => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'],
        'role'     => $_SESSION['admin_role'],
    ];
}

/**
 * Login admin with credentials
 */
function loginAdmin(string $username, string $password): bool
{
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id, username, password, role FROM accounts WHERE username = :username');
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            ensureSession();
            session_regenerate_id(true);
            $_SESSION['admin_id']       = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role']     = $admin['role'];
            return true;
        }

        return false;
    } catch (PDOException $e) {
        error_log('Login error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Logout admin
 */
function logoutAdmin(): void
{
    ensureSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Check if current admin has full role
 */
function isFullAdmin(): bool
{
    ensureSession();
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'full';
}
