<?php
require_once __DIR__ . '/../config.php';

$promoPath = __DIR__ . '/../promo.php';
if (is_file($promoPath)) {
    require_once $promoPath;
}


$secret = (string)envv('ENOT_WEBHOOK_SECRET', '');
if ($secret === '') {
    http_response_code(500);
    exit('Webhook not configured');
}

$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    http_response_code(400);
    exit('Empty body');
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    exit('Bad JSON');
}

$headerSig = '';
if (!empty($_SERVER['HTTP_X_API_SHA256_SIGNATURE'])) {
    $headerSig = (string)$_SERVER['HTTP_X_API_SHA256_SIGNATURE'];
} elseif (!empty($_SERVER['HTTP_X_API_SHA256_SIGNATURE'.''])) {
    $headerSig = (string)$_SERVER['HTTP_X_API_SHA256_SIGNATURE'];
}
$headerSig = trim($headerSig);

if ($headerSig === '') {
    http_response_code(403);
    exit('No signature');
}

$sorted = $payload;
ksort($sorted);
$sortedJson = json_encode($sorted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($sortedJson === false) {
    http_response_code(400);
    exit('Bad payload');
}
$calc = hash_hmac('sha256', $sortedJson, $secret);
if (!hash_equals($calc, $headerSig)) {
    http_response_code(403);
    exit('Invalid signature');
}

$status = (string)($payload['status'] ?? '');
$orderId = (string)($payload['order_id'] ?? '');
$amountStr = (string)($payload['amount'] ?? '');
$invoiceId = (string)($payload['invoice_id'] ?? '');

if ($status !== 'success' || $orderId === '' || $amountStr === '') {
    echo 'OK';
    exit;
}

$txId = (int)$orderId;
if ($txId <= 0) {
    http_response_code(400);
    exit('Bad order');
}

$payAmount = (float)str_replace(',', '.', $amountStr);

global $pdo;

try {
    $hasMetadata = false;
    try {
        $stmtCheck = $pdo->prepare("SHOW COLUMNS FROM transactions LIKE 'metadata'");
        $stmtCheck->execute();
        $hasMetadata = (bool)$stmtCheck->fetch();
    } catch (Throwable $e) {
        $hasMetadata = false;
    }

    $sql = "SELECT id, user_id, amount, status, description, created_at" . ($hasMetadata ? ", metadata" : "") .
        " FROM transactions WHERE id = ? AND type = 'deposit' LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$txId]);
    $tx = $stmt->fetch();

    if (!$tx) {
        echo 'OK';
        exit;
    }

    if ((string)$tx['status'] === 'completed') {
        echo 'OK';
        exit;
    }

    $txAmount = (float)$tx['amount'];
    if (abs($txAmount - $payAmount) > 0.01) {
        http_response_code(400);
        exit('Amount mismatch');
    }

    $userId = (int)$tx['user_id'];

    $pdo->beginTransaction();

    $stmtCheck = $pdo->prepare("SHOW COLUMNS FROM transactions LIKE 'external_id'");
    $stmtCheck->execute();
    $hasExternal = (bool)$stmtCheck->fetch();

    if ($hasExternal && $invoiceId !== '') {
        $stmt = $pdo->prepare("UPDATE transactions SET status = 'completed', external_id = ? WHERE id = ? AND status = 'pending'");
        $stmt->execute([$invoiceId, $txId]);
    } else {
        $stmt = $pdo->prepare("UPDATE transactions SET status = 'completed' WHERE id = ? AND status = 'pending'");
        $stmt->execute([$txId]);
    }

    if ($stmt->rowCount() > 0) {
        $bonus = 0.0;
        try {
            if (function_exists('applyPromoOnDepositCompletion')) {
                $bonus = applyPromoOnDepositCompletion($pdo, $tx, $payAmount);
            }
        } catch (Throwable $e) {
            $bonus = 0.0;
        }

        $credited = $payAmount + $bonus;

        $stmtCheck = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'deposits'");
        $stmtCheck->execute();
        $hasDeposits = (bool)$stmtCheck->fetch();

        if ($hasDeposits) {
            $stmt2 = $pdo->prepare("UPDATE users SET balance = balance + ?, deposits = deposits + 1 WHERE id = ?");
            $stmt2->execute([$credited, $userId]);
        } else {
            $stmt2 = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt2->execute([$credited, $userId]);
        }

        try {
            $stmtU = $pdo->prepare("SELECT id, discord_username, inviter FROM users WHERE id = ? LIMIT 1");
            $stmtU->execute([$userId]);
            $u = $stmtU->fetch(PDO::FETCH_ASSOC);

            $inviterName = isset($u['inviter']) ? trim((string)$u['inviter']) : '';
            $referredName = isset($u['discord_username']) ? (string)$u['discord_username'] : '';

            if ($inviterName !== '' && $referredName !== '' && strcasecmp($inviterName, $referredName) !== 0) {
                $stmtI = $pdo->prepare("SELECT id FROM users WHERE LOWER(discord_username) = LOWER(?) LIMIT 1");
                $stmtI->execute([$inviterName]);
                $inviterId = (int)($stmtI->fetchColumn() ?? 0);

                if ($inviterId > 0) {
                    $refBonus = round($payAmount * 0.10, 2);
                    if ($refBonus > 0) {
                        $marker = 'TX#' . $txId;
                        $stmtC = $pdo->prepare("SELECT 1 FROM transactions WHERE user_id = ? AND type = 'referral_bonus' AND description LIKE ? LIMIT 1");
                        $stmtC->execute([$inviterId, '%' . $marker . '%']);
                        $exists = (bool)$stmtC->fetchColumn();

                        if (!$exists) {
                            $stmtB = $pdo->prepare("UPDATE users SET balance = balance + ?, referral_earned = referral_earned + ? WHERE id = ?");
                            $stmtB->execute([$refBonus, $refBonus, $inviterId]);

                            if ($hasMetadata) {
                                $meta = json_encode([
                                    'source_tx' => $txId,
                                    'source_user_id' => $userId,
                                    'source_user' => $referredName,
                                    'percent' => 10
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                $stmtT = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description, status, metadata) VALUES (?, ?, 'referral_bonus', ?, 'completed', ?)");
                                $stmtT->execute([$inviterId, $refBonus, "Реферальное начисление 10% от пополнения пользователя {$referredName} ({$marker})", $meta]);
                            } else {
                                $stmtT = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description, status) VALUES (?, ?, 'referral_bonus', ?, 'completed')");
                                $stmtT->execute([$inviterId, $refBonus, "Реферальное начисление 10% от пополнения пользователя {$referredName} ({$marker})"]);
                            }
                        }
                    }
                }
            }
        } catch (Throwable $e) {
        }
    }

    $pdo->commit();

    echo 'OK';
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ENOT webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Server error';
}
