<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();
requireRole('admin', 'manager');

// ─── QuickBooks OAuth2 Callback ───────────────────────────────────────────────
$code  = sanitize($_GET['code']       ?? '');
$state = sanitize($_GET['state']      ?? '');
$realm = sanitize($_GET['realmId']    ?? '');
$error = sanitize($_GET['error']      ?? '');

if ($error) {
    redirectWithMessage(BASE_URL . '/quickbooks/connect.php', 'QuickBooks authorisation denied: ' . $error, 'danger');
}

if (!$code || !$realm) {
    redirectWithMessage(BASE_URL . '/quickbooks/connect.php', 'Invalid callback parameters.', 'danger');
}

// State/CSRF check
if (!hash_equals($_SESSION['csrf_token'] ?? '', $state)) {
    redirectWithMessage(BASE_URL . '/quickbooks/connect.php', 'State mismatch – possible CSRF attempt.', 'danger');
}

// Exchange code for tokens
$response = curlPost(QB_TOKEN_URL, [
    'grant_type'   => 'authorization_code',
    'code'         => $code,
    'redirect_uri' => QB_REDIRECT_URI,
], [
    'Authorization: Basic ' . base64_encode(QB_CLIENT_ID . ':' . QB_CLIENT_SECRET),
    'Accept: application/json',
    'Content-Type: application/x-www-form-urlencoded',
]);

if (!$response || empty($response['access_token'])) {
    error_log('QB token exchange failed: ' . json_encode($response));
    redirectWithMessage(BASE_URL . '/quickbooks/connect.php', 'Failed to retrieve QuickBooks tokens.', 'danger');
}

$expiresAt = date('Y-m-d H:i:s', time() + (int)($response['expires_in'] ?? 3600));
$rtExpiresAt = date('Y-m-d H:i:s', time() + (int)($response['x_refresh_token_expires_in'] ?? 8726400));

// Upsert token record
$existing = dbRow('SELECT id FROM qb_tokens WHERE realm_id = ?', [$realm]);

if ($existing) {
    dbUpdate('qb_tokens', [
        'access_token'           => $response['access_token'],
        'refresh_token'          => $response['refresh_token'],
        'expires_at'             => $expiresAt,
        'refresh_token_expires_at' => $rtExpiresAt,
        'updated_at'             => date('Y-m-d H:i:s'),
    ], ['realm_id' => $realm]);
} else {
    dbInsert('qb_tokens', [
        'realm_id'               => $realm,
        'access_token'           => $response['access_token'],
        'refresh_token'          => $response['refresh_token'],
        'expires_at'             => $expiresAt,
        'refresh_token_expires_at' => $rtExpiresAt,
        'created_at'             => date('Y-m-d H:i:s'),
        'updated_at'             => date('Y-m-d H:i:s'),
    ]);
}

redirectWithMessage(BASE_URL . '/quickbooks/connect.php', 'QuickBooks connected successfully!');

// ─── Helpers ──────────────────────────────────────────────────────────────────
function curlPost(string $url, array $data, array $headers = []): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $result = curl_exec($ch);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log('cURL error: ' . $err);
        return null;
    }

    return json_decode($result, true);
}
