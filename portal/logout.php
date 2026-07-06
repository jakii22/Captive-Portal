<?php
/**
 * Logout Page - Clears portal session and redirects to MikroTik logout
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();

// Hapus sesi pengguna di captive portal
$_SESSION = array();
session_destroy();

// Ambil URL hotspot login, dan ubah menjadi URL logout
// Biasanya http://hotspot.local/login -> http://hotspot.local/logout
$hotspotUrl = getSetting('hotspot_login_url', 'http://hotspot.local/login');
$logoutUrl = str_replace('/login', '/logout', $hotspotUrl);

// Redirect ke halaman logout MikroTik
header('Location: ' . $logoutUrl);
exit;
