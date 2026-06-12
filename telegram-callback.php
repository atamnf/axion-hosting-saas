<?php
require_once 'config.php';
startSecureSession();

function tg_fail(string $code, string $reason): void {
    error_log('TG_AUTH_FAIL[' . $code . ']: ' . $reason);
    header('Location: login.php?tgerr=' . rawurlencode($code));
    exit;
}

function axion_b64url_decode(string $s): string {
    $s = strtr($s, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad) $s .= str_repeat('=', 4 - $pad);
    $out = base64_decode($s, true);
    return $out === false ? '' : $out;
}

$token = (string)envv('TELEGRAM_BOT_TOKEN', '');
$botName = (string)envv('TELEGRAM_BOT_USERNAME', envv('TELEGRAM_BOT_NAME', ''));
$botName = preg_replace('/^@/', '', $botName);

if ($token === '' || $botName === '') {
    tg_fail('cfg', 'Missing TELEGRAM_BOT_TOKEN/NAME');
}

if (isset($_GET['tgAuthResult']) && is_string($_GET['tgAuthResult']) && $_GET['tgAuthResult'] !== '') {
    $decoded = axion_b64url_decode((string)$_GET['tgAuthResult']);
    if ($decoded !== '') {
        $tmp = null;
        $trim = ltrim($decoded);
        if ($trim !== '' && $trim[0] === '{') {
            $tmp = json_decode($decoded, true);
        } else {
            $tmp = [];
            parse_str($decoded, $tmp);
        }
        if (is_array($tmp) && !empty($tmp)) {
            foreach ($tmp as $k => $v) {
                if (!isset($_GET[$k])) $_GET[$k] = $v;
            }
        }
    }
}

$data = $_GET;
if (isset($data['tgAuthResult'])) {
    unset($data['tgAuthResult']);
}
if (!isset($data['hash']) || !is_string($data['hash'])) {
    tg_fail('nohash', 'No hash in callback');
}

$hash = (string)$data['hash'];
unset($data['hash']);

$checkArr = [];
foreach ($data as $k => $v) {
    if (!is_string($k)) continue;
    if (is_array($v) || is_object($v)) continue;
    $checkArr[] = $k . '=' . (string)$v;
}
sort($checkArr);
$dataCheckString = implode("\n", $checkArr);

$secretKey = hash('sha256', $token, true);
$calcHash = hash_hmac('sha256', $dataCheckString, $secretKey);

if (!hash_equals($calcHash, $hash)) {
    tg_fail('badhash', 'Hash verification failed');
}

$authDate = isset($_GET['auth_date']) ? (int)$_GET['auth_date'] : 0;
if ($authDate > 0 && $authDate < (time() - 86400)) {
    tg_fail('stale', 'auth_date too old');
}

$tgId = isset($_GET['id']) ? (string)$_GET['id'] : '';
if ($tgId === '') {
    tg_fail('noid', 'No telegram id');
}

$tgUsername = isset($_GET['username']) ? (string)$_GET['username'] : '';
$firstName = isset($_GET['first_name']) ? (string)$_GET['first_name'] : '';
$lastName = isset($_GET['last_name']) ? (string)$_GET['last_name'] : '';
$photoUrl = isset($_GET['photo_url']) ? (string)$_GET['photo_url'] : '';
if ($photoUrl === '') $photoUrl = null;

$displayName = $tgUsername;

$localId = 'tg_' . preg_replace('/\D+/', '', $tgId);
$avatar = $photoUrl;

try {
    $cols = [];
    foreach ($pdo->query("SHOW COLUMNS FROM users") as $row) {
        if (isset($row['Field'])) $cols[(string)$row['Field']] = true;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE discord_id = ?");
    $stmt->execute([$localId]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION['pending_auth'] = [
            'provider' => 'telegram',
            'provider_id' => $tgId,
            'suggested_username' => $displayName,
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'avatar' => $avatar
        ];
        header('Location: complete-profile.php');
        exit;
    } else {
        $userId = (int)$user['id'];
        $set = [];
        $vals = [];
        if (!empty($cols['discord_avatar'])) { $set[] = 'discord_avatar = ?'; $vals[] = $avatar; }
        if (!empty($cols['last_login'])) { $set[] = 'last_login = NOW()'; }
        if (!empty($set)) {
            $vals[] = $userId;
            $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $set) . " WHERE id = ?");
            $stmt->execute($vals);
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if ($user && !empty($cols['pterodactyl_id']) && empty($user['pterodactyl_id']) && !empty($user['email'])) {
        $pEmail = (string)$user['email'];
        $pUsername = $displayName !== '' ? $displayName : (string)$user['discord_username'];
        $pterodactylRegistration = registerUserInPterodactyl($pUsername, $pEmail, $firstName, $lastName);
        if (!empty($pterodactylRegistration['success'])) {
            $set = [];
            $vals = [];
            if (!empty($cols['pterodactyl_id'])) { $set[] = 'pterodactyl_id = ?'; $vals[] = (int)$pterodactylRegistration['user_id']; }
            if (!empty($cols['pterodactyl_username'])) { $set[] = 'pterodactyl_username = ?'; $vals[] = (string)$pterodactylRegistration['username']; }
            if (!empty($cols['pterodactyl_password'])) { $set[] = 'pterodactyl_password = ?'; $vals[] = (string)$pterodactylRegistration['password']; }
            if (!empty($set)) {
                $vals[] = $userId;
                $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $set) . " WHERE id = ?");
                $stmt->execute($vals);
            }
        }
    }

    $stmt = $pdo->prepare("SELECT discord_username FROM users WHERE id = ?");
    $stmt->execute([(int)$userId]);
    $row = $stmt->fetch();
    $sessionName = $row && !empty($row['discord_username']) ? (string)$row['discord_username'] : $displayName;

    $_SESSION['user_id'] = (int)$userId;
    $_SESSION['discord_username'] = $sessionName;

    header('Location: dashboard.php');
    exit;
} catch (Throwable $e) {
    tg_fail('ex', $e->getMessage());
}
