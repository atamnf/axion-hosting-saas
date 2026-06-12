<?php
require_once 'config.php';
startSecureSession();

$token = (string)envv('TELEGRAM_BOT_TOKEN', '');
$botName = (string)envv('TELEGRAM_BOT_USERNAME', envv('TELEGRAM_BOT_NAME', ''));
$botName = preg_replace('/^@/', '', $botName);

if ($token === '' || $botName === '') {
    header('Location: login.php');
    exit;
}

$botIdPart = explode(':', $token, 2)[0] ?? '';
$botId = (int)$botIdPart;
if ($botId <= 0) {
    header('Location: login.php');
    exit;
}

$origin = '';
$siteUrl = (string)envv('SITE_URL', '');
if ($siteUrl !== '') {
    $ps = parse_url($siteUrl);
    if (is_array($ps) && !empty($ps['host'])) {
        $scheme = !empty($ps['scheme']) ? (string)$ps['scheme'] : 'https';
        $origin = $scheme . '://' . (string)$ps['host'];
    }
}
if ($origin === '') {
    $host = $_SERVER['HTTP_HOST'] ?? 'axion-hosting.ru';
    $host = preg_replace('/:\\d+$/', '', (string)$host);
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
    $origin = $scheme . '://' . $host;
}

$returnTo = '/tg-bridge.php';

$qs = http_build_query([
    'bot_id' => $botId,
    'origin' => $origin,
    'request_access' => 'write',
    'return_to' => $returnTo
]);

header('Location: https://oauth.telegram.org/auth?' . $qs, true, 302);
exit;
