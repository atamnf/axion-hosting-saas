<?php
require_once 'config.php';
startSecureSession();
$tgBot = envv('TELEGRAM_BOT_USERNAME', envv('TELEGRAM_BOT_NAME', ''));
$tgBot = preg_replace('/^@/', '', (string)$tgBot);
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Вход | Axion Hosting</title>
  <link rel="stylesheet" href="styles.css?v=20">
  <style>
    body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--dark);}
    .auth-wrap{width:100%;max-width:520px;}
    .auth-card{background:var(--gradient-card);border-radius:24px;box-shadow:var(--shadow-card);padding:28px;}
    .auth-title{font-size:28px;font-weight:800;margin:0 0 8px 0;text-align:center;}
    .auth-sub{margin:0 0 20px 0;text-align:center;opacity:.8;}
    .auth-actions{display:flex;flex-direction:column;gap:14px;align-items:center;}
    .tg-box{width:100%;display:flex;justify-content:center;}
    .discord-btn{width:100%;display:flex;justify-content:center;}
    .discord-btn a{width:100%;text-align:center;}
    .back{margin-top:16px;display:flex;justify-content:center;}
    .back a{width:100%;text-align:center;display:block;padding:12px 18px;border-radius:14px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.04);backdrop-filter:blur(10px);transition:transform .15s ease, background .15s ease, border-color .15s ease; color:#fff !important; text-decoration:none !important;}
    .back a::after{display:none !important;content:none !important;}
    .back a:hover{background:rgba(255,255,255,.07);transform:translateY(-1px);border-color:rgba(255,255,255,.22);text-decoration:none !important;}
    .back a:focus{outline:none;box-shadow:0 0 0 3px rgba(255,215,0,.18);}
  </style>
</head>
<body>
  <div class="auth-wrap">
    <div class="auth-card">
      <h1 class="auth-title">Вход</h1>
      <p class="auth-sub">Выберите способ авторизации</p>
      <?php if (!empty($_GET['tgerr'])): ?>
        <div style="margin:0 0 14px 0;padding:12px 14px;border-radius:14px;border:1px solid rgba(239,68,68,.25);background:rgba(239,68,68,.10);color:rgba(255,255,255,.92);text-align:center;">
          Не удалось войти через Telegram. Открой консоль/логи и пришли ошибку (код: <b><?= e((string)$_GET['tgerr']) ?></b>).
        </div>
      <?php endif; ?>
      <div class="auth-actions">
        <div class="discord-btn">
          <a class="btn btn-primary" href="discord-login.php" style="padding:14px 18px;">Discord</a>
        </div>
        <div class="discord-btn">
          <?php if ($tgBot !== ''): ?>
            <a class="btn btn-secondary" href="telegram-login.php" style="padding:14px 18px; width:100%; text-align:center;">Telegram</a>
          <?php else: ?>
            <div style="text-align:center;opacity:.85;">Telegram не настроен</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="back">
        <a href="index.html">На главную</a>
      </div>
    </div>
  </div>
</body>
</html>
