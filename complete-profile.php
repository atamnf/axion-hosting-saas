<?php
require_once 'config.php';
startSecureSession();

$pending = $_SESSION['pending_auth'] ?? null;
if (!is_array($pending) || empty($pending['provider']) || empty($pending['provider_id'])) {
    header('Location: login.php');
    exit;
}

$err = '';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function getUsersColumns(PDO $pdo): array {
    $cols = [];
    foreach ($pdo->query('SHOW COLUMNS FROM users') as $row) {
        if (!empty($row['Field'])) $cols[(string)$row['Field']] = true;
    }
    return $cols;
}

function usernameTaken(PDO $pdo, string $username): bool {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(discord_username)=LOWER(?) LIMIT 1');
    $stmt->execute([$username]);
    return (bool)$stmt->fetch();
}

function emailTaken(PDO $pdo, string $email): bool {
    try {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1');
        $stmt->execute([$email]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

$prefUsername = (string)($pending['suggested_username'] ?? '');
$prefFirst = (string)($pending['first_name'] ?? '');
$prefLast = (string)($pending['last_name'] ?? '');
$prefEmail = (string)($pending['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $first = trim((string)($_POST['first_name'] ?? ''));
    $last = trim((string)($_POST['last_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $agree = (string)($_POST['agree_legal'] ?? '');

    if ($username === '' || !preg_match('/^[a-zA-Z0-9_.\-]{3,32}$/', $username)) {
        $err = 'Username должен быть 3–32 символа: буквы, цифры, точка, подчёркивание, дефис.';
    } elseif ($first === '' || $last === '') {
        $err = 'Заполни First Name и Last Name.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Укажи корректный Email.';
    } elseif ($agree !== '1') {
        $err = 'Чтобы продолжить, нужно согласиться с документами.';
    } elseif (!empty(getUsersColumns($pdo)['email']) && emailTaken($pdo, $email)) {
        $err = 'Этот Email уже используется. Укажи другой.';
    } elseif (usernameTaken($pdo, $username)) {
        $err = 'Этот Username уже занят. Выбери другой.';
    }

    if ($err === '') {
        try {
            $cols = getUsersColumns($pdo);

            $inviter = null;
            if (!empty($_COOKIE['axion_inviter']) && is_string($_COOKIE['axion_inviter'])) {
                $cand = trim($_COOKIE['axion_inviter']);
                if ($cand !== '' && preg_match('/^[a-zA-Z0-9_.\-]{3,64}$/', $cand)) {
                    $inviter = $cand;
                }
            }

            $inviterFinal = null;
            if ($inviter !== null && strcasecmp($inviter, $username) !== 0) {
                $chk = $pdo->prepare('SELECT discord_username FROM users WHERE LOWER(discord_username)=LOWER(?) LIMIT 1');
                $chk->execute([$inviter]);
                $row = $chk->fetch();
                if ($row && !empty($row['discord_username'])) $inviterFinal = (string)$row['discord_username'];
            }

            $provider = (string)$pending['provider'];
            $providerId = (string)$pending['provider_id'];
            $avatar = isset($pending['avatar']) ? (string)$pending['avatar'] : null;

            $discordId = $provider === 'telegram' ? ('tg_' . preg_replace('/\D+/', '', $providerId)) : $providerId;

            $registration = registerUserInPterodactyl($username, $email, $first, $last);
            $pterodactylId = null;
            $pterodactylUsername = null;
            $pterodactylPassword = null;
            if (!empty($registration['success'])) {
                $pterodactylId = (int)$registration['user_id'];
                $pterodactylUsername = (string)$registration['username'];
                $pterodactylPassword = (string)$registration['password'];
            }

            $fields = [];
            $place = [];
            $vals = [];

            $fields[] = 'discord_id'; $place[] = '?'; $vals[] = $discordId;
            $fields[] = 'discord_username'; $place[] = '?'; $vals[] = $username;
            if (!empty($cols['first_name'])) { $fields[] = 'first_name'; $place[] = '?'; $vals[] = $first; }
            if (!empty($cols['last_name'])) { $fields[] = 'last_name'; $place[] = '?'; $vals[] = $last; }
            if (!empty($cols['discord_avatar'])) { $fields[] = 'discord_avatar'; $place[] = '?'; $vals[] = $avatar; }
            if (!empty($cols['email'])) { $fields[] = 'email'; $place[] = '?'; $vals[] = $email; }
            if (!empty($cols['inviter'])) { $fields[] = 'inviter'; $place[] = '?'; $vals[] = $inviterFinal; }
            if (!empty($cols['deposits'])) { $fields[] = 'deposits'; $place[] = '0'; }
            if (!empty($cols['balance'])) { $fields[] = 'balance'; $place[] = '0.00'; }
            if (!empty($cols['referral_earned'])) { $fields[] = 'referral_earned'; $place[] = '0.00'; }
            if (!empty($cols['pterodactyl_id']) && $pterodactylId !== null) { $fields[] = 'pterodactyl_id'; $place[] = '?'; $vals[] = $pterodactylId; }
            if (!empty($cols['pterodactyl_username']) && $pterodactylUsername !== null) { $fields[] = 'pterodactyl_username'; $place[] = '?'; $vals[] = $pterodactylUsername; }
            if (!empty($cols['pterodactyl_password']) && $pterodactylPassword !== null) { $fields[] = 'pterodactyl_password'; $place[] = '?'; $vals[] = $pterodactylPassword; }
            if (!empty($cols['created_at'])) { $fields[] = 'created_at'; $place[] = 'NOW()'; }
            if (!empty($cols['last_login'])) { $fields[] = 'last_login'; $place[] = 'NOW()'; }

            $sql = 'INSERT INTO users (' . implode(',', $fields) . ') VALUES (' . implode(',', $place) . ')';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($vals);
            $userId = (int)$pdo->lastInsertId();

            unset($_SESSION['pending_auth']);
            $_SESSION['user_id'] = $userId;
            $_SESSION['discord_username'] = $username;
            header('Location: dashboard.php');
            exit;
        } catch (Throwable $e) {
            $err = 'Ошибка регистрации. Попробуй ещё раз.';
            error_log('PROFILE_COMPLETE_FAIL: ' . $e->getMessage());
        }
    }

    $prefUsername = $username;
    $prefFirst = $first;
    $prefLast = $last;
    $prefEmail = $email;
}

setSecurityHeaders();
?><!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Завершение регистрации</title>
  <link rel="stylesheet" href="styles.css?v=20">
</head>
<body>
  <div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>
  <div class="auth-page">
    <div class="auth-card">
      <div style="display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;">
        <div>
          <div style="font-size:22px;font-weight:700;">Заверши регистрацию</div>
          <div style="opacity:.8;margin-top:4px;">Заполни данные — они будут использоваться в панели.</div>
        </div>
        <a class="btn btn-secondary btn-home" href="index.html">На главную</a>
      </div>

      <?php if ($err !== ''): ?>
        <div class="alert alert-error" style="margin-top:14px;"><?= h($err) ?></div>
      <?php endif; ?>

      <form method="post" id="completeProfileForm" style="margin-top:14px;">
        <div class="form-grid">
          <div>
            <label class="form-label">Username</label>
            <input class="form-input" name="username" value="<?= h($prefUsername) ?>" required>
          </div>
          <div>
            <label class="form-label">Email</label>
            <input class="form-input" type="email" name="email" value="<?= h($prefEmail) ?>" required>
          </div>
          <div>
            <label class="form-label">First Name</label>
            <input class="form-input" name="first_name" value="<?= h($prefFirst) ?>" required>
          </div>
          <div>
            <label class="form-label">Last Name</label>
            <input class="form-input" name="last_name" value="<?= h($prefLast) ?>" required>
          </div>
        </div>

        <label class="check-row" style="margin-top:12px;display:flex;gap:10px;align-items:flex-start;">
          <input id="agree_legal" type="checkbox" name="agree_legal" value="1" required style="margin-top:3px;" />
          <span style="opacity:.88;line-height:1.35;">Я принимаю <a href="/oferta.html" target="_blank" rel="noopener">Публичную оферту</a>, ознакомлен(а) с <a href="/privacy.html" target="_blank" rel="noopener">Политикой конфиденциальности</a>, даю <a href="/consent.html" target="_blank" rel="noopener">согласие на обработку данных</a> и принимаю <a href="/refunds.html" target="_blank" rel="noopener">политику возвратов</a>.</span>
        </label>

        <button class="btn btn-primary" type="submit" style="width:100%;margin-top:14px;">Продолжить</button>
      </form>
    </div>
  </div>

  <script>
    (function () {
      const form = document.getElementById('completeProfileForm');
      const toastContainer = document.getElementById('toastContainer');

      function toast(type, title, msg) {
        if (!toastContainer) return;
        const el = document.createElement('div');
        el.className = 'toast toast--' + (type || 'error');
        el.innerHTML =
          '<span class="toast__dot" aria-hidden="true"></span>' +
          '<div style="min-width:0">' +
            '<p class="toast__title">' + escapeHtml(title || 'Ошибка') + '</p>' +
            (msg ? '<p class="toast__msg">' + escapeHtml(msg) + '</p>' : '') +
          '</div>' +
          '<button class="toast__close" type="button" aria-label="Закрыть">×</button>';

        const closeBtn = el.querySelector('.toast__close');
        closeBtn.addEventListener('click', () => closeToast(el));

        toastContainer.appendChild(el);
        const t = setTimeout(() => closeToast(el), 4500);
        el.dataset.timer = String(t);
      }

      function closeToast(el) {
        try { clearTimeout(Number(el.dataset.timer || '0')); } catch (e) {}
        el.style.animation = 'toastOut .18s ease forwards';
        setTimeout(() => el.remove(), 180);
      }

      function escapeHtml(s) {
        return String(s)
          .replaceAll('&', '&amp;')
          .replaceAll('<', '&lt;')
          .replaceAll('>', '&gt;')
          .replaceAll('"', '&quot;')
          .replaceAll("'", '&#39;');
      }

      function focusField(el) {
        if (!el) return;
        try { el.focus({ preventScroll: true }); } catch (e) { try { el.focus(); } catch (_) {} }
        try { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
      }

      if (form) {
        form.addEventListener('submit', function (e) {
          const username = form.querySelector('input[name="username"]');
          const email = form.querySelector('input[name="email"]');
          const first = form.querySelector('input[name="first_name"]');
          const last = form.querySelector('input[name="last_name"]');
          const agree = document.getElementById('agree_legal');

          const u = (username?.value || '').trim();
          const em = (email?.value || '').trim();
          const fn = (first?.value || '').trim();
          const ln = (last?.value || '').trim();

          if (!u) {
            e.preventDefault();
            toast('error', 'Заполни Username', 'Это имя будет отображаться в панели.');
            focusField(username);
            return;
          }

          if (!/^[a-zA-Z0-9_.\-]{3,32}$/.test(u)) {
            e.preventDefault();
            toast('error', 'Неподходящий Username', '3–32 символа: буквы, цифры, точка, подчёркивание, дефис.');
            focusField(username);
            return;
          }

          if (!em) {
            e.preventDefault();
            toast('error', 'Заполни Email', 'На него придут уведомления и данные, если понадобится.');
            focusField(email);
            return;
          }

          if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) {
            e.preventDefault();
            toast('error', 'Email выглядит неправильно', 'Проверь адрес и попробуй ещё раз.');
            focusField(email);
            return;
          }

          if (!fn || !ln) {
            e.preventDefault();
            toast('error', 'Заполни имя и фамилию', 'First Name и Last Name обязательны.');
            focusField(!fn ? first : last);
            return;
          }

          if (!agree?.checked) {
            e.preventDefault();
            toast('error', 'Нужно принять оферту', 'Поставь галочку, чтобы продолжить.');
            focusField(agree);
            return;
          }
        });
      }

      const serverErr = document.querySelector('.alert-error');
      if (serverErr && serverErr.textContent.trim()) {
        toast('error', 'Не получилось', serverErr.textContent.trim());
      }
    })();
  </script>
</body>
</html>
