<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function load_env(string $path): array {
    if (!file_exists($path)) {
        respond(500, ["error" => "ENV file not found", "env_path" => $path]);
    }
    if (!is_readable($path)) {
        respond(500, ["error" => "ENV file is not readable", "env_path" => $path]);
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        respond(500, ["error" => "ENV file is empty", "env_path" => $path]);
    }

    $env = [];
    $lines = preg_split("/\r\n|\n|\r/", $raw);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;

        $pos = strpos($line, '=');
        if ($pos === false) continue;

        $k = trim(substr($line, 0, $pos));
        $v = trim(substr($line, $pos + 1));

        if ((str_starts_with($v, '"') && str_ends_with($v, '"')) ||
            (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
            $v = substr($v, 1, -1);
        }

        $env[$k] = $v;
    }
    return $env;
}

$envPath = __DIR__ . '/.env';
$env = load_env($envPath);

$apiPass = $env['API_PASS'] ?? '';
if ($apiPass === '') {
    respond(500, ["error" => "API_PASS not set"]);
}

$key = $_GET['key'] ?? '';
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($key === '' && preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
    $key = trim($m[1]);
}
if ($key === '' || !hash_equals($apiPass, $key)) {
    respond(403, ["error" => "Access denied"]);
}

$dbHost = $env['DB_HOST'] ?? '';
$dbName = $env['DB_NAME'] ?? '';
$dbUser = $env['DB_USER'] ?? '';
$dbPass = $env['DB_PASS'] ?? '';

if ($dbHost === '' || $dbName === '' || $dbUser === '') {
    respond(500, ["error" => "DB_* not set"]);
}

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    respond(500, ["error" => "DB connection failed", "details" => $e->getMessage()]);
}

const USERS_TABLE = 'users';

$SAFE_USER_FIELDS = [
    'id',
    'discord_id',
    'discord_username',
    'first_name',
    'last_name',
    'discord_avatar',
    'telegram_id',
    'auth_provider',
    'email',
    'balance',
    'inviter',
    'pterodactyl_id',
    'pterodactyl_username',
    'created_at',
    'last_login',
    'deposits',
    'referral_earned',
    'referral_code',
    'inviter_id',
    'referral_earnings',
    'referral_count',
];

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {

    if ($action === 'user') {
        $filters = [
            'id' => isset($_GET['id']) ? (int)$_GET['id'] : null,
            'discord_id' => isset($_GET['discord_id']) ? trim((string)$_GET['discord_id']) : null,
            'discord_username' => isset($_GET['discord_username']) ? trim((string)$_GET['discord_username']) : null,
            'email' => isset($_GET['email']) ? trim((string)$_GET['email']) : null,
            'telegram_id' => isset($_GET['telegram_id']) ? trim((string)$_GET['telegram_id']) : null,
            'pterodactyl_username' => isset($_GET['pterodactyl_username']) ? trim((string)$_GET['pterodactyl_username']) : null,
        ];

        $where = null;
        $val = null;

        foreach ($filters as $k => $v) {
            if ($v !== null && $v !== '') {
                $where = $k;
                $val = $v;
                break;
            }
        }

        if ($where === null) {
            respond(400, ["error" => "Provide one of: id, discord_id, discord_username, email, telegram_id, pterodactyl_username"]);
        }

        $fieldsSql = implode(', ', array_map(fn($f) => "`$f`", $SAFE_USER_FIELDS));

        $stmt = $pdo->prepare("SELECT {$fieldsSql} FROM `" . USERS_TABLE . "` WHERE `{$where}` = ? LIMIT 1");
        $stmt->execute([$val]);
        $user = $stmt->fetch();

        respond(200, ["status" => "ok", "user" => $user ?: null]);
    }

    if ($action === 'top') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        if ($limit < 1) $limit = 1;
        if ($limit > 100) $limit = 100;

        $stmt = $pdo->prepare(
            "SELECT `id`, `discord_id`, `discord_username`, `balance`
             FROM `" . USERS_TABLE . "`
             ORDER BY `balance` DESC
             LIMIT {$limit}"
        );
        $stmt->execute();

        respond(200, ["status" => "ok", "users" => $stmt->fetchAll()]);
    }

    if ($action === 'balance_add' || $action === 'balance_set') {
        $identifierValue = null;
        $amount = null;

        if ($method === 'GET') {
            $identifierValue = isset($_GET['discord_username']) ? trim((string)$_GET['discord_username']) : null;
            $amount = $_GET['amount'] ?? null;
        } elseif ($method === 'POST') {
            $raw = file_get_contents('php://input');
            $payload = json_decode($raw, true);

            if (is_array($payload)) {
                $identifierValue = isset($payload['discord_username']) ? trim((string)$payload['discord_username']) : null;
                $amount = $payload['amount'] ?? null;
            }
        }

        if ($identifierValue === null || $identifierValue === '') {
            $message = ($method === 'GET')
                ? "Provide discord_username: ?action={$action}&discord_username=...&amount=..."
                : "Provide discord_username in JSON: {\"discord_username\":\"...\",\"amount\":...}";
            respond(400, ["error" => $message]);
        }

        if ($amount === null || !is_numeric($amount)) {
            respond(400, ["error" => "amount required (number)"]);
        }

        $amount = (float)$amount;

        if ($action === 'balance_set') {
            $stmt = $pdo->prepare(
                "UPDATE `" . USERS_TABLE . "`
                 SET `balance` = ?
                 WHERE `discord_username` = ?"
            );
            $stmt->execute([$amount, $identifierValue]);
        } else {
            $stmt = $pdo->prepare(
                "UPDATE `" . USERS_TABLE . "`
                 SET `balance` = `balance` + ?,
                     `deposits` = COALESCE(`deposits`, 0) + 1
                 WHERE `discord_username` = ?"
            );
            $stmt->execute([$amount, $identifierValue]);
        }

        $stmtCheck = $pdo->prepare(
            "SELECT `balance`, `deposits`
             FROM `" . USERS_TABLE . "`
             WHERE `discord_username` = ?
             LIMIT 1"
        );
        $stmtCheck->execute([$identifierValue]);
        $row = $stmtCheck->fetch();

        respond(200, [
            "status" => "ok",
            "affected" => $stmt->rowCount(),
            "new_balance" => $row['balance'] ?? null,
            "deposits" => $row['deposits'] ?? null,
            "identifier" => [
                "type" => "discord_username",
                "value" => $identifierValue
            ]
        ]);
    }

    respond(400, ["error" => "Unknown action"]);

} catch (Throwable $e) {
    respond(500, ["error" => "Server error", "details" => $e->getMessage()]);
}
