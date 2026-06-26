<?php
/**
 * Social Verification - OAuth Callback Handler
 * 
 * This is the KEY file that handles:
 * 1. Receives OAuth callback (Google/Facebook)
 * 2. Exchanges authorization code for access token
 * 3. Fetches user profile
 * 4. Saves user to `users` table (upsert)
 * 5. Generates dummy password for RADIUS
 * 6. Saves credentials to `radcheck` table (upsert)
 * 7. IMMEDIATELY redirects to MikroTik hotspot login
 * 
 * All steps happen in one request with NO extra loading screens.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();

// Determine provider from query string
$provider = $_GET['provider'] ?? '';
$code     = $_GET['code'] ?? '';
$error    = $_GET['error'] ?? '';

// Error from OAuth provider
if (!empty($error)) {
    error_log("OAuth Error from {$provider}: {$error}");
    redirectWithError('Login dibatalkan atau gagal. Silakan coba lagi.');
}

// Validate required parameters
if (empty($provider) || empty($code)) {
    redirectWithError('Parameter tidak valid. Silakan login ulang.');
}

try {
    $db = Database::getInstance();

    switch ($provider) {
        case 'google':
            $profile = handleGoogleCallback($code);
            break;
        case 'facebook':
            $profile = handleFacebookCallback($code);
            break;
        default:
            redirectWithError('Provider tidak dikenali.');
    }

    // Validate profile data
    if (empty($profile['username_identity'])) {
        throw new RuntimeException('Gagal mendapatkan identitas pengguna dari ' . ucfirst($provider));
    }

    // ── STEP A: Save profile to `users` table ──
    $userSaved = upsertUser(
        $profile['username_identity'],
        $profile['name'] ?? 'User',
        $provider,
        $profile['photo_url'] ?? null,
        $profile['gender'] ?? null
    );

    if (!$userSaved) {
        throw new RuntimeException('Gagal menyimpan data pengguna.');
    }

    // ── STEP B: Generate dummy password & save to `radcheck` ──
    $dummyPassword = generateDummyPassword();

    $radcheckSaved = upsertRadcheck(
        $profile['username_identity'],
        $dummyPassword
    );

    if (!$radcheckSaved) {
        throw new RuntimeException('Gagal menyimpan kredensial RADIUS.');
    }

    // ── STEP C: IMMEDIATELY redirect to MikroTik hotspot login ──
    // Store success info in session for success.php
    $_SESSION['login_success'] = true;
    $_SESSION['login_username'] = $profile['username_identity'];
    $_SESSION['login_name'] = $profile['name'] ?? 'User';
    $_SESSION['login_method'] = $provider;
    $_SESSION['login_time'] = time();

    // Redirect to MikroTik
    redirectToMikrotik($profile['username_identity'], $dummyPassword);

} catch (PDOException $e) {
    error_log('Social Verification DB Error: ' . $e->getMessage());
    redirectWithError('Terjadi kesalahan database. Silakan coba lagi.');
} catch (RuntimeException $e) {
    error_log('Social Verification Error: ' . $e->getMessage());
    redirectWithError($e->getMessage());
} catch (Exception $e) {
    error_log('Social Verification Unexpected Error: ' . $e->getMessage());
    redirectWithError('Terjadi kesalahan tidak terduga. Silakan coba lagi.');
}

// ── GOOGLE OAUTH HANDLER ──
function handleGoogleCallback(string $code): array
{
    $clientId     = getSetting('google_client_id');
    $clientSecret = getSetting('google_client_secret');

    if (empty($clientId) || empty($clientSecret)) {
        throw new RuntimeException('Google API belum dikonfigurasi.');
    }

    // Exchange code for access token
    $tokenResponse = httpPost('https://oauth2.googleapis.com/token', [
        'code'          => $code,
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]);

    if (empty($tokenResponse['access_token'])) {
        $errorMsg = $tokenResponse['error_description'] ?? 'Token exchange gagal';
        throw new RuntimeException('Google OAuth error: ' . $errorMsg);
    }

    // Fetch user profile
    $userInfo = httpGet(
        'https://www.googleapis.com/oauth2/v2/userinfo',
        $tokenResponse['access_token']
    );

    if (empty($userInfo['email'])) {
        throw new RuntimeException('Gagal mendapatkan email dari Google.');
    }

    return [
        'username_identity' => $userInfo['email'],
        'name'              => $userInfo['name'] ?? $userInfo['email'],
        'photo_url'         => $userInfo['picture'] ?? null,
        'gender'            => $userInfo['gender'] ?? null,
    ];
}

// ── FACEBOOK OAUTH HANDLER ──
function handleFacebookCallback(string $code): array
{
    $appId     = getSetting('facebook_app_id');
    $appSecret = getSetting('facebook_app_secret');

    if (empty($appId) || empty($appSecret)) {
        throw new RuntimeException('Facebook API belum dikonfigurasi.');
    }

    // Exchange code for access token
    $tokenResponse = httpGet(
        'https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query([
            'client_id'     => $appId,
            'client_secret' => $appSecret,
            'redirect_uri'  => FACEBOOK_REDIRECT_URI,
            'code'          => $code,
        ])
    );

    if (empty($tokenResponse['access_token'])) {
        $errorMsg = $tokenResponse['error']['message'] ?? 'Token exchange gagal';
        throw new RuntimeException('Facebook OAuth error: ' . $errorMsg);
    }

    // Fetch user profile
    $userInfo = httpGet(
        'https://graph.facebook.com/v18.0/me?fields=id,name,email,picture.type(large)&access_token='
        . urlencode($tokenResponse['access_token'])
    );

    // Facebook might not return email for some accounts
    $identity = $userInfo['email'] ?? ('fb_' . ($userInfo['id'] ?? ''));

    if (empty($identity) || $identity === 'fb_') {
        throw new RuntimeException('Gagal mendapatkan identitas dari Facebook.');
    }

    return [
        'username_identity' => $identity,
        'name'              => $userInfo['name'] ?? 'Facebook User',
        'photo_url'         => $userInfo['picture']['data']['url'] ?? null,
        'gender'            => null,
    ];
}

// ── HTTP HELPERS ──

/**
 * HTTP POST request using cURL
 */
function httpPost(string $url, array $data): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("HTTP POST Error: {$error}");
    }

    curl_close($ch);

    $decoded = json_decode($response, true);
    if ($decoded === null) {
        throw new RuntimeException("Invalid JSON response from {$url}");
    }

    return $decoded;
}

/**
 * HTTP GET request using cURL
 * Can accept a bearer token or a raw URL (for Facebook token-in-url style)
 */
function httpGet(string $url, ?string $bearerToken = null): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];

    if ($bearerToken !== null) {
        $headers[] = 'Authorization: Bearer ' . $bearerToken;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("HTTP GET Error: {$error}");
    }

    curl_close($ch);

    $decoded = json_decode($response, true);
    if ($decoded === null) {
        throw new RuntimeException("Invalid JSON response from {$url}");
    }

    return $decoded;
}

/**
 * Redirect to portal with error message
 */
function redirectWithError(string $message): void
{
    session_start();
    $_SESSION['portal_error'] = $message;
    header('Location: ' . PORTAL_URL . '/index.php?error=1');
    exit;
}

