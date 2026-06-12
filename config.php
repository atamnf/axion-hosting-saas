<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors','0');
ini_set('log_errors','1');

function loadEnv(string $path): void {
    if (!is_file($path) || !is_readable($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        if ($val !== '' && (($val[0] === '"' && substr($val, -1) === '"') || ($val[0] === "'" && substr($val, -1) === "'"))) {
            $val = substr($val, 1, -1);
        }
        if ($key === '') continue;
        if (getenv($key) === false) {
            putenv($key . '=' . $val);
            $_ENV[$key] = $val;
        }
    }
}

loadEnv(__DIR__ . '/.env');

function envv(string $key, ?string $default=null): ?string {
    $v = getenv($key);
    if ($v === false) return $default;
    return $v;
}

function requireEnv(string $key): string {
    $v = envv($key);
    if ($v === null || $v === '') {
        http_response_code(500);
        exit;
    }
    return $v;
}

function setSecurityHeaders(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), usb=(), payment=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https: https://cdn.discordapp.com https://t.me https://*.t.me; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' https://telegram.org; connect-src 'self' https://discord.com https://discordapp.com https://oauth.telegram.org; frame-src https://oauth.telegram.org; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $lifetimeSeconds = (int)envv('AXION_SESSION_LIFETIME', '2592000'); // default 30 days
    if ($lifetimeSeconds < 3600) { $lifetimeSeconds = 3600; }
    ini_set('session.gc_maxlifetime', (string)$lifetimeSeconds);
    ini_set('session.cookie_lifetime', (string)$lifetimeSeconds);
    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $lifetimeSeconds,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_name('AXIONSESSID');
    session_start();
}

function csrfToken(): string {
    startSecureSession();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf'];
}

function requireCsrf(): void {
    startSecureSession();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!is_string($token) || $token === '' || empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit;
    }
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

setSecurityHeaders();
startSecureSession();


function sendBotMessage($discordId, string $message): void {
    $discordId = is_numeric($discordId) ? (string)$discordId : '';
    if ($discordId === '') return;

    $baseUrl = (string)envv('BOT_API_URL', '');
    if ($baseUrl === '') return;
    $password = (string)envv('BOT_API_PASSWORD', '');
    if ($password === '') return;

    $url = $baseUrl . '?password=' . rawurlencode($password)
        . '&id=' . rawurlencode($discordId)
        . '&message=' . rawurlencode($message);

    $ok = false;
    try {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2,
            ]
        ]);
        $res = @file_get_contents($url, false, $ctx);
        if ($res !== false) $ok = true;
    } catch (\Throwable $e) {
        $ok = false;
    }

    if (!$ok && function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        @curl_exec($ch);
        @curl_close($ch);
    }
}

define('DB_HOST', requireEnv('DB_HOST'));
define('DB_NAME', requireEnv('DB_NAME'));
define('DB_USER', requireEnv('DB_USER'));
define('DB_PASS', requireEnv('DB_PASS'));

define('PANEL_URL', requireEnv('PANEL_URL'));
define('PTERO_API_KEY', requireEnv('PTERO_API_KEY'));
define('API_KEY', PTERO_API_KEY);

define('DISCORD_CLIENT_ID', requireEnv('DISCORD_CLIENT_ID'));
define('DISCORD_CLIENT_SECRET', requireEnv('DISCORD_CLIENT_SECRET'));
define('DISCORD_REDIRECT_URI', requireEnv('DISCORD_REDIRECT_URI'));

define('SITE_URL', envv('SITE_URL',''));
define('SITE_NAME', envv('SITE_NAME',''));

define('YOOKASSA_SHOP_ID', envv('YOOKASSA_SHOP_ID',''));
define('YOOKASSA_SECRET_KEY', envv('YOOKASSA_SECRET_KEY',''));
define('YOOKASSA_WEBHOOK_TOKEN', envv('YOOKASSA_WEBHOOK_TOKEN',''));

$__depost = strtolower((string)envv('DEPOST','true'));
define('DEPOST_ENABLED', in_array($__depost, ['1','true','yes','on'], true));

define('DISCORD_WEBHOOK_URL', envv('DISCORD_WEBHOOK_URL',''));

define('DISCORD_CONTACT_WEBHOOK_URL', envv('DISCORD_CONTACT_WEBHOOK_URL', ''));
define('DISCORD_ADMIN_WEBHOOK_URL', envv('DISCORD_ADMIN_WEBHOOK_URL', ''));

define(
    'DISCORD_TICKETS_WEBHOOK_URL',
    envv(
        'DISCORD_TICKETS_WEBHOOK_URL',
        (DISCORD_ADMIN_WEBHOOK_URL !== '' ? DISCORD_ADMIN_WEBHOOK_URL : (DISCORD_WEBHOOK_URL !== '' ? DISCORD_WEBHOOK_URL : DISCORD_CONTACT_WEBHOOK_URL))
    )
);

define('SECRET_KEY', requireEnv('APP_SECRET_KEY'));
define('ADMIN_PASSWORD_HASH', envv('ADMIN_PASSWORD_HASH',''));

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (Exception $ex) {
    http_response_code(500);
    exit;
}

function cryptoKey(): string {
    return hash('sha256', SECRET_KEY, true);
}

function encryptSecret(string $plaintext): string {
    $key = cryptoKey();
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) return '';
    return 'enc:' . base64_encode($iv . $tag . $cipher);
}

function decryptSecret(string $payload): string {
    if (strpos($payload, 'enc:') !== 0) return $payload;
    $raw = base64_decode(substr($payload, 4), true);
    if ($raw === false || strlen($raw) < 28) return '';
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', cryptoKey(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? '' : $plain;
}

function yookassaRequest(string $endpoint, string $method='GET', ?array $payload=null): array {
    $url = 'https://api.yookassa.ru/v3' . $endpoint;
    $ch = curl_init($url);
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Idempotence-Key: ' . bin2hex(random_bytes(16)),
        'Authorization: Basic ' . base64_encode(YOOKASSA_SHOP_ID . ':' . YOOKASSA_SECRET_KEY)
    ];
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ch = null;
    return ['code'=>(int)$code,'data'=>$resp?json_decode($resp,true):null];
}

function pterodactylRequest(string $endpoint, string $method='GET', ?array $payload=null): array {
    $url = rtrim(PANEL_URL, '/') . '/api/application' . $endpoint;
    $ch = curl_init($url);
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . PTERO_API_KEY
    ];

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_HEADER => true,
        CURLOPT_USERAGENT => 'AxionHosting/1.0 (+pterodactyl-api)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    if ($payload !== null) {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) $json = '{}';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }

    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $err = $errno ? curl_error($ch) : '';
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $ch = null;

    $rawStr = is_string($raw) ? $raw : '';
    $rawHeaders = $headerSize > 0 ? substr($rawStr, 0, $headerSize) : '';
    $body = $headerSize > 0 ? substr($rawStr, $headerSize) : $rawStr;
    $data = null;
    if ($body !== '') {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) $data = $decoded;
    }

    return [
        'code' => $code,
        'data' => $data,
        'raw_body' => $body,
        'raw_headers' => $rawHeaders,
        'effective_url' => $effectiveUrl,
        'curl_errno' => $errno,
        'curl_error' => $err,
    ];
}

function pterodactylErrorSummary(array $res): string {
    $code = (int)($res['code'] ?? 0);
    $errno = (int)($res['curl_errno'] ?? 0);
    $err = (string)($res['curl_error'] ?? '');
    $effective = (string)($res['effective_url'] ?? '');

    if ($errno !== 0) {
        return "Connection error (cURL {$errno}): {$err}";
    }

    if ($code === 301 || $code === 302 || $code === 307 || $code === 308) {
        return 'HTTP redirect received. Check PANEL_URL (use https and correct domain). Effective URL: ' . $effective;
    }
    if ($code === 401) return 'Unauthorized (401): API key is invalid or was revoked.';
    if ($code === 403) return 'Forbidden (403): API key has no permissions for this action.';
    if ($code === 404) return 'Not found (404): PANEL_URL is wrong or panel is not reachable from this host.';
    if ($code >= 500 && $code <= 599) return "Panel/server error ({$code}).";

    $data = $res['data'] ?? null;
    if (is_array($data) && !empty($data['errors']) && is_array($data['errors'])) {
        $first = $data['errors'][0] ?? null;
        if (is_array($first)) {
            $detail = (string)($first['detail'] ?? '');
            $status = (string)($first['status'] ?? '');
            if ($detail !== '') {
                return ($status !== '' ? "{$status}: " : '') . $detail;
            }
        }
    }

    return $code > 0 ? "Pterodactyl API error (HTTP {$code})." : 'Pterodactyl API error.';
}

function sanitizePterodactylUsername(string $username): string {
    $u = trim($username);
    $u = preg_replace('/\s+/', '_', $u) ?? $u;
    $u = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $u) ?? $u;
    $u = trim($u, '._-');
    if ($u === '') $u = 'user';
    if (strlen($u) < 4) $u = $u . str_pad('', 4 - strlen($u), '0');
    if (strlen($u) > 191) $u = substr($u, 0, 191);
    return $u;
}

function generateStrongPassword(int $len = 18): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*_-+=';
    $out = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}





function findPterodactylUserByEmail(string $email): ?array {
    $email = trim($email);
    if ($email === '') return null;

    $res = pterodactylRequest('/users?per_page=1&filter[email]=' . urlencode($email), 'GET');
    if ((int)($res['code'] ?? 0) !== 200) {
        return null;
    }

    $data = $res['data'] ?? null;
    if (!is_array($data) || empty($data['data'][0]['attributes']) || !is_array($data['data'][0]['attributes'])) {
        return null;
    }

    return $data['data'][0]['attributes'];
}

function updatePterodactylUserPassword(int $userId, string $username, string $email, string $firstName, string $lastName, string $newPassword): bool {
    if ($userId <= 0) return false;

    $payload = [
        'username' => $username,
        'email' => $email,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'password' => $newPassword,
        'language' => 'en',
    ];

    $res = pterodactylRequest('/users/' . $userId, 'PATCH', $payload);
    return (int)($res['code'] ?? 0) === 200;
}

function getOrCreatePterodactylUser(string $rawUsername, string $email, ?string $firstName=null, ?string $lastName=null): array {
    $baseUsername = sanitizePterodactylUsername($rawUsername);
    $email = trim($email);
    if ($email === '') {
        return ['success' => false, 'error' => 'Email is empty'];
    }

    $firstName = trim((string)$firstName);
    $lastName = trim((string)$lastName);

    $first = $firstName !== "" ? mb_substr($firstName, 0, 60) : mb_substr(($rawUsername !== "" ? $rawUsername : $baseUsername), 0, 60);
    $last  = $lastName !== "" ? mb_substr($lastName, 0, 60) : "User";


    $existing = findPterodactylUserByEmail($email);
    if ($existing && !empty($existing['id'])) {
        $userId = (int)$existing['id'];
        $username = (string)($existing['username'] ?? $baseUsername);

        $passwordPlain = generateStrongPassword(18);
        $updated = updatePterodactylUserPassword($userId, $username, $email, $first, $last, $passwordPlain);

        return [
            'success' => true,
            'user_id' => $userId,
            'username' => $username,
            'password' => $updated ? encryptSecret($passwordPlain) : '',
        ];
    }

    $attempts = 6;
    $passwordPlain = generateStrongPassword(18);

    for ($i = 0; $i < $attempts; $i++) {
        $username = $baseUsername;
        if ($i > 0) {
            $suffix = (string)random_int(10, 99) . (string)random_int(10, 99);
            $maxLen = 191 - strlen($suffix) - 1;
            if ($maxLen < 4) $maxLen = 4;
            $username = substr($baseUsername, 0, $maxLen) . '_' . $suffix;
        }

        $payload = [
            'username' => $username,
            'email' => $email,
            'first_name' => $first,
            'last_name' => $last,
            'password' => $passwordPlain,
            'language' => 'en',
        ];

        $res = pterodactylRequest('/users', 'POST', $payload);

        if ((int)($res['code'] ?? 0) === 201 && !empty($res['data']['attributes']['id'])) {
            $userId = (int)$res['data']['attributes']['id'];
            return [
                'success' => true,
                'user_id' => $userId,
                'username' => $username,
                'password' => encryptSecret($passwordPlain),
            ];
        }

        if (in_array((int)($res['code'] ?? 0), [400, 409, 422], true)) {
            $errors = $res['data']['errors'] ?? [];
            $combined = json_encode($errors, JSON_UNESCAPED_UNICODE);
            if (is_string($combined) && (stripos($combined, 'username') !== false)) {
                continue;
            }
        }

        $maybe = findPterodactylUserByEmail($email);
        if ($maybe && !empty($maybe['id'])) {
            return [
                'success' => true,
                'user_id' => (int)$maybe['id'],
                'username' => (string)($maybe['username'] ?? $username),
                'password' => encryptSecret($passwordPlain),
            ];
        }

        $summary = pterodactylErrorSummary($res);
        $log = [
            'code' => (int)($res['code'] ?? 0),
            'effective_url' => (string)($res['effective_url'] ?? ''),
            'curl_errno' => (int)($res['curl_errno'] ?? 0),
            'curl_error' => (string)($res['curl_error'] ?? ''),
            'summary' => $summary,
        ];
        $bodySnippet = (string)($res['raw_body'] ?? '');
        if ($bodySnippet !== '') {
            $log['body_snippet'] = mb_substr($bodySnippet, 0, 800);
        }
        error_log('[PTERO] create user failed: ' . json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return ['success' => false, 'error' => $summary, 'code' => (int)($res['code'] ?? 0)];
    }

    return ['success' => false, 'error' => 'Could not find a free username'];
}

function registerUserInPterodactyl(string $rawUsername, string $email, ?string $firstName=null, ?string $lastName=null): array {
    return getOrCreatePterodactylUser($rawUsername, $email, $firstName, $lastName);
}
function isLoggedIn(): bool {
    startSecureSession();
    return !empty($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    global $pdo;
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $u = $stmt->fetch();
    if (!$u) return null;
    if (!empty($u['pterodactyl_password'])) {
        $u['pterodactyl_password'] = decryptSecret((string)$u['pterodactyl_password']);
    }
    return $u;
}

function initDatabase(): void {
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        discord_id VARCHAR(255) UNIQUE NOT NULL,
        discord_username VARCHAR(255) NOT NULL,
        discord_avatar VARCHAR(255),
        email VARCHAR(255),
        balance DECIMAL(10,2) DEFAULT 0.00,
        inviter VARCHAR(255) DEFAULT NULL,
        deposits INT DEFAULT 0,
        referral_earned DECIMAL(10,2) DEFAULT 0.00,
        pterodactyl_id INT,
        pterodactyl_username VARCHAR(255),
        pterodactyl_password VARCHAR(512),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try {
        $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll();
        $have = [];
        foreach ($cols as $c) {
            if (!empty($c['Field'])) $have[(string)$c['Field']] = true;
        }
        if (empty($have['inviter'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN inviter VARCHAR(255) DEFAULT NULL AFTER balance");
        }
        if (empty($have['deposits'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN deposits INT DEFAULT 0 AFTER inviter");
        }
        if (empty($have['referral_earned'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN referral_earned DECIMAL(10,2) DEFAULT 0.00 AFTER deposits");
        }
        if (empty($have['first_name'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN first_name VARCHAR(255) DEFAULT NULL AFTER discord_username");
        }
        if (empty($have['last_name'])) {
            $pdo->exec("ALTER TABLE users ADD COLUMN last_name VARCHAR(255) DEFAULT NULL AFTER first_name");
        }
    } catch (Exception $e) {
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS global_banner (
        id TINYINT NOT NULL PRIMARY KEY,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        title VARCHAR(255) DEFAULT NULL,
        body TEXT DEFAULT NULL,
        button_text VARCHAR(64) DEFAULT NULL,
        button_url VARCHAR(512) DEFAULT NULL,
        image_path VARCHAR(512) DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try {
        $pdo->exec("INSERT IGNORE INTO global_banner (id, enabled) VALUES (1, 0)");
    } catch (Exception $e) {
    }
}


function discordWebhookPost(string $url, array $payload): void {
    if ($url === '') return;
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 3
    ]);
    @curl_exec($ch);
    $ch = null;
}

function discordWebhookTickets(array $payload): void {
    discordWebhookPost((string)DISCORD_TICKETS_WEBHOOK_URL, $payload);
}

initDatabase();
