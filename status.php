<?php
?>
<!DOCTYPE html>

<html lang="ru">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Axion — Статус</title>
<link href="axion-logo.png" rel="icon" type="image/png"/>
<link href="styles.css" rel="stylesheet">
<style>
    body.page-status{ background: var(--dark); }

    .status-wrap{
      padding-top: 120px; /* fixed navbar offset */
      padding-bottom: 70px;
    }

    .status-title{
      font-size: 34px;
      font-weight: 800;
      letter-spacing: -0.02em;
      margin-bottom: 18px;
    }

    .status-lead{
      color: rgba(255,255,255,.7);
      margin-bottom: 22px;
      max-width: 820px;
    }

    .status-banner{
      width: 100%;
      border-radius: 16px;
      padding: 16px 18px;
      background: rgba(34,197,94,.18);
      border: 1px solid rgba(34,197,94,.25);
      box-shadow: 0 18px 60px rgba(0,0,0,.35);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      margin: 18px 0 26px;
    }

    .status-banner .dot{
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background: #22c55e;
      box-shadow: 0 0 18px rgba(34,197,94,.7);
      flex: 0 0 auto;
    }

    .status-banner .text{
      font-weight: 800;
      font-size: 18px;
      color: #dcfce7;
    }

    .status-panel{
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 18px 60px rgba(0,0,0,.35);
      backdrop-filter: blur(12px);
    }

    .status-row{
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 18px 20px;
      border-bottom: 1px solid rgba(255,255,255,.06);
      gap: 12px;
    }

    .status-row:last-child{ border-bottom: none; }

    .row-left{
      display:flex;
      align-items:center;
      gap: 10px;
      min-width: 0;
    }

    .row-left .label{
      font-size: 15px;
      font-weight: 600;
      color: rgba(255,255,255,.88);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .status-pill{
      font-size: 13px;
      font-weight: 800;
      color: #22c55e;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 999px;
      background: rgba(34,197,94,.12);
      border: 1px solid rgba(34,197,94,.22);
      flex: 0 0 auto;
    }

    .status-pill::before{
      content: '';
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #22c55e;
      box-shadow: 0 0 12px rgba(34,197,94,.7);
    }

    .node-grid{
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px;
      margin-top: 18px;
    }

    .node-card{
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 18px;
      padding: 18px 20px;
      box-shadow: 0 18px 60px rgba(0,0,0,.35);
      backdrop-filter: blur(12px);
    }

.flag-inline{
  width: 18px;
  height: 12px;
  border-radius: 2px;
  vertical-align: -2px;
  margin: 0 6px 0 0;
  box-shadow: 0 0 0 1px rgba(255,255,255,.10);
}

.node-head{
  display:flex;
  align-items:flex-start;
  gap: 14px;
  margin-bottom: 12px;
}
.flag{
  width: 34px;
  height: 24px;
  border-radius: 4px;
  margin-top: 2px;
  box-shadow: 0 0 0 1px rgba(255,255,255,.10);
}
.node-info{ display:flex; flex-direction:column; }
.node-name{
  font-weight: 600;
  font-size: 15px;
  line-height: 1.2;
}
.node-card .node-title{
      font-weight: 800;
      font-size: 16px;
      margin-bottom: 12px;
      display:flex;
      align-items:center;
      justify-content: space-between;
      gap: 12px;
    }

    @media (max-width: 860px){
      .node-grid{ grid-template-columns: 1fr; }
      .status-title{ font-size: 28px; }
      .status-wrap{ padding-top: 110px; }
    }

    @media (max-width: 520px){
      .status-row{ padding: 16px 16px; }
      .status-pill{ padding: 7px 10px; }
    }

.node-rows{ display:flex; flex-direction:column; gap:12px; }
.node-row{ display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:12px; }
.node-item{
  background: rgba(255,255,255,.03);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 12px;
  padding: 12px 12px;
}
.node-item span{
  display:block;
  font-size:12px;
  color: rgba(255,255,255,.70);
  line-height:1.2;
}
.node-item b{
  display:block;
  margin-top:4px;
  font-size:16px;
  font-weight:800;
  color: rgba(255,255,255,.92);
  line-height:1.15;
}
.node-item b.ok{ color:#22c55e; }

@media (max-width: 900px){
  .node-grid{ grid-template-columns: 1fr; }
}
@media (max-width: 640px){
  .node-row{ grid-template-columns: 1fr; }
}
</style>
</link></head>
<body class="page-status">
<header class="topbar" id="top">
<div class="container">
<a class="brand" href="index.html">
<img alt="Axion Hosting" class="brand__logo" src="axion-logo.png"/>
<div class="brand__text">
<div class="brand__name">Axion Hosting</div>
<div class="brand__tag">Хостинг для Minecraft и проектов</div>
</div>
</a>
<nav aria-label="Основная навигация" class="nav">
<a class="nav__link" href="index.html#top">Главная</a>
<a class="nav__link" href="index.html#pricing">Тарифы</a>
<a class="nav__link" href="index.html#features">Преимущества</a>
<a class="nav__link" href="index.html#servers">Типы серверов</a>
<a class="nav__link" href="index.html#contact">Связь</a>
</nav>
<div class="topbar__actions">
<span id="authNavItem"><a class="btn btn--ghost" href="./login.php">Войти</a></span>
<button aria-controls="mobileMenu" aria-expanded="false" aria-label="Открыть меню" class="burger" id="burger">
<span></span><span></span><span></span>
</button>
</div>
</div>
<!-- Mobile menu -->
<div class="mobile" hidden="" id="mobileMenu">
<div class="container mobile__inner">
<a class="mobile__link" href="index.html#top">Главная</a>
<a class="mobile__link" href="index.html#pricing">Тарифы</a>
<a class="mobile__link" href="index.html#features">Преимущества</a>
<a class="mobile__link" href="index.html#servers">Типы серверов</a>
<a class="mobile__link" href="index.html#contact">Связь</a>
<div class="mobile__cta">
<span id="authNavItemMobile"><a class="btn btn--ghost btn--wide" href="./login.php">Войти</a></span>
</div>
</div>
</div>
</header>
<main class="status-wrap">
<div class="container">
<div class="status-title">Status Page</div>
<div class="status-lead">Статус ключевых компонентов хостинга. Если что-то не работает — здесь будет видно сразу.</div>
<div class="status-banner">
<span aria-hidden="true" class="dot"></span>
<div class="text">Все сервисы функционируют</div>
</div>
<!-- Rows (как на фото — просто друг под другом) -->
<div class="status-panel">
<div class="status-row">
<div class="row-left"><div class="label">Статус панели</div></div>
<div class="status-pill">Работает</div>
</div>
<div class="status-row">
<div class="row-left"><div class="label">Статус основной страницы</div></div>
<div class="status-pill">Работает</div>
</div>
<div class="status-row">
<div class="row-left"><div class="label">Статус узла <img alt="PL" class="flag-inline" src="assets/flags/pl.svg"/> Польша, Варшава</div></div>
<div class="status-pill">Работает</div>
</div>
<div class="status-row">
<div class="row-left"><div class="label">Статус узла <img alt="FI" class="flag-inline" src="assets/flags/fi.svg"/> Финляндия, Хельсинки</div></div>
<div class="status-pill">Работает</div>
</div>
</div>
<!-- Info blocks for nodes -->
<div class="node-grid">
<div class="node-card">
<div class="node-head">
<img alt="PL" class="flag" src="assets/flags/pl.svg"/>
<div class="node-info">
<div class="node-name">Польша, Варшава</div>


</div>
</div>

<div class="node-rows"><div class="node-row"><div class="node-item"><span>Аптайм (7 дней)</span><b class="ok">99,9%</b></div><div class="node-item"><span>Защита от DDoS</span><b>&gt; 1.0 Tbit/s</b></div><div class="node-item"><span>Процессор</span><b>AMD Ryzen 9 7900</b></div></div><div class="node-row"><div class="node-item"><span>Количество ядер (CPU)</span><b>24 ядра</b></div><div class="node-item"><span>Оперативная память</span><b>128 ГБ ОЗУ</b></div><div class="node-item"><span>Хранилище SSD</span><b>1000 GB</b></div></div></div></div>
<div class="node-card">
<div class="node-head">
<img alt="FI" class="flag" src="assets/flags/fi.svg"/>
<div class="node-info">
<div class="node-name">Финляндия, Хельсинки</div>


</div>
</div>

<div class="node-rows"><div class="node-row"><div class="node-item"><span>Аптайм (7 дней)</span><b class="ok">99,9%</b></div><div class="node-item"><span>Защита от DDoS</span><b>&gt; 1.0 Tbit/s</b></div><div class="node-item"><span>Процессор</span><b>AMD Ryzen 9 7900</b></div></div><div class="node-row"><div class="node-item"><span>Количество ядер (CPU)</span><b>24 ядра</b></div><div class="node-item"><span>Оперативная память</span><b>128 ГБ ОЗУ</b></div><div class="node-item"><span>Хранилище SSD</span><b>1000 GB</b></div></div></div></div>
</div>
</div></main>
<!-- Shared header logic (auth avatar/logout + mobile menu) -->
<script src="script.js"></script>
</body></html>
