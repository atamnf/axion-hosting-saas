<?php
require_once 'config.php';
startSecureSession();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = strtolower(preg_replace('/:\\d+$/', '', (string)$host));
    $cookieDomain = '';
    if ($host !== '' && preg_match('/^www\\.([a-z0-9-]+\\.[a-z0-9-]+)$/', $host, $m)) {
        $cookieDomain = '.' . $m[1];
    } elseif ($host !== '' && preg_match('/^([a-z0-9-]+\\.[a-z0-9-]+)$/', $host, $m)) {
        $cookieDomain = '.' . $m[1];
    }
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $p['path'] ?? '/',
        'domain' => $cookieDomain !== '' ? $cookieDomain : ($p['domain'] ?? ''),
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
setcookie('axion_inviter', '', time() - 42000, '/');
setcookie('axion_pending_ticket', '', time() - 42000, '/');
session_destroy();
header('Location: index.html');
exit;
