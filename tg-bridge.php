<?php
header('Content-Type: text/html; charset=utf-8');
$target = '/telegram-callback.php';
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Telegram Auth</title>
  <style>body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#0b0f19;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh} .box{max-width:520px;text-align:center;padding:24px} .muted{opacity:.75}</style>
</head>
<body>
  <div class="box">
    <div style="font-size:20px;font-weight:700;">Завершаем вход через Telegram…</div>
    <div class="muted" style="margin-top:10px;">Если ничего не происходит, вернись назад и попробуй ещё раз.</div>
  </div>
<script>
(function(){
  var h = window.location.hash || '';
  if (h.indexOf('#tgAuthResult=') === 0) {
    var v = h.slice('#tgAuthResult='.length);
    window.location.replace('<?php echo $target; ?>?tgAuthResult=' + encodeURIComponent(v));
    return;
  }
  if (h.indexOf('#') === 0 && h.length > 1) {
    window.location.replace('<?php echo $target; ?>?tgAuthResult=' + encodeURIComponent(h.slice(1)));
    return;
  }
  window.location.replace('login.php?tgerr=missing');
})();
</script>
</body>
</html>
