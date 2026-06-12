<?php
require_once 'config.php';

$promoPath = __DIR__ . '/promo.php';
if (is_file($promoPath)) {
    require_once $promoPath;
}

header('Content-Type: application/json');

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8', true, 200);
        }
        $msg = 'Внутренняя ошибка сервера';
        error_log('API fatal: ' . ($err['message'] ?? '') . ' in ' . ($err['file'] ?? '') . ':' . ($err['line'] ?? 0));
        echo json_encode(['success' => false, 'message' => $msg]);
    }
});

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? ($data['action'] ?? '');
if ($action === 'csrf') { echo json_encode(['csrf_token'=>csrfToken()]); exit; }
$mutating = ['create_deposit','apply_promo','order_server','delete_server','renew_server','create_ticket','send_ticket_message'];
if (in_array($action, $mutating, true)) { requireCsrf(); }

if ($action === 'session') {
    if (!isLoggedIn()) {
        echo json_encode(['logged_in' => false]);
        exit;
    }

    $u = getCurrentUser();
    echo json_encode([
        'logged_in' => true,
        'username' => $u['discord_username'] ?? null,
        'avatar' => $u['discord_avatar'] ?? null,
        'dashboard_url' => 'dashboard.php'
    ]);
    exit;
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit;
}

$user = getCurrentUser();


function promoModuleAvailable(): bool {
    return function_exists('fetchPromoByCode')
        && function_exists('redeemGrantPromo')
        && function_exists('evaluatePromoForUser')
        && function_exists('normalizePromoCode');
}

function promoModuleUnavailable(): void {
    echo json_encode(['success' => false, 'message' => 'Промокоды недоступны']);
}

switch ($action) {
    case 'create_deposit':
        createDeposit($user, $data);
        break;

    case 'apply_promo':
        applyPromo($user, $data);
        break;

    case 'order_server':
        orderServer($user, $data);
        break;

    case 'renew_server':
        renewServer($user, $data);
        break;

    case 'delete_server':
        deleteServer($user, $data);
        break;

    case 'create_ticket':
        createTicket($user, $data);
        break;

    case 'list_tickets':
        listTickets($user);
        break;

    case 'get_ticket':
        getTicket($user, $data);
        break;

    case 'send_ticket_message':
        sendTicketMessage($user, $data);
        break;

    case 'transaction_status':
        transactionStatus($user, $data);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Неизвестное действие']);
}

function transactionStatus($user, $data) {
    global $pdo;
    $txId = (int)($data['transaction_id'] ?? 0);
    if ($txId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Некорректная транзакция']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id, user_id, amount, type, status, created_at FROM transactions WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$txId, (int)$user['id']]);
    $tx = $stmt->fetch();
    if (!$tx) {
        echo json_encode(['success' => false, 'message' => 'Транзакция не найдена']);
        return;
    }

    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$user['id']]);
    $bal = (float)($stmt->fetch()['balance'] ?? 0);

    echo json_encode([
        'success' => true,
        'transaction' => [
            'id' => (int)$tx['id'],
            'amount' => (float)$tx['amount'],
            'type' => (string)$tx['type'],
            'status' => (string)$tx['status'],
            'created_at' => (string)$tx['created_at'],
        ],
        'balance' => $bal
    ]);
}

function createTicket($user, $data) {
    global $pdo;
    $subject = trim((string)($data['subject'] ?? ''));
    $message = trim((string)($data['message'] ?? ''));

    if ($subject === '' || mb_strlen($subject) < 3) {
        echo json_encode(['success' => false, 'message' => 'Тема тикета слишком короткая']);
        return;
    }
    if ($message === '' || mb_strlen($message) < 5) {
        echo json_encode(['success' => false, 'message' => 'Сообщение слишком короткое']);
        return;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO tickets (user_id, subject, status) VALUES (?, ?, 'open')");
        $stmt->execute([$user['id'], $subject]);
        $ticketId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender, message) VALUES (?, 'user', ?)");
        $stmt->execute([$ticketId, $message]);

        $pdo->commit();

        try {
            discordWebhookTickets([
                'username' => 'Axion Support',
                'embeds' => [[
                    'title' => '🆕 Новый тикет поддержки',
                    'color' => 0xFFD700,
                    'fields' => [
                        ['name' => 'Тикет', 'value' => '#' . $ticketId, 'inline' => true],
                        ['name' => 'Пользователь', 'value' => (string)($user['discord_username'] ?? 'unknown') . ' (ID: ' . (string)($user['discord_id'] ?? $user['id'] ?? '') . ')', 'inline' => false],
                        ['name' => 'Тема', 'value' => mb_substr($subject, 0, 256), 'inline' => false],
                        ['name' => 'Сообщение', 'value' => mb_substr($message, 0, 1000), 'inline' => false],
                    ],
                    'timestamp' => gmdate('c')
                ]]
            ]);
        } catch (Exception $ignore) {}
        echo json_encode(['success' => true, 'ticket_id' => $ticketId]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function listTickets($user) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, subject, status, created_at, updated_at FROM tickets WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$user['id']]);
    echo json_encode(['success' => true, 'tickets' => $stmt->fetchAll()]);
}

function getTicket($user, $data) {
    global $pdo;
    $ticketId = (int)($data['ticket_id'] ?? 0);
    if ($ticketId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Некорректный тикет']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ? AND user_id = ?");
    $stmt->execute([$ticketId, $user['id']]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Тикет не найден']);
        return;
    }

    $stmt = $pdo->prepare("SELECT sender, message, created_at FROM ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC");
    $stmt->execute([$ticketId]);
    $messages = $stmt->fetchAll();

    echo json_encode(['success' => true, 'ticket' => $ticket, 'messages' => $messages]);
}

function sendTicketMessage($user, $data) {
    global $pdo;
    $ticketId = (int)($data['ticket_id'] ?? 0);
    $message = trim((string)($data['message'] ?? ''));
    if ($ticketId <= 0 || $message === '') {
        echo json_encode(['success' => false, 'message' => 'Некорректные данные']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ? AND user_id = ?");
    $stmt->execute([$ticketId, $user['id']]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Тикет не найден']);
        return;
    }
    if ($ticket['status'] === 'closed') {
        echo json_encode(['success' => false, 'message' => 'Тикет закрыт']);
        return;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender, message) VALUES (?, 'user', ?)");
        $stmt->execute([$ticketId, $message]);
        $stmt = $pdo->prepare("UPDATE tickets SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$ticketId]);

        try {
            discordWebhookTickets([
                'username' => 'Axion Support',
                'embeds' => [[
                    'title' => '💬 Новое сообщение в тикете',
                    'color' => 0x4CAF50,
                    'fields' => [
                        ['name' => 'Тикет', 'value' => '#' . $ticketId, 'inline' => true],
                        ['name' => 'Пользователь', 'value' => (string)($user['discord_username'] ?? 'unknown') . ' (ID: ' . (string)($user['discord_id'] ?? $user['id'] ?? '') . ')', 'inline' => false],
                        ['name' => 'Тема', 'value' => mb_substr((string)($ticket['subject'] ?? ''), 0, 256), 'inline' => false],
                        ['name' => 'Сообщение', 'value' => mb_substr($message, 0, 1000), 'inline' => false],
                    ],
                    'timestamp' => gmdate('c')
                ]]
            ]);
        } catch (Exception $ignore) {}

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function applyPromo($user, $data) {
    global $pdo;
    $code = trim((string)($data['promo_code'] ?? ''));
    $amount = floatval($data['amount'] ?? 0);

    if ($code === '') {
        echo json_encode(['success' => false, 'message' => 'Введите промокод']);
        return;
    }

    if (!promoModuleAvailable()) {
        promoModuleUnavailable();
        return;
    }

    try {
        $promo = fetchPromoByCode($pdo, $code);
        if (!$promo) {
            echo json_encode(['success' => false, 'message' => 'Промокод не найден']);
            return;
        }

        if ((string)($promo['type'] ?? '') === 'grant_amount') {
            $credited = redeemGrantPromo($pdo, $promo, $user);
            $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$user['id']]);
            $balance = (float)($stmt->fetchColumn() ?? 0);

            echo json_encode([
                'success' => true,
                'kind' => 'grant',
                'credited' => $credited,
                'balance' => $balance,
                'message' => 'Промокод применен'
            ]);
            return;
        }

        $eval = evaluatePromoForUser($pdo, $promo, $user, $amount, null);
        if (empty($eval['ok'])) {
            echo json_encode(['success' => false, 'message' => $eval['message'] ?? 'Промокод недоступен']);
            return;
        }

        $bonus = (float)($eval['bonus_amount'] ?? 0.0);
        $percent = (float)($eval['percent'] ?? 0.0);
        $total = round($amount + $bonus, 2);

        echo json_encode([
            'success' => true,
            'kind' => 'percent',
            'percent' => $percent,
            'bonus' => $bonus,
            'total' => $total,
            'message' => 'Промокод применен'
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Ошибка сервера: ' . $e->getMessage()]);
    }
}

function createDeposit($user, $data) {
    global $pdo;

    $amount = floatval($data['amount'] ?? 0);
    $email = trim($data['email'] ?? '');
    $promoCode = trim((string)($data['promo_code'] ?? ''));

    if ($amount < 10) {
        echo json_encode(['success' => false, 'message' => 'Минимальная сумма пополнения 10₽']);
        return;
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Введите корректный email адрес']);
        return;
    }

    if ($promoCode !== '' && !promoModuleAvailable()) {
        promoModuleUnavailable();
        return;
    }

    try {

        if ($promoCode !== '') {
            $promo = fetchPromoByCode($pdo, $promoCode);
            if ($promo && (string)($promo['type'] ?? '') === 'grant_amount') {
                $credited = redeemGrantPromo($pdo, $promo, $user);
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([(int)$user['id']]);
                $balance = (float)($stmt->fetchColumn() ?? 0);
                echo json_encode([
                    'success' => true,
                    'promo_grant' => true,
                    'credited' => $credited,
                    'balance' => $balance,
                    'message' => 'Промокод применен, начисление выполнено'
                ]);
                return;
            }
        }

        $description = "Пополнение баланса на сумму {$amount}₽";

        $stmtCheck = $pdo->prepare("SHOW COLUMNS FROM transactions LIKE 'metadata'");
        $stmtCheck->execute();
        $hasMetadata = $stmtCheck->fetch();

        if ($hasMetadata) {

            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description, status, metadata) VALUES (?, ?, 'deposit', ?, 'pending', ?)");
            $metaArr = ['email' => $email];
            if ($promoCode !== '') {
                $metaArr['promo_code'] = normalizePromoCode($promoCode);
            }
            $metadata = json_encode($metaArr, JSON_UNESCAPED_UNICODE);
            $stmt->execute([$user['id'], $amount, $description, $metadata]);
        } else {

            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description, status) VALUES (?, ?, 'deposit', ?, 'pending')");
            $desc = $description;
            if ($promoCode !== '') {
                $desc .= ' [PROMO:' . normalizePromoCode($promoCode) . ']';
            }
            $stmt->execute([$user['id'], $amount, $desc]);
        }

        $transactionId = (int)$pdo->lastInsertId();

        $apiKey = envv('ENOT_API_KEY', '');
        $shopId = envv('ENOT_SHOP_ID', '');
        $currency = envv('ENOT_CURRENCY', 'RUB');

        if ($apiKey === '' || $shopId === '') {
            $stmt = $pdo->prepare("UPDATE transactions SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$transactionId]);
            echo json_encode(['success' => false, 'message' => 'Оплата не настроена: заполните ENOT_API_KEY и ENOT_SHOP_ID в .env']);
            return;
        }

        $hookUrl = envv('ENOT_HOOK_URL', rtrim(SITE_URL, '/') . '/enot/webhook.php');
        $successUrl = envv('ENOT_SUCCESS_URL', rtrim(SITE_URL, '/') . '/enot/success.php');
        $failUrl = envv('ENOT_FAIL_URL', rtrim(SITE_URL, '/') . '/enot/fail.php');

        $invoiceData = [
            'amount' => (float)$amount,
            'order_id' => (string)$transactionId,
            'email' => (string)$email,
            'currency' => (string)$currency,
            'comment' => $description,
            'fail_url' => $failUrl,
            'success_url' => $successUrl,
            'hook_url' => $hookUrl,
            'shop_id' => (string)$shopId,
            'custom_fields' => ['user_id' => (int)$user['id']],
            'expire' => (int)envv('ENOT_EXPIRE_MIN', '300'),
        ];

        $ch = curl_init('https://api.enot.io/invoice/create');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'content-type: application/json',
                'x-api-key: ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($invoiceData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $resp === '' || $httpCode < 200 || $httpCode >= 300) {
            $stmt = $pdo->prepare("UPDATE transactions SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$transactionId]);
            $msg = 'Ошибка создания инвойса ENOT.io';
            if ($curlErr) $msg .= ': ' . $curlErr;
            echo json_encode(['success' => false, 'message' => $msg]);
            return;
        }

        $data = json_decode($resp, true);
        $payUrl = $data['data']['url'] ?? '';
        $invoiceId = $data['data']['id'] ?? '';

        if (!is_string($payUrl) || $payUrl === '') {
            $stmt = $pdo->prepare("UPDATE transactions SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$transactionId]);
            echo json_encode(['success' => false, 'message' => 'ENOT.io вернул некорректный ответ (нет url)']);
            return;
        }

        try {
            $stmtCheck = $pdo->prepare("SHOW COLUMNS FROM transactions LIKE 'external_id'");
            $stmtCheck->execute();
            $hasExternal = (bool)$stmtCheck->fetch();
            if ($hasExternal && is_string($invoiceId) && $invoiceId !== '') {
                $stmt = $pdo->prepare("UPDATE transactions SET external_id = ? WHERE id = ?");
                $stmt->execute([$invoiceId, $transactionId]);
            }
        } catch (Throwable $e) {
        }

        echo json_encode([
            'success' => true,
            'payment_url' => $payUrl,
            'transaction_id' => $transactionId
        ]);

    } catch (Exception $e) {
        error_log('createDeposit exception: ' . $e->getMessage());
        error_log('Trace: ' . $e->getTraceAsString());
        echo json_encode(['success' => false, 'message' => 'Ошибка сервера при создании платежа: ' . $e->getMessage()]);
    }
}

function sendDepositWebhookToDiscord($amount, $email, $user) {
    $webhookUrl = defined('DISCORD_WEBHOOK_URL') ? DISCORD_WEBHOOK_URL : '';
    if ($webhookUrl === '') return false;

    $embed = [
        "title" => "💰 Новое пополнение баланса",
        "color" => 65280, // Зеленый цвет
        "fields" => [
            [
                "name" => "👤 Пользователь",
                "value" => "{$user['discord_username']} ({$user['discord_id']})",
                "inline" => true
            ],
            [
                "name" => "💳 Сумма",
                "value" => "{$amount}₽",
                "inline" => true
            ],
            [
                "name" => "📧 Email",
                "value" => $email,
                "inline" => true
            ],
            [
                "name" => "🆔 User ID",
                "value" => $user['id'],
                "inline" => true
            ],
            [
                "name" => "💎 Баланс",
                "value" => number_format($user['balance'], 2) . "₽",
                "inline" => true
            ],
            [
                "name" => "📅 Дата",
                "value" => date('d.m.Y H:i:s'),
                "inline" => true
            ]
        ],
        "footer" => [
            "text" => "Axion Hosting | Система уведомлений"
        ],
        "timestamp" => date('c')
    ];

    $payload = [
        "embeds" => [$embed],
        "username" => "Axion Payment Bot",
        "avatar_url" => "https://cdn.discordapp.com/avatars/1460348678463033346/abf3e8e4f7a2d3c4b5e6f7a8b9c0d1e2.png"
    ];

    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    @curl_exec($ch);
    $ch = null;

    return true;
}

function orderServer($user, $data) {
    global $pdo;

    $planId = intval($data['plan_id'] ?? 0);
    $serverType = strtolower(trim((string)($data['server_type'] ?? 'minecraft')));
    if (!in_array($serverType, ['minecraft', 'coding'], true)) {
        $serverType = 'minecraft';
    }

    $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ?");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();

    if (!$plan) {
        echo json_encode(['success' => false, 'message' => 'Тариф не найден']);
        return;
    }

    if ($user['balance'] < $plan['price']) {
        echo json_encode(['success' => false, 'message' => 'Недостаточно средств на балансе']);
        return;
    }

    try {
        $pdo->beginTransaction();

        if (empty($user['pterodactyl_id']) || empty($user['pterodactyl_username']) || empty($user['pterodactyl_password'])) {

            $email = $user['email'] ?? ($user['discord_id'] . '@discord.user');
            $registration = registerUserInPterodactyl($user['discord_username'], $email, ($user['first_name'] ?? ''), ($user['last_name'] ?? ''));

            if (!$registration['success']) {
                throw new Exception('Не удалось зарегистрировать пользователя в панели управления: ' . $registration['error']);
            }

            $stmt = $pdo->prepare("
                UPDATE users
                SET pterodactyl_id = ?, pterodactyl_username = ?, pterodactyl_password = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $registration['user_id'],
                $registration['username'],
                $registration['password'],
                $user['id']
            ]);

            $user['pterodactyl_id'] = $registration['user_id'];
            $user['pterodactyl_username'] = $registration['username'];
            $user['pterodactyl_password'] = $registration['password'];
        }

        $serverData = createPterodactylServer($user, $plan, $serverType);

        if ($serverData === false) {
            throw new Exception('Не удалось создать сервер в панели управления');
        }

        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$plan['price'], $user['id']]);

        $stmt = $pdo->prepare("
            INSERT INTO servers (user_id, pterodactyl_server_id, plan_name, plan_price, cpu, ram, disk, next_payment_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
        ");

        $stmt->execute([
            $user['id'],
            $serverData['id'],
            $plan['name'],
            $plan['price'],
            $plan['cpu'],
            $plan['ram'],
            $plan['disk']
        ]);

        $stmt = $pdo->prepare("
            INSERT INTO transactions (user_id, amount, type, description, status)
            VALUES (?, ?, 'purchase', ?, 'completed')
        ");

        $description = "Покупка сервера {$plan['name']}";
        $stmt->execute([$user['id'], $plan['price'], $description]);

        $pdo->commit();

        $webhookUrl = defined('DISCORD_WEBHOOK_URL') ? DISCORD_WEBHOOK_URL : '';
        if ($webhookUrl !== '') {

        $embedData = [
            'embeds' => [[
                'title' => '🎉 Новый заказ сервера',
                'color' => 0x4CAF50,
                'fields' => [
                    [
                        'name' => 'Пользователь',
                        'value' => $user['discord_username'],
                        'inline' => true
                    ],
                    [
                        'name' => 'Тариф',
                        'value' => $plan['name'],
                        'inline' => true
                    ],
                    [
                        'name' => 'Характеристики',
                        'value' => "CPU: {$plan['cpu']}%\nRAM: " . ($plan['ram']/1024) . "GB\nSSD: " . ($plan['disk']/1024) . "GB",
                        'inline' => false
                    ],
                    [
                        'name' => 'ID сервера',
                        'value' => $serverData['id'],
                        'inline' => true
                    ]
                ],
                'footer' => [
                    'text' => 'Axion Hosting'
                ],
                'timestamp' => date('c')
            ]]
        ];

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($embedData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        }

        echo json_encode([
            'success' => true,
            'server_id' => $serverData['id']
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function renewServer($user, $data) {
    global $pdo;

    $serverId = (int)($data['server_id'] ?? 0);
    if ($serverId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Некорректный сервер']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM servers WHERE id = ? AND user_id = ?");
    $stmt->execute([$serverId, $user['id']]);
    $server = $stmt->fetch();

    if (!$server) {
        echo json_encode(['success' => false, 'message' => 'Сервер не найден']);
        return;
    }

    $price = (float)($server['plan_price'] ?? 0);
    if ($price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Не удалось определить стоимость тарифа']);
        return;
    }

    $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $balanceRow = $stmt->fetch();
    $balance = $balanceRow ? (float)$balanceRow['balance'] : (float)$user['balance'];

    if ($balance < $price) {
        echo json_encode(['success' => false, 'message' => 'Недостаточно средств на балансе']);
        return;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$price, $user['id']]);

        $stmt = $pdo->prepare("
            UPDATE servers
            SET next_payment_date = DATE_ADD(
                IF(next_payment_date > NOW(), next_payment_date, NOW()),
                INTERVAL 30 DAY
            )
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$serverId, $user['id']]);

        $planName = (string)($server['plan_name'] ?? 'Тариф');
        $desc = "Продление сервера {$planName} (+30 дней)";
        $stmt = $pdo->prepare("
            INSERT INTO transactions (user_id, amount, type, description, status)
            VALUES (?, ?, 'renew', ?, 'completed')
        ");
        $stmt->execute([$user['id'], $price, $desc]);

        $pdo->commit();

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        try { $pdo->rollBack(); } catch (Exception $e2) {}
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteServer($user, $data) {
    global $pdo;

    $serverId = intval($data['server_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM servers WHERE id = ? AND user_id = ?");
    $stmt->execute([$serverId, $user['id']]);
    $server = $stmt->fetch();

    if (!$server) {
        echo json_encode(['success' => false, 'message' => 'Сервер не найден']);
        return;
    }

    try {

        $result = pterodactylRequest('/servers/' . $server['pterodactyl_server_id'], 'DELETE');

        $stmt = $pdo->prepare("DELETE FROM servers WHERE id = ?");
        $stmt->execute([$serverId]);

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function createPterodactylServer($user, $plan, $serverType = 'minecraft') {

    if (empty($user['pterodactyl_id'])) {
        throw new Exception('Пользователь не зарегистрирован в панели управления');
    }

    $freePort = getFreeAllocation(1);

    if (!$freePort) {
        throw new Exception('Нет свободных портов для создания сервера');
    }

    $serverName = substr($plan['name'] . '_' . $user['discord_username'], 0, 100);

    $eggId = 4;
    $nestId = 1;
    $dockerImage = 'ghcr.io/pterodactyl/yolks:java_21';
    $startup = 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}';
    $environment = [
        'SERVER_JARFILE' => 'server.jar',
        'VANILLA_VERSION' => 'latest',
        'BUILD_NUMBER' => 'latest'
    ];

    if ($serverType === 'coding') {
        $nestId = getNestIdByName('Custom Egg');
        if (!$nestId) {
            throw new Exception('Не найден Nest "Custom Egg" в Pterodactyl');
        }
        $eggId = getEggIdByName($nestId, 'python generic');
        if (!$eggId) {
            throw new Exception('Не найден Egg "python generic" в Nest "Custom Egg"');
        }

        $tpl = getEggTemplate($nestId, $eggId);
        $dockerImage = $tpl['docker_image'];
        $startup = $tpl['startup'];
        $environment = $tpl['environment'];

        $requiredDefaults = [

            'USER_UPLOAD' => '1',            // 1 = user uploads files (skip install script)
            'AUTO_UPDATE' => '0',
            'PY_FILE' => 'app.py',
            'REQUIREMENTS_FILE' => 'requirements.txt'
        ];
        foreach ($requiredDefaults as $k => $v) {
            if (!isset($environment[$k]) || $environment[$k] === '') {
                $environment[$k] = $v;
            }
        }
    }

    $serverData = [
        'name' => $serverName,
        'user' => intval($user['pterodactyl_id']),
        'egg' => $eggId,
        'nest' => $nestId,
        'docker_image' => $dockerImage,
        'startup' => $startup,
        'environment' => $environment,
        'limits' => [
            'memory' => intval($plan['ram']),
            'swap' => 0,
            'disk' => intval($plan['disk']),
            'io' => 500,
            'cpu' => intval($plan['cpu'])
        ],
        'feature_limits' => [
            'databases' => 2,
            'backups' => 3
        ],
        'allocation' => [
            'default' => intval($freePort['id']),
            'additional' => []
        ],
        'node' => 1,
        'location' => 1
    ];

    error_log("Создание сервера в Pterodactyl: " . json_encode($serverData));

    $result = pterodactylRequest('/servers', 'POST', $serverData);

    error_log("Ответ при создании сервера: " . json_encode($result));

    if ($result['code'] === 201 || $result['code'] === 200) {
        return $result['data']['attributes'];
    }

    throw new Exception('Ошибка Pterodactyl API: ' . ($result['data']['errors'][0]['detail'] ?? json_encode($result['data'])));
}


function getFreeAllocation(int $nodeId = 1): ?array {
    $page = 1;
    $perPage = 100;
    for ($i = 0; $i < 10; $i++) {
        $endpoint = '/nodes/' . $nodeId . '/allocations?page=' . $page . '&per_page=' . $perPage;
        $res = pterodactylRequest($endpoint, 'GET');
        if ((int)($res['code'] ?? 0) !== 200 || empty($res['data']['data']) || !is_array($res['data']['data'])) {
            return null;
        }
        foreach ($res['data']['data'] as $row) {
            $attrs = $row['attributes'] ?? [];
            $assigned = $attrs['assigned'] ?? null;
            $isAssigned = ($assigned === true || $assigned === 1 || $assigned === '1');
            if (!$isAssigned && !empty($attrs['id'])) {
                return [
                    'id' => (int)$attrs['id'],
                    'ip' => (string)($attrs['ip'] ?? ''),
                    'port' => (int)($attrs['port'] ?? 0)
                ];
            }
        }
        $meta = $res['data']['meta']['pagination'] ?? null;
        $totalPages = is_array($meta) ? (int)($meta['total_pages'] ?? 0) : 0;
        if ($totalPages > 0 && $page >= $totalPages) {
            return null;
        }
        $page++;
    }
    return null;
}

function getNestIdByName($nestName) {
    $result = pterodactylRequest('/nests');
    if ($result['code'] !== 200 || empty($result['data']['data'])) {
        return null;
    }
    foreach ($result['data']['data'] as $item) {
        $attrs = $item['attributes'] ?? [];
        if (isset($attrs['name']) && mb_strtolower($attrs['name']) === mb_strtolower($nestName)) {
            return (int)($attrs['id'] ?? 0);
        }
    }
    return null;
}

function getEggIdByName($nestId, $eggName) {
    $result = pterodactylRequest('/nests/' . intval($nestId) . '/eggs');
    if ($result['code'] !== 200 || empty($result['data']['data'])) {
        return null;
    }
    foreach ($result['data']['data'] as $item) {
        $attrs = $item['attributes'] ?? [];
        if (isset($attrs['name']) && mb_strtolower($attrs['name']) === mb_strtolower($eggName)) {
            return (int)($attrs['id'] ?? 0);
        }
    }
    return null;
}

function getEggTemplate($nestId, $eggId) {
    $result = pterodactylRequest('/nests/' . intval($nestId) . '/eggs/' . intval($eggId) . '?include=variables');
    if ($result['code'] !== 200 || empty($result['data']['attributes'])) {

        if (!empty($result['data']['data']['attributes'])) {
            $attrs = $result['data']['data']['attributes'];
            $relationships = $result['data']['data']['relationships'] ?? [];
        } else {
            throw new Exception('Не удалось получить шаблон Egg из Pterodactyl');
        }
    } else {
        $attrs = $result['data']['attributes'];
        $relationships = $result['data']['relationships'] ?? [];
    }

    $dockerImage = $attrs['docker_image'] ?? null;
    if (!$dockerImage && !empty($attrs['docker_images']) && is_array($attrs['docker_images'])) {
        $dockerImage = array_values($attrs['docker_images'])[0] ?? null;
    }
    if (!$dockerImage) {
        $dockerImage = 'ghcr.io/pterodactyl/yolks:python_3.11';
    }

    $startup = $attrs['startup'] ?? '';
    if ($startup === '') {
        throw new Exception('Egg не содержит startup-команду');
    }

    $environment = [];

    $included = null;
    if (!empty($result['data']['included']) && is_array($result['data']['included'])) {
        $included = $result['data']['included'];
    } elseif (!empty($result['data']['data']['included']) && is_array($result['data']['data']['included'])) {
        $included = $result['data']['data']['included'];
    }

    if (is_array($included)) {
        foreach ($included as $inc) {
            $vattrs = $inc['attributes'] ?? [];
            $key = $vattrs['env_variable'] ?? null;
            if (!$key) continue;
            $environment[$key] = (string)($vattrs['default_value'] ?? '');
        }
    }

    if (empty($environment)) {
        $varsRel = $relationships['variables']['data'] ?? null;
        if (is_array($varsRel)) {
            foreach ($varsRel as $v) {
                $vattrs = $v['attributes'] ?? [];
                $key = $vattrs['env_variable'] ?? null;
                if (!$key) continue;
                $environment[$key] = (string)($vattrs['default_value'] ?? '');
            }
        }
    }

    return [
        'docker_image' => $dockerImage,
        'startup' => $startup,
        'environment' => $environment
    ];
}
