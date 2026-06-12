<?php
require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

if (!isset($_SESSION['oauth_state']) || !isset($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    die('Ошибка безопасности: неверный state');
}

if (!isset($_GET['code'])) {
    die('Ошибка: код авторизации не получен');
}

$tokenUrl = 'https://discord.com/api/oauth2/token';
$tokenData = [
    'client_id' => DISCORD_CLIENT_ID,
    'client_secret' => DISCORD_CLIENT_SECRET,
    'grant_type' => 'authorization_code',
    'code' => $_GET['code'],
    'redirect_uri' => DISCORD_REDIRECT_URI
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode !== 200) {
    die('Ошибка получения токена: ' . $response);
}

$tokenInfo = json_decode($response, true);
$accessToken = $tokenInfo['access_token'];

$userUrl = 'https://discord.com/api/users/@me';
$ch = curl_init($userUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken
]);

$userResponse = curl_exec($ch);
$discordUser = json_decode($userResponse, true);

if (!isset($discordUser['id'])) {
    die('Ошибка получения данных пользователя Discord');
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE discord_id = ?");
$stmt->execute([$discordUser['id']]);
$user = $stmt->fetch();

$inviter = null;
if (!empty($_COOKIE['axion_inviter']) && is_string($_COOKIE['axion_inviter'])) {
    $cand = trim($_COOKIE['axion_inviter']);
    if ($cand !== '' && preg_match('/^[a-zA-Z0-9_.\-]{3,64}$/', $cand)) {
        $inviter = $cand;
    }
}

if (!$user) {
    $avatar = isset($discordUser['avatar']) ? "https://cdn.discordapp.com/avatars/{$discordUser['id']}/{$discordUser['avatar']}.png" : null;
    $_SESSION['pending_auth'] = [
        'provider' => 'discord',
        'provider_id' => (string)$discordUser['id'],
        'suggested_username' => (string)$discordUser['username'],
        'first_name' => '',
        'last_name' => '',
        'email' => (string)($discordUser['email'] ?? ''),
        'avatar' => $avatar
    ];


$notifyUrl = (string)(defined('SITE_URL') && SITE_URL !== '' ? SITE_URL : 'https://axion-hosting.ru');

$notifyUrl = (string)(defined('SITE_URL') && SITE_URL !== '' ? SITE_URL : 'https://axion-hosting.ru');
sendBotMessage($discordUser['id'] ?? '', ':white_check_mark: Вход в аккаунт ' . $notifyUrl);
header('Location: complete-profile.php');
    exit;
} else {
    $userId = $user['id'];

    if (empty($user['pterodactyl_id']) || empty($user['pterodactyl_password'])) {

        $email = $discordUser['email'] ?? $user['email'] ?? ($discordUser['id'] . '@discord.user');
        $username = $discordUser['username'];

        $pterodactylRegistration = registerUserInPterodactyl($username, $email, ($user['first_name'] ?? ''), ($user['last_name'] ?? ''));

        if ($pterodactylRegistration['success']) {

            $stmt = $pdo->prepare("
                UPDATE users
                SET pterodactyl_id = ?, pterodactyl_username = ?, pterodactyl_password = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $pterodactylRegistration['user_id'],
                $pterodactylRegistration['username'],
                $pterodactylRegistration['password'],
                $userId
            ]);
        }
    }

    $stmt = $pdo->prepare("
        UPDATE users
        SET discord_avatar = ?, email = ?, last_login = NOW()
        WHERE id = ?
    ");

    $avatar = isset($discordUser['avatar'])
        ? "https://cdn.discordapp.com/avatars/{$discordUser['id']}/{$discordUser['avatar']}.png"
        : null;

    $email = $discordUser['email'] ?? $user['email'];

    $stmt->execute([$avatar, $email, $userId]);
}

$stmt = $pdo->prepare("SELECT discord_username FROM users WHERE id = ?");
$stmt->execute([(int)$userId]);
$row = $stmt->fetch();
$sessionName = $row && !empty($row['discord_username']) ? (string)$row['discord_username'] : (string)$discordUser['username'];

$_SESSION['user_id'] = (int)$userId;
$_SESSION['discord_username'] = $sessionName;

$notifyUrl = (string)(defined('SITE_URL') && SITE_URL !== '' ? SITE_URL : 'https://axion-hosting.ru');
sendBotMessage($discordUser['id'] ?? '', ':white_check_mark: Вход в аккаунт ' . $notifyUrl);

$host = $_SERVER['HTTP_HOST'] ?? '';
$host = strtolower(preg_replace('/:\d+$/', '', (string)$host));
$cookieDomain = '';
if ($host !== '' && preg_match('/^www\.([a-z0-9-]+\.[a-z0-9-]+)$/', $host, $m)) {
    $cookieDomain = '.' . $m[1];
} elseif ($host !== '' && preg_match('/^([a-z0-9-]+\.[a-z0-9-]+)$/', $host, $m)) {
    $cookieDomain = '.' . $m[1];
}

if (!empty($_COOKIE['axion_inviter'])) {
    setcookie('axion_inviter', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => $cookieDomain !== '' ? $cookieDomain : null,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

if (!empty($_COOKIE['axion_pending_ticket']) && is_string($_COOKIE['axion_pending_ticket'])) {
    $raw = urldecode($_COOKIE['axion_pending_ticket']);
    if (is_string($raw) && $raw !== '') {
        $payload = json_decode($raw, true);
        $subject = isset($payload['subject']) && is_string($payload['subject']) ? trim($payload['subject']) : '';
        $message = isset($payload['message']) && is_string($payload['message']) ? trim($payload['message']) : '';
        if ($subject !== '' && $message !== '') {
            try {
                $stmt = $pdo->prepare("INSERT INTO tickets (user_id, subject, status) VALUES (?, ?, 'open')");
                $stmt->execute([(int)$userId, $subject]);
                $ticketId = (int)$pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender, message) VALUES (?, 'user', ?)");
                $stmt->execute([$ticketId, $message]);
                $_SESSION['created_ticket_id'] = $ticketId;

                try {
                    discordWebhookTickets([
                        'username' => 'Axion Support',
                        'embeds' => [[
                            'title' => '🆕 Новый тикет поддержки',
                            'color' => 0xFFD700,
                            'fields' => [
                                ['name' => 'Тикет', 'value' => '#' . $ticketId, 'inline' => true],
                                ['name' => 'Пользователь', 'value' => (string)($discordUsername ?? 'unknown') . ' (ID: ' . (string)($discordUser['id'] ?? $userId) . ')', 'inline' => false],
                                ['name' => 'Тема', 'value' => mb_substr($subject, 0, 256), 'inline' => false],
                                ['name' => 'Сообщение', 'value' => mb_substr($message, 0, 1000), 'inline' => false],
                            ],
                            'timestamp' => gmdate('c')
                        ]]
                    ]);
                } catch (Exception $ignore) {}
            } catch (Exception $e) {
            }
        }
    }
    setcookie('axion_pending_ticket', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => $cookieDomain !== '' ? $cookieDomain : null,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

$redirect = 'dashboard.php';
if (!empty($_SESSION['post_login_redirect']) && is_string($_SESSION['post_login_redirect'])) {
    $redirect = ltrim($_SESSION['post_login_redirect']);
    unset($_SESSION['post_login_redirect']);
}

if (!empty($_SESSION['created_ticket_id'])) {
    $redirect = 'dashboard.php?section=tickets&ticket=' . (int)$_SESSION['created_ticket_id'];
    unset($_SESSION['created_ticket_id']);
}
header('Location: ' . $redirect);
exit;
?>
