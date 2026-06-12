<?php
require_once 'config.php';

$u = $_GET['u'] ?? '';
if (!is_string($u)) {
    header('Location: /');
    exit;
}
$u = trim($u);
if ($u === '' || !preg_match('/^[a-zA-Z0-9_.\-]{3,64}$/', $u)) {
    header('Location: /');
    exit;
}

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

$host = $_SERVER['HTTP_HOST'] ?? '';
$host = strtolower(preg_replace('/:\d+$/', '', (string)$host));
$cookieDomain = '';
if ($host !== '' && preg_match('/^([a-z0-9-]+)\.([a-z0-9-]+)$/', $host, $m)) {
    $cookieDomain = $m[1] . '.' . $m[2];
} elseif ($host !== '' && preg_match('/^www\.([a-z0-9-]+\.[a-z0-9-]+)$/', $host, $m)) {
    $cookieDomain = $m[1];
}
if ($cookieDomain !== '') {
    $cookieDomain = '.' . $cookieDomain;
}

setcookie('axion_inviter', $u, [
    'expires' => time() + 60 * 60 * 24 * 30,
    'path' => '/',
    'domain' => $cookieDomain !== '' ? $cookieDomain : null,
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

header('Location: /');
exit;
